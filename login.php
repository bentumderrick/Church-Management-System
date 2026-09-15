<?php
session_start();
require_once 'db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$message_type = '';
$active_form = 'login';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// ========== HANDLE LOGIN ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $active_form = 'login';
    $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $message = 'Please enter your username/email and password.';
        $message_type = 'error';
    } else {
        $sql = "SELECT u.id, u.username, u.email, u.password, u.full_name, u.phone, u.address,
                       u.role, u.is_church_admin, u.membership_status, u.church_id,
                       c.church_name, c.church_username
                FROM users u
                LEFT JOIN churches c ON c.id = u.church_id
                WHERE u.username = ? OR u.email = ?
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $message = 'Database error: ' . mysqli_error($conn);
            $message_type = 'error';
        } else {
            mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
            if (!mysqli_stmt_execute($stmt)) {
                $message = 'Unable to process login.';
                $message_type = 'error';
            } else {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) === 0) {
                    $message = 'User not found.';
                    $message_type = 'error';
                } else {
                    mysqli_stmt_bind_result($stmt, $user_id, $username, $email, $password_hash,
                        $full_name, $phone, $address, $role, $is_church_admin,
                        $membership_status, $church_id, $church_name, $church_username);
                    mysqli_stmt_fetch($stmt);
                    if ($membership_status !== 'active') {
                        $message = 'Your account is not active. Please contact your church administrator.';
                        $message_type = 'error';
                    } elseif (!password_verify($password, $password_hash)) {
                        $message = 'Invalid password.';
                        $message_type = 'error';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['phone'] = $phone;
                        $_SESSION['address'] = $address;
                        $_SESSION['role'] = $role;
                        $_SESSION['is_church_admin'] = (int)$is_church_admin;
                        $_SESSION['church_id'] = $church_id;
                        $_SESSION['church_name'] = $church_name;
                        $_SESSION['church_username'] = $church_username;

                        $update = mysqli_prepare($conn, "UPDATE users SET last_active = NOW() WHERE id = ?");
                        if ($update) {
                            mysqli_stmt_bind_param($update, "i", $user_id);
                            mysqli_stmt_execute($update);
                            mysqli_stmt_close($update);
                        }
                        header('Location: index.php');
                        exit();
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ========== HANDLE REGISTRATION ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $active_form = 'register';
    $church_name = trim($_POST['church_name'] ?? '');
    $church_username = trim($_POST['church_username'] ?? '');
    $church_description = trim($_POST['church_description'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($church_name === '' || $church_username === '' || $full_name === '' ||
        $username === '' || $email === '' || $password === '') {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $message_type = 'error';
    } else {
        // Check duplicate user
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        if (!$check) {
            $message = 'Database error checking user.';
            $message_type = 'error';
        } else {
            mysqli_stmt_bind_param($check, "ss", $username, $email);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);
            if (mysqli_stmt_num_rows($check) > 0) {
                $message = 'That username or email is already registered.';
                $message_type = 'error';
                mysqli_stmt_close($check);
            } else {
                mysqli_stmt_close($check);
                // Check church username
                $church_check = mysqli_prepare($conn, "SELECT id FROM churches WHERE church_username = ? LIMIT 1");
                if (!$church_check) {
                    $message = 'Database error checking church username.';
                    $message_type = 'error';
                } else {
                    mysqli_stmt_bind_param($church_check, "s", $church_username);
                    mysqli_stmt_execute($church_check);
                    mysqli_stmt_store_result($church_check);
                    if (mysqli_stmt_num_rows($church_check) > 0) {
                        $message = 'That church username is already taken.';
                        $message_type = 'error';
                        mysqli_stmt_close($church_check);
                    } else {
                        mysqli_stmt_close($church_check);
                        mysqli_begin_transaction($conn);
                        try {
                            // Insert church
                            $stmt1 = mysqli_prepare($conn,
                                "INSERT INTO churches (church_username, church_name, church_description, is_public, created_by)
                                 VALUES (?, ?, ?, 1, NULL)");
                            if (!$stmt1) throw new Exception('Church prepare failed');
                            mysqli_stmt_bind_param($stmt1, "sss", $church_username, $church_name, $church_description);
                            if (!mysqli_stmt_execute($stmt1)) throw new Exception('Church insert failed');
                            $church_id = mysqli_insert_id($conn);
                            mysqli_stmt_close($stmt1);

                            // Insert user
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            $stmt2 = mysqli_prepare($conn,
                                "INSERT INTO users (username, email, password, full_name, phone, address,
                                 role, is_church_admin, membership_status, church_id)
                                 VALUES (?, ?, ?, ?, ?, ?, 'admin', 1, 'active', ?)");
                            if (!$stmt2) throw new Exception('User prepare failed');
                            mysqli_stmt_bind_param($stmt2, "ssssssi", $username, $email, $hashed, $full_name, $phone, $address, $church_id);
                            if (!mysqli_stmt_execute($stmt2)) throw new Exception('User insert failed');
                            $user_id = mysqli_insert_id($conn);
                            mysqli_stmt_close($stmt2);

                            // Update church owner
                            $stmt3 = mysqli_prepare($conn, "UPDATE churches SET created_by = ? WHERE id = ?");
                            if (!$stmt3) throw new Exception('Church owner prepare failed');
                            mysqli_stmt_bind_param($stmt3, "ii", $user_id, $church_id);
                            if (!mysqli_stmt_execute($stmt3)) throw new Exception('Church owner update failed');
                            mysqli_stmt_close($stmt3);

                            mysqli_commit($conn);
                            $message = 'Church account created successfully. You can now sign in.';
                            $message_type = 'success';
                            $active_form = 'login';
                            $_POST = [];
                        } catch (Exception $e) {
                            mysqli_rollback($conn);
                            $message = 'Registration failed. Please try again.';
                            $message_type = 'error';
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Management System</title>
    <link rel="shortcut icon" href="images/logo.jpg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Poppins',sans-serif;
            background:#0a1520;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:20px;
        }
        .auth-wrapper {
            width:100%;
            max-width:1000px;
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:40px;
            background:rgba(255,255,255,0.02);
            border-radius:20px;
            padding:40px;
            border:1px solid rgba(255,255,255,0.05);
            box-shadow:0 20px 60px rgba(0,0,0,0.3);
        }
        .brand-section {
            display:flex;
            flex-direction:column;
            justify-content:center;
            padding-right:30px;
        }
        .brand-section .logo {
            display:flex;
            align-items:center;
            gap:15px;
            margin-bottom:25px;
        }
        .brand-section .logo img { width:60px; height:60px; border-radius:50%; object-fit:cover; }
        .brand-section .logo h1 { color:orange; font-size:2rem; font-weight:700; }
        .brand-section .logo span { color:white; font-size:0.9rem; font-weight:300; display:block; }
        .brand-section h2 {
            color:white;
            font-size:1.8rem;
            font-weight:600;
            margin-bottom:15px;
            line-height:1.3;
        }
        .brand-section h2 i { color:orange; }
        .brand-section p {
            color:#adb5bd;
            font-size:1rem;
            line-height:1.8;
            margin-bottom:25px;
        }
        .brand-section .features { list-style:none; }
        .brand-section .features li {
            color:#e9ecef;
            padding:8px 0;
            display:flex;
            align-items:center;
            gap:12px;
            font-size:0.95rem;
        }
        .brand-section .features li i { color:#2ecc71; font-size:1.1rem; width:24px; }

        .forms-section {
            background:rgba(255,255,255,0.03);
            border-radius:16px;
            padding:35px;
            border:1px solid rgba(255,255,255,0.06);
        }
        .form-toggle {
            display:flex;
            background:rgba(255,255,255,0.05);
            border-radius:10px;
            padding:4px;
            margin-bottom:25px;
            border:1px solid rgba(255,255,255,0.06);
        }
        .form-toggle button {
            flex:1;
            padding:10px 20px;
            border:none;
            background:transparent;
            color:#adb5bd;
            font-family:"Poppins",sans-serif;
            font-weight:500;
            font-size:0.95rem;
            cursor:pointer;
            border-radius:8px;
            transition:all 0.3s ease;
        }
        .form-toggle button.active { background:orange; color:#0c1a2b; }
        .form-toggle button:hover:not(.active) { color:white; }
        .form-container { display:none; }
        .form-container.active { display:block; animation:fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        .form-container h2 { color:white; font-size:1.4rem; margin-bottom:5px; }
        .form-container .subtitle { color:#adb5bd; font-size:0.9rem; margin-bottom:20px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .input-box { position:relative; margin-bottom:14px; }
        .input-box.full-width { grid-column:1 / -1; }
        .input-box label { display:block; color:#adb5bd; font-size:0.8rem; font-weight:500; margin-bottom:4px; }
        .input-box label .required { color:#e74c3c; font-size:1.2rem; line-height:0; margin-left:2px; }
        .input-box input,
        .input-box textarea {
            width:100%;
            padding:11px 15px;
            background:rgba(255,255,255,0.06);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:8px;
            color:white;
            font-family:"Poppins",sans-serif;
            font-size:0.95rem;
            transition:all 0.3s ease;
        }
        .input-box input:focus,
        .input-box textarea:focus {
            outline:none;
            border-color:orange;
            background:rgba(255,255,255,0.08);
            box-shadow:0 0 0 3px rgba(255,165,0,0.1);
        }
        .input-box input::placeholder { color:rgba(255,255,255,0.25); }
        .input-box textarea { resize:vertical; min-height:60px; }
        .form-actions { display:flex; flex-direction:column; gap:12px; margin-top:20px; }
        .btn {
            padding:12px 30px;
            border:none;
            border-radius:8px;
            font-weight:600;
            font-size:1rem;
            cursor:pointer;
            transition:all 0.3s ease;
            font-family:"Poppins",sans-serif;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
        }
        .btn-primary { background:orange; color:#0c1a2b; }
        .btn-primary:hover { background:#ff8c00; transform:translateY(-2px); box-shadow:0 5px 20px rgba(255,165,0,0.3); }
        .btn-secondary { background:rgba(255,255,255,0.06); color:white; border:1px solid rgba(255,255,255,0.1); }
        .btn-secondary:hover { background:rgba(255,255,255,0.1); }
        .message {
            padding:12px 16px;
            border-radius:8px;
            margin-bottom:15px;
            display:flex;
            align-items:center;
            gap:10px;
            font-size:0.9rem;
        }
        .message.error { background:rgba(231,76,60,0.12); border:1px solid rgba(231,76,60,0.25); color:#e74c3c; }
        .message.success { background:rgba(46,204,113,0.12); border:1px solid rgba(46,204,113,0.25); color:#2ecc71; }
        .skip-login {
            text-align:center;
            margin-top:25px;
            padding-top:20px;
            border-top:1px solid rgba(255,255,255,0.08);
        }
        .skip-login a {
            display:inline-block;
            padding:10px 20px;
            background:rgba(255,255,255,0.05);
            color:orange;
            text-decoration:none;
            border-radius:8px;
            font-weight:500;
            transition:all 0.3s;
        }
        .skip-login a:hover { background:rgba(255,165,0,0.1); }

        /* Password toggle styles */
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 40px; /* leave room for the eye */
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #adb5bd;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0;
            line-height: 1;
        }
        .password-toggle:hover {
            color: orange;
        }

        @media(max-width:800px) {
            .auth-wrapper { grid-template-columns:1fr; }
            .brand-section { text-align:center; padding-right:0; }
            .brand-section .logo { justify-content:center; }
            .brand-section .features { display:inline-block; text-align:left; }
        }
        @media(max-width:500px) {
            .form-row { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="brand-section">
        <div class="logo">
            <img src="images/logo.jpg" alt="Church Logo" onerror="this.src='https://via.placeholder.com/60x60?text=LOGO'">
            <div>
                <h1>CM</h1>
                <span>Church Management</span>
            </div>
        </div>
        <h2><i class="fas fa-users"></i> Manage Your Church<br>With Ease</h2>
        <p>A complete church management system for tracking members, attendance, offerings, and more. Designed for churches of all sizes.</p>
        <ul class="features">
            <li><i class="fas fa-check-circle"></i> Member Registration & Profiles</li>
            <li><i class="fas fa-check-circle"></i> Attendance Tracking</li>
            <li><i class="fas fa-check-circle"></i> Offering Management</li>
            <li><i class="fas fa-check-circle"></i> Hymn Library with Player</li>
            <li><i class="fas fa-check-circle"></i> Role-Based Access Control</li>
            <li><i class="fas fa-check-circle"></i> Reports & Analytics</li>
        </ul>
    </div>

    <div class="forms-section">
        <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="form-toggle">
            <button class="<?php echo $active_form === 'login' ? 'active' : ''; ?>" onclick="showForm('login')">Login</button>
            <button class="<?php echo $active_form === 'register' ? 'active' : ''; ?>" onclick="showForm('register')">Register</button>
        </div>

        <!-- LOGIN FORM -->
        <div class="form-container <?php echo $active_form === 'login' ? 'active' : ''; ?>" id="loginForm">
            <h2>Welcome Back!</h2>
            <p class="subtitle">Login to access your church management dashboard.</p>
            <form method="POST">
                <div class="input-box">
                    <label>Username or Email <span class="required">*</span></label>
                    <input type="text" name="username" required placeholder="Enter username or email">
                </div>
                <div class="input-box">
                    <label>Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="loginPassword" required placeholder="Enter password">
                        <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="login" class="btn btn-primary">Sign In</button>
                </div>
            </form>
        </div>

        <!-- REGISTER FORM -->
        <div class="form-container <?php echo $active_form === 'register' ? 'active' : ''; ?>" id="registerForm">
            <h2>Create Your Church Account</h2>
            <p class="subtitle">Register your church and start managing members, attendance, and more.</p>
            <form method="POST">
                <div class="form-row">
                    <div class="input-box">
                        <label>Church Name <span class="required">*</span></label>
                        <input type="text" name="church_name" required placeholder="e.g., My Church">
                    </div>
                    <div class="input-box">
                        <label>Church Username <span class="required">*</span></label>
                        <input type="text" name="church_username" required placeholder="e.g., mychurch">
                    </div>
                </div>
                <div class="input-box full-width">
                    <label>Church Description</label>
                    <textarea name="church_description" placeholder="Brief description of your church..."></textarea>
                </div>
                <hr style="border-color:rgba(255,255,255,0.08);margin:15px 0;">
                <div class="form-row">
                    <div class="input-box">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" required placeholder="Enter your full name">
                    </div>
                    <div class="input-box">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" required placeholder="Choose username">
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-box">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" required placeholder="Enter email">
                    </div>
                    <div class="input-box">
                        <label>Phone</label>
                        <input type="text" name="phone" placeholder="Enter phone number">
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-box">
                        <label>Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="regPassword" required placeholder="Min 8 characters">
                            <button type="button" class="password-toggle" onclick="togglePassword('regPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="input-box">
                        <label>Confirm Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="regConfirmPassword" required placeholder="Confirm password">
                            <button type="button" class="password-toggle" onclick="togglePassword('regConfirmPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="input-box full-width">
                    <label>Address</label>
                    <textarea name="address" placeholder="Your church address"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="register" class="btn btn-primary">Create Church & Register</button>
                </div>
            </form>
        </div>

        <!-- SKIP LOGIN / PUBLIC MUSIC ACCESS -->
        <div class="skip-login">
            <a href="index.php?page=music">
                <i class="fas fa-music"></i> Skip Login – Browse Music as Visitor
            </a>
        </div>
    </div>
</div>

<script>
function showForm(form) {
    document.querySelectorAll('.form-container').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.form-toggle button').forEach(el => el.classList.remove('active'));
    if (form === 'register') {
        document.getElementById('registerForm').classList.add('active');
        document.querySelector('.form-toggle button:last-child').classList.add('active');
    } else {
        document.getElementById('loginForm').classList.add('active');
        document.querySelector('.form-toggle button:first-child').classList.add('active');
    }
}

function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}
</script>
</body>
</html>
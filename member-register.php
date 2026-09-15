<?php
// member-register.php – Fixed version (no member_profiles, CSRF, church_id, role whitelist)
session_start();
require_once 'db.php';

$error = '';
$success = '';
$is_admin = false;

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check if logged in as admin
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $check = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ?");
    if ($check) {
        mysqli_stmt_bind_param($check, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($check);
        $result = mysqli_stmt_get_result($check);
        $user = mysqli_fetch_assoc($result);
        $is_admin = in_array($user['role'] ?? '', ['developer', 'admin']);
        mysqli_stmt_close($check);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'member');
        $address = trim($_POST['address'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $hometown = trim($_POST['hometown'] ?? '');
        $residence = trim($_POST['residence'] ?? '');
        $church_username_input = trim($_POST['church_username'] ?? '');

        // Basic validation
        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (!$is_admin && empty($church_username_input)) {
            $error = 'Please enter your church\'s username to join it.';
        } else {
            // Check for existing username/email
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = 'Username or email already exists.';
                }
                mysqli_stmt_close($check_stmt);
            } else {
                $error = 'Database error checking existing user.';
            }

            if (empty($error)) {
                // Resolve church_id
                $church_id = 0;
                if ($is_admin) {
                    // Admin creates user for own church
                    $admin_church_stmt = mysqli_prepare($conn, "SELECT church_id FROM users WHERE id = ?");
                    if ($admin_church_stmt) {
                        mysqli_stmt_bind_param($admin_church_stmt, "i", $_SESSION['user_id']);
                        mysqli_stmt_execute($admin_church_stmt);
                        $admin_church_result = mysqli_stmt_get_result($admin_church_stmt);
                        $admin_church_row = mysqli_fetch_assoc($admin_church_result);
                        $church_id = (int)($admin_church_row['church_id'] ?? 0);
                        mysqli_stmt_close($admin_church_stmt);
                    } else {
                        $error = 'Database error fetching your church.';
                    }

                    if ($church_id <= 0) {
                        $error = 'Your own account has no church assigned, so a new user cannot be created under it.';
                    }
                } else {
                    // Self-registration: look up church by username
                    $church_stmt = mysqli_prepare($conn, "SELECT id FROM churches WHERE church_username = ?");
                    if ($church_stmt) {
                        mysqli_stmt_bind_param($church_stmt, "s", $church_username_input);
                        mysqli_stmt_execute($church_stmt);
                        $church_result = mysqli_stmt_get_result($church_stmt);
                        $church_row = mysqli_fetch_assoc($church_result);
                        $church_id = (int)($church_row['id'] ?? 0);
                        mysqli_stmt_close($church_stmt);
                    } else {
                        $error = 'Database error looking up church.';
                    }

                    if ($church_id <= 0) {
                        $error = 'No church found with that username. Check it and try again.';
                    }
                }

                if (empty($error) && $church_id > 0) {
                    // Role whitelist (both branches)
                    $allowed_roles = ['member', 'usher', 'pastor', 'admin'];
                    if (!in_array($role, $allowed_roles, true)) {
                        $role = 'member';
                    }

                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $membership_status = $is_admin ? 'active' : 'pending';
                    $is_verified = $is_admin ? 1 : 0;

                    // Insert user (no member_profiles table)
                    $stmt = mysqli_prepare($conn,
                        "INSERT INTO users (username, email, password, role, membership_status, is_verified, full_name, phone, address, church_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "sssssisssi", $username, $email, $hashed, $role, $membership_status, $is_verified, $full_name, $phone, $address, $church_id);
                        if (mysqli_stmt_execute($stmt)) {
                            if ($is_admin) {
                                $success = 'User created successfully! Role: ' . ucfirst($role);
                            } else {
                                $success = 'Registration successful! Your account is pending admin approval.';
                            }
                        } else {
                            $error = 'Error creating account: ' . mysqli_stmt_error($stmt);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = 'Database error preparing insert.';
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
    <title><?php echo $is_admin ? 'Create User' : 'Register'; ?> - Church System</title>
    <link rel="stylesheet" href="login-styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 800px; }
        .wrapper { padding: 40px; }
        .role-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .role-option {
            padding: 15px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.03);
        }
        .role-option:hover { border-color: rgba(255,165,0,0.3); }
        .role-option.selected { border-color: orange; background: rgba(255,165,0,0.1); }
        .role-option i { font-size: 2rem; display: block; margin-bottom: 8px; }
        .role-option .label { color: white; font-size: 0.9rem; }
        .role-option .desc { color: #adb5bd; font-size: 0.7rem; margin-top: 5px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .input-box { margin-bottom: 15px; }
        .input-box input, .input-box select, .input-box textarea {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            color: white;
            font-family: "Poppins", sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .input-box input:focus, .input-box select:focus, .input-box textarea:focus {
            outline: none;
            border-color: orange;
        }
        .input-box textarea { resize: vertical; min-height: 60px; }
        .input-box label { display: block; margin-bottom: 5px; color: #adb5bd; font-size: 0.9rem; }
        .input-box label .required { color: #e74c3c; }
        .input-box .hint { color: #6c757d; font-size: 0.75rem; margin-top: 4px; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: orange;
            color: #0c1a2b;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            width: 100%;
            font-family: "Poppins", sans-serif;
        }
        .btn:hover { background: #ff8c00; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255,165,0,0.3); }
        .btn-secondary { background: rgba(255,255,255,0.1); color: white; }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); transform: none; box-shadow: none; }
        .error-message, .success-message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error-message { background: rgba(231,76,60,0.12); border: 1px solid rgba(231,76,60,0.3); color: #e74c3c; }
        .success-message { background: rgba(46,204,113,0.12); border: 1px solid rgba(46,204,113,0.3); color: #2ecc71; }
        .register-link { text-align: center; margin-top: 20px; color: #adb5bd; }
        .register-link a { color: orange; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }
        @media (max-width: 768px) {
            .wrapper { padding: 25px; }
            .form-row { grid-template-columns: 1fr; }
            .role-selector { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="wrapper">
            <h1 style="text-align:center;color:white;margin-bottom:10px;">
                <i class="fas fa-user-plus" style="color:orange;"></i> 
                <?php echo $is_admin ? 'Create New User' : 'Register for Church System'; ?>
            </h1>
            <p style="text-align:center;color:#adb5bd;margin-bottom:25px;">
                <?php echo $is_admin ? 'Add a new member to the church management system.' : 'Sign up to access church resources.'; ?>
            </p>
            
            <?php if ($error): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!$success || $is_admin): ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <?php if ($is_admin): ?>
                    <input type="hidden" name="redirect" value="1">
                    <div class="input-box">
                        <label>Select Role <span class="required">*</span></label>
                        <div class="role-selector" id="roleSelector">
                            <div class="role-option selected" data-role="member" onclick="selectRole('member')">
                                <i class="fas fa-user"></i>
                                <div class="label">Member</div>
                                <div class="desc">View own profile</div>
                            </div>
                            <div class="role-option" data-role="usher" onclick="selectRole('usher')">
                                <i class="fas fa-hand-paper"></i>
                                <div class="label">Usher/Secretary</div>
                                <div class="desc">Mark attendance, view members</div>
                            </div>
                            <div class="role-option" data-role="pastor" onclick="selectRole('pastor')">
                                <i class="fas fa-church"></i>
                                <div class="label">Pastor</div>
                                <div class="desc">View all, upload hymns</div>
                            </div>
                            <div class="role-option" data-role="admin" onclick="selectRole('admin')">
                                <i class="fas fa-user-shield"></i>
                                <div class="label">Admin</div>
                                <div class="desc">Full access, manage users</div>
                            </div>
                        </div>
                        <input type="hidden" name="role" id="selectedRole" value="member">
                    </div>
                <?php else: ?>
                    <div class="input-box">
                        <label>Church Username <span class="required">*</span></label>
                        <input type="text" name="church_username" required placeholder="e.g., mychurch"
                               value="<?php echo htmlspecialchars($_POST['church_username'] ?? ''); ?>">
                        <div class="hint">Ask your church admin for this if you don't know it.</div>
                    </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" required placeholder="Enter full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>
                    <div class="input-box">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" required placeholder="Choose username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" required placeholder="Enter email address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="input-box">
                        <label>Phone</label>
                        <input type="tel" name="phone" placeholder="Enter phone number" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" required placeholder="Min 6 characters">
                    </div>
                    <div class="input-box">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" required placeholder="Confirm password">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                    </div>
                    <div class="input-box">
                        <label>Occupation</label>
                        <input type="text" name="occupation" placeholder="Enter occupation" value="<?php echo htmlspecialchars($_POST['occupation'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>Hometown</label>
                        <input type="text" name="hometown" placeholder="Enter hometown" value="<?php echo htmlspecialchars($_POST['hometown'] ?? ''); ?>">
                    </div>
                    <div class="input-box">
                        <label>Current Residence</label>
                        <input type="text" name="residence" placeholder="Enter residence" value="<?php echo htmlspecialchars($_POST['residence'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="input-box">
                    <label>Address</label>
                    <textarea name="address" placeholder="Enter full address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-<?php echo $is_admin ? 'user-plus' : 'user-check'; ?>"></i> 
                    <?php echo $is_admin ? 'Create User' : 'Register'; ?>
                </button>
            </form>
            <?php endif; ?>
            
            <?php if (!$is_admin && !$success): ?>
            <div class="register-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
            <?php endif; ?>
            
            <?php if ($is_admin): ?>
            <div class="register-link">
                <p><a href="member-management.php"><i class="fas fa-arrow-left"></i> Back to User Management</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function selectRole(role) {
            document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
            document.querySelector(`.role-option[data-role="${role}"]`).classList.add('selected');
            document.getElementById('selectedRole').value = role;
        }
    </script>
</body>
</html>
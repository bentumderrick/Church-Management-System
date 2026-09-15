<?php
// pages/add-user.php – SPA partial
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Please login to view this page.</div>';
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);

// Get admin's church_id and role
$admin_stmt = mysqli_prepare($conn, "SELECT church_id, role FROM users WHERE id = ?");
if (!$admin_stmt) {
    echo '<div class="error-message">Database error: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    exit();
}
mysqli_stmt_bind_param($admin_stmt, "i", $user_id);
mysqli_stmt_execute($admin_stmt);
mysqli_stmt_store_result($admin_stmt);
mysqli_stmt_bind_result($admin_stmt, $church_id, $admin_role);

if (!mysqli_stmt_fetch($admin_stmt)) {
    echo '<div class="error-message">User not found.</div>';
    mysqli_stmt_close($admin_stmt);
    exit();
}
mysqli_stmt_close($admin_stmt);

$church_id = (int)$church_id;
$admin_role = (string)$admin_role;

// Only admin/developer
if (!in_array($admin_role, ['developer', 'admin'], true)) {
    echo '<div class="error-message">You do not have permission to view this page.</div>';
    exit();
}

if ($church_id <= 0) {
    echo '<div class="error-message">Your account has no church assigned. Cannot create users.</div>';
    exit();
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $username   = trim($_POST['username'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';
        $full_name  = trim($_POST['full_name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $role       = trim($_POST['role'] ?? 'member');
        $address    = trim($_POST['address'] ?? '');

        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Duplicate check
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
                if (mysqli_stmt_execute($check_stmt)) {
                    mysqli_stmt_store_result($check_stmt);
                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $error = 'Username or email already exists.';
                    }
                } else {
                    $error = 'Database error checking existing user.';
                }
                mysqli_stmt_close($check_stmt);
            } else {
                $error = 'Database error preparing check.';
            }

            if (empty($error)) {
                $allowed_roles = ['member', 'usher', 'pastor', 'admin'];
                if (!in_array($role, $allowed_roles, true)) {
                    $role = 'member';
                }

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $is_church_admin = ($role === 'admin') ? 1 : 0;

                $stmt = mysqli_prepare($conn,
                    "INSERT INTO users 
                     (username, email, password, full_name, phone, address, role, is_church_admin, membership_status, church_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)"
                );

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssssssii",
                        $username, $email, $hashed, $full_name, $phone, $address, $role, $is_church_admin, $church_id
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $success = 'User "' . htmlspecialchars($username) . '" created successfully!';
                        // Regenerate CSRF after success
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $csrf_token = $_SESSION['csrf_token'];
                    } else {
                        $error = 'Failed to create user: ' . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = 'Database error preparing insert: ' . mysqli_error($conn);
                }
            }
        }
    }
}
?>

<style>
    .password-wrapper {
        position: relative;
    }
    .password-wrapper input {
        padding-right: 45px !important;
    }
    .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #adb5bd;
        cursor: pointer;
        font-size: 1.1rem;
        padding: 5px;
        transition: color 0.2s;
    }
    .toggle-password:hover {
        color: orange;
    }
</style>

<div class="header-section">
    <h1><i class="fas fa-user-plus"></i> Add User</h1>
    <p class="subtitle">Create a new user account for your church</p>
    <a href="#" class="back-btn" onclick="loadPage('dashboard'); return false;">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<?php if ($error): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <div><h3>Error</h3><p><?php echo htmlspecialchars($error); ?></p></div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <div><h3>Success</h3><p><?php echo $success; ?></p></div>
    </div>
<?php endif; ?>

<div class="content-card" style="max-width: 800px; margin: 0 auto;">
    <form method="POST" id="addUserForm" action="pages/add-user.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <div class="form-group">
            <label>Role <span style="color:#e74c3c;">*</span></label>
            <select name="role" class="form-control" required>
                <option value="member">Member</option>
                <option value="usher">Usher / Secretary</option>
                <option value="pastor">Pastor</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Full Name <span style="color:#e74c3c;">*</span></label>
                <input type="text" name="full_name" required class="form-control"
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Username <span style="color:#e74c3c;">*</span></label>
                <input type="text" name="username" required class="form-control"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Email <span style="color:#e74c3c;">*</span></label>
                <input type="email" name="email" required class="form-control"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Password <span style="color:#e74c3c;">*</span></label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required minlength="6" class="form-control" placeholder="Min 6 characters">
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label>Confirm Password <span style="color:#e74c3c;">*</span></label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" required class="form-control">
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Address</label>
            <textarea name="address" rows="2" class="form-control"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="submit-btn" id="createUserBtn">
            <i class="fas fa-save"></i> Create User
        </button>
    </form>
</div>

<script>
// Toggle password visibility
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// AJAX form submit (keeps SPA working)
(function() {
    const form = document.getElementById('addUserForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const btn = document.getElementById('createUserBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

        const formData = new FormData(form);

        fetch('pages/add-user.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.text())
        .then(html => {
            document.getElementById('pageContent').innerHTML = html;

            // Re-execute scripts from the new content
            document.getElementById('pageContent').querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                if (oldScript.src) {
                    newScript.src = oldScript.src;
                } else {
                    newScript.textContent = oldScript.textContent;
                }
                oldScript.replaceWith(newScript);
            });
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Something went wrong. Please try again.');
        });
    });
})();
</script>
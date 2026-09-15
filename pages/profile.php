<?php
// pages/profile.php - User profile management (AJAX fragment)
require_once __DIR__ . '/../init.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($email)) {
        $error = 'Username and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssi", $username, $email, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Profile updated successfully!';
            $user['username'] = $username;
            $user['email'] = $email;
        } else {
            $error = 'Failed to update profile.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $error = 'All password fields are required.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $hashed, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Password changed successfully!';
        } else {
            $error = 'Failed to change password.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<div class="header-section">
    <h1><i class="fas fa-user-cog"></i> My Profile</h1>
    <p class="subtitle">Manage your account settings</p>
    <a href="javascript:void(0)" class="back-btn" onclick="loadPage('dashboard')">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<div id="profileMessages">
    <?php if ($error): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
</div>

<div class="profile-container">
    <!-- Profile Information -->
    <div class="profile-card">
        <h2><i class="fas fa-user"></i> Profile Information</h2>
        <div style="margin-bottom: 20px;">
            <span class="member-id-badge"><i class="fas fa-id-card"></i> User ID: <?php echo $user['id']; ?></span>
            <span class="member-id-badge" style="margin-left: 10px; background: rgba(46,204,113,0.15); color: #2ecc71;">
                <i class="fas fa-calendar-plus"></i> Joined: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
            </span>
        </div>
        <form method="POST" id="updateProfileForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>
            <button type="submit" name="update_profile" value="1" class="submit-btn" id="updateProfileBtn">
                <i class="fas fa-save"></i> Update Profile
            </button>
        </form>
    </div>

    <!-- Change Password -->
    <div class="profile-card">
        <h2><i class="fas fa-lock"></i> Change Password</h2>
        <form method="POST" id="changePasswordForm">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
            </div>
            <button type="submit" name="change_password" value="1" class="submit-btn" id="changePasswordBtn">
                <i class="fas fa-key"></i> Change Password
            </button>
        </form>
    </div>
</div>

<script>
function initProfilePage() {
    const contentEl = document.getElementById('pageContent');

    function submitProfileForm(form, btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner" style="display:inline-block;width:16px;height:16px;border:2px solid rgba(12,26,43,0.3);border-radius:50%;border-top-color:#0c1a2b;animation:spin 0.7s linear infinite;"></span> Saving...';
        btn.disabled = true;

        const formData = new FormData(form);

        fetch('pages/profile.php', {
            method: 'POST',
            body: formData
        })
            .then(function(response) {
                if (!response.ok) throw new Error('Request failed');
                return response.text();
            })
            .then(function(html) {
                if (contentEl) {
                    contentEl.innerHTML = html;
                    // innerHTML doesn't execute <script> tags — re-create them
                    contentEl.querySelectorAll('script').forEach(function(oldScript) {
                        const newScript = document.createElement('script');
                        if (oldScript.src) {
                            newScript.src = oldScript.src;
                        } else {
                            newScript.textContent = oldScript.textContent;
                        }
                        oldScript.replaceWith(newScript);
                    });
                }
            })
            .catch(function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Something went wrong saving your changes. Please try again.');
            });
    }

    const updateForm = document.getElementById('updateProfileForm');
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitProfileForm(updateForm, document.getElementById('updateProfileBtn'));
        });
    }

    const passwordForm = document.getElementById('changePasswordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitProfileForm(passwordForm, document.getElementById('changePasswordBtn'));
        });
    }
}

initProfilePage();
</script>

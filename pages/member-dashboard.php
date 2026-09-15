<?php
// pages/member-dashboard.php – SPA partial with avatar upload + change password
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Please login to view this page.</div>';
    exit();
}

// Important: always use absolute paths based on this file's location
define('UPLOAD_BASE', __DIR__ . '/../uploads/');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Get user + church data
$query = "SELECT u.*, c.church_name, c.church_username 
          FROM users u 
          JOIN churches c ON u.church_id = c.id 
          WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    echo '<div class="error-message">User not found.</div>';
    exit();
}

$success = $error = '';

// =====================================================
// 1. HANDLE PROFILE UPDATE (including avatar)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $new_avatar_filename = null;

    if (empty($full_name)) {
        $error = 'Full name is required.';
    } else {
        // Avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Avatar must be less than 2MB.';
            } elseif (!in_array($file['type'], $allowed_types) || !in_array($ext, $allowed_ext)) {
                $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
            } else {
                $upload_dir = UPLOAD_BASE . 'avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;

                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                    $new_avatar_filename = $new_name;

                    // Delete old avatar (check both folders)
                    if (!empty($user['profile_photo'])) {
                        $old1 = UPLOAD_BASE . 'profile_photos/' . $user['profile_photo'];
                        $old2 = UPLOAD_BASE . 'avatars/' . $user['profile_photo'];
                        if (file_exists($old1)) unlink($old1);
                        if (file_exists($old2)) unlink($old2);
                    }
                } else {
                    $error = 'Failed to upload avatar.';
                }
            }
        }

        if (empty($error)) {
            if ($new_avatar_filename !== null) {
                $update = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, profile_photo = ? WHERE id = ?";
                $ustmt = mysqli_prepare($conn, $update);
                mysqli_stmt_bind_param($ustmt, "sssssi", $full_name, $email, $phone, $address, $new_avatar_filename, $user_id);
            } else {
                $update = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
                $ustmt = mysqli_prepare($conn, $update);
                mysqli_stmt_bind_param($ustmt, "ssssi", $full_name, $email, $phone, $address, $user_id);
            }

            if (mysqli_stmt_execute($ustmt)) {
                $success = 'Profile updated successfully!';
                // Refresh local data
                $user['full_name'] = $full_name;
                $user['email']     = $email;
                $user['phone']     = $phone;
                $user['address']   = $address;
                if ($new_avatar_filename !== null) {
                    $user['profile_photo'] = $new_avatar_filename;
                }
            } else {
                $error = 'Failed to update profile.';
            }
            mysqli_stmt_close($ustmt);
        }
    }
}

// =====================================================
// 2. HANDLE PASSWORD CHANGE
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Password updated successfully!';
        } else {
            $error = 'Failed to update password.';
        }
        mysqli_stmt_close($stmt);
    }
}

// =====================================================
// Build profile photo source
// =====================================================
$profile_photo_src = '';
if (!empty($user['profile_photo'])) {
    $paths_to_check = [
        ['fs' => UPLOAD_BASE . 'avatars/' . $user['profile_photo'],        'web' => 'uploads/avatars/' . $user['profile_photo']],
        ['fs' => UPLOAD_BASE . 'profile_photos/' . $user['profile_photo'], 'web' => 'uploads/profile_photos/' . $user['profile_photo']],
    ];
    foreach ($paths_to_check as $path) {
        if (file_exists($path['fs'])) {
            $profile_photo_src = $path['web'];
            break;
        }
    }
}

// Fallback SVG avatar
$default_avatar_uri = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'%3E%3Cdefs%3E%3ClinearGradient id='bg' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23ffc107'/%3E%3Cstop offset='100%25' style='stop-color:%23e67e22'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='200' height='200' fill='url(%23bg)'/%3E%3Ccircle cx='100' cy='70' r='35' fill='%23fff' opacity='0.9'/%3E%3Cellipse cx='100' cy='160' rx='60' ry='45' fill='%23fff' opacity='0.9'/%3E%3C/svg%3E";

if (empty($profile_photo_src)) {
    $profile_photo_src = $default_avatar_uri;
}
?>

<div class="header-section">
    <h1><i class="fas fa-user-circle"></i> My Dashboard</h1>
    <p class="subtitle">Manage your profile and account settings</p>
    <a href="#" class="back-btn" onclick="loadPage('dashboard'); return false;">
        <i class="fas fa-arrow-left"></i> Back to Main Dashboard
    </a>
</div>

<?php if ($error): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <div><p><?php echo htmlspecialchars($error); ?></p></div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <div><p><?php echo htmlspecialchars($success); ?></p></div>
    </div>
<?php endif; ?>

<div class="profile-container" style="display:flex; flex-wrap:wrap; gap:30px; margin-top:20px;">

    <!-- ==================== PROFILE CARD ==================== -->
    <div class="profile-card" style="flex:1; min-width:300px; background:#0c1a2b; border-radius:12px; padding:25px; border:1px solid rgba(255,255,255,0.1);">
        <div style="display:flex; align-items:center; gap:20px; margin-bottom:25px;">
            <img src="<?php echo htmlspecialchars($profile_photo_src); ?>" 
                 alt="Profile Photo" 
                 style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid orange;">
            <div>
                <h2 style="color:white; margin:0;"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h2>
                <p style="color:#adb5bd; margin:5px 0;"><i class="fas fa-user-tag"></i> <?php echo ucfirst($user['role'] ?? 'Member'); ?></p>
                <p style="color:#adb5bd; margin:5px 0;">
                    <i class="fas fa-church"></i> 
                    <?php echo htmlspecialchars($user['church_name']); ?> 
                    (<?php echo htmlspecialchars($user['church_username']); ?>)
                </p>
            </div>
        </div>

        <div style="color:#adb5bd;">
            <div style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
                <strong><i class="fas fa-envelope"></i> Email:</strong> 
                <?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?>
            </div>
            <div style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
                <strong><i class="fas fa-phone"></i> Phone:</strong> 
                <?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?>
            </div>
            <div style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
                <strong><i class="fas fa-map-marker-alt"></i> Address:</strong> 
                <?php echo htmlspecialchars($user['address'] ?? 'Not set'); ?>
            </div>
            <div style="padding:10px 0;">
                <strong><i class="fas fa-calendar-alt"></i> Member Since:</strong> 
                <?php echo date('F j, Y', strtotime($user['joined_church_at'] ?? $user['created_at'])); ?>
            </div>
        </div>
    </div>

    <!-- ==================== FORMS ==================== -->
    <div style="flex:1; min-width:300px;">

        <!-- Edit Profile -->
        <div style="background:#0c1a2b; border-radius:12px; padding:25px; margin-bottom:20px; border:1px solid rgba(255,255,255,0.1);">
            <h3 style="color:white; margin-top:0;"><i class="fas fa-edit"></i> Edit Profile</h3>

            <form method="POST" enctype="multipart/form-data" id="editProfileForm">
                <input type="hidden" name="update_profile" value="1">

                <div style="margin-bottom:15px;">
                    <label style="color:#adb5bd; display:block; margin-bottom:5px;">Profile Photo</label>
                    <div style="display:flex; align-items:center; gap:15px;">
                        <img src="<?php echo htmlspecialchars($profile_photo_src); ?>" 
                             style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid orange;">
                        <input type="file" name="avatar" accept="image/*" style="color:white;">
                    </div>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="color:#adb5bd; display:block; margin-bottom:5px;">Full Name <span style="color:#e74c3c;">*</span></label>
                    <input type="text" name="full_name" required 
                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                           style="width:100%; padding:10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:white;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="color:#adb5bd; display:block; margin-bottom:5px;">Email</label>
                    <input type="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                           style="width:100%; padding:10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:white;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="color:#adb5bd; display:block; margin-bottom:5px;">Phone</label>
                    <input type="text" name="phone" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                           style="width:100%; padding:10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:white;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="color:#adb5bd; display:block; margin-bottom:5px;">Address</label>
                    <input type="text" name="address" 
                           value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                           style="width:100%; padding:10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:white;">
                </div>

                <button type="submit" style="background:orange; color:#0c1a2b; border:none; padding:12px 25px; border-radius:8px; font-weight:600; cursor:pointer;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div style="background:#0c1a2b; border-radius:12px; padding:25px; border:1px solid rgba(255,255,255,0.1);">
            <h3 style="color:white; margin-top:0;"><i class="fas fa-key"></i> Change Password</h3>

            <form method="POST" id="changePasswordForm">
                <input type="hidden" name="change_password" value="1">

                <div style="margin-bottom:15px;">
                    <label style="color:#adb5bd; display:block; margin-bottom:5px;">Current Password</label>
                    <input type="password" name="current_password" required
                           style="width:100%; padding:10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:white;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="color:#adb5bd; display:block; margin-bottom:5px;">New Password (min 6 chars)</label>
                    <input type="password" name="new_password" required minlength="6"
                           style="width:100%; padding:10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:white;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="color:#adb5bd; display:block; margin-bottom:5px;">Confirm New Password</label>
                    <input type="password" name="confirm_password" required
                           style="width:100%; padding:10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:white;">
                </div>

                <button type="submit" style="background:orange; color:#0c1a2b; border:none; padding:12px 25px; border-radius:8px; font-weight:600; cursor:pointer;">
                    <i class="fas fa-lock"></i> Update Password
                </button>
            </form>
        </div>

    </div>
</div>

<script>
// =====================================================
// AJAX handlers (keep everything inside the SPA)
// =====================================================
(function () {
    // Helper to replace content + re-run scripts
    function replaceContent(html) {
        const contentEl = document.getElementById('pageContent');
        contentEl.innerHTML = html;

        contentEl.querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');
            if (oldScript.src) {
                newScript.src = oldScript.src;
            } else {
                newScript.textContent = oldScript.textContent;
            }
            oldScript.replaceWith(newScript);
        });
    }

    // Profile form
    const profileForm = document.getElementById('editProfileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;

            fetch('pages/member-dashboard.php', {
                method: 'POST',
                body: new FormData(this),
                credentials: 'same-origin'
            })
            .then(r => r.text())
            .then(html => replaceContent(html))
            .catch(() => {
                btn.innerHTML = original;
                btn.disabled = false;
                alert('An error occurred while saving. Please try again.');
            });
        });
    }

    // Password form
    const passwordForm = document.getElementById('changePasswordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            btn.disabled = true;

            fetch('pages/member-dashboard.php', {
                method: 'POST',
                body: new FormData(this),
                credentials: 'same-origin'
            })
            .then(r => r.text())
            .then(html => replaceContent(html))
            .catch(() => {
                btn.innerHTML = original;
                btn.disabled = false;
                alert('An error occurred while updating password.');
            });
        });
    }
})();
</script>
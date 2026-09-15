<?php
// member-management.php - Admin user management
require_once 'init.php';
requireLogin();

$user_id = $_SESSION['user_id'];
if (!in_array($_SESSION['role'] ?? '', ['developer', 'admin'])) {
    echo '<div class="error-message">Access denied.</div>';
    exit();
}
// Check if user is admin
$check_admin = "SELECT role FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $check_admin);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_role = mysqli_fetch_assoc($result);
$is_admin = in_array($user_role['role'] ?? '', ['developer', 'admin']);
mysqli_stmt_close($stmt);

if (!$is_admin) {
    header('Location: index.php');
    exit();
}

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $approval_id = intval($_POST['approval_id']);
        $action = $_POST['action'] ?? 'approve';
        
        // Get approval details
        $query = "SELECT * FROM user_approvals WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $approval_id);
        mysqli_stmt_execute($stmt);
        $approval = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        
        if ($approval) {
            if ($action === 'approve') {
                if ($approval['action_type'] === 'delete') {
                    // Delete user account
                    $del_user = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($del_user, "i", $approval['user_id']);
                    mysqli_stmt_execute($del_user);
                    mysqli_stmt_close($del_user);
                    
                    // Delete profile
                    $del_profile = mysqli_prepare($conn, "DELETE FROM member_profiles WHERE user_id = ?");
                    mysqli_stmt_bind_param($del_profile, "i", $approval['user_id']);
                    mysqli_stmt_execute($del_profile);
                    mysqli_stmt_close($del_profile);
                } elseif ($approval['action_type'] === 'update') {
                    // Apply profile updates
                    $new_data = json_decode($approval['new_data'], true);
                    $query = "UPDATE member_profiles SET 
                              full_name = ?, phone = ?, address = ?, dob = ?, 
                              occupation = ?, hometown = ?, residence = ?,
                              emergency_contact = ?, emergency_phone = ?,
                              spouse_name = ?, anniversary_date = ?
                              WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "sssssssssssi",
                        $new_data['full_name'], $new_data['phone'], $new_data['address'],
                        $new_data['dob'], $new_data['occupation'], $new_data['hometown'],
                        $new_data['residence'], $new_data['emergency_contact'],
                        $new_data['emergency_phone'], $new_data['spouse_name'],
                        $new_data['anniversary_date'], $approval['user_id']
                    );
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
            
            // Update approval status
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $update = mysqli_prepare($conn,
                "UPDATE user_approvals SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?"
            );
            mysqli_stmt_bind_param($update, "sii", $status, $user_id, $approval_id);
            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);
        }
    }
}

// Get all users
$users_query = "SELECT u.*, mp.full_name, mp.phone, mp.dob 
                FROM users u 
                LEFT JOIN member_profiles mp ON u.id = mp.user_id 
                ORDER BY u.created_at DESC";
$users_result = mysqli_query($conn, $users_query);

// Get pending approvals
$pending_query = "SELECT a.*, u.username, u.email, mp.full_name as requester_name 
                  FROM user_approvals a 
                  JOIN users u ON a.user_id = u.id 
                  LEFT JOIN member_profiles mp ON a.requested_by = mp.user_id 
                  WHERE a.status = 'pending' 
                  ORDER BY a.created_at ASC";
$pending_result = mysqli_query($conn, $pending_query);
$pending_count = mysqli_num_rows($pending_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CM</title>
    <link rel="stylesheet" href="main.css">
    <link rel="shortcut icon" href="images/logo.jpg" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Sidebar and main content styles (same as before) */
        .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100vh; background: #0c1a2b; border-right: 1px solid rgba(255,255,255,0.1); z-index: 1000; display: flex; flex-direction: column; overflow: hidden; }
        .sidebar-scroll { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0 0 10px 0; }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,165,0,0.3); border-radius: 3px; }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .logo-group { display: flex; align-items: center; gap: 12px; padding: 16px 16px 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); background: transparent !important; flex-shrink: 0; }
        .logo-group img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
        .logo-text .name { color: orange !important; font-size: 22px; font-weight: 700; text-decoration: none; display: block; }
        .logo-text .full-name { color: #adb5bd !important; font-size: 11px; margin: 0; font-weight: 400; }
        .sidebar-items { padding: 8px 0; }
        .sidebar-item { margin: 1px 0; }
        .sidebar-item a { display: flex; align-items: center; gap: 15px; padding: 10px 22px; color: #adb5bd; text-decoration: none; transition: all 0.3s ease; border-left: 3px solid transparent; font-size: 14px; }
        .sidebar-item a:hover { background: rgba(255,255,255,0.05); color: white; }
        .sidebar-item.active a { background: rgba(255,165,0,0.1); color: orange; border-left-color: orange; }
        .sidebar-item a i { width: 20px; text-align: center; font-size: 15px; }
        .logout-wrapper { border-top: 1px solid rgba(255,255,255,0.08); padding: 8px 0 12px 0; flex-shrink: 0; background: #0c1a2b; }
        .logout-wrapper .sidebar-item a { color: #e74c3c; }
        .logout-wrapper .sidebar-item a:hover { background: rgba(231,76,60,0.1); color: #e74c3c; }

        .main-content { margin-left: 260px; padding: 30px 40px; min-height: 100vh; background: #0a1520; }
        .header-section { margin-bottom: 30px; }
        .header-section h1 { color: white; margin: 0 0 10px 0; font-size: 28px; }
        .header-section .subtitle { color: #adb5bd; margin: 0; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; color: #adb5bd; text-decoration: none; padding: 8px 16px; border-radius: 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s ease; font-size: 14px; }
        .back-btn:hover { background: rgba(255,255,255,0.1); color: white; }

        .pending-alert { background: rgba(255,165,0,0.1); border: 1px solid rgba(255,165,0,0.3); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #adb5bd; }
        .pending-alert i { color: orange; font-size: 1.2rem; }
        .pending-alert .badge { background: orange; color: #0c1a2b; padding: 2px 10px; border-radius: 12px; font-weight: 600; font-size: 0.8rem; margin-left: 5px; }

        .users-grid { display: grid; gap: 15px; }
        .user-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); align-items: center; }
        .user-row:hover { border-color: rgba(255,165,0,0.2); }
        .user-row .name { color: white; font-weight: 500; }
        .user-row .email { color: #adb5bd; font-size: 0.9rem; }
        .user-row .role { font-size: 0.75rem; padding: 2px 10px; border-radius: 12px; display: inline-block; }
        .role-developer { background: rgba(231,76,60,0.2); color: #e74c3c; }
        .role-admin { background: rgba(255,165,0,0.2); color: orange; }
        .role-pastor { background: rgba(52,152,219,0.2); color: #3498db; }
        .role-usher { background: rgba(46,204,113,0.2); color: #2ecc71; }
        .role-member { background: rgba(155,89,182,0.2); color: #9b59b6; }
        .status-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 12px; }
        .status-active { background: rgba(46,204,113,0.2); color: #2ecc71; }
        .status-pending { background: rgba(255,165,0,0.2); color: orange; }
        .status-inactive { background: rgba(231,76,60,0.2); color: #e74c3c; }
        .user-actions { display: flex; gap: 8px; }
        .user-actions button { background: none; border: none; color: #adb5bd; cursor: pointer; padding: 5px; transition: all 0.3s ease; }
        .user-actions button:hover { color: orange; }
        .user-actions button.danger:hover { color: #e74c3c; }

        .empty-state { text-align: center; padding: 60px 20px; color: #adb5bd; }
        .empty-state i { font-size: 4rem; color: rgba(255,255,255,0.1); margin-bottom: 20px; display: block; }
        .add-btn { background: orange; color: #0c1a2b; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 10px; }
        .add-btn:hover { background: #ff8c00; transform: translateY(-2px); }

        .approval-item { padding: 15px; background: rgba(255,165,0,0.05); border-radius: 8px; border: 1px solid rgba(255,165,0,0.15); margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .approval-item .info { flex: 1; }
        .approval-item .info .title { color: white; font-weight: 500; }
        .approval-item .info .details { color: #adb5bd; font-size: 0.85rem; margin-top: 3px; }
        .approval-item .actions { display: flex; gap: 8px; }

        @media (max-width: 768px) {
            .sidebar { width: 70px; padding: 0; }
            .sidebar .logo-text, .sidebar .sidebar-item span { display: none; }
            .sidebar .logo-group { padding: 10px 8px 16px 8px; justify-content: center; }
            .sidebar .logo-group img { width: 40px; height: 40px; }
            .sidebar .sidebar-item a { padding: 12px 0; justify-content: center; }
            .sidebar .sidebar-item a i { margin: 0; font-size: 18px; }
            .logout-wrapper .sidebar-item a { padding: 12px 0; justify-content: center; }
            .main-content { margin-left: 70px; padding: 20px; }
            .user-row { grid-template-columns: 1fr; gap: 8px; text-align: center; }
            .user-actions { justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
            .header-section h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="logo-group">
            <img src="images/logo.jpg" alt="Church Logo" onerror="this.src='https://via.placeholder.com/80x80?text=LOGO'">
            <div class="logo-text">
                <a class="name" href="index.php">CM</a>
                <p class="full-name">COC Management</p>
            </div>
        </div>
        <div class="sidebar-scroll">
            <ul>
                <div class="sidebar-items">
                    <li class="sidebar-item"><a href="index.php"><i class="fas fa-users"></i> <span>Members</span></a></li>
                    <li class="sidebar-item"><a href="add-member.php"><i class="fas fa-user-plus"></i> <span>Add Member</span></a></li>
                    <li class="sidebar-item"><a href="lookup.php"><i class="fas fa-search"></i> <span>Find Member</span></a></li>
                    <li class="sidebar-item"><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                    <li class="sidebar-item"><a href="music.php"><i class="fas fa-music"></i> <span>Music</span></a></li>
                    <li class="sidebar-item active"><a href="member-management.php"><i class="fas fa-user-cog"></i> <span>User Management</span></a></li>
                    <li class="sidebar-item"><a href="privacy-policy.php"><i class="fas fa-shield-alt"></i> <span>Privacy Policy</span></a></li>
                </div>
            </ul>
        </div>
        <div class="logout-wrapper">
            <ul>
                <li class="sidebar-item"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <div class="header-section">
            <h1><i class="fas fa-user-cog"></i> User Management</h1>
            <p class="subtitle">Manage users, roles, and pending requests</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
                <a href="member-register.php" class="add-btn">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Pending Approvals -->
        <?php if ($pending_count > 0): ?>
            <div class="pending-alert">
                <i class="fas fa-bell"></i>
                <span>You have <span class="badge"><?php echo $pending_count; ?></span> pending approval request(s)</span>
            </div>

            <h2 style="color:white;margin-bottom:15px;font-size:1.2rem;">
                <i class="fas fa-clock" style="color:orange;"></i> Pending Approvals
            </h2>
            <?php while ($pending = mysqli_fetch_assoc($pending_result)): ?>
                <div class="approval-item">
                    <div class="info">
                        <div class="title">
                            <?php echo ucfirst($pending['action_type']); ?> Request
                            <?php if ($pending['action_type'] === 'delete'): ?>
                                <span style="color:#e74c3c;">⚠️</span>
                            <?php endif; ?>
                        </div>
                        <div class="details">
                            <strong><?php echo htmlspecialchars($pending['username']); ?></strong>
                            (<?php echo htmlspecialchars($pending['email']); ?>)
                            <?php if ($pending['action_type'] === 'delete'): ?>
                                - <span style="color:#e74c3c;">Requested account deletion</span>
                            <?php else: ?>
                                - <span style="color:#2ecc71;">Requested profile update</span>
                            <?php endif; ?>
                            <br>
                            <small style="color:#6c757d;">
                                Requested by: <?php echo htmlspecialchars($pending['requester_name'] ?? $pending['username']); ?>
                                • <?php echo date('d/m/Y H:i', strtotime($pending['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <div class="actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="approval_id" value="<?php echo $pending['id']; ?>">
                            <button type="submit" name="approve" value="approve" class="add-btn" style="background:#2ecc71;padding:5px 15px;font-size:0.8rem;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="submit" name="approve" value="reject" class="add-btn" style="background:#e74c3c;padding:5px 15px;font-size:0.8rem;">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
            <hr style="border-color:rgba(255,255,255,0.1);margin:30px 0;">
        <?php endif; ?>

        <!-- User List -->
        <h2 style="color:white;margin-bottom:15px;font-size:1.2rem;">
            <i class="fas fa-users" style="color:orange;"></i> All Users
        </h2>

        <div class="users-grid">
            <?php if (mysqli_num_rows($users_result) > 0): ?>
                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                    <div class="user-row">
                        <div>
                            <div class="name">
                                <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                            </div>
                            <div class="email"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div>
                            <span class="role role-<?php echo $user['role']; ?>">
                                <i class="fas <?php echo in_array($user['role'], ['developer', 'admin']) ? 'fa-user-shield' : 'fa-user'; ?>"></i>
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $user['membership_status'] ?? 'pending'; ?>">
                                <?php echo ucfirst($user['membership_status'] ?? 'Pending'); ?>
                            </span>
                            <?php if (!empty($user['phone'])): ?>
                                <br><small style="color:#6c757d;">📞 <?php echo htmlspecialchars($user['phone']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="user-actions">
                            <?php if ($user['role'] !== 'developer'): ?>
                                <a href="member-profile-view.php?id=<?php echo $user['id']; ?>" title="View Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <button onclick="changeRole(<?php echo $user['id']; ?>)" title="Change Role">
                                        <i class="fas fa-user-tag"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($user['membership_status'] === 'pending'): ?>
                                    <button onclick="approveUser(<?php echo $user['id']; ?>)" title="Approve User" style="color:#2ecc71;">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3 style="color:white;">No Users Found</h3>
                    <p>Start by adding your first user to the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changeRole(userId) {
            const role = prompt('Enter new role (admin, pastor, usher, member):');
            if (role && ['admin', 'pastor', 'usher', 'member'].includes(role.toLowerCase())) {
                if (confirm('Change user role to ' + role.toLowerCase() + '?')) {
                    fetch('member-actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=change_role&id=' + userId + '&role=' + role.toLowerCase()
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (result.message || 'Unknown error'));
                        }
                    })
                    .catch(() => alert('Network error. Please try again.'));
                }
            } else if (role !== null) {
                alert('Invalid role. Please enter: admin, pastor, usher, or member');
            }
        }

        function approveUser(userId) {
            if (confirm('Approve this user? They will gain full access.')) {
                fetch('member-actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=approve_user&id=' + userId
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (result.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Network error. Please try again.'));
            }
        }
    </script>
</body>
</html>
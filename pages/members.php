<?php
// pages/members.php - Member list with modal popups (church-based)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Please login to view this page.</div>';
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

// ===== FETCH CHURCH_ID FROM DATABASE =====
$church_id = 0;
$church_query = "SELECT church_id FROM users WHERE id = ?";
$church_stmt = mysqli_prepare($conn, $church_query);
if ($church_stmt) {
    mysqli_stmt_bind_param($church_stmt, "i", $user_id);
    mysqli_stmt_execute($church_stmt);
    mysqli_stmt_bind_result($church_stmt, $church_id);
    mysqli_stmt_fetch($church_stmt);
    mysqli_stmt_close($church_stmt);
}

if ($church_id == 0 && isset($_SESSION['church_id'])) {
    $church_id = (int)$_SESSION['church_id'];
}

if ($church_id <= 0) {
    echo '<div class="error-message">You are not assigned to a valid church. Please contact your church administrator.</div>';
    exit();
}

$_SESSION['church_id'] = $church_id;

// Optionally fetch church name
if (empty($_SESSION['church_name'])) {
    $name_query = "SELECT church_name FROM churches WHERE id = ?";
    $name_stmt = mysqli_prepare($conn, $name_query);
    if ($name_stmt) {
        mysqli_stmt_bind_param($name_stmt, "i", $church_id);
        mysqli_stmt_execute($name_stmt);
        mysqli_stmt_bind_result($name_stmt, $church_name);
        mysqli_stmt_fetch($name_stmt);
        mysqli_stmt_close($name_stmt);
        if (!empty($church_name)) {
            $_SESSION['church_name'] = $church_name;
        }
    }
}
?>
<div class="header-section">
    <h1><i class="fas fa-users"></i> Church Members</h1>
    <p class="subtitle">View and manage all registered church members</p>
    <div style="display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
        <a href="#" class="add-btn" onclick="loadPage('add-member'); return false;">
            <i class="fas fa-user-plus"></i> Add New Member
        </a>
        <a href="#" class="add-btn export" onclick="loadPage('export-members'); return false;">
            <i class="fas fa-file-export"></i> Export CSV
        </a>
    </div>
</div>

<div id="members-container">
    <?php
    // Query members using church_id
    $query = "SELECT * FROM members WHERE church_id = ? ORDER BY name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $church_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        echo '<div class="members-grid">';

        while ($member = mysqli_fetch_assoc($result)) {
            $dob = !empty($member['dob']) ? date('d/m/Y', strtotime($member['dob'])) : 'Not set';
            $created_at = !empty($member['created_at']) ? date('d/m/Y', strtotime($member['created_at'])) : '';
            $first_letter = strtoupper(substr($member['name'], 0, 1));
            $profile_photo = !empty($member['profile_photo']) ? htmlspecialchars($member['profile_photo']) : '';
            $member_name = htmlspecialchars($member['name'], ENT_QUOTES);
            $member_id = (int) $member['id'];
            $occupation = !empty($member['occupation']) ? htmlspecialchars($member['occupation']) : 'Not specified';
            $hometown = !empty($member['hometown']) ? htmlspecialchars($member['hometown']) : 'Not specified';
            $residence = htmlspecialchars($member['residence'] ?? '');
            $photo_path = $profile_photo !== '' ? 'uploads/profile_photos/' . $profile_photo : '';

            $avatar_html = $profile_photo !== ''
                ? '<img src="' . $photo_path . '" alt="' . $member_name . '">'
                : '<span>' . $first_letter . '</span>';

            echo <<<HTML
            <div class="member-card" data-member-id="{$member_id}">
                <div class="member-header">
                    <div class="member-avatar" onclick="viewFullPhoto({$member_id}, '{$member_name}', '{$photo_path}')">
                        {$avatar_html}
                        <div class="hover-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <div class="member-info">
                        <h3>{$member_name}</h3>
                        <span class="member-id">ID: {$member_id} | Joined: {$created_at}</span>
                    </div>
                </div>

                <div class="member-details">
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-calendar-alt"></i> Date of Birth</span>
                        <span class="detail-value">{$dob}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-briefcase"></i> Occupation</span>
                        <span class="detail-value">{$occupation}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-home"></i> Hometown</span>
                        <span class="detail-value">{$hometown}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Residence</span>
                        <span class="detail-value">{$residence}</span>
                    </div>
                </div>

                <div class="member-actions">
                    <button class="action-btn view" onclick="openMemberModal({$member_id})">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="action-btn edit" onclick="openEditModal({$member_id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="action-btn photo" onclick="uploadPhoto({$member_id})">
                        <i class="fas fa-camera"></i> Photo
                    </button>
                </div>
            </div>
HTML;
        }

        echo '</div>';
        mysqli_stmt_close($stmt);

        // Statistics
        $totalQuery = "SELECT COUNT(*) as total, COALESCE(SUM(children), 0) as total_children FROM members WHERE church_id = ?";
        $totalStmt = mysqli_prepare($conn, $totalQuery);
        mysqli_stmt_bind_param($totalStmt, "i", $church_id);
        mysqli_stmt_execute($totalStmt);
        $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt));
        mysqli_stmt_close($totalStmt);

        $churchQuery = "SELECT COUNT(DISTINCT previous_church) as church_count FROM members WHERE church_id = ? AND previous_church != '' AND previous_church IS NOT NULL";
        $churchStmt = mysqli_prepare($conn, $churchQuery);
        mysqli_stmt_bind_param($churchStmt, "i", $church_id);
        mysqli_stmt_execute($churchStmt);
        $churchStats = mysqli_fetch_assoc(mysqli_stmt_get_result($churchStmt));
        mysqli_stmt_close($churchStmt);

        echo '
        <div class="stats-container">
            <div class="stat-box"><h3><i class="fas fa-user-friends"></i> Total Members</h3><p class="stat-number">' . ($stats['total'] ?? 0) . '</p></div>
            <div class="stat-box"><h3><i class="fas fa-child"></i> Total Children</h3><p class="stat-number">' . ($stats['total_children'] ?? 0) . '</p></div>
            <div class="stat-box"><h3><i class="fas fa-church"></i> Previous Churches</h3><p class="stat-number">' . ($churchStats['church_count'] ?? 0) . '</p></div>
        </div>';
    } else {
        echo '
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>No Members Found</h3>
            <p>No members have been registered yet. Start by adding the first member to your church database.</p>
            <a href="#" class="add-btn" onclick="loadPage(\'add-member\'); return false;" style="margin-top: 20px; display: inline-block;">
                <i class="fas fa-user-plus"></i> Add First Member
            </a>
        </div>
        <div class="stats-container">
            <div class="stat-box"><h3><i class="fas fa-user-friends"></i> Total Members</h3><p class="stat-number">0</p></div>
            <div class="stat-box"><h3><i class="fas fa-child"></i> Total Children</h3><p class="stat-number">0</p></div>
            <div class="stat-box"><h3><i class="fas fa-church"></i> Previous Churches</h3><p class="stat-number">0</p></div>
        </div>';
    }
    ?>
</div>

<!-- ===== Include the modal and toast system ===== -->
<?php include __DIR__ . '/member_modal.php'; ?>
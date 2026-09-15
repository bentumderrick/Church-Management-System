<?php
// pages/developer.php - Developer-only dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'developer') {
    echo '<div class="error-message">Access denied. Developer only.</div>';
    exit();
}

$user_id = $_SESSION['user_id'];

// Use the variables from db.php (adjust names if yours differ)
$db_host = $host ?? 'localhost';
$db_name = $database ?? 'unknown';
$db_user = $username ?? 'unknown';
?>
<div class="header-section">
    <h1><i class="fas fa-code"></i> Developer Panel</h1>
    <p class="subtitle">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>!</p>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
    <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 25px; border: 1px solid rgba(255,255,255,0.1);">
        <h3 style="color: white; margin-bottom: 15px;"><i class="fas fa-database"></i> Database Info</h3>
        <p style="color: #adb5bd;">Database: <?php echo htmlspecialchars($db_name); ?></p>
        <p style="color: #adb5bd;">User: <?php echo htmlspecialchars($db_user); ?></p>
        <p style="color: #adb5bd;">Host: <?php echo htmlspecialchars($db_host); ?></p>
    </div>
    <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 25px; border: 1px solid rgba(255,255,255,0.1);">
        <h3 style="color: white; margin-bottom: 15px;"><i class="fas fa-cog"></i> System Info</h3>
        <p style="color: #adb5bd;">PHP Version: <?php echo phpversion(); ?></p>
        <p style="color: #adb5bd;">Server: <?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></p>
        <p style="color: #adb5bd;">Upload Max: <?php echo ini_get('upload_max_filesize'); ?></p>
    </div>
</div>

<div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 25px; border: 1px solid rgba(255,255,255,0.08); margin-top: 25px;">
    <h3 style="color: white; margin-bottom: 15px;"><i class="fas fa-tools"></i> Quick Tools</h3>
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <a href="#" onclick="loadPage('members')" class="add-btn" style="background: #3498db; color: white;">
            <i class="fas fa-users"></i> Members
        </a>
        <a href="#" onclick="loadPage('attendance')" class="add-btn" style="background: #2ecc71; color: white;">
            <i class="fas fa-calendar-check"></i> Attendance
        </a>
        <a href="#" onclick="loadPage('music')" class="add-btn" style="background: #9b59b6; color: white;">
            <i class="fas fa-music"></i> Music
        </a>
        <a href="#" onclick="loadPage('member-management')" class="add-btn" style="background: orange; color: #0c1a2b;">
            <i class="fas fa-user-cog"></i> User Management
        </a>
    </div>
</div>
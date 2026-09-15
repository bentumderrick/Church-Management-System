<?php
// pages/dashboard.php - Dashboard content with church-based stats
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Please login to view this page.</div>';
    exit();
}

$church_id = $_SESSION['church_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Get stats for this church
$stats = [];

// Total members in church
$query = "SELECT COUNT(*) as total FROM members WHERE church_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $church_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$stats['members'] = $row['total'] ?? 0;
mysqli_stmt_close($stmt);

// Total attendance for this church
$query = "SELECT COUNT(*) as total FROM attendance WHERE church_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $church_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$stats['attendance'] = $row['total'] ?? 0;
mysqli_stmt_close($stmt);

// Total offerings for this church
$query = "SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount FROM offerings WHERE church_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $church_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$stats['offerings_count'] = $row['total'] ?? 0;
$stats['offerings_total'] = $row['total_amount'] ?? 0;
mysqli_stmt_close($stmt);

// Total hymns for this church
$query = "SELECT COUNT(*) as total FROM hymns WHERE church_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $church_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$stats['hymns'] = $row['total'] ?? 0;
mysqli_stmt_close($stmt);
?>
<style>
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 25px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.08);
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        border-color: rgba(255,165,0,0.3);
        transform: translateY(-3px);
    }
    .stat-card .number {
        font-size: 2.5rem;
        font-weight: bold;
        color: orange;
    }
    .stat-card .label {
        color: #adb5bd;
        font-size: 0.9rem;
        margin-top: 5px;
    }
    .stat-card .icon {
        font-size: 2rem;
        color: rgba(255,165,0,0.2);
        margin-bottom: 10px;
    }
    .welcome-section {
        background: rgba(255,255,255,0.03);
        border-radius: 12px;
        padding: 30px;
        border: 1px solid rgba(255,255,255,0.08);
        margin-bottom: 30px;
    }
    .welcome-section h2 {
        color: white;
        font-size: 1.8rem;
        margin-bottom: 5px;
    }
    .welcome-section .subtitle {
        color: #adb5bd;
    }
    .quick-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    .quick-action {
        padding: 10px 20px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 8px;
        color: white;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    .quick-action:hover {
        background: rgba(255,165,0,0.1);
        border-color: rgba(255,165,0,0.3);
        transform: translateY(-2px);
    }
    .quick-action.primary {
        background: orange;
        color: #0c1a2b;
    }
    .quick-action.primary:hover {
        background: #ff8c00;
    }
</style>

<div class="welcome-section">
    <h2>👋 Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>!</h2>
    <p class="subtitle">
        <?php if (!empty($_SESSION['church_name'])): ?>
            Church: <strong><?php echo htmlspecialchars($_SESSION['church_name']); ?></strong>
        <?php else: ?>
            No church assigned yet.
        <?php endif; ?>
    </p>
    <div class="quick-actions">
        <a class="quick-action primary" onclick="loadPage('add-member')">
            <i class="fas fa-user-plus"></i> Add Member
        </a>
        <a class="quick-action" onclick="loadPage('attendance')">
            <i class="fas fa-calendar-check"></i> Take Attendance
        </a>
        <a class="quick-action" onclick="loadPage('music')">
            <i class="fas fa-music"></i> Music
        </a>
        <a class="quick-action" onclick="loadPage('members')">
            <i class="fas fa-users"></i> View Members
        </a>
    </div>
</div>

<div class="dashboard-stats">
    <div class="stat-card">
        <div class="icon"><i class="fas fa-users"></i></div>
        <div class="number"><?php echo $stats['members']; ?></div>
        <div class="label">Total Members</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-calendar-check"></i></div>
        <div class="number"><?php echo $stats['attendance']; ?></div>
        <div class="label">Total Attendance</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="number">₵<?php echo number_format($stats['offerings_total'], 2); ?></div>
        <div class="label">Total Offerings</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-music"></i></div>
        <div class="number"><?php echo $stats['hymns']; ?></div>
        <div class="label">Total Hymns</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div style="background:rgba(255,255,255,0.03);border-radius:12px;padding:25px;border:1px solid rgba(255,255,255,0.08);">
        <h3 style="color:white;margin-bottom:15px;"><i class="fas fa-clock" style="color:orange;"></i> Recent Activity</h3>
        <p style="color:#adb5bd;text-align:center;padding:20px 0;">No recent activity to display.</p>
    </div>
    <div style="background:rgba(255,255,255,0.03);border-radius:12px;padding:25px;border:1px solid rgba(255,255,255,0.08);">
        <h3 style="color:white;margin-bottom:15px;"><i class="fas fa-tips" style="color:orange;"></i> Quick Tips</h3>
        <ul style="color:#adb5bd;list-style:none;padding:0;">
            <li style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
                <i class="fas fa-check-circle" style="color:#2ecc71;"></i> Add members to start building your church directory
            </li>
            <li style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
                <i class="fas fa-check-circle" style="color:#2ecc71;"></i> Track attendance to monitor member engagement
            </li>
            <li style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
                <i class="fas fa-check-circle" style="color:#2ecc71;"></i> Upload hymns to build your church music library
            </li>
            <li style="padding:8px 0;">
                <i class="fas fa-check-circle" style="color:#2ecc71;"></i> Use the member dashboard to manage your profile
            </li>
        </ul>
    </div>
</div>
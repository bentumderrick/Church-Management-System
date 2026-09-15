<?php
// init.php - Initialize application with church support
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Get current user data (including church info)
function getCurrentUser() {
    global $conn;
    if (!isset($_SESSION['user_id'])) return null;
    
    $user_id = $_SESSION['user_id'];
    $query = "SELECT u.*, c.church_name, c.church_username, c.church_logo 
              FROM users u 
              LEFT JOIN churches c ON u.church_id = c.id 
              WHERE u.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $user;
}

// Get church data
function getChurch($church_id) {
    global $conn;
    $query = "SELECT * FROM churches WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $church_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $church = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $church;
}

// Check if user is logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Check if user is admin of their church
function isChurchAdmin($user_id) {
    global $conn;
    $query = "SELECT is_church_admin, role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return ($user['is_church_admin'] == 1 || in_array($user['role'], ['developer', 'admin']));
}

// Check if database is set up
function checkDatabaseSetup() {
    global $conn;
    $required_tables = ['users', 'churches', 'members'];
    $missing = [];
    
    foreach ($required_tables as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $result = mysqli_query($conn, $query);
        if (mysqli_num_rows($result) == 0) {
            $missing[] = $table;
        }
    }
    
    return $missing;
}
?>
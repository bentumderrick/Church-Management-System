<?php
// auth.php - Authentication functions
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Check for remember me cookie
        if (isset($_COOKIE['user_id'])) {
            require_once 'db.php';
            global $conn;
            
            $user_id = mysqli_real_escape_string($conn, $_COOKIE['user_id']);
            $query = "SELECT * FROM users WHERE id = '$user_id'";
            $result = mysqli_query($conn, $query);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['church_name'] = $user['church_name'];
                return true;
            }
        }
        
        // Redirect to login page
        header('Location: login.php');
        exit();
    }
    return true;
}

// Get current user ID
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}
?>
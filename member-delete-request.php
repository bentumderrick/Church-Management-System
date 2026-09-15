<?php
// member-delete-request.php - Request account deletion
require_once 'init.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Check if there's already a pending deletion request
$check = mysqli_prepare($conn, "SELECT id FROM user_approvals WHERE user_id = ? AND action_type = 'delete' AND status = 'pending'");
mysqli_stmt_bind_param($check, "i", $user_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    $message = 'You already have a pending deletion request.';
    $type = 'error';
} else {
    mysqli_stmt_close($check);
    
    // Create deletion request
    $insert = mysqli_prepare($conn,
        "INSERT INTO user_approvals (user_id, requested_by, action_type) VALUES (?, ?, 'delete')"
    );
    mysqli_stmt_bind_param($insert, "ii", $user_id, $user_id);
    
    if (mysqli_stmt_execute($insert)) {
        $message = 'Account deletion request submitted for admin approval.';
        $type = 'success';
    } else {
        $message = 'Failed to submit deletion request: ' . mysqli_error($conn);
        $type = 'error';
    }
    mysqli_stmt_close($insert);
}

header('Location: member-dashboard.php?message=' . urlencode($message) . '&type=' . $type);
exit();
?>
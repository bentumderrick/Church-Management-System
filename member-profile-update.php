<?php
// member-profile-update.php - Submit profile update for admin approval
require_once 'init.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $hometown = trim($_POST['hometown'] ?? '');
    $residence = trim($_POST['residence'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    $spouse_name = trim($_POST['spouse_name'] ?? '');
    $anniversary_date = trim($_POST['anniversary_date'] ?? '');
    
    // Check if there's already a pending request
    $check = mysqli_prepare($conn, "SELECT id FROM user_approvals WHERE user_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($check, "i", $user_id);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    
    if (mysqli_stmt_num_rows($check) > 0) {
        $error = 'You already have a pending profile update request.';
    } else {
        mysqli_stmt_close($check);
        
        // Build old data
        $old_query = "SELECT * FROM member_profiles WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $old_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $old_result = mysqli_stmt_get_result($stmt);
        $old_data = mysqli_fetch_assoc($old_result);
        mysqli_stmt_close($stmt);
        
        $new_data = [
            'full_name' => $full_name,
            'phone' => $phone,
            'address' => $address,
            'dob' => $dob,
            'occupation' => $occupation,
            'hometown' => $hometown,
            'residence' => $residence,
            'emergency_contact' => $emergency_contact,
            'emergency_phone' => $emergency_phone,
            'spouse_name' => $spouse_name,
            'anniversary_date' => $anniversary_date
        ];
        
        // Insert approval request
        $insert = mysqli_prepare($conn,
            "INSERT INTO user_approvals (user_id, requested_by, action_type, old_data, new_data) VALUES (?, ?, 'update', ?, ?)"
        );
        $old_json = json_encode($old_data);
        $new_json = json_encode($new_data);
        mysqli_stmt_bind_param($insert, "iiss", $user_id, $user_id, $old_json, $new_json);
        
        if (mysqli_stmt_execute($insert)) {
            $success = 'Profile update request submitted for admin approval.';
        } else {
            $error = 'Failed to submit update request: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($insert);
    }
    
    header('Location: member-dashboard.php?message=' . urlencode($success ?: $error) . '&type=' . ($success ? 'success' : 'error'));
    exit();
}
?>
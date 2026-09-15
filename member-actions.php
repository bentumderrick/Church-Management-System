<?php
// member-actions.php - Handle member AJAX actions (church-based)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ===== GET CHURCH_ID FROM SESSION OR DATABASE =====
$church_id = 0;
if (isset($_SESSION['church_id']) && $_SESSION['church_id'] > 0) {
    $church_id = (int)$_SESSION['church_id'];
} else {
    // Fetch from database
    $q = "SELECT church_id FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $q);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $church_id);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }
}

if ($church_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No church assigned to this user']);
    exit();
}

$action = $_POST['action'] ?? '';
header('Content-Type: application/json');

switch ($action) {
    case 'get_member':
        $member_id = intval($_POST['member_id'] ?? 0);
        if ($member_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
            break;
        }
        // Query using church_id for security
        $query = "SELECT * FROM members WHERE id = ? AND church_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $member_id, $church_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            // Format date fields for display
            if (!empty($row['dob'])) {
                $row['dob_formatted'] = date('d/m/Y', strtotime($row['dob']));
            }
            if (!empty($row['created_at'])) {
                $row['created_at_formatted'] = date('d/m/Y', strtotime($row['created_at']));
            }
            echo json_encode(['success' => true, 'member' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'update_member':
        $member_id = intval($_POST['member_id'] ?? 0);
        if ($member_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
            break;
        }
        // Verify member belongs to church
        $check = "SELECT id FROM members WHERE id = ? AND church_id = ?";
        $chk = mysqli_prepare($conn, $check);
        mysqli_stmt_bind_param($chk, "ii", $member_id, $church_id);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) == 0) {
            echo json_encode(['success' => false, 'message' => 'Member not found or not in your church']);
            mysqli_stmt_close($chk);
            break;
        }
        mysqli_stmt_close($chk);

        $name = trim($_POST['name'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $hometown = trim($_POST['hometown'] ?? '');
        $residence = trim($_POST['residence'] ?? '');
        $previous_church = trim($_POST['previous_church'] ?? '');
        $children = intval($_POST['children'] ?? 0);
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name) || empty($residence)) {
            echo json_encode(['success' => false, 'message' => 'Name and Residence are required']);
            break;
        }

        $query = "UPDATE members SET 
                  name = ?, dob = ?, occupation = ?, hometown = ?, 
                  residence = ?, previous_church = ?, children = ?, 
                  contact = ?, notes = ?, updated_at = NOW() 
                  WHERE id = ? AND church_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssssissii", 
            $name, $dob, $occupation, $hometown, $residence, 
            $previous_church, $children, $contact, $notes, 
            $member_id, $church_id
        );
        if (mysqli_stmt_execute($stmt)) {
            // Fetch updated member data
            $fetch = "SELECT * FROM members WHERE id = ? AND church_id = ?";
            $f = mysqli_prepare($conn, $fetch);
            mysqli_stmt_bind_param($f, "ii", $member_id, $church_id);
            mysqli_stmt_execute($f);
            $res = mysqli_stmt_get_result($f);
            $updated = mysqli_fetch_assoc($res);
            mysqli_stmt_close($f);
            echo json_encode(['success' => true, 'message' => 'Member updated', 'member' => $updated]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'delete_member':
        $member_id = intval($_POST['member_id'] ?? 0);
        if ($member_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
            break;
        }
        // Verify member belongs to church
        $check = "SELECT id, profile_photo FROM members WHERE id = ? AND church_id = ?";
        $chk = mysqli_prepare($conn, $check);
        mysqli_stmt_bind_param($chk, "ii", $member_id, $church_id);
        mysqli_stmt_execute($chk);
        $result = mysqli_stmt_get_result($chk);
        $member = mysqli_fetch_assoc($result);
        mysqli_stmt_close($chk);
        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Member not found or not in your church']);
            break;
        }
        // Delete photo file if exists
        if (!empty($member['profile_photo']) && file_exists('uploads/profile_photos/' . $member['profile_photo'])) {
            unlink('uploads/profile_photos/' . $member['profile_photo']);
        }
        $del = "DELETE FROM members WHERE id = ? AND church_id = ?";
        $stmt = mysqli_prepare($conn, $del);
        mysqli_stmt_bind_param($stmt, "ii", $member_id, $church_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Member deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'upload_photo':
        $member_id = intval($_POST['member_id'] ?? 0);
        if ($member_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
            break;
        }
        // Verify member belongs to church
        $check = "SELECT id, profile_photo FROM members WHERE id = ? AND church_id = ?";
        $chk = mysqli_prepare($conn, $check);
        mysqli_stmt_bind_param($chk, "ii", $member_id, $church_id);
        mysqli_stmt_execute($chk);
        $result = mysqli_stmt_get_result($chk);
        $member = mysqli_fetch_assoc($result);
        mysqli_stmt_close($chk);
        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Member not found or not in your church']);
            break;
        }

        if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
            break;
        }
        $file = $_FILES['profile_photo'];
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file['type'], $allowed) || !in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
            break;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File must be less than 2MB']);
            break;
        }
        $upload_dir = 'uploads/profile_photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = 'member_' . $member_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Delete old photo if exists
            if (!empty($member['profile_photo']) && file_exists($upload_dir . $member['profile_photo'])) {
                unlink($upload_dir . $member['profile_photo']);
            }
            $upd = "UPDATE members SET profile_photo = ?, updated_at = NOW() WHERE id = ? AND church_id = ?";
            $stmt = mysqli_prepare($conn, $upd);
            mysqli_stmt_bind_param($stmt, "sii", $filename, $member_id, $church_id);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Photo uploaded successfully']);
            } else {
                // Delete uploaded file if DB fails
                unlink($dest);
                echo json_encode(['success' => false, 'message' => 'Database update failed: ' . mysqli_error($conn)]);
            }
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        }
        break;

    case 'create_member':
        // Validate required fields
        $name = trim($_POST['name'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $hometown = trim($_POST['hometown'] ?? '');
        $residence = trim($_POST['residence'] ?? '');
        $previous_church = trim($_POST['previous_church'] ?? '');
        $children = intval($_POST['children'] ?? 0);
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $profile_photo = ''; // handle file upload separately

        // Validate
        if (empty($name) || empty($dob) || empty($residence)) {
            echo json_encode(['success' => false, 'message' => 'Name, Date of Birth, and Residence are required']);
            break;
        }

        // Check duplicate
        $check = mysqli_prepare($conn, "SELECT id FROM members WHERE name = ? AND dob = ? AND church_id = ?");
        mysqli_stmt_bind_param($check, "ssi", $name, $dob, $church_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            echo json_encode(['success' => false, 'message' => 'A member with the same name and date of birth already exists in your church']);
            mysqli_stmt_close($check);
            break;
        }
        mysqli_stmt_close($check);

        // Handle file upload if present
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($file['type'], $allowed) && in_array($ext, ['jpg','jpeg','png','gif','webp']) && $file['size'] <= 2*1024*1024) {
                $upload_dir = 'uploads/profile_photos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'member_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $profile_photo = $filename;
                }
            }
        }

        $query = "INSERT INTO members (church_id, name, dob, occupation, hometown, residence, previous_church, children, contact, notes, profile_photo, added_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "issssssssssi", $church_id, $name, $dob, $occupation, $hometown, $residence, $previous_church, $children, $contact, $notes, $profile_photo, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            echo json_encode(['success' => true, 'id' => $new_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

mysqli_close($conn);
?>
<?php
// export-members.php - Export members to CSV
require_once 'init.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="members_export_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Column headers
fputcsv($output, [
    'ID', 'Name', 'Date of Birth', 'Occupation', 'Hometown', 
    'Residence', 'Previous Church', 'Children', 'Contact', 
    'Joined Date', 'Notes'
]);

// Fetch members
$query = "SELECT * FROM members WHERE user_id = ? ORDER BY name ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['dob'] ? date('d/m/Y', strtotime($row['dob'])) : '',
        $row['occupation'] ?? '',
        $row['hometown'] ?? '',
        $row['residence'],
        $row['previous_church'] ?? '',
        $row['children'] ?? 0,
        $row['contact'] ?? '',
        $row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : '',
        $row['notes'] ?? ''
    ]);
}

mysqli_stmt_close($stmt);
fclose($output);
exit();
?>
<?php
// db.php – secure database connection

// Load environment
$envPath = __DIR__ . '/secured/.env';
if (!file_exists($envPath)) {
    die('Configuration error: .env file missing');
}

require_once __DIR__ . '/secured/env.php';
loadEnv($envPath);

// Connect using env values
$conn = mysqli_connect(
    $_ENV['DB_HOST'] ?? '',
    $_ENV['DB_USER'] ?? '',
    $_ENV['DB_PASS'] ?? '',
    $_ENV['DB_NAME'] ?? ''
);

if (!$conn) {
    $errorMsg = mysqli_connect_error();

    // AJAX / JSON requests
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
        (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json')
    ) {
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'error'   => $errorMsg
        ]));
    }

    // Regular requests
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Error</title>
        <style>
            body { font-family: Arial; padding: 40px; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>Database Connection Failed</h2>
            <p><strong>Error:</strong> {$errorMsg}</p>
            <p><strong>Details:</strong><br>
            Host: " . htmlspecialchars($_ENV['DB_HOST'] ?? '') . "<br>
            Username: " . htmlspecialchars($_ENV['DB_USER'] ?? '') . "<br>
            Database: " . htmlspecialchars($_ENV['DB_NAME'] ?? '') . "</p>
        </div>
    </body>
    </html>
    ");
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');

// Optional: verify essential tables exist
function checkTablesExist() {
    global $conn;
    $tables = ['users', 'members'];
    foreach ($tables as $table) {
        $stmt = mysqli_prepare($conn, "SHOW TABLES LIKE ?");
        mysqli_stmt_bind_param($stmt, "s", $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) === 0) {
            mysqli_stmt_close($stmt);
            return false;
        }
        mysqli_stmt_close($stmt);
    }
    return true;
}
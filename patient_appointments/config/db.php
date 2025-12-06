<?php
/**
 * Database Configuration
 * PDO Connection for MySQL
 */
//config/db.php
// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'appointment_system');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Create PDO instance with error mode
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Timezone setting
date_default_timezone_set('Asia/Manila');

// Helper function for sanitizing input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Helper function to generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}
?>
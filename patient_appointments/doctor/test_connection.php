<?php
session_start();

echo "<h2>Testing Connection and Session</h2>";

// Test 1: Check if session exists
echo "<h3>1. Session Check:</h3>";
if (isset($_SESSION['doctor_id'])) {
    echo "✓ Doctor logged in: ID = " . $_SESSION['doctor_id'] . "<br>";
    echo "✓ Doctor name: " . ($_SESSION['doctor_name'] ?? 'N/A') . "<br>";
} else {
    echo "✗ No doctor session found<br>";
}

// Test 2: Try to load database
echo "<h3>2. Database Connection Check:</h3>";
$db_paths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../../../config/db.php',
];

foreach ($db_paths as $path) {
    echo "Checking: $path ... ";
    if (file_exists($path)) {
        echo "✓ Found!<br>";
        require_once $path;
        break;
    } else {
        echo "✗ Not found<br>";
    }
}

// Test 3: Check database connection
echo "<h3>3. PDO Connection Check:</h3>";
if (isset($pdo)) {
    echo "✓ PDO object exists<br>";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM appointments");
        $count = $stmt->fetchColumn();
        echo "✓ Database query successful<br>";
        echo "✓ Total appointments in database: $count<br>";
    } catch (PDOException $e) {
        echo "✗ Database query failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ PDO object not found<br>";
}

// Test 4: Check appointments for this doctor
if (isset($_SESSION['doctor_id']) && isset($pdo)) {
    echo "<h3>4. Doctor's Appointments Check:</h3>";
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
        $stmt->execute([$_SESSION['doctor_id']]);
        $count = $stmt->fetchColumn();
        echo "✓ This doctor has $count appointments<br>";
        
        // Fetch one appointment for testing
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE doctor_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['doctor_id']]);
        $apt = $stmt->fetch();
        if ($apt) {
            echo "✓ Sample appointment ID: " . $apt['id'] . "<br>";
            echo "✓ Sample appointment status: " . $apt['status'] . "<br>";
        }
    } catch (PDOException $e) {
        echo "✗ Query failed: " . $e->getMessage() . "<br>";
    }
}

echo "<h3>5. File Location:</h3>";
echo "This test file is located at: " . __FILE__ . "<br>";
echo "Directory: " . __DIR__ . "<br>";
?>
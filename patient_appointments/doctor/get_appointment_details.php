<?php
// Disable all error display to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if appointment ID is provided
$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

try {
    // Check if gender column exists in users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'gender'");
    $has_gender = $stmt->fetch();
    
    // Build the SELECT clause dynamically
    $gender_select = $has_gender ? "u.gender" : "'N/A'";
    
    // Fetch appointment details
    $query = "
        SELECT 
            a.id,
            a.user_id,
            a.doctor_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            a.created_at,
            u.fullname as patient_name, 
            u.email as patient_email, 
            u.phone as patient_phone,
            $gender_select as patient_gender
        FROM appointments a
        INNER JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.doctor_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id, $doctor_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }
    
    // Format the time
    $appointment['appointment_time'] = date('h:i A', strtotime($appointment['appointment_time']));
    
    echo json_encode([
        'success' => true,
        'appointment' => $appointment
    ]);
    
} catch (PDOException $e) {
    error_log("Get Appointment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
<?php
// Disable all error display to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);
//doctor/update_appointment_status.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$appointment_id = $_POST['appointment_id'] ?? null;
$new_status = $_POST['status'] ?? null;

// Validate input
if (!$appointment_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status
$allowed_statuses = ['pending', 'approved', 'completed', 'cancelled'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

try {
    // Verify that the appointment belongs to this doctor
    $stmt = $pdo->prepare("SELECT id, status FROM appointments WHERE id = ? AND doctor_id = ?");
    $stmt->execute([$appointment_id, $doctor_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
        exit;
    }

    // Update the appointment status (without updated_at column)
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $appointment_id]);

    // Set success message based on status
    $status_messages = [
        'approved' => 'Appointment approved successfully',
        'completed' => 'Appointment marked as completed',
        'cancelled' => 'Appointment cancelled successfully',
        'pending' => 'Appointment status updated to pending'
    ];

    $_SESSION['success'] = $status_messages[$new_status] ?? 'Appointment status updated successfully';

    echo json_encode([
        'success' => true, 
        'message' => $status_messages[$new_status] ?? 'Status updated successfully',
        'new_status' => $new_status
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
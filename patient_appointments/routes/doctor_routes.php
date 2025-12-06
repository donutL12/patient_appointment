<?php
/**
 * Doctor Routes Handler
 * Handles doctor-specific operations
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$doctor_id = $_SESSION['doctor_id'];

// ==========================================
// GET PATIENT DETAILS
// ==========================================
if ($action === 'get_patient_details') {
    try {
        $patient_id = $_GET['patient_id'] ?? null;
        
        if (!$patient_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
            exit;
        }
        
        // Get patient information
        $stmt = $pdo->prepare("
            SELECT id, fullname, email, phone, dob, gender, address, profile_photo, created_at
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Patient not found']);
            exit;
        }
        
        // Get appointment history with this doctor
        $stmt = $pdo->prepare("
            SELECT 
                id, 
                appointment_date, 
                appointment_time, 
                status, 
                notes,
                created_at
            FROM appointments 
            WHERE user_id = ? AND doctor_id = ?
            ORDER BY appointment_date DESC, appointment_time DESC
            LIMIT 10
        ");
        $stmt->execute([$patient_id, $doctor_id]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total appointment count
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM appointments 
            WHERE user_id = ? AND doctor_id = ?
        ");
        $stmt->execute([$patient_id, $doctor_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'patient' => $patient,
            'appointments' => $appointments,
            'stats' => $stats
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log("Get Patient Details Error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        exit;
    }
}

// ==========================================
// INVALID ACTION
// ==========================================
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
?>
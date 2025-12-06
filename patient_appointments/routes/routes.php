<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/AuthController.php';

// Debug logging
if ($_POST['action'] === 'send_message' || $_GET['action'] === 'send_message') {
    error_log("=== SEND MESSAGE DEBUG ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    error_log("Session: " . print_r($_SESSION, true));
}

// Initialize controllers
$authController = new AuthController($pdo);

// Helper function for last seen text
function generateLastSeenText($last_seen) {
    if (empty($last_seen)) return 'Last seen a long time ago';
    
    $time_diff = time() - strtotime($last_seen);
    
    if ($time_diff < 60) return 'Active now';
    if ($time_diff < 3600) return 'Last seen ' . floor($time_diff / 60) . ' minute' . (floor($time_diff / 60) > 1 ? 's' : '') . ' ago';
    if ($time_diff < 86400) return 'Last seen ' . floor($time_diff / 3600) . ' hour' . (floor($time_diff / 3600) > 1 ? 's' : '') . ' ago';
    if ($time_diff < 604800) return 'Last seen ' . floor($time_diff / 86400) . ' day' . (floor($time_diff / 86400) > 1 ? 's' : '') . ' ago';
    
    return 'Last seen on ' . date('M d, Y', strtotime($last_seen));
}

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Route handling
switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->register($_POST);
        }
        break;
        
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->login($_POST);
        }
        break;
        
    case 'forgot_password':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->forgotPassword($_POST);
        }
        break;
        
    case 'reset_password':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->resetPassword($_POST);
        }
        break;
        
    case 'logout':
        // Set user offline before logout
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $table = 'users';
        } elseif (isset($_SESSION['doctor_id'])) {
            $user_id = $_SESSION['doctor_id'];
            $table = 'doctors';
        }
        
        if (isset($user_id) && isset($table)) {
            try {
                $stmt = $pdo->prepare("UPDATE {$table} SET is_online = 0, last_seen = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
            } catch (PDOException $e) {
                error_log("Logout Status Update Error: " . $e->getMessage());
            }
        }
        
        $authController->logout();
        break;
    // ============ BOOK APPOINTMENT ============
    case 'book_appointment':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Check if user is logged in
                if (!isset($_SESSION['user_id'])) {
                    $_SESSION['error'] = 'Please login to book an appointment.';
                    header('Location: ../index.php');
                    exit;
                }
                
                // Validate required fields
                if (empty($_POST['doctor_id']) || empty($_POST['appointment_date']) || empty($_POST['appointment_time'])) {
                    $_SESSION['error'] = 'All fields are required.';
                    header('Location: ../user/book_appointment.php');
                    exit;
                }
                
                $user_id = $_SESSION['user_id'];
                $doctor_id = $_POST['doctor_id'];
                $appointment_date = $_POST['appointment_date'];
                $appointment_time = $_POST['appointment_time'];
                $notes = $_POST['notes'] ?? '';
                
                // Validate date is not in the past
                if (strtotime($appointment_date) < strtotime('today')) {
                    $_SESSION['error'] = 'Cannot book appointment in the past.';
                    header('Location: ../user/book_appointment.php');
                    exit;
                }
                
                // Check if doctor exists
                $stmt = $pdo->prepare("SELECT id FROM doctors WHERE id = ?");
                $stmt->execute([$doctor_id]);
                if (!$stmt->fetch()) {
                    $_SESSION['error'] = 'Invalid doctor selected.';
                    header('Location: ../user/book_appointment.php');
                    exit;
                }
                
                // Check for duplicate appointment
                $stmt = $pdo->prepare("
                    SELECT id FROM appointments 
                    WHERE doctor_id = ? 
                    AND appointment_date = ? 
                    AND appointment_time = ?
                    AND status IN ('pending', 'approved')
                ");
                $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = 'This time slot is already booked. Please choose another time.';
                    header('Location: ../user/book_appointment.php');
                    exit;
                }
                
                // Check if appointments table has schedule_id column
                $stmt = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'schedule_id'");
                $hasScheduleId = $stmt->fetch();
                
                if ($hasScheduleId) {
                    // Table has schedule_id column - insert with NULL or find matching schedule
                    $stmt = $pdo->prepare("
                        INSERT INTO appointments 
                        (user_id, doctor_id, schedule_id, appointment_date, appointment_time, notes, status, created_at) 
                        VALUES (?, ?, NULL, ?, ?, ?, 'pending', NOW())
                    ");
                    
                    $stmt->execute([
                        $user_id,
                        $doctor_id,
                        $appointment_date,
                        $appointment_time,
                        $notes
                    ]);
                } else {
                    // Table doesn't have schedule_id column
                    $stmt = $pdo->prepare("
                        INSERT INTO appointments 
                        (user_id, doctor_id, appointment_date, appointment_time, notes, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    
                    $stmt->execute([
                        $user_id,
                        $doctor_id,
                        $appointment_date,
                        $appointment_time,
                        $notes
                    ]);
                }
                
                $_SESSION['success'] = 'Appointment booked successfully! Waiting for doctor approval.';
                header('Location: ../user/appointments.php');
                exit;
                
            } catch (PDOException $e) {
                // Check if it's a foreign key constraint error
                if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                    $_SESSION['error'] = 'Database configuration issue. Please contact administrator.';
                    error_log("Foreign Key Error: " . $e->getMessage());
                } else {
                    $_SESSION['error'] = 'Failed to book appointment. Please try again.';
                    error_log("Book Appointment Error: " . $e->getMessage());
                }
                header('Location: ../user/book_appointment.php');
                exit;
            }
        }
        break;
        
    // ============ CANCEL APPOINTMENT ============
    case 'cancel_appointment':
        try {
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['error'] = 'Please login first.';
                header('Location: ../index.php');
                exit;
            }
            
            $appointment_id = $_GET['id'] ?? null;
            
            if (!$appointment_id) {
                $_SESSION['error'] = 'Invalid appointment.';
                header('Location: ../user/appointments.php');
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, status FROM appointments 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$appointment_id, $_SESSION['user_id']]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                $_SESSION['error'] = 'Appointment not found.';
                header('Location: ../user/appointments.php');
                exit;
            }
            
            if ($appointment['status'] === 'completed' || $appointment['status'] === 'cancelled') {
                $_SESSION['error'] = 'Cannot cancel this appointment.';
                header('Location: ../user/appointments.php');
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$appointment_id]);
            
            $_SESSION['success'] = 'Appointment cancelled successfully.';
            header('Location: ../user/appointments.php');
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to cancel appointment.';
            error_log("Cancel Appointment Error: " . $e->getMessage());
            header('Location: ../user/appointments.php');
            exit;
        }
        break;


        // ============ ADD SCHEDULE (DOCTOR) ============
case 'add_schedule':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Check if doctor is logged in
            if (!isset($_SESSION['doctor_id'])) {
                $_SESSION['error'] = 'Please login to add schedules.';
                header('Location: ../index.php');
                exit;
            }
            
            // Validate required fields
            if (empty($_POST['schedule_date']) || empty($_POST['start_time']) || 
                empty($_POST['end_time']) || empty($_POST['slots'])) {
                $_SESSION['error'] = 'All fields are required.';
                header('Location: ../doctor/schedule.php');
                exit;
            }
            
            $doctor_id = $_SESSION['doctor_id'];
            $schedule_date = $_POST['schedule_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $slots = (int)$_POST['slots'];
            $notes = $_POST['notes'] ?? '';
            
            // Validate date is not in the past
            if (strtotime($schedule_date) < strtotime('today')) {
                $_SESSION['error'] = 'Cannot create schedule in the past.';
                header('Location: ../doctor/schedule.php');
                exit;
            }
            
            // Validate time range
            if (strtotime($start_time) >= strtotime($end_time)) {
                $_SESSION['error'] = 'End time must be after start time.';
                header('Location: ../doctor/schedule.php');
                exit;
            }
            
            // Validate slots
            if ($slots < 1 || $slots > 100) {
                $_SESSION['error'] = 'Slots must be between 1 and 100.';
                header('Location: ../doctor/schedule.php');
                exit;
            }
            
            // Check for overlapping schedules
            $stmt = $pdo->prepare("
                SELECT id FROM doctor_schedule 
                WHERE doctor_id = ? AND schedule_date = ?
                AND ((start_time <= ? AND end_time > ?) OR
                     (start_time < ? AND end_time >= ?) OR
                     (start_time >= ? AND end_time <= ?))
            ");
            $stmt->execute([
                $doctor_id, $schedule_date,
                $start_time, $start_time,
                $end_time, $end_time,
                $start_time, $end_time
            ]);
            
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'This schedule overlaps with an existing schedule.';
                header('Location: ../doctor/schedule.php');
                exit;
            }
            
            // Insert schedule
            $stmt = $pdo->prepare("
                INSERT INTO doctor_schedule 
                (doctor_id, schedule_date, start_time, end_time, slots, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $doctor_id,
                $schedule_date,
                $start_time,
                $end_time,
                $slots
            ]);
            
            $_SESSION['success'] = 'Schedule added successfully!';
            header('Location: ../doctor/schedule.php');
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to add schedule. Please try again.';
            error_log("Add Schedule Error: " . $e->getMessage());
            header('Location: ../doctor/schedule.php');
            exit;
        }
    }
    break;

// ============ DELETE SCHEDULE (DOCTOR) ============
case 'delete_schedule':
    try {
        if (!isset($_SESSION['doctor_id'])) {
            $_SESSION['error'] = 'Please login first.';
            header('Location: ../index.php');
            exit;
        }
        
        $schedule_id = $_GET['id'] ?? null;
        
        if (!$schedule_id) {
            $_SESSION['error'] = 'Invalid schedule.';
            header('Location: ../doctor/schedule.php');
            exit;
        }
        
        // Verify schedule belongs to this doctor
        $stmt = $pdo->prepare("
            SELECT id FROM doctor_schedule 
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$schedule_id, $_SESSION['doctor_id']]);
        
        if (!$stmt->fetch()) {
            $_SESSION['error'] = 'Schedule not found.';
            header('Location: ../doctor/schedule.php');
            exit;
        }
        
        // Check if there are appointments for this schedule
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM appointments 
            WHERE schedule_id = ? AND status IN ('pending', 'approved')
        ");
        $stmt->execute([$schedule_id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            $_SESSION['error'] = 'Cannot delete schedule with existing appointments.';
            header('Location: ../doctor/schedule.php');
            exit;
        }
        
        // Delete schedule
        $stmt = $pdo->prepare("DELETE FROM doctor_schedule WHERE id = ?");
        $stmt->execute([$schedule_id]);
        
        $_SESSION['success'] = 'Schedule deleted successfully.';
        header('Location: ../doctor/schedule.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete schedule.';
        error_log("Delete Schedule Error: " . $e->getMessage());
        header('Location: ../doctor/schedule.php');
        exit;
    }
    break;
    
// ============ SEND MESSAGE (Patient & Doctor) ============
case 'send_message':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            $message = trim($_POST['message'] ?? '');
            $receiver_id = $_POST['receiver_id'] ?? null;
            $receiver_type = $_POST['receiver_type'] ?? null;
            $image_path = null;
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                $file = $_FILES['image'];
                $file_type = $file['type'];
                $file_size = $file['size'];
                
                // Validate file type
                if (!in_array($file_type, $allowed_types)) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Invalid image type. Only JPG, PNG, GIF, and WebP are allowed.']);
                    ob_end_flush();
                    exit;
                }
                
                // Validate file size
                if ($file_size > $max_size) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Image size must be less than 5MB']);
                    ob_end_flush();
                    exit;
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'chat_' . uniqid() . '_' . time() . '.' . $extension;
                $upload_dir = __DIR__ . '/../public/uploads/chat/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $target_path = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $image_path = 'chat/' . $filename;
                } else {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                    ob_end_flush();
                    exit;
                }
            }
            
            // Require either message or image (UPDATED LOGIC)
            if (empty($message) && empty($image_path)) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please enter a message or select an image']);
                ob_end_flush();
                exit;
            }
            
            if (empty($receiver_id) || empty($receiver_type)) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
                ob_end_flush();
                exit;
            }
            
            // Determine sender
            if (isset($_SESSION['user_id'])) {
                $sender_id = $_SESSION['user_id'];
                $sender_type = 'patient';
                $redirect_url = "../user/chat.php?doctor_id=$receiver_id";
            } elseif (isset($_SESSION['doctor_id'])) {
                $sender_id = $_SESSION['doctor_id'];
                $sender_type = 'doctor';
                $redirect_url = "../doctor/chat.php?patient_id=$receiver_id";
            } else {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                ob_end_flush();
                exit;
            }
            
            // Insert message - Allow NULL message if image exists
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, sender_type, receiver_id, receiver_type, message, image_path, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $result = $stmt->execute([
                $sender_id,
                $sender_type,
                $receiver_id,
                $receiver_type,
                $message ?: null,  // Allow NULL if message is empty
                $image_path
            ]);
            
            if ($result) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
                ob_end_flush();
                exit;
            } else {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to send message']);
                ob_end_flush();
                exit;
            }
            
        } catch (PDOException $e) {
            error_log("Send Message Error: " . $e->getMessage());
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            ob_end_flush();
            exit;
        } catch (Exception $e) {
            error_log("General Error: " . $e->getMessage());
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            ob_end_flush();
            exit;
        }
    }
    break;

// ============ CREATE CALL NOTIFICATION IN MESSAGES ============

    case 'create_call_notification':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            $call_id = $_POST['call_id'] ?? null;
            $status = $_POST['status'] ?? 'ended';
            $duration = (int)($_POST['duration'] ?? 0);
            
            if (!$call_id) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Call ID required']);
                exit;
            }
            
            // Get call details
            $stmt = $pdo->prepare("
                SELECT caller_id, caller_type, receiver_id, receiver_type, call_type, created_at
                FROM calls 
                WHERE id = ?
            ");
            $stmt->execute([$call_id]);
            $call = $stmt->fetch();
            
            if (!$call) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Call not found']);
                exit;
            }
            
            // Determine sender and receiver for message
            if (isset($_SESSION['user_id'])) {
                $current_user_id = $_SESSION['user_id'];
                $current_user_type = 'patient';
            } elseif (isset($_SESSION['doctor_id'])) {
                $current_user_id = $_SESSION['doctor_id'];
                $current_user_type = 'doctor';
            } else {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }
            
            // Insert call notification as a message
            $stmt = $pdo->prepare("
                INSERT INTO messages 
                (sender_id, sender_type, receiver_id, receiver_type, message, call_id, is_call_notification, is_read, created_at) 
                VALUES (?, ?, ?, ?, NULL, ?, 1, 0, NOW())
            ");
            
            $stmt->execute([
                $call['caller_id'],
                $call['caller_type'],
                $call['receiver_id'],
                $call['receiver_type'],
                $call_id
            ]);
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Call notification created']);
            ob_end_flush();
            exit;
            
        } catch (PDOException $e) {
            error_log("Create Call Notification Error: " . $e->getMessage());
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error']);
            ob_end_flush();
            exit;
        }
    }
    break;

        // ============ UPDATE APPOINTMENT STATUS (DOCTOR) ============
case 'update_appointment_status':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Check if doctor is logged in
            if (!isset($_SESSION['doctor_id'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                exit;
            }
            
            $appointment_id = $_POST['appointment_id'] ?? null;
            $new_status = $_POST['status'] ?? null;
            
            if (!$appointment_id || !$new_status) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            // Validate status
            $valid_statuses = ['pending', 'approved', 'cancelled', 'completed'];
            if (!in_array($new_status, $valid_statuses)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            // Verify appointment belongs to this doctor
            $stmt = $pdo->prepare("
                SELECT id, status FROM appointments 
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([$appointment_id, $_SESSION['doctor_id']]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                exit;
            }
            
            // Prevent certain status transitions
            if ($appointment['status'] === 'completed' && $new_status !== 'completed') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Cannot change status of completed appointment']);
                exit;
            }
            
            // Update status
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$new_status, $appointment_id]);
            
            if ($result) {
                // Log activity
                error_log("Doctor #{$_SESSION['doctor_id']} updated appointment #{$appointment_id} to status: {$new_status}");
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Appointment status updated successfully',
                    'new_status' => $new_status
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to update appointment']);
            }
            exit;
            
        } catch (PDOException $e) {
            error_log("Update Appointment Status Error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
            exit;
        }
    }
    break;

// ============ CALL MANAGEMENT ============
case 'initiate_call':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            $receiver_id = $_POST['receiver_id'] ?? null;
            $receiver_type = $_POST['receiver_type'] ?? null;
            $call_type = $_POST['call_type'] ?? 'audio';
            
            if (!$receiver_id || !$receiver_type) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }
            
            // Validate call type
            if (!in_array($call_type, ['audio', 'video'])) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid call type']);
                exit;
            }
            
            // Determine caller
            if (isset($_SESSION['user_id'])) {
                $caller_id = $_SESSION['user_id'];
                $caller_type = 'patient';
            } elseif (isset($_SESSION['doctor_id'])) {
                $caller_id = $_SESSION['doctor_id'];
                $caller_type = 'doctor';
            } else {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }
            
            // Check if receiver is currently on another call
            $stmt = $pdo->prepare("
                SELECT id FROM calls 
                WHERE ((receiver_id = ? AND receiver_type = ?) OR (caller_id = ? AND caller_type = ?))
                AND status IN ('initiated', 'ringing', 'answered')
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute([$receiver_id, $receiver_type, $receiver_id, $receiver_type]);
            
            if ($stmt->fetch()) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'User is currently on another call']);
                exit;
            }
            
            // Create call record
            $stmt = $pdo->prepare("
                INSERT INTO calls (caller_id, caller_type, receiver_id, receiver_type, call_type, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'initiated', NOW())
            ");
            $stmt->execute([$caller_id, $caller_type, $receiver_id, $receiver_type, $call_type]);
            $call_id = $pdo->lastInsertId();
            
            // Schedule automatic missed call status if not answered in 30 seconds
            // This will be handled by the trigger or by periodic cleanup
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'call_id' => $call_id,
                'call_type' => $call_type,
                'message' => 'Call initiated'
            ]);
            ob_end_flush();
            exit;
            
        } catch (PDOException $e) {
            error_log("Initiate Call Error: " . $e->getMessage());
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error']);
            ob_end_flush();
            exit;
        }
    }
    break;

case 'update_call_status':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            $call_id = $_POST['call_id'] ?? null;
            $status = $_POST['status'] ?? null;
            
            if (!$call_id || !$status) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }
            
            $valid_statuses = ['ringing', 'answered', 'missed', 'rejected', 'ended', 'cancelled', 'no_answer'];
            if (!in_array($status, $valid_statuses)) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            // Get current call status
            $stmt = $pdo->prepare("SELECT status, created_at FROM calls WHERE id = ?");
            $stmt->execute([$call_id]);
            $call = $stmt->fetch();
            
            if (!$call) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Call not found']);
                exit;
            }
            
            // Update call status based on transition
            if ($status === 'answered') {
                $stmt = $pdo->prepare("
                    UPDATE calls 
                    SET status = ?, started_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $call_id]);
            } elseif ($status === 'ended') {
                $stmt = $pdo->prepare("
                    UPDATE calls 
                    SET status = ?, 
                        ended_at = NOW(),
                        duration = COALESCE(TIMESTAMPDIFF(SECOND, started_at, NOW()), 0)
                    WHERE id = ?
                ");
                $stmt->execute([$status, $call_id]);
            } elseif ($status === 'missed' || $status === 'no_answer') {
                // Check if call was actually ringing for some time
                $time_diff = time() - strtotime($call['created_at']);
                if ($time_diff < 3) {
                    $status = 'cancelled'; // Too quick, likely a cancel
                }
                
                $stmt = $pdo->prepare("UPDATE calls SET status = ?, ended_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $call_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE calls SET status = ? WHERE id = ?");
                $stmt->execute([$status, $call_id]);
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Call status updated']);
            ob_end_flush();
            exit;
            
        } catch (PDOException $e) {
            error_log("Update Call Status Error: " . $e->getMessage());
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update call']);
            ob_end_flush();
            exit;
        }
    }
    break;

case 'check_incoming_calls':
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        // Determine receiver
        if (isset($_SESSION['user_id'])) {
            $receiver_id = $_SESSION['user_id'];
            $receiver_type = 'patient';
        } elseif (isset($_SESSION['doctor_id'])) {
            $receiver_id = $_SESSION['doctor_id'];
            $receiver_type = 'doctor';
        } else {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'incoming_calls' => []]);
            exit;
        }
        
        // Mark old unanswered calls as missed
        $stmt = $pdo->prepare("
            UPDATE calls 
            SET status = 'no_answer', ended_at = NOW()
            WHERE receiver_id = ? 
            AND receiver_type = ?
            AND status IN ('initiated', 'ringing')
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ");
        $stmt->execute([$receiver_id, $receiver_type]);
        
        // Check for incoming calls
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   CASE 
                       WHEN c.caller_type = 'doctor' THEN d.fullname
                       WHEN c.caller_type = 'patient' THEN u.fullname
                   END as caller_name,
                   CASE 
                       WHEN c.caller_type = 'doctor' THEN d.profile_photo
                       WHEN c.caller_type = 'patient' THEN u.profile_photo
                   END as caller_photo
            FROM calls c
            LEFT JOIN doctors d ON c.caller_id = d.id AND c.caller_type = 'doctor'
            LEFT JOIN users u ON c.caller_id = u.id AND c.caller_type = 'patient'
            WHERE c.receiver_id = ? 
            AND c.receiver_type = ?
            AND c.status IN ('initiated', 'ringing')
            AND c.created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            ORDER BY c.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$receiver_id, $receiver_type]);
        $incoming_calls = $stmt->fetchAll();
        
        // Update status to ringing if still initiated
        if (!empty($incoming_calls)) {
            foreach ($incoming_calls as $call) {
                if ($call['status'] === 'initiated') {
                    $stmt = $pdo->prepare("UPDATE calls SET status = 'ringing' WHERE id = ?");
                    $stmt->execute([$call['id']]);
                }
            }
        }
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'incoming_calls' => $incoming_calls
        ]);
        ob_end_flush();
        exit;
        
    } catch (PDOException $e) {
        error_log("Check Incoming Calls Error: " . $e->getMessage());
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'incoming_calls' => []]);
        ob_end_flush();
        exit;
    }
    break;

case 'get_call_history':
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        header('Content-Type: application/json');
        
        // Determine user
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $user_type = 'patient';
        } elseif (isset($_SESSION['doctor_id'])) {
            $user_id = $_SESSION['doctor_id'];
            $user_type = 'doctor';
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'calls' => []]);
            exit;
        }
        
        // Get call history with contact details
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                CASE 
                    WHEN c.caller_id = ? AND c.caller_type = ? THEN 'outgoing'
                    ELSE 'incoming'
                END as direction,
                CASE 
                    WHEN c.caller_id = ? AND c.caller_type = ? THEN
                        CASE 
                            WHEN c.receiver_type = 'doctor' THEN d.fullname
                            WHEN c.receiver_type = 'patient' THEN u.fullname
                        END
                    ELSE
                        CASE 
                            WHEN c.caller_type = 'doctor' THEN d2.fullname
                            WHEN c.caller_type = 'patient' THEN u2.fullname
                        END
                END as contact_name,
                CASE 
                    WHEN c.caller_id = ? AND c.caller_type = ? THEN
                        CASE 
                            WHEN c.receiver_type = 'doctor' THEN d.profile_photo
                            WHEN c.receiver_type = 'patient' THEN u.profile_photo
                        END
                    ELSE
                        CASE 
                            WHEN c.caller_type = 'doctor' THEN d2.profile_photo
                            WHEN c.caller_type = 'patient' THEN u2.profile_photo
                        END
                END as contact_photo,
                CASE 
                    WHEN c.caller_id = ? AND c.caller_type = ? THEN c.receiver_id
                    ELSE c.caller_id
                END as contact_id,
                CASE 
                    WHEN c.caller_id = ? AND c.caller_type = ? THEN c.receiver_type
                    ELSE c.caller_type
                END as contact_type
            FROM calls c
            LEFT JOIN doctors d ON c.receiver_id = d.id AND c.receiver_type = 'doctor'
            LEFT JOIN users u ON c.receiver_id = u.id AND c.receiver_type = 'patient'
            LEFT JOIN doctors d2 ON c.caller_id = d2.id AND c.caller_type = 'doctor'
            LEFT JOIN users u2 ON c.caller_id = u2.id AND c.caller_type = 'patient'
            WHERE (c.caller_id = ? AND c.caller_type = ?)
               OR (c.receiver_id = ? AND c.receiver_type = ?)
            ORDER BY c.created_at DESC
            LIMIT 100
        ");
        
        $stmt->execute([
            $user_id, $user_type,  // direction check
            $user_id, $user_type,  // contact_name for outgoing
            $user_id, $user_type,  // contact_photo for outgoing
            $user_id, $user_type,  // contact_id
            $user_id, $user_type,  // contact_type
            $user_id, $user_type,  // WHERE caller
            $user_id, $user_type   // WHERE receiver
        ]);
        
        $calls = $stmt->fetchAll();
        
        // Format call data
        foreach ($calls as &$call) {
            $call['is_missed'] = in_array($call['status'], ['missed', 'no_answer']) && $call['direction'] === 'incoming';
            $call['time_ago'] = formatTimeAgo($call['created_at']);
            $call['formatted_duration'] = formatDuration($call['duration']);
        }
        
        ob_clean();
        echo json_encode([
            'success' => true, 
            'calls' => $calls
        ]);
        ob_end_flush();
        exit;
        
    } catch (PDOException $e) {
        error_log("Get Call History Error: " . $e->getMessage());
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'calls' => []]);
        ob_end_flush();
        exit;
    }
    break;

// Helper functions for call history
function formatTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        $mins = floor($seconds / 60);
        $secs = $seconds % 60;
        return $mins . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
    } else {
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
    }
}

    case 'set_online':
        ob_clean();
        header('Content-Type: application/json');
        
        $user_id = $_SESSION['user_id'] ?? $_SESSION['doctor_id'] ?? null;
        $user_type = isset($_SESSION['user_id']) ? 'patient' : 'doctor';
        $table = $user_type === 'doctor' ? 'doctors' : 'users';
        
        if ($user_id) {
            $pdo->prepare("UPDATE {$table} SET is_online = 1, last_seen = NOW() WHERE id = ?")->execute([$user_id]);
            echo json_encode(['success' => true, 'status' => 'online']);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    
    case 'set_offline':
        ob_clean();
        header('Content-Type: application/json');
        
        $user_id = $_SESSION['user_id'] ?? $_SESSION['doctor_id'] ?? null;
        $user_type = isset($_SESSION['user_id']) ? 'patient' : 'doctor';
        $table = $user_type === 'doctor' ? 'doctors' : 'users';
        
        if ($user_id) {
            $pdo->prepare("UPDATE {$table} SET is_online = 0, last_seen = NOW() WHERE id = ?")->execute([$user_id]);
            echo json_encode(['success' => true, 'status' => 'offline']);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    
    case 'heartbeat':
        ob_clean();
        header('Content-Type: application/json');
        
        $user_id = $_SESSION['user_id'] ?? $_SESSION['doctor_id'] ?? null;
        $user_type = isset($_SESSION['user_id']) ? 'patient' : 'doctor';
        $table = $user_type === 'doctor' ? 'doctors' : 'users';
        
        if ($user_id) {
            $pdo->prepare("UPDATE {$table} SET is_online = 1, last_seen = NOW() WHERE id = ?")->execute([$user_id]);
            echo json_encode(['success' => true, 'heartbeat' => 'ok']);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    
    case 'get_user_status':
        ob_clean();
        header('Content-Type: application/json');
        
        $uid = $_GET['user_id'] ?? null;
        $utype = $_GET['user_type'] ?? null;
        
        if (!$uid || !$utype) {
            echo json_encode(['success' => false, 'message' => 'Missing params']);
            exit;
        }
        
        $table = $utype === 'doctor' ? 'doctors' : 'users';
        $stmt = $pdo->prepare("SELECT id, fullname, is_online, last_seen FROM {$table} WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        $time_diff = time() - strtotime($user['last_seen']);
        $is_online = ($time_diff <= 240 && $user['is_online'] == 1);
        
        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'name' => $user['fullname'],
            'is_online' => $is_online,
            'last_seen' => $user['last_seen'],
            'last_seen_text' => generateLastSeenText($user['last_seen']),
            'time_ago_seconds' => $time_diff
        ]);
        exit;
        
    default:
        $_SESSION['error'] = 'Invalid action.';
        header('Location: ../index.php');
        exit;
}
?>
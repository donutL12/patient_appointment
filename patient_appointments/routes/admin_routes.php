<?php
/**
 * Admin Routes Handler
 * Handles all admin CRUD operations for users, doctors, schedules, appointments, and settings.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$admin_id = $_SESSION['admin_id'];

// Helper function for sanitizing input
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

// Log Admin Activity
function logActivity($pdo, $admin_id, $action) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (admin_id, action, timestamp) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$admin_id, $action]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}


// ==========================================
// SYSTEM SETTINGS MANAGEMENT
// ==========================================

if ($action === 'update_settings') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = 'Invalid request method.';
        header('Location: ../admin/settings.php');
        exit;
    }

    try {
        // Handle logo upload
        $logo_filename = null;
        $remove_logo = isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1';
        
        if (isset($_FILES['system_logo']) && $_FILES['system_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['system_logo'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
                header('Location: ../admin/settings.php');
                exit;
            }
            
            // Validate file size (2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $_SESSION['error'] = 'File size must be less than 2MB.';
                header('Location: ../admin/settings.php');
                exit;
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = '../public/images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $logo_filename = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_path = $upload_dir . $logo_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                $_SESSION['error'] = 'Failed to upload logo.';
                header('Location: ../admin/settings.php');
                exit;
            }
            
            // Delete old logo if exists
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'system_logo'");
            $stmt->execute();
            $old_logo = $stmt->fetchColumn();
            if ($old_logo && file_exists($upload_dir . $old_logo)) {
                unlink($upload_dir . $old_logo);
            }
        }
        
        // Handle logo removal
        if ($remove_logo && !$logo_filename) {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'system_logo'");
            $stmt->execute();
            $old_logo = $stmt->fetchColumn();
            if ($old_logo && file_exists('../public/images/' . $old_logo)) {
                unlink('../public/images/' . $old_logo);
            }
            $logo_filename = ''; // Set to empty to remove from database
        }
        
        $settings_to_update = [
            'system_name' => sanitize($_POST['system_name']),
            'contact_email' => sanitize($_POST['contact_email']),
            'contact_phone' => sanitize($_POST['contact_phone']),
            'contact_address' => sanitize($_POST['contact_address'] ?? ''),
            'default_slots' => (int)sanitize($_POST['default_slots']), 
        ];
        
        // Add logo to settings if uploaded or removed
        if ($logo_filename !== null) {
            $settings_to_update['system_logo'] = $logo_filename;
        }

        // Validate essential inputs
        if (empty($settings_to_update['system_name'])) {
            $_SESSION['error'] = 'System Name is required.';
            header('Location: ../admin/settings.php');
            exit;
        }

        // Validate email format if provided
        if (!empty($settings_to_update['contact_email']) && 
            !filter_var($settings_to_update['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Invalid email format.';
            header('Location: ../admin/settings.php');
            exit;
        }

        // Validate default slots
        if ($settings_to_update['default_slots'] < 1 || $settings_to_update['default_slots'] > 100) {
            $_SESSION['error'] = 'Default slots must be between 1 and 100.';
            header('Location: ../admin/settings.php');
            exit;
        }

        // Begin transaction
        $pdo->beginTransaction();

        foreach ($settings_to_update as $key => $value) {
            // Check if setting exists
            $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetch()) {
                // Update existing setting
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                // Insert new setting
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }

        // Commit transaction
        $pdo->commit();

        // Update session variables so changes appear immediately
        $_SESSION['system_name'] = $settings_to_update['system_name'];

        // Log activity
        $activity_details = [];
        if ($logo_filename !== null) {
            $activity_details[] = $logo_filename === '' ? 'Removed logo' : 'Updated logo';
        }
        $activity_details[] = 'Updated system settings';
        
        logActivity($pdo, $admin_id, implode(', ', $activity_details));

        $_SESSION['success'] = 'System settings updated successfully! Changes are now visible in the header and footer.';
        header('Location: ../admin/settings.php');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Failed to update settings: ' . $e->getMessage();
        error_log("Settings Update Error: " . $e->getMessage());
        header('Location: ../admin/settings.php');
        exit;
    }
}

// ==========================================
// USER (PATIENT) MANAGEMENT
// ==========================================

if ($action === 'add_user') {
    try {
        if (empty($_POST['fullname']) || empty($_POST['email']) || 
            empty($_POST['phone']) || empty($_POST['password'])) {
            $_SESSION['error'] = 'All required fields must be filled.';
            header('Location: ../admin/manage_users.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already exists.';
            header('Location: ../admin/manage_users.php');
            exit;
        }

        $password = password_hash(sanitize($_POST['password']), PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (fullname, email, phone, password, dob, gender, address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            sanitize($_POST['fullname']),
            sanitize($_POST['email']),
            sanitize($_POST['phone']),
            $password,
            sanitize($_POST['dob']) ?: null,
            sanitize($_POST['gender']) ?: null,
            sanitize($_POST['address']) ?: null
        ]);

        logActivity($pdo, $admin_id, "Added new patient: " . sanitize($_POST['fullname']));

        $_SESSION['success'] = 'Patient added successfully.';
        header('Location: ../admin/manage_users.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to add patient: ' . $e->getMessage();
        header('Location: ../admin/manage_users.php');
        exit;
    }
}

if ($action === 'edit_user') {
    try {
        $user_id = (int)$_POST['user_id'];
        
        if (empty($_POST['fullname']) || empty($_POST['email']) || empty($_POST['phone'])) {
            $_SESSION['error'] = 'All required fields must be filled.';
            header('Location: ../admin/manage_users.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $user_id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already exists for another patient.';
            header('Location: ../admin/manage_users.php');
            exit;
        }

        $fullname = sanitize($_POST['fullname']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $dob = sanitize($_POST['dob']) ?: null;
        $gender = sanitize($_POST['gender']) ?: null;
        $address = sanitize($_POST['address']) ?: null;

        if (!empty($_POST['password'])) {
            $password = password_hash(sanitize($_POST['password']), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET fullname = ?, email = ?, phone = ?, password = ?, 
                    dob = ?, gender = ?, address = ?
                WHERE id = ?
            ");
            $stmt->execute([$fullname, $email, $phone, $password, $dob, $gender, $address, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET fullname = ?, email = ?, phone = ?, dob = ?, gender = ?, address = ?
                WHERE id = ?
            ");
            $stmt->execute([$fullname, $email, $phone, $dob, $gender, $address, $user_id]);
        }

        logActivity($pdo, $admin_id, "Updated patient: {$fullname}");

        $_SESSION['success'] = 'Patient updated successfully.';
        header('Location: ../admin/manage_users.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to update patient: ' . $e->getMessage();
        header('Location: ../admin/manage_users.php');
        exit;
    }
}

if ($action === 'delete_user') {
    try {
        $user_id = (int)$_GET['id'];

        $stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['error'] = 'Patient not found.';
            header('Location: ../admin/manage_users.php');
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        logActivity($pdo, $admin_id, "Deleted patient: {$user['fullname']}");

        $_SESSION['success'] = 'Patient deleted successfully.';
        header('Location: ../admin/manage_users.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete patient: ' . $e->getMessage();
        header('Location: ../admin/manage_users.php');
        exit;
    }
}

if ($action === 'get_user') {
    try {
        $user_id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;

    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// DOCTOR MANAGEMENT
// ==========================================

if ($action === 'add_doctor') {
    try {
        if (empty($_POST['fullname']) || empty($_POST['specialization']) || 
            empty($_POST['email']) || empty($_POST['password'])) {
            $_SESSION['error'] = 'All required fields must be filled.';
            header('Location: ../admin/manage_doctors.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already exists.';
            header('Location: ../admin/manage_doctors.php');
            exit;
        }

        $password = password_hash(sanitize($_POST['password']), PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO doctors (fullname, specialization, email, phone, password, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            sanitize($_POST['fullname']),
            sanitize($_POST['specialization']),
            sanitize($_POST['email']),
            sanitize($_POST['phone']) ?: null,
            $password
        ]);

        logActivity($pdo, $admin_id, "Added new doctor: Dr. " . sanitize($_POST['fullname']));

        $_SESSION['success'] = 'Doctor added successfully.';
        header('Location: ../admin/manage_doctors.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to add doctor: ' . $e->getMessage();
        header('Location: ../admin/manage_doctors.php');
        exit;
    }
}

if ($action === 'edit_doctor') {
    try {
        $doctor_id = (int)$_POST['doctor_id'];
        
        if (empty($_POST['fullname']) || empty($_POST['specialization']) || empty($_POST['email'])) {
            $_SESSION['error'] = 'All required fields must be filled.';
            header('Location: ../admin/manage_doctors.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $doctor_id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already exists for another doctor.';
            header('Location: ../admin/manage_doctors.php');
            exit;
        }
        
        $fullname = sanitize($_POST['fullname']);
        $specialization = sanitize($_POST['specialization']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']) ?: null;

        if (!empty($_POST['password'])) {
            $password = password_hash(sanitize($_POST['password']), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE doctors 
                SET fullname = ?, specialization = ?, email = ?, phone = ?, password = ?
                WHERE id = ?
            ");
            $stmt->execute([$fullname, $specialization, $email, $phone, $password, $doctor_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE doctors 
                SET fullname = ?, specialization = ?, email = ?, phone = ?
                WHERE id = ?
            ");
            $stmt->execute([$fullname, $specialization, $email, $phone, $doctor_id]);
        }

        logActivity($pdo, $admin_id, "Updated doctor: Dr. {$fullname}");

        $_SESSION['success'] = 'Doctor updated successfully.';
        header('Location: ../admin/manage_doctors.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to update doctor: ' . $e->getMessage();
        header('Location: ../admin/manage_doctors.php');
        exit;
    }
}

if ($action === 'delete_doctor') {
    try {
        $doctor_id = (int)$_GET['id'];

        $stmt = $pdo->prepare("SELECT fullname FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch();

        if (!$doctor) {
            $_SESSION['error'] = 'Doctor not found.';
            header('Location: ../admin/manage_doctors.php');
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);

        logActivity($pdo, $admin_id, "Deleted doctor: Dr. {$doctor['fullname']}");

        $_SESSION['success'] = 'Doctor deleted successfully.';
        header('Location: ../admin/manage_doctors.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete doctor: ' . $e->getMessage();
        header('Location: ../admin/manage_doctors.php');
        exit;
    }
}

if ($action === 'get_doctor') {
    try {
        $doctor_id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch();

        if ($doctor) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'doctor' => $doctor]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Doctor not found']);
        }
        exit;

    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// SCHEDULE MANAGEMENT
// ==========================================

if ($action === 'add_schedule') {
    try {
        if (empty($_POST['doctor_id']) || empty($_POST['schedule_date']) || 
            empty($_POST['start_time']) || empty($_POST['end_time'])) {
            $_SESSION['error'] = 'All required fields must be filled.';
            header('Location: ../admin/manage_schedules.php');
            exit;
        }

        if (strtotime($_POST['start_time']) >= strtotime($_POST['end_time'])) {
            $_SESSION['error'] = 'End time must be after start time.';
            header('Location: ../admin/manage_schedules.php');
            exit;
        }

        $doctor_id = (int)$_POST['doctor_id'];
        $schedule_date = sanitize($_POST['schedule_date']);
        $start_time = sanitize($_POST['start_time']);
        $end_time = sanitize($_POST['end_time']);
        $slots = (int)($_POST['slots'] ?: 20);

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
            $_SESSION['error'] = 'Schedule conflicts with existing schedule.';
            header('Location: ../admin/manage_schedules.php');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO doctor_schedule (doctor_id, schedule_date, start_time, end_time, slots, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$doctor_id, $schedule_date, $start_time, $end_time, $slots]);

        $stmt = $pdo->prepare("SELECT fullname FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch();

        logActivity($pdo, $admin_id, "Added schedule for Dr. {$doctor['fullname']} on {$schedule_date}");

        $_SESSION['success'] = 'Schedule added successfully.';
        header('Location: ../admin/manage_schedules.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to add schedule: ' . $e->getMessage();
        header('Location: ../admin/manage_schedules.php');
        exit;
    }
}

if ($action === 'delete_schedule') {
    try {
        $schedule_id = (int)$_GET['id'];

        $stmt = $pdo->prepare("
            SELECT ds.*, d.fullname 
            FROM doctor_schedule ds
            JOIN doctors d ON ds.doctor_id = d.id
            WHERE ds.id = ?
        ");
        $stmt->execute([$schedule_id]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            $_SESSION['error'] = 'Schedule not found.';
            header('Location: ../admin/manage_schedules.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE schedule_id = ?");
        $stmt->execute([$schedule_id]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            $_SESSION['error'] = 'Cannot delete schedule with existing appointments.';
            header('Location: ../admin/manage_schedules.php');
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM doctor_schedule WHERE id = ?");
        $stmt->execute([$schedule_id]);

        logActivity($pdo, $admin_id, "Deleted schedule for Dr. {$schedule['fullname']}");

        $_SESSION['success'] = 'Schedule deleted successfully.';
        header('Location: ../admin/manage_schedules.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete schedule: ' . $e->getMessage();
        header('Location: ../admin/manage_schedules.php');
        exit;
    }
}

// ==========================================
// APPOINTMENT MANAGEMENT
// ==========================================

if ($action === 'update_appointment_status') {
    try {
        $appointment_id = (int)$_POST['appointment_id'];
        $status = sanitize($_POST['status']);

        $valid_statuses = ['pending', 'approved', 'cancelled', 'completed'];
        if (!in_array($status, $valid_statuses)) {
            $_SESSION['error'] = 'Invalid status.';
            header('Location: ../admin/manage_appointments.php');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $appointment_id]);

        $stmt = $pdo->prepare("
            SELECT a.*, u.fullname as patient_name, d.fullname as doctor_name
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $apt = $stmt->fetch();

        logActivity($pdo, $admin_id, "Updated appointment #{$appointment_id} status to {$status}");

        $_SESSION['success'] = 'Appointment status updated successfully.';
        header('Location: ../admin/manage_appointments.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to update appointment: ' . $e->getMessage();
        header('Location: ../admin/manage_appointments.php');
        exit;
    }
}

// If no valid action
$_SESSION['error'] = 'Invalid action: ' . htmlspecialchars($action);
header('Location: ../admin/dashboard.php');
exit;
?>
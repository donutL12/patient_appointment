<?php
/**
 * AuthController
 * Handles authentication for all user types (Admin, Doctor, Patient)
 */

class AuthController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
            if (rand(1, 100) === 1) {
        @include_once __DIR__ . '/../cron/cron_tasks.php';
    }
    }

    /**
     * Universal Login Handler
     * Checks Admin, Doctor, and Patient tables
     */
    public function login($data) {
        try {
            // Validate input
            if (empty($data['identifier']) || empty($data['password'])) {
                $_SESSION['error'] = 'Email/Username and password are required.';
                header('Location: ../index.php');
                exit;
            }

            $identifier = trim($data['identifier']);
            $password = $data['password'];

            // Step 1: Try Admin Login (username-based)
            $admin = $this->checkAdminLogin($identifier, $password);
            if ($admin) {
                $this->setAdminSession($admin);
                $this->logActivity($admin['id'], 'Admin logged in');
                header('Location: ../admin/dashboard.php');
                exit;
            }

            // Step 2: Try Doctor Login (email-based)
            $doctor = $this->checkDoctorLogin($identifier, $password);
            if ($doctor) {
                $this->setDoctorSession($doctor);
                header('Location: ../doctor/dashboard.php');
                exit;
            }

            // Step 3: Try Patient Login (email-based)
            $patient = $this->checkPatientLogin($identifier, $password);
            if ($patient) {
                $this->setPatientSession($patient);
                header('Location: ../user/dashboard.php');
                exit;
            }

            // No match found
            $_SESSION['error'] = 'Invalid credentials. Please try again.';
            header('Location: ../index.php');
            exit;

        } catch (PDOException $e) {
            $_SESSION['error'] = 'Login failed. Please try again later.';
            error_log("Login Error: " . $e->getMessage());
            header('Location: ../index.php');
            exit;
        }
    }

    /**
     * Check Admin Login
     */
    private function checkAdminLogin($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            return $admin;
        }
        return false;
    }

    /**
     * Check Doctor Login
     */
    private function checkDoctorLogin($email, $password) {
        // Check if email is valid format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM doctors WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $doctor = $stmt->fetch();

        if ($doctor && isset($doctor['password']) && password_verify($password, $doctor['password'])) {
            return $doctor;
        }
        return false;
    }

    /**
     * Check Patient Login
     */
    private function checkPatientLogin($email, $password) {
        // Check if email is valid format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $patient = $stmt->fetch();

        if ($patient && password_verify($password, $patient['password'])) {
            return $patient;
        }
        return false;
    }

    /**
     * Set Admin Session
     */
    private function setAdminSession($admin) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['fullname'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['user_type'] = 'admin';
    }

    /**
     * Set Doctor Session
     */
    private function setDoctorSession($doctor) {
        $_SESSION['doctor_id'] = $doctor['id'];
        $_SESSION['doctor_name'] = $doctor['fullname'];
        $_SESSION['doctor_email'] = $doctor['email'];
        $_SESSION['doctor_specialization'] = $doctor['specialization'];
        $_SESSION['doctor_photo'] = $doctor['profile_photo'];
        $_SESSION['user_type'] = 'doctor';
    }

    /**
     * Set Patient Session
     */
    private function setPatientSession($patient) {
        $_SESSION['user_id'] = $patient['id'];
        $_SESSION['user_name'] = $patient['fullname'];
        $_SESSION['user_email'] = $patient['email'];
        $_SESSION['user_phone'] = $patient['phone'];
        $_SESSION['user_photo'] = $patient['profile_photo'];
        $_SESSION['user_type'] = 'patient';
    }

    /**
     * Patient Registration
     */
    public function register($data) {
        try {
            // Validate input
            if (empty($data['fullname']) || empty($data['email']) || 
                empty($data['phone']) || empty($data['password'])) {
                $_SESSION['error'] = 'All fields are required.';
                header('Location: ../register.php');
                exit;
            }

            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Invalid email format.';
                header('Location: ../register.php');
                exit;
            }

            // Check if email already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Email already registered.';
                header('Location: ../register.php');
                exit;
            }

            // Validate password strength
            if (strlen($data['password']) < 6) {
                $_SESSION['error'] = 'Password must be at least 6 characters.';
                header('Location: ../register.php');
                exit;
            }

            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (fullname, email, phone, password, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['fullname'],
                $data['email'],
                $data['phone'],
                $hashedPassword
            ]);

            $_SESSION['success'] = 'Registration successful! Please login.';
            header('Location: ../index.php');
            exit;

        } catch (PDOException $e) {
            $_SESSION['error'] = 'Registration failed. Please try again.';
            error_log("Registration Error: " . $e->getMessage());
            header('Location: ../register.php');
            exit;
        }
    }

    /**
     * Forgot Password
     */
    public function forgotPassword($data) {
        try {
            if (empty($data['email'])) {
                $_SESSION['error'] = 'Email is required.';
                header('Location: ../forgot_password.php');
                exit;
            }

            $email = $data['email'];

            // Check if email exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $_SESSION['error'] = 'Email not found.';
                header('Location: ../forgot_password.php');
                exit;
            }

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in database
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET reset_token = ?, reset_token_expiry = ? 
                WHERE email = ?
            ");
            $stmt->execute([$token, $expiry, $email]);

            // TODO: Send email with reset link
            // For now, just show success message
            $_SESSION['success'] = 'Password reset instructions sent to your email.';
            header('Location: ../index.php');
            exit;

        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to process request.';
            error_log("Forgot Password Error: " . $e->getMessage());
            header('Location: ../forgot_password.php');
            exit;
        }
    }

    /**
     * Reset Password
     */
    public function resetPassword($data) {
        try {
            if (empty($data['token']) || empty($data['password'])) {
                $_SESSION['error'] = 'Invalid request.';
                header('Location: ../index.php');
                exit;
            }

            $token = $data['token'];
            $password = $data['password'];

            // Validate token
            $stmt = $this->pdo->prepare("
                SELECT id FROM users 
                WHERE reset_token = ? 
                AND reset_token_expiry > NOW()
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                $_SESSION['error'] = 'Invalid or expired reset token.';
                header('Location: ../index.php');
                exit;
            }

            // Hash new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update password and clear token
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET password = ?, reset_token = NULL, reset_token_expiry = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$hashedPassword, $user['id']]);

            $_SESSION['success'] = 'Password reset successful! Please login.';
            header('Location: ../index.php');
            exit;

        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to reset password.';
            error_log("Reset Password Error: " . $e->getMessage());
            header('Location: ../index.php');
            exit;
        }
    }

    /**
     * Logout
     */
    public function logout() {
        session_destroy();
        header('Location: ../index.php');
        exit;
    }

    /**
     * Log Admin Activity
     */
public function logActivity($admin_id, $action) {
    try {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (admin_id, action, timestamp) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$admin_id, $action]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}
}
?>
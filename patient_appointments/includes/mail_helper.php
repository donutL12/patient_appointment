<?php
/**
 * Enhanced Email Helper using PHPMailer
 * Install PHPMailer via Composer: composer require phpmailer/phpmailer
 */
//includes/mail_helper.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Check if composer autoload exists
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    die('PHPMailer not installed. Run: composer require phpmailer/phpmailer');
}

// Email Configuration - Use environment variables or config file for security
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'your-email@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'your-app-password');
define('FROM_EMAIL', getenv('FROM_EMAIL') ?: 'noreply@healthcare.com');
define('FROM_NAME', getenv('FROM_NAME') ?: 'Healthcare System');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/patient_appointments/');

// Enable debug mode (set to false in production)
define('MAIL_DEBUG', getenv('MAIL_DEBUG') ?: false);

/**
 * Create and configure PHPMailer instance
 */
function getMailerInstance() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        if (MAIL_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Additional settings for better compatibility
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set timeout
        $mail->Timeout = 30;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addReplyTo(FROM_EMAIL, FROM_NAME);
        
        return $mail;
    } catch (Exception $e) {
        error_log("Mailer Configuration Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get email template wrapper
 */
function getEmailTemplate($content, $title = 'Healthcare System') {
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$title}</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                line-height: 1.6; 
                color: #333; 
                background-color: #f4f4f4;
            }
            .email-wrapper {
                max-width: 600px;
                margin: 20px auto;
                background: #ffffff;
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white; 
                padding: 40px 30px; 
                text-align: center;
            }
            .header h1 {
                font-size: 28px;
                margin-bottom: 10px;
                font-weight: 700;
            }
            .content { 
                padding: 40px 30px;
                background: #ffffff;
            }
            .content h2 {
                color: #333;
                font-size: 22px;
                margin-bottom: 20px;
            }
            .content p {
                margin-bottom: 15px;
                color: #555;
                font-size: 15px;
            }
            .button { 
                display: inline-block; 
                padding: 15px 35px; 
                background: #667eea;
                color: white !important; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 25px 0;
                font-weight: 600;
                font-size: 16px;
                transition: background 0.3s;
            }
            .button:hover {
                background: #5568d3;
            }
            .link-text {
                word-break: break-all; 
                color: #667eea;
                font-size: 13px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 5px;
                border-left: 4px solid #667eea;
            }
            .warning-box { 
                background: #fff3cd; 
                border-left: 4px solid #ffc107; 
                padding: 20px; 
                margin: 20px 0;
                border-radius: 5px;
            }
            .warning-box strong {
                display: block;
                margin-bottom: 10px;
                color: #856404;
            }
            .warning-box ul {
                margin-left: 20px;
                color: #856404;
            }
            .warning-box li {
                margin-bottom: 8px;
            }
            .features-box {
                background: #f8f9fa;
                padding: 25px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .features-box h3 {
                color: #333;
                margin-bottom: 15px;
                font-size: 18px;
            }
            .features-box ul {
                margin-left: 20px;
            }
            .features-box li {
                margin-bottom: 10px;
                color: #555;
            }
            .footer { 
                text-align: center; 
                padding: 30px; 
                background: #f8f9fa;
                color: #666; 
                font-size: 13px;
                border-top: 1px solid #e9ecef;
            }
            .footer p {
                margin-bottom: 8px;
            }
            .footer a {
                color: #667eea;
                text-decoration: none;
            }
            @media only screen and (max-width: 600px) {
                .content, .header, .footer {
                    padding: 20px !important;
                }
                .button {
                    display: block;
                    text-align: center;
                }
            }
        </style>
    </head>
    <body>
        <div class='email-wrapper'>
            {$content}
            <div class='footer'>
                <p><strong>&copy; " . date('Y') . " Healthcare Appointment System</strong></p>
                <p>All rights reserved.</p>
                <p style='margin-top: 15px;'>
                    <a href='" . SITE_URL . "'>Visit Website</a> | 
                    <a href='" . SITE_URL . "contact.php'>Contact Support</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Send verification email
 */
function sendVerificationEmail($email, $fullname, $token) {
    $mail = getMailerInstance();
    
    if (!$mail) {
        return false;
    }
    
    try {
        // Recipients
        $mail->addAddress($email, $fullname);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - Healthcare System';
        
        $verificationLink = SITE_URL . "verify_email.php?token=" . urlencode($token);
        
        $emailContent = "
            <div class='header'>
                <h1>🎉 Welcome to Healthcare System!</h1>
                <p style='margin-top: 10px; font-size: 16px;'>We're excited to have you on board</p>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($fullname) . ",</h2>
                <p>Thank you for registering with our Healthcare Appointment System. You're just one step away from accessing our services!</p>
                <p>Please verify your email address by clicking the button below:</p>
                <div style='text-align: center;'>
                    <a href='{$verificationLink}' class='button'>✓ Verify Email Address</a>
                </div>
                <p><strong>Or copy and paste this link in your browser:</strong></p>
                <div class='link-text'>{$verificationLink}</div>
                <div class='warning-box'>
                    <strong>⏰ Important:</strong>
                    <ul>
                        <li>This verification link will expire in <strong>24 hours</strong></li>
                        <li>If you didn't create an account, please ignore this email</li>
                        <li>Do not share this link with anyone</li>
                    </ul>
                </div>
                <p>If you have any questions, feel free to contact our support team.</p>
            </div>
        ";
        
        $mail->Body = getEmailTemplate($emailContent, 'Verify Your Email');
        $mail->AltBody = "Hello {$fullname},\n\nWelcome to Healthcare Appointment System!\n\nPlease verify your email by visiting: {$verificationLink}\n\nThis link expires in 24 hours.\n\nIf you didn't create an account, please ignore this email.";
        
        $mail->send();
        error_log("Verification email sent successfully to: " . $email);
        return true;
        
    } catch (Exception $e) {
        error_log("Email Error (Verification): " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $fullname, $token) {
    $mail = getMailerInstance();
    
    if (!$mail) {
        return false;
    }
    
    try {
        // Recipients
        $mail->addAddress($email, $fullname);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Healthcare System';
        
        $resetLink = SITE_URL . "reset_password.php?token=" . urlencode($token);
        
        $emailContent = "
            <div class='header'>
                <h1>🔐 Password Reset Request</h1>
                <p style='margin-top: 10px; font-size: 16px;'>We received a request to reset your password</p>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($fullname) . ",</h2>
                <p>Someone requested a password reset for your Healthcare System account. If this was you, click the button below to reset your password:</p>
                <div style='text-align: center;'>
                    <a href='{$resetLink}' class='button'>Reset Password</a>
                </div>
                <p><strong>Or copy and paste this link in your browser:</strong></p>
                <div class='link-text'>{$resetLink}</div>
                <div class='warning-box'>
                    <strong>⚠️ Security Notice:</strong>
                    <ul>
                        <li>This link will expire in <strong>1 hour</strong></li>
                        <li>If you didn't request this, please ignore this email - your password won't change</li>
                        <li>Your password remains unchanged until you create a new one</li>
                        <li>Never share this link with anyone</li>
                    </ul>
                </div>
                <p>If you're having trouble, please contact our support team immediately.</p>
            </div>
        ";
        
        $mail->Body = getEmailTemplate($emailContent, 'Password Reset Request');
        $mail->AltBody = "Hello {$fullname},\n\nWe received a password reset request for your account.\n\nReset your password by visiting: {$resetLink}\n\nThis link expires in 1 hour.\n\nIf you didn't request this, please ignore this email.";
        
        $mail->send();
        error_log("Password reset email sent successfully to: " . $email);
        return true;
        
    } catch (Exception $e) {
        error_log("Email Error (Password Reset): " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send welcome email after verification
 */
function sendWelcomeEmail($email, $fullname) {
    $mail = getMailerInstance();
    
    if (!$mail) {
        return false;
    }
    
    try {
        // Recipients
        $mail->addAddress($email, $fullname);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = '🎊 Welcome to Healthcare System!';
        
        $loginLink = SITE_URL . "index.php";
        $dashboardLink = SITE_URL . "user/dashboard.php";
        
        $emailContent = "
            <div class='header'>
                <h1>🎉 Welcome Aboard!</h1>
                <p style='margin-top: 10px; font-size: 16px;'>Your email has been verified successfully</p>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($fullname) . ",</h2>
                <p><strong>Great news!</strong> Your email has been verified and your account is now fully active. Welcome to Healthcare Appointment System! 🏥</p>
                
                <div class='features-box'>
                    <h3>🌟 What you can do now:</h3>
                    <ul>
                        <li><strong>📅 Book Appointments</strong> - Schedule appointments with available doctors</li>
                        <li><strong>💬 Direct Messaging</strong> - Communicate with your healthcare providers</li>
                        <li><strong>📊 Track History</strong> - View all your past and upcoming appointments</li>
                        <li><strong>👤 Manage Profile</strong> - Update your personal information anytime</li>
                        <li><strong>🔔 Get Notifications</strong> - Receive reminders for your appointments</li>
                    </ul>
                </div>
                
                <p>Ready to get started? Login to your dashboard now:</p>
                <div style='text-align: center;'>
                    <a href='{$loginLink}' class='button'>🚀 Login to Dashboard</a>
                </div>
                
                <div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3;'>
                    <p style='margin: 0; color: #1976D2;'><strong>💡 Pro Tip:</strong> Complete your profile information to help doctors provide better care tailored to your needs.</p>
                </div>
                
                <p>If you have any questions or need assistance, our support team is here to help!</p>
            </div>
        ";
        
        $mail->Body = getEmailTemplate($emailContent, 'Welcome to Healthcare System');
        $mail->AltBody = "Hello {$fullname},\n\nWelcome to Healthcare Appointment System!\n\nYour email has been verified successfully. You can now:\n- Book appointments with doctors\n- Message healthcare providers\n- View appointment history\n- Manage your profile\n\nLogin now: {$loginLink}\n\nThank you for joining us!";
        
        $mail->send();
        error_log("Welcome email sent successfully to: " . $email);
        return true;
        
    } catch (Exception $e) {
        error_log("Email Error (Welcome): " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send appointment confirmation email
 */
function sendAppointmentConfirmation($email, $fullname, $appointmentDetails) {
    $mail = getMailerInstance();
    
    if (!$mail) {
        return false;
    }
    
    try {
        $mail->addAddress($email, $fullname);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation - Healthcare System';
        
        $emailContent = "
            <div class='header'>
                <h1>✅ Appointment Confirmed</h1>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($fullname) . ",</h2>
                <p>Your appointment has been confirmed! Here are the details:</p>
                <div class='features-box'>
                    <p><strong>Doctor:</strong> " . htmlspecialchars($appointmentDetails['doctor'] ?? 'N/A') . "</p>
                    <p><strong>Date:</strong> " . htmlspecialchars($appointmentDetails['date'] ?? 'N/A') . "</p>
                    <p><strong>Time:</strong> " . htmlspecialchars($appointmentDetails['time'] ?? 'N/A') . "</p>
                    <p><strong>Reason:</strong> " . htmlspecialchars($appointmentDetails['reason'] ?? 'N/A') . "</p>
                </div>
                <p>Please arrive 10 minutes early for check-in.</p>
            </div>
        ";
        
        $mail->Body = getEmailTemplate($emailContent, 'Appointment Confirmation');
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email Error (Appointment Confirmation): " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Test email configuration
 */
function testEmailConfiguration() {
    $mail = getMailerInstance();
    
    if (!$mail) {
        return ['success' => false, 'message' => 'Failed to create mailer instance'];
    }
    
    try {
        // Try to connect to SMTP server
        $mail->addAddress(SMTP_USERNAME, 'Test User');
        $mail->Subject = 'Test Email - Healthcare System';
        $mail->Body = 'This is a test email to verify PHPMailer configuration.';
        
        $mail->send();
        return ['success' => true, 'message' => 'Email configuration is working correctly!'];
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Email configuration failed: ' . $mail->ErrorInfo,
            'exception' => $e->getMessage()
        ];
    }
}
?>
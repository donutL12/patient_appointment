<?php
session_start();
require_once 'config/db.php';
require_once 'controllers/AuthController.php';

//verify_email.php
$token = $_GET['token'] ?? '';
$authController = new AuthController($pdo);
$result = $authController->verifyEmail($token);

$success = $result['success'];
$message = $result['message'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Healthcare System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#1e40af',
                        'secondary-blue': '#3b82f6',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Verification Card -->
        <div class="bg-white rounded-3xl shadow-2xl p-8 sm:p-10 text-center transform transition-all duration-500 hover:shadow-3xl">
            <?php if ($success): ?>
                <!-- Success State -->
                <div class="mb-6 animate-bounce-slow">
                    <div class="inline-block p-5 bg-gradient-to-br from-green-400 to-green-600 rounded-full mb-4 shadow-lg">
                        <i class="fas fa-check-circle text-white text-6xl"></i>
                    </div>
                    <h1 class="text-3xl font-extrabold text-gray-900 mb-3">Email Verified! 🎉</h1>
                    <p class="text-gray-600 text-lg mb-6"><?= htmlspecialchars($message) ?></p>
                    
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
                        <p class="text-green-800 font-medium">
                            <i class="fas fa-envelope-open-text mr-2"></i>
                            A welcome email has been sent to your inbox!
                        </p>
                    </div>

                    <div class="space-y-3">
                        <a href="index.php" 
                           class="block w-full bg-primary-blue text-white px-8 py-4 rounded-xl font-bold text-lg hover:bg-blue-800 transition duration-300 shadow-lg transform hover:scale-105 active:scale-95">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login to Your Account
                        </a>
                        
                        <p class="text-sm text-gray-500 mt-4">
                            <i class="fas fa-lightbulb mr-1 text-yellow-500"></i>
                            <strong>Next Steps:</strong> Login and complete your profile for a better experience
                        </p>
                    </div>
                </div>

            <?php else: ?>
                <!-- Error State -->
                <div class="mb-6">
                    <div class="inline-block p-5 bg-gradient-to-br from-red-400 to-red-600 rounded-full mb-4 shadow-lg">
                        <i class="fas fa-times-circle text-white text-6xl"></i>
                    </div>
                    <h1 class="text-3xl font-extrabold text-gray-900 mb-3">Verification Failed</h1>
                    <p class="text-gray-600 text-lg mb-6"><?= htmlspecialchars($message) ?></p>
                    
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                        <p class="text-red-800 font-medium mb-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Common Issues:</strong>
                        </p>
                        <ul class="text-sm text-red-700 text-left space-y-2">
                            <li><i class="fas fa-clock mr-2"></i>Link expired (24-hour validity)</li>
                            <li><i class="fas fa-check-double mr-2"></i>Email already verified</li>
                            <li><i class="fas fa-link mr-2"></i>Incorrect or incomplete link</li>
                        </ul>
                    </div>

                    <div class="space-y-3">
                        <a href="index.php" 
                           class="block w-full bg-primary-blue text-white px-8 py-4 rounded-xl font-bold text-lg hover:bg-blue-800 transition duration-300 shadow-lg transform hover:scale-105 active:scale-95">
                            <i class="fas fa-home mr-2"></i>Go to Home
                        </a>
                        
                        <a href="register.php" 
                           class="block w-full bg-gray-200 text-gray-700 px-8 py-4 rounded-xl font-bold text-lg hover:bg-gray-300 transition duration-300 shadow transform hover:scale-105 active:scale-95">
                            <i class="fas fa-user-plus mr-2"></i>Register Again
                        </a>

                        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                            <p class="text-blue-800 text-sm">
                                <i class="fas fa-headset mr-2"></i>
                                <strong>Need Help?</strong> Contact our support team with your email address
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-gray-600 text-sm mb-2">
                <i class="fas fa-shield-alt mr-1 text-primary-blue"></i>
                Your data is secure and encrypted
            </p>
            <p class="text-gray-500 text-xs">
                &copy; <?= date('Y') ?> Healthcare Appointment System. All rights reserved.
            </p>
        </div>
    </div>

    <style>
        @keyframes bounce-slow {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        .animate-bounce-slow {
            animation: bounce-slow 2s ease-in-out infinite;
        }
    </style>
</body>
</html>
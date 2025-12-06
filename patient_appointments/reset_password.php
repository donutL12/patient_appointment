<?php
session_start();
require_once 'config/db.php';
//reset_password.php
$token = $_GET['token'] ?? '';
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

if (!$token) {
    $_SESSION['error'] = 'Invalid or missing reset token.';
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Healthcare System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-block p-3 bg-blue-600 rounded-full mb-4">
                <i class="fas fa-lock text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">Reset Password</h1>
            <p class="text-gray-600 mt-2">Enter your new password</p>
        </div>

        <!-- Reset Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded">
                    <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form action="routes/routes.php" method="POST" id="resetForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <!-- New Password -->
                <div class="mb-5">
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-lock mr-2 text-blue-600"></i>New Password
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                               placeholder="Enter new password"
                               minlength="8">
                        <button type="button" onclick="togglePassword('password', 'toggleIcon1')" 
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Minimum 8 characters</p>
                </div>

                <!-- Confirm Password -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-lock mr-2 text-blue-600"></i>Confirm Password
                    </label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                               placeholder="Re-enter new password"
                               minlength="8">
                        <button type="button" onclick="togglePassword('confirm_password', 'toggleIcon2')" 
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200 shadow-lg hover:shadow-xl mb-4">
                    <i class="fas fa-check mr-2"></i>Reset Password
                </button>

                <!-- Back to Login -->
                <a href="index.php" 
                   class="block text-center text-blue-600 hover:text-blue-800 font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Login
                </a>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>
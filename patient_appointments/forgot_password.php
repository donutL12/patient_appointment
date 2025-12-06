<?php
session_start();
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
//forgot_password.php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Healthcare System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-block p-3 bg-blue-600 rounded-full mb-4">
                <i class="fas fa-key text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">Forgot Password?</h1>
            <p class="text-gray-600 mt-2">Enter your email to reset your password</p>
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

            <form action="routes/routes.php" method="POST">
                <input type="hidden" name="action" value="forgot_password">
                
                <!-- Email Field -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-envelope mr-2 text-blue-600"></i>Email Address
                    </label>
                    <input type="email" name="email" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                           placeholder="Enter your registered email">
                    <p class="text-sm text-gray-500 mt-2">
                        We'll send a password reset link to this email address
                    </p>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200 shadow-lg hover:shadow-xl mb-4">
                    <i class="fas fa-paper-plane mr-2"></i>Send Reset Link
                </button>

                <!-- Back to Login -->
                <a href="index.php" 
                   class="block text-center text-blue-600 hover:text-blue-800 font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Login
                </a>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-gray-600 text-sm">
            <p>&copy; 2024 Healthcare Appointment System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
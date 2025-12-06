<?php
session_start();
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: user/dashboard.php');
    exit;
} elseif (isset($_SESSION['doctor_id'])) {
    header('Location: doctor/dashboard.php');
    exit;
} elseif (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

// Fetch system settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    $system_name = $settings['system_name'] ?? 'Healthcare Appointment System';
    $system_logo = $settings['system_logo'] ?? '';
} catch (PDOException $e) {
    $system_name = 'Healthcare Appointment System';
    $system_logo = '';
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($system_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-white': '#FFFFFF',
                        'brand-orange': '#F1824A',
                        'brand-teal': '#164A41',
                        'brand-green': '#4D774E',
                        'brand-light-green': '#9DC88D',
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Poppins', sans-serif; }
        
        body {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            min-height: 100vh;
        }
        
        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .brand-panel {
            background: linear-gradient(135deg, #164A41 0%, #4D774E 100%);
            position: relative;
            overflow: hidden;
        }
        
        .brand-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .floating-animation {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }
        
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .input-field {
            background: #F5F5F5;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            background: white;
            border-color: #F1824A;
            box-shadow: 0 0 0 4px rgba(241, 130, 74, 0.1);
            outline: none;
        }
        
        .btn-signin {
            background: linear-gradient(135deg, #FF6B6B 0%, #F1824A 100%);
            transition: all 0.3s ease;
        }
        
        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(241, 130, 74, 0.4);
        }
        
        .btn-signin:active {
            transform: translateY(0);
        }
        
        .logo-container {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .decorative-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            animation: float-slow 20s ease-in-out infinite;
        }
        
        @keyframes float-slow {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }
        
        .alert {
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    
    <div class="login-container max-w-6xl w-full flex flex-col lg:flex-row fade-in">
        
        <!-- Left Panel - Brand Information (Hidden on mobile) -->
        <div class="brand-panel hidden lg:flex lg:w-5/12 p-8 lg:p-12 items-center justify-center relative min-h-[300px] lg:min-h-[600px]">
            <!-- Decorative circles -->
            <div class="decorative-circle" style="width: 300px; height: 300px; top: -100px; left: -100px;"></div>
            <div class="decorative-circle" style="width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: 5s;"></div>
            
            <div class="relative z-10 text-center text-white">
                <!-- Logo -->
                <div class="logo-container mx-auto mb-6 floating-animation">
                    <?php if (!empty($system_logo) && file_exists("public/images/" . $system_logo)): ?>
                        <img src="public/images/<?= htmlspecialchars($system_logo) ?>?v=<?= time() ?>" 
                             alt="<?= htmlspecialchars($system_name) ?>" 
                             class="w-full h-full object-contain p-4">
                    <?php else: ?>
                        <i class="fas fa-heartbeat text-6xl text-white opacity-90"></i>
                    <?php endif; ?>
                </div>
                
                <!-- System Name -->
                <h1 class="text-3xl lg:text-4xl font-bold mb-4">
                    <?= htmlspecialchars($system_name) ?>
                </h1>
                
                <!-- Description -->
                <p class="text-lg lg:text-xl text-white opacity-90 mb-6 leading-relaxed max-w-md mx-auto">
                    Your trusted partner in managing medical appointments with ease and efficiency
                </p>
                
                <!-- Features -->
                <div class="space-y-3 text-left max-w-sm mx-auto">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-calendar-check text-white"></i>
                        </div>
                        <span class="text-white opacity-90">Easy appointment booking</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-md text-white"></i>
                        </div>
                        <span class="text-white opacity-90">Connect with specialists</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-shield-alt text-white"></i>
                        </div>
                        <span class="text-white opacity-90">Secure & confidential</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Login Form -->
        <div class="w-full lg:w-7/12 p-8 lg:p-12 flex items-center justify-center bg-white">
            <div class="w-full max-w-md">
                
                <!-- Mobile Brand Header (Only visible on mobile) -->
                <div class="lg:hidden mb-8 text-center">
                    <div class="w-20 h-20 bg-gradient-to-br from-brand-teal to-brand-green rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-lg p-3">
                        <?php if (!empty($system_logo) && file_exists("public/images/" . $system_logo)): ?>
                            <img src="public/images/<?= htmlspecialchars($system_logo) ?>?v=<?= time() ?>" 
                                 alt="<?= htmlspecialchars($system_name) ?>" 
                                 class="w-full h-full object-contain">
                        <?php else: ?>
                            <i class="fas fa-heartbeat text-4xl text-white"></i>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">
                        <?= htmlspecialchars($system_name) ?>
                    </h1>
                    <p class="text-gray-600 text-sm">
                        Your trusted partner in managing medical appointments
                    </p>
                </div>
                
                <!-- Header (Only visible on desktop) -->
                <div class="hidden lg:block mb-8">
                    <h2 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">Welcome Back!</h2>
                    <p class="text-gray-500 text-base">Sign in to continue to your account</p>
                </div>

                <!-- Alerts -->
                <div id="alertContainer">
                    <?php if ($error): ?>
                        <div class="alert mb-6 p-4 bg-red-50 border-red-200 text-red-700 border rounded-xl text-sm flex items-start space-x-3" role="alert">
                            <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0"></i>
                            <span class="flex-1"><?= htmlspecialchars($error) ?></span>
                            <button onclick="this.parentElement.remove()" class="text-current hover:opacity-70 transition">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert mb-6 p-4 bg-green-50 border-green-200 text-green-700 border rounded-xl text-sm flex items-start space-x-3" role="alert">
                            <i class="fas fa-check-circle mt-0.5 flex-shrink-0"></i>
                            <span class="flex-1"><?= htmlspecialchars($success) ?></span>
                            <button onclick="this.parentElement.remove()" class="text-current hover:opacity-70 transition">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Login Form -->
                <form action="routes/routes.php" method="POST" class="space-y-5">
                    <input type="hidden" name="action" value="login">
                    
                    <!-- Username -->
                    <div>
                        <label for="identifier" class="block text-sm font-semibold text-gray-700 mb-2">
                            Username
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   name="identifier" 
                                   id="identifier" 
                                   required 
                                   autocomplete="username"
                                   class="input-field w-full pl-12 pr-4 py-3.5 rounded-xl text-gray-700 placeholder-gray-400"
                                   placeholder="Enter your username">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   required 
                                   autocomplete="current-password"
                                   class="input-field w-full pl-12 pr-12 py-3.5 rounded-xl text-gray-700 placeholder-gray-400"
                                   placeholder="Enter your password">
                            <button type="button" 
                                    onclick="togglePassword()" 
                                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-brand-orange transition">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center cursor-pointer group">
                            <input type="checkbox" name="remember" class="w-4 h-4 text-brand-orange border-gray-300 rounded focus:ring-brand-orange">
                            <span class="ml-2 text-gray-600 group-hover:text-gray-800">Remember me</span>
                        </label>
                        <a href="forgot_password.php" class="text-brand-orange hover:text-brand-teal transition font-medium">
                            Forgot password?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="btn-signin w-full text-white py-3.5 rounded-xl font-semibold text-base shadow-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </form>

                <!-- Register Link -->
                <div class="mt-8 text-center">
                    <p class="text-gray-600">
                        Don't have an account? 
                        <a href="register.php" class="text-brand-orange font-semibold hover:text-brand-teal transition ml-1">
                            Register now
                        </a>
                    </p>
                </div>
                
            </div>
        </div>
        
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
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

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
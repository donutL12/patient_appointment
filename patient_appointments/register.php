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
    <title>Register - <?= htmlspecialchars($system_name) ?></title>
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
        
        .btn-register {
            background: linear-gradient(135deg, #FF6B6B 0%, #F1824A 100%);
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(241, 130, 74, 0.4);
        }
        
        .btn-register:active {
            transform: translateY(0);
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

        /* Hide default select arrow */
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-3xl w-full fade-in">
        
        <!-- Desktop Header with Logo (Hidden on mobile) -->
        <div class="hidden md:flex items-center justify-center mb-8">
            <div class="flex items-center space-x-4 bg-white px-6 py-4 rounded-2xl shadow-lg">
                <?php if (!empty($system_logo) && file_exists("public/images/" . $system_logo)): ?>
                    <div class="w-14 h-14 bg-gradient-to-br from-brand-teal to-brand-green rounded-xl flex items-center justify-center p-2">
                        <img src="public/images/<?= htmlspecialchars($system_logo) ?>?v=<?= time() ?>" 
                             alt="<?= htmlspecialchars($system_name) ?>" 
                             class="w-full h-full object-contain">
                    </div>
                <?php else: ?>
                    <div class="w-14 h-14 bg-gradient-to-br from-brand-teal to-brand-green rounded-xl flex items-center justify-center">
                        <i class="fas fa-heartbeat text-white text-2xl"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($system_name) ?></h1>
                    <p class="text-sm text-gray-500">Patient Registration Portal</p>
                </div>
            </div>
        </div>
        
        <!-- Registration Form Card -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <div class="p-6 sm:p-8 md:p-10">
                
                <!-- Header -->
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Create Account</h2>
                    <p class="text-gray-600">Join us today and start managing your healthcare appointments</p>
                </div>

                <!-- Alerts -->
                <div id="alertContainer">
                    <?php if ($error): ?>
                        <div class="alert mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-start space-x-3" role="alert">
                            <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0"></i>
                            <span class="flex-1"><?= htmlspecialchars($error) ?></span>
                            <button onclick="this.parentElement.remove()" class="text-current hover:opacity-70 transition">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm flex items-start space-x-3" role="alert">
                            <i class="fas fa-check-circle mt-0.5 flex-shrink-0"></i>
                            <span class="flex-1"><?= htmlspecialchars($success) ?></span>
                            <button onclick="this.parentElement.remove()" class="text-current hover:opacity-70 transition">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <form action="routes/routes.php" method="POST" id="registerForm" class="space-y-5">
                    <input type="hidden" name="action" value="register">
                    
                    <!-- Full Name -->
                    <div>
                        <label for="fullname" class="block text-sm font-semibold text-gray-700 mb-2">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   name="fullname" 
                                   id="fullname" 
                                   required 
                                   class="input-field w-full pl-12 pr-4 py-3.5 rounded-xl text-gray-700 placeholder-gray-400"
                                   placeholder="Enter your full name"
                                   pattern="[A-Za-z\s]+" 
                                   title="Only letters and spaces allowed">
                        </div>
                    </div>

                    <!-- Email & Phone -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input type="email" 
                                       name="email" 
                                       id="email" 
                                       required 
                                       class="input-field w-full pl-12 pr-4 py-3.5 rounded-xl text-gray-700 placeholder-gray-400"
                                       placeholder="your@email.com">
                            </div>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">
                                Phone Number <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-phone text-gray-400"></i>
                                </div>
                                <input type="tel" 
                                       name="phone" 
                                       id="phone" 
                                       required 
                                       class="input-field w-full pl-12 pr-4 py-3.5 rounded-xl text-gray-700 placeholder-gray-400"
                                       placeholder="+1 555 123 4567"
                                       pattern="[0-9+\-\s()]+" 
                                       title="Enter a valid phone number">
                            </div>
                        </div>
                    </div>
                    
                    <!-- DOB & Gender -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="dob" class="block text-sm font-semibold text-gray-700 mb-2">
                                Date of Birth <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-calendar-alt text-gray-400"></i>
                                </div>
                                <input type="date" 
                                       name="dob" 
                                       id="dob" 
                                       required 
                                       class="input-field w-full pl-12 pr-4 py-3.5 rounded-xl text-gray-700"
                                       max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <div>
                            <label for="gender" class="block text-sm font-semibold text-gray-700 mb-2">
                                Gender <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-venus-mars text-gray-400"></i>
                                </div>
                                <select name="gender" 
                                        id="gender" 
                                        required 
                                        class="input-field w-full pl-12 pr-4 py-3.5 rounded-xl text-gray-700 cursor-pointer appearance-none">
                                    <option value="" disabled selected>Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address -->
                    <div>
                        <label for="address" class="block text-sm font-semibold text-gray-700 mb-2">
                            Address <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute top-3.5 left-0 pl-4 pointer-events-none">
                                <i class="fas fa-map-marker-alt text-gray-400"></i>
                            </div>
                            <textarea name="address" 
                                      id="address" 
                                      required 
                                      rows="2"
                                      class="input-field w-full pl-12 pr-4 py-3.5 rounded-xl text-gray-700 placeholder-gray-400"
                                      placeholder="Street address, City, State, Zip Code"></textarea>
                        </div>
                    </div>

                    <!-- Password & Confirm Password -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       required 
                                       class="input-field w-full pl-12 pr-12 py-3.5 rounded-xl text-gray-700 placeholder-gray-400"
                                       placeholder="Create password"
                                       minlength="8">
                                <button type="button" 
                                        onclick="togglePassword('password', 'toggleIcon1')" 
                                        class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-brand-orange transition">
                                    <i class="fas fa-eye" id="toggleIcon1"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Min. 8 characters</p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirm_password" 
                                       required 
                                       class="input-field w-full pl-12 pr-12 py-3.5 rounded-xl text-gray-700 placeholder-gray-400"
                                       placeholder="Re-enter password"
                                       minlength="8">
                                <button type="button" 
                                        onclick="togglePassword('confirm_password', 'toggleIcon2')" 
                                        class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-brand-orange transition">
                                    <i class="fas fa-eye" id="toggleIcon2"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="pt-2">
                        <label class="flex items-start cursor-pointer group">
                            <input type="checkbox" 
                                   name="terms" 
                                   required 
                                   class="w-4 h-4 text-brand-orange border-gray-300 rounded focus:ring-brand-orange mt-1 flex-shrink-0">
                            <span class="ml-3 text-sm text-gray-700 leading-relaxed">
                                I agree to the <a href="#" class="text-brand-orange hover:text-brand-teal font-semibold transition">Terms and Conditions</a> 
                                and <a href="#" class="text-brand-orange hover:text-brand-teal font-semibold transition">Privacy Policy</a>
                                <span class="text-red-500">*</span>
                            </span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="btn-register w-full text-white py-3.5 rounded-xl font-semibold text-base shadow-lg">
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </button>
                </form>

                <!-- Sign In Link -->
                <div class="mt-8 text-center">
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="index.php" class="text-brand-orange font-semibold hover:text-brand-teal transition ml-1">
                            Sign In
                        </a>
                    </p>
                </div>
                
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-gray-600 text-sm">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($system_name) ?>. All rights reserved.</p>
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

        // Password validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match! Please ensure both password fields are the same.');
                document.getElementById('confirm_password').focus();
                return false;
            }
        });

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
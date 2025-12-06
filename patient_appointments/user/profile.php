<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullname = sanitize($_POST['fullname']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
       $dob = sanitize($_POST['date_of_birth']);
        $gender = sanitize($_POST['gender']);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, dob = ?, gender = ? WHERE id = ?");
            $stmt->execute([$fullname, $email, $phone, $address, $dob, $gender, $user_id]);
            
            $_SESSION['user_name'] = $fullname;
            $_SESSION['user_email'] = $email;
            
            $success_message = "Profile updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_photo']) && isset($_FILES['profile_photo'])) {
        $file = $_FILES['profile_photo'];
        
        if ($file['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $file['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                if ($file['size'] <= 5000000) {
                    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_dir = __DIR__ . '/../public/uploads/';
                    
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $old_photo = $stmt->fetchColumn();
                        
                        if ($old_photo && $old_photo !== 'default.png') {
                            $old_path = $upload_dir . $old_photo;
                            if (file_exists($old_path)) {
                                unlink($old_path);
                            }
                        }
                        
                        $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                        $stmt->execute([$new_filename, $user_id]);
                        
                        $_SESSION['user_photo'] = $new_filename;
                        $success_message = "Profile photo updated successfully!";
                    } else {
                        $error_message = "Failed to upload file. Please check folder permissions.";
                    }
                } else {
                    $error_message = "File size too large. Maximum 5MB.";
                }
            } else {
                $error_message = "Invalid file type. Only JPG, JPEG, PNG, and GIF allowed.";
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password === $confirm_password) {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if (password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } catch (PDOException $e) {
                $error_message = "Error changing password: " . $e->getMessage();
            }
        } else {
            $error_message = "New passwords do not match.";
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

$page_title = "Profile Settings";
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? '';
$user_photo = $_SESSION['user_photo'] ?? 'default.png';

include '../includes/header.php';
?>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar w-64 bg-white fixed left-0 top-14 sm:top-16 h-[calc(100vh-3.5rem)] sm:h-[calc(100vh-4rem)] sidebar-transition shadow-lg z-40 border-r border-gray-200 flex flex-col overflow-hidden">
    <!-- User Profile Section -->
    <div class="p-4 sm:p-5 border-b border-gray-200 flex-shrink-0 bg-gradient-to-br from-[#4D774E] to-[#164A41]">
        <div class="flex flex-col items-center">
            <div class="relative mb-2">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full overflow-hidden ring-4 ring-[#9DC88D]/30 shadow-md">
                    <?php if ($user_photo && $user_photo !== 'default.png' && file_exists("../public/uploads/$user_photo")): ?>
                        <img src="../public/uploads/<?= htmlspecialchars($user_photo) ?>" 
                             alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-br from-[#F1824A] to-[#164A41] flex items-center justify-center">
                            <span class="text-white text-2xl sm:text-3xl font-bold">
                                <?= strtoupper(substr($user_name, 0, 1)) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="absolute bottom-0 right-0 w-4 h-4 bg-[#9DC88D] rounded-full border-3 border-white shadow-sm"></div>
            </div>
            <div class="text-center w-full mt-1">
                <h3 class="text-white font-bold text-base sm:text-lg truncate">
                    <?= htmlspecialchars(explode(' ', $user_name)[0]) ?>
                </h3>
                <p class="text-[#9DC88D] text-xs sm:text-sm truncate"><?= htmlspecialchars($user_email) ?></p>
                <span class="inline-block mt-2 px-3 py-1 bg-[#9DC88D]/20 text-[#9DC88D] text-xs rounded-full font-semibold shadow-sm border border-[#9DC88D]/30">
                    <i class="fas fa-circle text-[8px] mr-1 animate-pulse"></i>Active
                </span>
            </div>
        </div>
    </div>
    
    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-1">
        <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
            Main Menu
        </div>
        
        <a href="dashboard.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 <?= $current_page === 'dashboard.php' ? 'bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white shadow-lg shadow-[#164A41]/30' : 'text-gray-700 hover:bg-[#9DC88D]/10' ?>">
            <i class="fas fa-home w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Dashboard</span>
        </a>
        
        <a href="appointments.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 <?= $current_page === 'appointments.php' ? 'bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white shadow-lg shadow-[#164A41]/30' : 'text-gray-700 hover:bg-[#9DC88D]/10' ?>">
            <i class="fas fa-calendar-alt w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">My Appointments</span>
        </a>
        
        <a href="book_appointment.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 <?= $current_page === 'book_appointment.php' ? 'bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white shadow-lg shadow-[#164A41]/30' : 'text-gray-700 hover:bg-[#9DC88D]/10' ?>">
            <i class="fas fa-plus-circle w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Book Appointment</span>
        </a>
        
        <a href="messages.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 <?= $current_page === 'messages.php' ? 'bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white shadow-lg shadow-[#164A41]/30' : 'text-gray-700 hover:bg-[#9DC88D]/10' ?>">
            <i class="fas fa-envelope w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Messages</span>
        </a>
        
        <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-4">
            Settings
        </div>
        
        <a href="profile.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 <?= $current_page === 'profile.php' ? 'bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white shadow-lg shadow-[#164A41]/30' : 'text-gray-700 hover:bg-[#9DC88D]/10' ?>">
            <i class="fas fa-user-cog w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Profile Settings</span>
        </a>
        
        <div class="border-t border-gray-200 mt-4 pt-4">
            <a href="../routes/routes.php?action=logout" 
               class="group flex items-center px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 transition-all duration-200">
                <i class="fas fa-sign-out-alt w-5 text-base"></i>
                <span class="ml-3 font-semibold text-sm">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Main Content -->
<div class="ml-0 md:ml-64 pt-14 sm:pt-16 min-h-screen bg-gradient-to-br from-[#FFFFFF] via-[#9DC88D]/10 to-[#4D774E]/10">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-user-cog text-transparent bg-clip-text bg-gradient-to-r from-[#4D774E] to-[#164A41] mr-2"></i>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#4D774E] to-[#164A41]">Profile Settings</span>
                </h1>
                <p class="text-sm sm:text-base text-gray-600">Manage your personal information and account settings</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="mb-6 bg-gradient-to-r from-[#9DC88D]/20 to-[#4D774E]/10 border-2 border-[#9DC88D] rounded-xl p-4 flex items-start shadow-sm animate-fade-in">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[#9DC88D] to-[#4D774E] flex items-center justify-center flex-shrink-0 mr-3 shadow-md">
                        <i class="fas fa-check-circle text-white text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-[#164A41] mb-1">Success!</h3>
                        <p class="text-sm text-gray-700"><?= $success_message ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mb-6 bg-gradient-to-r from-red-50 to-red-100/50 border-2 border-red-300 rounded-xl p-4 flex items-start shadow-sm animate-fade-in">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center flex-shrink-0 mr-3 shadow-md">
                        <i class="fas fa-exclamation-circle text-white text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-red-800 mb-1">Error</h3>
                        <p class="text-sm text-red-700"><?= $error_message ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile Photo Section -->
            <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-camera text-[#4D774E] mr-3"></i>
                        Profile Photo
                    </h2>
                    <div class="w-12 h-12 bg-gradient-to-br from-[#9DC88D] to-[#4D774E] rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-image text-white"></i>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start space-y-6 sm:space-y-0 sm:space-x-8">
                        <div class="relative">
                            <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-2xl overflow-hidden ring-4 ring-[#9DC88D]/30 shadow-xl flex items-center justify-center bg-gradient-to-br from-[#9DC88D] to-[#4D774E]">
                                <?php if ($user['profile_photo'] && $user['profile_photo'] !== 'default.png'): ?>
                                    <img src="../public/uploads/<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile" class="w-full h-full object-cover" id="photoPreview">
                                <?php else: ?>
                                    <span class="text-white text-5xl font-bold" id="photoPlaceholder"><?= strtoupper(substr($user['fullname'], 0, 1)) ?></span>
                                    <img src="" alt="Preview" class="w-full h-full object-cover hidden" id="photoPreview">
                                <?php endif; ?>
                            </div>
                            <div class="absolute -bottom-2 -right-2 w-12 h-12 bg-gradient-to-br from-[#F1824A] to-[#F1824A]/80 rounded-xl shadow-lg flex items-center justify-center border-4 border-white">
                                <i class="fas fa-camera text-white"></i>
                            </div>
                        </div>
                        
                        <div class="flex-1 text-center sm:text-left">
                            <h3 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($user['fullname']) ?></h3>
                            <p class="text-sm text-gray-600 mb-4 flex items-center justify-center sm:justify-start">
                                <i class="fas fa-envelope text-[#4D774E] mr-2"></i>
                                <?= htmlspecialchars($user['email']) ?>
                            </p>
                            <div class="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-3">
                                <input type="file" name="profile_photo" id="profile_photo" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                                <label for="profile_photo" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 text-gray-700 rounded-xl cursor-pointer transition-all duration-200 text-sm font-semibold shadow-sm hover:shadow-md">
                                    <i class="fas fa-upload mr-2"></i>Choose Photo
                                </label>
                                <button type="submit" name="update_photo" class="px-5 py-2.5 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white rounded-xl hover:shadow-lg transition-all duration-300 text-sm font-semibold shadow-md hover:-translate-y-0.5">
                                    <i class="fas fa-save mr-2"></i>Save Photo
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-4 bg-gray-50 rounded-lg p-3 border border-gray-200">
                                <i class="fas fa-info-circle text-[#4D774E] mr-2"></i>JPG, JPEG, PNG or GIF. Maximum file size 5MB.
                            </p>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Personal Information Section -->
            <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-user text-[#4D774E] mr-3"></i>
                        Personal Information
                    </h2>
                    <div class="w-12 h-12 bg-gradient-to-br from-[#9DC88D] to-[#4D774E] rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-id-card text-white"></i>
                    </div>
                </div>
                
                <?php
                $fullnameVal = htmlspecialchars($user['fullname'] ?? '');
                $emailVal = htmlspecialchars($user['email'] ?? '');
                $phoneVal = htmlspecialchars($user['phone'] ?? '');
                $dobVal = htmlspecialchars($user['dob'] ?? ''); // ← Use 'dob'
                $addressVal = htmlspecialchars($user['address'] ?? '');
                $genderVal = $user['gender'] ?? 'male';
                $inputClass = "w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4D774E] focus:border-[#4D774E] transition-all duration-200 text-sm bg-white hover:border-[#9DC88D]";
                ?>
                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-user-circle text-[#4D774E] mr-2"></i>Full Name
                            </label>
                            <input type="text" name="fullname" value="<?= $fullnameVal ?>" required class="<?= $inputClass ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-envelope text-[#4D774E] mr-2"></i>Email Address
                            </label>
                            <input type="email" name="email" value="<?= $emailVal ?>" required class="<?= $inputClass ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-phone text-[#4D774E] mr-2"></i>Phone Number
                            </label>
                            <input type="tel" name="phone" value="<?= $phoneVal ?>" class="<?= $inputClass ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-calendar text-[#4D774E] mr-2"></i>Date of Birth
                            </label>
                            <input type="date" name="date_of_birth" value="<?= $dobVal ?>" class="<?= $inputClass ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-venus-mars text-[#4D774E] mr-2"></i>Gender
                            </label>
                            <select name="gender" class="<?= $inputClass ?>">
                                <option value="male" <?= $genderVal === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= $genderVal === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= $genderVal === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-map-marker-alt text-[#4D774E] mr-2"></i>Address
                            </label>
                            <textarea name="address" rows="3" class="<?= $inputClass ?> resize-none"><?= $addressVal ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3">
                        <button type="button" onclick="window.location.href='dashboard.php'" class="px-6 py-3 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-xl hover:shadow-md transition-all duration-200 font-semibold hover:-translate-y-0.5">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit" name="update_profile" class="px-6 py-3 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white rounded-xl hover:shadow-xl transition-all duration-300 font-semibold shadow-lg hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Settings Section -->
            <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-shield-alt text-[#4D774E] mr-3"></i>
                        Security Settings
                    </h2>
                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-lock text-white"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-[#F1824A]/10 to-[#F1824A]/5 border-2 border-[#F1824A]/30 rounded-xl p-4 mb-6">
                    <div class="flex items-start">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[#F1824A] to-[#F1824A]/80 flex items-center justify-center flex-shrink-0 mr-3 shadow-md">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                        <div class="text-sm">
                            <p class="font-bold text-gray-900 mb-2">Password Security Tips:</p>
                            <ul class="space-y-1 text-gray-700">
                                <li class="flex items-start">
                                    <i class="fas fa-check text-[#4D774E] mr-2 mt-0.5"></i>
                                    <span>Use at least 8 characters</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-[#4D774E] mr-2 mt-0.5"></i>
                                    <span>Include uppercase, lowercase, numbers, and symbols</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-[#4D774E] mr-2 mt-0.5"></i>
                                    <span>Avoid common words or personal information</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <form method="POST">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-key text-red-600 mr-2"></i>Current Password
                            </label>
                            <input type="password" name="current_password" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 text-sm bg-white hover:border-red-300" placeholder="Enter your current password">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-lock text-red-600 mr-2"></i>New Password
                            </label>
                            <input type="password" name="new_password" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 text-sm bg-white hover:border-red-300" placeholder="Enter your new password">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-check-circle text-red-600 mr-2"></i>Confirm New Password
                            </label>
                            <input type="password" name="confirm_password" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 text-sm bg-white hover:border-red-300" placeholder="Confirm your new password">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3">
                        <button type="button" onclick="this.closest('form').reset()" class="px-6 py-3 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-xl hover:shadow-md transition-all duration-200 font-semibold hover:-translate-y-0.5">
                            <i class="fas fa-undo mr-2"></i>Reset
                        </button>
                        <button type="submit" name="change_password" class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:shadow-xl transition-all duration-300 font-semibold shadow-lg hover:-translate-y-0.5">
                            <i class="fas fa-key mr-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const preview = document.getElementById('photoPreview');
        const placeholder = document.getElementById('photoPlaceholder');
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('[class*="animate-fade-in"]');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
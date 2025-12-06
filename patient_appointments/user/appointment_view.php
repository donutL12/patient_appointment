<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$appointment_id = $_GET['id'] ?? null;
$page_title = "Appointment Details";
$current_page = "appointments.php";

if (!$appointment_id) {
    $_SESSION['error'] = 'Invalid appointment ID';
    header('Location: appointments.php');
    exit;
}

// Get appointment details
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            d.fullname as doctor_name,
            d.specialization,
            d.phone as doctor_phone,
            d.email as doctor_email,
            d.profile_photo as doctor_photo,
            u.fullname as patient_name,
            u.email as patient_email,
            u.phone as patient_phone,
            u.dob as patient_dob,
            u.gender as patient_gender,
            u.address as patient_address
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        $_SESSION['error'] = 'Appointment not found';
        header('Location: appointments.php');
        exit;
    }
    
    $appointment['consultation_fee'] = $appointment['consultation_fee'] ?? null;
    $appointment['experience_years'] = $appointment['experience_years'] ?? null;
    
} catch (PDOException $e) {
    error_log("Appointment View Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading appointment details: ' . $e->getMessage();
    header('Location: appointments.php');
    exit;
}

// Calculate appointment status info
$isPast = strtotime($appointment['appointment_date']) < strtotime('today');
$isToday = strtotime($appointment['appointment_date']) === strtotime('today');
$isFuture = strtotime($appointment['appointment_date']) > strtotime('today');

// Get user details for sidebar
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
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-[#9DC88D]/10">
            <i class="fas fa-home w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Dashboard</span>
        </a>
        
        <a href="appointments.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white shadow-lg shadow-[#164A41]/30">
            <i class="fas fa-calendar-alt w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">My Appointments</span>
        </a>
        
        <a href="book_appointment.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-[#9DC88D]/10">
            <i class="fas fa-plus-circle w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Book Appointment</span>
        </a>
        
        <a href="messages.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-[#9DC88D]/10">
            <i class="fas fa-envelope w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Messages</span>
        </a>
        
        <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-4">
            Settings
        </div>
        
        <a href="profile.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-[#9DC88D]/10">
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
    <div class="p-3 sm:p-4 lg:p-6 xl:p-8">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="mb-4 sm:mb-6 no-print">
                <a href="appointments.php" class="text-[#4D774E] hover:text-[#164A41] font-bold text-xs sm:text-sm mb-3 inline-flex items-center bg-white px-4 py-2 rounded-xl shadow-sm hover:shadow-md transition-all duration-200 border border-[#9DC88D]/30">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Appointments
                </a>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-3">
                    <div>
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-1 sm:mb-2">
                            <i class="fas fa-file-medical text-[#F1824A] mr-2"></i>
                            Appointment Details
                        </h1>
                        <p class="text-sm sm:text-base text-gray-600 font-semibold">Reference ID: <span class="text-[#4D774E]">#APT-<?= str_pad($appointment['id'], 6, '0', STR_PAD_LEFT) ?></span></p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="window.print()" 
                                class="flex-1 sm:flex-none px-4 py-2 sm:py-3 bg-white border-2 border-[#9DC88D] text-[#164A41] rounded-xl hover:bg-[#9DC88D]/10 hover:border-[#4D774E] transition-all duration-200 text-sm font-bold shadow-sm hover:shadow-md">
                            <i class="fas fa-print mr-2"></i>
                            <span class="hidden sm:inline">Print</span>
                        </button>
                        <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'approved'): ?>
                            <button onclick="cancelAppointment(<?= $appointment['id'] ?>)" 
                                    class="flex-1 sm:flex-none px-4 py-2 sm:py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:shadow-lg transition-all duration-200 text-sm font-bold transform hover:-translate-y-0.5">
                                <i class="fas fa-times mr-2"></i>
                                <span class="hidden sm:inline">Cancel</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-4 sm:space-y-6">
                    <!-- Status Banner -->
                    <div class="bg-gradient-to-br 
                        <?php 
                            if ($appointment['status'] === 'approved') echo 'from-[#9DC88D]/20 to-[#4D774E]/20';
                            elseif ($appointment['status'] === 'pending') echo 'from-[#F1824A]/20 to-yellow-100';
                            elseif ($appointment['status'] === 'cancelled') echo 'from-red-100 to-red-200';
                            elseif ($appointment['status'] === 'completed') echo 'from-[#164A41]/20 to-[#4D774E]/20';
                        ?> rounded-xl p-5 sm:p-6 border-2 
                        <?php 
                            if ($appointment['status'] === 'approved') echo 'border-[#9DC88D]';
                            elseif ($appointment['status'] === 'pending') echo 'border-[#F1824A]';
                            elseif ($appointment['status'] === 'cancelled') echo 'border-red-300';
                            elseif ($appointment['status'] === 'completed') echo 'border-[#164A41]';
                        ?> shadow-lg">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h2 class="text-base sm:text-xl font-bold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-heartbeat text-[#F1824A] mr-2"></i>
                                    Appointment Status
                                </h2>
                                <span class="inline-block px-5 sm:px-7 py-2 sm:py-3 rounded-xl text-sm sm:text-lg font-bold shadow-md
                                    <?php 
                                        if ($appointment['status'] === 'approved') echo 'bg-gradient-to-r from-[#9DC88D] to-[#4D774E] text-white';
                                        elseif ($appointment['status'] === 'pending') echo 'bg-gradient-to-r from-[#F1824A] to-yellow-500 text-white';
                                        elseif ($appointment['status'] === 'cancelled') echo 'bg-gradient-to-r from-red-500 to-red-600 text-white';
                                        elseif ($appointment['status'] === 'completed') echo 'bg-gradient-to-r from-[#164A41] to-[#4D774E] text-white';
                                    ?>">
                                    <i class="fas 
                                        <?php 
                                            if ($appointment['status'] === 'approved') echo 'fa-check-circle';
                                            elseif ($appointment['status'] === 'pending') echo 'fa-clock';
                                            elseif ($appointment['status'] === 'cancelled') echo 'fa-times-circle';
                                            elseif ($appointment['status'] === 'completed') echo 'fa-check-double';
                                        ?> mr-2"></i>
                                    <?= ucfirst($appointment['status']) ?>
                                </span>
                            </div>
                            <div>
                                <?php if ($isToday): ?>
                                    <span class="inline-block px-4 sm:px-5 py-2 bg-white text-[#4D774E] rounded-xl text-xs sm:text-sm font-bold shadow-md border-2 border-[#9DC88D]">
                                        <i class="fas fa-calendar-day mr-2"></i>Today
                                    </span>
                                <?php elseif ($isFuture): ?>
                                    <span class="inline-block px-4 sm:px-5 py-2 bg-white text-[#164A41] rounded-xl text-xs sm:text-sm font-bold shadow-md border-2 border-[#4D774E]">
                                        <i class="fas fa-calendar-plus mr-2"></i>Upcoming
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment Information -->
                    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5 sm:p-6">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 mb-5 sm:mb-6 flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-br from-[#4D774E] to-[#164A41] rounded-xl flex items-center justify-center mr-3 shadow-md">
                                <i class="fas fa-calendar-check text-white"></i>
                            </div>
                            Appointment Information
                        </h2>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 sm:gap-6">
                            <div class="flex items-start space-x-4 p-4 bg-gradient-to-br from-blue-50 to-blue-100/50 rounded-xl border-2 border-blue-200 hover:shadow-md transition-all duration-200">
                                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-md">
                                    <i class="fas fa-calendar text-white text-lg sm:text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs sm:text-sm text-blue-700 mb-1 font-semibold">Appointment Date</p>
                                    <p class="font-bold text-gray-900 text-sm sm:text-lg truncate">
                                        <?= date('F d, Y', strtotime($appointment['appointment_date'])) ?>
                                    </p>
                                    <p class="text-xs text-blue-600 font-medium">
                                        <?= date('l', strtotime($appointment['appointment_date'])) ?>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-4 p-4 bg-gradient-to-br from-purple-50 to-purple-100/50 rounded-xl border-2 border-purple-200 hover:shadow-md transition-all duration-200">
                                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-md">
                                    <i class="fas fa-clock text-white text-lg sm:text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-xs sm:text-sm text-purple-700 mb-1 font-semibold">Appointment Time</p>
                                    <p class="font-bold text-gray-900 text-sm sm:text-lg">
                                        <?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
                                    </p>
                                    <p class="text-xs text-purple-600 font-medium">
                                        30 minutes duration
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-4 p-4 bg-gradient-to-br from-[#9DC88D]/30 to-[#4D774E]/20 rounded-xl border-2 border-[#9DC88D] hover:shadow-md transition-all duration-200">
                                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-[#9DC88D] to-[#4D774E] rounded-xl flex items-center justify-center flex-shrink-0 shadow-md">
                                    <i class="fas fa-file-alt text-white text-lg sm:text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-xs sm:text-sm text-[#164A41] mb-1 font-semibold">Booking Date</p>
                                    <p class="font-bold text-gray-900 text-sm sm:text-base">
                                        <?= date('M d, Y', strtotime($appointment['created_at'])) ?>
                                    </p>
                                    <p class="text-xs text-[#4D774E] font-medium">
                                        <?= date('h:i A', strtotime($appointment['created_at'])) ?>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-4 p-4 bg-gradient-to-br from-[#F1824A]/20 to-yellow-100/50 rounded-xl border-2 border-[#F1824A]/50 hover:shadow-md transition-all duration-200">
                                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-[#F1824A] to-yellow-500 rounded-xl flex items-center justify-center flex-shrink-0 shadow-md">
                                    <i class="fas fa-hashtag text-white text-lg sm:text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs sm:text-sm text-orange-700 mb-1 font-semibold">Reference Number</p>
                                    <p class="font-bold text-gray-900 text-sm sm:text-base truncate">
                                        APT-<?= str_pad($appointment['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </p>
                                    <p class="text-xs text-orange-600 font-medium">
                                        Keep for reference
                                    </p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($appointment['notes'])): ?>
                            <div class="mt-5 sm:mt-6 p-4 sm:p-5 bg-gradient-to-r from-[#4D774E]/10 to-[#9DC88D]/10 border-l-4 border-[#4D774E] rounded-xl shadow-sm">
                                <p class="text-xs sm:text-sm font-bold text-gray-900 mb-2 flex items-center">
                                    <i class="fas fa-sticky-note text-[#4D774E] mr-2"></i>
                                    Reason for Visit / Notes
                                </p>
                                <p class="text-xs sm:text-sm text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($appointment['notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Patient Information -->
                    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5 sm:p-6">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 mb-5 sm:mb-6 flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-br from-[#F1824A] to-[#164A41] rounded-xl flex items-center justify-center mr-3 shadow-md">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            Patient Information
                        </h2>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs sm:text-sm text-gray-600 mb-1 font-semibold">Full Name</p>
                                <p class="font-bold text-gray-900 text-sm sm:text-base truncate"><?= htmlspecialchars($appointment['patient_name']) ?></p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs sm:text-sm text-gray-600 mb-1 font-semibold">Email Address</p>
                                <p class="font-bold text-gray-900 text-sm sm:text-base truncate"><?= htmlspecialchars($appointment['patient_email']) ?></p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs sm:text-sm text-gray-600 mb-1 font-semibold">Phone Number</p>
                                <p class="font-bold text-gray-900 text-sm sm:text-base"><?= htmlspecialchars($appointment['patient_phone']) ?></p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs sm:text-sm text-gray-600 mb-1 font-semibold">Gender</p>
                                <p class="font-bold text-gray-900 text-sm sm:text-base"><?= ucfirst($appointment['patient_gender'] ?? 'Not specified') ?></p>
                            </div>
                            <?php if ($appointment['patient_dob']): ?>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs sm:text-sm text-gray-600 mb-1 font-semibold">Date of Birth</p>
                                <p class="font-bold text-gray-900 text-sm sm:text-base"><?= date('M d, Y', strtotime($appointment['patient_dob'])) ?></p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs sm:text-sm text-gray-600 mb-1 font-semibold">Age</p>
                                <p class="font-bold text-gray-900 text-sm sm:text-base">
                                    <?= date_diff(date_create($appointment['patient_dob']), date_create('today'))->y ?> years
                                </p>
                            </div>
                            <?php endif; ?>
                            <?php if ($appointment['patient_address']): ?>
                            <div class="sm:col-span-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs sm:text-sm text-gray-600 mb-1 font-semibold">Address</p>
                                <p class="font-bold text-gray-900 text-sm sm:text-base"><?= htmlspecialchars($appointment['patient_address']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4 sm:space-y-6">
                    <!-- Doctor Information -->
                    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5 sm:p-6 hover:shadow-xl transition-all duration-300">
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 mb-5 sm:mb-6 flex items-center">
                            <i class="fas fa-user-md text-[#4D774E] mr-2"></i>
                            Your Doctor
                        </h2>
                        
                        <div class="text-center mb-5 sm:mb-6">
                            <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl bg-gradient-to-br from-[#9DC88D] to-[#4D774E] flex items-center justify-center mx-auto mb-4 overflow-hidden shadow-lg ring-4 ring-[#9DC88D]/30">
                                <?php if ($appointment['doctor_photo'] && $appointment['doctor_photo'] !== 'doctor.png' && file_exists("../public/uploads/" . $appointment['doctor_photo'])): ?>
                                    <img src="../public/uploads/<?= htmlspecialchars($appointment['doctor_photo']) ?>" 
                                         alt="Doctor" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user-md text-white text-4xl sm:text-5xl"></i>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2 truncate">
                                Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                            </h3>
                            <p class="text-[#4D774E] font-bold mb-4 text-sm sm:text-base truncate">
                                <?= htmlspecialchars($appointment['specialization']) ?>
                            </p>
                        </div>
                        
                        <div class="space-y-3 sm:space-y-4">
                            <div class="flex items-center text-xs sm:text-sm text-gray-700 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <i class="fas fa-phone w-6 text-[#4D774E] flex-shrink-0"></i>
                                <span class="truncate font-medium"><?= htmlspecialchars($appointment['doctor_phone']) ?></span>
                            </div>
                            <div class="flex items-center text-xs sm:text-sm text-gray-700 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <i class="fas fa-envelope w-6 text-[#4D774E] flex-shrink-0"></i>
                                <span class="truncate font-medium"><?= htmlspecialchars($appointment['doctor_email']) ?></span>
                            </div>
                            <?php if ($appointment['experience_years']): ?>
                            <div class="flex items-center text-xs sm:text-sm text-gray-700 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <i class="fas fa-briefcase w-6 text-[#4D774E] flex-shrink-0"></i>
                                <span class="font-medium"><?= $appointment['experience_years'] ?> years experience</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($appointment['consultation_fee']): ?>
                            <div class="flex items-center text-xs sm:text-sm text-gray-700 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <i class="fas fa-dollar-sign w-6 text-[#4D774E] flex-shrink-0"></i>
                                <span class="font-medium">₱<?= number_format($appointment['consultation_fee'], 2) ?> fee</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-5 sm:mt-6 pt-5 sm:pt-6 border-t border-gray-200">
                            <a href="messages.php?doctor=<?= $appointment['doctor_id'] ?>" 
                               class="w-full bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white px-4 py-3 sm:py-4 rounded-xl hover:shadow-xl transition-all duration-300 font-bold text-center block text-sm sm:text-base transform hover:-translate-y-0.5">
                                <i class="fas fa-comment-medical mr-2"></i>
                                Message Doctor
                            </a>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5 sm:p-6 no-print">
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 mb-4 sm:mb-5 flex items-center">
                            <i class="fas fa-bolt text-[#F1824A] mr-2"></i>
                            Quick Actions
                        </h2>
                        
                        <div class="space-y-3 sm:space-y-4">
                            <a href="book_appointment.php" 
                               class="w-full bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white px-4 py-3 rounded-xl hover:shadow-lg transition-all duration-300 font-bold text-center block text-sm transform hover:-translate-y-0.5">
                                <i class="fas fa-calendar-plus mr-2"></i>
                                Book New Appointment
                            </a>
                            <a href="appointments.php" 
                               class="w-full bg-white border-2 border-[#9DC88D] text-[#164A41] px-4 py-3 rounded-xl hover:bg-[#9DC88D]/10 hover:border-[#4D774E] transition-all duration-300 font-bold text-center block text-sm">
                                <i class="fas fa-list mr-2"></i>
                                View All Appointments
                            </a>
                            <button onclick="window.print()" 
                                    class="w-full bg-white border-2 border-gray-300 text-gray-700 px-4 py-3 rounded-xl hover:bg-gray-50 hover:border-[#9DC88D] transition-all duration-300 font-bold text-center text-sm">
                                <i class="fas fa-print mr-2"></i>
                                Print Details
                            </button>
                        </div>
                    </div>

                    <!-- Help & Support -->
                    <div class="bg-gradient-to-br from-[#9DC88D]/20 to-[#4D774E]/20 rounded-xl p-5 sm:p-6 border-2 border-[#9DC88D]/50 shadow-md">
                        <h3 class="font-bold text-gray-900 mb-2 flex items-center text-sm sm:text-base">
                            <i class="fas fa-question-circle text-[#4D774E] mr-2"></i>
                            Need Help?
                        </h3>
                        <p class="text-xs sm:text-sm text-gray-700 mb-4 leading-relaxed">
                            Contact our support team if you have any questions about your appointment.
                        </p>
                        <a href="messages.php" class="text-[#4D774E] hover:text-[#164A41] font-bold text-xs sm:text-sm inline-flex items-center">
                            Contact Support 
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
    @media print {
        nav, footer, .no-print, aside {
            display: none !important;
        }
        .ml-0 {
            margin-left: 0 !important;
        }
        body {
            background: white;
        }
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<script>
    function cancelAppointment(appointmentId) {
        if (confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
            window.location.href = `../routes/routes.php?action=cancel_appointment&id=${appointmentId}`;
        }
    }
</script>
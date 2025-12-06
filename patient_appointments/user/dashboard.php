<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];

// Get user details and statistics
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get appointment statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Get upcoming appointments
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            d.fullname as doctor_name,
            d.specialization,
            d.profile_photo as doctor_photo
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.user_id = ?
        AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $upcoming_appointments = $stmt->fetchAll();
    
    // Get recent appointments
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            d.fullname as doctor_name,
            d.specialization
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_appointments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $stats = ['total_appointments' => 0, 'pending' => 0, 'approved' => 0, 'completed' => 0, 'cancelled' => 0];
    $upcoming_appointments = [];
    $recent_appointments = [];
}

$page_title = "Dashboard";
$current_page = basename($_SERVER['PHP_SELF']);

// Get user data for sidebar
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
                    Welcome back, <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#4D774E] to-[#164A41]"><?= htmlspecialchars(explode(' ', $user['fullname'])[0]) ?></span>!
                </h1>
                <p class="text-sm sm:text-base text-gray-600">Here's your health dashboard overview</p>
            </div>

            <!-- Statistics Cards - Single Row on Mobile -->
            <div class="grid grid-cols-4 gap-2 sm:gap-4 lg:gap-6 mb-6 sm:mb-8">
                <!-- Total Appointments -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-md hover:shadow-xl border border-gray-100 p-2 sm:p-4 lg:p-6 transition-all duration-300 hover:-translate-y-1">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-8 h-8 sm:w-12 sm:h-12 lg:w-14 lg:h-14 bg-gradient-to-br from-[#4D774E] to-[#164A41] rounded-lg sm:rounded-xl flex items-center justify-center mb-2 sm:mb-3 shadow-lg">
                            <i class="fas fa-calendar-check text-white text-xs sm:text-lg lg:text-xl"></i>
                        </div>
                        <span class="text-lg sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-1"><?= $stats['total_appointments'] ?? 0 ?></span>
                        <h3 class="text-[10px] sm:text-xs lg:text-sm font-semibold text-gray-600">Total</h3>
                    </div>
                </div>

                <!-- Pending -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-md hover:shadow-xl border border-gray-100 p-2 sm:p-4 lg:p-6 transition-all duration-300 hover:-translate-y-1">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-8 h-8 sm:w-12 sm:h-12 lg:w-14 lg:h-14 bg-gradient-to-br from-[#F1824A] to-[#F1824A]/70 rounded-lg sm:rounded-xl flex items-center justify-center mb-2 sm:mb-3 shadow-lg">
                            <i class="fas fa-clock text-white text-xs sm:text-lg lg:text-xl"></i>
                        </div>
                        <span class="text-lg sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-1"><?= $stats['pending'] ?? 0 ?></span>
                        <h3 class="text-[10px] sm:text-xs lg:text-sm font-semibold text-gray-600">Pending</h3>
                    </div>
                </div>

                <!-- Approved -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-md hover:shadow-xl border border-gray-100 p-2 sm:p-4 lg:p-6 transition-all duration-300 hover:-translate-y-1">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-8 h-8 sm:w-12 sm:h-12 lg:w-14 lg:h-14 bg-gradient-to-br from-[#9DC88D] to-[#4D774E] rounded-lg sm:rounded-xl flex items-center justify-center mb-2 sm:mb-3 shadow-lg">
                            <i class="fas fa-check-circle text-white text-xs sm:text-lg lg:text-xl"></i>
                        </div>
                        <span class="text-lg sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-1"><?= $stats['approved'] ?? 0 ?></span>
                        <h3 class="text-[10px] sm:text-xs lg:text-sm font-semibold text-gray-600">Approved</h3>
                    </div>
                </div>

                <!-- Completed -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-md hover:shadow-xl border border-gray-100 p-2 sm:p-4 lg:p-6 transition-all duration-300 hover:-translate-y-1">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-8 h-8 sm:w-12 sm:h-12 lg:w-14 lg:h-14 bg-gradient-to-br from-[#164A41] to-[#4D774E] rounded-lg sm:rounded-xl flex items-center justify-center mb-2 sm:mb-3 shadow-lg">
                            <i class="fas fa-check-double text-white text-xs sm:text-lg lg:text-xl"></i>
                        </div>
                        <span class="text-lg sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-1"><?= $stats['completed'] ?? 0 ?></span>
                        <h3 class="text-[10px] sm:text-xs lg:text-sm font-semibold text-gray-600">Done</h3>
                    </div>
                </div>
            </div>

            <!-- Quick Actions - Single Row on Mobile -->
            <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
                <h2 class="text-base sm:text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-bolt text-[#F1824A] mr-2"></i>
                    Quick Actions
                </h2>
                <div class="grid grid-cols-3 gap-2 sm:gap-4">
                    <a href="book_appointment.php" class="flex flex-col items-center p-3 sm:p-4 bg-gradient-to-br from-[#4D774E] to-[#164A41] text-white rounded-xl hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                        <i class="fas fa-calendar-plus text-xl sm:text-2xl mb-2"></i>
                        <p class="font-semibold text-[10px] sm:text-sm text-center">Book</p>
                    </a>
                    <a href="appointments.php" class="flex flex-col items-center p-3 sm:p-4 border-2 border-[#9DC88D] rounded-xl hover:border-[#4D774E] hover:bg-[#9DC88D]/10 transition-all duration-300 hover:-translate-y-1">
                        <i class="fas fa-list text-xl sm:text-2xl text-[#164A41] mb-2"></i>
                        <p class="font-semibold text-gray-900 text-[10px] sm:text-sm text-center">View</p>
                    </a>
                    <a href="messages.php" class="flex flex-col items-center p-3 sm:p-4 border-2 border-[#9DC88D] rounded-xl hover:border-[#4D774E] hover:bg-[#9DC88D]/10 transition-all duration-300 hover:-translate-y-1">
                        <i class="fas fa-envelope text-xl sm:text-2xl text-[#164A41] mb-2"></i>
                        <p class="font-semibold text-gray-900 text-[10px] sm:text-sm text-center">Messages</p>
                    </a>
                </div>
            </div>

            <!-- Appointments Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                <!-- Upcoming Appointments -->
                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-4 sm:mb-6">
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 flex items-center">
                            <i class="fas fa-calendar-day text-[#4D774E] mr-2"></i>
                            Upcoming
                        </h2>
                        <a href="appointments.php" class="text-xs sm:text-sm text-[#4D774E] hover:text-[#164A41] font-semibold transition">
                            View all →
                        </a>
                    </div>
                    
                    <?php if (count($upcoming_appointments) > 0): ?>
                        <div class="space-y-3 sm:space-y-4">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="border-2 border-gray-100 rounded-xl p-3 sm:p-4 hover:border-[#9DC88D] hover:shadow-md transition-all duration-300">
                                    <div class="flex items-start space-x-3">
                                        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-gradient-to-br from-[#9DC88D] to-[#4D774E] flex items-center justify-center flex-shrink-0 overflow-hidden shadow-sm">
                                            <?php if ($appointment['doctor_photo'] && $appointment['doctor_photo'] !== 'doctor.png'): ?>
                                                <img src="../public/uploads/<?= htmlspecialchars($appointment['doctor_photo']) ?>" alt="Doctor" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-user-md text-white text-lg"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between mb-2">
                                                <div class="min-w-0 flex-1">
                                                    <h3 class="font-bold text-gray-900 text-sm sm:text-base truncate">
                                                        Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                                                    </h3>
                                                    <p class="text-xs text-gray-600 truncate">
                                                        <?= htmlspecialchars($appointment['specialization']) ?>
                                                    </p>
                                                </div>
                                                <span class="px-2 sm:px-3 py-1 rounded-full text-[10px] sm:text-xs font-bold ml-2 flex-shrink-0
                                                    <?php 
                                                        if ($appointment['status'] === 'approved') echo 'bg-[#9DC88D]/20 text-[#164A41] border border-[#9DC88D]';
                                                        elseif ($appointment['status'] === 'pending') echo 'bg-[#F1824A]/20 text-[#F1824A] border border-[#F1824A]';
                                                        else echo 'bg-gray-100 text-gray-700 border border-gray-300';
                                                    ?>">
                                                    <?= ucfirst($appointment['status']) ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-3 text-xs text-gray-600">
                                                <span class="flex items-center">
                                                    <i class="fas fa-calendar text-[#4D774E] mr-1"></i>
                                                    <?= date('M d, Y', strtotime($appointment['appointment_date'])) ?>
                                                </span>
                                                <span class="flex items-center">
                                                    <i class="fas fa-clock text-[#4D774E] mr-1"></i>
                                                    <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-calendar-times text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-600 mb-4">No upcoming appointments</p>
                            <a href="book_appointment.php" class="text-sm text-[#4D774E] hover:text-[#164A41] font-semibold">
                                Book your first appointment →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent History -->
                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-4 sm:mb-6">
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 flex items-center">
                            <i class="fas fa-history text-[#4D774E] mr-2"></i>
                            Recent History
                        </h2>
                        <a href="appointments.php" class="text-xs sm:text-sm text-[#4D774E] hover:text-[#164A41] font-semibold transition">
                            View all →
                        </a>
                    </div>
                    
                    <?php if (count($recent_appointments) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($recent_appointments as $appointment): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-[#9DC88D]/5 rounded-xl hover:from-[#9DC88D]/10 hover:to-[#9DC88D]/10 transition-all duration-200 border border-gray-100">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-gray-900 text-sm truncate">
                                            Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-600 truncate">
                                            <?= htmlspecialchars($appointment['specialization']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?= date('M d, Y - g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])) ?>
                                        </p>
                                    </div>
                                    <span class="px-2 sm:px-3 py-1 rounded-full text-[10px] sm:text-xs font-bold ml-3 flex-shrink-0
                                        <?php 
                                            if ($appointment['status'] === 'completed') echo 'bg-[#164A41]/20 text-[#164A41] border border-[#164A41]';
                                            elseif ($appointment['status'] === 'approved') echo 'bg-[#9DC88D]/20 text-[#4D774E] border border-[#9DC88D]';
                                            elseif ($appointment['status'] === 'pending') echo 'bg-[#F1824A]/20 text-[#F1824A] border border-[#F1824A]';
                                            elseif ($appointment['status'] === 'cancelled') echo 'bg-red-100 text-red-700 border border-red-300';
                                        ?>">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-clipboard-list text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-600">No appointment history yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
//doctor/schedule.php
// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'My Schedule';
$doctor_id = $_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'] ?? 'Doctor';
$doctor_email = $_SESSION['doctor_email'] ?? '';
$doctor_specialization = $_SESSION['doctor_specialization'] ?? 'General';
$doctor_photo = $_SESSION['doctor_photo'] ?? 'default.png';

// Fetch system settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    $system_name = $settings['system_name'] ?? 'MediCare';
    $system_logo = $settings['system_logo'] ?? '';
} catch (PDOException $e) {
    $system_name = 'MediCare';
    $system_logo = '';
}

// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$view_mode = $_GET['view'] ?? 'week'; // week, month, list

// Fetch pending appointments count for badge
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'pending'");
    $stmt->execute([$doctor_id]);
    $pending_appointments = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $pending_appointments = 0;
}

// Fetch doctor's schedules based on view mode
try {
    if ($view_mode === 'week') {
        // Get current week schedules
        $start_of_week = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
        $end_of_week = date('Y-m-d', strtotime('sunday this week', strtotime($filter_date)));
        
        $stmt = $pdo->prepare("
            SELECT ds.*, 
                   (SELECT COUNT(*) FROM appointments a 
                    WHERE a.doctor_id = ds.doctor_id 
                    AND a.appointment_date = ds.schedule_date 
                    AND a.status IN ('pending', 'approved')) as booked_appointments
            FROM doctor_schedule ds
            WHERE ds.doctor_id = ? 
            AND ds.schedule_date BETWEEN ? AND ?
            ORDER BY ds.schedule_date ASC, ds.start_time ASC
        ");
        $stmt->execute([$doctor_id, $start_of_week, $end_of_week]);
        $schedules = $stmt->fetchAll();
    } elseif ($view_mode === 'month') {
        // Get current month schedules
        $start_of_month = date('Y-m-01', strtotime($filter_date));
        $end_of_month = date('Y-m-t', strtotime($filter_date));
        
        $stmt = $pdo->prepare("
            SELECT ds.*, 
                   (SELECT COUNT(*) FROM appointments a 
                    WHERE a.doctor_id = ds.doctor_id 
                    AND a.appointment_date = ds.schedule_date 
                    AND a.status IN ('pending', 'approved')) as booked_appointments
            FROM doctor_schedule ds
            WHERE ds.doctor_id = ? 
            AND ds.schedule_date BETWEEN ? AND ?
            ORDER BY ds.schedule_date ASC, ds.start_time ASC
        ");
        $stmt->execute([$doctor_id, $start_of_month, $end_of_month]);
        $schedules = $stmt->fetchAll();
    } else {
        // List view - show all upcoming schedules
        $stmt = $pdo->prepare("
            SELECT ds.*, 
                   (SELECT COUNT(*) FROM appointments a 
                    WHERE a.doctor_id = ds.doctor_id 
                    AND a.appointment_date = ds.schedule_date 
                    AND a.status IN ('pending', 'approved')) as booked_appointments
            FROM doctor_schedule ds
            WHERE ds.doctor_id = ? 
            AND ds.schedule_date >= CURDATE()
            ORDER BY ds.schedule_date ASC, ds.start_time ASC
            LIMIT 50
        ");
        $stmt->execute([$doctor_id]);
        $schedules = $stmt->fetchAll();
    }

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_schedules
        FROM doctor_schedule
        WHERE doctor_id = ? AND schedule_date >= CURDATE()
    ");
    $stmt->execute([$doctor_id]);
    $total_schedules = $stmt->fetch()['total_schedules'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as today_schedules
        FROM doctor_schedule
        WHERE doctor_id = ? AND schedule_date = CURDATE()
    ");
    $stmt->execute([$doctor_id]);
    $today_schedules = $stmt->fetch()['today_schedules'];

    $stmt = $pdo->prepare("
        SELECT SUM(slots) as total_slots
        FROM doctor_schedule
        WHERE doctor_id = ? AND schedule_date >= CURDATE()
    ");
    $stmt->execute([$doctor_id]);
    $total_slots = $stmt->fetch()['total_slots'] ?? 0;

} catch (PDOException $e) {
    $schedules = [];
    $total_schedules = 0;
    $today_schedules = 0;
    $total_slots = 0;
}

// Group schedules by date for calendar view
$schedules_by_date = [];
foreach ($schedules as $schedule) {
    $date = $schedule['schedule_date'];
    if (!isset($schedules_by_date[$date])) {
        $schedules_by_date[$date] = [];
    }
    $schedules_by_date[$date][] = $schedule;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= htmlspecialchars($system_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #4D774E 0%, #164A41 100%);
        }

        .gradient-primary {
            background: linear-gradient(135deg, #9DC88D 0%, #4D774E 100%);
        }

        .gradient-accent {
            background: linear-gradient(135deg, #F1824A 0%, #4D774E 100%);
        }
        
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(22, 74, 65, 0.2), 0 10px 10px -5px rgba(22, 74, 65, 0.1);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .system-name-full {
                display: none;
            }
            .system-name-short {
                display: block;
                letter-spacing: 0.3em;
            }
        }

        @media (min-width: 769px) {
            .system-name-full {
                display: block;
            }
            .system-name-short {
                display: none;
            }
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,1) 100%);
            backdrop-filter: blur(10px);
        }

        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }

        .calendar-day {
            min-height: 100px;
            border: 1px solid #e5e7eb;
        }

        .calendar-day.today {
            background-color: rgba(157, 200, 141, 0.1);
            border-color: #4D774E;
        }

        .schedule-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
        }

        .text-primary-custom {
            color: #4D774E;
        }

        .text-accent-custom {
            color: #F1824A;
        }

        .bg-primary-custom {
            background-color: #4D774E;
        }

        .bg-accent-custom {
            background-color: #F1824A;
        }

        .hover\:bg-primary-custom:hover {
            background-color: #4D774E;
        }

        .border-primary-custom {
            border-color: #4D774E;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#4D774E',
                        'primary-dark': '#164A41',
                        'secondary': '#9DC88D',
                        'accent': '#F1824A',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100">
    
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-lg border-b border-gray-200 fixed top-0 left-0 right-0 z-50">
        <div class="px-4 lg:px-6">
            <div class="flex justify-between items-center h-16 lg:h-18">
                <!-- Left: Logo & Menu Toggle -->
                <div class="flex items-center space-x-4 flex-1 min-w-0">
                    <button onclick="toggleSidebar()" 
                            class="md:hidden p-2 text-gray-600 hover:text-primary hover:bg-primary/10 rounded-lg transition-all duration-200">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl overflow-hidden flex-shrink-0 gradient-bg shadow-lg">
                            <?php if (!empty($system_logo) && file_exists("../public/images/" . $system_logo)): ?>
                                <img src="../public/images/<?= htmlspecialchars($system_logo) ?>" 
                                     alt="Logo" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-user-md text-white text-xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <span class="text-xl font-bold text-primary system-name-full">
                                <?= htmlspecialchars($system_name) ?>
                            </span>
                            <span class="text-lg font-bold text-primary system-name-short">
                                <?php 
                                    $initials = '';
                                    $words = explode(' ', $system_name);
                                    foreach($words as $word) {
                                        $initials .= strtoupper(substr($word, 0, 1)) . ' ';
                                    }
                                    echo trim($initials);
                                ?>
                            </span>
                            <p class="text-xs text-gray-500 hidden sm:block">Doctor Portal</p>
                        </div>
                    </div>
                </div>

                <!-- Right: Actions & Profile -->
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <!-- Profile -->
                    <div class="flex items-center space-x-2 sm:space-x-3 pl-2 sm:pl-3 border-l border-gray-200">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-semibold text-gray-900">
                                Dr. <?= htmlspecialchars(explode(' ', $doctor_name)[0]) ?>
                            </p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($doctor_specialization) ?></p>
                        </div>
                        
                        <a href="profile.php" class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl gradient-bg flex items-center justify-center text-white font-semibold overflow-hidden hover:shadow-lg transition-all duration-200 text-sm">
                            <?php if ($doctor_photo && $doctor_photo !== 'default.png' && file_exists("../public/uploads/$doctor_photo")): ?>
                                <img src="../public/uploads/<?= htmlspecialchars($doctor_photo) ?>" 
                                     alt="Profile" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($doctor_name, 0, 2)) ?>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" 
         class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden" 
         onclick="toggleSidebar()">
    </div>

    <!-- Sidebar -->
    <aside id="sidebar" 
           class="sidebar fixed left-0 top-16 bottom-0 w-64 bg-white shadow-xl z-40 transition-transform duration-300 overflow-y-auto">
        <div class="p-4 space-y-2">
            <a href="dashboard.php" 
               class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-primary/10 hover:text-primary rounded-xl transition-all duration-200">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="appointments.php" 
               class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-primary/10 hover:text-primary rounded-xl transition-all duration-200">
                <i class="fas fa-calendar-check"></i>
                <span>Appointments</span>
                <?php if ($pending_appointments > 0): ?>
                    <span class="ml-auto bg-accent text-white text-xs px-2 py-1 rounded-full"><?= $pending_appointments ?></span>
                <?php endif; ?>
            </a>
            
            <a href="schedule.php" 
               class="flex items-center space-x-3 px-4 py-3 gradient-bg text-white rounded-xl font-medium">
                <i class="fas fa-clock"></i>
                <span>My Schedule</span>
            </a>
            
            <a href="messages.php" 
               class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-primary/10 hover:text-primary rounded-xl transition-all duration-200">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            
            
            <div class="pt-4 mt-4 border-t border-gray-200">
                <a href="../routes/routes.php?action=logout" 
                   class="flex items-center space-x-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="md:ml-64 pt-20 pb-8 px-4 lg:px-8 min-h-screen">
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?= htmlspecialchars($_SESSION['success']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?= htmlspecialchars($_SESSION['error']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="mb-8">
            <div class="gradient-primary rounded-2xl p-6 lg:p-8 text-white shadow-2xl">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-4 lg:space-y-0">
                    <div>
                        <h1 class="text-2xl lg:text-4xl font-bold mb-2">My Schedule 📅</h1>
                        <p class="text-white/90 text-sm lg:text-base">Manage your availability and appointment slots</p>
                    </div>
                    
                    <button onclick="openAddModal()" 
                            class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-4 sm:px-6 py-3 rounded-xl font-medium transition-all duration-200 flex items-center space-x-2 text-sm sm:text-base">
                        <i class="fas fa-plus"></i>
                        <span>Add Schedule</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- View Mode Selector - Mobile Optimized -->
        <div class="mb-6 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
            <div class="flex bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <a href="?view=week&date=<?= $filter_date ?>" 
                   class="flex-1 px-3 sm:px-4 py-2 text-xs sm:text-sm <?= $view_mode === 'week' ? 'bg-primary-custom text-white' : 'text-gray-700 hover:bg-gray-50' ?> transition-all duration-200 text-center">
                    <i class="fas fa-calendar-week mr-1 sm:mr-2"></i><span class="hidden xs:inline">Week</span>
                </a>
                <a href="?view=month&date=<?= $filter_date ?>" 
                   class="flex-1 px-3 sm:px-4 py-2 text-xs sm:text-sm <?= $view_mode === 'month' ? 'bg-primary-custom text-white' : 'text-gray-700 hover:bg-gray-50' ?> transition-all duration-200 border-l border-gray-200 text-center">
                    <i class="fas fa-calendar-alt mr-1 sm:mr-2"></i><span class="hidden xs:inline">Month</span>
                </a>
                <a href="?view=list" 
                   class="flex-1 px-3 sm:px-4 py-2 text-xs sm:text-sm <?= $view_mode === 'list' ? 'bg-primary-custom text-white' : 'text-gray-700 hover:bg-gray-50' ?> transition-all duration-200 border-l border-gray-200 text-center">
                    <i class="fas fa-list mr-1 sm:mr-2"></i><span class="hidden xs:inline">List</span>
                </a>
            </div>

            <!-- Date Navigation -->
            <?php if ($view_mode !== 'list'): ?>
                <div class="flex items-center justify-center space-x-3 bg-white rounded-xl shadow-sm border border-gray-200 px-3 py-2">
                    <?php
                        $prev_date = date('Y-m-d', strtotime($view_mode === 'week' ? '-1 week' : '-1 month', strtotime($filter_date)));
                        $next_date = date('Y-m-d', strtotime($view_mode === 'week' ? '+1 week' : '+1 month', strtotime($filter_date)));
                    ?>
                    <a href="?view=<?= $view_mode ?>&date=<?= $prev_date ?>" 
                       class="p-2 text-gray-600 hover:text-primary hover:bg-primary/10 rounded-lg transition-all duration-200">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <a href="?view=<?= $view_mode ?>&date=<?= date('Y-m-d') ?>" 
                       class="px-3 sm:px-4 py-2 bg-primary/10 text-primary rounded-lg font-medium hover:bg-primary/20 transition-all duration-200 text-xs sm:text-sm">
                        Today
                    </a>
                    
                    <a href="?view=<?= $view_mode ?>&date=<?= $next_date ?>" 
                       class="p-2 text-gray-600 hover:text-primary hover:bg-primary/10 rounded-lg transition-all duration-200">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6 mb-8">
            <!-- Total Schedules -->
            <div class="stat-card rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg card-hover border border-gray-100">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg sm:rounded-xl flex items-center justify-center">
                        <i class="fas fa-calendar text-blue-600 text-lg sm:text-xl"></i>
                    </div>
                    <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2 sm:px-3 py-1 rounded-full">UPCOMING</span>
                </div>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1"><?= $total_schedules ?></h3>
                <p class="text-gray-600 text-xs sm:text-sm">Total Schedules</p>
            </div>

            <!-- Today's Schedules -->
            <div class="stat-card rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg card-hover border border-gray-100">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center" style="background-color: rgba(77, 119, 78, 0.2);">
                        <i class="fas fa-calendar-day text-lg sm:text-xl" style="color: #4D774E;"></i>
                    </div>
                    <span class="text-xs font-semibold px-2 sm:px-3 py-1 rounded-full" style="color: #4D774E; background-color: rgba(77, 119, 78, 0.2);">TODAY</span>
                </div>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1"><?= $today_schedules ?></h3>
                <p class="text-gray-600 text-xs sm:text-sm">Today's Schedules</p>
            </div>

            <!-- Available Slots -->
            <div class="stat-card rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg card-hover border border-gray-100 col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center" style="background-color: rgba(157, 200, 141, 0.3);">
                        <i class="fas fa-users text-lg sm:text-xl" style="color: #4D774E;"></i>
                    </div>
                    <span class="text-xs font-semibold px-2 sm:px-3 py-1 rounded-full" style="color: #4D774E; background-color: rgba(157, 200, 141, 0.3);">SLOTS</span>
                </div>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1"><?= $total_slots ?></h3>
                <p class="text-gray-600 text-xs sm:text-sm">Available Slots</p>
            </div>
        </div>

        <!-- Schedule Content -->
        <?php if ($view_mode === 'list'): ?>
            <!-- List View -->
            <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-list text-primary mr-2 sm:mr-3"></i>
                        Upcoming Schedules
                    </h2>
                </div>

                <?php if (empty($schedules)): ?>
                    <div class="text-center py-12 sm:py-16">
                        <i class="fas fa-calendar-times text-gray-300 text-5xl sm:text-6xl mb-4"></i>
                        <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2">No Schedules Found</h3>
                        <p class="text-gray-500 mb-6 text-sm sm:text-base">You haven't added any schedules yet</p>
                        <button onclick="openAddModal()" 
                                class="gradient-bg text-white px-4 sm:px-6 py-2 sm:py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 text-sm sm:text-base">
                            <i class="fas fa-plus mr-2"></i>Add Your First Schedule
                        </button>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 sm:space-y-4">
                        <?php foreach ($schedules as $schedule): ?>
                            <?php
                                $date = new DateTime($schedule['schedule_date']);
                                $availability_percent = $schedule['slots'] > 0 ? 
                                    (($schedule['slots'] - $schedule['booked_appointments']) / $schedule['slots']) * 100 : 0;
                            ?>
                            <div class="border border-gray-200 rounded-xl p-4 sm:p-5 hover:shadow-md transition-all duration-200">
                                <div class="flex flex-col space-y-4">
                                    <div class="flex items-start space-x-3 sm:space-x-4">
                                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl gradient-bg flex flex-col items-center justify-center text-white flex-shrink-0">
                                            <span class="text-xs font-medium"><?= $date->format('M') ?></span>
                                            <span class="text-xl sm:text-2xl font-bold"><?= $date->format('d') ?></span>
                                        </div>
                                        
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-semibold text-gray-900 text-base sm:text-lg mb-2 truncate">
                                                <?= $date->format('l, F j, Y') ?>
                                            </h3>
                                            <div class="flex flex-wrap gap-2 sm:gap-4 text-xs sm:text-sm text-gray-600">
                                                <span class="flex items-center">
                                                    <i class="fas fa-clock text-primary mr-1 sm:mr-2"></i>
                                                    <span class="truncate"><?= date('h:i A', strtotime($schedule['start_time'])) ?> - 
                                                    <?= date('h:i A', strtotime($schedule['end_time'])) ?>
                                                     </span>
                                                <span class="flex items-center">
                                                    <i class="fas fa-users text-primary mr-1 sm:mr-2"></i>
                                                    <?= $schedule['booked_appointments'] ?> / <?= $schedule['slots'] ?> booked
                                                </span>
                                            </div>
                                            
                                            <!-- Progress Bar -->
                                            <div class="mt-3">
                                                <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                                    <span>Availability</span>
                                                    <span><?= number_format($availability_percent, 0) ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="h-2 rounded-full transition-all duration-300" 
                                                         style="width: <?= $availability_percent ?>%; background-color: <?= $availability_percent > 50 ? '#4D774E' : ($availability_percent > 25 ? '#F1824A' : '#dc2626') ?>;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-end space-x-2 pt-3 border-t border-gray-100">
                                        <button onclick="viewScheduleDetails(<?= $schedule['id'] ?>)" 
                                                class="px-3 sm:px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-all duration-200 text-sm">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editSchedule(<?= $schedule['id'] ?>)" 
                                                class="px-3 sm:px-4 py-2 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 transition-all duration-200 text-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteSchedule(<?= $schedule['id'] ?>)" 
                                                class="px-3 sm:px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-all duration-200 text-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Calendar View (Week/Month) -->
            <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900">
                        <?= $view_mode === 'week' ? 'Week View' : 'Month View' ?>
                    </h2>
                </div>

                <?php if ($view_mode === 'week'): ?>
                    <!-- Week Calendar Grid -->
                    <div class="grid grid-cols-7 gap-1 sm:gap-2">
                        <?php
                            $days_of_week = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                            $current_date = strtotime($start_of_week);
                            
                            foreach ($days_of_week as $day):
                        ?>
                            <div class="text-center font-semibold text-gray-700 py-2 sm:py-3 bg-gray-50 rounded-lg text-xs sm:text-sm">
                                <?= $day ?>
                            </div>
                        <?php endforeach; ?>

                        <?php
                            for ($i = 0; $i < 7; $i++):
                                $date_str = date('Y-m-d', $current_date);
                                $is_today = $date_str === date('Y-m-d');
                                $day_schedules = $schedules_by_date[$date_str] ?? [];
                        ?>
                            <div class="calendar-day p-2 sm:p-3 rounded-lg <?= $is_today ? 'today' : '' ?> min-h-[80px] sm:min-h-[100px]">
                                <div class="text-center mb-2">
                                    <span class="text-sm sm:text-lg font-bold <?= $is_today ? 'text-primary' : 'text-gray-700' ?>">
                                        <?= date('d', $current_date) ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($day_schedules)): ?>
                                    <div class="space-y-1">
                                        <?php foreach (array_slice($day_schedules, 0, 2) as $schedule): ?>
                                            <div class="bg-primary/10 text-primary rounded px-1 sm:px-2 py-1 text-xs cursor-pointer hover:bg-primary/20 transition-all duration-200"
                                                 onclick="viewScheduleDetails(<?= $schedule['id'] ?>)">
                                                <div class="font-semibold truncate text-[10px] sm:text-xs">
                                                    <?= date('h:i A', strtotime($schedule['start_time'])) ?>
                                                </div>
                                                <div class="text-[9px] sm:text-xs opacity-75 truncate">
                                                    <?= $schedule['booked_appointments'] ?>/<?= $schedule['slots'] ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($day_schedules) > 2): ?>
                                            <div class="text-center text-[9px] sm:text-xs text-gray-500">
                                                +<?= count($day_schedules) - 2 ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-gray-400 text-[9px] sm:text-xs mt-2 sm:mt-4">
                                        No schedules
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php
                                $current_date = strtotime('+1 day', $current_date);
                            endfor;
                        ?>
                    </div>

                <?php else: ?>
                    <!-- Month Calendar Grid -->
                    <?php
                        $first_day = new DateTime($start_of_month);
                        $last_day = new DateTime($end_of_month);
                        $start_dow = (int)$first_day->format('N');
                        $days_in_month = (int)$first_day->format('t');
                    ?>
                    
                    <div class="mb-4 text-center">
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900">
                            <?= $first_day->format('F Y') ?>
                        </h3>
                    </div>

                    <div class="grid grid-cols-7 gap-1 sm:gap-2">
                        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?>
                            <div class="text-center font-semibold text-gray-700 py-1 sm:py-2 bg-gray-50 rounded-lg text-[10px] sm:text-sm">
                                <?= $day ?>
                            </div>
                        <?php endforeach; ?>

                        <?php for ($i = 1; $i < $start_dow; $i++): ?>
                            <div class="calendar-day p-1 sm:p-2 bg-gray-50 rounded-lg opacity-50 min-h-[60px] sm:min-h-[80px]"></div>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= $days_in_month; $day++): 
                            $date_str = $first_day->format('Y-m-') . sprintf('%02d', $day);
                            $is_today = $date_str === date('Y-m-d');
                            $day_schedules = $schedules_by_date[$date_str] ?? [];
                        ?>
                            <div class="calendar-day p-1 sm:p-2 rounded-lg <?= $is_today ? 'today' : '' ?> min-h-[60px] sm:min-h-[80px]">
                                <div class="text-right mb-1">
                                    <span class="text-xs sm:text-sm font-semibold <?= $is_today ? 'text-primary' : 'text-gray-700' ?>">
                                        <?= $day ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($day_schedules)): ?>
                                    <div class="space-y-0.5 sm:space-y-1">
                                        <?php foreach (array_slice($day_schedules, 0, 2) as $schedule): ?>
                                            <div class="schedule-badge bg-primary/10 text-primary rounded px-1 py-0.5 cursor-pointer hover:bg-primary/20 transition-all duration-200 text-[8px] sm:text-[10px]"
                                                 onclick="viewScheduleDetails(<?= $schedule['id'] ?>)">
                                                <?= date('h:iA', strtotime($schedule['start_time'])) ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($day_schedules) > 2): ?>
                                            <div class="schedule-badge bg-gray-200 text-gray-600 rounded px-1 py-0.5 text-center text-[8px] sm:text-[10px]">
                                                +<?= count($day_schedules) - 2 ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Add Schedule Modal -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 sm:p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Add New Schedule</h2>
                    <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl sm:text-2xl"></i>
                    </button>
                </div>
            </div>

            <form action="../routes/routes.php?action=add_schedule" method="POST" class="p-4 sm:p-6 space-y-4 sm:space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar text-primary mr-2"></i>Date
                        </label>
                        <input type="date" 
                               name="schedule_date" 
                               min="<?= date('Y-m-d') ?>"
                               required
                               class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 text-sm sm:text-base">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-users text-primary mr-2"></i>Available Slots
                        </label>
                        <input type="number" 
                               name="slots" 
                               min="1" 
                               max="50"
                               required
                               placeholder="e.g., 10"
                               class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 text-sm sm:text-base">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-clock text-primary mr-2"></i>Start Time
                        </label>
                        <input type="time" 
                               name="start_time" 
                               required
                               class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 text-sm sm:text-base">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-clock text-primary mr-2"></i>End Time
                        </label>
                        <input type="time" 
                               name="end_time" 
                               required
                               class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 text-sm sm:text-base">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-sticky-note text-primary mr-2"></i>Notes (Optional)
                    </label>
                    <textarea name="notes" 
                              rows="3"
                              placeholder="Any special instructions or notes..."
                              class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 text-sm sm:text-base"></textarea>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end space-y-2 sm:space-y-0 sm:space-x-3 pt-4 border-t border-gray-200">
                    <button type="button" 
                            onclick="closeAddModal()"
                            class="px-4 sm:px-6 py-2 sm:py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition-all duration-200 text-sm sm:text-base">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 sm:px-6 py-2 sm:py-3 gradient-bg text-white rounded-xl font-medium hover:shadow-lg transition-all duration-200 text-sm sm:text-base">
                        <i class="fas fa-plus mr-2"></i>Add Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
            
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('active');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });

        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function viewScheduleDetails(scheduleId) {
            window.location.href = `schedule_details.php?id=${scheduleId}`;
        }

        function editSchedule(scheduleId) {
            window.location.href = `edit_schedule.php?id=${scheduleId}`;
        }

        function deleteSchedule(scheduleId) {
            if (confirm('Are you sure you want to delete this schedule? This action cannot be undone.')) {
                window.location.href = `../routes/routes.php?action=delete_schedule&id=${scheduleId}`;
            }
        }

        document.getElementById('addModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
            }
        });
    </script>
</body>
</html>
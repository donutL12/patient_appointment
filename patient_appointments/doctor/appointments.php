<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    die("ERROR: Not logged in as doctor. Session: " . print_r($_SESSION, true));
}
//doctor/appointments.php
$doctor_id = $_SESSION['doctor_id'];
$page_title = 'My Appointments';
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
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search_query = $_GET['search'] ?? '';

// Fetch appointment statistics
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_appointments = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'pending'");
    $stmt->execute([$doctor_id]);
    $pending_count = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'approved'");
    $stmt->execute([$doctor_id]);
    $approved_count = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed'");
    $stmt->execute([$doctor_id]);
    $completed_count = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'cancelled'");
    $stmt->execute([$doctor_id]);
    $cancelled_count = $stmt->fetch()['count'];

} catch (PDOException $e) {
    $total_appointments = 0;
    $pending_count = 0;
    $approved_count = 0;
    $completed_count = 0;
    $cancelled_count = 0;
}

// Fetch appointments - FIXED VERSION
try {
    // Check if gender column exists in users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'gender'");
    $has_gender = $stmt->fetch();
    
    // Build the SELECT clause dynamically based on available columns
    $gender_select = $has_gender ? "u.gender" : "'N/A'";
    
    $query = "
        SELECT 
            a.id,
            a.user_id,
            a.doctor_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            a.created_at,
            u.fullname as patient_name, 
            u.email as patient_email, 
            u.phone as patient_phone,
            $gender_select as patient_gender
        FROM appointments a
        INNER JOIN users u ON a.user_id = u.id
        WHERE a.doctor_id = ?
    ";
    
    $params = [$doctor_id];
    
    // Apply status filter
    if ($status_filter !== 'all') {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    // Apply date filter
    if (!empty($date_filter)) {
        $query .= " AND a.appointment_date = ?";
        $params[] = $date_filter;
    }
    
    // Apply search filter
    if (!empty($search_query)) {
        $query .= " AND (
            u.fullname LIKE ? OR 
            u.email LIKE ? OR 
            u.phone LIKE ? OR
            a.notes LIKE ?
        )";
        $search_param = "%{$search_query}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $appointments = [];
    error_log("Fetch Appointments Error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load appointments. Please try again.";
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

        .status-badge { 
            font-size: 0.75rem; 
            padding: 0.25rem 0.75rem; 
            border-radius: 9999px; 
            font-weight: 600; 
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

        .ring-primary-custom {
            --tw-ring-color: #4D774E;
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
    
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b border-gray-200 fixed top-0 left-0 right-0 z-50">
        <div class="px-4 lg:px-6">
            <div class="flex justify-between items-center h-16 lg:h-18">
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

                <div class="flex items-center space-x-2 sm:space-x-3">

                    <div class="flex items-center space-x-2 sm:space-x-3 pl-2 sm:pl-3 border-l border-gray-200">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-semibold text-gray-900">
                                Dr. <?= htmlspecialchars(explode(' ', $doctor_name)[0]) ?>
                            </p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($doctor_specialization) ?></p>
                        </div>
                        <a href="profile.php" class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl gradient-bg flex items-center justify-center text-white font-semibold overflow-hidden hover:shadow-lg transition-all duration-200 text-sm">
                            <?= strtoupper(substr($doctor_name, 0, 2)) ?>
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
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-primary/10 hover:text-primary rounded-xl transition-all duration-200">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="appointments.php" class="flex items-center space-x-3 px-4 py-3 gradient-bg text-white rounded-xl font-medium">
                <i class="fas fa-calendar-check"></i>
                <span>Appointments</span>
                <?php if ($pending_count > 0): ?>
                    <span class="ml-auto bg-white/30 text-white text-xs px-2 py-1 rounded-full"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            
            <a href="schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-primary/10 hover:text-primary rounded-xl transition-all duration-200">
                <i class="fas fa-clock"></i>
                <span>My Schedule</span>
            </a>
            
            <a href="messages.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-primary/10 hover:text-primary rounded-xl transition-all duration-200">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            
            <div class="pt-4 mt-4 border-t border-gray-200">
                <a href="../routes/routes.php?action=logout" class="flex items-center space-x-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="md:ml-64 pt-20 pb-8 px-4 lg:px-8 min-h-screen">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="gradient-primary rounded-2xl p-6 lg:p-8 text-white shadow-2xl">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-4 lg:space-y-0">
                    <div>
                        <h1 class="text-2xl lg:text-4xl font-bold mb-2">
                            <i class="fas fa-calendar-check mr-3"></i>
                            My Appointments
                        </h1>
                        <p class="text-white/90 text-sm lg:text-base">
                            Manage and track all your patient appointments
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?= $_SESSION['success'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?= $_SESSION['error'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 mb-8">
            <a href="?status=all" class="stat-card rounded-xl p-4 shadow-lg card-hover border border-gray-100 <?= $status_filter === 'all' ? 'ring-2 ring-primary' : '' ?>">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar text-blue-600"></i>
                    </div>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?= $total_appointments ?></h3>
                <p class="text-gray-600 text-xs">Total</p>
            </a>

            <a href="?status=pending" class="stat-card rounded-xl p-4 shadow-lg card-hover border border-gray-100 <?= $status_filter === 'pending' ? 'ring-2' : '' ?>" style="<?= $status_filter === 'pending' ? '--tw-ring-color: #F1824A;' : '' ?>">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: rgba(241, 130, 74, 0.2);">
                        <i class="fas fa-clock" style="color: #F1824A;"></i>
                    </div>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?= $pending_count ?></h3>
                <p class="text-gray-600 text-xs">Pending</p>
            </a>

            <a href="?status=approved" class="stat-card rounded-xl p-4 shadow-lg card-hover border border-gray-100 <?= $status_filter === 'approved' ? 'ring-2 ring-primary' : '' ?>">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: rgba(77, 119, 78, 0.2);">
                        <i class="fas fa-check" style="color: #4D774E;"></i>
                    </div>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?= $approved_count ?></h3>
                <p class="text-gray-600 text-xs">Approved</p>
            </a>

            <a href="?status=completed" class="stat-card rounded-xl p-4 shadow-lg card-hover border border-gray-100 <?= $status_filter === 'completed' ? 'ring-2' : '' ?>" style="<?= $status_filter === 'completed' ? '--tw-ring-color: #9DC88D;' : '' ?>">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: rgba(157, 200, 141, 0.3);">
                        <i class="fas fa-check-circle" style="color: #4D774E;"></i>
                    </div>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?= $completed_count ?></h3>
                <p class="text-gray-600 text-xs">Completed</p>
            </a>

            <a href="?status=cancelled" class="stat-card rounded-xl p-4 shadow-lg card-hover border border-gray-100 <?= $status_filter === 'cancelled' ? 'ring-2 ring-red-500' : '' ?>">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-600"></i>
                    </div>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1"><?= $cancelled_count ?></h3>
                <p class="text-gray-600 text-xs">Cancelled</p>
            </a>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 mb-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($search_query) ?>"
                               placeholder="Search patient name, email, phone..."
                               class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base">
                    </div>

                    <div class="relative">
                        <i class="fas fa-calendar absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="date" 
                               name="date" 
                               value="<?= htmlspecialchars($date_filter) ?>"
                               class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base">
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" 
                                class="flex-1 gradient-bg text-white px-4 sm:px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 text-sm sm:text-base">
                            <i class="fas fa-filter mr-2"></i>
                            <span class="hidden sm:inline">Apply Filters</span>
                            <span class="sm:hidden">Filter</span>
                        </button>
                        <a href="appointments.php" 
                           class="px-4 sm:px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-all duration-200">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Appointments List -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <?php if (empty($appointments)): ?>
                <div class="text-center py-16">
                    <i class="fas fa-calendar-times text-gray-300 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Appointments Found</h3>
                    <p class="text-gray-500">There are no appointments matching your criteria.</p>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="gradient-bg text-white">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Patient</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Contact</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Date & Time</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Status</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Notes</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($appointments as $apt): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-full gradient-bg flex items-center justify-center text-white font-semibold">
                                                <?= strtoupper(substr($apt['patient_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($apt['patient_name']) ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <i class="fas fa-venus-mars mr-1"></i>
                                                    <?= htmlspecialchars($apt['patient_gender'] ?? 'N/A') ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900">
                                            <i class="fas fa-envelope text-primary mr-2"></i>
                                            <?= htmlspecialchars($apt['patient_email']) ?>
                                        </p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <i class="fas fa-phone text-primary mr-2"></i>
                                            <?= htmlspecialchars($apt['patient_phone']) ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-semibold text-gray-900">
                                            <i class="fas fa-calendar text-primary mr-2"></i>
                                            <?= date('M d, Y', strtotime($apt['appointment_date'])) ?>
                                        </p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <i class="fas fa-clock text-primary mr-2"></i>
                                            <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $status_classes = [
                                            'pending' => 'text-white',
                                            'approved' => 'text-white',
                                            'completed' => 'text-white',
                                            'cancelled' => 'bg-red-100 text-red-700'
                                        ];
                                        $status_bg = [
                                            'pending' => 'background-color: #F1824A;',
                                            'approved' => 'background-color: #4D774E;',
                                            'completed' => 'background-color: #9DC88D;',
                                            'cancelled' => ''
                                        ];
                                        $status_icons = [
                                            'pending' => 'fa-clock',
                                            'approved' => 'fa-check',
                                            'completed' => 'fa-check-circle',
                                            'cancelled' => 'fa-times-circle'
                                        ];
                                        ?>
                                        <span class="status-badge <?= $status_classes[$apt['status']] ?>" style="<?= $status_bg[$apt['status']] ?>">
                                            <i class="fas <?= $status_icons[$apt['status']] ?> mr-1"></i>
                                            <?= ucfirst($apt['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="max-w-xs">
                                            <?php if (!empty($apt['notes'])): ?>
                                                <p class="text-sm text-gray-700 truncate">
                                                    <i class="fas fa-sticky-note text-gray-400 mr-1"></i>
                                                    <?= htmlspecialchars($apt['notes']) ?>
                                                </p>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-400">No notes</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center space-x-2">
                                            <?php if ($apt['status'] === 'pending'): ?>
                                                <button onclick="updateStatus(<?= $apt['id'] ?>, 'approved')"
                                                        class="px-3 py-2 text-white rounded-lg hover:shadow-lg transition-all duration-200 text-sm"
                                                        style="background-color: #4D774E;">
                                                    <i class="fas fa-check mr-1"></i>Approve
                                                </button>
                                                <button onclick="updateStatus(<?= $apt['id'] ?>, 'cancelled')"
                                                        class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all duration-200 text-sm">
                                                    <i class="fas fa-times mr-1"></i>Cancel
                                                </button>
                                            <?php elseif ($apt['status'] === 'approved'): ?>
                                                <button onclick="updateStatus(<?= $apt['id'] ?>, 'completed')"
                                                        class="px-3 py-2 text-white rounded-lg hover:shadow-lg transition-all duration-200 text-sm"
                                                        style="background-color: #9DC88D;">
                                                    <i class="fas fa-check-circle mr-1"></i>Complete
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="viewDetails(<?= $apt['id'] ?>)"
                                                    class="px-3 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all duration-200 text-sm">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="lg:hidden divide-y divide-gray-200">
                    <?php foreach ($appointments as $apt): ?>
                        <div class="p-4 hover:bg-gray-50 transition-colors duration-150">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 rounded-full gradient-bg flex items-center justify-center text-white font-semibold">
                                        <?= strtoupper(substr($apt['patient_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($apt['patient_name']) ?></p>
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-venus-mars mr-1"></i>
                                            <?= htmlspecialchars($apt['patient_gender'] ?? 'N/A') ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                                $status_classes = [
                                    'pending' => 'text-white',
                                    'approved' => 'text-white',
                                    'completed' => 'text-white',
                                    'cancelled' => 'bg-red-100 text-red-700'
                                ];
                                $status_bg = [
                                    'pending' => 'background-color: #F1824A;',
                                    'approved' => 'background-color: #4D774E;',
                                    'completed' => 'background-color: #9DC88D;',
                                    'cancelled' => ''
                                ];
                                $status_icons = [
                                    'pending' => 'fa-clock',
                                    'approved' => 'fa-check',
                                    'completed' => 'fa-check-circle',
                                    'cancelled' => 'fa-times-circle'
                                ];
                                ?>
                                <span class="status-badge <?= $status_classes[$apt['status']] ?>" style="<?= $status_bg[$apt['status']] ?>">
                                    <i class="fas <?= $status_icons[$apt['status']] ?>"></i>
                                    <?= ucfirst($apt['status']) ?>
                                </span>
                            </div>

                            <div class="space-y-2 mb-3">
                                <p class="text-sm text-gray-700">
                                    <i class="fas fa-envelope text-primary mr-2"></i>
                                    <?= htmlspecialchars($apt['patient_email']) ?>
                                </p>
                                <p class="text-sm text-gray-700">
                                    <i class="fas fa-phone text-primary mr-2"></i>
                                    <?= htmlspecialchars($apt['patient_phone']) ?>
                                </p>
                                <p class="text-sm text-gray-700">
                                    <i class="fas fa-calendar text-primary mr-2"></i>
                                    <?= date('M d, Y', strtotime($apt['appointment_date'])) ?>
                                </p>
                                <p class="text-sm text-gray-700">
                                    <i class="fas fa-clock text-primary mr-2"></i>
                                    <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                                </p>
                                <?php if (!empty($apt['notes'])): ?>
                                    <p class="text-sm text-gray-700">
                                        <i class="fas fa-sticky-note text-primary mr-2"></i>
                                        <?= htmlspecialchars($apt['notes']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="flex space-x-2">
                                <?php if ($apt['status'] === 'pending'): ?>
                                    <button onclick="updateStatus(<?= $apt['id'] ?>, 'approved')"
                                            class="flex-1 px-3 py-2 text-white rounded-lg hover:shadow-lg transition-all duration-200 text-sm"
                                            style="background-color: #4D774E;">
                                        <i class="fas fa-check mr-1"></i>Approve
                                    </button>
                                    <button onclick="updateStatus(<?= $apt['id'] ?>, 'cancelled')"
                                            class="flex-1 px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all duration-200 text-sm">
                                        <i class="fas fa-times mr-1"></i>Cancel
                                    </button>
                                <?php elseif ($apt['status'] === 'approved'): ?>
                                    <button onclick="updateStatus(<?= $apt['id'] ?>, 'completed')"
                                            class="flex-1 px-3 py-2 text-white rounded-lg hover:shadow-lg transition-all duration-200 text-sm"
                                            style="background-color: #9DC88D;">
                                        <i class="fas fa-check-circle mr-1"></i>Complete
                                    </button>
                                <?php endif; ?>
                                <button onclick="viewDetails(<?= $apt['id'] ?>)"
                                        class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all duration-200 text-sm">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- View Details Modal -->
    <div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="gradient-bg p-6 text-white">
                <div class="flex justify-between items-center">
                    <h3 class="text-2xl font-bold">
                        <i class="fas fa-info-circle mr-2"></i>Appointment Details
                    </h3>
                    <button onclick="closeModal()" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            <div id="modalContent" class="p-6">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
        }

function updateStatus(appointmentId, newStatus) {
    if (!confirm(`Are you sure you want to ${newStatus} this appointment?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('appointment_id', appointmentId);
    formData.append('status', newStatus);

    fetch('update_appointment_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        
        try {
            const data = JSON.parse(text);
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Response was:', text);
            alert('Server returned invalid response. Check console for details.');
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error);
        alert('An error occurred while updating the appointment status.');
    });
}

function viewDetails(appointmentId) {
    document.getElementById('detailsModal').classList.remove('hidden');
    document.getElementById('modalContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-primary"></i></div>';

    fetch(`get_appointment_details.php?id=${appointmentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    const apt = data.appointment;
                    const statusClasses = {
                        'pending': 'text-white',
                        'approved': 'text-white',
                        'completed': 'text-white',
                        'cancelled': 'bg-red-100 text-red-700'
                    };
                    const statusBg = {
                        'pending': 'background-color: #F1824A;',
                        'approved': 'background-color: #4D774E;',
                        'completed': 'background-color: #9DC88D;',
                        'cancelled': ''
                    };
                    
                    document.getElementById('modalContent').innerHTML = `
                        <div class="space-y-6">
                            <div class="flex items-center space-x-4 pb-6 border-b border-gray-200">
                                <div class="w-16 h-16 rounded-full gradient-bg flex items-center justify-center text-white text-2xl font-bold">
                                    ${apt.patient_name.substring(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-gray-900">${apt.patient_name}</h4>
                                    <span class="status-badge ${statusClasses[apt.status]} mt-1 inline-block" style="${statusBg[apt.status]}">
                                        ${apt.status.charAt(0).toUpperCase() + apt.status.slice(1)}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1">Email</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        <i class="fas fa-envelope text-primary mr-2"></i>${apt.patient_email}
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1">Phone</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        <i class="fas fa-phone text-primary mr-2"></i>${apt.patient_phone}
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1">Gender</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        <i class="fas fa-venus-mars text-primary mr-2"></i>${apt.patient_gender || 'N/A'}
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1">Appointment Date</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        <i class="fas fa-calendar text-primary mr-2"></i>${new Date(apt.appointment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1">Appointment Time</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        <i class="fas fa-clock text-primary mr-2"></i>${apt.appointment_time}
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1">Booked On</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        <i class="fas fa-calendar-plus text-primary mr-2"></i>${new Date(apt.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
                                    </p>
                                </div>
                            </div>

                            ${apt.notes ? `
                                <div class="p-4 rounded-xl border" style="background-color: rgba(157, 200, 141, 0.1); border-color: #4D774E;">
                                    <p class="text-xs font-semibold mb-2" style="color: #4D774E;">
                                        <i class="fas fa-sticky-note mr-1"></i>NOTES
                                    </p>
                                    <p class="text-sm text-gray-700">${apt.notes}</p>
                                </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    document.getElementById('modalContent').innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                            <p class="text-gray-700">${data.message}</p>
                        </div>
                    `;
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Response was:', text);
                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                        <p class="text-gray-700">Error loading details. Check console.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                    <p class="text-gray-700">Failed to load appointment details.</p>
                </div>
            `;
        });
}

        function closeModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('active');
                overlay.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
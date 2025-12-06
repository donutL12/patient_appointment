<?php
session_start();
require_once __DIR__ . '/../config/db.php';
//doctor/dashboard.php
// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Doctor Dashboard';
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

// Fetch dashboard statistics
try {
    // Today's appointments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = CURDATE()
    ");
    $stmt->execute([$doctor_id]);
    $today_appointments = $stmt->fetch()['count'];

    // Pending appointments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$doctor_id]);
    $pending_appointments = $stmt->fetch()['count'];

    // Total patients
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM appointments 
        WHERE doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetch()['count'];

    // Completed this month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND status = 'completed' 
        AND MONTH(appointment_date) = MONTH(CURDATE())
        AND YEAR(appointment_date) = YEAR(CURDATE())
    ");
    $stmt->execute([$doctor_id]);
    $completed_month = $stmt->fetch()['count'];

    // Upcoming appointments (next 7 days)
    $stmt = $pdo->prepare("
        SELECT a.*, u.fullname as patient_name, u.phone as patient_phone
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.doctor_id = ? 
        AND a.appointment_date >= CURDATE()
        AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND a.status IN ('pending', 'approved')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $upcoming_appointments = $stmt->fetchAll();

    // Recent patients
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.*, MAX(a.created_at) as last_appointment
        FROM users u
        JOIN appointments a ON u.id = a.user_id
        WHERE a.doctor_id = ?
        GROUP BY u.id
        ORDER BY last_appointment DESC
        LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $recent_patients = $stmt->fetchAll();

} catch (PDOException $e) {
    $today_appointments = 0;
    $pending_appointments = 0;
    $total_patients = 0;
    $completed_month = 0;
    $upcoming_appointments = [];
    $recent_patients = [];
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

        .footer-bg {
            background-color: #164A41;
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

        .bg-light-green {
            background-color: #9DC88D;
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
               class="flex items-center space-x-3 px-4 py-3 gradient-bg text-white rounded-xl font-medium">
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
               class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-primary/10 hover:text-primary rounded-xl transition-all duration-200">
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
        <!-- Welcome Header -->
        <div class="mb-8">
            <div class="gradient-primary rounded-2xl p-6 lg:p-8 text-white shadow-2xl">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-4 lg:space-y-0">
                    <div>
                        <h1 class="text-2xl lg:text-4xl font-bold mb-2">
                            Welcome back, Dr. <?= htmlspecialchars(explode(' ', $doctor_name)[0]) ?>! 👋
                        </h1>
                        <p class="text-white/90 text-sm lg:text-base">
                            <?= date('l, F j, Y') ?> • You have <?= $today_appointments ?> appointments today
                        </p>
                    </div>
                    <a href="schedule.php" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-4 sm:px-6 py-3 rounded-xl font-medium transition-all duration-200 flex items-center space-x-2 text-sm sm:text-base">
                        <i class="fas fa-calendar-plus"></i>
                        <span>View Schedule</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6 mb-8">
            <!-- Today's Appointments -->
            <div class="stat-card rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg card-hover border border-gray-100">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg sm:rounded-xl flex items-center justify-center">
                        <i class="fas fa-calendar-day text-blue-600 text-lg sm:text-xl"></i>
                    </div>
                    <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2 sm:px-3 py-1 rounded-full">TODAY</span>
                </div>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1"><?= $today_appointments ?></h3>
                <p class="text-gray-600 text-xs sm:text-sm">Today's Appointments</p>
            </div>

            <!-- Pending Approvals -->
            <div class="stat-card rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg card-hover border border-gray-100">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center" style="background-color: rgba(241, 130, 74, 0.2);">
                        <i class="fas fa-clock text-lg sm:text-xl" style="color: #F1824A;"></i>
                    </div>
                    <span class="text-xs font-semibold px-2 sm:px-3 py-1 rounded-full" style="color: #F1824A; background-color: rgba(241, 130, 74, 0.2);">PENDING</span>
                </div>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1"><?= $pending_appointments ?></h3>
                <p class="text-gray-600 text-xs sm:text-sm">Pending Approval</p>
            </div>

            <!-- Total Patients -->
            <div class="stat-card rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg card-hover border border-gray-100">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center" style="background-color: rgba(77, 119, 78, 0.2);">
                        <i class="fas fa-users text-lg sm:text-xl" style="color: #4D774E;"></i>
                    </div>
                    <span class="text-xs font-semibold px-2 sm:px-3 py-1 rounded-full" style="color: #4D774E; background-color: rgba(77, 119, 78, 0.2);">TOTAL</span>
                </div>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1"><?= $total_patients ?></h3>
                <p class="text-gray-600 text-xs sm:text-sm">Total Patients</p>
            </div>

            <!-- Completed This Month -->
            <div class="stat-card rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg card-hover border border-gray-100">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center" style="background-color: rgba(157, 200, 141, 0.3);">
                        <i class="fas fa-check-circle text-lg sm:text-xl" style="color: #4D774E;"></i>
                    </div>
                    <span class="text-xs font-semibold px-2 sm:px-3 py-1 rounded-full" style="color: #4D774E; background-color: rgba(157, 200, 141, 0.3);">MONTH</span>
                </div>
                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1"><?= $completed_month ?></h3>
                <p class="text-gray-600 text-xs sm:text-sm">Completed This Month</p>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Upcoming Appointments -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-calendar-alt mr-3" style="color: #4D774E;"></i>
                        Upcoming Appointments
                    </h2>
                    <a href="appointments.php" class="text-sm font-medium hover:underline" style="color: #4D774E;">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="space-y-4">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-calendar-times text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-500">No upcoming appointments</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_appointments as $apt): ?>
                            <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-all duration-200">
                                <div class="flex items-start justify-between flex-col sm:flex-row space-y-3 sm:space-y-0">
                                    <div class="flex items-start space-x-4 w-full sm:w-auto">
                                        <div class="w-12 h-12 rounded-xl gradient-bg flex items-center justify-center text-white font-bold flex-shrink-0">
                                            <?= strtoupper(substr($apt['patient_name'], 0, 1)) ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($apt['patient_name']) ?></h3>
                                            <p class="text-sm text-gray-600 flex items-center mt-1">
                                                <i class="fas fa-calendar mr-2" style="color: #4D774E;"></i>
                                                <?= date('M d, Y', strtotime($apt['appointment_date'])) ?>
                                            </p>
                                            <p class="text-sm text-gray-600 flex items-center mt-1">
                                                <i class="fas fa-clock mr-2" style="color: #4D774E;"></i>
                                                <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $apt['status'] === 'approved' ? 'text-white' : '' ?> self-start sm:self-auto" 
                                          style="<?= $apt['status'] === 'approved' ? 'background-color: #4D774E;' : 'background-color: rgba(241, 130, 74, 0.2); color: #F1824A;' ?>">
                                        <?= ucfirst($apt['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Patients -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-user-friends mr-3" style="color: #4D774E;"></i>
                        Recent Patients
                    </h2>
                </div>

                <div class="space-y-3">
                    <?php if (empty($recent_patients)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-500 text-sm">No patients yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_patients as $patient): ?>
                            <div class="flex items-center space-x-3 p-3 rounded-xl hover:bg-gray-50 transition-all duration-200 cursor-pointer" 
                                 onclick="viewPatientDetails(<?= $patient['id'] ?>)">
                                <div class="w-10 h-10 rounded-full gradient-bg flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                                    <?php if (!empty($patient['profile_photo']) && file_exists("../public/uploads/{$patient['profile_photo']}")): ?>
                                        <img src="../public/uploads/<?= htmlspecialchars($patient['profile_photo']) ?>" 
                                             alt="Profile" 
                                             class="w-full h-full object-cover rounded-full">
                                    <?php else: ?>
                                        <?= strtoupper(substr($patient['fullname'], 0, 2)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($patient['fullname']) ?></p>
                                    <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($patient['email']) ?></p>
                                </div>
                                <button style="color: #4D774E;" onclick="event.stopPropagation(); viewPatientDetails(<?= $patient['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            </div>
        </div>
        <div id="patientModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="gradient-bg p-6 text-white rounded-t-2xl">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-user-circle mr-3"></i>
                    Patient Information
                </h3>
                <button onclick="closePatientModal()" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <div id="patientModalContent" class="p-6">
            <!-- Content will be loaded here -->
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-primary text-3xl"></i>
                <p class="text-gray-600 mt-3">Loading patient information...</p>
            </div>
        </div>
    </div>
</div>
    </main>


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

        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('active');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
        function viewPatientDetails(patientId) {
    const modal = document.getElementById('patientModal');
    const content = document.getElementById('patientModalContent');
    
    // Show modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Fetch patient details
    fetch(`../routes/doctor_routes.php?action=get_patient_details&patient_id=${patientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const patient = data.patient;
                const appointments = data.appointments || [];
                
                // Format patient info
                const profilePhoto = patient.profile_photo && patient.profile_photo !== 'default.png' 
                    ? `<img src="../public/uploads/${patient.profile_photo}" alt="Profile" class="w-full h-full object-cover">`
                    : `<div class="w-full h-full gradient-bg flex items-center justify-center text-white text-4xl font-bold">${patient.fullname.substring(0, 2).toUpperCase()}</div>`;
                
                const age = patient.dob ? calculateAge(patient.dob) : 'N/A';
                
                content.innerHTML = `
                    <div class="space-y-6">
                        <!-- Profile Section -->
                        <div class="flex items-start space-x-4 pb-6 border-b border-gray-200">
                            <div class="w-24 h-24 rounded-xl overflow-hidden flex-shrink-0 shadow-lg">
                                ${profilePhoto}
                            </div>
                            <div class="flex-1">
                                <h4 class="text-2xl font-bold text-gray-900 mb-2">${escapeHtml(patient.fullname)}</h4>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="flex items-center text-gray-600">
                                        <i class="fas fa-birthday-cake mr-2 text-primary"></i>
                                        <span>${age} years old</span>
                                    </div>
                                    <div class="flex items-center text-gray-600">
                                        <i class="fas fa-venus-mars mr-2 text-primary"></i>
                                        <span>${patient.gender || 'Not specified'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div>
                            <h5 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-address-card mr-2 text-primary"></i>
                                Contact Information
                            </h5>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1">Email</p>
                                    <p class="font-medium text-gray-900 break-all">${escapeHtml(patient.email)}</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1">Phone</p>
                                    <p class="font-medium text-gray-900">${escapeHtml(patient.phone || 'Not provided')}</p>
                                </div>
                                ${patient.address ? `
                                <div class="bg-gray-50 p-4 rounded-xl col-span-full">
                                    <p class="text-xs text-gray-500 mb-1">Address</p>
                                    <p class="font-medium text-gray-900">${escapeHtml(patient.address)}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- Appointment History -->
                        <div>
                            <h5 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-history mr-2 text-primary"></i>
                                Recent Appointments
                            </h5>
                            ${appointments.length > 0 ? `
                                <div class="space-y-2 max-h-60 overflow-y-auto">
                                    ${appointments.map(apt => `
                                        <div class="bg-gray-50 p-4 rounded-xl flex items-center justify-between">
                                            <div>
                                                <p class="font-medium text-gray-900">${formatDate(apt.appointment_date)}</p>
                                                <p class="text-sm text-gray-600">${formatTime(apt.appointment_time)}</p>
                                                ${apt.notes ? `<p class="text-xs text-gray-500 mt-1">${escapeHtml(apt.notes)}</p>` : ''}
                                            </div>
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full ${getStatusClass(apt.status)}">
                                                ${apt.status.charAt(0).toUpperCase() + apt.status.slice(1)}
                                            </span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : `
                                <div class="text-center py-6 bg-gray-50 rounded-xl">
                                    <i class="fas fa-calendar-times text-gray-300 text-3xl mb-2"></i>
                                    <p class="text-gray-500 text-sm">No appointment history</p>
                                </div>
                            `}
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex space-x-3 pt-4 border-t border-gray-200">
                            <a href="chat.php?patient_id=${patient.id}" 
                               class="flex-1 bg-primary text-white px-4 py-3 rounded-xl font-medium hover:bg-primary-dark transition text-center">
                                <i class="fas fa-comments mr-2"></i>
                                Send Message
                            </a>
                            <button onclick="closePatientModal()" 
                                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition">
                                Close
                            </button>
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-3"></i>
                        <p class="text-gray-600">${data.message || 'Failed to load patient information'}</p>
                        <button onclick="closePatientModal()" class="mt-4 px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition">
                            Close
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-3"></i>
                    <p class="text-gray-600">An error occurred while loading patient information</p>
                    <button onclick="closePatientModal()" class="mt-4 px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition">
                        Close
                    </button>
                </div>
            `;
        });
}

function closePatientModal() {
    const modal = document.getElementById('patientModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function calculateAge(dob) {
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    return age;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function getStatusClass(status) {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'approved': 'bg-green-100 text-green-800',
        'completed': 'bg-blue-100 text-blue-800',
        'cancelled': 'bg-red-100 text-red-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePatientModal();
    }
});
    </script>
</body>
</html>
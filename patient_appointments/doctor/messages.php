<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    header('Location: ../index.php');
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$page_title = "Messages";
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

// Get pending appointments count for sidebar badge
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$doctor_id]);
    $pending_appointments = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $pending_appointments = 0;
}

// Get all conversations (patients the doctor has messaged)
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            u.id as patient_id,
            u.fullname as patient_name,
            u.phone as patient_phone,
            u.email as patient_email,
            u.profile_photo,
            (
                SELECT message 
                FROM messages 
                WHERE (sender_id = ? AND receiver_id = u.id AND sender_type = 'doctor' AND receiver_type = 'patient')
                   OR (sender_id = u.id AND receiver_id = ? AND sender_type = 'patient' AND receiver_type = 'doctor')
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at 
                FROM messages 
                WHERE (sender_id = ? AND receiver_id = u.id AND sender_type = 'doctor' AND receiver_type = 'patient')
                   OR (sender_id = u.id AND receiver_id = ? AND sender_type = 'patient' AND receiver_type = 'doctor')
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*) 
                FROM messages 
                WHERE sender_id = u.id 
                AND receiver_id = ? 
                AND sender_type = 'patient' 
                AND receiver_type = 'doctor'
                AND is_read = 0
            ) as unread_count
        FROM users u
        WHERE EXISTS (
            SELECT 1 FROM messages m
            WHERE (m.sender_id = ? AND m.receiver_id = u.id AND m.sender_type = 'doctor' AND m.receiver_type = 'patient')
               OR (m.sender_id = u.id AND m.receiver_id = ? AND m.sender_type = 'patient' AND m.receiver_type = 'doctor')
        )
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$doctor_id, $doctor_id, $doctor_id, $doctor_id, $doctor_id, $doctor_id, $doctor_id]);
    $conversations = $stmt->fetchAll();
    
    // Get total unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_unread
        FROM messages
        WHERE receiver_id = ?
        AND receiver_type = 'doctor'
        AND is_read = 0
    ");
    $stmt->execute([$doctor_id]);
    $total_unread = $stmt->fetch()['total_unread'];
    
} catch (PDOException $e) {
    error_log("Messages Error: " . $e->getMessage());
    $conversations = [];
    $total_unread = 0;
}

// Get all patients for new message
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.fullname, u.email, u.phone, u.profile_photo
        FROM users u
        INNER JOIN appointments a ON u.id = a.user_id
        WHERE a.doctor_id = ?
        ORDER BY u.fullname ASC
    ");
    $stmt->execute([$doctor_id]);
    $all_patients = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Patients Fetch Error: " . $e->getMessage());
    $all_patients = [];
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

        .conversation-hover {
            transition: all 0.2s ease;
        }

        .conversation-hover:hover {
            transform: translateX(4px);
            background-color: rgba(77, 119, 78, 0.05);
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

        .unread-indicator {
            background: linear-gradient(135deg, #F1824A 0%, #4D774E 100%);
        }

        .message-card {
            background: white;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .message-card:hover {
            border-left-color: #4D774E;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .message-card.unread {
            background: linear-gradient(to right, rgba(157, 200, 141, 0.1) 0%, white 100%);
            border-left-color: #4D774E;
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
    <div data-current-user-id="<?= $doctor_id ?>" data-current-user-type="doctor" style="display:none;"></div>
    
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
               class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-primary/10 hover:text-primary rounded-xl transition-all duration-200">
                <i class="fas fa-clock"></i>
                <span>My Schedule</span>
            </a>
            
            <a href="messages.php" 
               class="flex items-center space-x-3 px-4 py-3 gradient-bg text-white rounded-xl font-medium">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
                <?php if ($total_unread > 0): ?>
                    <span class="ml-auto bg-white text-primary text-xs px-2 py-1 rounded-full font-bold"><?= $total_unread ?></span>
                <?php endif; ?>
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
                        <h1 class="text-2xl lg:text-4xl font-bold mb-2 flex items-center">
                            <i class="fas fa-comments mr-3"></i>
                            Messages
                        </h1>
                        <p class="text-white/90 text-sm lg:text-base">
                            <?= date('l, F j, Y') ?> • 
                            <?php if ($total_unread > 0): ?>
                                You have <?= $total_unread ?> unread message<?= $total_unread > 1 ? 's' : '' ?>
                            <?php else: ?>
                                All caught up!
                            <?php endif; ?>
                        </p>
                    </div>
                    <button onclick="openNewMessageModal()" 
                            class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-4 sm:px-6 py-3 rounded-xl font-medium transition-all duration-200 flex items-center space-x-2 text-sm sm:text-base">
                        <i class="fas fa-plus-circle"></i>
                        <span>New Message</span>
                    </button>
                </div>
            </div>
        </div>


        <?php if (count($conversations) > 0): ?>
            <!-- Messages List -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <!-- Search Bar -->
                <div class="p-4 lg:p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
                    <div class="relative">
                        <input type="text" 
                               id="searchConversations"
                               placeholder="Search conversations by name or email..."
                               class="w-full pl-12 pr-4 py-3 sm:py-4 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                    </div>
                </div>

                <!-- Conversations List -->
                <div class="divide-y divide-gray-100">
                    <?php foreach ($conversations as $conversation): ?>
                        <div class="conversation-item message-card <?= $conversation['unread_count'] > 0 ? 'unread' : '' ?> conversation-hover p-4 sm:p-5 lg:p-6 cursor-pointer"
                             data-search="<?= strtolower($conversation['patient_name'] . ' ' . $conversation['patient_email']) ?>"
                             onclick="window.location.href='chat.php?patient_id=<?= $conversation['patient_id'] ?>'">
                            <div class="flex items-start space-x-3 sm:space-x-4">
                                <!-- Patient Avatar -->
                                <div class="relative flex-shrink-0">
                                    <div class="w-12 h-12 sm:w-14 sm:h-14 lg:w-16 lg:h-16 rounded-xl gradient-bg flex items-center justify-center overflow-hidden shadow-md">
                                        <?php if ($conversation['profile_photo'] && $conversation['profile_photo'] !== 'default.png' && file_exists("../public/uploads/" . $conversation['profile_photo'])): ?>
                                            <img src="../public/uploads/<?= htmlspecialchars($conversation['profile_photo']) ?>" 
                                                 alt="Patient" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="text-white text-lg sm:text-xl font-bold">
                                                <?= strtoupper(substr($conversation['patient_name'], 0, 1)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($conversation['unread_count'] > 0): ?>
                                        <span class="absolute -top-1 -right-1 w-5 h-5 sm:w-6 sm:h-6 unread-indicator rounded-full border-2 border-white flex items-center justify-center">
                                            <span class="pulse-dot w-2 h-2 bg-white rounded-full"></span>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Message Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between mb-1 gap-2">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="font-bold text-gray-900 text-base sm:text-lg truncate <?= $conversation['unread_count'] > 0 ? 'text-primary' : '' ?>">
                                                <?= htmlspecialchars($conversation['patient_name']) ?>
                                            </h3>
                                            <p class="text-xs sm:text-sm text-gray-500 truncate mt-0.5">
                                                <i class="fas fa-envelope text-gray-400 mr-1"></i>
                                                <?= htmlspecialchars($conversation['patient_email']) ?>
                                            </p>
                                        </div>
                                        <div class="flex flex-col items-end gap-1 sm:gap-2 flex-shrink-0">
                                            <span class="text-xs text-gray-500 whitespace-nowrap">
                                                <?php
                                                    $time = strtotime($conversation['last_message_time']);
                                                    $now = time();
                                                    $diff = $now - $time;
                                                    
                                                    if ($diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($diff < 3600) {
                                                        echo floor($diff / 60) . 'm ago';
                                                    } elseif ($diff < 86400) {
                                                        echo floor($diff / 3600) . 'h ago';
                                                    } elseif ($diff < 604800) {
                                                        echo floor($diff / 86400) . 'd ago';
                                                    } else {
                                                        echo date('M d', $time);
                                                    }
                                                ?>
                                            </span>
                                            <?php if ($conversation['unread_count'] > 0): ?>
                                                <span class="px-2 sm:px-2.5 py-0.5 sm:py-1 text-white text-xs font-bold rounded-full shadow-sm" style="background: linear-gradient(135deg, #F1824A 0%, #4D774E 100%);">
                                                    <?= $conversation['unread_count'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-700 truncate mt-2 <?= $conversation['unread_count'] > 0 ? 'font-semibold' : '' ?>">
                                        <i class="fas fa-comment-dots mr-2 text-gray-400"></i>
                                        <?= htmlspecialchars($conversation['last_message'] ?? 'No messages yet') ?>
                                    </p>
                                </div>

                                <!-- Arrow Icon -->
                                <div class="flex items-center flex-shrink-0">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8 sm:p-12 lg:p-16 text-center">
                <div class="w-20 h-20 sm:w-24 sm:h-24 gradient-bg rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <i class="fas fa-comments text-white text-3xl sm:text-4xl"></i>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">No Messages Yet</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto text-sm sm:text-base">
                    Start a conversation with your patients to provide medical advice and support
                </p>
                <button onclick="openNewMessageModal()" 
                        class="inline-flex items-center px-6 py-3 gradient-bg text-white rounded-xl hover:shadow-lg transition-all duration-200 font-medium">
                    <i class="fas fa-plus-circle mr-2"></i>
                    Start New Conversation
                </button>
            </div>
        <?php endif; ?>
    </main>

    <!-- New Message Modal -->
    <div id="newMessageModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="gradient-bg p-6 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-comment-medical mr-2 sm:mr-3"></i>
                        New Message
                    </h2>
                    <button onclick="closeNewMessageModal()" class="text-white hover:text-gray-200 transition p-2">
                        <i class="fas fa-times text-xl sm:text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-4 sm:p-6 overflow-y-auto flex-1">
                <p class="text-gray-600 mb-6 text-sm sm:text-base">Select a patient to start a conversation</p>
                
                <!-- Search Patients -->
                <div class="mb-6">
                    <div class="relative">
                        <input type="text" 
                               id="searchPatients"
                               placeholder="Search patients by name or email..."
                               class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>

                <!-- Patients List -->
                <div id="patientsList" class="space-y-3">
                    <?php if (count($all_patients) > 0): ?>
                        <?php foreach ($all_patients as $patient): ?>
                            <div class="patient-item border border-gray-200 rounded-xl p-4 cursor-pointer hover:border-primary hover:bg-primary/5 transition card-hover"
                                 data-search="<?= strtolower($patient['fullname'] . ' ' . $patient['email']) ?>"
                                 onclick="startConversation(<?= $patient['id'] ?>)">
                                <div class="flex items-center space-x-3 sm:space-x-4">
                                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl gradient-bg flex items-center justify-center overflow-hidden flex-shrink-0 shadow">
                                        <?php if ($patient['profile_photo'] && $patient['profile_photo'] !== 'default.png' && file_exists("../public/uploads/" . $patient['profile_photo'])): ?>
                                            <img src="../public/uploads/<?= htmlspecialchars($patient['profile_photo']) ?>" 
                                                 alt="Patient" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="text-white font-bold text-sm sm:text-base">
                                                <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-bold text-gray-900 truncate text-sm sm:text-base"><?= htmlspecialchars($patient['fullname']) ?></h3>
                                        <p class="text-xs sm:text-sm text-gray-600 truncate"><?= htmlspecialchars($patient['email']) ?></p>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 flex-shrink-0"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-500 text-sm">No patients found. Patients appear here after their first appointment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

        // Search conversations
        document.getElementById('searchConversations')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('.conversation-item');
            let visibleCount = 0;
            
            items.forEach(item => {
                const searchData = item.dataset.search;
                if (searchData.includes(searchTerm)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Search patients in modal
        document.getElementById('searchPatients')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('.patient-item');
            
            items.forEach(item => {
                const searchData = item.dataset.search;
                if (searchData.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Modal functions
        function openNewMessageModal() {
            document.getElementById('newMessageModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeNewMessageModal() {
            document.getElementById('newMessageModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        function startConversation(patientId) {
            window.location.href = 'chat.php?patient_id=' + patientId;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('newMessageModal');
            if (event.target === modal) {
                closeNewMessageModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeNewMessageModal();
            }
        });
    </script>
    <script src="../public/js/online-status.js"></script>
</body>
</html>
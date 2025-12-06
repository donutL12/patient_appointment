<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    header('Location: ../index.php');
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$page_title = "Chat";
$current_page = "messages.php";
$patient_id = $_GET['patient_id'] ?? null;

if (!$patient_id) {
    $_SESSION['error'] = 'Invalid patient ID';
    header('Location: messages.php');
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
    $system_name = $settings['system_name'] ?? 'MediCare';
    $system_logo = $settings['system_logo'] ?? '';
} catch (PDOException $e) {
    $system_name = 'MediCare';
    $system_logo = '';
}

// Get patient details
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            fullname,
            phone,
            email,
            profile_photo,
            dob,
            gender,
            address
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = 'Patient not found';
        header('Location: messages.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Patient Fetch Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading patient details';
    header('Location: messages.php');
    exit;
}

// Get all messages
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            d.fullname as sender_doctor_name,
            d.profile_photo as sender_doctor_photo
        FROM messages m
        LEFT JOIN doctors d ON m.sender_id = d.id AND m.sender_type = 'doctor'
        WHERE (m.sender_id = ? AND m.sender_type = 'doctor' AND m.receiver_id = ? AND m.receiver_type = 'patient')
           OR (m.sender_id = ? AND m.sender_type = 'patient' AND m.receiver_id = ? AND m.receiver_type = 'doctor')
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$doctor_id, $patient_id, $patient_id, $doctor_id]);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    $updateStmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE receiver_id = ? 
        AND receiver_type = 'doctor' 
        AND sender_id = ? 
        AND sender_type = 'patient' 
        AND is_read = 0
    ");
    $updateStmt->execute([$doctor_id, $patient_id]);
} catch (PDOException $e) {
    error_log("Messages Fetch Error: " . $e->getMessage());
    $messages = [];
}

$doctor_name = $_SESSION['doctor_name'] ?? 'Doctor';
$doctor_email = $_SESSION['doctor_email'] ?? '';
$doctor_specialization = $_SESSION['doctor_specialization'] ?? 'General';
$doctor_photo = $_SESSION['doctor_photo'] ?? 'default.png';
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

        .sidebar {
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-transition {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }

        #messagesArea::-webkit-scrollbar {
            width: 6px;
        }
        
        #messagesArea::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        #messagesArea::-webkit-scrollbar-thumb {
            background: #4D774E;
            border-radius: 10px;
        }

        #messagesArea::-webkit-scrollbar-thumb:hover {
            background: #164A41;
        }

        [data-status-text] {
            color: #1e40af !important;
        }

        [data-status-text] span {
            background-color: #1e40af !important;
        }

        .bg-white [data-status-text],
        #infoPanel [data-status-text] {
            color: #1e40af !important;
        }

        .bg-primary [data-status-text] {
            color: #dbeafe !important;
        }

        .bg-primary [data-status-text] span {
            background-color: #dbeafe !important;
        }

        #imagePreviewContainer {
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

        #imagePreviewContainer button {
            transition: all 0.2s ease;
        }

        #imagePreview {
            transition: transform 0.2s ease;
        }

        #imagePreview:hover {
            transform: scale(1.05);
        }

        @media (max-width: 640px) {
            #imagePreviewContainer {
                padding: 0.5rem;
            }
            
            #imagePreview {
                width: 4rem;
                height: 4rem;
            }
        }

        .call-notification {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
<body class="bg-gray-50">

<div data-current-user-id="<?= $doctor_id ?>" data-current-user-type="doctor" style="display:none;"></div>

<!-- Top Navigation Bar -->
<nav class="bg-white shadow-lg border-b border-gray-200 fixed top-0 left-0 right-0 z-50 h-14 sm:h-16">
    <div class="h-full px-3 sm:px-6 flex items-center justify-between">
        <!-- Left: Menu Toggle & Logo -->
        <div class="flex items-center space-x-3 sm:space-x-4">
            <button onclick="toggleSidebar()" 
                    class="md:hidden p-2 text-gray-600 hover:text-primary hover:bg-primary/10 rounded-lg transition">
                <i class="fas fa-bars text-lg"></i>
            </button>
            
            <div class="flex items-center space-x-2 sm:space-x-3">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl overflow-hidden flex-shrink-0 bg-gradient-to-br from-primary to-primary-dark shadow-md">
                    <?php if (!empty($system_logo) && file_exists("../public/images/" . $system_logo)): ?>
                        <img src="../public/images/<?= htmlspecialchars($system_logo) ?>" 
                             alt="Logo" 
                             class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-user-md text-white text-base sm:text-lg"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <span class="text-base sm:text-lg font-bold text-primary">
                        <?= htmlspecialchars($system_name) ?>
                    </span>
                    <p class="text-xs text-gray-500 hidden sm:block">Doctor Portal</p>
                </div>
            </div>
        </div>

        <!-- Right: Profile -->
        <div class="flex items-center space-x-2 sm:space-x-3">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-semibold text-gray-900">
                    Dr. <?= htmlspecialchars(explode(' ', $doctor_name)[0]) ?>
                </p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($doctor_specialization) ?></p>
            </div>
            
            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-br from-primary to-primary-dark flex items-center justify-center text-white font-semibold overflow-hidden shadow-md text-sm">
                <?php if ($doctor_photo && $doctor_photo !== 'default.png' && file_exists("../public/uploads/$doctor_photo")): ?>
                    <img src="../public/uploads/<?= htmlspecialchars($doctor_photo) ?>" 
                         alt="Profile" 
                         class="w-full h-full object-cover">
                <?php else: ?>
                    <?= strtoupper(substr($doctor_name, 0, 2)) ?>
                <?php endif; ?>
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
<aside id="sidebar" class="sidebar w-64 bg-white fixed left-0 top-14 sm:top-16 h-[calc(100vh-3.5rem)] sm:h-[calc(100vh-4rem)] sidebar-transition shadow-lg z-40 border-r border-gray-200 flex flex-col overflow-hidden">
    <!-- Doctor Profile Section -->
    
    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-1">
        <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
        </div>
        
        <a href="dashboard.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-secondary/10">
            <i class="fas fa-home w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Dashboard</span>
        </a>
        
        <a href="appointments.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-secondary/10">
            <i class="fas fa-calendar-check w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Appointments</span>
        </a>
        
        <a href="schedule.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-secondary/10">
            <i class="fas fa-clock w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">My Schedule</span>
        </a>
        
        <a href="messages.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 bg-gradient-to-r from-primary to-primary-dark text-white shadow-lg shadow-primary-dark/30">
            <i class="fas fa-comments w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">Messages</span>
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
<div class="ml-0 md:ml-64 pt-14 sm:pt-16">
    <div class="h-[calc(100vh-3.5rem)] sm:h-[calc(100vh-4rem)] flex flex-col">
        <div class="flex-1 overflow-hidden">
            <div class="h-full grid grid-cols-1 lg:grid-cols-4 gap-0 lg:gap-4 p-0 lg:p-4">
                <!-- Chat Area -->
                <div class="lg:col-span-3 flex flex-col h-full bg-white border-t lg:border lg:rounded-xl shadow-sm overflow-hidden">
                    <!-- Chat Header -->
                    <div class="bg-gradient-to-r from-primary to-primary-dark p-3 sm:p-4 flex items-center justify-between text-white flex-shrink-0">
                        <div class="flex items-center space-x-2 sm:space-x-4 min-w-0 flex-1">
                            <a href="messages.php" class="text-white hover:text-gray-200 transition flex-shrink-0 p-1">
                                <i class="fas fa-arrow-left text-sm sm:text-base"></i>
                            </a>
                            <div class="relative flex-shrink-0">
                                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center overflow-hidden">
                                    <?php if ($patient['profile_photo'] && $patient['profile_photo'] !== 'default.png' && file_exists("../public/uploads/" . $patient['profile_photo'])): ?>
                                        <img src="../public/uploads/<?= htmlspecialchars($patient['profile_photo']) ?>" 
                                             alt="Patient" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <span class="text-white text-lg font-bold">
                                            <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="absolute bottom-0 right-0 w-2.5 h-2.5 sm:w-3 sm:h-3 bg-blue-400 rounded-full border-2 border-white" data-status-indicator></div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="font-bold text-sm sm:text-lg truncate"><?= htmlspecialchars($patient['fullname']) ?></h2>
                                <p class="text-xs text-blue-100 truncate" data-status-text>
                                    <span class="w-2 h-2 bg-blue-100 rounded-full mr-1.5 inline-block"></span>
                                    Checking status...
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 flex-shrink-0">
                            <!-- View Call History -->
                            <button onclick="CallSystem.openCallHistory()" 
                                    class="p-2 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition"
                                    title="Call History">
                                <i class="fas fa-history text-sm"></i>
                            </button>
                            
                            <!-- Audio Call -->
                            <button onclick="CallSystem.initiateCall(<?= $patient_id ?>, 'patient', '<?= htmlspecialchars($patient['fullname']) ?>', 'audio', '<?= htmlspecialchars($patient['profile_photo']) ?>')" 
                                    class="p-2 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition"
                                    title="Audio Call">
                                <i class="fas fa-phone text-sm"></i>
                            </button>
                            
                            <!-- Video Call -->
                            <button onclick="CallSystem.initiateCall(<?= $patient_id ?>, 'patient', '<?= htmlspecialchars($patient['fullname']) ?>', 'video', '<?= htmlspecialchars($patient['profile_photo']) ?>')" 
                                    class="p-2 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition"
                                    title="Video Call">
                                <i class="fas fa-video text-sm"></i>
                            </button>
                            
                            <!-- Info Panel (Mobile) -->
                            <button onclick="toggleInfoPanel()" 
                                    class="p-2 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition lg:hidden">
                                <i class="fas fa-info-circle text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div id="messagesArea" class="flex-1 overflow-y-auto p-3 sm:p-6 bg-gray-50 space-y-3 sm:space-y-4">
                        <?php if (count($messages) > 0): ?>
                            <?php 
                            $currentDate = null;
                            foreach ($messages as $message): 
                                $messageDate = date('Y-m-d', strtotime($message['created_at']));
                                
                                if ($currentDate !== $messageDate) {
                                    $currentDate = $messageDate;
                                    $today = date('Y-m-d');
                                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                                    
                                    if ($messageDate === $today) {
                                        $dateLabel = 'Today';
                                    } elseif ($messageDate === $yesterday) {
                                        $dateLabel = 'Yesterday';
                                    } else {
                                        $dateLabel = date('F d, Y', strtotime($messageDate));
                                    }
                            ?>
                                <div class="flex justify-center my-2 sm:my-4">
                                    <span class="px-3 sm:px-4 py-1 bg-white rounded-full text-xs font-medium text-gray-600 shadow-sm">
                                        <?= $dateLabel ?>
                                    </span>
                                </div>
                            <?php } ?>

                            <?php if ($message['is_call_notification']): ?>
                            <!-- Call Notification Message -->
                            <?php
                                $callStmt = $pdo->prepare("
                                    SELECT call_type, status, duration, created_at,
                                           caller_id, caller_type
                                    FROM calls 
                                    WHERE id = ?
                                ");
                                $callStmt->execute([$message['call_id']]);
                                $call = $callStmt->fetch();
                                
                                if ($call) {
                                    $isOutgoing = ($call['caller_id'] == $doctor_id && $call['caller_type'] == 'doctor');
                                    $isMissed = in_array($call['status'], ['missed', 'no_answer', 'rejected']) && !$isOutgoing;
                                    $callIcon = $call['call_type'] === 'video' ? 'fa-video' : 'fa-phone';
                                    $callTime = date('g:i A', strtotime($call['created_at']));
                                    
                                    if ($isMissed) {
                                        $statusText = 'Missed ' . ($call['call_type'] === 'video' ? 'video' : 'audio') . ' call';
                                        $statusColor = 'text-red-600';
                                        $bgColor = 'bg-red-50 border-red-200';
                                        $iconColor = 'text-red-500';
                                    } elseif ($call['status'] === 'cancelled') {
                                        $statusText = 'Cancelled';
                                        $statusColor = 'text-gray-600';
                                        $bgColor = 'bg-gray-100 border-gray-200';
                                        $iconColor = 'text-gray-500';
                                    } elseif ($call['duration'] > 0) {
                                        $mins = floor($call['duration'] / 60);
                                        $secs = $call['duration'] % 60;
                                        $durationText = $mins > 0 
                                            ? "{$mins} min" . ($secs > 0 ? ", {$secs} sec" : "")
                                            : "{$secs} sec";
                                        $statusText = ($call['call_type'] === 'video' ? 'Video' : 'Audio') . ' call';
                                        $statusColor = 'text-gray-700';
                                        $bgColor = 'bg-white border-gray-200';
                                        $iconColor = 'text-green-500';
                                    } else {
                                        $statusText = $isOutgoing ? 'Outgoing call' : 'Incoming call';
                                        $statusColor = 'text-gray-600';
                                        $bgColor = 'bg-gray-100 border-gray-200';
                                        $iconColor = 'text-gray-500';
                                    }
                            ?>
                            <div class="flex <?= $isOutgoing ? 'justify-end' : 'justify-start' ?> my-3">
                                <div class="max-w-[85%] sm:max-w-[70%]">
                                    <div class="<?= $bgColor ?> border-2 rounded-2xl px-4 py-3 shadow-sm call-notification">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center shadow-sm">
                                                    <i class="fas <?= $callIcon ?> text-xl <?= $iconColor ?>"></i>
                                                </div>
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold <?= $statusColor ?> text-sm mb-0.5">
                                                    <?= $statusText ?>
                                                </p>
                                                <p class="text-xs text-gray-600">
                                                    <?= $callTime ?>
                                                    <?php if (isset($durationText)): ?>
                                                        <span class="mx-1">•</span>
                                                        <?= $durationText ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            
                                            <button onclick="CallSystem.initiateCall(<?= $patient_id ?>, 'patient', '<?= htmlspecialchars($patient['fullname']) ?>', '<?= $call['call_type'] ?>', '<?= htmlspecialchars($patient['profile_photo']) ?>')" 
                                                    class="flex-shrink-0 p-2.5 bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-full transition transform hover:scale-110 active:scale-95"
                                                    title="Call back">
                                                <i class="fas <?= $callIcon ?> text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                    unset($durationText);
                                } 
                            ?>
                            
                            <?php elseif ($message['sender_type'] === 'doctor'): ?>
                            <!-- Sent Message -->
                            <div class="flex justify-end">
                                <div class="max-w-[85%] sm:max-w-[70%] bg-gradient-to-r from-primary to-primary-dark text-white rounded-2xl rounded-tr-sm px-3 sm:px-4 py-2 sm:py-3 shadow-sm">
                                    <?php if ($message['image_path']): ?>
                                        <div class="mb-2">
                                            <a href="../public/uploads/<?= htmlspecialchars($message['image_path']) ?>" 
                                               target="_blank" 
                                               class="block">
                                                <img src="../public/uploads/<?= htmlspecialchars($message['image_path']) ?>" 
                                                     alt="Shared image" 
                                                     class="max-w-full h-auto rounded-lg hover:opacity-90 transition"
                                                     style="max-height: 300px; object-fit: contain;">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($message['message']): ?>
                                        <p class="text-xs sm:text-sm leading-relaxed break-words"><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-secondary mt-1 text-right">
                                        <?= date('h:i A', strtotime($message['created_at'])) ?>
                                        <?php if ($message['is_read']): ?>
                                            <i class="fas fa-check-double ml-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-check ml-1"></i>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Received Message -->
                            <div class="flex justify-start">
                                <div class="flex items-end space-x-1 sm:space-x-2 max-w-[85%] sm:max-w-[70%]">
                                    <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-gradient-to-br from-primary to-primary-dark flex items-center justify-center overflow-hidden flex-shrink-0">
                                        <?php if ($patient['profile_photo'] && $patient['profile_photo'] !== 'default.png' && file_exists("../public/uploads/" . $patient['profile_photo'])): ?>
                                            <img src="../public/uploads/<?= htmlspecialchars($patient['profile_photo']) ?>" 
                                                 alt="Patient" class="w-full h-full object-cover">
                                                 <?php else: ?>
                                            <span class="text-white text-xs font-bold">
                                                <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bg-gray-200 text-gray-900 rounded-2xl rounded-tl-sm px-3 sm:px-4 py-2 sm:py-3 shadow-sm">
                                        <?php if ($message['image_path']): ?>
                                            <div class="mb-2">
                                                <a href="../public/uploads/<?= htmlspecialchars($message['image_path']) ?>" 
                                                   target="_blank" 
                                                   class="block">
                                                    <img src="../public/uploads/<?= htmlspecialchars($message['image_path']) ?>" 
                                                         alt="Shared image" 
                                                         class="max-w-full h-auto rounded-lg hover:opacity-90 transition"
                                                         style="max-height: 300px; object-fit: contain;">
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($message['message']): ?>
                                            <p class="text-xs sm:text-sm leading-relaxed break-words"><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                        <?php endif; ?>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?= date('h:i A', strtotime($message['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="h-full flex items-center justify-center">
                                <div class="text-center">
                                    <div class="w-20 h-20 gradient-bg rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                        <i class="fas fa-comments text-white text-3xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">No messages yet</h3>
                                    <p class="text-gray-600 text-sm">Start the conversation with <?= htmlspecialchars($patient['fullname']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div id="bottomAnchor"></div>
                    </div>

                    <!-- Message Input -->
                    <div class="p-2 sm:p-4 bg-white border-t border-gray-200 flex-shrink-0">
                        <!-- Image Preview Container -->
                        <div id="imagePreviewContainer" class="hidden mb-3 p-3 bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl border-2 border-blue-200 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center space-x-3 flex-1 min-w-0">
                                    <div class="relative flex-shrink-0">
                                        <img id="imagePreview" src="" alt="Preview" class="w-20 h-20 object-cover rounded-lg shadow-md border-2 border-white">
                                        <div class="absolute -top-1 -right-1 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center">
                                            <i class="fas fa-image text-white text-xs"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate" id="imageName">image.jpg</p>
                                        <p class="text-xs text-gray-600 flex items-center mt-0.5">
                                            <i class="fas fa-file-image text-blue-500 mr-1"></i>
                                            <span id="imageSize">0 KB</span>
                                        </p>
                                        <p class="text-xs text-green-600 mt-1 font-medium">
                                            <i class="fas fa-check-circle mr-1"></i>Ready to send
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2 flex-shrink-0">
                                    <button type="button" 
                                            onclick="quickSendImage()" 
                                            class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:from-blue-600 hover:to-purple-700 transition font-medium text-sm shadow-md hover:shadow-lg transform hover:scale-105 active:scale-95 flex items-center space-x-1"
                                            title="Send image now">
                                        <i class="fas fa-paper-plane text-sm"></i>
                                        <span>Send</span>
                                    </button>
                                    
                                    <button type="button" 
                                            onclick="cancelImageUpload()" 
                                            class="p-2 bg-red-100 text-red-600 hover:bg-red-200 rounded-lg transition transform hover:scale-105 active:scale-95"
                                            title="Remove image">
                                        <i class="fas fa-times text-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <form id="messageForm" action="../routes/routes.php" method="POST" enctype="multipart/form-data" class="flex items-end gap-2 sm:gap-3">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="receiver_id" value="<?= $patient_id ?>">
                            <input type="hidden" name="receiver_type" value="patient">
                            
                            <input type="file" id="imageInput" name="image" accept="image/*" class="hidden" onchange="previewImage(this)">
                            <button type="button" 
                                    onclick="document.getElementById('imageInput').click()" 
                                    class="p-2 sm:p-3 text-gray-500 hover:text-primary hover:bg-purple-50 rounded-lg transition flex-shrink-0 transform hover:scale-105 active:scale-95" 
                                    title="Attach image">
                                <i class="fas fa-paperclip text-base sm:text-xl"></i>
                            </button>
                            
                            <div class="flex-1 min-w-0">
                                <textarea 
                                    name="message" 
                                    id="messageInput"
                                    rows="1"
                                    placeholder="Type your message... (or just send image)"
                                    class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
                                    onkeydown="handleKeyPress(event)"></textarea>
                            </div>
                            
                            <button type="submit" 
                                    class="px-4 sm:px-6 py-2 sm:py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg sm:rounded-xl hover:from-blue-600 hover:to-purple-700 transition font-medium text-sm sm:text-base flex-shrink-0 shadow-md hover:shadow-lg transform hover:scale-105 active:scale-95">
                                <i class="fas fa-paper-plane sm:mr-2"></i>
                                <span class="hidden sm:inline">Send</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Patient Info Panel (Desktop) -->
                <div id="infoPanel" class="hidden lg:block bg-white lg:rounded-xl shadow-sm border-l lg:border border-gray-200 overflow-y-auto p-4 sm:p-6">
                    <!-- Patient Profile -->
                    <div class="text-center mb-6 pb-6 border-b border-gray-200">
                        <div class="relative inline-block mb-4">
                            <div class="w-24 h-24 rounded-2xl gradient-bg flex items-center justify-center overflow-hidden shadow-lg ring-4 ring-purple-100">
                                <?php if ($patient['profile_photo'] && $patient['profile_photo'] !== 'default.png' && file_exists("../public/uploads/" . $patient['profile_photo'])): ?>
                                    <img src="../public/uploads/<?= htmlspecialchars($patient['profile_photo']) ?>" 
                                         alt="Patient" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-white text-3xl font-bold">
                                        <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="absolute bottom-2 right-0 w-5 h-5 bg-green-500 rounded-full border-3 border-white shadow-lg"></div>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-1">
                            <?= htmlspecialchars($patient['fullname']) ?>
                        </h3>
                        <p class="text-sm text-gray-600 mb-3">Patient</p>
                                <p class="text-xs text-blue-600 truncate" data-status-text>
                                    <span class="w-2 h-2 bg-blue-600 rounded-full mr-1.5 inline-block"></span>
                                    Checking status...
                                </p>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-3 mb-6">
                        <h4 class="font-semibold text-gray-900 text-sm mb-3 flex items-center">
                            <i class="fas fa-address-card text-primary mr-2"></i>
                            Contact Details
                        </h4>

                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-xl hover:bg-blue-100 transition">
                            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-phone text-white text-sm"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-600 mb-0.5">Phone Number</p>
                                <a href="tel:<?= htmlspecialchars($patient['phone']) ?>" class="font-semibold text-gray-900 hover:text-primary truncate block">
                                    <?= htmlspecialchars($patient['phone']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-3 bg-purple-50 rounded-xl hover:bg-purple-100 transition">
                            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-envelope text-white text-sm"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-600 mb-0.5">Email Address</p>
                                <a href="mailto:<?= htmlspecialchars($patient['email']) ?>" class="font-semibold text-gray-900 hover:text-primary truncate block text-sm">
                                    <?= htmlspecialchars($patient['email']) ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <?php if ($patient['dob'] || $patient['gender'] || $patient['address']): ?>
                    <div class="space-y-3 mb-6 pb-6 border-b border-gray-200">
                        <h4 class="font-semibold text-gray-900 text-sm mb-3 flex items-center">
                            <i class="fas fa-user text-primary mr-2"></i>
                            Personal Information
                        </h4>

                        <?php if ($patient['dob']): ?>
                        <div class="flex items-center space-x-3 p-3 bg-pink-50 rounded-xl">
                            <div class="w-10 h-10 bg-pink-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-birthday-cake text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Date of Birth</p>
                                <p class="font-semibold text-gray-900"><?= date('M d, Y', strtotime($patient['dob'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($patient['gender']): ?>
                        <div class="flex items-center space-x-3 p-3 bg-indigo-50 rounded-xl">
                            <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-venus-mars text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Gender</p>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars(ucfirst($patient['gender'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($patient['address']): ?>
                        <div class="flex items-start space-x-3 p-3 bg-orange-50 rounded-xl">
                            <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-white text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-600 mb-0.5">Address</p>
                                <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($patient['address']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="space-y-2.5">
                        <a href="appointments.php?patient_id=<?= $patient_id ?>" 
                           class="flex items-center justify-center space-x-2 w-full gradient-bg text-white px-4 py-3 rounded-xl hover:shadow-lg transition font-medium">
                            <i class="fas fa-calendar-alt"></i>
                            <span>View Appointments</span>
                        </a>
                        <a href="tel:<?= htmlspecialchars($patient['phone']) ?>" 
                           class="flex items-center justify-center space-x-2 w-full bg-white border-2 border-primary text-primary px-4 py-3 rounded-xl hover:bg-primary hover:text-white transition font-medium">
                            <i class="fas fa-phone"></i>
                            <span>Call Patient</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../public/js/online-status.js"></script>
<script>
// Initialize
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    document.getElementById('messageInput').focus();
});

// Auto-resize textarea
const messageInput = document.getElementById('messageInput');
messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Handle Enter key
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        document.getElementById('messageForm').submit();
    }
}

// Scroll to bottom
function scrollToBottom(smooth = false) {
    const messagesArea = document.getElementById('messagesArea');
    const bottomAnchor = document.getElementById('bottomAnchor');
    if (smooth) {
        bottomAnchor.scrollIntoView({ behavior: 'smooth', block: 'end' });
    } else {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }
}

// Toggle sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('hidden');
}

// Toggle info panel
function toggleInfoPanel() {
    document.getElementById('infoPanel').classList.toggle('hidden');
}

// Image upload functions
function previewImage(input) {
    const file = input.files[0];
    if (!file) return;
    
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        input.value = '';
        return;
    }
    
    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('Image size must be less than 5MB');
        input.value = '';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('imagePreview').src = e.target.result;
        document.getElementById('imageName').textContent = file.name;
        document.getElementById('imageSize').textContent = formatFileSize(file.size);
        document.getElementById('imagePreviewContainer').classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

function cancelImageUpload() {
    document.getElementById('imageInput').value = '';
    document.getElementById('imagePreviewContainer').classList.add('hidden');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Update form submission to handle AJAX properly
document.getElementById('messageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    const imageFile = document.getElementById('imageInput').files[0];
    
    // Require either message or image
    if (!message && !imageFile) {
        alert('Please enter a message or select an image');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('receiver_id', '<?= $patient_id ?>');
    formData.append('receiver_type', 'patient');
    
    // Add message only if it's not empty
    if (message) {
        formData.append('message', message);
    }
    
    // Add image if selected
    if (imageFile) {
        formData.append('image', imageFile);
    }
    
    // Show sending indicator
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i><span class="hidden sm:inline">Sending...</span>';
    
    try {
        const response = await fetch('../routes/routes.php', {
            method: 'POST',
            body: formData
        });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text.substring(0, 500));
            alert('Server error. Check console for details.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnContent;
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Clear form
            messageInput.value = '';
            messageInput.style.height = 'auto';
            cancelImageUpload();
            
            // Show success briefly
            submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i><span class="hidden sm:inline">Sent!</span>';
            
            // Reload messages after short delay
            setTimeout(() => {
                window.location.reload();
            }, 300);
        } else {
            alert('Failed to send message: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnContent;
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Failed to send message. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnContent;
    }
});

function quickSendImage() {
    const imageFile = document.getElementById('imageInput').files[0];
    
    if (!imageFile) {
        alert('No image selected');
        return;
    }
    
    // Trigger form submission
    document.getElementById('messageForm').dispatchEvent(new Event('submit'));
}

// Call System placeholder (add full implementation from documents 2/3)
const CallSystem = {
    currentCall: null,
    callCheckInterval: null,
    callTimer: null,
    callDuration: 0,
    
    init() {
        this.startCallChecking();
    },
    
    startCallChecking() {
        this.callCheckInterval = setInterval(() => {
            this.checkIncomingCalls();
        }, 2000);
    },
    
    async checkIncomingCalls() {
        try {
            const response = await fetch('../routes/routes.php?action=check_incoming_calls');
            const data = await response.json();
            
            if (data.success && data.incoming_calls.length > 0) {
                const call = data.incoming_calls[0];
                if (!this.currentCall || this.currentCall.id !== call.id) {
                    console.log('Incoming call:', call);
                    // Show incoming call modal here
                }
            }
        } catch (error) {
            console.error('Error checking calls:', error);
        }
    },
    
    async initiateCall(receiverId, receiverType, receiverName, callType = 'audio', photo = '') {
        try {
            const formData = new FormData();
            formData.append('action', 'initiate_call');
            formData.append('receiver_id', receiverId);
            formData.append('receiver_type', receiverType);
            formData.append('call_type', callType);
            
            const response = await fetch('../routes/routes.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('Call initiated:', data.call_id);
                alert(`${callType} call initiated to ${receiverName}`);
            } else {
                alert('Failed to initiate call: ' + data.message);
            }
        } catch (error) {
            console.error('Error initiating call:', error);
            alert('Failed to initiate call');
        }
    },
    
    openCallHistory() {
        console.log('Opening call history...');
        alert('Call history feature - coming soon!');
    }
};

// Initialize Call System
document.addEventListener('DOMContentLoaded', () => {
    CallSystem.init();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (CallSystem.callCheckInterval) {
        clearInterval(CallSystem.callCheckInterval);
    }
});
</script>

</body>
</html>
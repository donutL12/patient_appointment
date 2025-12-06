<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
//user/chat.php
$user_id = $_SESSION['user_id'];
$page_title = "Chat";
$current_page = "messages.php";
$doctor_id = $_GET['doctor_id'] ?? null;

if (!$doctor_id) {
    $_SESSION['error'] = 'Invalid doctor ID';
    header('Location: messages.php');
    exit;
}

// Get doctor details
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            fullname,
            specialization,
            profile_photo,
            phone,
            email
        FROM doctors 
        WHERE id = ?
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) {
        $_SESSION['error'] = 'Doctor not found';
        header('Location: messages.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Doctor Fetch Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading doctor details';
    header('Location: messages.php');
    exit;
}

// Get all messages
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            u.fullname as sender_name,
            u.profile_photo as sender_photo
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'patient'
        WHERE (m.sender_id = ? AND m.sender_type = 'patient' AND m.receiver_id = ? AND m.receiver_type = 'doctor')
           OR (m.sender_id = ? AND m.sender_type = 'doctor' AND m.receiver_id = ? AND m.receiver_type = 'patient')
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$user_id, $doctor_id, $doctor_id, $user_id]);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    $updateStmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE receiver_id = ? 
        AND receiver_type = 'patient' 
        AND sender_id = ? 
        AND sender_type = 'doctor' 
        AND is_read = 0
    ");
    $updateStmt->execute([$user_id, $doctor_id]);
} catch (PDOException $e) {
    error_log("Messages Fetch Error: " . $e->getMessage());
    $messages = [];
}

// Get user details for sidebar
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? '';
$user_photo = $_SESSION['user_photo'] ?? 'default.png';

include '../includes/header.php';
?>
<div data-current-user-id="<?= $user_id ?>" data-current-user-type="patient" style="display:none;"></div>
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
<div class="ml-0 md:ml-64 pt-14 sm:pt-16">
    <div class="h-[calc(100vh-3.5rem)] sm:h-[calc(100vh-4rem)] flex flex-col">
        <div class="flex-1 overflow-hidden">
            <div class="h-full grid grid-cols-1 lg:grid-cols-4 gap-0 lg:gap-4 p-0 lg:p-4">
                <!-- Chat Area -->
                <div class="lg:col-span-3 flex flex-col h-full bg-white border-t lg:border lg:rounded-xl shadow-sm overflow-hidden">
                    <!-- Chat Header -->
                    <div class="bg-primary p-3 sm:p-4 flex items-center justify-between text-white flex-shrink-0">
                        <div class="flex items-center space-x-2 sm:space-x-4 min-w-0 flex-1">
                            <a href="messages.php" class="text-white hover:text-gray-200 transition flex-shrink-0 p-1">
                                <i class="fas fa-arrow-left text-sm sm:text-base"></i>
                            </a>
                            <div class="relative flex-shrink-0">
                                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center overflow-hidden">
                                    <?php if ($doctor['profile_photo'] && $doctor['profile_photo'] !== 'doctor.png' && file_exists("../public/uploads/" . $doctor['profile_photo'])): ?>
                                        <img src="../public/uploads/<?= htmlspecialchars($doctor['profile_photo']) ?>" 
                                             alt="Doctor" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-user-md text-white text-base sm:text-xl"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="absolute bottom-0 right-0 w-2.5 h-2.5 sm:w-3 sm:h-3 bg-blue-400 rounded-full border-2 border-white" data-status-indicator></div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2 class="font-bold text-sm sm:text-lg truncate">Dr. <?= htmlspecialchars($doctor['fullname']) ?></h2>
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
    <button onclick="CallSystem.initiateCall(<?= $doctor_id ?>, 'doctor', 'Dr. <?= htmlspecialchars($doctor['fullname']) ?>', 'audio', '<?= htmlspecialchars($doctor['profile_photo']) ?>')" 
            class="p-2 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition"
            title="Audio Call">
        <i class="fas fa-phone text-sm"></i>
    </button>
    
    <!-- Video Call -->
    <button onclick="CallSystem.initiateCall(<?= $doctor_id ?>, 'doctor', 'Dr. <?= htmlspecialchars($doctor['fullname']) ?>', 'video', '<?= htmlspecialchars($doctor['profile_photo']) ?>')" 
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
            
            // Show date separator
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
            // Get call details
            $callStmt = $pdo->prepare("
                SELECT call_type, status, duration, created_at,
                       caller_id, caller_type
                FROM calls 
                WHERE id = ?
            ");
            $callStmt->execute([$message['call_id']]);
            $call = $callStmt->fetch();
            
            if ($call) {
                $isOutgoing = ($call['caller_id'] == $user_id && $call['caller_type'] == 'patient');
                $isMissed = in_array($call['status'], ['missed', 'no_answer', 'rejected']) && !$isOutgoing;
                $callIcon = $call['call_type'] === 'video' ? 'fa-video' : 'fa-phone';
                $callTime = date('g:i A', strtotime($call['created_at']));
                
                // Determine call status text
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
                        <!-- Call Icon -->
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center shadow-sm">
                                <i class="fas <?= $callIcon ?> text-xl <?= $iconColor ?>"></i>
                            </div>
                        </div>
                        
                        <!-- Call Info -->
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
                        
                        <!-- Call Back Button -->
                        <button onclick="CallSystem.initiateCall(<?= $doctor_id ?>, 'doctor', 'Dr. <?= htmlspecialchars($doctor['fullname']) ?>', '<?= $call['call_type'] ?>', '<?= htmlspecialchars($doctor['profile_photo']) ?>')" 
                                class="flex-shrink-0 p-2.5 bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-full transition transform hover:scale-110 active:scale-95"
                                title="Call back">
                            <i class="fas <?= $callIcon ?> text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php 
                unset($durationText); // Clear for next iteration
            } 
        ?>
        
        <?php elseif ($message['sender_type'] === 'patient'): ?>
        <!-- Regular Sent Message -->
        <div class="flex justify-end">
            <div class="max-w-[85%] sm:max-w-[70%] bg-primary text-white rounded-2xl rounded-tr-sm px-3 sm:px-4 py-2 sm:py-3 shadow-sm">
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
                <p class="text-xs text-blue-100 mt-1 text-right">
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
        <!-- Regular Received Message -->
        <div class="flex justify-start">
            <div class="flex items-end space-x-1 sm:space-x-2 max-w-[85%] sm:max-w-[70%]">
                <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-primary flex items-center justify-center overflow-hidden flex-shrink-0">
                    <?php if ($doctor['profile_photo'] && $doctor['profile_photo'] !== 'doctor.png' && file_exists("../public/uploads/" . $doctor['profile_photo'])): ?>
                        <img src="../public/uploads/<?= htmlspecialchars($doctor['profile_photo']) ?>" 
                             alt="Doctor" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user-md text-white text-xs"></i>
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
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                    <i class="fas fa-comments text-gray-400 text-2xl sm:text-3xl"></i>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2">No messages yet</h3>
                <p class="text-gray-600 text-xs sm:text-sm">Start the conversation</p>
            </div>
        </div>
    <?php endif; ?>
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
        
        <!-- Action Buttons -->
        <div class="flex items-center space-x-2 flex-shrink-0">
            <!-- Quick Send Button -->
            <button type="button" 
                    onclick="quickSendImage()" 
                    class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:from-blue-600 hover:to-purple-700 transition font-medium text-sm shadow-md hover:shadow-lg transform hover:scale-105 active:scale-95 flex items-center space-x-1"
                    title="Send image now">
                <i class="fas fa-paper-plane text-sm"></i>
                <span>Send</span>
            </button>
            
            <!-- Cancel Button -->
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
    <input type="hidden" name="receiver_id" value="<?= $doctor_id ?? $patient_id ?>">
    <input type="hidden" name="receiver_type" value="<?= isset($doctor_id) ? 'doctor' : 'patient' ?>">
                            
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

                <!-- Doctor Info Sidebar -->
                <div id="infoPanel" class="hidden lg:block fixed lg:relative inset-0 lg:inset-auto bg-black bg-opacity-50 lg:bg-transparent z-50 lg:z-auto">
                    <div class="absolute lg:relative right-0 top-0 bottom-0 w-80 lg:w-auto bg-white lg:rounded-xl shadow-lg lg:shadow-sm border-l lg:border border-gray-200 p-4 sm:p-6 overflow-y-auto">
                        <!-- Close button for mobile -->
                        <button onclick="toggleInfoPanel()" class="lg:hidden absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>

                        <div class="text-center mb-4 sm:mb-6">
                            <div class="relative inline-block">
                                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-primary flex items-center justify-center overflow-hidden mx-auto mb-3 sm:mb-4">
                                    <?php if ($doctor['profile_photo'] && $doctor['profile_photo'] !== 'doctor.png' && file_exists("../public/uploads/" . $doctor['profile_photo'])): ?>
                                        <img src="../public/uploads/<?= htmlspecialchars($doctor['profile_photo']) ?>" 
                                             alt="Doctor" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-user-md text-white text-3xl sm:text-4xl"></i>
                                    <?php endif; ?>
                                </div>
                               <div class="absolute bottom-3 sm:bottom-4 right-0 w-3 h-3 sm:w-4 sm:h-4 bg-green-500 rounded-full border-2 border-white hidden" data-status-indicator></div>
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-1 truncate">
                                Dr. <?= htmlspecialchars($doctor['fullname']) ?>
                            </h3>
                            <p class="text-primary font-medium mb-2 text-sm sm:text-base truncate">
                                <?= htmlspecialchars($doctor['specialization']) ?>
                            </p>
                                <p class="text-xs text-blue-600 truncate" data-status-text>
                                    <span class="w-2 h-2 bg-blue-600 rounded-full mr-1.5 inline-block"></span>
                                    Checking status...
                                </p>
                        </div>

                        <div class="space-y-3 sm:space-y-4 mb-4 sm:mb-6">
                            <div class="flex items-center text-xs sm:text-sm">
                                <div class="w-9 h-9 sm:w-10 sm:h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-phone text-blue-600 text-sm"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs text-gray-500">Phone</p>
                                    <p class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($doctor['phone']) ?></p>
                                </div>
                            </div>

                            <div class="flex items-center text-xs sm:text-sm">
                                <div class="w-9 h-9 sm:w-10 sm:h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-envelope text-purple-600 text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-500">Email</p>
                                    <p class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($doctor['email']) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 sm:space-y-3 pt-4 sm:pt-6 border-t border-gray-200">
                            <a href="book_appointment.php?doctor_id=<?= $doctor_id ?>" 
                               class="w-full bg-primary text-white px-4 py-2 sm:py-3 rounded-lg hover:bg-primary-dark transition font-medium text-center block text-sm">
                                <i class="fas fa-calendar-plus mr-2"></i>
                                Book Appointment
                            </a>
                            <a href="tel:<?= htmlspecialchars($doctor['phone']) ?>" 
                               class="w-full bg-white border border-gray-300 text-gray-700 px-4 py-2 sm:py-3 rounded-lg hover:bg-gray-50 transition font-medium text-center block text-sm">
                                <i class="fas fa-phone mr-2"></i>
                                Call Doctor
                            </a>
                        </div>

                        <div class="mt-4 sm:mt-6 p-3 sm:p-4 bg-blue-50 rounded-lg sm:rounded-xl">
                            <h4 class="font-semibold text-gray-900 mb-2 text-xs sm:text-sm">
                                <i class="fas fa-info-circle text-primary mr-1"></i>
                                Quick Tips
                            </h4>
                            <ul class="text-xs text-gray-600 space-y-1">
                                <li>• Be clear about your symptoms</li>
                                <li>• Mention any medications you're taking</li>
                                <li>• Ask questions if unsure</li>
                                <li>• Response time: Usually within hours</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .messages-area::-webkit-scrollbar {
        width: 6px;
    }
    
    .messages-area::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .messages-area::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 10px;
    }
    [data-status-text] {
    color: #1e40af !important; /* Dark blue color */
}

[data-status-text] span {
    background-color: #1e40af !important; /* Dark blue dot */
}

/* For white/light backgrounds in sidebar */
.bg-white [data-status-text],
#infoPanel [data-status-text] {
    color: #1e40af !important; /* Dark blue */
}

/* For dark/primary backgrounds in header */
.bg-primary [data-status-text] {
    color: #dbeafe !important; /* Light blue */
}

.bg-primary [data-status-text] span {
    background-color: #dbeafe !important; /* Light blue dot */
}

/* Image Preview Animations */
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

/* Button Hover Effects */
#imagePreviewContainer button {
    transition: all 0.2s ease;
}

/* Image Preview Hover */
#imagePreview {
    transition: transform 0.2s ease;
}

#imagePreview:hover {
    transform: scale(1.05);
}

/* Responsive Adjustments */
@media (max-width: 640px) {
    #imagePreviewContainer {
        padding: 0.5rem;
    }
    
    #imagePreview {
        width: 4rem;
        height: 4rem;
    }
}
</style>



<script>
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
    function scrollToBottom() {
        const messagesArea = document.getElementById('messagesArea');
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    // Scroll to bottom on load
    window.addEventListener('load', scrollToBottom);

    // Toggle info panel
    function toggleInfoPanel() {
        const panel = document.getElementById('infoPanel');
        panel.classList.toggle('hidden');
        if (!panel.classList.contains('hidden')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    // Close info panel when clicking outside on mobile
    document.getElementById('infoPanel')?.addEventListener('click', function(e) {
        if (e.target === this) {
            toggleInfoPanel();
        }
    });
    /**
 * In-App Call System
 * Add this script to both user/chat.php and doctor/chat.php
 * Place before the closing </body> tag
 */

// Call System State
const CallSystem = {
    currentCall: null,
    callCheckInterval: null,
    callTimer: null,
    callDuration: 0,
    
    init() {
        this.startCallChecking();
        this.setupEventListeners();
    },
    
    setupEventListeners() {
        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            const outgoingModal = document.getElementById('outgoingCallModal');
            const incomingModal = document.getElementById('incomingCallModal');
            const activeModal = document.getElementById('activeCallModal');
            
            if (e.target === outgoingModal) this.endCall();
            if (e.target === incomingModal) this.rejectCall();
            if (e.target === activeModal) this.endCall();
        });
    },
    
    // Start checking for incoming calls
    startCallChecking() {
        this.callCheckInterval = setInterval(() => {
            this.checkIncomingCalls();
        }, 2000); // Check every 2 seconds
    },
    
    // Check for incoming calls
    async checkIncomingCalls() {
        try {
            const response = await fetch('../routes/routes.php?action=check_incoming_calls');
            const data = await response.json();
            
            if (data.success && data.incoming_calls.length > 0) {
                const call = data.incoming_calls[0];
                if (!this.currentCall || this.currentCall.id !== call.id) {
                    this.showIncomingCall(call);
                }
            }
        } catch (error) {
            console.error('Error checking calls:', error);
        }
    },
    
    // Initiate outgoing call
    async initiateCall(receiverId, receiverType, receiverName, callType = 'audio') {
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
                this.currentCall = {
                    id: data.call_id,
                    receiver_name: receiverName,
                    type: callType,
                    status: 'calling'
                };
                
                this.showOutgoingCall();
                this.updateCallStatus(data.call_id, 'ringing');
                
                // Auto-end if no answer after 30 seconds
                setTimeout(() => {
                    if (this.currentCall && this.currentCall.status === 'calling') {
                        this.endCall();
                    }
                }, 30000);
            } else {
                alert('Failed to initiate call: ' + data.message);
            }
        } catch (error) {
            console.error('Error initiating call:', error);
            alert('Failed to initiate call');
        }
    },
    
    // Show outgoing call modal
    showOutgoingCall() {
        const modal = document.getElementById('outgoingCallModal');
        const receiverName = document.getElementById('outgoingReceiverName');
        const callType = document.getElementById('outgoingCallType');
        
        receiverName.textContent = this.currentCall.receiver_name;
        callType.innerHTML = this.currentCall.type === 'video' 
            ? '<i class="fas fa-video mr-2"></i>Video Call'
            : '<i class="fas fa-phone mr-2"></i>Voice Call';
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Play ringing sound (optional)
        this.playRingtone();
    },
    
    // Show incoming call modal
    showIncomingCall(call) {
        this.currentCall = {
            id: call.id,
            caller_name: call.caller_name,
            caller_photo: call.caller_photo,
            type: call.call_type,
            status: 'ringing'
        };
        
        const modal = document.getElementById('incomingCallModal');
        const callerName = document.getElementById('incomingCallerName');
        const callerPhoto = document.getElementById('incomingCallerPhoto');
        const callType = document.getElementById('incomingCallType');
        
        callerName.textContent = call.caller_type === 'doctor' 
            ? 'Dr. ' + call.caller_name 
            : call.caller_name;
        
        // Set caller photo
        if (call.caller_photo && call.caller_photo !== 'default.png') {
            callerPhoto.innerHTML = `<img src="../public/uploads/${call.caller_photo}" alt="Caller" class="w-full h-full object-cover">`;
        } else {
            callerPhoto.innerHTML = `<span class="text-white text-4xl font-bold">${call.caller_name.charAt(0).toUpperCase()}</span>`;
        }
        
        callType.innerHTML = call.call_type === 'video'
            ? '<i class="fas fa-video mr-2"></i>Video Call'
            : '<i class="fas fa-phone mr-2"></i>Voice Call';
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Play ringing sound
        this.playRingtone();
    },
    
    // Answer incoming call
    async answerCall() {
        if (!this.currentCall) return;
        
        await this.updateCallStatus(this.currentCall.id, 'answered');
        this.currentCall.status = 'active';
        
        document.getElementById('incomingCallModal').classList.add('hidden');
        this.showActiveCall();
        this.stopRingtone();
        this.startCallTimer();
    },
    
    // Reject incoming call
    async rejectCall() {
        if (!this.currentCall) return;
        
        await this.updateCallStatus(this.currentCall.id, 'rejected');
        
        document.getElementById('incomingCallModal').classList.add('hidden');
        document.body.style.overflow = '';
        this.stopRingtone();
        this.currentCall = null;
    },
    
    // Show active call interface
    showActiveCall() {
        const modal = document.getElementById('activeCallModal');
        const contactName = document.getElementById('activeCallName');
        const duration = document.getElementById('callDuration');
        
        contactName.textContent = this.currentCall.receiver_name || this.currentCall.caller_name;
        duration.textContent = '00:00';
        
        modal.classList.remove('hidden');
        
        // Simulate call connection (in real app, this would establish WebRTC connection)
        console.log('Call connected');
    },
    
    // Start call timer
    startCallTimer() {
        this.callDuration = 0;
        this.callTimer = setInterval(() => {
            this.callDuration++;
            const minutes = Math.floor(this.callDuration / 60);
            const seconds = this.callDuration % 60;
            const duration = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            const durationEl = document.getElementById('callDuration');
            if (durationEl) {
                durationEl.textContent = duration;
            }
        }, 1000);
    },
    
    // End call
    async endCall() {
        if (!this.currentCall) return;
        
        await this.updateCallStatus(this.currentCall.id, 'ended');
        
        // Hide all modals
        document.getElementById('outgoingCallModal')?.classList.add('hidden');
        document.getElementById('incomingCallModal')?.classList.add('hidden');
        document.getElementById('activeCallModal')?.classList.add('hidden');
        document.body.style.overflow = '';
        
        this.stopRingtone();
        
        if (this.callTimer) {
            clearInterval(this.callTimer);
            this.callTimer = null;
        }
        
        this.currentCall = null;
        this.callDuration = 0;
    },
    
    // Update call status
    async updateCallStatus(callId, status) {
        try {
            const formData = new FormData();
            formData.append('action', 'update_call_status');
            formData.append('call_id', callId);
            formData.append('status', status);
            
            await fetch('../routes/routes.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error updating call status:', error);
        }
    },
    
    // Mute/unmute audio
    toggleMute() {
        const btn = document.getElementById('muteBtn');
        const icon = btn.querySelector('i');
        
        if (icon.classList.contains('fa-microphone')) {
            icon.classList.remove('fa-microphone');
            icon.classList.add('fa-microphone-slash');
            btn.classList.add('bg-red-500');
            // In real app: mute audio stream
        } else {
            icon.classList.remove('fa-microphone-slash');
            icon.classList.add('fa-microphone');
            btn.classList.remove('bg-red-500');
            // In real app: unmute audio stream
        }
    },
    
    // Toggle speaker
    toggleSpeaker() {
        const btn = document.getElementById('speakerBtn');
        const icon = btn.querySelector('i');
        
        if (icon.classList.contains('fa-volume-up')) {
            icon.classList.remove('fa-volume-up');
            icon.classList.add('fa-volume-mute');
            // In real app: switch to earpiece
        } else {
            icon.classList.remove('fa-volume-mute');
            icon.classList.add('fa-volume-up');
            // In real app: switch to speaker
        }
    },
    
    // Play ringtone
    playRingtone() {
        // In a real app, play an actual ringtone audio file
        console.log('Playing ringtone...');
    },
    
    // Stop ringtone
    stopRingtone() {
        // In a real app, stop the ringtone audio
        console.log('Stopping ringtone...');
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    CallSystem.init();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (CallSystem.callCheckInterval) {
        clearInterval(CallSystem.callCheckInterval);
    }
    if (CallSystem.currentCall) {
        CallSystem.endCall();
    }
});

// Expose CallSystem globally for HTML onclick handlers
window.CallSystem = CallSystem;

// Image Upload Functions
let selectedImage = null;

function previewImage(input) {
    const file = input.files[0];
    
    if (!file) return;
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        input.value = '';
        return;
    }
    
    // Validate file size (5MB max)
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        alert('Image size must be less than 5MB');
        input.value = '';
        return;
    }
    
    selectedImage = file;
    
    // Show preview
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
    selectedImage = null;
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

// Update form submission to handle images with or without text
document.getElementById('messageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    const imageFile = document.getElementById('imageInput').files[0];
    
    // Require either message or image (but not necessarily both)
    if (!message && !imageFile) {
        alert('Please enter a message or select an image');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('receiver_id', '<?= $doctor_id ?? $patient_id ?>');
    formData.append('receiver_type', '<?= isset($doctor_id) ? "doctor" : "patient" ?>');
    
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

// Quick send image button (optional - add to image preview)
function quickSendImage() {
    const imageFile = document.getElementById('imageInput').files[0];
    
    if (!imageFile) {
        alert('No image selected');
        return;
    }
    
    // Get message text if any
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('receiver_id', '<?= $doctor_id ?? $patient_id ?>');
    formData.append('receiver_type', '<?= isset($doctor_id) ? "doctor" : "patient" ?>');
    formData.append('image', imageFile);
    
    // Include text message if present
    if (message) {
        formData.append('message', message);
    }
    
    // Show sending indicator in preview
    const previewContainer = document.getElementById('imagePreviewContainer');
    const originalContent = previewContainer.innerHTML;
    previewContainer.innerHTML = `
        <div class="flex items-center justify-center py-4">
            <i class="fas fa-spinner fa-spin text-primary text-2xl mr-3"></i>
            <span class="text-gray-700 font-medium">Sending image...</span>
        </div>
    `;
    
    fetch('../routes/routes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear form
            messageInput.value = '';
            messageInput.style.height = 'auto';
            cancelImageUpload();
            
            // Show success message briefly
            previewContainer.innerHTML = `
                <div class="flex items-center justify-center py-4 text-green-600">
                    <i class="fas fa-check-circle text-2xl mr-3"></i>
                    <span class="font-medium">Image sent!</span>
                </div>
            `;
            
            // Reload after short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            alert('Failed to send image: ' + data.message);
            previewContainer.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error sending image:', error);
        alert('Failed to send image. Please try again.');
        previewContainer.innerHTML = originalContent;
    });
}

// Enhanced image preview with quick send button
function previewImage(input) {
    const file = input.files[0];
    
    if (!file) return;
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        input.value = '';
        return;
    }
    
    // Validate file size (5MB max)
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        alert('Image size must be less than 5MB');
        input.value = '';
        return;
    }
    
    selectedImage = file;
    
    // Show preview
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
    selectedImage = null;
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
</script>

<!-- ============================================ -->
<!-- ENHANCED CALL SYSTEM MODALS WITH VIDEO & HISTORY -->
<!-- Add this before closing </body> tag in chat.php -->
<!-- ============================================ -->

<!-- Outgoing Call Modal -->
<div id="outgoingCallModal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-[9999] flex items-center justify-center p-4">
    <div class="bg-gradient-to-br from-blue-600 to-purple-600 rounded-3xl shadow-2xl max-w-md w-full p-8 text-center text-white relative overflow-hidden">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-white to-transparent animate-pulse"></div>
        </div>
        
        <div class="relative z-10">
            <div id="outgoingAvatar" class="w-32 h-32 mx-auto mb-6 rounded-full bg-white bg-opacity-20 flex items-center justify-center overflow-hidden ring-4 ring-white ring-opacity-30 shadow-2xl">
                <i class="fas fa-user text-6xl text-white opacity-80"></i>
            </div>
            
            <h2 id="outgoingReceiverName" class="text-2xl font-bold mb-2">Calling...</h2>
            <p id="outgoingCallType" class="text-blue-100 mb-2 flex items-center justify-center">
                <i class="fas fa-phone mr-2"></i>Voice Call
            </p>
            <p class="text-blue-200 text-sm mb-8">Connecting...</p>
            
            <div class="flex justify-center space-x-2 mb-8">
                <div class="w-3 h-3 bg-white rounded-full animate-bounce" style="animation-delay: 0s"></div>
                <div class="w-3 h-3 bg-white rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                <div class="w-3 h-3 bg-white rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
            </div>
            
            <button onclick="CallSystem.endCall('cancelled')" 
                    class="w-20 h-20 bg-red-500 hover:bg-red-600 rounded-full flex items-center justify-center mx-auto shadow-lg transition transform hover:scale-105 active:scale-95">
                <i class="fas fa-phone-slash text-2xl text-white"></i>
            </button>
            <p class="text-white text-xs mt-4 opacity-75">Cancel Call</p>
        </div>
    </div>
</div>

<!-- Incoming Call Modal -->
<div id="incomingCallModal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-[9999] flex items-center justify-center p-4">
    <div class="bg-gradient-to-br from-green-600 to-teal-600 rounded-3xl shadow-2xl max-w-md w-full p-8 text-center text-white relative overflow-hidden">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-white to-transparent animate-pulse"></div>
        </div>
        
        <div class="relative z-10">
            <div id="incomingCallerPhoto" class="w-32 h-32 mx-auto mb-6 rounded-full bg-white bg-opacity-20 flex items-center justify-center overflow-hidden ring-4 ring-white ring-opacity-30 shadow-2xl animate-pulse">
                <i class="fas fa-user text-6xl text-white opacity-80"></i>
            </div>
            
            <h2 id="incomingCallerName" class="text-2xl font-bold mb-2">Incoming Call...</h2>
            <p id="incomingCallType" class="text-green-100 mb-2 flex items-center justify-center">
                <i class="fas fa-phone mr-2"></i>Voice Call
            </p>
            <p class="text-green-100 text-sm mb-8 flex items-center justify-center">
                <i class="fas fa-phone-volume mr-2 animate-ping"></i>
                <span class="animate-pulse">Ringing...</span>
            </p>
            
            <div class="flex justify-center items-center space-x-8 mb-4">
                <div class="text-center">
                    <button onclick="CallSystem.rejectCall()" 
                            class="w-20 h-20 bg-red-500 hover:bg-red-600 rounded-full flex items-center justify-center shadow-lg transition transform hover:scale-110 active:scale-95">
                        <i class="fas fa-phone-slash text-2xl text-white"></i>
                    </button>
                    <p class="text-white text-xs mt-2 opacity-75">Decline</p>
                </div>
                
                <div class="text-center">
                    <button onclick="CallSystem.answerCall()" 
                            class="w-24 h-24 bg-white hover:bg-gray-100 rounded-full flex items-center justify-center shadow-2xl transition transform hover:scale-110 active:scale-95 animate-pulse">
                        <i class="fas fa-phone text-3xl text-green-600"></i>
                    </button>
                    <p class="text-white text-xs mt-2 opacity-75 font-semibold">Answer</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Active Audio Call Modal -->
<div id="activeCallModal" class="hidden fixed inset-0 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 z-[9999] flex items-center justify-center p-4">
    <div class="max-w-md w-full relative">
        <div class="text-center mb-12">
            <div id="activeCallAvatar" class="w-40 h-40 mx-auto mb-6 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center overflow-hidden ring-8 ring-white ring-opacity-10 shadow-2xl">
                <i class="fas fa-user text-7xl text-white"></i>
            </div>
            
            <h2 id="activeCallName" class="text-3xl font-bold text-white mb-2">John Doe</h2>
            <p class="text-green-400 text-lg mb-1 flex items-center justify-center">
                <i class="fas fa-circle text-xs mr-2 animate-pulse"></i>Call in Progress
            </p>
            <p id="callDuration" class="text-gray-300 text-3xl font-mono tracking-wider">00:00</p>
        </div>
        
        <div class="flex justify-center space-x-6 mb-8">
            <div class="text-center">
                <button id="muteBtn" 
                        onclick="CallSystem.toggleMute()" 
                        class="w-16 h-16 bg-gray-700 hover:bg-gray-600 rounded-full flex items-center justify-center transition transform hover:scale-105 active:scale-95 shadow-lg">
                    <i class="fas fa-microphone text-white text-xl"></i>
                </button>
                <p class="text-gray-400 text-xs mt-2">Mute</p>
            </div>
            
            <div class="text-center">
                <button onclick="CallSystem.endCall('ended')" 
                        class="w-20 h-20 bg-red-500 hover:bg-red-600 rounded-full flex items-center justify-center transition transform hover:scale-105 active:scale-95 shadow-2xl">
                    <i class="fas fa-phone-slash text-white text-2xl"></i>
                </button>
                <p class="text-gray-400 text-xs mt-2">End Call</p>
            </div>
            
            <div class="text-center">
                <button id="speakerBtn" 
                        onclick="CallSystem.toggleSpeaker()" 
                        class="w-16 h-16 bg-gray-700 hover:bg-gray-600 rounded-full flex items-center justify-center transition transform hover:scale-105 active:scale-95 shadow-lg">
                    <i class="fas fa-volume-up text-white text-xl"></i>
                </button>
                <p class="text-gray-400 text-xs mt-2">Speaker</p>
            </div>
        </div>
        
        <div class="flex justify-center space-x-4">
            <button onclick="CallSystem.switchToVideo()" 
                    class="w-12 h-12 bg-gray-700 hover:bg-gray-600 rounded-full flex items-center justify-center transition transform hover:scale-105 active:scale-95" 
                    title="Switch to Video">
                <i class="fas fa-video text-white"></i>
            </button>
        </div>
    </div>
</div>

<!-- Active Video Call Modal -->
<div id="activeVideoCallModal" class="hidden fixed inset-0 bg-black z-[9999]">
    <div class="h-full w-full relative">
        <div id="remoteVideoContainer" class="absolute inset-0 bg-gradient-to-br from-gray-900 to-gray-800 flex items-center justify-center">
            <div class="text-center">
                <div id="remoteVideoAvatar" class="w-48 h-48 mx-auto mb-6 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center overflow-hidden shadow-2xl">
                    <i class="fas fa-user text-8xl text-white"></i>
                </div>
                <h2 id="videoCallName" class="text-3xl font-bold text-white mb-2">John Doe</h2>
                <p id="videoCallDuration" class="text-gray-300 text-2xl font-mono">00:00</p>
            </div>
        </div>
        
        <div id="localVideoContainer" class="absolute top-4 right-4 w-32 h-48 bg-gray-800 rounded-2xl overflow-hidden shadow-2xl border-2 border-white border-opacity-20">
            <div class="w-full h-full bg-gradient-to-br from-purple-600 to-blue-500 flex items-center justify-center">
                <i class="fas fa-user text-white text-4xl"></i>
            </div>
        </div>
        
        <div class="absolute top-0 left-0 right-0 p-4 bg-gradient-to-b from-black to-transparent">
            <div class="flex items-center justify-between text-white">
                <div class="flex items-center space-x-3">
                    <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                    <span class="text-sm font-medium">Video Call</span>
                </div>
                <button onclick="CallSystem.toggleFullscreen()" class="p-2 hover:bg-white hover:bg-opacity-20 rounded-lg transition">
                    <i class="fas fa-expand text-white"></i>
                </button>
            </div>
        </div>
        
        <div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-black to-transparent">
            <div class="flex justify-center space-x-4">
                <div class="text-center">
                    <button id="videoToggleBtn" 
                            onclick="CallSystem.toggleVideo()" 
                            class="w-16 h-16 bg-gray-700 bg-opacity-90 hover:bg-gray-600 rounded-full flex items-center justify-center transition transform hover:scale-105 active:scale-95 shadow-lg backdrop-blur-sm">
                        <i class="fas fa-video text-white text-xl"></i>
                    </button>
                    <p class="text-white text-xs mt-2">Camera</p>
                </div>
                
                <div class="text-center">
                    <button id="videoMuteBtn" 
                            onclick="CallSystem.toggleMute()" 
                            class="w-16 h-16 bg-gray-700 bg-opacity-90 hover:bg-gray-600 rounded-full flex items-center justify-center transition transform hover:scale-105 active:scale-95 shadow-lg backdrop-blur-sm">
                        <i class="fas fa-microphone text-white text-xl"></i>
                    </button>
                    <p class="text-white text-xs mt-2">Mute</p>
                </div>
                
                <div class="text-center">
                    <button onclick="CallSystem.endCall('ended')" 
                            class="w-20 h-20 bg-red-500 hover:bg-red-600 rounded-full flex items-center justify-center transition transform hover:scale-105 active:scale-95 shadow-2xl">
                        <i class="fas fa-phone-slash text-white text-2xl"></i>
                    </button>
                    <p class="text-white text-xs mt-2">End Call</p>
                </div>
                
                <div class="text-center">
                    <button onclick="CallSystem.switchCamera()" 
                            class="w-16 h-16 bg-gray-700 bg-opacity-90 hover:bg-gray-600 rounded-full flex items-center justify-center transition transform hover:scale-105 active:scale-95 shadow-lg backdrop-blur-sm">
                        <i class="fas fa-sync-alt text-white text-xl"></i>
                    </button>
                    <p class="text-white text-xs mt-2">Flip</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Call History Modal -->
<div id="callHistoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[9998] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col">
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-6 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-white flex items-center">
                <i class="fas fa-history mr-3"></i>
                Call History
            </h2>
            <button onclick="CallSystem.closeCallHistory()" class="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-4 border-b border-gray-200">
            <div class="flex gap-3">
                <div class="flex-1 relative">
                    <input type="text" 
                           id="callHistorySearch"
                           placeholder="Search call history..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <select id="callTypeFilter" 
                        onchange="CallSystem.filterCallHistory()" 
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="all">All Calls</option>
                    <option value="audio">Audio Only</option>
                    <option value="video">Video Only</option>
                    <option value="missed">Missed</option>
                    <option value="incoming">Incoming</option>
                    <option value="outgoing">Outgoing</option>
                </select>
            </div>
        </div>
        
        <div id="callHistoryList" class="flex-1 overflow-y-auto p-4">
            <div class="text-center py-12">
                <i class="fas fa-phone-slash text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-500">Loading call history...</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Call Modal Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

[id$="CallModal"]:not(.hidden) {
    animation: fadeIn 0.3s ease-out;
}

#callHistoryList::-webkit-scrollbar {
    width: 8px;
}

#callHistoryList::-webkit-scrollbar-track {
    background: #f1f5f9;
}

#callHistoryList::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

button:active {
    transform: scale(0.95) !important;
}

#localVideoContainer {
    transition: all 0.3s ease;
}

#localVideoContainer:hover {
    transform: scale(1.05);
}
</style>

<script src="../public/js/online-status.js"></script>
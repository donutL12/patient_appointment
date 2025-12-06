<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
//user/messages.php
$user_id = $_SESSION['user_id'];
$page_title = "Messages";
$current_page = basename($_SERVER['PHP_SELF']);

// Get all conversations (doctors the user has messaged)
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            d.id as doctor_id,
            d.fullname as doctor_name,
            d.specialization,
            d.profile_photo,
            (
                SELECT message 
                FROM messages 
                WHERE (sender_id = ? AND receiver_id = d.id AND sender_type = 'patient' AND receiver_type = 'doctor')
                   OR (sender_id = d.id AND receiver_id = ? AND sender_type = 'doctor' AND receiver_type = 'patient')
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at 
                FROM messages 
                WHERE (sender_id = ? AND receiver_id = d.id AND sender_type = 'patient' AND receiver_type = 'doctor')
                   OR (sender_id = d.id AND receiver_id = ? AND sender_type = 'doctor' AND receiver_type = 'patient')
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*) 
                FROM messages 
                WHERE sender_id = d.id 
                AND receiver_id = ? 
                AND sender_type = 'doctor' 
                AND receiver_type = 'patient'
                AND is_read = 0
            ) as unread_count
        FROM doctors d
        WHERE EXISTS (
            SELECT 1 FROM messages m
            WHERE (m.sender_id = ? AND m.receiver_id = d.id AND m.sender_type = 'patient' AND m.receiver_type = 'doctor')
               OR (m.sender_id = d.id AND m.receiver_id = ? AND m.sender_type = 'doctor' AND m.receiver_type = 'patient')
        )
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll();
    
    // Get total unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_unread
        FROM messages
        WHERE receiver_id = ?
        AND receiver_type = 'patient'
        AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $total_unread = $stmt->fetch()['total_unread'];
    
} catch (PDOException $e) {
    error_log("Messages Error: " . $e->getMessage());
    $conversations = [];
    $total_unread = 0;
}

// Get all doctors for new message
try {
    $stmt = $pdo->prepare("
        SELECT id, fullname, specialization, profile_photo
        FROM doctors
        ORDER BY fullname ASC
    ");
    $stmt->execute();
    $all_doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Doctors Fetch Error: " . $e->getMessage());
    $all_doctors = [];
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
            <?php if ($total_unread > 0): ?>
                <span class="ml-auto px-2 py-0.5 bg-[#F1824A] text-white text-xs rounded-full font-bold shadow-sm"><?= $total_unread ?></span>
            <?php endif; ?>
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
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-900 mb-2">
                            <i class="fas fa-comments text-transparent bg-clip-text bg-gradient-to-r from-[#4D774E] to-[#164A41] mr-2"></i>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#4D774E] to-[#164A41]">Messages</span>
                        </h1>
                        <p class="text-sm sm:text-base text-gray-600">Stay connected with your healthcare providers</p>
                    </div>
                    <button onclick="openNewMessageModal()" 
                            class="inline-flex items-center justify-center px-4 sm:px-6 py-2.5 sm:py-3 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white rounded-xl hover:shadow-xl transition-all duration-300 font-semibold text-sm sm:text-base shadow-lg hover:-translate-y-1">
                        <i class="fas fa-plus-circle mr-2"></i>
                        <span class="hidden sm:inline">New Message</span>
                        <span class="sm:hidden">New</span>
                    </button>
                </div>
            </div>

            <?php if (count($conversations) > 0): ?>
                <!-- Messages List -->
                <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
                    <!-- Search Bar -->
                    <div class="p-4 sm:p-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-[#9DC88D]/5">
                        <div class="relative">
                            <input type="text" 
                                   id="searchConversations"
                                   placeholder="Search conversations..."
                                   class="w-full pl-11 pr-4 py-3 sm:py-3.5 text-sm sm:text-base border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4D774E] focus:border-transparent transition-all duration-200 bg-white shadow-sm">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        </div>
                    </div>

                    <!-- Conversations List -->
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($conversations as $conversation): ?>
                            <div class="conversation-item p-4 sm:p-5 hover:bg-gradient-to-r hover:from-[#9DC88D]/10 hover:to-transparent transition-all duration-200 cursor-pointer border-l-4 <?= $conversation['unread_count'] > 0 ? 'bg-gradient-to-r from-[#9DC88D]/10 to-transparent border-[#4D774E]' : 'border-transparent hover:border-[#9DC88D]' ?>"
                                 data-search="<?= strtolower($conversation['doctor_name'] . ' ' . $conversation['specialization']) ?>"
                                 onclick="window.location.href='chat.php?doctor_id=<?= $conversation['doctor_id'] ?>'">
                                <div class="flex items-start space-x-3 sm:space-x-4">
                                    <!-- Doctor Avatar -->
                                    <div class="relative flex-shrink-0">
                                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl bg-gradient-to-br from-[#9DC88D] to-[#4D774E] flex items-center justify-center overflow-hidden shadow-md ring-2 ring-white">
                                            <?php if ($conversation['profile_photo'] && $conversation['profile_photo'] !== 'doctor.png' && file_exists("../public/uploads/" . $conversation['profile_photo'])): ?>
                                                <img src="../public/uploads/<?= htmlspecialchars($conversation['profile_photo']) ?>" 
                                                     alt="Doctor" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-user-md text-white text-xl sm:text-2xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-[#9DC88D] rounded-full border-2 border-white hidden shadow-sm" data-status-indicator></span>
                                    </div>

                                    <!-- Message Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between mb-1.5">
                                            <div class="min-w-0 flex-1">
                                                <h3 class="font-bold text-gray-900 text-sm sm:text-base truncate <?= $conversation['unread_count'] > 0 ? 'text-[#164A41]' : '' ?>">
                                                    Dr. <?= htmlspecialchars($conversation['doctor_name']) ?>
                                                </h3>
                                                <p class="text-xs sm:text-sm text-gray-600 truncate">
                                                    <i class="fas fa-stethoscope text-[#4D774E] mr-1 text-[10px]"></i>
                                                    <?= htmlspecialchars($conversation['specialization']) ?>
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                                                <?php if ($conversation['unread_count'] > 0): ?>
                                                    <span class="px-2.5 py-1 bg-gradient-to-r from-[#F1824A] to-[#F1824A]/80 text-white text-xs font-bold rounded-full shadow-sm">
                                                        <?= $conversation['unread_count'] ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="text-xs text-gray-500 whitespace-nowrap font-medium">
                                                    <?php
                                                        $time = strtotime($conversation['last_message_time']);
                                                        $now = time();
                                                        $diff = $now - $time;
                                                        
                                                        if ($diff < 60) {
                                                            echo 'Now';
                                                        } elseif ($diff < 3600) {
                                                            echo floor($diff / 60) . 'm';
                                                        } elseif ($diff < 86400) {
                                                            echo floor($diff / 3600) . 'h';
                                                        } elseif ($diff < 604800) {
                                                            echo floor($diff / 86400) . 'd';
                                                        } else {
                                                            echo date('M d', $time);
                                                        }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="text-xs sm:text-sm text-gray-700 truncate <?= $conversation['unread_count'] > 0 ? 'font-semibold' : '' ?>">
                                            <?= htmlspecialchars($conversation['last_message'] ?? 'No messages yet') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-8 sm:p-16 text-center">
                    <div class="w-24 h-24 sm:w-32 sm:h-32 bg-gradient-to-br from-[#9DC88D] to-[#4D774E] rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <i class="fas fa-comments text-white text-5xl sm:text-6xl"></i>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">No Messages Yet</h3>
                    <p class="text-sm sm:text-base text-gray-600 mb-6 max-w-md mx-auto">Start a conversation with your doctors to get medical advice and support</p>
                    <button onclick="openNewMessageModal()" 
                            class="inline-flex items-center px-6 sm:px-8 py-3 sm:py-4 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white rounded-xl hover:shadow-xl transition-all duration-300 font-semibold text-sm sm:text-base shadow-lg hover:-translate-y-1">
                        <i class="fas fa-plus-circle mr-2 text-lg"></i>Start New Conversation
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div id="newMessageModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        <div class="bg-gradient-to-r from-[#4D774E] to-[#164A41] p-5 sm:p-6 flex-shrink-0">
            <div class="flex items-center justify-between">
                <h2 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-comment-medical mr-3"></i>
                    New Message
                </h2>
                <button onclick="closeNewMessageModal()" class="text-white/80 hover:text-white transition-colors p-2 hover:bg-white/10 rounded-lg">
                    <i class="fas fa-times text-xl sm:text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-5 sm:p-6 overflow-y-auto flex-1 bg-gradient-to-br from-white to-[#9DC88D]/5">
            <p class="text-sm sm:text-base text-gray-600 mb-5 sm:mb-6">Select a doctor to start a conversation</p>
            
            <!-- Search Doctors -->
            <div class="mb-5 sm:mb-6">
                <div class="relative">
                    <input type="text" 
                           id="searchDoctors"
                           placeholder="Search doctors by name or specialization..."
                           class="w-full pl-11 pr-4 py-3 sm:py-3.5 text-sm sm:text-base border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4D774E] focus:border-transparent transition-all duration-200 shadow-sm">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                </div>
            </div>

            <!-- Doctors List -->
            <div id="doctorsList" class="space-y-3">
                <?php foreach ($all_doctors as $doctor): ?>
                    <div class="doctor-item border-2 border-gray-100 rounded-xl p-4 cursor-pointer hover:border-[#4D774E] hover:bg-gradient-to-r hover:from-[#9DC88D]/10 hover:to-transparent hover:shadow-md transition-all duration-300 hover:-translate-y-0.5"
                         data-search="<?= strtolower($doctor['fullname'] . ' ' . $doctor['specialization']) ?>"
                         onclick="startConversation(<?= $doctor['id'] ?>)">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-gradient-to-br from-[#9DC88D] to-[#4D774E] flex items-center justify-center overflow-hidden flex-shrink-0 shadow-md">
                                <?php if ($doctor['profile_photo'] && $doctor['profile_photo'] !== 'doctor.png' && file_exists("../public/uploads/" . $doctor['profile_photo'])): ?>
                                    <img src="../public/uploads/<?= htmlspecialchars($doctor['profile_photo']) ?>" 
                                         alt="Doctor" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user-md text-white text-lg sm:text-xl"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-gray-900 text-sm sm:text-base truncate">Dr. <?= htmlspecialchars($doctor['fullname']) ?></h3>
                                <p class="text-xs sm:text-sm text-gray-600 truncate">
                                    <i class="fas fa-stethoscope text-[#4D774E] mr-1 text-[10px]"></i>
                                    <?= htmlspecialchars($doctor['specialization']) ?>
                                </p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 flex-shrink-0 text-sm"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="../public/js/online-status.js"></script>

<?php include '../includes/footer.php'; ?>

<script>
    // Animate modal on open
    function openNewMessageModal() {
        const modal = document.getElementById('newMessageModal');
        const content = document.getElementById('modalContent');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Trigger animation
        setTimeout(() => {
            content.style.transform = 'scale(1)';
            content.style.opacity = '1';
        }, 10);
    }

    function closeNewMessageModal() {
        const modal = document.getElementById('newMessageModal');
        const content = document.getElementById('modalContent');
        
        content.style.transform = 'scale(0.95)';
        content.style.opacity = '0';
        
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }, 200);
    }

    // Search conversations
    document.getElementById('searchConversations')?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const items = document.querySelectorAll('.conversation-item');
        
        items.forEach(item => {
            const searchData = item.dataset.search;
            if (searchData.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Search doctors in modal
    document.getElementById('searchDoctors')?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const items = document.querySelectorAll('.doctor-item');
        
        items.forEach(item => {
            const searchData = item.dataset.search;
            if (searchData.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    function startConversation(doctorId) {
        window.location.href = 'chat.php?doctor_id=' + doctorId;
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
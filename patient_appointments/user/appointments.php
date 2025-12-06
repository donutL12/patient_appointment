<?php
    session_start();


    // Check for messages
    $success_message = '';
    $error_message = '';

    if (isset($_SESSION['success'])) {
        $success_message = $_SESSION['success'];
        unset($_SESSION['success']); // Clear immediately
    }

    if (isset($_SESSION['error'])) {
        $error_message = $_SESSION['error'];
        unset($_SESSION['error']); // Clear immediately
    }

    require_once __DIR__ . '/../config/db.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $page_title = "My Appointments";
    $current_page = basename($_SERVER['PHP_SELF']);
    // Get all appointments
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.notes,
                a.created_at,
                d.fullname as doctor_name,
                d.specialization,
                d.phone as doctor_phone,
                d.email as doctor_email,
                d.profile_photo as doctor_photo
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.user_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt->execute([$user_id]);
        $appointments = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Appointments Error: " . $e->getMessage());
        $error_message = "Failed to load appointments. Please try again.";
        $appointments = [];
    }

    // Get user details for sidebar
    $user_name = $_SESSION['user_name'] ?? 'Guest';
    $user_email = $_SESSION['user_email'] ?? '';
    $user_photo = $_SESSION['user_photo'] ?? 'default.png';

    include '../includes/header.php';
    ?>

    <!-- Display Messages -->
    <?php if ($success_message): ?>
    <div id="successMessage" style="position:fixed;top:80px;left:50%;transform:translateX(-50%);z-index:9999;background:#4D774E;color:white;padding:16px 24px;border-radius:12px;box-shadow:0 4px 12px rgba(77,119,78,0.3);max-width:90%;animation:slideDown 0.3s ease-out;border:2px solid #9DC88D;">
        <div style="display:flex;align-items:center;gap:12px;">
            <i class="fas fa-check-circle" style="font-size:20px;"></i>
            <span style="font-weight:500;"><?= htmlspecialchars($success_message) ?></span>
            <button onclick="this.parentElement.parentElement.remove()" style="margin-left:12px;background:none;border:none;color:white;cursor:pointer;font-size:20px;">&times;</button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            const msg = document.getElementById('successMessage');
            if (msg) {
                msg.style.animation = 'slideUp 0.3s ease-out';
                setTimeout(() => msg.remove(), 300);
            }
        }, 5000);
    </script>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div id="errorMessage" style="position:fixed;top:80px;left:50%;transform:translateX(-50%);z-index:9999;background:#F1824A;color:white;padding:16px 24px;border-radius:12px;box-shadow:0 4px 12px rgba(241,130,74,0.3);max-width:90%;animation:slideDown 0.3s ease-out;border:2px solid #FFFFFF;">
        <div style="display:flex;align-items:center;gap:12px;">
            <i class="fas fa-exclamation-circle" style="font-size:20px;"></i>
            <span style="font-weight:500;"><?= htmlspecialchars($error_message) ?></span>
            <button onclick="this.parentElement.parentElement.remove()" style="margin-left:12px;background:none;border:none;color:white;cursor:pointer;font-size:20px;">&times;</button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            const msg = document.getElementById('errorMessage');
            if (msg) {
                msg.style.animation = 'slideUp 0.3s ease-out';
                setTimeout(() => msg.remove(), 300);
            }
        }, 5000);
    </script>
    <?php endif; ?>

    <style>
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        to {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
    }
    </style>

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
        <div class="p-3 sm:p-4 lg:p-6 xl:p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-1 sm:mb-2">
                                <i class="fas fa-calendar-alt text-transparent bg-clip-text bg-gradient-to-r from-[#4D774E] to-[#164A41] mr-2"></i>
                                <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#164A41] to-[#4D774E]">My Appointments</span>
                            </h1>
                            <p class="text-sm sm:text-base text-gray-600">Manage and track your healthcare appointments</p>
                        </div>
                        <a href="book_appointment.php" 
                           class="inline-flex items-center justify-center px-4 sm:px-6 py-2 sm:py-3 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white rounded-xl hover:shadow-lg transition-all duration-300 hover:-translate-y-1 font-medium text-sm sm:text-base shadow-md">
                            <i class="fas fa-plus-circle mr-2"></i>
                            <span class="hidden sm:inline">New Appointment</span>
                            <span class="sm:hidden">New</span>
                        </a>
                    </div>
                </div>

                <!-- Filter & Search Section -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-md border border-gray-100 p-3 sm:p-6 mb-4 sm:mb-6">
                    <div class="flex flex-col gap-4">
                        <!-- Filter Buttons -->
                        <div class="flex flex-wrap gap-2">
                            <button onclick="filterAppointments('all')" 
                                    class="filter-btn active px-3 sm:px-4 py-2 rounded-lg font-medium text-xs sm:text-sm transition-all duration-200" data-filter="all">
                                <i class="fas fa-list mr-1 sm:mr-2"></i>
                                <span class="hidden sm:inline">All</span>
                                <span class="ml-1 text-xs bg-white/50 px-2 py-0.5 rounded-full"><?= count($appointments) ?></span>
                            </button>
                            <button onclick="filterAppointments('pending')" 
                                    class="filter-btn px-3 sm:px-4 py-2 rounded-lg font-medium text-xs sm:text-sm transition-all duration-200" data-filter="pending">
                                <i class="fas fa-clock mr-1 sm:mr-2"></i>
                                <span class="hidden sm:inline">Pending</span>
                                <span class="ml-1 text-xs bg-gray-200 px-2 py-0.5 rounded-full">
                                    <?= count(array_filter($appointments, fn($a) => $a['status'] === 'pending')) ?>
                                </span>
                            </button>
                            <button onclick="filterAppointments('approved')" 
                                    class="filter-btn px-3 sm:px-4 py-2 rounded-lg font-medium text-xs sm:text-sm transition-all duration-200" data-filter="approved">
                                <i class="fas fa-check-circle mr-1 sm:mr-2"></i>
                                <span class="hidden sm:inline">Approved</span>
                                <span class="ml-1 text-xs bg-gray-200 px-2 py-0.5 rounded-full">
                                    <?= count(array_filter($appointments, fn($a) => $a['status'] === 'approved')) ?>
                                </span>
                            </button>
                            <button onclick="filterAppointments('completed')" 
                                    class="filter-btn px-3 sm:px-4 py-2 rounded-lg font-medium text-xs sm:text-sm transition-all duration-200" data-filter="completed">
                                <i class="fas fa-check-double mr-1 sm:mr-2"></i>
                                <span class="hidden sm:inline">Completed</span>
                                <span class="ml-1 text-xs bg-gray-200 px-2 py-0.5 rounded-full">
                                    <?= count(array_filter($appointments, fn($a) => $a['status'] === 'completed')) ?>
                                </span>
                            </button>
                            <button onclick="filterAppointments('cancelled')" 
                                    class="filter-btn px-3 sm:px-4 py-2 rounded-lg font-medium text-xs sm:text-sm transition-all duration-200" data-filter="cancelled">
                                <i class="fas fa-times-circle mr-1 sm:mr-2"></i>
                                <span class="hidden sm:inline">Cancelled</span>
                                <span class="ml-1 text-xs bg-gray-200 px-2 py-0.5 rounded-full">
                                    <?= count(array_filter($appointments, fn($a) => $a['status'] === 'cancelled')) ?>
                                </span>
                            </button>
                        </div>
                        
                        <!-- Search Box -->
                        <div class="relative">
                            <input type="text" 
                                   id="searchInput"
                                   placeholder="Search doctor or specialty..."
                                   class="pl-10 pr-4 py-2 sm:py-3 text-sm sm:text-base border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4D774E] focus:border-[#9DC88D] w-full transition-all duration-200">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-[#4D774E] text-sm"></i>
                        </div>
                    </div>
                </div>

                <!-- Appointments List -->
                <?php if (count($appointments) > 0): ?>
                    <div id="appointmentsList" class="space-y-3 sm:space-y-4">
                        <?php foreach ($appointments as $appointment): 
                            $isPast = strtotime($appointment['appointment_date']) < strtotime('today');
                            $isToday = strtotime($appointment['appointment_date']) === strtotime('today');
                        ?>
                            <div class="appointment-card bg-white rounded-lg sm:rounded-xl shadow-md border-2 border-gray-100 p-4 sm:p-6 hover:shadow-xl hover:border-[#9DC88D] transition-all duration-300 hover:-translate-y-1" 
                                 data-status="<?= $appointment['status'] ?>"
                                 data-search="<?= strtolower($appointment['doctor_name'] . ' ' . $appointment['specialization']) ?>">
                                <div class="flex flex-col gap-4">
                                    <!-- Doctor Info -->
                                    <div class="flex items-start space-x-3 sm:space-x-4">
                                        <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-xl bg-gradient-to-br from-[#9DC88D] to-[#4D774E] flex items-center justify-center flex-shrink-0 overflow-hidden shadow-md">
                                            <?php if ($appointment['doctor_photo'] && $appointment['doctor_photo'] !== 'doctor.png' && file_exists("../public/uploads/" . $appointment['doctor_photo'])): ?>
                                                <img src="../public/uploads/<?= htmlspecialchars($appointment['doctor_photo']) ?>" 
                                                     alt="Doctor" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-user-md text-white text-xl sm:text-2xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-bold text-gray-900 text-base sm:text-lg mb-1 truncate">
                                                Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                                            </h3>
                                            <p class="text-xs sm:text-sm text-gray-600 flex items-center truncate">
                                                <i class="fas fa-stethoscope text-[#4D774E] mr-1 sm:mr-2 flex-shrink-0"></i>
                                                <?= htmlspecialchars($appointment['specialization']) ?>
                                            </p>
                                        </div>
                                        <!-- Status Badge - Mobile -->
                                        <span class="sm:hidden px-2 py-1 rounded-full text-xs font-semibold flex-shrink-0 border
                                            <?php 
                                                if ($appointment['status'] === 'approved') echo 'bg-[#9DC88D]/20 text-[#164A41] border-[#9DC88D]';
                                                elseif ($appointment['status'] === 'pending') echo 'bg-[#F1824A]/20 text-[#F1824A] border-[#F1824A]';
                                                elseif ($appointment['status'] === 'cancelled') echo 'bg-red-100 text-red-700 border-red-300';
                                                elseif ($appointment['status'] === 'completed') echo 'bg-[#164A41]/20 text-[#164A41] border-[#164A41]';
                                            ?>">
                                            <i class="fas 
                                                <?php 
                                                    if ($appointment['status'] === 'approved') echo 'fa-check-circle';
                                                    elseif ($appointment['status'] === 'pending') echo 'fa-clock';
                                                    elseif ($appointment['status'] === 'cancelled') echo 'fa-times-circle';
                                                    elseif ($appointment['status'] === 'completed') echo 'fa-check-double';
                                                ?>"></i>
                                        </span>
                                    </div>

                                    <!-- Date & Time Info -->
                                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                        <div class="flex items-center text-xs sm:text-sm">
                                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-[#9DC88D]/20 rounded-lg flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0 border border-[#9DC88D]/30">
                                                <i class="fas fa-calendar text-[#4D774E] text-sm"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs text-gray-500">Date</p>
                                                <p class="font-semibold text-gray-900 truncate">
                                                    <?= date('M d, Y', strtotime($appointment['appointment_date'])) ?>
                                                </p>
                                                <?php if ($isToday): ?>
                                                    <span class="inline-block mt-0.5 px-1.5 py-0.5 bg-[#9DC88D]/20 text-[#164A41] text-xs rounded border border-[#9DC88D]">Today</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center text-xs sm:text-sm">
                                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-[#F1824A]/20 rounded-lg flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0 border border-[#F1824A]/30">
                                                <i class="fas fa-clock text-[#F1824A] text-sm"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs text-gray-500">Time</p>
                                                <p class="font-semibold text-gray-900 truncate">
                                                    <?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status & Actions -->
                                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 sm:items-center">
                                        <!-- Status Badge - Desktop -->
                                        <span class="hidden sm:inline-flex px-4 py-2 rounded-lg text-sm font-semibold border
                                            <?php 
                                                if ($appointment['status'] === 'approved') echo 'bg-[#9DC88D]/20 text-[#164A41] border-[#9DC88D]';
                                                elseif ($appointment['status'] === 'pending') echo 'bg-[#F1824A]/20 text-[#F1824A] border-[#F1824A]';
                                                elseif ($appointment['status'] === 'cancelled') echo 'bg-red-100 text-red-700 border-red-300';
                                                elseif ($appointment['status'] === 'completed') echo 'bg-[#164A41]/20 text-[#164A41] border-[#164A41]';
                                            ?>">
                                            <i class="fas 
                                                <?php 
                                                    if ($appointment['status'] === 'approved') echo 'fa-check-circle';
                                                    elseif ($appointment['status'] === 'pending') echo 'fa-clock';
                                                    elseif ($appointment['status'] === 'cancelled') echo 'fa-times-circle';
                                                    elseif ($appointment['status'] === 'completed') echo 'fa-check-double';
                                                ?> mr-1"></i>
                                            <?= ucfirst($appointment['status']) ?>
                                        </span>
                                        
                                        <div class="flex gap-2 sm:ml-auto">
                                            <a href="appointment_view.php?id=<?= $appointment['id'] ?>" 
                                               class="flex-1 sm:flex-none bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white px-3 sm:px-4 py-2 rounded-lg hover:shadow-lg transition-all duration-300 text-xs sm:text-sm font-medium text-center">
                                                <i class="fas fa-eye mr-1"></i>
                                                <span class="hidden sm:inline">View Details</span>
                                                <span class="sm:hidden">View</span>
                                            </a>
                                            <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'approved'): ?>
                                                <button onclick="cancelAppointment(<?= $appointment['id'] ?>)" 
                                                        class="flex-1 sm:flex-none bg-white text-red-600 border-2 border-red-600 px-3 sm:px-4 py-2 rounded-lg hover:bg-red-50 hover:shadow-md transition-all duration-300 text-xs sm:text-sm font-medium">
                                                    <i class="fas fa-times mr-1"></i>
                                                    <span class="hidden sm:inline">Cancel</span>
                                                    <span class="sm:hidden">Cancel</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Notes -->
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="p-3 sm:p-4 bg-gradient-to-r from-[#9DC88D]/10 to-[#4D774E]/10 border-2 border-[#9DC88D]/30 rounded-lg">
                                            <p class="text-xs sm:text-sm text-gray-700">
                                                <i class="fas fa-sticky-note text-[#4D774E] mr-2"></i>
                                                <strong>Notes:</strong> <?= htmlspecialchars($appointment['notes']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Empty State (hidden by default, shown when no results) -->
                    <div id="emptyState" class="hidden bg-white rounded-lg sm:rounded-xl shadow-md border-2 border-gray-100 p-8 sm:p-12 text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-[#9DC88D] to-[#4D774E] rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4 shadow-md">
                            <i class="fas fa-search text-white text-2xl sm:text-3xl"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">No appointments found</h3>
                        <p class="text-sm sm:text-base text-gray-600">Try adjusting your filters or search terms</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg sm:rounded-xl shadow-md border-2 border-gray-100 p-8 sm:p-12 text-center">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-[#4D774E] to-[#164A41] rounded-full flex items-center justify-center mx-auto mb-4 sm:mb-6 shadow-lg">
                            <i class="fas fa-calendar-plus text-white text-3xl sm:text-4xl"></i>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">No Appointments Yet</h3>
                        <p class="text-sm sm:text-base text-gray-600 mb-4 sm:mb-6">Start your healthcare journey by booking your first appointment</p>
                        <a href="book_appointment.php" 
                           class="inline-flex items-center px-4 sm:px-6 py-2 sm:py-3 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white rounded-xl hover:shadow-lg transition-all duration-300 hover:-translate-y-1 font-medium text-sm sm:text-base shadow-md">
                            <i class="fas fa-plus-circle mr-2"></i>Book Your First Appointment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <style>
        .filter-btn {
            transition: all 0.3s ease;
            position: relative;
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .filter-btn:hover {
            background: #e5e7eb;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #4D774E 0%, #164A41 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(77, 119, 78, 0.3);
            transform: translateY(-1px);
        }
        
        .filter-btn.active .ml-1 {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .appointment-card {
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
        // Filter appointments
        function filterAppointments(status) {
            const cards = document.querySelectorAll('.appointment-card');
            const buttons = document.querySelectorAll('.filter-btn');
            const emptyState = document.getElementById('emptyState');
            const appointmentsList = document.getElementById('appointmentsList');
            let visibleCount = 0;
            
            // Update button states
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });
            
            const activeBtn = document.querySelector(`[data-filter="${status}"]`);
            activeBtn.classList.add('active');
            
            // Filter cards
            cards.forEach(card => {
                const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                const cardSearch = card.dataset.search;
                const matchesSearch = cardSearch.includes(searchTerm);
                const matchesStatus = status === 'all' || card.dataset.status === status;
                
                if (matchesSearch && matchesStatus) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide empty state
            if (visibleCount === 0) {
                if (appointmentsList) appointmentsList.style.display = 'none';
                emptyState.classList.remove('hidden');
            } else {
                if (appointmentsList) appointmentsList.style.display = 'block';
                emptyState.classList.add('hidden');
            }
        }

        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function() {
            const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
            filterAppointments(activeFilter);
        });

        // Cancel appointment
        function cancelAppointment(appointmentId) {
            if (confirm('Are you sure you want to cancel this appointment?')) {
                window.location.href = `../routes/routes.php?action=cancel_appointment&id=${appointmentId}`;
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const activeBtn = document.querySelector('.filter-btn.active');
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        });
    </script>
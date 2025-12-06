<?php
session_start();
if (isset($_SESSION['error'])) {
    echo "<div style='position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;background:red;color:white;padding:20px;border-radius:10px;'>";
    echo "ERROR: " . htmlspecialchars($_SESSION['error']);
    echo "</div>";
    unset($_SESSION['error']);
}
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = "Book Appointment";
$current_page = basename($_SERVER['PHP_SELF']);

// Get all doctors
$doctors = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            fullname,
            specialization,
            phone,
            email,
            profile_photo
        FROM doctors 
        ORDER BY fullname ASC
    ");
    $stmt->execute();
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Doctors Fetch Error: " . $e->getMessage());
    $doctors = [];
}

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
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-[#9DC88D]/10">
            <i class="fas fa-calendar-alt w-5 text-base"></i>
            <span class="ml-3 font-semibold text-sm">My Appointments</span>
        </a>
        
        <a href="book_appointment.php" 
           class="group flex items-center px-4 py-3 rounded-xl transition-all duration-200 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white shadow-lg shadow-[#164A41]/30">
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
            <div class="mb-4 sm:mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-1 sm:mb-2">
                            <i class="fas fa-calendar-plus text-[#F1824A] mr-2"></i>
                            Book New Appointment
                        </h1>
                        <p class="text-sm sm:text-base text-gray-600">Schedule your healthcare visit in 3 easy steps</p>
                    </div>
                    <a href="appointments.php" 
                       class="inline-flex items-center justify-center px-4 py-2 bg-white border-2 border-[#9DC88D] text-[#164A41] rounded-xl hover:bg-[#9DC88D]/10 hover:border-[#4D774E] transition-all duration-300 font-semibold text-sm shadow-sm hover:shadow-md">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back
                    </a>
                </div>
            </div>

            <!-- Progress Steps -->
            <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center flex-1">
                        <div id="step-indicator-1" class="step-indicator bg-gradient-to-br from-[#4D774E] to-[#164A41] text-white flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-xl font-bold text-sm shadow-lg">
                            1
                        </div>
                        <div class="flex-1 h-2 bg-gray-200 mx-2 sm:mx-4 rounded-full"></div>
                    </div>
                    <div class="flex items-center flex-1">
                        <div id="step-indicator-2" class="step-indicator flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-gray-200 font-bold text-sm text-gray-600">
                            2
                        </div>
                        <div class="flex-1 h-2 bg-gray-200 mx-2 sm:mx-4 rounded-full"></div>
                    </div>
                    <div class="flex items-center">
                        <div id="step-indicator-3" class="step-indicator flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-gray-200 font-bold text-sm text-gray-600">
                            3
                        </div>
                    </div>
                </div>
                <div class="flex justify-between mt-3 sm:mt-4">
                    <span class="text-xs sm:text-sm font-bold text-[#4D774E]">Select Doctor</span>
                    <span class="text-xs sm:text-sm text-gray-500 font-semibold">Date & Time</span>
                    <span class="text-xs sm:text-sm text-gray-500 font-semibold">Confirm</span>
                </div>
            </div>

            <!-- Booking Form -->
            <form id="bookingForm" action="../routes/routes.php" method="POST" class="space-y-4 sm:space-y-6">  
                <input type="hidden" name="action" value="book_appointment">
                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                <input type="hidden" id="selected_doctor" name="doctor_id" required>

                <!-- Step 1: Select Doctor -->
                <div id="step1" class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-4 sm:mb-6 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-[#4D774E] to-[#164A41] rounded-xl flex items-center justify-center mr-3 shadow-md">
                            <i class="fas fa-user-md text-white"></i>
                        </div>
                        Step 1: Select Your Doctor
                    </h2>

                    <!-- Search & Filter -->
                    <div class="mb-4 sm:mb-6">
                        <div class="relative">
                            <input type="text" 
                                   id="doctorSearch"
                                   placeholder="Search by name or specialization..."
                                   class="w-full pl-12 pr-4 py-3 sm:py-4 text-sm sm:text-base border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4D774E] focus:border-[#4D774E] transition-all duration-200">
                            <div class="absolute left-4 top-1/2 transform -translate-y-1/2 w-8 h-8 bg-gradient-to-br from-[#9DC88D] to-[#4D774E] rounded-lg flex items-center justify-center">
                                <i class="fas fa-search text-white text-sm"></i>
                            </div>
                        </div>
                    </div>

                    <?php if (count($doctors) > 0): ?>
                        <div id="doctorsList" class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
                            <?php foreach ($doctors as $doctor): ?>
                                <div class="doctor-card border-2 border-gray-200 rounded-xl p-4 sm:p-5 hover:shadow-xl hover:border-[#9DC88D] transition-all duration-300 cursor-pointer transform hover:-translate-y-1"
                                     data-doctor-id="<?= $doctor['id'] ?>"
                                     data-search="<?= strtolower($doctor['fullname'] . ' ' . $doctor['specialization']) ?>"
                                     onclick="selectDoctor(<?= $doctor['id'] ?>, '<?= htmlspecialchars($doctor['fullname']) ?>', '<?= htmlspecialchars($doctor['specialization']) ?>')">
                                    <div class="flex items-start space-x-3 sm:space-x-4">
                                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-gradient-to-br from-[#9DC88D] to-[#4D774E] flex items-center justify-center flex-shrink-0 overflow-hidden shadow-md">
                                            <?php if ($doctor['profile_photo'] && $doctor['profile_photo'] !== 'doctor.png' && file_exists("../public/uploads/" . $doctor['profile_photo'])): ?>
                                                <img src="../public/uploads/<?= htmlspecialchars($doctor['profile_photo']) ?>" 
                                                     alt="Doctor" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-user-md text-white text-2xl sm:text-3xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-bold text-gray-900 mb-1 text-sm sm:text-base truncate">
                                                Dr. <?= htmlspecialchars($doctor['fullname']) ?>
                                            </h3>
                                            <p class="text-xs sm:text-sm text-gray-600 mb-2 truncate flex items-center">
                                                <span class="w-2 h-2 bg-[#4D774E] rounded-full mr-2"></span>
                                                <?= htmlspecialchars($doctor['specialization']) ?>
                                            </p>
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                                <span class="flex items-center bg-[#9DC88D]/10 px-2 py-1 rounded-lg">
                                                    <i class="fas fa-envelope text-[#4D774E] mr-1"></i>
                                                    Available
                                                </span>
                                            </div>
                                        </div>
                                        <div class="check-circle w-6 h-6 sm:w-7 sm:h-7 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0 transition-all duration-200">
                                            <i class="fas fa-check text-white text-xs hidden"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 sm:py-16">
                            <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-[#9DC88D]/20 to-[#4D774E]/20 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-inner">
                                <i class="fas fa-user-md text-gray-400 text-3xl sm:text-4xl"></i>
                            </div>
                            <p class="text-sm sm:text-base text-gray-600 font-semibold">No doctors available at the moment</p>
                            <p class="text-xs sm:text-sm text-gray-500 mt-2">Please check back later</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Step 2: Date & Time -->
                <div id="step2" class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6 hidden">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-4 sm:mb-6 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-[#F1824A] to-[#164A41] rounded-xl flex items-center justify-center mr-3 shadow-md">
                            <i class="fas fa-calendar-alt text-white"></i>
                        </div>
                        Step 2: Choose Date & Time
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-calendar text-[#4D774E] mr-2"></i>
                                Appointment Date
                            </label>
                            <div class="relative">
                                <input type="date" 
                                       name="appointment_date" 
                                       id="appointment_date"
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                       class="w-full px-4 py-3 sm:py-4 text-sm sm:text-base border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4D774E] focus:border-[#4D774E] transition-all duration-200"
                                       required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-clock text-[#4D774E] mr-2"></i>
                                Appointment Time
                            </label>
                            <select name="appointment_time" 
                                    id="appointment_time"
                                    class="w-full px-4 py-3 sm:py-4 text-sm sm:text-base border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4D774E] focus:border-[#4D774E] transition-all duration-200"
                                    required>
                                <option value="">Select time</option>
                                <option value="08:00:00">🌅 08:00 AM</option>
                                <option value="08:30:00">🌅 08:30 AM</option>
                                <option value="09:00:00">🌅 09:00 AM</option>
                                <option value="09:30:00">🌅 09:30 AM</option>
                                <option value="10:00:00">☀️ 10:00 AM</option>
                                <option value="10:30:00">☀️ 10:30 AM</option>
                                <option value="11:00:00">☀️ 11:00 AM</option>
                                <option value="11:30:00">☀️ 11:30 AM</option>
                                <option value="13:00:00">🌤️ 01:00 PM</option>
                                <option value="13:30:00">🌤️ 01:30 PM</option>
                                <option value="14:00:00">🌤️ 02:00 PM</option>
                                <option value="14:30:00">🌤️ 02:30 PM</option>
                                <option value="15:00:00">🌆 03:00 PM</option>
                                <option value="15:30:00">🌆 03:30 PM</option>
                                <option value="16:00:00">🌆 04:00 PM</option>
                                <option value="16:30:00">🌆 04:30 PM</option>
                            </select>
                        </div>
                    </div>

                    <!-- Time Slot Info -->
                    <div class="mt-6 p-4 bg-gradient-to-r from-[#9DC88D]/10 to-[#4D774E]/10 rounded-xl border-2 border-[#9DC88D]/30">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-[#4D774E] mt-1"></i>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 mb-1">Available Time Slots</p>
                                <p class="text-xs text-gray-600">Morning: 8:00 AM - 12:00 PM | Afternoon: 1:00 PM - 5:00 PM</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Additional Details -->
                <div id="step3" class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6 hidden">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-4 sm:mb-6 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-[#9DC88D] to-[#4D774E] rounded-xl flex items-center justify-center mr-3 shadow-md">
                            <i class="fas fa-info-circle text-white"></i>
                        </div>
                        Step 3: Additional Information
                    </h2>

                    <div class="space-y-4 sm:space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-notes-medical text-[#4D774E] mr-2"></i>
                                Reason for Visit / Notes (Optional)
                            </label>
                            <textarea name="notes" 
                                      rows="4"
                                      placeholder="Describe your symptoms or reason for consultation..."
                                      class="w-full px-4 py-3 text-sm sm:text-base border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-[#4D774E] focus:border-[#4D774E] transition-all duration-200"></textarea>
                        </div>

                        <!-- Booking Summary -->
                        <div class="bg-gradient-to-br from-[#4D774E] to-[#164A41] rounded-xl p-5 sm:p-6 text-white shadow-xl">
                            <h3 class="font-bold text-lg sm:text-xl mb-4 flex items-center">
                                <i class="fas fa-clipboard-check mr-2"></i>
                                Appointment Summary
                            </h3>
                            <div class="space-y-3 sm:space-y-4">
                                <div class="flex items-start justify-between p-3 bg-white/10 rounded-lg backdrop-blur-sm">
                                    <span class="text-[#9DC88D] font-semibold text-sm">Doctor:</span>
                                    <span id="summary_doctor" class="font-bold text-right flex-1 ml-3">-</span>
                                </div>
                                <div class="flex items-start justify-between p-3 bg-white/10 rounded-lg backdrop-blur-sm">
                                    <span class="text-[#9DC88D] font-semibold text-sm">Specialization:</span>
                                    <span id="summary_specialization" class="font-bold text-right flex-1 ml-3 truncate">-</span>
                                </div>
                                <div class="flex items-start justify-between p-3 bg-white/10 rounded-lg backdrop-blur-sm">
                                    <span class="text-[#9DC88D] font-semibold text-sm">Date:</span>
                                    <span id="summary_date" class="font-bold text-right flex-1 ml-3">-</span>
                                </div>
                                <div class="flex items-start justify-between p-3 bg-white/10 rounded-lg backdrop-blur-sm">
                                    <span class="text-[#9DC88D] font-semibold text-sm">Time:</span>
                                    <span id="summary_time" class="font-bold text-right flex-1 ml-3">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                    <button type="button" 
                            id="prevBtn" 
                            onclick="changeStep(-1)"
                            class="px-6 py-3 sm:py-4 bg-white border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-[#9DC88D] transition-all duration-300 font-bold text-sm sm:text-base shadow-sm hover:shadow-md hidden order-2 sm:order-1">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Previous
                    </button>
                    <div class="hidden sm:block flex-1"></div>
                    <button type="button" 
                            id="nextBtn" 
                            onclick="changeStep(1)"
                            class="px-6 py-3 sm:py-4 bg-gradient-to-r from-[#4D774E] to-[#164A41] text-white rounded-xl hover:shadow-xl transition-all duration-300 font-bold text-sm sm:text-base disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2 transform hover:-translate-y-0.5"
                            disabled>
                        Next
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                    <button type="submit" 
                            id="submitBtn"
                            class="px-6 py-3 sm:py-4 bg-gradient-to-r from-[#9DC88D] to-[#4D774E] text-white rounded-xl hover:shadow-xl transition-all duration-300 font-bold text-sm sm:text-base hidden order-1 sm:order-2 transform hover:-translate-y-0.5">
                        <i class="fas fa-check-circle mr-2"></i>
                        Confirm Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
    .doctor-card {
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
    }
    
    .doctor-card.selected {
        border-color: #4D774E;
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        box-shadow: 0 10px 25px -5px rgba(77, 119, 78, 0.2);
    }
    
    .doctor-card.selected .check-circle {
        background: linear-gradient(135deg, #9DC88D 0%, #4D774E 100%);
        border-color: #4D774E;
    }
    
    .doctor-card.selected .check-circle i {
        display: block !important;
    }
    
    .doctor-card:hover {
        border-color: #9DC88D;
    }
    
    .step-indicator {
        transition: all 0.3s ease;
    }
    
    .step-indicator.active {
        background: linear-gradient(135deg, #4D774E 0%, #164A41 100%);
        color: white;
        box-shadow: 0 5px 15px rgba(77, 119, 78, 0.3);
    }
    
    .step-indicator.completed {
        background: linear-gradient(135deg, #9DC88D 0%, #4D774E 100%);
        color: white;
        box-shadow: 0 5px 15px rgba(157, 200, 141, 0.3);
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

    input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
        filter: invert(48%) sepia(13%) saturate(1000%) hue-rotate(80deg);
    }

    select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234D774E' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }
</style>

<script>
    let currentStep = 1;
    let selectedDoctorData = {};

    console.log('=== BOOK APPOINTMENT PAGE LOADED ===');

    // Search doctors
    document.getElementById('doctorSearch')?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const cards = document.querySelectorAll('.doctor-card');
        
        cards.forEach(card => {
            const searchData = card.dataset.search;
            if (searchData.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Select doctor
    function selectDoctor(id, name, specialization) {
        console.log('=== DOCTOR SELECTED ===');
        console.log('Doctor ID:', id);
        console.log('Doctor Name:', name);
        console.log('Specialization:', specialization);
        
        // Remove previous selection
        document.querySelectorAll('.doctor-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selection
        const selectedCard = document.querySelector(`[data-doctor-id="${id}"]`);
        selectedCard.classList.add('selected');
        
        // Store data
        document.getElementById('selected_doctor').value = id;
        selectedDoctorData = { name, specialization };
        
        console.log('Hidden input value set to:', document.getElementById('selected_doctor').value);
        
        // Update summary
        document.getElementById('summary_doctor').textContent = 'Dr. ' + name;
        document.getElementById('summary_specialization').textContent = specialization;
        
        // Enable next button
        document.getElementById('nextBtn').disabled = false;
        console.log('Next button enabled');
    }

    // Change step
    function changeStep(direction) {
        console.log('=== CHANGE STEP ===');
        console.log('Current step:', currentStep);
        console.log('Direction:', direction);
        
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        
        // Validate current step BEFORE moving forward
        if (direction === 1) {
            // Validate Step 1: Doctor selection
            if (currentStep === 1) {
                const doctorId = document.getElementById('selected_doctor').value;
                console.log('Step 1 validation - Doctor ID:', doctorId);
                
                if (!doctorId) {
                    alert('Please select a doctor');
                    return;
                }
                console.log('Step 1 validated, moving to Step 2');
            }
            
            // Validate Step 2: Date and time selection
            if (currentStep === 2) {
                const date = document.getElementById('appointment_date').value;
                const time = document.getElementById('appointment_time').value;
                
                console.log('Step 2 validation - Date:', date, 'Time:', time);
                
                if (!date || !time) {
                    alert('Please select both date and time');
                    return;
                }
                
                // Update summary
                document.getElementById('summary_date').textContent = new Date(date).toLocaleDateString('en-US', { 
                    year: 'numeric', month: 'long', day: 'numeric' 
                });
                document.getElementById('summary_time').textContent = formatTime(time);
                
                console.log('Step 2 validated, moving to Step 3');
            }
        }
        
        // Hide all steps first
        step1.classList.add('hidden');
        step2.classList.add('hidden');
        step3.classList.add('hidden');
        
        // Update step
        currentStep += direction;
        console.log('New current step:', currentStep);
        
        // Show the correct step
        if (currentStep === 1) {
            step1.classList.remove('hidden');
        } else if (currentStep === 2) {
            step2.classList.remove('hidden');
        } else if (currentStep === 3) {
            step3.classList.remove('hidden');
        }
        
        // Update indicators
        for (let i = 1; i <= 3; i++) {
            const indicator = document.getElementById(`step-indicator-${i}`);
            if (i < currentStep) {
                indicator.classList.add('completed');
                indicator.classList.remove('active');
            } else if (i === currentStep) {
                indicator.classList.add('active');
                indicator.classList.remove('completed');
            } else {
                indicator.classList.remove('active', 'completed');
            }
        }
        
        // Update buttons
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        
        if (currentStep === 1) {
            prevBtn.classList.add('hidden');
            nextBtn.classList.remove('hidden');
            submitBtn.classList.add('hidden');
        } else if (currentStep === 3) {
            prevBtn.classList.remove('hidden');
            nextBtn.classList.add('hidden');
            submitBtn.classList.remove('hidden');
        } else {
            prevBtn.classList.remove('hidden');
            nextBtn.classList.remove('hidden');
            submitBtn.classList.add('hidden');
        }
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Format time
    function formatTime(time) {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const period = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
        return `${displayHour}:${minutes} ${period}`;
    }

    // Form submission
    document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        console.log('=== FORM SUBMISSION TRIGGERED ===');
        
        const formData = new FormData(this);
        
        console.log('Form action:', this.action);
        console.log('Form method:', this.method);
        console.log('All form data:');
        for (let [key, value] of formData.entries()) {
            console.log(`  ${key}: ${value}`);
        }
        
        const action = formData.get('action');
        const userId = formData.get('user_id');
        const doctorId = formData.get('doctor_id');
        const date = formData.get('appointment_date');
        const time = formData.get('appointment_time');
        
        console.log('Field validation:');
        console.log('  action:', action, action ? '✓' : '✗ MISSING');
        console.log('  user_id:', userId, userId ? '✓' : '✗ MISSING');
        console.log('  doctor_id:', doctorId, doctorId ? '✓' : '✗ MISSING');
        console.log('  appointment_date:', date, date ? '✓' : '✗ MISSING');
        console.log('  appointment_time:', time, time ? '✓' : '✗ MISSING');
        
        if (!action || !userId || !doctorId || !date || !time) {
            alert('ERROR: Missing required fields. Check console for details.');
            console.error('Form submission blocked - missing fields');
            return false;
        }
        
        console.log('All validations passed!');
        console.log('Submitting form to:', this.action);
        
        this.submit();
    });

    console.log('=== Script initialization complete ===');
</script>
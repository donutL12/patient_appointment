<?php
// includes/navbar.php
// Navigation bar for patient pages
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Guest';
?>

<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo/Brand -->
            <div class="flex items-center">
                <a href="dashboard.php" class="flex items-center">
                    <i class="fas fa-hospital text-primary-blue text-2xl mr-3"></i>
                    <span class="text-xl font-bold text-gray-800">Healthcare System</span>
                </a>
            </div>

            <!-- Right Side Menu -->
            <div class="flex items-center space-x-4">
                <!-- Notifications (Optional) -->
                <button class="relative text-gray-600 hover:text-gray-800 transition">
                    <i class="fas fa-bell text-xl"></i>
                    <!-- Notification Badge -->
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        3
                    </span>
                </button>

                <!-- User Dropdown -->
                <div class="flex items-center space-x-3">
                    <span class="text-gray-700 hidden sm:block">
                        <i class="fas fa-user-circle mr-2"></i>
                        <?= htmlspecialchars($user_name) ?>
                    </span>
                    <a href="../routes/routes.php?action=logout" 
                       class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-sm">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <span class="hidden sm:inline">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
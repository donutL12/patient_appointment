<?php
/**
 * Admin Settings Page
 * Manage system-wide settings and configurations
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'System Settings';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Fetch current settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Default values if not set
    $system_name = $settings['system_name'] ?? 'Healthcare System';
    $system_logo = $settings['system_logo'] ?? '';
    $contact_address = $settings['contact_address'] ?? '';
    $contact_email = $settings['contact_email'] ?? '';
    $contact_phone = $settings['contact_phone'] ?? '';
    $default_slots = $settings['default_slots'] ?? 20;
    
} catch (PDOException $e) {
    $error_message = "Error fetching settings: " . $e->getMessage();
    $system_name = 'Healthcare System';
    $system_logo = '';
    $contact_email = '';
    $contact_address = '';
    $contact_phone = '';
    $default_slots = 20;
}

// Fetch pending appointments count for sidebar badge
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'");
    $pending_appointments = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $pending_appointments = 0;
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
        * { font-family: 'Inter', sans-serif; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#667eea',
                        'primary-dark': '#5568d3',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    
    <!-- Top Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-50">
        <div class="px-4 lg:px-6">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <button onclick="toggleSidebar()" class="md:hidden p-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-primary flex items-center justify-center">
                            <i class="fas fa-heartbeat text-white text-xl"></i>
                        </div>
                        <span class="text-xl font-bold text-gray-900"><?= htmlspecialchars($system_name) ?></span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button class="relative p-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if ($pending_appointments > 0): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="flex items-center space-x-3 pl-4 border-l border-gray-200">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($admin_name) ?></p>
                            <p class="text-xs text-gray-500"><?= ucfirst($admin_role) ?></p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                            <?= strtoupper(substr($admin_name, 0, 1)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-white border-r border-gray-200 overflow-y-auto z-40 sidebar-transition">
        <nav class="p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-chart-line w-5"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="manage_users.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-users w-5"></i>
                <span class="font-medium">Patients</span>
            </a>
            <a href="manage_doctors.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-user-md w-5"></i>
                <span class="font-medium">Doctors</span>
            </a>
            <a href="manage_schedules.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-calendar-alt w-5"></i>
                <span class="font-medium">Schedules</span>
            </a>
            <a href="manage_appointments.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-calendar-check w-5"></i>
                <span class="font-medium">Appointments</span>
                <?php if ($pending_appointments > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?= $pending_appointments ?></span>
                <?php endif; ?>
            </a>
            <a href="activity_logs.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-history w-5"></i>
                <span class="font-medium">Activity Logs</span>
            </a>
            <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 bg-primary text-white rounded-lg">
                <i class="fas fa-cog w-5"></i>
                <span class="font-medium">Settings</span>
            </a>
            <a href="../routes/routes.php?action=logout" class="flex items-center space-x-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition">
                <i class="fas fa-sign-out-alt w-5"></i>
                <span class="font-medium">Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="md:ml-64 pt-16 min-h-screen">
        <div class="p-6 max-w-7xl mx-auto">
            
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">System Settings</h1>
                <p class="text-gray-600">Configure system-wide settings - changes will appear in header and footer immediately</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Settings Form -->
            <form action="../routes/admin_routes.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="action" value="update_settings">

                <!-- General Settings -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center space-x-2">
                            <i class="fas fa-building text-primary"></i>
                            <span>General Settings</span>
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Basic system information and branding (appears in header)</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <!-- System Logo -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                System Logo
                            </label>
                            <div class="flex items-start space-x-4">
                                <!-- Logo Preview -->
                                <div class="flex-shrink-0">
                                    <div id="logoPreview" class="w-24 h-24 rounded-lg border-2 border-gray-300 overflow-hidden bg-gray-50 flex items-center justify-center">
                                        <?php if (!empty($system_logo) && file_exists("../public/images/" . $system_logo)): ?>
                                            <img src="../public/images/<?= htmlspecialchars($system_logo) ?>?v=<?= time() ?>" 
                                                 alt="Current Logo" 
                                                 class="w-full h-full object-cover"
                                                 id="currentLogo">
                                        <?php else: ?>
                                            <div class="text-center p-4">
                                                <i class="fas fa-image text-gray-400 text-3xl mb-2"></i>
                                                <p class="text-xs text-gray-500">No logo</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Upload Controls -->
                                <div class="flex-1">
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-primary transition">
                                        <input 
                                            type="file" 
                                            id="logo_upload" 
                                            name="system_logo" 
                                            accept="image/*"
                                            class="hidden"
                                            onchange="previewLogo(this)">
                                        <label for="logo_upload" class="cursor-pointer">
                                            <div class="text-center">
                                                <i class="fas fa-cloud-upload-alt text-primary text-3xl mb-2"></i>
                                                <p class="text-sm font-semibold text-gray-700 mb-1">Click to upload logo</p>
                                                <p class="text-xs text-gray-500">PNG, JPG, or GIF (max 2MB)</p>
                                                <p class="text-xs text-gray-500 mt-1">Recommended: 200x200px</p>
                                            </div>
                                        </label>
                                    </div>
                                    <?php if (!empty($system_logo)): ?>
                                    <div class="mt-2">
                                        <label class="flex items-center space-x-2 text-sm text-gray-600">
                                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-primary focus:ring-primary">
                                            <span>Remove current logo</span>
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Logo appears in the header next to the system name
                            </p>
                        </div>

                        <!-- System Name -->
                        <div>
                            <label for="system_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                System Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="system_name" 
                                name="system_name" 
                                value="<?= htmlspecialchars($system_name) ?>"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
                                placeholder="e.g., City General Hospital">
                            <p class="text-xs text-gray-500 mt-1">This name appears in the header, footer, and page titles</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center space-x-2">
                            <i class="fas fa-address-book text-primary"></i>
                            <span>Contact Information</span>
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Contact details displayed in footer</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="contact_email" class="block text-sm font-semibold text-gray-700 mb-2">
                                Contact Email
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input 
                                    type="email" 
                                    id="contact_email" 
                                    name="contact_email" 
                                    value="<?= htmlspecialchars($contact_email) ?>"
                                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
                                    placeholder="info@hospital.com">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Primary email shown in footer</p>
                        </div>

                        <div>
                            <label for="contact_phone" class="block text-sm font-semibold text-gray-700 mb-2">
                                Contact Phone
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-phone text-gray-400"></i>
                                </div>
                                <input 
                                    type="tel" 
                                    id="contact_phone" 
                                    name="contact_phone" 
                                    value="<?= htmlspecialchars($contact_phone) ?>"
                                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
                                    placeholder="+1 (555) 123-4567">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Main contact number shown in footer</p>
                        </div>

                        <div>
                            <label for="contact_address" class="block text-sm font-semibold text-gray-700 mb-2">
                                Contact Address
                            </label>
                            <div class="relative">
                                <div class="absolute top-3 left-0 pl-3 pointer-events-none">
                                    <i class="fas fa-map-marker-alt text-gray-400"></i>
                                </div>
                                <textarea 
                                    id="contact_address" 
                                    name="contact_address" 
                                    rows="3"
                                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
                                    placeholder="123 Medical Center Drive&#10;City, State 12345"><?= htmlspecialchars($contact_address) ?></textarea>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Physical address shown in footer (use line breaks for multiple lines)</p>
                        </div>
                    </div>
                </div>

                <!-- Appointment Settings -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center space-x-2">
                            <i class="fas fa-calendar-check text-primary"></i>
                            <span>Appointment Settings</span>
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Configure default appointment parameters</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="default_slots" class="block text-sm font-semibold text-gray-700 mb-2">
                                Default Appointment Slots
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-users text-gray-400"></i>
                                </div>
                                <input 
                                    type="number" 
                                    id="default_slots" 
                                    name="default_slots" 
                                    value="<?= htmlspecialchars($default_slots) ?>"
                                    min="1"
                                    max="100"
                                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
                                    placeholder="20">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Default number of appointment slots per schedule (1-100)</p>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                                <div class="text-sm text-blue-800">
                                    <p class="font-semibold mb-1">About Appointment Slots</p>
                                    <p>This setting determines the default number of patients that can be scheduled during a doctor's availability window. Doctors can override this when creating their schedules.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end space-x-3 bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <button 
                        type="button" 
                        onclick="window.location.href='dashboard.php'"
                        class="px-6 py-2.5 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button 
                        type="submit"
                        class="px-6 py-2.5 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition shadow-sm">
                        <i class="fas fa-save mr-2"></i>Save Settings
                    </button>
                </div>
            </form>

        </div>
    </main>

    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
        }

        // Preview Logo
        function previewLogo(input) {
            const preview = document.getElementById('logoPreview');
            
            if (input.files && input.files[0]) {
                // Validate file size (2MB)
                if (input.files[0].size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    input.value = '';
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(input.files[0].type)) {
                    alert('Please upload a valid image file (JPG, PNG, or GIF)');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Logo Preview" class="w-full h-full object-cover">`;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Mobile sidebar styles
        const style = document.createElement('style');
        style.textContent = `
            @media (max-width: 768px) {
                .sidebar { transform: translateX(-100%); }
                .sidebar.active { transform: translateX(0); }
            }
            .sidebar-transition { transition: transform 0.3s ease; }
        `;
        document.head.appendChild(style);

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('[class*="bg-green-50"], [class*="bg-red-50"]');
            alerts.forEach(alert => {
                if (alert.querySelector('.fa-check-circle, .fa-exclamation-circle')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>
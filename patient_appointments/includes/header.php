<?php
// includes/header.php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? '';
$user_photo = $_SESSION['user_photo'] ?? 'default.png';

// Fetch system settings from database
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Set system variables from database
    $system_name = $settings['system_name'] ?? 'MediCare';
    $system_logo = $settings['system_logo'] ?? '';
    
    // Update session with latest system name
    $_SESSION['system_name'] = $system_name;
    
} catch (PDOException $e) {
    // Fallback to default/session values if database query fails
    $system_name = $_SESSION['system_name'] ?? 'MediCare';
    $system_logo = '';
    error_log("Header Settings Error: " . $e->getMessage());
}

// Generate abbreviated name for mobile (first letter of each word with spaces)
$system_name_mobile = implode(' ', array_map(function($word) {
    return strtoupper(substr($word, 0, 1));
}, explode(' ', $system_name)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Healthcare System' ?> - <?= htmlspecialchars($system_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #4D774E;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #164A41;
        }
        
        /* Header gradient animation */
        .header-gradient {
            background: linear-gradient(135deg, #FFFFFF 0%, #9DC88D 50%, #4D774E 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* System name responsive styles */
        .system-name-full {
            display: inline;
        }
        
        .system-name-mobile {
            display: none;
        }
        
        @media (max-width: 640px) {
            .system-name-full {
                display: none;
            }
            
            .system-name-mobile {
                display: inline;
                letter-spacing: 0.15em;
                font-weight: 800;
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
    <!-- Top Navigation Bar -->
    <nav class="header-gradient shadow-lg border-b-2 border-[#9DC88D]/30 fixed top-0 left-0 right-0 z-50">
        <div class="px-3 sm:px-4 lg:px-6">
            <div class="flex justify-between items-center h-14 sm:h-16">
                <!-- Left: Logo & Menu Toggle -->
                <div class="flex items-center space-x-2 sm:space-x-4 flex-1 min-w-0">
                    <!-- Mobile Menu Toggle -->
                    <button onclick="toggleSidebar()" 
                            class="md:hidden p-2 text-[#164A41] hover:text-[#F1824A] hover:bg-white/50 rounded-lg focus:outline-none flex-shrink-0 transition-all duration-200">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    
                    <!-- Logo & Name -->
                    <div class="flex items-center space-x-2 sm:space-x-3 min-w-0">
                        <!-- Logo Container -->
                        <div class="logo-container relative w-8 h-8 sm:w-10 sm:h-10 rounded-xl overflow-hidden flex-shrink-0 bg-gradient-to-br from-[#4D774E] to-[#164A41] shadow-lg ring-2 ring-white">
                            <?php if (!empty($system_logo) && file_exists("../public/images/" . $system_logo)): ?>
                                <img src="../public/images/<?= htmlspecialchars($system_logo) ?>?v=<?= time() ?>" 
                                     alt="<?= htmlspecialchars($system_name) ?> Logo" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-heartbeat text-white text-base sm:text-xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- System Name - Responsive -->
                        <span class="text-sm sm:text-xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-[#164A41] to-[#4D774E]">
                            <!-- Full name for tablet/desktop -->
                            <span class="system-name-full">
                                <?= htmlspecialchars($system_name) ?>
                            </span>
                            <!-- Abbreviated name for mobile -->
                            <span class="system-name-mobile">
                                <?= htmlspecialchars($system_name_mobile) ?>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Right: Notifications & Profile -->
                <div class="flex items-center space-x-2 sm:space-x-4 flex-shrink-0">

                    <!-- Profile -->
                    <div class="flex items-center space-x-2 sm:space-x-3 pl-2 sm:pl-4 border-l-2 border-[#9DC88D]/30">
                        <!-- User Info - Hidden on small screens -->
                        <div class="text-right hidden sm:block">
                            <p class="text-xs sm:text-sm font-bold text-[#164A41] truncate max-w-[120px]">
                                <?= htmlspecialchars(explode(' ', $user_name)[0]) ?>
                            </p>
                            <p class="text-[10px] sm:text-xs text-[#4D774E] font-medium">Patient</p>
                        </div>
                        
                        <!-- Profile Picture -->
                        <a href="profile.php" 
                           class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-gradient-to-br from-[#F1824A] to-[#164A41] flex items-center justify-center text-white font-bold overflow-hidden hover:shadow-lg transition-all duration-200 flex-shrink-0 ring-2 ring-white hover:ring-[#9DC88D]">
                            <?php if ($user_photo && $user_photo !== 'default.png' && file_exists("../public/uploads/$user_photo")): ?>
                                <img src="../public/uploads/<?= htmlspecialchars($user_photo) ?>" 
                                     alt="Profile" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-xs sm:text-sm">
                                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" 
         class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden backdrop-blur-sm" 
         onclick="toggleSidebar()">
    </div>

    <script>
        // Toggle Sidebar Function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('hidden');
                
                // Prevent body scroll when sidebar is open
                if (sidebar.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('sidebarOverlay');
            const sidebar = document.getElementById('sidebar');
            
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleSidebar();
                });
            }
            
            // Close sidebar when clicking a link on mobile
            if (sidebar) {
                const sidebarLinks = sidebar.querySelectorAll('a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 768) {
                            toggleSidebar();
                        }
                    });
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    // Desktop view - ensure sidebar is visible and overlay is hidden
                    if (sidebar) sidebar.classList.remove('active');
                    if (overlay) overlay.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        });
    </script>
</body>
</html>
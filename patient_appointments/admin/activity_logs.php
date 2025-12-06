<?php
/**
 * Admin Activity Logs
 * View and filter system activity logs
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Activity Logs';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Pagination settings
$records_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Filter parameters
$filter_admin = $_GET['admin'] ?? '';
$filter_date = $_GET['date'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build SQL query with filters
$where_conditions = [];
$params = [];

if (!empty($filter_admin)) {
    $where_conditions[] = "al.admin_id = ?";
    $params[] = $filter_admin;
}

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(al.timestamp) = ?";
    $params[] = $filter_date;
}

if (!empty($search_query)) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%{$search_query}%";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Get total records count
    $count_sql = "SELECT COUNT(*) as total FROM activity_logs al {$where_sql}";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Fetch activity logs with pagination
    $sql = "SELECT al.*, ad.fullname as admin_name, ad.username as admin_username
            FROM activity_logs al
            LEFT JOIN admin ad ON al.admin_id = ad.id
            {$where_sql}
            ORDER BY al.timestamp DESC
            LIMIT {$records_per_page} OFFSET {$offset}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activity_logs = $stmt->fetchAll();
    
    // Get list of admins for filter dropdown
    $stmt = $pdo->query("SELECT id, fullname, username FROM admin ORDER BY fullname");
    $admin_list = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs");
    $total_logs = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs WHERE DATE(timestamp) = CURDATE()");
    $today_logs = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT admin_id) as total FROM activity_logs");
    $active_admins = $stmt->fetch()['total'];
    
    // Pending Appointments for notification
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'");
    $pending_appointments = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
}

// System Settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    $system_name = $settings['system_name'] ?? 'Healthcare System';
} catch (PDOException $e) {
    $system_name = 'Healthcare System';
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
            <a href="activity_logs.php" class="flex items-center space-x-3 px-4 py-3 bg-primary text-white rounded-lg">
                <i class="fas fa-history w-5"></i>
                <span class="font-medium">Activity Logs</span>
            </a>
            <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
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
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Activity Logs</h1>
                <p class="text-gray-600">Monitor all system activities and administrative actions</p>
            </div>

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

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                <!-- Total Logs -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-list text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded">TOTAL</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($total_logs) ?></h3>
                    <p class="text-sm text-gray-600">Activity Entries</p>
                </div>

                <!-- Today's Logs -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-day text-green-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded">TODAY</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($today_logs) ?></h3>
                    <p class="text-sm text-gray-600">Actions Today</p>
                </div>

                <!-- Active Admins -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-shield text-purple-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-purple-600 bg-purple-50 px-2 py-1 rounded">ACTIVE</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($active_admins) ?></h3>
                    <p class="text-sm text-gray-600">Active Admins</p>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Action</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" 
                                   placeholder="Search activities..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Admin Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Admin</label>
                        <select name="admin" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">All Admins</option>
                            <?php foreach ($admin_list as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= $filter_admin == $admin['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['fullname']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Date</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <!-- Actions -->
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="activity_logs.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Activity Logs Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">Activity History</h3>
                        <span class="text-sm text-gray-500">Showing <?= count($activity_logs) ?> of <?= number_format($total_records) ?> entries</span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Admin</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($activity_logs)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                                    <p>No activity logs found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($activity_logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500">#<?= $log['id'] ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-sm font-semibold">
                                            <?= strtoupper(substr($log['admin_name'] ?? 'S', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($log['admin_name'] ?? 'System') ?></div>
                                            <?php if ($log['admin_username']): ?>
                                            <div class="text-xs text-gray-500">@<?= htmlspecialchars($log['admin_username']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($log['action']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($log['timestamp'])) ?></div>
                                    <div class="text-xs text-gray-500"><?= date('h:i:s A', strtotime($log['timestamp'])) ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            Page <?= $current_page ?> of <?= $total_pages ?>
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?><?= !empty($filter_admin) ? '&admin=' . $filter_admin : '' ?><?= !empty($filter_date) ? '&date=' . $filter_date : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>" 
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="?page=<?= $i ?><?= !empty($filter_admin) ? '&admin=' . $filter_admin : '' ?><?= !empty($filter_date) ? '&date=' . $filter_date : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>" 
                               class="px-4 py-2 border rounded-lg transition <?= $i == $current_page ? 'bg-primary text-white border-primary' : 'border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?><?= !empty($filter_admin) ? '&admin=' . $filter_admin : '' ?><?= !empty($filter_date) ? '&date=' . $filter_date : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>" 
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

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
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
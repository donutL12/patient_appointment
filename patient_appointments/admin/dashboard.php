<?php
/**
 * Admin Dashboard
 * Overview of system statistics and recent activity
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Admin Dashboard';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Fetch Dashboard Statistics
try {
    // Total Users (Patients)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    
    // Total Doctors
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM doctors");
    $total_doctors = $stmt->fetch()['total'];
    
    // Total Appointments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments");
    $total_appointments = $stmt->fetch()['total'];
    
    // Pending Appointments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'");
    $pending_appointments = $stmt->fetch()['total'];
    
    // Today's Appointments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE()");
    $today_appointments = $stmt->fetch()['total'];
    
    // Completed Appointments (This Month)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments 
                        WHERE status = 'completed' 
                        AND MONTH(appointment_date) = MONTH(CURDATE())
                        AND YEAR(appointment_date) = YEAR(CURDATE())");
    $monthly_completed = $stmt->fetch()['total'];
    
    // Appointments by Status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    $status_data = $stmt->fetchAll();
    
    // Recent Appointments (Last 5)
    $stmt = $pdo->query("SELECT a.*, u.fullname as patient_name, d.fullname as doctor_name, d.specialization
                        FROM appointments a
                        JOIN users u ON a.user_id = u.id
                        JOIN doctors d ON a.doctor_id = d.id
                        ORDER BY a.created_at DESC
                        LIMIT 5");
    $recent_appointments = $stmt->fetchAll();
    
    // Recent Activity Logs (Last 10)
    $stmt = $pdo->query("SELECT al.*, ad.fullname as admin_name
                        FROM activity_logs al
                        LEFT JOIN admin ad ON al.admin_id = ad.id
                        ORDER BY al.timestamp DESC
                        LIMIT 10");
    $activity_logs = $stmt->fetchAll();
    
    // Appointments trend (Last 7 days)
    $stmt = $pdo->query("SELECT DATE(appointment_date) as date, COUNT(*) as count
                        FROM appointments
                        WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        GROUP BY DATE(appointment_date)
                        ORDER BY date ASC");
    $appointment_trend = $stmt->fetchAll();
    
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-primary text-white rounded-lg">
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
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard Overview</h1>
                <p class="text-gray-600">Welcome back, <?= htmlspecialchars($admin_name) ?>! Here's what's happening today.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Patients -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded">TOTAL</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($total_users) ?></h3>
                    <p class="text-sm text-gray-600">Registered Patients</p>
                </div>

                <!-- Total Doctors -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-md text-green-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded">ACTIVE</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($total_doctors) ?></h3>
                    <p class="text-sm text-gray-600">Medical Professionals</p>
                </div>

                <!-- Pending Appointments -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded">PENDING</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($pending_appointments) ?></h3>
                    <p class="text-sm text-gray-600">Awaiting Approval</p>
                </div>

                <!-- Today's Appointments -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-day text-purple-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-purple-600 bg-purple-50 px-2 py-1 rounded">TODAY</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($today_appointments) ?></h3>
                    <p class="text-sm text-gray-600">Scheduled Today</p>
                </div>
            </div>

            <!-- Charts & Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Appointment Status Chart -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Appointment Status</h3>
                    <canvas id="statusChart" class="max-h-64"></canvas>
                </div>

                <!-- Weekly Trend -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">7-Day Trend</h3>
                    <canvas id="trendChart" class="max-h-64"></canvas>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">Recent Appointments</h3>
                        <a href="manage_appointments.php" class="text-sm text-primary hover:text-primary-dark font-medium">View All →</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Patient</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Doctor</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($recent_appointments as $apt): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($apt['patient_name']) ?></td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($apt['doctor_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($apt['specialization']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></div>
                                    <div class="text-xs text-gray-500"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $status_colors = [
                                        'pending' => 'bg-orange-100 text-orange-800',
                                        'approved' => 'bg-blue-100 text-blue-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $color = $status_colors[$apt['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $color ?>">
                                        <?= ucfirst($apt['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Activity Logs -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">Recent Activity</h3>
                        <a href="activity_logs.php" class="text-sm text-primary hover:text-primary-dark font-medium">View All →</a>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($activity_logs as $log): ?>
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-primary rounded-full mt-2"></div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-900"><?= htmlspecialchars($log['action']) ?></p>
                                <p class="text-xs text-gray-500">
                                    <?= htmlspecialchars($log['admin_name'] ?? 'System') ?> • 
                                    <?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
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

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php foreach($status_data as $s): ?>'<?= ucfirst($s['status']) ?>',<?php endforeach; ?>],
                datasets: [{
                    data: [<?php foreach($status_data as $s): ?><?= $s['count'] ?>,<?php endforeach; ?>],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach($appointment_trend as $t): ?>'<?= date('M d', strtotime($t['date'])) ?>',<?php endforeach; ?>],
                datasets: [{
                    label: 'Appointments',
                    data: [<?php foreach($appointment_trend as $t): ?><?= $t['count'] ?>,<?php endforeach; ?>],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

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
    </script>
</body>
</html>
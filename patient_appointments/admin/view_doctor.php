<?php
/**
 * View Doctor Details
 * Displays comprehensive information about a specific doctor
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

// Get doctor ID
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($doctor_id <= 0) {
    $_SESSION['error'] = 'Invalid doctor ID.';
    header('Location: manage_doctors.php');
    exit;
}

$page_title = 'Doctor Details';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

try {
    // Fetch doctor details
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        $_SESSION['error'] = 'Doctor not found.';
        header('Location: manage_doctors.php');
        exit;
    }

    // Fetch appointment statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments 
        WHERE doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $stats = $stmt->fetch();

    // Fetch schedule statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_schedules,
            SUM(CASE WHEN schedule_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_schedules,
            SUM(CASE WHEN schedule_date < CURDATE() THEN 1 ELSE 0 END) as past_schedules
        FROM doctor_schedule 
        WHERE doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $schedule_stats = $stmt->fetch();

    // Fetch upcoming schedules
    $stmt = $pdo->prepare("
        SELECT * FROM doctor_schedule
        WHERE doctor_id = ? AND schedule_date >= CURDATE()
        ORDER BY schedule_date ASC, start_time ASC
        LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $upcoming_schedules = $stmt->fetchAll();

    // Fetch recent appointments
    $stmt = $pdo->prepare("
        SELECT a.*, u.fullname as patient_name, u.email as patient_email, u.phone as patient_phone
        FROM appointments a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor_id]);
    $appointments = $stmt->fetchAll();

    // Calculate availability this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as available_slots
        FROM doctor_schedule
        WHERE doctor_id = ? 
        AND schedule_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$doctor_id]);
    $availability = $stmt->fetch();

    // Get system settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    $system_name = $settings['system_name'] ?? 'Healthcare System';

} catch (PDOException $e) {
    $_SESSION['error'] = 'Error loading doctor details.';
    error_log("View Doctor Error: " . $e->getMessage());
    header('Location: manage_doctors.php');
    exit;
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
                    </button>
                    
                    <div class="flex items-center space-x-3 pl-4 border-l border-gray-200">
                        <div class="text-right hidden sm:block">
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
            <a href="manage_doctors.php" class="flex items-center space-x-3 px-4 py-3 bg-primary text-white rounded-lg">
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
            
            <!-- Back Button & Header -->
            <div class="mb-6">
                <a href="manage_doctors.php" class="inline-flex items-center text-primary hover:text-primary-dark mb-4 font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Doctors
                </a>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Doctor Details</h1>
                        <p class="text-gray-600">Complete information about Dr. <?= htmlspecialchars($doctor['fullname']) ?></p>
                    </div>
                    <div class="flex gap-3">
                        <a href="manage_schedules.php?doctor_id=<?= $doctor['id'] ?>" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition shadow-lg">
                            <i class="fas fa-calendar-plus mr-2"></i>Add Schedule
                        </a>
                        <button onclick="editDoctor(<?= $doctor['id'] ?>)" class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition shadow-lg">
                            <i class="fas fa-edit mr-2"></i>Edit
                        </button>
                        <button onclick="deleteDoctor(<?= $doctor['id'] ?>, '<?= htmlspecialchars($doctor['fullname']) ?>')" class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 transition shadow-lg">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Doctor Profile Card -->
            <div class="bg-gradient-to-r from-primary to-primary-dark rounded-xl shadow-lg p-8 mb-6 text-white">
                <div class="flex flex-col md:flex-row md:items-center gap-6">
                    <div class="flex-shrink-0">
                        <div class="w-32 h-32 rounded-full bg-white flex items-center justify-center text-primary text-5xl font-bold shadow-lg">
                            <?= strtoupper(substr($doctor['fullname'], 0, 2)) ?>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-3xl font-bold mb-2">Dr. <?= htmlspecialchars($doctor['fullname']) ?></h2>
                        <p class="text-xl opacity-90 mb-4"><?= htmlspecialchars($doctor['specialization']) ?></p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if ($doctor['email']): ?>
                            <div class="flex items-center">
                                <i class="fas fa-envelope w-6"></i>
                                <span class="ml-3"><?= htmlspecialchars($doctor['email']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($doctor['phone']): ?>
                            <div class="flex items-center">
                                <i class="fas fa-phone w-6"></i>
                                <span class="ml-3"><?= htmlspecialchars($doctor['phone']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex items-center">
                                <i class="fas fa-calendar w-6"></i>
                                <span class="ml-3">Joined: <?= date('M d, Y', strtotime($doctor['created_at'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-clock w-6"></i>
                                <span class="ml-3"><?= $availability['available_slots'] ?> slots this week</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Appointments</p>
                            <h3 class="text-3xl font-bold text-gray-900"><?= number_format($stats['total_appointments']) ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Pending</p>
                            <h3 class="text-3xl font-bold text-yellow-600"><?= number_format($stats['pending']) ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Approved</p>
                            <h3 class="text-3xl font-bold text-purple-600"><?= number_format($stats['approved']) ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Completed</p>
                            <h3 class="text-3xl font-bold text-green-600"><?= number_format($stats['completed']) ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Schedules</p>
                            <h3 class="text-3xl font-bold text-indigo-600"><?= number_format($schedule_stats['total_schedules']) ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-indigo-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                
                <!-- Upcoming Schedules -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900">Upcoming Schedules</h3>
                        <a href="manage_schedules.php?doctor_id=<?= $doctor['id'] ?>" class="text-primary hover:text-primary-dark font-semibold text-sm">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <div class="p-6">
                        <?php if (empty($upcoming_schedules)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No upcoming schedules</p>
                            <a href="manage_schedules.php?doctor_id=<?= $doctor['id'] ?>" class="mt-3 inline-flex items-center text-primary hover:text-primary-dark font-semibold text-sm">
                                <i class="fas fa-plus mr-2"></i>Add Schedule
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcoming_schedules as $schedule): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div>
                                    <p class="font-semibold text-gray-900"><?= date('l, M d, Y', strtotime($schedule['schedule_date'])) ?></p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?= date('h:i A', strtotime($schedule['start_time'])) ?> - <?= date('h:i A', strtotime($schedule['end_time'])) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-primary"><?= $schedule['slots'] ?></p>
                                    <p class="text-xs text-gray-500">slots</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Performance Overview</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <!-- Completion Rate -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-700">Completion Rate</span>
                                    <span class="text-sm font-bold text-green-600">
                                        <?= $stats['total_appointments'] > 0 ? round(($stats['completed'] / $stats['total_appointments']) * 100, 1) : 0 ?>%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-green-600 h-3 rounded-full" style="width: <?= $stats['total_appointments'] > 0 ? round(($stats['completed'] / $stats['total_appointments']) * 100, 1) : 0 ?>%"></div>
                                </div>
                            </div>

                            <!-- Approval Rate -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-700">Approval Rate</span>
                                    <span class="text-sm font-bold text-blue-600">
                                        <?= $stats['total_appointments'] > 0 ? round((($stats['approved'] + $stats['completed']) / $stats['total_appointments']) * 100, 1) : 0 ?>%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-blue-600 h-3 rounded-full" style="width: <?= $stats['total_appointments'] > 0 ? round((($stats['approved'] + $stats['completed']) / $stats['total_appointments']) * 100, 1) : 0 ?>%"></div>
                                </div>
                            </div>

                            <!-- Schedule Stats -->
                            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-100">
                                <div class="text-center p-4 bg-blue-50 rounded-lg">
                                    <p class="text-2xl font-bold text-blue-600"><?= $schedule_stats['upcoming_schedules'] ?></p>
                                    <p class="text-xs text-gray-600 mt-1">Upcoming</p>
                                </div>
                                <div class="text-center p-4 bg-gray-50 rounded-lg">
                                    <p class="text-2xl font-bold text-gray-600"><?= $schedule_stats['past_schedules'] ?></p>
                                    <p class="text-xs text-gray-600 mt-1">Past</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Recent Appointments -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900">Recent Appointments</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Patient</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Date & Time</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-calendar-times text-4xl mb-4 block text-gray-300"></i>
                                    <p class="font-medium">No appointments found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($appointments as $apt): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($apt['patient_name']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($apt['patient_email']) ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></p>
                                        <p class="text-sm text-gray-500"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-blue-100 text-blue-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusColor = $statusColors[$apt['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>">
                                        <?= ucfirst($apt['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-gray-600"><?= $apt['notes'] ? htmlspecialchars($apt['notes']) : '-' ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
        }

        function editDoctor(id) {
            window.location.href = `manage_doctors.php?edit=${id}#edit`;
        }

        function deleteDoctor(id, name) {
            if (confirm(`Are you sure you want to delete Dr. ${name}? This will also delete all their schedules and appointments. This action cannot be undone.`)) {
                window.location.href = `../routes/admin_routes.php?action=delete_doctor&id=${id}`;
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
    </script>
</body>
</html>
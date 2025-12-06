<?php
/**
 * Manage Doctor Schedules
 * Admin page for viewing, adding, editing, and deleting doctor schedules
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Manage Schedules';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Fetch all doctors for dropdown
try {
    $stmt = $pdo->query("SELECT id, fullname, specialization FROM doctors ORDER BY fullname ASC");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    $doctors = [];
    $error_message = "Error fetching doctors: " . $e->getMessage();
}

// Get filter parameters
$filter_doctor = $_GET['doctor_id'] ?? '';
$filter_date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT ds.*, d.fullname, d.specialization,
          (SELECT COUNT(*) FROM appointments WHERE schedule_id = ds.id) as booked_count
          FROM doctor_schedule ds
          JOIN doctors d ON ds.doctor_id = d.id
          WHERE 1=1";

$params = [];

if ($filter_doctor) {
    $query .= " AND ds.doctor_id = ?";
    $params[] = $filter_doctor;
}

if ($filter_date) {
    $query .= " AND ds.schedule_date = ?";
    $params[] = $filter_date;
}

if ($search) {
    $query .= " AND d.fullname LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY ds.schedule_date DESC, ds.start_time ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
    
    // Get total schedules count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM doctor_schedule");
    $total_schedules = $stmt->fetch()['total'];
    
    // Get upcoming schedules count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM doctor_schedule WHERE schedule_date >= CURDATE()");
    $upcoming_schedules = $stmt->fetch()['total'];
    
    // Get today's schedules count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM doctor_schedule WHERE schedule_date = CURDATE()");
    $today_schedules = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $schedules = [];
    $error_message = "Error fetching schedules: " . $e->getMessage();
}

// Fetch system settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    $system_name = $settings['system_name'] ?? 'Healthcare System';
    $default_slots = $settings['default_slots'] ?? 20;
} catch (PDOException $e) {
    $system_name = 'Healthcare System';
    $default_slots = 20;
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
            <a href="manage_schedules.php" class="flex items-center space-x-3 px-4 py-3 bg-primary text-white rounded-lg">
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
            
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Doctor Schedules</h1>
                <p class="text-gray-600">Create and manage doctor availability schedules</p>
            </div>

            <!-- Alert Messages -->
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
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded">TOTAL</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($total_schedules) ?></h3>
                    <p class="text-sm text-gray-600">Total Schedules</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded">UPCOMING</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($upcoming_schedules) ?></h3>
                    <p class="text-sm text-gray-600">Upcoming Schedules</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-day text-purple-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-semibold text-purple-600 bg-purple-50 px-2 py-1 rounded">TODAY</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= number_format($today_schedules) ?></h3>
                    <p class="text-sm text-gray-600">Today's Schedules</p>
                </div>
            </div>

            <!-- Filters and Add Button -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
                <div class="p-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <form method="GET" class="flex flex-col sm:flex-row gap-3 flex-1">
                            <div class="flex-1">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Search by doctor name..." 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <select name="doctor_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">All Doctors</option>
                                <?php foreach ($doctors as $doc): ?>
                                <option value="<?= $doc['id'] ?>" <?= $filter_doctor == $doc['id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doc['fullname']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" 
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <button type="submit" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            <?php if ($search || $filter_doctor || $filter_date): ?>
                            <a href="manage_schedules.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-center">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                            <?php endif; ?>
                        </form>
                        <button onclick="openAddModal()" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition whitespace-nowrap">
                            <i class="fas fa-plus mr-2"></i>Add Schedule
                        </button>
                    </div>
                </div>
            </div>

            <!-- Schedules Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Doctor</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Slots</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-calendar-times text-4xl mb-2 text-gray-300"></i>
                                    <p>No schedules found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($schedules as $schedule): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">Dr. <?= htmlspecialchars($schedule['fullname']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($schedule['specialization']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($schedule['schedule_date'])) ?></div>
                                    <div class="text-xs text-gray-500"><?= date('l', strtotime($schedule['schedule_date'])) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?= date('h:i A', strtotime($schedule['start_time'])) ?> - 
                                        <?= date('h:i A', strtotime($schedule['end_time'])) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?= $schedule['booked_count'] ?> / <?= $schedule['slots'] ?>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                        <div class="bg-primary h-1.5 rounded-full" style="width: <?= ($schedule['slots'] > 0) ? ($schedule['booked_count'] / $schedule['slots'] * 100) : 0 ?>%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $status = 'upcoming';
                                    $status_color = 'bg-blue-100 text-blue-800';
                                    
                                    if (strtotime($schedule['schedule_date']) < strtotime('today')) {
                                        $status = 'past';
                                        $status_color = 'bg-gray-100 text-gray-800';
                                    } elseif ($schedule['booked_count'] >= $schedule['slots']) {
                                        $status = 'full';
                                        $status_color = 'bg-red-100 text-red-800';
                                    } elseif (strtotime($schedule['schedule_date']) == strtotime('today')) {
                                        $status = 'today';
                                        $status_color = 'bg-green-100 text-green-800';
                                    }
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $status_color ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button onclick="deleteSchedule(<?= $schedule['id'] ?>, '<?= htmlspecialchars($schedule['fullname']) ?>', '<?= date('M d, Y', strtotime($schedule['schedule_date'])) ?>')" 
                                            class="text-red-600 hover:text-red-800 transition" 
                                            title="Delete Schedule">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

    <!-- Add Schedule Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-900">Add New Schedule</h3>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <!-- CORRECTED PATH: admin_routes.php is in routes/ folder -->
        <form action="../routes/admin_routes.php" method="POST" class="p-6">
            <input type="hidden" name="action" value="add_schedule">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Doctor *</label>
                    <select name="doctor_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>">Dr. <?= htmlspecialchars($doc['fullname']) ?> - <?= htmlspecialchars($doc['specialization']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                    <input type="date" name="schedule_date" required min="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Time *</label>
                        <input type="time" name="start_time" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Time *</label>
                        <input type="time" name="end_time" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Available Slots *</label>
                    <input type="number" name="slots" value="<?= $default_slots ?>" min="1" max="100" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    <p class="text-xs text-gray-500 mt-1">Maximum number of appointments for this schedule</p>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition">
                    <i class="fas fa-plus mr-2"></i>Add Schedule
                </button>
            </div>
        </form>
    </div>
</div>
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
        }

        // Modal Functions
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function deleteSchedule(id, doctorName, date) {
            if (confirm(`Are you sure you want to delete the schedule for Dr. ${doctorName} on ${date}?\n\nNote: Schedules with existing appointments cannot be deleted.`)) {
                // CORRECTED PATH: admin_routes.php is in routes/ folder
                window.location.href = `../routes/admin_routes.php?action=delete_schedule&id=${id}`;
            }
        }

        // Close modal when clicking outside
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
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
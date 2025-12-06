<?php
/**
 * Admin Manage Appointments
 * Displays a list of all appointments with filtering and status management.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Manage Appointments';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Initialize Controller
$appointmentController = new AppointmentController($pdo);

// --- Handle Status Update Request (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null;

    if ($appointment_id && $new_status) {
        $result = $appointmentController->updateAppointmentStatus($appointment_id, $new_status);
        
        // Log activity (using AuthController's logActivity function for consistency)
        require_once __DIR__ . '/../controllers/AuthController.php';
        $authController = new AuthController($pdo);
        $log_action = "Updated appointment ID $appointment_id status to '$new_status'";
        $authController->logActivity($_SESSION['admin_id'], $log_action);

        if ($result) {
            $_SESSION['success'] = "Appointment status updated to '" . ucfirst($new_status) . "' successfully.";
        } else {
            $_SESSION['error'] = "Failed to update appointment status.";
        }
        // Redirect to clear POST data and prevent resubmission
        header('Location: manage_appointments.php');
        exit;
    }
}

// --- Fetch All Appointments with details ---
try {
    // Get filter and search parameters
    $filter_status = $_GET['status'] ?? 'all';
    $search_term = $_GET['search'] ?? '';

    $appointments = $appointmentController->getAllAppointmentsWithDetails($filter_status, $search_term);
    
    // Fetch pending count for the sidebar badge
    $pending_appointments_count = $appointmentController->getAppointmentCountByStatus('pending');

    // System Settings (re-used from dashboard.php for navigation/title)
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'system_name'");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $system_name = $settings['setting_value'] ?? 'Healthcare System';

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching appointments: " . $e->getMessage();
    $appointments = [];
    $pending_appointments_count = 0;
}


/**
 * Helper function to determine badge styles for status.
 * Replicated from dashboard.php for easy reuse.
 */
function getStatusBadgeClass($status) {
    $status_colors = [
        'pending' => 'bg-orange-100 text-orange-800',
        'approved' => 'bg-blue-100 text-blue-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];
    return $status_colors[$status] ?? 'bg-gray-100 text-gray-800';
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
        .sidebar { transition: transform 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
        }
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
                        <?php if ($pending_appointments_count > 0): ?>
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
            <a href="manage_appointments.php" class="flex items-center space-x-3 px-4 py-3 bg-primary text-white rounded-lg">
                <i class="fas fa-calendar-check w-5"></i>
                <span class="font-medium">Appointments</span>
                <?php if ($pending_appointments_count > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?= $pending_appointments_count ?></span>
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

    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden" onclick="toggleSidebar()"></div>

    <main class="md:ml-64 pt-16 min-h-screen">
        <div class="p-6 max-w-7xl mx-auto">
            
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= $page_title ?></h1>
                <p class="text-gray-600">Review, filter, and manage all patient appointment requests.</p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                    <span class="font-medium">Success!</span> <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                    <span class="font-medium">Error!</span> <?= $_SESSION['error'] ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">Appointment Records</h3>
                </div>
                
                <div class="p-6">
                    <form action="manage_appointments.php" method="GET" class="flex flex-col sm:flex-row gap-4">
                        <div class="flex-grow">
                            <label for="search" class="sr-only">Search</label>
                            <input type="search" name="search" id="search" value="<?= htmlspecialchars($search_term) ?>"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:border-primary focus:ring-primary"
                                placeholder="Search by patient name, doctor, or reason...">
                        </div>
                        <div>
                            <label for="status" class="sr-only">Filter by Status</label>
                            <select name="status" id="status" class="border-gray-300 rounded-lg shadow-sm focus:border-primary focus:ring-primary">
                                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                    </form>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Patient</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Doctor</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date/Time</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No appointments found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $apt): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-500">#<?= htmlspecialchars($apt['id']) ?></td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($apt['patient_name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($apt['patient_email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($apt['doctor_name']) ?></div>
                                        <div class="text-xs text-primary"><?= htmlspecialchars($apt['specialization']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></div>
                                        <div class="text-xs text-gray-500"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= getStatusBadgeClass($apt['status']) ?>">
                                            <?= ucfirst($apt['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="appointment_view.php?id=<?= $apt['id'] ?>" title="View Details"
                                               class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <div class="relative inline-block text-left">
                                                <button type="button" onclick="document.getElementById('dropdown-<?= $apt['id'] ?>').classList.toggle('hidden')"
                                                    class="inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-2 py-1 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none" 
                                                    id="menu-button-<?= $apt['id'] ?>" aria-expanded="true" aria-haspopup="true">
                                                    Update <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                                </button>
                                                
                                                <div id="dropdown-<?= $apt['id'] ?>" class="origin-top-right absolute right-0 mt-2 w-32 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none hidden z-10" role="menu" aria-orientation="vertical" aria-labelledby="menu-button-<?= $apt['id'] ?>">
                                                    <div class="py-1" role="none">
                                                        <?php 
                                                            $all_statuses = ['pending', 'approved', 'completed', 'cancelled'];
                                                            foreach ($all_statuses as $status_option):
                                                                if ($status_option !== $apt['status']):
                                                        ?>
                                                                <form method="POST" action="manage_appointments.php" class="block">
                                                                    <input type="hidden" name="action" value="update_status">
                                                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                                    <input type="hidden" name="new_status" value="<?= $status_option ?>">
                                                                    <button type="submit" 
                                                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                                                                        onclick="return confirm('Are you sure you want to change the status to \'<?= ucfirst($status_option) ?>\' for appointment #<?= $apt['id'] ?>?');">
                                                                        To <?= ucfirst($status_option) ?>
                                                                    </button>
                                                                </form>
                                                        <?php 
                                                                endif;
                                                            endforeach;
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
        }

        // Hide dropdown on click outside
        document.addEventListener('click', function(event) {
            document.querySelectorAll('[id^="dropdown-"]').forEach(dropdown => {
                const button = document.getElementById('menu-button-' + dropdown.id.split('-')[1]);
                if (button && !button.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>
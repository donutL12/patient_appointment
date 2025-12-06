<?php
/**
 * View User (Patient) Details
 * Admin panel to view detailed patient information and appointment history
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Patient Details';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Get user ID
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    $_SESSION['error'] = 'Invalid patient ID.';
    header('Location: manage_users.php');
    exit;
}

// Fetch patient details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'Patient not found.';
        header('Location: manage_users.php');
        exit;
    }
    
    // Fetch patient's appointments
    $stmt = $pdo->prepare("
        SELECT a.*, 
               d.fullname as doctor_name, 
               d.specialization,
               ds.schedule_date,
               ds.start_time,
               ds.end_time
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN doctor_schedule ds ON a.schedule_id = ds.id
        WHERE a.user_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$user_id]);
    $appointments = $stmt->fetchAll();
    
    // Count appointments by status
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching patient details: " . $e->getMessage();
    header('Location: manage_users.php');
    exit;
}

// Get system settings
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
            <a href="manage_users.php" class="flex items-center space-x-3 px-4 py-3 bg-primary text-white rounded-lg">
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
            <div class="mb-6">
                <div class="flex items-center space-x-3 mb-4">
                    <a href="manage_users.php" class="p-2 hover:bg-gray-100 rounded-lg transition">
                        <i class="fas fa-arrow-left text-gray-600"></i>
                    </a>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Patient Details</h1>
                        <p class="text-gray-600">Viewing information for <?= htmlspecialchars($user['fullname']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Patient Information Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-20 h-20 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-semibold flex-shrink-0">
                            <?= strtoupper(substr($user['fullname'], 0, 1)) ?>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($user['fullname']) ?></h2>
                            <div class="space-y-2">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-envelope w-5 mr-2"></i>
                                    <span><?= htmlspecialchars($user['email']) ?></span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-phone w-5 mr-2"></i>
                                    <span><?= htmlspecialchars($user['phone']) ?></span>
                                </div>
                                <?php if (!empty($user['dob'])): ?>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-birthday-cake w-5 mr-2"></i>
                                    <span><?= date('M d, Y', strtotime($user['dob'])) ?> (<?= date_diff(date_create($user['dob']), date_create('today'))->y ?> years old)</span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($user['gender'])): ?>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-venus-mars w-5 mr-2"></i>
                                    <span><?= ucfirst(htmlspecialchars($user['gender'])) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($user['address'])): ?>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-map-marker-alt w-5 mr-2"></i>
                                    <span><?= htmlspecialchars($user['address']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button onclick="editUser(<?= $user['id'] ?>)" 
                                class="px-4 py-2 bg-primary text-white rounded-lg font-semibold hover:bg-primary-dark transition">
                            <i class="fas fa-edit mr-2"></i>Edit Patient
                        </button>
                        <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['fullname']) ?>')" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Patient ID:</span>
                            <span class="ml-2 font-semibold text-gray-900">#<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Registered:</span>
                            <span class="ml-2 font-semibold text-gray-900"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointment Statistics -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-1">Total</p>
                        <h3 class="text-2xl font-bold text-gray-900"><?= $stats['total'] ?></h3>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-1">Pending</p>
                        <h3 class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?></h3>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-1">Approved</p>
                        <h3 class="text-2xl font-bold text-blue-600"><?= $stats['approved'] ?></h3>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-1">Completed</p>
                        <h3 class="text-2xl font-bold text-green-600"><?= $stats['completed'] ?></h3>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-1">Cancelled</p>
                        <h3 class="text-2xl font-bold text-red-600"><?= $stats['cancelled'] ?></h3>
                    </div>
                </div>
            </div>

            <!-- Appointment History -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-900">Appointment History</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Date & Time</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Doctor</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase hidden lg:table-cell">Notes</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
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
                                        <p class="font-semibold text-gray-900">
                                            <?= date('M d, Y', strtotime($apt['appointment_date'])) ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($apt['doctor_name']) ?></p>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($apt['specialization']) ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden lg:table-cell">
                                    <p class="text-sm text-gray-600 max-w-xs truncate">
                                        <?= !empty($apt['notes']) ? htmlspecialchars($apt['notes']) : '-' ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center">
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800'
                                        ];
                                        $color = $status_colors[$apt['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                            <?= ucfirst($apt['status']) ?>
                                        </span>
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

    <!-- Edit Patient Modal (Reuse from manage_users.php) -->
    <div id="patientModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">Edit Patient</h3>
                    <button onclick="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg transition">
                        <i class="fas fa-times text-gray-500"></i>
                    </button>
                </div>
            </div>
            <form id="patientForm" action="../routes/admin_routes.php" method="POST" class="p-6">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="userId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                        <input type="text" name="fullname" id="fullname" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" id="email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                        <input type="tel" name="phone" id="phone" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input type="password" name="password" id="password" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"
                               placeholder="Leave blank to keep current">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date of Birth</label>
                        <input type="date" name="dob" id="dob" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Gender</label>
                        <select name="gender" id="gender" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                        <textarea name="address" id="address" rows="3" 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary-dark transition">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
        }

        function closeModal() {
            document.getElementById('patientModal').classList.add('hidden');
        }

        function editUser(id) {
            fetch(`../routes/admin_routes.php?action=get_user&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('userId').value = data.user.id;
                        document.getElementById('fullname').value = data.user.fullname;
                        document.getElementById('email').value = data.user.email;
                        document.getElementById('phone').value = data.user.phone;
                        document.getElementById('dob').value = data.user.dob || '';
                        document.getElementById('gender').value = data.user.gender || '';
                        document.getElementById('address').value = data.user.address || '';
                        document.getElementById('patientModal').classList.remove('hidden');
                    }
                });
        }

        function deleteUser(id, name) {
            if (confirm(`Are you sure you want to delete patient "${name}"? This action cannot be undone.`)) {
                window.location.href = `../routes/admin_routes.php?action=delete_user&id=${id}`;
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
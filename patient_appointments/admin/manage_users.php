    <?php
    /**
     * Manage Users (Patients)
     * Admin panel to view, add, edit, and delete patients
     */
    session_start();
    require_once __DIR__ . '/../config/db.php';

    // Check if admin is logged in
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ../index.php');
        exit;
    }

    $page_title = 'Manage Patients';
    $admin_name = $_SESSION['admin_name'] ?? 'Admin';
    $admin_role = $_SESSION['admin_role'] ?? 'staff';

    // Handle actions
    $success = $_SESSION['success'] ?? '';
    $error = $_SESSION['error'] ?? '';
    unset($_SESSION['success'], $_SESSION['error']);

    // Fetch all patients
    try {
        $search = $_GET['search'] ?? '';
        $query = "SELECT * FROM users WHERE 1=1";
        
        if ($search) {
            $query .= " AND (fullname LIKE :search OR email LIKE :search OR phone LIKE :search)";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($query);
        
        if ($search) {
            $stmt->execute([':search' => "%$search%"]);
        } else {
            $stmt->execute();
        }
        
        $users = $stmt->fetchAll();
        $total_users = count($users);
        
    } catch (PDOException $e) {
        $error = "Error fetching patients: " . $e->getMessage();
        $users = [];
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
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Patients</h1>
                            <p class="text-gray-600">View and manage registered patients</p>
                        </div>
                        <button onclick="openAddModal()" class="inline-flex items-center px-6 py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary-dark transition shadow-lg">
                            <i class="fas fa-plus mr-2"></i>Add Patient
                        </button>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span class="text-green-700 font-medium"><?= htmlspecialchars($success) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <span class="text-red-700 font-medium"><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Search & Filter -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
                    <form method="GET" class="flex flex-col sm:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" 
                                       name="search" 
                                       value="<?= htmlspecialchars($search) ?>"
                                       placeholder="Search by name, email, or phone..." 
                                       class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                        </div>
                        <button type="submit" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <?php if ($search): ?>
                        <a href="manage_users.php" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition text-center">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Total Patients</p>
                                <h3 class="text-2xl font-bold text-gray-900"><?= number_format($total_users) ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patients Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Patient</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase hidden lg:table-cell">Registered</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-user-slash text-4xl mb-4 block text-gray-300"></i>
                                        <p class="font-medium">No patients found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-semibold flex-shrink-0">
                                                <?= strtoupper(substr($user['fullname'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($user['fullname']) ?></p>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900"><?= htmlspecialchars($user['phone']) ?></p>
                                    </td>
                                    <td class="px-6 py-4 hidden lg:table-cell">
                                        <p class="text-sm text-gray-600"><?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button onclick="viewUser(<?= $user['id'] ?>)" 
                                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editUser(<?= $user['id'] ?>)" 
                                                    class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['fullname']) ?>')" 
                                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

        <!-- Add/Edit Patient Modal -->
        <div id="patientModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Add New Patient</h3>
                        <button onclick="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg transition">
                            <i class="fas fa-times text-gray-500"></i>
                        </button>
                    </div>
                </div>
                <form id="patientForm" action="../routes/admin_routes.php" method="POST" class="p-6">
                    <input type="hidden" name="action" id="formAction" value="add_user">
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
                            <i class="fas fa-save mr-2"></i>Save Patient
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

            function openAddModal() {
                document.getElementById('modalTitle').textContent = 'Add New Patient';
                document.getElementById('formAction').value = 'add_user';
                document.getElementById('patientForm').reset();
                document.getElementById('userId').value = '';
                document.getElementById('password').required = true;
                document.getElementById('patientModal').classList.remove('hidden');
            }

            function closeModal() {
                document.getElementById('patientModal').classList.add('hidden');
            }

            function viewUser(id) {
                window.location.href = `view_user.php?id=${id}`;
            }

            function editUser(id) {
                // Fetch user data and populate modal
                fetch(`../routes/admin_routes.php?action=get_user&id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('modalTitle').textContent = 'Edit Patient';
                            document.getElementById('formAction').value = 'edit_user';
                            document.getElementById('userId').value = data.user.id;
                            document.getElementById('fullname').value = data.user.fullname;
                            document.getElementById('email').value = data.user.email;
                            document.getElementById('phone').value = data.user.phone;
                            document.getElementById('dob').value = data.user.dob || '';
                            document.getElementById('gender').value = data.user.gender || '';
                            document.getElementById('address').value = data.user.address || '';
                            document.getElementById('password').required = false;
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
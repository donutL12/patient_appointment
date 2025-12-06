<?php
/**
 * Appointments System Debug Tool
 * Place this file in: doctor/debug_appointments.php
 * Access via: localhost/patient_appointments/doctor/debug_appointments.php
 */

session_start();
require_once __DIR__ . '/../config/db.php';

// Force display errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Debug Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .status-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f5f5f5;
        }
        
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-pending { background: #ffc107; color: #000; }
        .badge-approved { background: #28a745; color: white; }
        .badge-completed { background: #17a2b8; color: white; }
        .badge-cancelled { background: #dc3545; color: white; }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #667eea;
        }
        
        .info-value {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Appointments System Debug Tool</h1>
            <p>Comprehensive diagnostic information for troubleshooting appointments</p>
        </div>

        <?php
        // ==========================================
        // 1. SESSION CHECK
        // ==========================================
        ?>
        <div class="section">
            <h2>1. Session Information</h2>
            <?php if (isset($_SESSION['doctor_id'])): ?>
                <div class="status-box status-success">
                    ✓ Doctor is logged in
                </div>
                <div class="info-row">
                    <span class="info-label">Doctor ID:</span>
                    <span class="info-value"><?= $_SESSION['doctor_id'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Doctor Name:</span>
                    <span class="info-value"><?= $_SESSION['doctor_name'] ?? 'Not Set' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Doctor Email:</span>
                    <span class="info-value"><?= $_SESSION['doctor_email'] ?? 'Not Set' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Specialization:</span>
                    <span class="info-value"><?= $_SESSION['doctor_specialization'] ?? 'Not Set' ?></span>
                </div>
            <?php else: ?>
                <div class="status-box status-error">
                    ✗ No doctor logged in - Session missing 'doctor_id'
                </div>
                <p><strong>Action Required:</strong> Please login as a doctor first.</p>
                <a href="../index.php" class="btn">Go to Login</a>
            <?php endif; ?>
        </div>

        <?php
        // ==========================================
        // 2. DATABASE CONNECTION
        // ==========================================
        ?>
        <div class="section">
            <h2>2. Database Connection</h2>
            <?php
            try {
                $test = $pdo->query("SELECT 1");
                echo '<div class="status-box status-success">✓ Database connection successful</div>';
                
                // Get database info
                $dbInfo = $pdo->query("SELECT DATABASE() as db_name, VERSION() as version")->fetch();
                echo '<div class="info-row">';
                echo '<span class="info-label">Database Name:</span>';
                echo '<span class="info-value">' . $dbInfo['db_name'] . '</span>';
                echo '</div>';
                echo '<div class="info-row">';
                echo '<span class="info-label">MySQL Version:</span>';
                echo '<span class="info-value">' . $dbInfo['version'] . '</span>';
                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="status-box status-error">✗ Database connection failed</div>';
                echo '<div class="code-block">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <?php
        // ==========================================
        // 3. TABLES CHECK
        // ==========================================
        ?>
        <div class="section">
            <h2>3. Database Tables Check</h2>
            <?php
            $requiredTables = ['appointments', 'users', 'doctors', 'doctor_schedule'];
            $tablesExist = true;
            
            foreach ($requiredTables as $table) {
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        // Get row count
                        $count = $pdo->query("SELECT COUNT(*) as count FROM $table")->fetch()['count'];
                        echo '<div class="info-row">';
                        echo '<span class="info-label">✓ ' . ucfirst($table) . ' table:</span>';
                        echo '<span class="info-value">' . $count . ' records</span>';
                        echo '</div>';
                    } else {
                        echo '<div class="status-box status-error">✗ Table "' . $table . '" does not exist</div>';
                        $tablesExist = false;
                    }
                } catch (Exception $e) {
                    echo '<div class="status-box status-error">✗ Error checking table "' . $table . '": ' . $e->getMessage() . '</div>';
                    $tablesExist = false;
                }
            }
            ?>
        </div>

        <?php if (isset($_SESSION['doctor_id'])): ?>
            <?php
            $doctor_id = $_SESSION['doctor_id'];
            
            // ==========================================
            // 4. DOCTOR VERIFICATION
            // ==========================================
            ?>
            <div class="section">
                <h2>4. Doctor Verification</h2>
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
                    $stmt->execute([$doctor_id]);
                    $doctor = $stmt->fetch();
                    
                    if ($doctor) {
                        echo '<div class="status-box status-success">✓ Doctor found in database</div>';
                        echo '<table>';
                        echo '<tr><th>Field</th><th>Value</th></tr>';
                        foreach ($doctor as $key => $value) {
                            if (!is_numeric($key)) {
                                echo '<tr>';
                                echo '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
                                echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
                                echo '</tr>';
                            }
                        }
                        echo '</table>';
                    } else {
                        echo '<div class="status-box status-error">✗ Doctor ID ' . $doctor_id . ' not found in doctors table</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="status-box status-error">✗ Error: ' . $e->getMessage() . '</div>';
                }
                ?>
            </div>

            <?php
            // ==========================================
            // 5. APPOINTMENTS STATISTICS
            // ==========================================
            ?>
            <div class="section">
                <h2>5. Appointments Statistics</h2>
                <?php
                try {
                    // Total appointments
                    $total = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
                    $total->execute([$doctor_id]);
                    $totalCount = $total->fetch()['count'];
                    
                    // By status
                    $statuses = ['pending', 'approved', 'completed', 'cancelled'];
                    $stats = [];
                    
                    foreach ($statuses as $status) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = ?");
                        $stmt->execute([$doctor_id, $status]);
                        $stats[$status] = $stmt->fetch()['count'];
                    }
                    
                    if ($totalCount > 0) {
                        echo '<div class="status-box status-success">✓ Found ' . $totalCount . ' appointment(s) for this doctor</div>';
                        echo '<div class="grid">';
                        echo '<div class="stat-card"><h3>' . $totalCount . '</h3><p>Total Appointments</p></div>';
                        echo '<div class="stat-card"><h3>' . $stats['pending'] . '</h3><p>Pending</p></div>';
                        echo '<div class="stat-card"><h3>' . $stats['approved'] . '</h3><p>Approved</p></div>';
                        echo '<div class="stat-card"><h3>' . $stats['completed'] . '</h3><p>Completed</p></div>';
                        echo '<div class="stat-card"><h3>' . $stats['cancelled'] . '</h3><p>Cancelled</p></div>';
                        echo '</div>';
                    } else {
                        echo '<div class="status-box status-warning">⚠ No appointments found for doctor ID: ' . $doctor_id . '</div>';
                        echo '<p><strong>This is why appointments are not displaying!</strong></p>';
                        echo '<p>You need to create test appointments. See the "Create Test Data" section below.</p>';
                    }
                } catch (Exception $e) {
                    echo '<div class="status-box status-error">✗ Error: ' . $e->getMessage() . '</div>';
                }
                ?>
            </div>

            <?php
            // ==========================================
            // 6. APPOINTMENTS QUERY TEST
            // ==========================================
            ?>
            <div class="section">
                <h2>6. Appointments Query Test</h2>
                <?php
                try {
                    $query = "
                        SELECT 
                            a.id,
                            a.user_id,
                            a.doctor_id,
                            a.appointment_date,
                            a.appointment_time,
                            a.reason,
                            a.status,
                            a.notes,
                            u.fullname as patient_name,
                            u.email as patient_email,
                            u.phone as patient_phone,
                            COALESCE(u.gender, 'N/A') as patient_gender
                        FROM appointments a
                        INNER JOIN users u ON a.user_id = u.id
                        WHERE a.doctor_id = ?
                        ORDER BY a.appointment_date DESC, a.appointment_time DESC
                    ";
                    
                    echo '<div class="status-box status-info">Testing query execution...</div>';
                    echo '<div class="code-block">' . htmlspecialchars($query) . '</div>';
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$doctor_id]);
                    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo '<div class="info-row">';
                    echo '<span class="info-label">Query Executed:</span>';
                    echo '<span class="info-value">✓ Successfully</span>';
                    echo '</div>';
                    echo '<div class="info-row">';
                    echo '<span class="info-label">Rows Returned:</span>';
                    echo '<span class="info-value">' . count($appointments) . '</span>';
                    echo '</div>';
                    
                    if (count($appointments) > 0) {
                        echo '<div class="status-box status-success">✓ Query returned ' . count($appointments) . ' appointment(s)</div>';
                        echo '<table>';
                        echo '<tr>';
                        echo '<th>ID</th>';
                        echo '<th>Patient</th>';
                        echo '<th>Date</th>';
                        echo '<th>Time</th>';
                        echo '<th>Status</th>';
                        echo '<th>Reason</th>';
                        echo '</tr>';
                        
                        foreach ($appointments as $apt) {
                            $statusClass = 'badge-' . $apt['status'];
                            echo '<tr>';
                            echo '<td>#' . $apt['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($apt['patient_name']) . '</td>';
                            echo '<td>' . date('M d, Y', strtotime($apt['appointment_date'])) . '</td>';
                            echo '<td>' . date('h:i A', strtotime($apt['appointment_time'])) . '</td>';
                            echo '<td><span class="badge ' . $statusClass . '">' . ucfirst($apt['status']) . '</span></td>';
                            echo '<td>' . htmlspecialchars($apt['reason'] ?? 'N/A') . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    } else {
                        echo '<div class="status-box status-warning">⚠ Query executed successfully but returned 0 rows</div>';
                    }
                    
                } catch (Exception $e) {
                    echo '<div class="status-box status-error">✗ Query failed</div>';
                    echo '<div class="code-block">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                ?>
            </div>

            <?php
            // ==========================================
            // 7. USERS CHECK
            // ==========================================
            ?>
            <div class="section">
                <h2>7. Users (Patients) Check</h2>
                <?php
                try {
                    $stmt = $pdo->query("SELECT id, fullname, email, phone FROM users LIMIT 10");
                    $users = $stmt->fetchAll();
                    
                    if (count($users) > 0) {
                        echo '<div class="status-box status-success">✓ Found ' . count($users) . ' user(s) in database</div>';
                        echo '<table>';
                        echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th></tr>';
                        foreach ($users as $user) {
                            echo '<tr>';
                            echo '<td>' . $user['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($user['fullname']) . '</td>';
                            echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                            echo '<td>' . htmlspecialchars($user['phone']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    } else {
                        echo '<div class="status-box status-warning">⚠ No users found in database</div>';
                        echo '<p>You need to create a test patient first!</p>';
                    }
                } catch (Exception $e) {
                    echo '<div class="status-box status-error">✗ Error: ' . $e->getMessage() . '</div>';
                }
                ?>
            </div>

            <?php
            // ==========================================
            // 8. ALL APPOINTMENTS (ANY DOCTOR)
            // ==========================================
            ?>
            <div class="section">
                <h2>8. All Appointments (Any Doctor)</h2>
                <?php
                try {
                    $stmt = $pdo->query("
                        SELECT 
                            a.id,
                            a.doctor_id,
                            a.user_id,
                            a.appointment_date,
                            a.status,
                            d.fullname as doctor_name,
                            u.fullname as patient_name
                        FROM appointments a
                        LEFT JOIN doctors d ON a.doctor_id = d.id
                        LEFT JOIN users u ON a.user_id = u.id
                        LIMIT 20
                    ");
                    $allAppointments = $stmt->fetchAll();
                    
                    if (count($allAppointments) > 0) {
                        echo '<div class="status-box status-info">Found ' . count($allAppointments) . ' total appointment(s) in system</div>';
                        echo '<table>';
                        echo '<tr><th>ID</th><th>Doctor ID</th><th>Doctor Name</th><th>Patient ID</th><th>Patient Name</th><th>Date</th><th>Status</th></tr>';
                        foreach ($allAppointments as $apt) {
                            $highlight = ($apt['doctor_id'] == $doctor_id) ? 'style="background: #ffffcc;"' : '';
                            echo '<tr ' . $highlight . '>';
                            echo '<td>#' . $apt['id'] . '</td>';
                            echo '<td>' . $apt['doctor_id'] . '</td>';
                            echo '<td>' . htmlspecialchars($apt['doctor_name'] ?? 'NULL') . '</td>';
                            echo '<td>' . $apt['user_id'] . '</td>';
                            echo '<td>' . htmlspecialchars($apt['patient_name'] ?? 'NULL') . '</td>';
                            echo '<td>' . date('M d, Y', strtotime($apt['appointment_date'])) . '</td>';
                            echo '<td><span class="badge badge-' . $apt['status'] . '">' . ucfirst($apt['status']) . '</span></td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                        echo '<p style="margin-top: 10px;"><em>Rows highlighted in yellow are for your doctor ID (' . $doctor_id . ')</em></p>';
                    } else {
                        echo '<div class="status-box status-warning">⚠ No appointments found in entire system</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="status-box status-error">✗ Error: ' . $e->getMessage() . '</div>';
                }
                ?>
            </div>

            <?php
            // ==========================================
            // 9. CREATE TEST DATA
            // ==========================================
            ?>
            <div class="section">
                <h2>9. Create Test Data</h2>
                <?php
                // Check if we have users
                $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $appointmentCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
                $appointmentCount->execute([$doctor_id]);
                $myAppointments = $appointmentCount->fetchColumn();
                
                if ($userCount == 0) {
                    echo '<div class="status-box status-warning">⚠ No users found - Create a test patient first</div>';
                    echo '<p><strong>SQL Query to create a test patient:</strong></p>';
                    echo '<div class="code-block">';
                    echo htmlspecialchars("INSERT INTO users (fullname, email, phone, password, gender, created_at) VALUES 
('John Doe', 'john@example.com', '1234567890', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'male', NOW());");
                    echo '</div>';
                    echo '<p><em>Note: Default password is "password"</em></p>';
                }
                
                if ($myAppointments == 0 && $userCount > 0) {
                    // Get first user ID
                    $firstUser = $pdo->query("SELECT id FROM users LIMIT 1")->fetch();
                    $userId = $firstUser['id'];
                    
                    echo '<div class="status-box status-info">📝 Ready to create test appointments</div>';
                    echo '<p><strong>SQL Query to create test appointments for your doctor:</strong></p>';
                    echo '<div class="code-block">';
                    echo htmlspecialchars("INSERT INTO appointments (user_id, doctor_id, schedule_id, appointment_date, appointment_time, reason, status, notes, created_at) VALUES 
($userId, $doctor_id, NULL, '2025-12-05', '10:00:00', 'Regular Checkup', 'pending', 'First visit', NOW()),
($userId, $doctor_id, NULL, '2025-12-06', '14:30:00', 'Follow-up Consultation', 'approved', 'Second visit', NOW()),
($userId, $doctor_id, NULL, '2025-12-07', '09:00:00', 'General Consultation', 'completed', 'Completed visit', NOW()),
($userId, $doctor_id, NULL, '2025-12-10', '11:00:00', 'Lab Results Review', 'pending', 'Check results', NOW());");
                    echo '</div>';
                    echo '<p><strong>Instructions:</strong></p>';
                    echo '<ol>';
                    echo '<li>Copy the SQL query above</li>';
                    echo '<li>Go to phpMyAdmin → SQL tab</li>';
                    echo '<li>Paste and execute the query</li>';
                    echo '<li>Refresh this page to verify</li>';
                    echo '</ol>';
                } elseif ($myAppointments > 0) {
                    echo '<div class="status-box status-success">✓ You already have ' . $myAppointments . ' appointment(s)</div>';
                }
                ?>
            </div>

            <?php
            // ==========================================
            // 10. RECOMMENDATIONS
            // ==========================================
            ?>
            <div class="section">
                <h2>10. Recommendations</h2>
                <?php
                $issues = [];
                
                if (!isset($_SESSION['doctor_id'])) {
                    $issues[] = '❌ <strong>Critical:</strong> Doctor not logged in - Login required';
                }
                
                if ($userCount == 0) {
                    $issues[] = '⚠️ <strong>Warning:</strong> No patients in database - Create test users';
                }
                
                if ($myAppointments == 0) {
                    $issues[] = '⚠️ <strong>Warning:</strong> No appointments for this doctor - Create test appointments';
                }
                
                if (empty($issues)) {
                    echo '<div class="status-box status-success">✅ Everything looks good! Your appointments should display correctly.</div>';
                    echo '<a href="appointments.php" class="btn">Go to Appointments Page</a>';
                } else {
                    echo '<div class="status-box status-error">Issues found that need attention:</div>';
                    echo '<ul style="margin-left: 20px; margin-top: 10px;">';
                    foreach ($issues as $issue) {
                        echo '<li style="margin-bottom: 10px;">' . $issue . '</li>';
                    }
                    echo '</ul>';
                }
                ?>
            </div>

        <?php endif; ?>

        <div class="section">
            <h2>Debug Complete</h2>
            <p>Use the information above to troubleshoot your appointments system.</p>
            <a href="appointments.php" class="btn">Go to Appointments Page</a>
            <a href="dashboard.php" class="btn" style="margin-left: 10px;">Go to Dashboard</a>
        </div>
    </div>
</body>
</html>
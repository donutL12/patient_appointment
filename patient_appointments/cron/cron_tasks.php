<?php
/**
 * Cron Tasks - Database Maintenance
 * Replaces stored procedures and events for shared hosting
 * Place in: patient_appointments/cron/cron_tasks.php
 */

// Prevent direct browser access without secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = 'your_secret_key_change_this'; // CHANGE THIS!
    if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
        http_response_code(403);
        die('Access denied');
    }
}

require_once __DIR__ . '/../config/db.php';

try {
    echo "=== Starting Maintenance Tasks ===\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    // 1. Cleanup expired verification tokens
    $stmt = $pdo->query("
        UPDATE users 
        SET verification_token = NULL, token_expiry = NULL 
        WHERE token_expiry < NOW() AND verification_token IS NOT NULL
    ");
    $count = $stmt->rowCount();
    echo "✓ Cleaned {$count} expired verification tokens\n";
    
    // 2. Cleanup expired reset tokens
    $stmt = $pdo->query("
        UPDATE users 
        SET reset_token = NULL, reset_token_expiry = NULL 
        WHERE reset_token_expiry < NOW() AND reset_token IS NOT NULL
    ");
    $count = $stmt->rowCount();
    echo "✓ Cleaned {$count} expired reset tokens\n";
    
    // 3. Clear expired account locks
    $stmt = $pdo->query("
        UPDATE users 
        SET account_locked_until = NULL, failed_login_attempts = 0 
        WHERE account_locked_until < NOW() AND account_locked_until IS NOT NULL
    ");
    $count = $stmt->rowCount();
    echo "✓ Unlocked {$count} accounts\n";
    
    // 4. Mark patients offline (5 minutes inactive)
    $stmt = $pdo->query("
        UPDATE users 
        SET is_online = 0 
        WHERE is_online = 1 
        AND last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $count = $stmt->rowCount();
    echo "✓ Marked {$count} patients offline\n";
    
    // 5. Mark doctors offline (5 minutes inactive)
    $stmt = $pdo->query("
        UPDATE doctors 
        SET is_online = 0 
        WHERE is_online = 1 
        AND last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $count = $stmt->rowCount();
    echo "✓ Marked {$count} doctors offline\n";
    
    // 6. Update online_status table
    $stmt = $pdo->query("
        UPDATE online_status 
        SET is_online = 0 
        WHERE is_online = 1 
        AND last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $count = $stmt->rowCount();
    echo "✓ Updated {$count} online_status records\n";
    
    // 7. Clean old email logs (90 days)
    $stmt = $pdo->query("
        DELETE FROM email_logs 
        WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $count = $stmt->rowCount();
    echo "✓ Deleted {$count} old email logs\n";
    
    // 8. Mark missed calls (over 30 seconds with no answer)
    $stmt = $pdo->query("
        UPDATE calls 
        SET status = 'missed', ended_at = NOW()
        WHERE status IN ('initiated', 'ringing') 
        AND TIMESTAMPDIFF(SECOND, created_at, NOW()) > 30
    ");
    $count = $stmt->rowCount();
    echo "✓ Marked {$count} calls as missed\n";
    
    echo "\n=== All Maintenance Tasks Completed Successfully! ===\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Cron Tasks Error: " . $e->getMessage());
    exit(1);
}
?>
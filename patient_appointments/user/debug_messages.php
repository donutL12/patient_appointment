<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("ERROR: User not logged in. Session data: " . print_r($_SESSION, true));
}

$user_id = $_SESSION['user_id'];

echo "<h2>Debug Information</h2>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<p><strong>User Name:</strong> " . ($_SESSION['user_name'] ?? 'N/A') . "</p>";

// Get all conversations
try {
    echo "<h3>Testing Conversation Query...</h3>";
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            d.id as doctor_id,
            d.fullname as doctor_name,
            d.specialization,
            d.profile_photo,
            d.status as doctor_status,
            (
                SELECT message 
                FROM messages 
                WHERE (sender_id = ? AND receiver_id = d.id AND sender_type = 'patient' AND receiver_type = 'doctor')
                   OR (sender_id = d.id AND receiver_id = ? AND sender_type = 'doctor' AND receiver_type = 'patient')
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at 
                FROM messages 
                WHERE (sender_id = ? AND receiver_id = d.id AND sender_type = 'patient' AND receiver_type = 'doctor')
                   OR (sender_id = d.id AND receiver_id = ? AND sender_type = 'doctor' AND receiver_type = 'patient')
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*) 
                FROM messages 
                WHERE sender_id = d.id 
                AND receiver_id = ? 
                AND sender_type = 'doctor' 
                AND receiver_type = 'patient'
                AND is_read = 0
            ) as unread_count
        FROM doctors d
        WHERE EXISTS (
            SELECT 1 FROM messages m
            WHERE (m.sender_id = ? AND m.receiver_id = d.id AND m.sender_type = 'patient' AND m.receiver_type = 'doctor')
               OR (m.sender_id = d.id AND m.receiver_id = ? AND m.sender_type = 'doctor' AND m.receiver_type = 'patient')
        )
        ORDER BY last_message_time DESC
    ");
    
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll();
    
    echo "<p><strong>Conversations Found:</strong> " . count($conversations) . "</p>";
    
    if (count($conversations) > 0) {
        echo "<h4>Conversation Details:</h4>";
        echo "<pre>";
        print_r($conversations);
        echo "</pre>";
    }
    
    // Get total unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_unread
        FROM messages
        WHERE receiver_id = ?
        AND receiver_type = 'patient'
        AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $total_unread = $stmt->fetch()['total_unread'];
    
    echo "<p><strong>Total Unread Messages:</strong> $total_unread</p>";
    
    // Check all messages for this user
    echo "<h3>All Messages (Sent & Received):</h3>";
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND sender_type = 'patient')
           OR (receiver_id = ? AND receiver_type = 'patient')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $all_messages = $stmt->fetchAll();
    
    echo "<p><strong>Total Messages:</strong> " . count($all_messages) . "</p>";
    
    if (count($all_messages) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>
                <th>ID</th>
                <th>Sender ID</th>
                <th>Sender Type</th>
                <th>Receiver ID</th>
                <th>Receiver Type</th>
                <th>Message</th>
                <th>Read</th>
                <th>Created</th>
              </tr>";
        
        foreach ($all_messages as $msg) {
            echo "<tr>";
            echo "<td>" . $msg['id'] . "</td>";
            echo "<td>" . $msg['sender_id'] . "</td>";
            echo "<td>" . $msg['sender_type'] . "</td>";
            echo "<td>" . $msg['receiver_id'] . "</td>";
            echo "<td>" . $msg['receiver_type'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($msg['message'], 0, 50)) . "</td>";
            echo "<td>" . ($msg['is_read'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $msg['created_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p><em>No messages found in database.</em></p>";
        
        // Check if doctors exist
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM doctors");
        $doctor_count = $stmt->fetch()['count'];
        echo "<p><strong>Doctors in database:</strong> $doctor_count</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    error_log("Messages Debug Error: " . $e->getMessage());
}

echo "<hr>";
echo "<p><a href='messages.php'>Back to Messages</a></p>";
?>
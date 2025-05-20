<?php
// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Widget ID to test
$widgetId = '450a6c5bead35a8c3648a923a33da5a5';

// Get user by widget ID
$user = $db->fetch(
    "SELECT * FROM users WHERE widget_id = :widget_id", 
    ['widget_id' => $widgetId]
);

if (!$user) {
    die('User not found for widget ID: ' . $widgetId);
}

echo "Found user: {$user['name']} (ID: {$user['id']})<br>";

// Create visitor with direct SQL to avoid the "read" reserved word issue
echo "<h3>Creating test visitor:</h3>";
$visitorSql = "INSERT INTO visitors 
               (user_id, name, email, url, ip_address, user_agent, created_at, last_active) 
               VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";

$db->query($visitorSql, [
    $user['id'],
    'Test Visitor',
    'test@example.com',
    'https://test-page.com',
    $_SERVER['REMOTE_ADDR'],
    'Test Script'
]);

// Get the created visitor
$visitorRecord = $db->fetch(
    "SELECT * FROM visitors WHERE user_id = ? AND user_agent = ? ORDER BY created_at DESC LIMIT 1",
    [$user['id'], 'Test Script']
);

if (!$visitorRecord) {
    die("Failed to create visitor");
}

$visitorId = $visitorRecord['id'];
echo "Created visitor with ID: {$visitorId}<br>";

// Insert a test message with direct SQL
echo "<h3>Creating test message:</h3>";
$messageSql = "INSERT INTO messages 
               (user_id, visitor_id, widget_id, message, sender_type, `read`, created_at) 
               VALUES (?, ?, ?, ?, ?, ?, NOW())";

$db->query($messageSql, [
    $user['id'],
    $visitorId,
    $widgetId,
    'This is a test message from the diagnostic script',
    'visitor',
    0  // Not read
]);

// Get the created message
$messageRecord = $db->fetch(
    "SELECT * FROM messages WHERE user_id = ? AND visitor_id = ? ORDER BY created_at DESC LIMIT 1",
    [$user['id'], $visitorId]
);

$messageId = $messageRecord ? $messageRecord['id'] : 0;
echo "Created message with ID: {$messageId}<br>";

// Insert a test reply
$replySql = "INSERT INTO messages 
             (user_id, visitor_id, widget_id, message, sender_type, `read`, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())";

$db->query($replySql, [
    $user['id'],
    $visitorId,
    $widgetId,
    'This is an auto-reply from the system',
    'agent',
    1  // Read
]);

// Get the created reply
$replyRecord = $db->fetch(
    "SELECT * FROM messages WHERE user_id = ? AND visitor_id = ? AND sender_type = 'agent' ORDER BY created_at DESC LIMIT 1",
    [$user['id'], $visitorId]
);

$replyId = $replyRecord ? $replyRecord['id'] : 0;
echo "Created reply with ID: {$replyId}<br>";

// Verify the messages were inserted
$messages = $db->fetchAll(
    "SELECT * FROM messages WHERE user_id = ? AND visitor_id = ? ORDER BY created_at DESC",
    [$user['id'], $visitorId]
);

echo "<h3>Recent Messages:</h3>";
echo "<pre>";
print_r($messages);
echo "</pre>";

echo "<p>Done! Now check your admin panel to see if these messages appear.</p>";
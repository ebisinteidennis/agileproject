<?php
// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Test data - use your actual widget ID
$testData = [
    'widget_id' => '450a6c5bead35a8c3648a923a33da5a5',
    'message' => 'Test message from diagnostic script ' . date('Y-m-d H:i:s'),
    'visitor_id' => '',  // Leave empty to create a new visitor
    'url' => 'https://test-site.com',
    'user_agent' => 'Diagnostic Test Script'
];

// Simulate the API call
$response = file_get_contents(
    SITE_URL . '/widget/api.php?action=send_message',
    false,
    stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($testData)
        ]
    ])
);

// Display result
echo "<h3>API Test Results:</h3>";
echo "<pre>";
echo "Request data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";
echo "Response: " . $response;
echo "</pre>";

// Try to check if the message was saved
try {
    $db = new Database();
    $recentMessage = $db->fetch(
        "SELECT * FROM messages WHERE message LIKE ? ORDER BY created_at DESC LIMIT 1",
        ['%Test message from diagnostic script%']
    );
    
    echo "<h3>Database Check:</h3>";
    echo "<pre>";
    echo $recentMessage ? "Message found in database:\n" . print_r($recentMessage, true) 
                       : "No matching message found in database.";
    echo "</pre>";
} catch (Exception $e) {
    echo "<h3>Database Error:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
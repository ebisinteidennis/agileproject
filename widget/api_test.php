<?php
// Simple API test script
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

echo "<h1>LiveSupport Widget API Test</h1>";

// Test Database Connection
echo "<h2>1. Testing Database Connection</h2>";
try {
    $db = new Database();
    $result = $db->fetch("SELECT 1 as test");
    echo "<p style='color:green'>✅ Database connection successful!</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Check Messages Table Structure
echo "<h2>2. Checking Messages Table Structure</h2>";
try {
    $columns = $db->fetchAll("SHOW COLUMNS FROM messages");
    echo "<p>Found " . count($columns) . " columns in messages table:</p>";
    echo "<ul>";
    $hasWidgetIdColumn = false;
    foreach ($columns as $column) {
        echo "<li>" . $column['Field'] . " (" . $column['Type'] . ")</li>";
        if ($column['Field'] === 'widget_id') {
            $hasWidgetIdColumn = true;
        }
    }
    echo "</ul>";
    
    if ($hasWidgetIdColumn) {
        echo "<p style='color:green'>✅ widget_id column exists in messages table!</p>";
    } else {
        echo "<p style='color:red'>❌ widget_id column missing from messages table!</p>";
        echo "<p>To add the column, run this SQL:<br><code>ALTER TABLE messages ADD COLUMN widget_id VARCHAR(32) DEFAULT NULL;</code></p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error checking table structure: " . $e->getMessage() . "</p>";
}

// Test Creating a Visitor
echo "<h2>3. Testing Visitor Creation</h2>";
try {
    $visitorData = [
        'name' => 'Test Visitor',
        'email' => 'test@example.com',
        'url' => 'https://test-site.com',
        'user_agent' => 'API Test Script'
    ];
    
    // Get a valid user ID for testing
    $testUser = $db->fetch("SELECT id FROM users WHERE widget_id = '450a6c5bead35a8c3648a923a33da5a5'");
    if ($testUser) {
        $userId = $testUser['id'];
        echo "<p>Using user ID: " . $userId . " for test</p>";
        
        // Create visitor
        $visitorId = $db->insert('visitors', [
            'user_id' => $userId,
            'name' => $visitorData['name'],
            'email' => $visitorData['email'],
            'url' => $visitorData['url'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $visitorData['user_agent']
        ]);
        
        if ($visitorId) {
            echo "<p style='color:green'>✅ Visitor created successfully with ID: " . $visitorId . "</p>";
            
            // Test message insertion
            echo "<h2>4. Testing Message Insertion</h2>";
            try {
                $messageData = [
                    'user_id' => $userId,
                    'visitor_id' => $visitorId,
                    'message' => 'Test message from API diagnostic script',
                    'sender_type' => 'visitor',
                    'read' => 0
                ];
                
                // Add widget_id if the column exists
                if ($hasWidgetIdColumn) {
                    $messageData['widget_id'] = '450a6c5bead35a8c3648a923a33da5a5';
                }
                
                $messageId = $db->insert('messages', $messageData);
                
                if ($messageId) {
                    echo "<p style='color:green'>✅ Message inserted successfully with ID: " . $messageId . "</p>";
                } else {
                    echo "<p style='color:red'>❌ Failed to insert message</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color:red'>❌ Error inserting message: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color:red'>❌ Failed to create visitor</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Could not find test user with widget ID: 450a6c5bead35a8c3648a923a33da5a5</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error creating visitor: " . $e->getMessage() . "</p>";
}

// Additional info
echo "<h2>5. Error Logs Location</h2>";
echo "<p>Check server error logs for more details. PHP errors should be logged to: <code>/logs/widget_api_" . date('Y-m-d') . ".log</code></p>";
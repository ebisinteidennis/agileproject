<?php
// Widget Messaging Test Script
// Save this as widget_message_test.php

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Initialize database connection
$db = new Database();

// Create a log function for this test
function testLog($message, $data = null) {
    echo "<div style='margin: 5px 0; padding: 5px; border-bottom: 1px solid #eee;'>";
    echo "<strong>" . htmlspecialchars($message) . "</strong>";
    
    if ($data !== null) {
        echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
    }
    
    echo "</div>";
}

// Start output
echo "<!DOCTYPE html>
<html>
<head>
    <title>LiveSupport Widget Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .widget-test { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>LiveSupport Widget Messaging Test</h1>";

// Test 1: Check database connection
try {
    $testQuery = $db->fetch("SELECT 1 as test");
    if ($testQuery && isset($testQuery['test']) && $testQuery['test'] == 1) {
        testLog("✅ Database connection successful!");
    } else {
        testLog("❌ Database connection error: Query did not return expected result");
    }
} catch (Exception $e) {
    testLog("❌ Database connection error: " . $e->getMessage());
}

// Test 2: Get all widgets
try {
    $widgets = $db->fetchAll("SELECT widget_id, name FROM users WHERE widget_id IS NOT NULL AND widget_id != ''");
    
    if (count($widgets) > 0) {
        testLog("✅ Found " . count($widgets) . " widgets in the system", $widgets);
    } else {
        testLog("⚠️ No widgets found in the system");
    }
} catch (Exception $e) {
    testLog("❌ Error fetching widgets: " . $e->getMessage());
}

// Test 3: Send test message to each widget and verify
echo "<h2>Widget Tests</h2>";

if (!empty($widgets)) {
    foreach ($widgets as $index => $widget) {
        $widgetId = $widget['widget_id'];
        $widgetName = $widget['name'] ?? 'Unknown';
        
        echo "<div class='widget-test'>";
        echo "<h3>Testing Widget #" . ($index + 1) . ": " . htmlspecialchars($widgetName) . "</h3>";
        echo "<p>Widget ID: " . htmlspecialchars($widgetId) . "</p>";
        
        // Generate test message
        $testMessageContent = "Test message for widget: " . $widgetId . " - Time: " . date('Y-m-d H:i:s');
        
        // Test data
        $testData = [
            'widget_id' => $widgetId,
            'message' => $testMessageContent,
            'visitor_id' => '',  // Empty to create a new visitor
            'url' => 'https://widget-test.com',
            'user_agent' => 'Widget Test Script'
        ];
        
        testLog("Sending test message:", $testData);
        
        // Capture the original error reporting setting
        $originalErrorReporting = error_reporting();
        
        // Temporarily turn off error reporting for the API call
        error_reporting(0);
        
        // Simulate the API call
        $apiUrl = SITE_URL . '/widget/api.php?action=send_message';
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($testData),
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($apiUrl, false, $context);
        
        // Restore error reporting
        error_reporting($originalErrorReporting);
        
        // Check if request was successful
        if ($response === false) {
            testLog("❌ API request failed: " . error_get_last()['message']);
        } else {
            $responseData = json_decode($response, true);
            testLog("API Response:", $responseData);
            
            // Verify the message was stored
            try {
                // Use backticks for 'read' column since it's a reserved word
                $messageSaved = $db->fetch(
                    "SELECT id, user_id, visitor_id, message, sender_type, `read`, created_at 
                     FROM messages 
                     WHERE message = ? AND widget_id = ? 
                     ORDER BY created_at DESC LIMIT 1",
                    [$testMessageContent, $widgetId]
                );
                
                if ($messageSaved) {
                    testLog("✅ Message successfully saved in database:", $messageSaved);
                    
                    // Check if the visitor was created
                    $visitorId = $messageSaved['visitor_id'];
                    $visitorInfo = $db->fetch(
                        "SELECT * FROM visitors WHERE id = ?",
                        [$visitorId]
                    );
                    
                    if ($visitorInfo) {
                        testLog("✅ Visitor record exists:", $visitorInfo);
                    } else {
                        testLog("⚠️ Visitor record not found for ID: " . $visitorId);
                    }
                } else {
                    testLog("❌ Message not found in database after API call");
                }
            } catch (Exception $e) {
                testLog("❌ Database error when verifying message: " . $e->getMessage());
            }
        }
        
        echo "</div>";
    }
} else {
    echo "<p class='error'>No widgets to test</p>";
}

// Test 4: Additional Database Verification
echo "<h2>Database Structure Verification</h2>";

// Check if messages table has the proper structure
try {
    $messageColumns = $db->fetchAll("DESCRIBE messages");
    testLog("Messages Table Structure:", $messageColumns);
    
    // Look for the read column and check if it's properly defined
    $readColumnFound = false;
    foreach ($messageColumns as $column) {
        if ($column['Field'] === 'read') {
            $readColumnFound = true;
            testLog("'read' column found with type: " . $column['Type']);
            break;
        }
    }
    
    if (!$readColumnFound) {
        testLog("❌ 'read' column not found in messages table");
    }
    
    // Check if visitors table has the proper structure
    $visitorColumns = $db->fetchAll("DESCRIBE visitors");
    testLog("Visitors Table Structure:", $visitorColumns);
    
    // Check for the id column and its type
    $idColumnFound = false;
    foreach ($visitorColumns as $column) {
        if ($column['Field'] === 'id') {
            $idColumnFound = true;
            testLog("'id' column found with type: " . $column['Type']);
            break;
        }
    }
    
    if (!$idColumnFound) {
        testLog("❌ 'id' column not found in visitors table");
    }
    
} catch (Exception $e) {
    testLog("❌ Error checking database structure: " . $e->getMessage());
}

echo "</body></html>";
?>
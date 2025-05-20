<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get all request data
$requestInfo = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
    'get_params' => $_GET,
    'post_params' => $_POST,
    'raw_body' => file_get_contents('php://input')
];

// Try to parse JSON body
$jsonBody = null;
try {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
} catch (Exception $e) {
    $jsonBody = ['error' => $e->getMessage()];
}

// Test database connection
$dbConnection = [
    'status' => 'unknown'
];

try {
    $testQuery = "SELECT 1 as test";
    $testResult = $db->fetch($testQuery);
    $dbConnection['status'] = isset($testResult['test']) ? 'connected' : 'error';
} catch (Exception $e) {
    $dbConnection['status'] = 'error';
    $dbConnection['error'] = $e->getMessage();
}

// Check widget_id from request
$widgetId = '';
if (isset($_GET['widget_id'])) {
    $widgetId = $_GET['widget_id'];
} elseif ($jsonBody && isset($jsonBody['widget_id'])) {
    $widgetId = $jsonBody['widget_id'];
}

// Test widget_id
$widgetInfo = [
    'widget_id' => $widgetId,
    'valid' => false
];

if ($widgetId) {
    try {
        $user = $db->fetch("SELECT id, name, email FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $widgetId]
        );
        
        if ($user) {
            $widgetInfo['valid'] = true;
            $widgetInfo['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ];
        }
    } catch (Exception $e) {
        $widgetInfo['error'] = $e->getMessage();
    }
}

// Check messages table
$messagesTable = [
    'status' => 'unknown'
];

try {
    // Check if table exists
    $tableCheck = $db->fetch("SHOW TABLES LIKE 'messages'");
    $messagesTable['exists'] = !empty($tableCheck);
    
    if ($messagesTable['exists']) {
        // Check columns
        $columns = $db->fetchAll("SHOW COLUMNS FROM messages");
        $messagesTable['columns'] = array_map(function($col) {
            return $col['Field'];
        }, $columns);
        
        // Check for widget_id column
        $messagesTable['has_widget_id'] = in_array('widget_id', $messagesTable['columns']);
        
        // Count messages
        $messagesCount = $db->fetch("SELECT COUNT(*) as count FROM messages");
        $messagesTable['count'] = $messagesCount['count'];
        
        // Recent messages
        if ($widgetInfo['valid']) {
            $recentMessages = $db->fetchAll(
                "SELECT * FROM messages WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5",
                ['user_id' => $widgetInfo['user']['id']]
            );
            
            $messagesTable['recent_messages'] = array_map(function($msg) {
                return [
                    'id' => $msg['id'],
                    'message' => substr($msg['message'], 0, 50) . (strlen($msg['message']) > 50 ? '...' : ''),
                    'sender_type' => $msg['sender_type'],
                    'created_at' => $msg['created_at'],
                    'widget_id' => $msg['widget_id'] ?? null
                ];
            }, $recentMessages);
        }
    }
} catch (Exception $e) {
    $messagesTable['error'] = $e->getMessage();
}

// Return diagnostic information
echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'request' => $requestInfo,
    'json_body' => $jsonBody,
    'database_connection' => $dbConnection,
    'widget_info' => $widgetInfo,
    'messages_table' => $messagesTable,
    'server_info' => [
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ]
], JSON_PRETTY_PRINT);
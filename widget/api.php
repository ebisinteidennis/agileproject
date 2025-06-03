<?php
/**
 * LiveSupport Widget API - Simplified Version
 */

// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// CORS headers - CRITICAL for cross-site messaging
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get action
$action = $_GET['action'] ?? '';

// Process actions
try {
    switch ($action) {
        case 'get_config':
            handleGetConfig();
            break;
            
        case 'send_message':
            handleSendMessage();
            break;
            
        case 'register_visitor':
            handleRegisterVisitor();
            break;
            
        case 'get_messages':
            handleGetMessages();
            break;
            
        default:
            sendResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage());
}

/**
 * Get widget configuration
 */
function handleGetConfig() {
    global $db;
    
    $widgetId = $_GET['widget_id'] ?? '';
    
    if (empty($widgetId)) {
        sendResponse(false, 'Widget ID required');
    }
    
    // Verify widget exists
    $user = $db->fetch("SELECT id FROM users WHERE widget_id = ?", [$widgetId]);
    
    if (!$user) {
        sendResponse(false, 'Invalid widget ID');
    }
    
    // Return config
    sendResponse(true, null, [
        'config' => [
            'primaryColor' => '#4a6cf7',
            'position' => 'bottom-right',
            'siteUrl' => 'https://agileproject.site'
        ]
    ]);
}

/**
 * Send message
 */
function handleSendMessage() {
    global $db;
    
    // Get data from request
    $data = array_merge($_POST, $_GET);
    if (empty($data['widget_id']) || empty($data['message'])) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) $data = array_merge($data, $json);
    }
    
    $widgetId = $data['widget_id'] ?? '';
    $visitorId = $data['visitor_id'] ?? '';
    $message = $data['message'] ?? '';
    
    if (empty($widgetId) || empty($message)) {
        sendResponse(false, 'Widget ID and message required');
    }
    
    // Get user
    $user = $db->fetch("SELECT id FROM users WHERE widget_id = ?", [$widgetId]);
    
    if (!$user) {
        sendResponse(false, 'Invalid widget ID');
    }
    
    // Create or update visitor
    if (empty($visitorId)) {
        $visitorId = 'visitor_' . time() . '_' . rand(1000, 9999);
    }
    
    // Check if visitor exists
    $visitor = $db->fetch("SELECT id FROM visitors WHERE id = ? AND user_id = ?", [$visitorId, $user['id']]);
    
    if (!$visitor) {
        // Create visitor
        $db->query(
            "INSERT INTO visitors (id, user_id, ip_address, user_agent, url, created_at, last_active) 
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $visitorId,
                $user['id'],
                $_SERVER['REMOTE_ADDR'] ?? '',
                $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
                $data['url'] ?? ''
            ]
        );
    } else {
        // Update last active
        $db->query("UPDATE visitors SET last_active = NOW() WHERE id = ?", [$visitorId]);
    }
    
    // Insert message
    $db->query(
        "INSERT INTO messages (user_id, visitor_id, widget_id, message, sender_type, `read`, created_at) 
         VALUES (?, ?, ?, ?, 'visitor', 0, NOW())",
        [$user['id'], $visitorId, $widgetId, $message]
    );
    
    $messageId = $db->lastInsertId();
    
    sendResponse(true, null, [
        'message_id' => $messageId,
        'visitor_id' => $visitorId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get messages
 */
function handleGetMessages() {
    global $db;
    
    $widgetId = $_GET['widget_id'] ?? '';
    $visitorId = $_GET['visitor_id'] ?? '';
    
    if (empty($widgetId) || empty($visitorId)) {
        sendResponse(false, 'Widget ID and visitor ID required');
    }
    
    // Get user
    $user = $db->fetch("SELECT id FROM users WHERE widget_id = ?", [$widgetId]);
    
    if (!$user) {
        sendResponse(false, 'Invalid widget ID');
    }
    
    // Get all messages for this visitor
    $messages = $db->fetchAll(
        "SELECT id, message, sender_type, created_at 
         FROM messages 
         WHERE user_id = ? AND visitor_id = ?
         ORDER BY created_at ASC",
        [$user['id'], $visitorId]
    );
    
    sendResponse(true, null, ['messages' => $messages ?: []]);
}

/**
 * Register visitor
 */
function handleRegisterVisitor() {
    global $db;
    
    // Get data
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $widgetId = $data['widget_id'] ?? '';
    $visitorId = $data['visitor_id'] ?? '';
    
    if (empty($widgetId)) {
        sendResponse(false, 'Widget ID required');
    }
    
    // Get user
    $user = $db->fetch("SELECT id FROM users WHERE widget_id = ?", [$widgetId]);
    
    if (!$user) {
        sendResponse(false, 'Invalid widget ID');
    }
    
    // Generate visitor ID if not provided
    if (empty($visitorId)) {
        $visitorId = 'visitor_' . time() . '_' . rand(1000, 9999);
    }
    
    sendResponse(true, null, ['visitor_id' => $visitorId]);
}

/**
 * Send JSON response
 */
function sendResponse($success, $error = null, $data = []) {
    $response = ['success' => $success];
    
    if ($error) {
        $response['error'] = $error;
    }
    
    echo json_encode(array_merge($response, $data));
    exit;
}

// Add these functions if they don't exist in your includes/functions.php:

/**
 * Check if subscription is active (simplified - always returns true)
 */
if (!function_exists('isSubscriptionActive')) {
    function isSubscriptionActive($user) {
        return true; // Simplified - remove subscription checks
    }
}

/**
 * Check if can send message (simplified - always returns true)
 */
if (!function_exists('canSendMessage')) {
    function canSendMessage($userId, $widgetId) {
        return true; // Simplified - remove message limits
    }
}

/**
 * Check if can create visitor (simplified - always returns true)
 */
if (!function_exists('canCreateVisitor')) {
    function canCreateVisitor($userId) {
        return true; // Simplified - remove visitor limits
    }
}
?>
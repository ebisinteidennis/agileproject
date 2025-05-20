// Real-Time Enhancements for check_messages.php

<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Enable CORS for cross-domain requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Ensure user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get parameters
$userId = $_SESSION['user_id'];
$visitorId = isset($_GET['visitor']) ? intval($_GET['visitor']) : 0;
$since = isset($_GET['since']) ? intval($_GET['since']) / 1000 : 0; // Convert from milliseconds to seconds
$widgetId = isset($_GET['widget_id']) ? $_GET['widget_id'] : null;

// Validate parameters
if (!$visitorId || !$since) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Get user's widget_id if not provided
if (!$widgetId) {
    $user = getUserById($userId);
    $widgetId = $user['widget_id'] ?? null;
}

// Enable logging
$logDebug = true;
function debugLog($message, $data = null) {
    global $logDebug;
    if (!$logDebug) return;
    
    $logFile = __DIR__ . '/../logs/realtime_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    
    if ($data !== null) {
        $logEntry .= ": " . print_r($data, true);
    }
    
    // Ensure log directory exists
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND);
}

debugLog('Check messages request', [
    'user_id' => $userId,
    'visitor_id' => $visitorId,
    'since' => date('Y-m-d H:i:s', $since),
    'widget_id' => $widgetId
]);

try {
    // Format the timestamp for database comparison
    $sinceDate = date('Y-m-d H:i:s', $since);
    
    // Get new messages using prepared statements
    $messagesQuery = "SELECT * FROM messages 
                    WHERE user_id = :user_id AND visitor_id = :visitor_id 
                    AND created_at > :since
                    ORDER BY created_at ASC";
    
    $params = [
        'user_id' => $userId,
        'visitor_id' => $visitorId,
        'since' => $sinceDate
    ];
    
    // Add widget_id to query if available
    if ($widgetId) {
        $messagesQuery = "SELECT * FROM messages 
                        WHERE user_id = :user_id AND visitor_id = :visitor_id 
                        AND widget_id = :widget_id
                        AND created_at > :since
                        ORDER BY created_at ASC";
        $params['widget_id'] = $widgetId;
    }
    
    $messages = $db->fetchAll($messagesQuery, $params);
    
    // Mark messages as read if they're from the visitor
    if (!empty($messages)) {
        $markReadQuery = "UPDATE messages SET `read` = 1 
                        WHERE user_id = :user_id AND visitor_id = :visitor_id 
                        AND sender_type = 'visitor' AND created_at > :since";
        
        $markReadParams = [
            'user_id' => $userId,
            'visitor_id' => $visitorId,
            'since' => $sinceDate
        ];
        
        // Add widget_id to query if available
        if ($widgetId) {
            $markReadQuery = "UPDATE messages SET `read` = 1 
                            WHERE user_id = :user_id AND visitor_id = :visitor_id 
                            AND widget_id = :widget_id
                            AND sender_type = 'visitor' AND created_at > :since";
            $markReadParams['widget_id'] = $widgetId;
        }
        
        $db->query($markReadQuery, $markReadParams);
        
        debugLog('Marked messages as read', [
            'count' => count($messages),
            'visitor_id' => $visitorId
        ]);
    }
    
    // Get total unread count for header stats
    $unreadQuery = "SELECT COUNT(*) as count FROM messages 
                  WHERE user_id = :user_id AND sender_type = 'visitor' AND `read` = 0";
    
    $unreadParams = ['user_id' => $userId];
    
    // Add widget_id to query if available
    if ($widgetId) {
        $unreadQuery = "SELECT COUNT(*) as count FROM messages 
                      WHERE user_id = :user_id AND sender_type = 'visitor' 
                      AND widget_id = :widget_id AND `read` = 0";
        $unreadParams['widget_id'] = $widgetId;
    }
    
    $unreadCount = $db->fetch($unreadQuery, $unreadParams);
    
    // Get visitor activity status
    $visitorInfo = $db->fetch(
        "SELECT last_active FROM visitors WHERE id = :visitor_id AND user_id = :user_id",
        ['visitor_id' => $visitorId, 'user_id' => $userId]
    );
    
    $isVisitorActive = false;
    $lastActiveTime = null;
    
    if ($visitorInfo && isset($visitorInfo['last_active'])) {
        $lastActiveTime = strtotime($visitorInfo['last_active']);
        $now = time();
        $isVisitorActive = ($now - $lastActiveTime) < 300; // Active within last 5 minutes
    }
    
    // Return the data with additional realtime info
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'unread_count' => $unreadCount['count'] ?? 0,
        'visitor_status' => [
            'is_active' => $isVisitorActive,
            'last_active' => $lastActiveTime ? date('Y-m-d H:i:s', $lastActiveTime) : null
        ],
        'server_time' => date('Y-m-d H:i:s')
    ]);
    
    debugLog('Returning messages', [
        'count' => count($messages),
        'unread_count' => $unreadCount['count'] ?? 0
    ]);
    
} catch (Exception $e) {
    debugLog('Error in check_messages', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
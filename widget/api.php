<?php
/**
 * LiveSupport Widget API - Enhanced Real-time Version
 * 
 * This file handles real-time messaging functionality for the LiveSupport widget
 */

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Log file for debugging
$logFile = __DIR__ . '/../logs/widget_api_' . date('Y-m-d') . '.log';

// Create log directory if it doesn't exist
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

// Function to log messages for debugging
function logMessage($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    
    if ($data !== null) {
        $logEntry .= ": " . print_r($data, true);
    }
    
    file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND);
}

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log the request for debugging
logMessage('API Request', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'get' => $_GET,
    'post' => $_POST,
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input')
]);

// Check action directly from request body for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_GET['action'])) {
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    if ($data && isset($data['action'])) {
        $_GET['action'] = $data['action'];
        logMessage('Action extracted from POST body', ['action' => $data['action']]);
    }
}

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Process action
try {
    switch ($action) {
        case 'get_config':
            getWidgetConfig();
            break;
            
        case 'send_message':
            sendMessage();
            break;
            
        case 'register_visitor':
            registerVisitor();
            break;
            
        case 'get_messages':
            getMessages();
            break;
            
        case 'mark_as_read':
            markMessagesAsRead();
            break;
            
        case 'update_activity':
            updateVisitorActivity();
            break;
            
        default:
            sendErrorResponse('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    logMessage('Critical error in API: ' . $e->getMessage(), $e->getTraceAsString());
    sendErrorResponse('Server error: ' . $e->getMessage());
}

/**
 * Update visitor activity status
 */
function updateVisitorActivity() {
    global $db;
    
    // Get request body
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    // Validate required fields
    if (empty($data['widget_id']) || empty($data['visitor_id'])) {
        sendErrorResponse('Widget ID and visitor ID are required');
        return;
    }
    
    try {
        logMessage('Updating visitor activity', [
            'visitor_id' => $data['visitor_id'],
            'widget_id' => $data['widget_id'],
            'activity' => $data['activity'] ?? 'unknown'
        ]);
        
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $data['widget_id']]
        );
        
        if (!$user) {
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        // Update visitor's last activity
        $db->query(
            "UPDATE visitors SET last_active = NOW() WHERE id = ? AND user_id = ?",
            [$data['visitor_id'], $user['id']]
        );
        
        // Log the activity
        $activityType = isset($data['activity']) ? $data['activity'] : 'ping';
        $url = isset($data['url']) ? $data['url'] : null;
        
        // Optional: Store visitor activity history in a separate table
        // This is useful for analytics and detailed visitor tracking
        if (tableExists($db, 'visitor_activities')) {
            $db->query(
                "INSERT INTO visitor_activities (visitor_id, user_id, activity_type, url, created_at) 
                VALUES (?, ?, ?, ?, NOW())",
                [$data['visitor_id'], $user['id'], $activityType, $url]
            );
        }
        
        // Return success response
        echo json_encode([
            'success' => true
        ]);
        
    } catch (Exception $e) {
        logMessage('Error updating visitor activity: ' . $e->getMessage(), $e->getTraceAsString());
        sendErrorResponse('Error updating visitor activity: ' . $e->getMessage());
    }
}

/**
 * Mark messages as read
 */
function markMessagesAsRead() {
    global $db;
    
    // Get request body
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    // Validate required fields
    if (empty($data['widget_id']) || empty($data['visitor_id']) || empty($data['message_ids'])) {
        sendErrorResponse('Widget ID, visitor ID, and message IDs are required');
        return;
    }
    
    try {
        logMessage('Marking messages as read', [
            'visitor_id' => $data['visitor_id'],
            'widget_id' => $data['widget_id'],
            'message_ids' => $data['message_ids']
        ]);
        
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $data['widget_id']]
        );
        
        if (!$user) {
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        // Convert message IDs to array if not already
        $messageIds = is_array($data['message_ids']) ? $data['message_ids'] : [$data['message_ids']];
        
        // Update read status for each message
        foreach ($messageIds as $messageId) {
            $db->query(
                "UPDATE messages SET `read` = 1 
                WHERE id = ? AND visitor_id = ? AND sender_type = 'agent'",
                [$messageId, $data['visitor_id']]
            );
        }
        
        // Return success response
        echo json_encode([
            'success' => true
        ]);
        
    } catch (Exception $e) {
        logMessage('Error marking messages as read: ' . $e->getMessage(), $e->getTraceAsString());
        sendErrorResponse('Error marking messages as read: ' . $e->getMessage());
    }
}

/**
 * Get widget configuration
 */
function getWidgetConfig() {
    global $db;
    
    // Get widget ID from request
    $widgetId = isset($_GET['widget_id']) ? $_GET['widget_id'] : '';
    
    if (empty($widgetId)) {
        sendErrorResponse('Widget ID is required');
        return;
    }
    
    try {
        logMessage('Getting config for widget_id: ' . $widgetId);
        
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $widgetId]
        );
        
        if (!$user) {
            logMessage('Invalid widget ID - no user found', ['widget_id' => $widgetId]);
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        logMessage('Found user for widget_id', ['user_id' => $user['id'], 'name' => $user['name'] ?? 'Unknown']);
        
        // Check if user has an active subscription
        $isSubscriptionActive = isSubscriptionActive($user);
        
        // Check if user is online
        $isUserOnline = isUserOnline($user['id']);
        
        // Default configuration
        $config = [
            'theme' => 'light',
            'position' => 'bottom-right',
            'primaryColor' => '#4a6cf7',
            'autoOpen' => false,
            'greetingMessage' => 'Hi there! How can we help you today?',
            'offlineMessage' => 'We\'re currently offline. Leave a message and we\'ll get back to you soon.',
            'showBranding' => !$isSubscriptionActive,
            'userOnline' => $isUserOnline
        ];
        
        // If user has a custom configuration, use it
        if ($isSubscriptionActive) {
            $settings = $db->fetch(
                "SELECT * FROM widget_settings WHERE user_id = :user_id", 
                ['user_id' => $user['id']]
            );
            
            if ($settings) {
                $config = [
                    'theme' => $settings['theme'] ?? 'light',
                    'position' => $settings['position'] ?? 'bottom-right',
                    'primaryColor' => $settings['primary_color'] ?? '#4a6cf7',
                    'autoOpen' => (bool)($settings['auto_open'] ?? false),
                    'greetingMessage' => $settings['greeting_message'] ?? 'Hi there! How can we help you today?',
                    'offlineMessage' => $settings['offline_message'] ?? 'We\'re currently offline. Leave a message and we\'ll get back to you soon.',
                    'showBranding' => false,
                    'userOnline' => $isUserOnline
                ];
            }
        }
        
        logMessage('Sending widget config', $config);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
        
    } catch (Exception $e) {
        logMessage('Error getting config: ' . $e->getMessage(), $e->getTraceAsString());
        sendErrorResponse('Error retrieving widget configuration: ' . $e->getMessage());
    }
}

/**
 * Send a message from visitor to agent
 */
function sendMessage() {
    global $db;
    
    // Get request body
    $requestBody = file_get_contents('php://input');
    logMessage('Raw request body', $requestBody);
    
    // Try to decode JSON
    $data = json_decode($requestBody, true);
    
    // Check for JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage('JSON decode error', json_last_error_msg());
        
        // Try to get data from POST
        if (!empty($_POST)) {
            $data = $_POST;
            logMessage('Using POST data instead', $data);
        } else {
            sendErrorResponse('Invalid request data: ' . json_last_error_msg());
            return;
        }
    }
    
    // Log the parsed data
    logMessage('Parsed request data', $data);
    
    // Validate required fields
    if (empty($data['widget_id']) || empty($data['message'])) {
        logMessage('Missing required fields', ['widget_id' => $data['widget_id'] ?? 'empty', 'message' => $data['message'] ?? 'empty']);
        sendErrorResponse('Widget ID and message are required');
        return;
    }
    
    try {
        logMessage('Processing message for widget_id: ' . $data['widget_id'], [
            'message' => $data['message'],
            'url' => $data['url'] ?? 'Not provided',
        ]);
        
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $data['widget_id']]
        );
        
       if (!$user) {
            logMessage('User not found for widget_id', ['widget_id' => $data['widget_id']]);
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        logMessage('Found user', ['user_id' => $user['id'], 'name' => $user['name'] ?? 'Unknown']);
        
        // Get or create visitor
        $visitorId = getOrCreateVisitor($user['id'], $data);
        
        if (!$visitorId) {
            sendErrorResponse('Failed to create visitor');
            return;
        }
        
        logMessage('Using visitor', ['visitor_id' => $visitorId]);
        
        // Insert message with widget_id using direct SQL to handle 'read' reserved word
        $messageSql = "INSERT INTO messages 
                      (user_id, visitor_id, widget_id, message, sender_type, `read`, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $db->query($messageSql, [
            $user['id'],
            $visitorId,
            $data['widget_id'],
            $data['message'],
            'visitor',
            0  // Not read
        ]);
        
        // Get the inserted message ID and created_at timestamp
        $messageRecord = $db->fetch(
            "SELECT id, created_at FROM messages WHERE user_id = ? AND visitor_id = ? ORDER BY created_at DESC LIMIT 1",
            [$user['id'], $visitorId]
        );
        
        $messageId = $messageRecord ? $messageRecord['id'] : 0;
        $createdAt = $messageRecord ? $messageRecord['created_at'] : date('Y-m-d H:i:s');
        
        if (!$messageId) {
            logMessage('Failed to insert message into database');
            sendErrorResponse('Database error: Failed to save message');
            return;
        }
        
        logMessage('Message saved to database', ['message_id' => $messageId]);
        
        // Check if user is online
        $isUserOnline = isUserOnline($user['id']);
        
        // Generate auto-reply if user is offline
        $reply = null;
        $replyId = null;
        
        if (!$isUserOnline) {
            // Get settings for offline message
            $settings = $db->fetch(
                "SELECT offline_message FROM widget_settings WHERE user_id = :user_id", 
                ['user_id' => $user['id']]
            );
            
            $offlineMessage = $settings && !empty($settings['offline_message']) 
                ? $settings['offline_message'] 
                : "Thanks for your message! We're currently offline, but we'll get back to you as soon as possible.";
            
            $reply = $offlineMessage;
            
            // Insert auto-reply with widget_id using direct SQL
            $replySql = "INSERT INTO messages 
                        (user_id, visitor_id, widget_id, message, sender_type, `read`, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $db->query($replySql, [
                $user['id'],
                $visitorId,
                $data['widget_id'],
                $reply,
                'agent',
                0  // Not read initially
            ]);
            
            // Get auto-reply message ID
            $replyRecord = $db->fetch(
                "SELECT id, created_at FROM messages WHERE user_id = ? AND visitor_id = ? ORDER BY created_at DESC LIMIT 1",
                [$user['id'], $visitorId]
            );
            
            $replyId = $replyRecord ? $replyRecord['id'] : null;
            $replyCreatedAt = $replyRecord ? $replyRecord['created_at'] : null;
        }
        
        // Update visitor's last activity
        $db->query(
            "UPDATE visitors SET last_active = NOW() WHERE id = ?",
            [$visitorId]
        );
        
        // Return success response
        $response = [
            'success' => true,
            'message_id' => $messageId,
            'created_at' => $createdAt,
            'reply' => $reply,
            'reply_id' => $replyId
        ];
        
        logMessage('Sending success response', $response);
        echo json_encode($response);
        
    } catch (Exception $e) {
        logMessage('Error processing message: ' . $e->getMessage(), $e->getTraceAsString());
        sendErrorResponse('Error sending message: ' . $e->getMessage());
    }
}

/**
 * Get visitor information
 */
function getVisitorInfo($visitorId) {
    global $db;
    
    try {
        $visitor = $db->fetch(
            "SELECT * FROM visitors WHERE id = ?",
            [$visitorId]
        );
        
        if (!$visitor) {
            return null;
        }
        
        // Return basic visitor info
        return [
            'id' => $visitor['id'],
            'name' => $visitor['name'] ?? 'Anonymous Visitor',
            'email' => $visitor['email'] ?? null,
            'url' => $visitor['url'] ?? null,
            'last_active' => $visitor['last_active'] ?? null
        ];
    } catch (Exception $e) {
        logMessage('Error getting visitor info: ' . $e->getMessage());
        return null;
    }
}

/**
 * Register a visitor
 */
function registerVisitor() {
    global $db;
    
    // Get request body
    $requestBody = file_get_contents('php://input');
    logMessage('Raw request body for visitor registration', $requestBody);
    
    // Try to decode JSON
    $data = json_decode($requestBody, true);
    
    // Check for JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage('JSON decode error in visitor registration', json_last_error_msg());
        
        // Try to get data from POST
        if (!empty($_POST)) {
            $data = $_POST;
            logMessage('Using POST data instead for visitor registration', $data);
        } else {
            sendErrorResponse('Invalid request data: ' . json_last_error_msg());
            return;
        }
    }
    
    // Validate required fields
    if (empty($data['widget_id'])) {
        sendErrorResponse('Widget ID is required');
        return;
    }
    
    try {
        logMessage('Registering visitor for widget_id: ' . $data['widget_id'], [
            'url' => $data['url'] ?? 'Not provided',
            'user_agent' => $data['user_agent'] ?? 'Not provided'
        ]);
        
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $data['widget_id']]
        );
        
        if (!$user) {
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        logMessage('Found user for visitor registration', ['user_id' => $user['id']]);
        
        // Create visitor
        $visitorId = getOrCreateVisitor($user['id'], $data);
        
        if (!$visitorId) {
            sendErrorResponse('Failed to create visitor');
            return;
        }
        
        logMessage('Visitor registered/updated successfully', ['visitor_id' => $visitorId]);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'visitor_id' => $visitorId
        ]);
        
    } catch (Exception $e) {
        logMessage('Error registering visitor: ' . $e->getMessage(), $e->getTraceAsString());
        sendErrorResponse('Error registering visitor: ' . $e->getMessage());
    }
}

/**
 * Get messages for a visitor
 */
function getMessages() {
    global $db;
    
    // Get request parameters
    $widgetId = isset($_GET['widget_id']) ? $_GET['widget_id'] : '';
    $visitorId = isset($_GET['visitor_id']) ? $_GET['visitor_id'] : '';
    $since = isset($_GET['since']) ? $_GET['since'] : null;
    
    if (empty($widgetId) || empty($visitorId)) {
        sendErrorResponse('Widget ID and visitor ID are required');
        return;
    }
    
    try {
        logMessage('Getting messages for widget_id: ' . $widgetId . ', visitor_id: ' . $visitorId . 
                   ($since ? ', since: ' . date('Y-m-d H:i:s', $since/1000) : ''));
        
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $widgetId]
        );
        
        if (!$user) {
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        // Build query
        $query = "SELECT * FROM messages 
                 WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id = :widget_id";
        $params = [
            'user_id' => $user['id'],
            'visitor_id' => $visitorId,
            'widget_id' => $widgetId
        ];
        
        // Add since filter if provided
        if ($since) {
            $query .= " AND created_at > :since";
            $params['since'] = date('Y-m-d H:i:s', intval($since) / 1000);
        }
        
        $query .= " ORDER BY created_at ASC";
        
        // Get messages
        $messages = $db->fetchAll($query, $params);
        
        // Check if agent is currently typing to this visitor
        $agentTyping = false;
        
        // Check for any recent agent activity for this visitor
        if (tableExists($db, 'agent_typing')) {
            $typingRecord = $db->fetch(
                "SELECT * FROM agent_typing 
                WHERE user_id = :user_id AND visitor_id = :visitor_id 
                AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)",
                ['user_id' => $user['id'], 'visitor_id' => $visitorId]
            );
            
            $agentTyping = !empty($typingRecord);
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'messages' => $messages ?: [],
            'agent_typing' => $agentTyping,
            'agent_online' => isUserOnline($user['id'])
        ]);
        
    } catch (Exception $e) {
        logMessage('Error retrieving messages: ' . $e->getMessage(), $e->getTraceAsString());
        sendErrorResponse('Error retrieving messages: ' . $e->getMessage());
    }
}

/**
 * Get or create a visitor
 */
function getOrCreateVisitor($userId, $data) {
    global $db;
    
    $url = isset($data['url']) ? $data['url'] : null;
    $userAgent = isset($data['user_agent']) ? $data['user_agent'] : null;
    $visitorId = isset($data['visitor_id']) ? $data['visitor_id'] : null;
    
    // If visitor ID is provided, try to find that visitor first
    if ($visitorId) {
        logMessage('Looking for visitor by ID', ['visitor_id' => $visitorId]);
        
        $visitor = $db->fetch(
            "SELECT * FROM visitors 
            WHERE id = :visitor_id AND user_id = :user_id",
            [
                'visitor_id' => $visitorId,
                'user_id' => $userId
            ]
        );
        
        if ($visitor) {
            logMessage('Found existing visitor by ID', ['visitor_id' => $visitor['id']]);
            
            // Update visitor
            $db->query(
                "UPDATE visitors 
                SET last_active = NOW(), url = ? 
                WHERE id = ?",
                [$url, $visitor['id']]
            );
            
            return $visitor['id'];
        }
    }
    
    // If visitor ID not found or not provided, try to find by IP
    $ip = getClientIP();
    
    logMessage('Looking for existing visitor by IP', [
        'user_id' => $userId,
        'ip' => $ip,
        'user_agent' => substr($userAgent ?? '', 0, 30) . '...'  // Truncate for log readability
    ]);
    
    $visitor = $db->fetch(
        "SELECT * FROM visitors 
        WHERE user_id = :user_id AND ip_address = :ip 
        ORDER BY last_active DESC 
        LIMIT 1",
        [
            'user_id' => $userId,
            'ip' => $ip
        ]
    );
    
    if ($visitor) {
        logMessage('Found existing visitor by IP', ['visitor_id' => $visitor['id']]);
        
        // Update visitor
        $db->query(
            "UPDATE visitors 
            SET last_active = NOW(), url = ? 
            WHERE id = ?",
            [$url, $visitor['id']]
        );
        
        return $visitor['id'];
    }
    
    logMessage('Creating new visitor', [
        'user_id' => $userId,
        'ip' => $ip,
        'url' => $url
    ]);
    
    // Create new visitor using query to avoid issues with table schema
    $db->query(
        "INSERT INTO visitors (user_id, ip_address, user_agent, url, created_at, last_active) 
        VALUES (?, ?, ?, ?, NOW(), NOW())",
        [
            $userId,
            $ip,
            $userAgent,
            $url
        ]
    );
    
    // Get the created visitor
    $newVisitor = $db->fetch(
        "SELECT id FROM visitors 
        WHERE user_id = ? AND ip_address = ? 
        ORDER BY created_at DESC 
        LIMIT 1",
        [$userId, $ip]
    );
    
    if (!$newVisitor) {
        logMessage('Failed to create visitor record');
        return null;
    }
    
    $newVisitorId = $newVisitor['id'];
    logMessage('Created new visitor', ['visitor_id' => $newVisitorId]);
    
    return $newVisitorId;
}

/**
 * Check if user is online
 */
function isUserOnline($userId) {
    global $db;
    
    $lastActivity = $db->fetch(
        "SELECT last_activity FROM users WHERE id = :user_id",
        ['user_id' => $userId]
    );
    
    if (!$lastActivity || empty($lastActivity['last_activity'])) {
        return false;
    }
    
    // Consider user online if activity was within the last 5 minutes
    $lastActivityTime = strtotime($lastActivity['last_activity']);
    $now = time();
    
    $isOnline = ($now - $lastActivityTime) < 300; // 5 minutes = 300 seconds
    logMessage('Checking if user is online', [
        'user_id' => $userId,
        'is_online' => $isOnline ? 'Yes' : 'No',
        'last_activity' => date('Y-m-d H:i:s', $lastActivityTime),
        'time_ago' => ($now - $lastActivityTime) . ' seconds'
    ]);
    
    return $isOnline;
}

/**
 * Check if a database table exists
 */
function tableExists($db, $tableName) {
    try {
        $result = $db->fetch("SHOW TABLES LIKE ?", [$tableName]);
        return !empty($result);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get client IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Send error response
 */
function sendErrorResponse($message) {
    logMessage('Error response: ' . $message);
    
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}
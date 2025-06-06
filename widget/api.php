<?php
/**
 * LiveSupport Widget API - Simplified Version
 */

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Allow requests from any origin (CRITICAL for cross-site messaging)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Set Content-Type header for JSON responses
header('Content-Type: application/json');

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

// Get action from request (either GET parameter or POST body)
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
            
        default:
            sendErrorResponse('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    logMessage('Critical error in API: ' . $e->getMessage(), $e->getTraceAsString());
    sendErrorResponse('Server error: ' . $e->getMessage());
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
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $widgetId]
        );
        
        if (!$user) {
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        // Get site URL from settings
        $siteSettings = $db->fetch("SELECT * FROM settings WHERE id = 1");
        $siteUrl = $siteSettings && isset($siteSettings['site_name']) ? 
            'https://agileproject.site' : 
            'https://agileproject.site';
        
        // Default configuration
        $config = [
            'theme' => 'light',
            'position' => 'bottom-right',
            'primaryColor' => '#4a6cf7',
            'autoOpen' => false,
            'greetingMessage' => 'Hi there! How can we help you today?',
            'offlineMessage' => 'We\'re currently offline. Leave a message and we\'ll get back to you soon.',
            'showBranding' => true,
            'siteUrl' => $siteUrl
        ];
        
        // Return success response
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
        
    } catch (Exception $e) {
        sendErrorResponse('Error retrieving widget configuration: ' . $e->getMessage());
    }
}

/**
 * Send a message from visitor to agent with simplified approach
 */
function sendMessage() {
    global $db;
    
    // Get data from all possible sources
    $data = [];
    
    // Check POST data first
    if (!empty($_POST)) {
        $data = $_POST;
    } 
    // Then check JSON body
    else {
        $requestBody = file_get_contents('php://input');
        if (!empty($requestBody)) {
            $jsonData = json_decode($requestBody, true);
            if ($jsonData) {
                $data = $jsonData;
            }
        }
    }
    
    // Then check GET parameters as fallback
    if (empty($data)) {
        $data = $_GET;
    }
    
    // Validate required fields
    if (empty($data['widget_id']) || empty($data['message'])) {
        sendErrorResponse('Widget ID and message are required');
        return;
    }
    
    try {
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $data['widget_id']]
        );
        
        if (!$user) {
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        // Get or create visitor with simplified approach
        $visitorId = createOrGetVisitor($user['id'], $data);
        
        if (!$visitorId) {
            sendErrorResponse('Failed to create visitor');
            return;
        }
        
        // Insert message into database with backticks for reserved words
        try {
            $sql = "INSERT INTO messages (user_id, visitor_id, widget_id, message, sender_type, `read`, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $db->query($sql, [
                $user['id'],
                $visitorId,
                $data['widget_id'],
                $data['message'],
                'visitor',
                0  // Not read
            ]);
            
            $messageId = $db->lastInsertId();
            
            if (!$messageId) {
                // Fallback to querying for the message
                $messageRecord = $db->fetch(
                    "SELECT id, created_at FROM messages 
                     WHERE user_id = ? AND visitor_id = ? AND message = ?
                     ORDER BY created_at DESC LIMIT 1",
                    [$user['id'], $visitorId, $data['message']]
                );
                
                $messageId = $messageRecord ? $messageRecord['id'] : 0;
                $createdAt = $messageRecord ? $messageRecord['created_at'] : date('Y-m-d H:i:s');
            } else {
                // Get created_at time
                $messageRecord = $db->fetch(
                    "SELECT created_at FROM messages WHERE id = ?",
                    [$messageId]
                );
                $createdAt = $messageRecord ? $messageRecord['created_at'] : date('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            throw new Exception('Failed to insert message: ' . $e->getMessage());
        }
        
        // Return success response
        $response = [
            'success' => true,
            'message_id' => $messageId,
            'created_at' => $createdAt,
            'visitor_id' => $visitorId
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        sendErrorResponse('Error sending message: ' . $e->getMessage());
    }
}

/**
 * Simplified function to create or get a visitor
 */
function createOrGetVisitor($userId, $data) {
    global $db;
    
    // If visitor ID is provided, try to use it first
    if (!empty($data['visitor_id'])) {
        $visitorId = $data['visitor_id'];
        
        // Check if this visitor exists
        $visitor = $db->fetch(
            "SELECT id FROM visitors WHERE id = ? AND user_id = ?",
            [$visitorId, $userId]
        );
        
        if ($visitor) {
            // Update last activity
            $db->query(
                "UPDATE visitors SET last_active = NOW() WHERE id = ?",
                [$visitorId]
            );
            
            return $visitorId;
        }
    }
    
    // Create a new visitor
    $ip = getClientIP();
    $url = isset($data['url']) ? $data['url'] : '';
    $userAgent = isset($data['user_agent']) ? $data['user_agent'] : '';
    
    try {
        // Generate a new numeric ID
        $newVisitorId = rand(1, 999999999);
        
        // Insert visitor with direct SQL
        $db->query(
            "INSERT INTO visitors (id, user_id, ip_address, user_agent, url, created_at, last_active) 
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $newVisitorId,
                $userId,
                $ip,
                $userAgent,
                $url
            ]
        );
        
        return $newVisitorId;
    } catch (Exception $e) {
        // Try one more time with a different ID
        try {
            $newVisitorId = rand(1000000000, 1999999999);
            
            $db->query(
                "INSERT INTO visitors (id, user_id, ip_address, user_agent, url, created_at, last_active) 
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $newVisitorId,
                    $userId,
                    $ip,
                    $userAgent,
                    $url
                ]
            );
            
            return $newVisitorId;
        } catch (Exception $e2) {
            return null;
        }
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
                 WHERE user_id = ? AND visitor_id = ?";
        $params = [$user['id'], $visitorId];
        
        // Add since filter if provided
        if ($since) {
            $query .= " AND created_at > ?";
            $params[] = date('Y-m-d H:i:s', intval($since) / 1000);
        }
        
        $query .= " ORDER BY created_at ASC";
        
        // Get messages
        $messages = $db->fetchAll($query, $params);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'messages' => $messages ?: []
        ]);
        
    } catch (Exception $e) {
        sendErrorResponse('Error retrieving messages: ' . $e->getMessage());
    }
}

/**
 * Register a visitor
 */
function registerVisitor() {
    global $db;
    
    // Get data from all possible sources
    $data = [];
    
    // Check POST data first
    if (!empty($_POST)) {
        $data = $_POST;
    } 
    // Then check JSON body
    else {
        $requestBody = file_get_contents('php://input');
        if (!empty($requestBody)) {
            $jsonData = json_decode($requestBody, true);
            if ($jsonData) {
                $data = $jsonData;
            }
        }
    }
    
    // Validate required fields
    if (empty($data['widget_id'])) {
        sendErrorResponse('Widget ID is required');
        return;
    }
    
    try {
        // Get user by widget ID
        $user = $db->fetch(
            "SELECT * FROM users WHERE widget_id = :widget_id", 
            ['widget_id' => $data['widget_id']]
        );
        
        if (!$user) {
            sendErrorResponse('Invalid widget ID or user not found');
            return;
        }
        
        // Create visitor
        $visitorId = createOrGetVisitor($user['id'], $data);
        
        if (!$visitorId) {
            sendErrorResponse('Failed to create visitor');
            return;
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'visitor_id' => $visitorId
        ]);
        
    } catch (Exception $e) {
        sendErrorResponse('Error registering visitor: ' . $e->getMessage());
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
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}
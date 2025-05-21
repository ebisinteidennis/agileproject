<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

// Check if JSON request
if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request format']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Validate data
if (!isset($data['visitor_id']) || !isset($data['message']) || empty($data['message'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$userId = $_SESSION['user_id'];
$user = getUserById($userId);
$visitorId = $data['visitor_id'];
$message = sanitizeInput($data['message']);

// Get the widget_id from session (set in chat.php)
$widgetId = isset($_SESSION['current_visitor_widget_id']) ? $_SESSION['current_visitor_widget_id'] : null;

// Log for debugging
error_log("Sending message to visitor {$visitorId} using widget_id: " . ($widgetId ?? 'unknown'));

// Check subscription status
if (!isSubscriptionActive($user)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Your subscription is inactive']);
    exit;
}

// Check if visitor exists
$visitor = $db->fetch(
    "SELECT * FROM visitors WHERE id = :id AND user_id = :user_id",
    ['id' => $visitorId, 'user_id' => $userId]
);

if (!$visitor) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Visitor not found']);
    exit;
}

// Check message limit
if (!canSendMessage($userId)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message limit reached']);
    exit;
}

// Prepare message data
$messageData = [
    'user_id' => $userId,
    'visitor_id' => $visitorId,
    'message' => $message,
    'sender_type' => 'agent'
];

// Include widget_id if available
if ($widgetId) {
    $messageData['widget_id'] = $widgetId;
}

// Save message
$messageId = $db->insert('messages', $messageData);

if ($messageId) {
    // Update visitor's last activity
    $db->query(
        "UPDATE visitors SET last_active = NOW() WHERE id = :id",
        ['id' => $visitorId]
    );
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message_id' => $messageId]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to save message']);
}
exit;
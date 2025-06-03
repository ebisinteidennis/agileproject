<?php
/**
 * AJAX endpoint for checking new messages in real-time
 */

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$visitorId = isset($_GET['visitor_id']) ? intval($_GET['visitor_id']) : 0;
$lastId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if (!$visitorId) {
    echo json_encode(['success' => false, 'error' => 'Visitor ID required']);
    exit;
}

try {
    // Verify user owns this conversation
    $visitor = $db->fetch(
        "SELECT * FROM visitors WHERE id = ? AND user_id = ?",
        [$visitorId, $userId]
    );
    
    if (!$visitor) {
        echo json_encode(['success' => false, 'error' => 'Invalid visitor']);
        exit;
    }
    
    // Get new messages since last check
    $query = "SELECT * FROM messages 
              WHERE user_id = ? AND visitor_id = ? AND id > ?
              ORDER BY created_at ASC";
    
    $messages = $db->fetchAll($query, [$userId, $visitorId, $lastId]);
    
    // Mark visitor messages as read
    if (!empty($messages)) {
        $visitorMessages = array_filter($messages, function($msg) {
            return $msg['sender_type'] === 'visitor';
        });
        
        if (!empty($visitorMessages)) {
            $messageIds = array_column($visitorMessages, 'id');
            $placeholders = str_repeat('?,', count($messageIds) - 1) . '?';
            $db->query(
                "UPDATE messages SET `read` = 1 WHERE id IN ($placeholders)",
                $messageIds
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages ?: []
    ]);
    
} catch (Exception $e) {
    error_log("AJAX check messages error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
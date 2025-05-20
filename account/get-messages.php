<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

// Validate parameters
if (!isset($_GET['visitor_id']) || empty($_GET['visitor_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Visitor ID is required']);
    exit;
}

$userId = $_SESSION['user_id'];
$visitorId = $_GET['visitor_id'];
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

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

// Get new messages
$messages = $db->fetchAll(
    "SELECT * FROM messages WHERE user_id = :user_id AND visitor_id = :visitor_id AND id > :last_id ORDER BY created_at ASC",
    ['user_id' => $userId, 'visitor_id' => $visitorId, 'last_id' => $lastId]
);

// Mark messages as read
if (!empty($messages)) {
    $db->query(
        "UPDATE messages SET `read` = 1 WHERE user_id = :user_id AND visitor_id = :visitor_id AND sender_type = 'visitor' AND `read` = 0",
        ['user_id' => $userId, 'visitor_id' => $visitorId]
    );
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'messages' => $messages]);
exit;
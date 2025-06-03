<?php
/**
 * Secure file download handler for message attachments
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];
$messageId = isset($_GET['message_id']) ? intval($_GET['message_id']) : 0;

if (!$messageId) {
    http_response_code(400);
    die('Invalid request');
}

try {
    // Get message and verify ownership
    $message = $db->fetch(
        "SELECT m.*, v.user_id as visitor_user_id 
         FROM messages m 
         JOIN visitors v ON m.visitor_id = v.id 
         WHERE m.id = ? AND (m.user_id = ? OR v.user_id = ?)",
        [$messageId, $userId, $userId]
    );
    
    if (!$message) {
        http_response_code(404);
        die('File not found');
    }
    
    // Check if message has a file
    if (empty($message['file_path']) || empty($message['file_name'])) {
        http_response_code(404);
        die('No file attached to this message');
    }
    
    $filePath = '../' . $message['file_path'];
    
    // Check if file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File not found on server');
    }
    
    // Get file info
    $fileName = $message['file_name'];
    $fileSize = filesize($filePath);
    $mimeType = $message['file_type'] ?: 'application/octet-stream';
    
    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Output file
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    error_log("File download error: " . $e->getMessage());
    http_response_code(500);
    die('Server error');
}
?>
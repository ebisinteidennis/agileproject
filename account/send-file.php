<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Check subscription status
if (!isSubscriptionActive($user)) {
    echo json_encode(['success' => false, 'error' => 'Subscription is not active']);
    exit;
}

// Check if visitor ID is provided
if (!isset($_POST['visitor_id']) || empty($_POST['visitor_id'])) {
    echo json_encode(['success' => false, 'error' => 'Visitor ID is required']);
    exit;
}

$visitorId = $_POST['visitor_id'];

// Verify visitor belongs to user
$visitor = $db->fetch(
    "SELECT * FROM visitors WHERE id = :id AND user_id = :user_id",
    ['id' => $visitorId, 'user_id' => $userId]
);

if (!$visitor) {
    echo json_encode(['success' => false, 'error' => 'Invalid visitor']);
    exit;
}

// Check if file is uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

// Validate file
$file = $_FILES['file'];
$allowedTypes = [
    'image/jpeg', 
    'image/png', 
    'image/gif', 
    'image/webp',
    'application/pdf', 
    'application/msword', 
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain'
];

$maxSize = 5 * 1024 * 1024; // 5MB

// Check file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: images (jpg, png, gif, webp), PDF, DOC, DOCX, XLS, XLSX, TXT']);
    exit;
}

// Check file size
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size: 5MB']);
    exit;
}

// Create upload directory structure
$uploadBaseDir = dirname(__DIR__) . '/uploads/chat';
$yearMonth = date('Y/m');
$uploadDir = $uploadBaseDir . '/' . $yearMonth;

if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        exit;
    }
}

// Generate unique filename
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
$fileName = uniqid('chat_') . '_' . $safeFileName;
$filePath = $uploadDir . '/' . $fileName;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Create web-accessible path
$webPath = '/uploads/chat/' . $yearMonth . '/' . $fileName;

// Get widget ID from session or visitor's last message
$widgetId = $_SESSION['current_visitor_widget_id'] ?? null;

if (!$widgetId) {
    // Try to get widget ID from visitor's messages
    $lastMessage = $db->fetch(
        "SELECT widget_id FROM messages 
         WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id IS NOT NULL
         ORDER BY created_at DESC LIMIT 1",
        ['user_id' => $userId, 'visitor_id' => $visitorId]
    );
    
    $widgetId = $lastMessage ? $lastMessage['widget_id'] : null;
}

// Create message with file info
$messageText = '[File: ' . $file['name'] . ']';

try {
    // Insert message into database
    $sql = "INSERT INTO messages (user_id, visitor_id, widget_id, message, file_path, sender_type, `read`, created_at) 
            VALUES (:user_id, :visitor_id, :widget_id, :message, :file_path, :sender_type, :read, NOW())";
    
    $params = [
        'user_id' => $userId,
        'visitor_id' => $visitorId,
        'widget_id' => $widgetId,
        'message' => $messageText,
        'file_path' => $webPath,
        'sender_type' => 'agent',
        'read' => 0
    ];
    
    $db->query($sql, $params);
    
    $messageId = $db->lastInsertId();
    
    // Create thumbnail for images (optional)
    if (strpos($mimeType, 'image/') === 0 && $fileExt !== 'gif') {
        createThumbnail($filePath, $uploadDir . '/thumb_' . $fileName, 200, 200);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'file_path' => $webPath,
        'file_name' => $file['name'],
        'file_size' => $file['size'],
        'file_type' => $mimeType
    ]);
    
} catch (Exception $e) {
    // Delete uploaded file on database error
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Create thumbnail for images
 */
function createThumbnail($source, $destination, $maxWidth, $maxHeight) {
    try {
        list($width, $height, $type) = getimagesize($source);
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        
        // Create new image
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        // Load source image based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($source);
                // Preserve transparency
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($source);
                break;
            default:
                return false;
        }
        
        // Resize image
        imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save thumbnail
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumb, $destination, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumb, $destination, 8);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($thumb, $destination, 85);
                break;
        }
        
        // Clean up
        imagedestroy($thumb);
        imagedestroy($sourceImage);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
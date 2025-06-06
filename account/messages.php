<?php
$pageTitle = 'Messages';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];

// Get user info
try {
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
} catch (Exception $e) {
    error_log("Error getting user: " . $e->getMessage());
    $user = null;
}

// Get widget_id for this user
$widgetId = isset($user['widget_id']) ? $user['widget_id'] : null;

// Simple file upload check - assume allowed for now
$canUpload = true;

// Get subscription info if exists
$subscription = null;
if (isset($user['subscription_id']) && $user['subscription_id']) {
    try {
        $subscription = $db->fetch("SELECT * FROM subscriptions WHERE id = ?", [$user['subscription_id']]);
    } catch (Exception $e) {
        error_log("Error getting subscription: " . $e->getMessage());
    }
}

// Check if widget_id column exists in messages table
$hasWidgetIdColumn = false;
try {
    $columnsResult = $db->fetchAll("SHOW COLUMNS FROM messages LIKE 'widget_id'");
    $hasWidgetIdColumn = !empty($columnsResult);
} catch (Exception $e) {
    error_log("Error checking for widget_id column: " . $e->getMessage());
}

// Check if viewing a specific conversation
$visitorId = isset($_GET['visitor']) ? intval($_GET['visitor']) : null;

// Handle message search if provided
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle read/unread filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Handle pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get visitor-specific widget_id if viewing a conversation
$visitorWidgetId = null;
if ($visitorId) {
    // Get widget_id associated with this visitor's messages
    try {
        $visitorWidget = $db->fetch(
            "SELECT widget_id FROM messages 
             WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id IS NOT NULL
             ORDER BY created_at DESC 
             LIMIT 1",
            ['user_id' => $userId, 'visitor_id' => $visitorId]
        );
        
        $visitorWidgetId = $visitorWidget ? $visitorWidget['widget_id'] : null;
        
        // Store widget_id in session for use in other files
        $_SESSION['current_visitor_widget_id'] = $visitorWidgetId;
        
        // Mark messages as read if viewing a specific conversation
        if ($hasWidgetIdColumn && $visitorWidgetId) {
            $db->query(
                "UPDATE messages SET `read` = 1 
                WHERE user_id = ? AND visitor_id = ? AND sender_type = ? AND widget_id = ?", 
                [$userId, $visitorId, 'visitor', $visitorWidgetId]
            );
        } else {
            $db->query(
                "UPDATE messages SET `read` = 1 
                WHERE user_id = ? AND visitor_id = ? AND sender_type = ?", 
                [$userId, $visitorId, 'visitor']
            );
        }
    } catch (Exception $e) {
        error_log("Error processing visitor: " . $e->getMessage());
    }
}

// Build the base query for visitors with message count
$baseVisitorsQuery = "SELECT v.*, 
                     (SELECT COUNT(*) FROM messages m WHERE m.visitor_id = v.id AND m.user_id = :user_id";
                     
if ($hasWidgetIdColumn && $widgetId) {
    $baseVisitorsQuery .= " AND m.widget_id = :widget_id";
}

$baseVisitorsQuery .= ") as message_count,
                     (SELECT COUNT(*) FROM messages m WHERE m.visitor_id = v.id AND m.user_id = :user_id";

if ($hasWidgetIdColumn && $widgetId) {
    $baseVisitorsQuery .= " AND m.widget_id = :widget_id";
}

$baseVisitorsQuery .= " AND m.sender_type = 'visitor' AND m.`read` = 0) as unread_count,
                     (SELECT MAX(created_at) FROM messages m WHERE m.visitor_id = v.id AND m.user_id = :user_id";

if ($hasWidgetIdColumn && $widgetId) {
    $baseVisitorsQuery .= " AND m.widget_id = :widget_id";
}

$baseVisitorsQuery .= ") as last_message_date,
                     (SELECT message FROM messages m WHERE m.visitor_id = v.id AND m.user_id = :user_id";

if ($hasWidgetIdColumn && $widgetId) {
    $baseVisitorsQuery .= " AND m.widget_id = :widget_id";
}

$baseVisitorsQuery .= " ORDER BY created_at DESC LIMIT 1) as last_message
                     FROM visitors v 
                     WHERE v.user_id = :user_id";
                 
// Add search condition if provided
if (!empty($searchTerm)) {
    $baseVisitorsQuery .= " AND (v.name LIKE :search OR v.email LIKE :search)";
}

// Add having clause for filters
if ($filter === 'unread') {
    $baseVisitorsQuery .= " HAVING unread_count > 0";
} else if ($filter === 'read') {
    $baseVisitorsQuery .= " HAVING unread_count = 0 AND message_count > 0";
}

// ORDER BY clause
$baseVisitorsQuery .= " ORDER BY last_message_date DESC";

// Build the full query with LIMIT and OFFSET
$visitorsQuery = $baseVisitorsQuery . " LIMIT " . intval($limit) . " OFFSET " . intval($offset);

// Prepare parameters
$queryParams = ['user_id' => $userId];

if ($hasWidgetIdColumn && $widgetId) {
    $queryParams['widget_id'] = $widgetId;
}

if (!empty($searchTerm)) {
    $queryParams['search'] = '%' . $searchTerm . '%';
}

// Execute the query
try {
    $visitors = $db->fetchAll($visitorsQuery, $queryParams);
} catch (Exception $e) {
    error_log("Visitors query error: " . $e->getMessage());
    $visitors = [];
}

// Count total visitors for pagination
$countQuery = "SELECT COUNT(*) as total FROM visitors v 
              WHERE v.user_id = :user_id";
              
if (!empty($searchTerm)) {
    $countQuery .= " AND (v.name LIKE :search OR v.email LIKE :search)";
}

$countParams = ['user_id' => $userId];
if (!empty($searchTerm)) {
    $countParams['search'] = '%' . $searchTerm . '%';
}

try {
    $totalCount = $db->fetch($countQuery, $countParams)['total'];
} catch (Exception $e) {
    $totalCount = 0;
}

$totalPages = ceil($totalCount / $limit);

// Get conversation messages if a visitor is selected
$messages = [];
$visitorInfo = null;

if ($visitorId) {
    // Get visitor information
    try {
        $visitorInfo = $db->fetch(
            "SELECT * FROM visitors WHERE id = :visitor_id AND user_id = :user_id", 
            ['visitor_id' => $visitorId, 'user_id' => $userId]
        );
    } catch (Exception $e) {
        error_log("Visitor info query error: " . $e->getMessage());
    }
    
    // Get messages for this conversation
    if ($visitorInfo) {
        try {
            if ($hasWidgetIdColumn && $visitorWidgetId) {
                $messages = $db->fetchAll(
                    "SELECT * FROM messages 
                    WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id = :widget_id
                    ORDER BY created_at ASC", 
                    ['user_id' => $userId, 'visitor_id' => $visitorId, 'widget_id' => $visitorWidgetId]
                );
            } else {
                $messages = $db->fetchAll(
                    "SELECT * FROM messages 
                    WHERE user_id = :user_id AND visitor_id = :visitor_id 
                    ORDER BY created_at ASC", 
                    ['user_id' => $userId, 'visitor_id' => $visitorId]
                );
            }
        } catch (Exception $e) {
            error_log("Messages query error: " . $e->getMessage());
        }
    }
}

// Process message sending if form was submitted
$messageSent = false;
$messageError = null;
$fileUploaded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $visitorId) {
    $messageContent = trim($_POST['message']);
    $hasFiles = !empty($_FILES['files']['tmp_name'][0]);
    
    // Simple validation - ensure either message or file is provided
    if (empty($messageContent) && !$hasFiles) {
        $messageError = "Message or file is required.";
    } else {
        try {
            // Handle file uploads first
            $uploadedFiles = [];
            if ($hasFiles && $canUpload) {
                $uploadDir = '../uploads/messages';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
                    if (!empty($tmpName)) {
                        $file = [
                            'name' => $_FILES['files']['name'][$index],
                            'type' => $_FILES['files']['type'][$index],
                            'tmp_name' => $tmpName,
                            'size' => $_FILES['files']['size'][$index],
                            'error' => $_FILES['files']['error'][$index]
                        ];
                        
                        // Simple file upload
                        if ($file['error'] === UPLOAD_ERR_OK) {
                            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                            $filename = uniqid() . '_' . time() . '.' . $extension;
                            $filepath = $uploadDir . '/' . $filename;
                            
                            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                                $uploadedFiles[] = [
                                    'filename' => $filename,
                                    'filepath' => $filepath,
                                    'original_name' => $file['name'],
                                    'size' => $file['size'],
                                    'type' => $file['type']
                                ];
                            }
                        }
                    }
                }
            }
            
            // Send text message if provided
            if (!empty($messageContent)) {
                // Prepare message data
                $messageData = [
                    'user_id' => $userId,
                    'visitor_id' => $visitorId,
                    'message' => $messageContent,
                    'sender_type' => 'agent',
                    'read' => 0
                ];
                
                // Include widget_id if available
                if (isset($_SESSION['current_visitor_widget_id']) && $_SESSION['current_visitor_widget_id']) {
                    $messageData['widget_id'] = $_SESSION['current_visitor_widget_id'];
                } elseif ($visitorWidgetId) {
                    $messageData['widget_id'] = $visitorWidgetId;
                }
                
                // Insert message using the db->insert method
                $messageId = $db->insert('messages', $messageData);
                $messageSent = true;
            }
            
            // Send file messages
            foreach ($uploadedFiles as $file) {
                $messageData = [
                    'user_id' => $userId,
                    'visitor_id' => $visitorId,
                    'message' => 'File: ' . $file['original_name'],
                    'sender_type' => 'agent',
                    'read' => 0,
                    'file_path' => $file['filename'],
                    'file_name' => $file['original_name'],
                    'file_size' => round($file['size'] / 1024, 2) . ' KB',
                    'file_type' => $file['type']
                ];
                
                // Include widget_id if available
                if (isset($_SESSION['current_visitor_widget_id']) && $_SESSION['current_visitor_widget_id']) {
                    $messageData['widget_id'] = $_SESSION['current_visitor_widget_id'];
                } elseif ($visitorWidgetId) {
                    $messageData['widget_id'] = $visitorWidgetId;
                }
                
                $db->insert('messages', $messageData);
                $fileUploaded = true;
            }
            
            // Update visitor's last activity
            $db->query(
                "UPDATE visitors SET last_active = NOW() WHERE id = ?",
                [$visitorId]
            );
            
            // Reload messages
            if ($hasWidgetIdColumn && $visitorWidgetId) {
                $messages = $db->fetchAll(
                    "SELECT * FROM messages 
                    WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id = :widget_id
                    ORDER BY created_at ASC", 
                    ['user_id' => $userId, 'visitor_id' => $visitorId, 'widget_id' => $visitorWidgetId]
                );
            } else {
                $messages = $db->fetchAll(
                    "SELECT * FROM messages 
                    WHERE user_id = :user_id AND visitor_id = :visitor_id 
                    ORDER BY created_at ASC", 
                    ['user_id' => $userId, 'visitor_id' => $visitorId]
                );
            }
            
        } catch (Exception $e) {
            $messageError = "Failed to send message. Please try again.";
            error_log("Message sending error: " . $e->getMessage());
        }
    }
}

// Get message counts for header stats
try {
    if ($hasWidgetIdColumn && $widgetId) {
        $totalMessages = $db->fetch(
            "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND widget_id = :widget_id", 
            ['user_id' => $userId, 'widget_id' => $widgetId]
        );
    } else {
        $totalMessages = $db->fetch(
            "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id", 
            ['user_id' => $userId]
        );
    }
    $totalMessagesCount = isset($totalMessages['count']) ? $totalMessages['count'] : 0;
} catch (Exception $e) {
    $totalMessagesCount = 0;
}

try {
    if ($hasWidgetIdColumn && $widgetId) {
        $unreadCount = $db->fetch(
            "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND widget_id = :widget_id AND sender_type = 'visitor' AND `read` = 0", 
            ['user_id' => $userId, 'widget_id' => $widgetId]
        );
    } else {
        $unreadCount = $db->fetch(
            "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND sender_type = 'visitor' AND `read` = 0", 
            ['user_id' => $userId]
        );
    }
    $unreadMessagesCount = isset($unreadCount['count']) ? $unreadCount['count'] : 0;
} catch (Exception $e) {
    $unreadMessagesCount = 0;
}

// Get current usage stats
try {
    $currentVisitors = $db->fetch("SELECT COUNT(*) as count FROM visitors WHERE user_id = ?", [$userId]);
    $currentVisitors = $currentVisitors ? $currentVisitors['count'] : 0;
} catch (Exception $e) {
    $currentVisitors = 0;
}

try {
    $currentMessages = $db->fetch("SELECT COUNT(*) as count FROM messages WHERE user_id = ?", [$userId]);
    $currentMessages = $currentMessages ? $currentMessages['count'] : 0;
} catch (Exception $e) {
    $currentMessages = 0;
}

// Simple helper functions
function isImage($filename) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $imageExtensions);
}

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc' => 'fas fa-file-word text-primary',
        'docx' => 'fas fa-file-word text-primary',
        'xls' => 'fas fa-file-excel text-success',
        'xlsx' => 'fas fa-file-excel text-success',
        'ppt' => 'fas fa-file-powerpoint text-warning',
        'pptx' => 'fas fa-file-powerpoint text-warning',
        'txt' => 'fas fa-file-alt text-secondary',
        'zip' => 'fas fa-file-archive text-info',
        'rar' => 'fas fa-file-archive text-info',
        'jpg' => 'fas fa-file-image text-success',
        'jpeg' => 'fas fa-file-image text-success',
        'png' => 'fas fa-file-image text-success',
        'gif' => 'fas fa-file-image text-success',
        'webp' => 'fas fa-file-image text-success'
    ];
    
    return isset($icons[$extension]) ? $icons[$extension] : 'fas fa-file text-secondary';
}

// Include header
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #5e72e4;
            --primary-light: #8b98ff;
            --primary-dark: #4c63d2;
            --secondary-color: #8392ab;
            --success-color: #2dce89;
            --danger-color: #f5365c;
            --warning-color: #fb6340;
            --info-color: #11cdef;
            --light-color: #f8f9fe;
            --dark-color: #32325d;
            --white: #ffffff;
            --gray-100: #f6f9fc;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #cbd5e0;
            --gray-500: #adb5bd;
            --gray-600: #8898aa;
            --gray-700: #525f7f;
            --gray-800: #32325d;
            --gray-900: #212529;
            --message-sent: #5e72e4;
            --message-sent-text: #ffffff;
            --message-received: #f6f9fc;
            --message-received-text: #525f7f;
            --sidebar-bg: #ffffff;
            --border-color: #e9ecef;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            --border-radius: 0.375rem;
            --border-radius-lg: 0.5rem;
            --border-radius-xl: 1rem;
            --font-family-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family-sans);
            background-color: var(--gray-100);
            color: var(--gray-800);
            font-size: 0.9rem;
            line-height: 1.6;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .messages-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--white);
        }

        /* Header */
        .messages-header {
            background: var(--white);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            z-index: 1000;
        }

        .messages-header .navbar {
            padding: 1rem 1.5rem;
        }

        .messages-header .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--dark-color);
            letter-spacing: -0.025em;
        }

        .messages-header .navbar-brand i {
            color: var(--primary-color);
        }

        .stats-badge {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 0.375rem 0.875rem;
            border-radius: var(--border-radius-xl);
            font-size: 0.813rem;
            font-weight: 600;
            margin-left: 0.5rem;
            border: 1px solid var(--gray-200);
            letter-spacing: 0.025em;
        }

        .stats-badge i {
            color: var(--primary-color);
        }

        .stats-badge.warning {
            background: rgba(251, 99, 64, 0.1);
            color: var(--warning-color);
            border-color: var(--warning-color);
        }

        .stats-badge.danger {
            background: rgba(245, 54, 92, 0.1);
            color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .widget-info {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 0.75rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
        }

        /* Main Layout */
        .messages-layout {
            flex: 1;
            display: flex;
            overflow: hidden;
            background: var(--gray-100);
        }

        /* Sidebar */
        .conversations-sidebar {
            width: 380px;
            background: var(--white);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 6px -1px rgba(0, 0, 0, 0.07);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--white);
        }

        .sidebar-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
            letter-spacing: -0.025em;
        }

        .sidebar-title i {
            color: var(--primary-color);
        }

        .search-container {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--border-color);
        }

        .search-input {
            border-radius: var(--border-radius-xl);
            border: 2px solid transparent;
            padding: 0.625rem 1rem 0.625rem 2.75rem;
            background-color: var(--gray-100);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            background-color: var(--white);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(94, 114, 228, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1.125rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
        }

        .filter-tabs {
            background: var(--white);
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 0.5rem;
        }

        .filter-tab {
            border: 2px solid transparent;
            background: var(--gray-100);
            padding: 0.5rem 1.25rem;
            border-radius: var(--border-radius-xl);
            transition: all 0.2s ease;
            font-size: 0.813rem;
            font-weight: 600;
            color: var(--gray-600);
        }

        .filter-tab.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.25);
        }

        .filter-tab:hover:not(.active) {
            background: var(--white);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Conversations List */
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 1.125rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
            background: var(--white);
        }

        .conversation-item:hover {
            background: var(--gray-50);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }

        .conversation-item.active {
            background: linear-gradient(135deg, rgba(94, 114, 228, 0.1) 0%, rgba(94, 114, 228, 0.05) 100%);
            border-color: var(--primary-color);
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.15);
        }

        .conversation-item.unread {
            background: linear-gradient(135deg, rgba(94, 114, 228, 0.05) 0%, rgba(139, 152, 255, 0.05) 100%);
            border-left: 4px solid var(--primary-color);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: var(--border-radius-lg);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.125rem;
            margin-right: 1rem;
            position: relative;
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.25);
        }

        .online-indicator {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 14px;
            height: 14px;
            background: var(--success-color);
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(45, 206, 137, 0.25);
        }

        .conversation-details {
            flex: 1;
            min-width: 0;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .visitor-name {
            font-weight: 700;
            font-size: 0.9375rem;
            color: var(--gray-900);
            margin: 0;
            letter-spacing: -0.025em;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .last-message {
            font-size: 0.813rem;
            color: var(--gray-600);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }

        .conversation-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .visitor-email {
            font-size: 0.75rem;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .unread-badge {
            background: var(--primary-color);
            color: white;
            font-size: 0.688rem;
            font-weight: 700;
            padding: 0.125rem 0.5rem;
            border-radius: var(--border-radius-xl);
            min-width: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(94, 114, 228, 0.25);
        }

        /* Chat Area */
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--white);
            border-radius: var(--border-radius-lg) 0 0 var(--border-radius-lg);
            margin-left: -1px;
            box-shadow: -4px 0 6px -1px rgba(0, 0, 0, 0.07);
        }

        .chat-header {
            background: var(--white);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            border-radius: var(--border-radius-lg) 0 0 0;
        }

        .chat-user-info {
            display: flex;
            align-items: center;
        }

        .chat-avatar {
            width: 45px;
            height: 45px;
            border-radius: var(--border-radius-lg);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.125rem;
            margin-right: 1rem;
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.25);
        }

        .chat-user-details h5 {
            margin: 0;
            font-weight: 700;
            color: var(--gray-900);
            font-size: 1.0625rem;
            letter-spacing: -0.025em;
        }

        .chat-user-status {
            font-size: 0.813rem;
            color: var(--gray-500);
            margin: 0;
            font-weight: 500;
        }

        .online-status {
            color: var(--success-color);
            font-weight: 600;
        }

        .offline-status {
            color: var(--gray-500);
        }

        /* Messages Area */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: var(--gray-50);
        }

        .message-group {
            margin-bottom: 1.5rem;
        }

        .date-divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }

        .date-divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: var(--gray-200);
        }

        .date-divider span {
            background: var(--gray-50);
            padding: 0.375rem 1rem;
            border-radius: var(--border-radius-xl);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            position: relative;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .message {
            display: flex;
            margin-bottom: 1rem;
            max-width: 70%;
            animation: fadeInUp 0.3s ease-out;
        }

        .message.sent {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        .message.received {
            margin-right: auto;
        }

        .message-content {
            padding: 0.875rem 1.25rem;
            border-radius: 1.25rem;
            position: relative;
            max-width: 100%;
            word-wrap: break-word;
        }

        .message.sent .message-content {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: var(--message-sent-text);
            border-bottom-right-radius: 0.25rem;
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.25);
        }

        .message.received .message-content {
            background: var(--white);
            color: var(--message-received-text);
            border-bottom-left-radius: 0.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
        }

        .message-text {
            margin: 0;
            line-height: 1.5;
            font-size: 0.9375rem;
            font-weight: 500;
        }

        .message-time {
            font-size: 0.688rem;
            opacity: 0.8;
            margin-top: 0.375rem;
            font-weight: 500;
        }

        .message.sent .message-time {
            text-align: right;
            color: rgba(255, 255, 255, 0.8);
        }

        .message.received .message-time {
            text-align: left;
            color: var(--gray-500);
        }

        .message-status {
            margin-left: 0.25rem;
        }

        /* File Message Styles */
        .file-message {
            background: var(--white);
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-lg);
            padding: 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .file-message:hover {
            background: var(--gray-50);
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .file-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-100);
            border-radius: var(--border-radius-lg);
            margin-right: 1rem;
            font-size: 1.5rem;
        }

        .file-details h6 {
            margin: 0;
            font-weight: 700;
            color: var(--gray-900);
            font-size: 0.875rem;
        }

        .file-size {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .download-btn {
            margin-left: auto;
            padding: 0.5rem;
            border: none;
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(94, 114, 228, 0.25);
        }

        .download-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.35);
        }

        /* Image Preview */
        .image-message {
            max-width: 300px;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .image-message:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 12px -3px rgba(0, 0, 0, 0.15);
        }

        .image-message img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Chat Input */
        .chat-input-container {
            background: var(--white);
            border-top: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .chat-input-wrapper {
            display: flex;
            align-items: flex-end;
            gap: 0.75rem;
        }

        .message-input {
            flex: 1;
            border: 2px solid var(--gray-200);
            border-radius: 1.25rem;
            padding: 0.75rem 1.25rem;
            resize: none;
            max-height: 120px;
            font-size: 0.9375rem;
            font-weight: 500;
            transition: all 0.2s ease;
            background: var(--gray-50);
        }

        .message-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(94, 114, 228, 0.1);
            background: var(--white);
        }

        .input-actions {
            display: flex;
            gap: 0.5rem;
        }

        .file-upload-btn, .emoji-btn, .send-btn {
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1.125rem;
        }

        .file-upload-btn, .emoji-btn {
            background: var(--gray-100);
            color: var(--gray-600);
            border: 2px solid transparent;
        }

        .file-upload-btn:hover, .emoji-btn:hover {
            background: var(--white);
            color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .file-upload-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .send-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.25);
        }

        .send-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 8px -2px rgba(94, 114, 228, 0.35);
        }

        .send-btn:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            box-shadow: none;
        }

        /* File Upload Area */
        .file-upload-area {
            margin-top: 0.75rem;
            padding: 1.5rem;
            border: 2px dashed var(--gray-300);
            border-radius: var(--border-radius-lg);
            text-align: center;
            transition: all 0.2s ease;
            display: none;
            background: var(--gray-50);
        }

        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(94, 114, 228, 0.05);
        }

        .file-upload-area i {
            color: var(--primary-color);
        }

        .file-upload-area p {
            font-weight: 600;
            color: var(--gray-700);
            margin: 0.5rem 0 0.25rem;
        }

        .file-upload-area small {
            font-weight: 500;
        }

        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .file-preview-item {
            display: flex;
            align-items: center;
            background: var(--gray-100);
            padding: 0.5rem 0.875rem;
            border-radius: var(--border-radius-xl);
            font-size: 0.813rem;
            font-weight: 600;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }

        .file-preview-item .remove-file {
            margin-left: 0.75rem;
            color: var(--danger-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .file-preview-item .remove-file:hover {
            transform: scale(1.1);
        }

        /* Subscription Notice */
        .subscription-notice {
            background: linear-gradient(135deg, rgba(251, 99, 64, 0.1) 0%, rgba(251, 99, 64, 0.05) 100%);
            border: 1px solid var(--warning-color);
            border-radius: var(--border-radius-lg);
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .subscription-notice.danger {
            background: linear-gradient(135deg, rgba(245, 54, 92, 0.1) 0%, rgba(245, 54, 92, 0.05) 100%);
            border-color: var(--danger-color);
        }

        /* Empty States */
        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--gray-500);
            padding: 2rem;
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: var(--gray-300);
        }

        .empty-state h4, .empty-state h5 {
            margin-bottom: 0.5rem;
            color: var(--gray-700);
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .empty-state p {
            color: var(--gray-500);
            font-weight: 500;
            max-width: 300px;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                transform: translate3d(0,-10px,0);
            }
            70% {
                transform: translate3d(0,-5px,0);
            }
            90% {
                transform: translate3d(0,-2px,0);
            }
        }

        .typing-indicator {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.25rem;
            background: var(--white);
            border-radius: 1.25rem;
            margin-bottom: 1rem;
            border-bottom-left-radius: 0.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
        }

        .typing-dots {
            display: flex;
            gap: 0.375rem;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-color);
            animation: bounce 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        /* Buttons and Controls */
        .btn {
            font-weight: 600;
            letter-spacing: 0.025em;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border: none;
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 8px -2px rgba(94, 114, 228, 0.35);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(94, 114, 228, 0.25);
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: var(--border-radius-lg);
            font-weight: 500;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(45, 206, 137, 0.1) 0%, rgba(45, 206, 137, 0.05) 100%);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(245, 54, 92, 0.1) 0%, rgba(245, 54, 92, 0.05) 100%);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(251, 99, 64, 0.1) 0%, rgba(251, 99, 64, 0.05) 100%);
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }

        /* Modal Styling */
        .modal-content {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
        }

        .modal-header {
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 1.5rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .conversations-sidebar {
                width: 100%;
                position: absolute;
                top: 0;
                left: 0;
                z-index: 1000;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                height: 100vh;
            }

            .conversations-sidebar.show {
                transform: translateX(0);
            }

            .chat-container {
                width: 100%;
                border-radius: 0;
                margin-left: 0;
            }

            .message {
                max-width: 85%;
            }

            .messages-header .d-flex {
                flex-wrap: wrap;
            }

            .stats-badge {
                margin: 0.25rem 0;
            }

            .chat-header {
                border-radius: 0;
            }
        }

        /* Scrollbar Styling */
        .conversations-list::-webkit-scrollbar,
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .conversations-list::-webkit-scrollbar-track,
        .chat-messages::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        .conversations-list::-webkit-scrollbar-thumb,
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }

        .conversations-list::-webkit-scrollbar-thumb:hover,
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }

        /* Focus states for accessibility */
        *:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(94, 114, 228, 0.1);
        }

        /* Print styles */
        @media print {
            .messages-header,
            .conversations-sidebar,
            .chat-input-container,
            .input-actions {
                display: none;
            }

            .chat-messages {
                background: white;
            }
        }
    </style>
</head>
<body>
    <div class="messages-container">
        <!-- Header -->
        <div class="messages-header">
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid">
                    <div class="d-flex align-items-center flex-wrap w-100">
                        <button class="btn btn-link d-md-none me-2" id="toggleSidebar">
                            <i class="fas fa-bars"></i>
                        </button>
                        <span class="navbar-brand mb-0 h1">
                            <i class="fas fa-comments me-2"></i>Messages
                        </span>
                        <div class="ms-auto d-flex align-items-center flex-wrap">
                            <span class="stats-badge">
                                <i class="fas fa-envelope me-1"></i><?php echo $totalMessagesCount; ?> Total
                            </span>
                            <span class="stats-badge">
                                <i class="fas fa-bell me-1"></i><?php echo $unreadMessagesCount; ?> Unread
                            </span>
                            
                            <?php if ($subscription): ?>
                                <span class="stats-badge <?php 
                                    $messagePercentage = ($currentMessages / $subscription['message_limit']) * 100;
                                    $visitorPercentage = ($currentVisitors / $subscription['visitor_limit']) * 100;
                                    if ($messagePercentage > 90 || $visitorPercentage > 90) echo 'danger';
                                    elseif ($messagePercentage > 75 || $visitorPercentage > 75) echo 'warning';
                                ?>">
                                    <i class="fas fa-chart-bar me-1"></i>
                                    <?php echo $currentMessages; ?>/<?php echo $subscription['message_limit']; ?> Messages
                                </span>
                                <span class="stats-badge <?php 
                                    if ($visitorPercentage > 90) echo 'danger';
                                    elseif ($visitorPercentage > 75) echo 'warning';
                                ?>">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $currentVisitors; ?>/<?php echo $subscription['visitor_limit']; ?> Visitors
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($visitorWidgetId): ?>
                                <span class="widget-info ms-2">
                                    Widget: <?php echo htmlspecialchars($visitorWidgetId); ?>
                                </span>
                            <?php elseif ($widgetId): ?>
                                <span class="widget-info ms-2">
                                    Your Widget: <?php echo htmlspecialchars($widgetId); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Main Layout -->
        <div class="messages-layout">
            <!-- Sidebar -->
            <div class="conversations-sidebar" id="conversationsSidebar">
                <div class="sidebar-header">
                    <h4 class="sidebar-title">
                        <i class="fas fa-users me-2"></i>Conversations
                        <?php if ($visitorId): ?>
                            <a href="messages.php" class="btn btn-sm btn-outline-primary ms-2">
                                <i class="fas fa-arrow-left me-1"></i>Back
                            </a>
                        <?php endif; ?>
                    </h4>
                </div>

                <!-- Search -->
                <div class="search-container">
                    <div class="position-relative">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-control search-input" id="searchInput" 
                               placeholder="Search conversations..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <?php if (!empty($searchTerm)): ?>
                            <a href="messages.php" class="btn btn-sm btn-link position-absolute" 
                               style="right: 10px; top: 50%; transform: translateY(-50%);">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <button class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" data-filter="all">
                        <i class="fas fa-inbox me-1"></i>All
                    </button>
                    <button class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>" data-filter="unread">
                        <i class="fas fa-circle me-1"></i>Unread
                    </button>
                    <button class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>" data-filter="read">
                        <i class="fas fa-check-circle me-1"></i>Read
                    </button>
                </div>

                <!-- Conversations List -->
                <div class="conversations-list">
                    <?php if (empty($visitors)): ?>
                        <div class="empty-state">
                            <?php if (!empty($searchTerm)): ?>
                                <i class="fas fa-search"></i>
                                <h5>No Results Found</h5>
                                <p>No visitors found matching "<?php echo htmlspecialchars($searchTerm); ?>"</p>
                                <button class="btn btn-outline-primary btn-sm" onclick="clearSearch()">
                                    <i class="fas fa-times me-1"></i>Clear Search
                                </button>
                            <?php elseif ($filter === 'unread'): ?>
                                <i class="fas fa-inbox"></i>
                                <h5>All Caught Up!</h5>
                                <p>You have no unread messages</p>
                            <?php elseif ($filter === 'read'): ?>
                                <i class="fas fa-check-circle"></i>
                                <h5>No Read Conversations</h5>
                                <p>You have no read conversations yet</p>
                            <?php else: ?>
                                <i class="fas fa-comments"></i>
                                <h5>No Conversations Yet</h5>
                                <p>When visitors start chatting, their conversations will appear here</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($visitors as $visitor): ?>
                            <?php 
                            $hasUnread = isset($visitor['unread_count']) && $visitor['unread_count'] > 0;
                            $isActive = $visitorId && $visitorId == $visitor['id'];
                            $lastActiveTime = strtotime($visitor['last_active']);
                            $isOnline = (time() - $lastActiveTime) < 300; // 5 minutes
                            ?>
                            <a href="messages.php?visitor=<?php echo $visitor['id']; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . htmlspecialchars($filter) : ''; ?>" 
                               class="conversation-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $hasUnread ? 'unread' : ''; ?>">
                                <div class="conversation-avatar">
                                    <?php echo substr($visitor['name'] ?? 'A', 0, 1); ?>
                                    <?php if ($isOnline): ?>
                                        <div class="online-indicator"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-details">
                                    <div class="conversation-header">
                                        <h6 class="visitor-name"><?php echo htmlspecialchars($visitor['name'] ?? 'Anonymous'); ?></h6>
                                        <span class="message-time">
                                            <?php 
                                            if (isset($visitor['last_message_date'])) {
                                                $lastMessageTime = strtotime($visitor['last_message_date']);
                                                $diff = time() - $lastMessageTime;
                                                
                                                if ($diff < 60) {
                                                    echo 'now';
                                                } elseif ($diff < 3600) {
                                                    echo floor($diff / 60) . 'm';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . 'h';
                                                } else {
                                                    echo date('M j', $lastMessageTime);
                                                }
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="last-message">
                                        <?php 
                                        $lastMsg = $visitor['last_message'] ?? '';
                                        if (strpos($lastMsg, 'File: ') === 0) {
                                            echo '<i class="fas fa-paperclip me-1"></i>' . htmlspecialchars(strlen($lastMsg) > 40 ? substr($lastMsg, 0, 40) . '...' : $lastMsg);
                                        } else {
                                            echo htmlspecialchars(strlen($lastMsg) > 50 ? substr($lastMsg, 0, 50) . '...' : $lastMsg);
                                        }
                                        ?>
                                    </div>
                                    <div class="conversation-meta">
                                        <?php if (!empty($visitor['email'])): ?>
                                            <span class="visitor-email"><?php echo htmlspecialchars($visitor['email']); ?></span>
                                        <?php else: ?>
                                            <span class="visitor-email">
                                                <?php 
                                                $url = parse_url($visitor['url'] ?? '');
                                                echo isset($url['host']) ? htmlspecialchars($url['host']) : 'Unknown';
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($hasUnread): ?>
                                            <span class="unread-badge"><?php echo $visitor['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top">
                        <?php if ($page > 1): ?>
                            <a href="messages.php?page=<?php echo $page - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . htmlspecialchars($filter) : ''; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chevron-left me-1"></i>Previous
                            </a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        
                        <small class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="messages.php?page=<?php echo $page + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . htmlspecialchars($filter) : ''; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                Next<i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Chat Area -->
            <div class="chat-container">
                <?php if ($visitorId && $visitorInfo): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="chat-user-info">
                                <div class="chat-avatar">
                                    <?php echo substr($visitorInfo['name'] ?? 'A', 0, 1); ?>
                                </div>
                                <div class="chat-user-details">
                                    <h5><?php echo htmlspecialchars($visitorInfo['name'] ?? 'Anonymous Visitor'); ?></h5>
                                    <p class="chat-user-status <?php 
                                        $lastActive = strtotime($visitorInfo['last_active']);
                                        $isOnline = (time() - $lastActive) < 300;
                                        echo $isOnline ? 'online-status' : 'offline-status';
                                    ?>">
                                        <?php if ($isOnline): ?>
                                            <i class="fas fa-circle me-1"></i>Online now
                                        <?php else: ?>
                                            <i class="far fa-clock me-1"></i>Last seen <?php 
                                                $diff = time() - $lastActive;
                                                if ($diff < 3600) {
                                                    echo floor($diff / 60) . ' minutes ago';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . ' hours ago';
                                                } else {
                                                    echo date('M j, g:i a', $lastActive);
                                                }
                                            ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <?php if (!empty($visitorInfo['url'])): ?>
                                    <a href="<?php echo htmlspecialchars($visitorInfo['url']); ?>" target="_blank" 
                                       class="btn btn-outline-primary btn-sm me-2">
                                        <i class="fas fa-external-link-alt me-1"></i>Visit Site
                                    </a>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#visitorInfoModal">
                                    <i class="fas fa-info-circle me-1"></i>Info
                                </button>
                                <button class="btn btn-outline-secondary btn-sm d-md-none" onclick="showSidebar()">
                                    <i class="fas fa-list me-1"></i>Chats
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <i class="fas fa-comment-dots"></i>
                                <h4>Start the Conversation</h4>
                                <p>No messages yet. Send a message to begin chatting with this visitor.</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $currentDate = '';
                            foreach ($messages as $index => $message): 
                                $messageDate = date('Y-m-d', strtotime($message['created_at']));
                                
                                // Show date divider if date changes
                                if ($currentDate !== $messageDate):
                                    $currentDate = $messageDate;
                            ?>
                                <div class="date-divider">
                                    <span><?php echo date('F j, Y', strtotime($message['created_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php $isAgent = $message['sender_type'] === 'agent'; ?>
                            <div class="message <?php echo $isAgent ? 'sent' : 'received'; ?>" data-id="<?php echo $message['id']; ?>">
                                <div class="message-content">
                                    <?php if (!empty($message['file_path'])): ?>
                                        <?php if (isImage($message['file_name'])): ?>
                                            <div class="image-message" onclick="openImageModal('<?php echo htmlspecialchars($message['file_path']); ?>', '<?php echo htmlspecialchars($message['file_name']); ?>')">
                                                <img src="<?php echo htmlspecialchars($message['file_path']); ?>" alt="<?php echo htmlspecialchars($message['file_name']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="file-message" onclick="downloadFile('<?php echo htmlspecialchars($message['file_path']); ?>', '<?php echo htmlspecialchars($message['file_name']); ?>')">
                                                <div class="file-icon">
                                                    <i class="<?php echo getFileIcon($message['file_name']); ?>"></i>
                                                </div>
                                                <div class="file-details">
                                                    <h6><?php echo htmlspecialchars($message['file_name']); ?></h6>
                                                    <div class="file-size"><?php echo htmlspecialchars($message['file_size']); ?></div>
                                                </div>
                                                <button class="download-btn">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($message['message']) && strpos($message['message'], 'File: ') !== 0): ?>
                                        <p class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="message-time">
                                        <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                                        <?php if ($isAgent): ?>
                                            <span class="message-status">
                                                <?php if (isset($message['read']) && $message['read']): ?>
                                                    <i class="fas fa-check-double text-info" title="Read"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check" title="Delivered"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Chat Input -->
                    <div class="chat-input-container">
                        <!-- Subscription Notices -->
                        <?php if ($subscription): ?>
                            <?php 
                            $messagePercentage = ($currentMessages / $subscription['message_limit']) * 100;
                            $visitorPercentage = ($currentVisitors / $subscription['visitor_limit']) * 100;
                            ?>
                            
                            <?php if ($messagePercentage > 90 || $visitorPercentage > 90): ?>
                                <div class="subscription-notice danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Limit Warning:</strong> You're approaching your subscription limits. 
                                    <?php if ($messagePercentage > 90): ?>
                                        Messages: <?php echo $currentMessages; ?>/<?php echo $subscription['message_limit']; ?>
                                    <?php endif; ?>
                                    <?php if ($visitorPercentage > 90): ?>
                                        <?php echo $messagePercentage > 90 ? ', ' : ''; ?>Visitors: <?php echo $currentVisitors; ?>/<?php echo $subscription['visitor_limit']; ?>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($messagePercentage > 75 || $visitorPercentage > 75): ?>
                                <div class="subscription-notice">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Usage Notice:</strong> You're using a significant portion of your subscription limits.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($messageSent || $fileUploaded): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php if ($messageSent && $fileUploaded): ?>
                                    Message and file(s) sent successfully!
                                <?php elseif ($fileUploaded): ?>
                                    File(s) sent successfully!
                                <?php else: ?>
                                    Message sent successfully!
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php elseif ($messageError): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($messageError); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" id="chatForm" enctype="multipart/form-data">
                            <div class="chat-input-wrapper">
                                <textarea name="message" class="message-input" id="messageInput" 
                                          placeholder="Type your message..." rows="1"></textarea>
                                <div class="input-actions">
                                    <button type="button" class="file-upload-btn" 
                                            onclick="document.getElementById('fileInput').click()" 
                                            <?php echo !$canUpload ? 'disabled title="File uploads not available on your plan"' : ''; ?>>
                                        <i class="fas fa-paperclip"></i>
                                    </button>
                                    <button type="button" class="emoji-btn" onclick="addEmoji()">
                                        <i class="fas fa-smile"></i>
                                    </button>
                                    <button type="submit" class="send-btn" id="sendBtn">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Hidden file input -->
                            <input type="file" id="fileInput" name="files[]" multiple style="display: none;" 
                                   accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar"
                                   <?php echo !$canUpload ? 'disabled' : ''; ?>>
                            
                            <!-- File upload area -->
                            <div class="file-upload-area" id="fileUploadArea">
                                <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                <p>Drag and drop files here, or click to browse</p>
                                <small class="text-muted">
                                    <?php if ($canUpload): ?>
                                        Supports: Images, Documents, Archives (Max 10MB per file)
                                    <?php else: ?>
                                        File uploads not available on your current plan
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <!-- File preview -->
                            <div class="file-preview" id="filePreview"></div>
                        </form>
                        
                        <?php if ($visitorWidgetId): ?>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-widget me-1"></i>Widget: <?php echo htmlspecialchars($visitorWidgetId); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <i class="fas fa-comment-alt"></i>
                        <h4>Select a Conversation</h4>
                        <p>Choose a conversation from the sidebar to start chatting with your visitors.</p>
                        
                        <?php if ($subscription): ?>
                            <div class="mt-4">
                                <small class="text-muted">
                                    <strong>Current Plan:</strong> <?php echo htmlspecialchars($subscription['name']); ?><br>
                                    <strong>Usage:</strong> <?php echo $currentMessages; ?>/<?php echo $subscription['message_limit']; ?> messages, 
                                    <?php echo $currentVisitors; ?>/<?php echo $subscription['visitor_limit']; ?> visitors<br>
                                    <strong>File Uploads:</strong> <?php echo $canUpload ? 'Enabled' : 'Not Available'; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Visitor Info Modal -->
    <?php if ($visitorInfo): ?>
    <div class="modal fade" id="visitorInfoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>Visitor Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="chat-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?php echo substr($visitorInfo['name'] ?? 'A', 0, 1); ?>
                        </div>
                        <h5><?php echo htmlspecialchars($visitorInfo['name'] ?? 'Anonymous Visitor'); ?></h5>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-4"><strong>Email:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($visitorInfo['email'] ?? 'Not provided'); ?></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Website:</strong></div>
                        <div class="col-sm-8">
                            <?php if (!empty($visitorInfo['url'])): ?>
                                <a href="<?php echo htmlspecialchars($visitorInfo['url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($visitorInfo['url']); ?>
                                </a>
                            <?php else: ?>
                                Not available
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>IP Address:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($visitorInfo['ip_address'] ?? 'Unknown'); ?></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>First Visit:</strong></div>
                        <div class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($visitorInfo['created_at'])); ?></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Last Active:</strong></div>
                        <div class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($visitorInfo['last_active'])); ?></div>
                    </div>
                    <?php if ($visitorWidgetId): ?>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Widget ID:</strong></div>
                        <div class="col-sm-8">
                            <code><?php echo htmlspecialchars($visitorWidgetId); ?></code>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imageModalImg" src="" alt="" class="img-fluid">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="downloadImageBtn">
                        <i class="fas fa-download me-1"></i>Download
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }

        // Auto-scroll to bottom
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Mobile sidebar toggle
        function showSidebar() {
            document.getElementById('conversationsSidebar').classList.add('show');
        }

        function hideSidebar() {
            document.getElementById('conversationsSidebar').classList.remove('show');
        }

        document.getElementById('toggleSidebar')?.addEventListener('click', showSidebar);

        // Filter functionality
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const filter = this.dataset.filter;
                const searchTerm = document.getElementById('searchInput')?.value || '';
                const url = new URL(window.location);
                
                if (filter === 'all') {
                    url.searchParams.delete('filter');
                } else {
                    url.searchParams.set('filter', filter);
                }
                
                if (searchTerm) {
                    url.searchParams.set('search', searchTerm);
                } else {
                    url.searchParams.delete('search');
                }
                
                window.location.href = url.toString();
            });
        });

        // Search functionality
        let searchTimeout;
        document.getElementById('searchInput')?.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = this.value.trim();
                const url = new URL(window.location);
                
                if (searchTerm) {
                    url.searchParams.set('search', searchTerm);
                } else {
                    url.searchParams.delete('search');
                }
                
                window.location.href = url.toString();
            }, 500);
        });

        function clearSearch() {
            const url = new URL(window.location);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }

        // File upload functionality
        const fileInput = document.getElementById('fileInput');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const filePreview = document.getElementById('filePreview');
        let selectedFiles = [];

        fileInput?.addEventListener('change', handleFileSelect);

        function handleFileSelect(event) {
            if (!<?php echo $canUpload ? 'true' : 'false'; ?>) {
                alert('File uploads are not available on your current subscription plan.');
                return;
            }
            
            const files = Array.from(event.target.files);
            selectedFiles = [...selectedFiles, ...files];
            updateFilePreview();
        }

        function updateFilePreview() {
            if (selectedFiles.length === 0) {
                filePreview.style.display = 'none';
                return;
            }

            filePreview.style.display = 'flex';
            filePreview.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-preview-item';
                fileItem.innerHTML = `
                    <i class="fas fa-file me-2"></i>
                    <span>${file.name}</span>
                    <i class="fas fa-times remove-file" onclick="removeFile(${index})"></i>
                `;
                filePreview.appendChild(fileItem);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFilePreview();
        }

        // Drag and drop (only if uploads are allowed)
        if (<?php echo $canUpload ? 'true' : 'false'; ?>) {
            document.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (fileUploadArea) {
                    fileUploadArea.style.display = 'block';
                    fileUploadArea.classList.add('dragover');
                }
            });

            document.addEventListener('dragleave', function(e) {
                if (!e.relatedTarget && fileUploadArea) {
                    fileUploadArea.classList.remove('dragover');
                }
            });

            document.addEventListener('drop', function(e) {
                e.preventDefault();
                if (fileUploadArea) {
                    fileUploadArea.classList.remove('dragover');
                    const files = Array.from(e.dataTransfer.files);
                    selectedFiles = [...selectedFiles, ...files];
                    updateFilePreview();
                }
            });
        }

        // Emoji functionality
        function addEmoji() {
            const emojis = ['😊', '😂', '❤️', '👍', '😍', '😢', '😮', '😡', '🎉', '👏'];
            const randomEmoji = emojis[Math.floor(Math.random() * emojis.length)];
            messageInput.value += randomEmoji;
            messageInput.focus();
        }

        // Image modal
        function openImageModal(imageUrl, imageName) {
            document.getElementById('imageModalImg').src = imageUrl;
            document.getElementById('imageModalTitle').textContent = imageName;
            document.getElementById('downloadImageBtn').onclick = () => downloadFile(imageUrl, imageName);
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        // File download
        function downloadFile(url, filename) {
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Form submission enhancement
        document.getElementById('chatForm')?.addEventListener('submit', function(e) {
            const sendBtn = document.getElementById('sendBtn');
            const messageText = messageInput.value.trim();
            const hasFiles = selectedFiles.length > 0;
            
            if (!messageText && !hasFiles) {
                e.preventDefault();
                return;
            }
            
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            sendBtn.disabled = true;
        });

        // Real-time message checking
        <?php if ($visitorId): ?>
        let lastMessageId = <?php echo !empty($messages) ? max(array_column($messages, 'id')) : 0; ?>;
        
        function checkForNewMessages() {
            fetch(`ajax/check-messages.php?visitor_id=<?php echo $visitorId; ?>&last_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            if (message.sender_type === 'visitor') {
                                addMessageToChat(message);
                                lastMessageId = Math.max(lastMessageId, message.id);
                            }
                        });
                    }
                })
                .catch(error => console.error('Error checking messages:', error));
        }
        
        function addMessageToChat(message) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageEl = document.createElement('div');
            messageEl.className = 'message received';
            
            let messageContent = '';
            if (message.file_path) {
                if (['jpg', 'jpeg', 'png', 'gif'].includes(message.file_name.split('.').pop().toLowerCase())) {
                    messageContent = `<div class="image-message" onclick="openImageModal('${message.file_path}', '${message.file_name}')">
                        <img src="${message.file_path}" alt="${message.file_name}">
                    </div>`;
                } else {
                    messageContent = `<div class="file-message" onclick="downloadFile('${message.file_path}', '${message.file_name}')">
                        <div class="file-icon"><i class="fas fa-file"></i></div>
                        <div class="file-details">
                            <h6>${message.file_name}</h6>
                            <div class="file-size">${message.file_size}</div>
                        </div>
                        <button class="download-btn"><i class="fas fa-download"></i></button>
                    </div>`;
                }
            }
            
            if (message.message && !message.message.startsWith('File: ')) {
                messageContent += `<p class="message-text">${message.message.replace(/\n/g, '<br>')}</p>`;
            }
            
            messageEl.innerHTML = `
                <div class="message-content">
                    ${messageContent}
                    <div class="message-time">${new Date(message.created_at).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'})}</div>
                </div>
            `;
            messagesContainer.appendChild(messageEl);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Check for new messages every 3 seconds
        setInterval(checkForNewMessages, 3000);
        <?php endif; ?>

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>
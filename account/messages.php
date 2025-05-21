<?php
$pageTitle = 'Messages';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Get widget_id for this user
$widgetId = isset($user['widget_id']) ? $user['widget_id'] : null;

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
    
    // Log for debugging
    error_log("Viewing messages for visitor {$visitorId} with widget_id: " . ($visitorWidgetId ?? 'unknown'));
    
    // Mark messages as read if viewing a specific conversation
    // Include widget_id in the filter if the column exists and we have a widget_id
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
}

// Build the base query for visitors with message count
// Include widget_id filter if the column exists
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

$baseVisitorsQuery .= ") as last_message_date
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

// Include widget_id in params if the column exists
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
    // Log the error for debugging
    error_log("Visitors query error: " . $e->getMessage());
    error_log("Query: " . $visitorsQuery);
    error_log("Params: " . print_r($queryParams, true));
    
    // Set visitors to empty array to prevent further errors
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
    // Log the error for debugging
    error_log("Count query error: " . $e->getMessage());
    error_log("Query: " . $countQuery);
    error_log("Params: " . print_r($countParams, true));
    
    // Set default value
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
            // Include widget_id in the filter if the column exists and we have a widget_id
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $visitorId) {
    $messageContent = trim($_POST['message']);
    
    if (!empty($messageContent)) {
        try {
            // Prepare message data
            $messageData = [
                'user_id' => $userId,
                'visitor_id' => $visitorId,
                'message' => $messageContent,
                'sender_type' => 'agent',
                'read' => 0 // Not read by default
            ];
            
            // Include widget_id if available - either from session or from the query
            if (isset($_SESSION['current_visitor_widget_id']) && $_SESSION['current_visitor_widget_id']) {
                $messageData['widget_id'] = $_SESSION['current_visitor_widget_id'];
            } elseif ($visitorWidgetId) {
                $messageData['widget_id'] = $visitorWidgetId;
            }
            
            // Insert message using the db->insert method
            $messageId = $db->insert('messages', $messageData);
            
            // Update visitor's last activity
            $db->query(
                "UPDATE visitors SET last_active = NOW() WHERE id = ?",
                [$visitorId]
            );
            
            $messageSent = true;
            
            // Get the updated conversation with the same widget_id filter
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
            // Log the actual error for debugging
            error_log("Message sending error: " . $e->getMessage());
        }
    } else {
        $messageError = "Message cannot be empty.";
    }
}

// Get message counts for header stats
try {
    // Include widget_id in the count queries if the column exists
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
    error_log("Total messages count error: " . $e->getMessage());
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
    error_log("Unread messages count error: " . $e->getMessage());
    $unreadMessagesCount = 0;
}

// Include header
include '../includes/header.php';
?>

<style>
/* Messages Page Styles */
.messages-container {
    max-width: 1400px;
    padding: 0;
    height: calc(100vh - 80px);
    margin: 0 auto;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #fff;
    border-bottom: 1px solid #e6e9f0;
}

.page-header h1 {
    margin: 0;
    font-size: 1.5rem;
    color: #333;
}

.total-stats {
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-item {
    display: flex;
    align-items: center;
    color: #555;
}

.stat-item .count {
    font-weight: 700;
    font-size: 1.1rem;
    color: #333;
    margin-right: 5px;
}

.stat-item .label {
    font-size: 0.85rem;
}

.stat-item .id {
    font-family: monospace;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 5px;
    font-size: 0.85rem;
}

.messages-layout {
    display: flex;
    height: 100%;
    overflow: hidden;
}

/* Sidebar Styles */
.conversations-sidebar {
    width: 340px;
    border-right: 1px solid #e6e9f0;
    display: flex;
    flex-direction: column;
    background-color: #f8f9fa;
    overflow: hidden;
}

.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #e6e9f0;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 1.1rem;
    color: #333;
}

.btn-back {
    font-size: 0.85rem;
    color: #3498db;
    text-decoration: none;
}

.btn-back:hover {
    text-decoration: underline;
}

.conversation-filters {
    padding: 15px;
    border-bottom: 1px solid #e6e9f0;
}

.search-form {
    margin-bottom: 12px;
}

.search-input-container {
    position: relative;
}

.search-input {
    width: 100%;
    padding: 10px 35px 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.3s;
}

.search-input:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

.search-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #888;
    cursor: pointer;
    padding: 5px;
}

.clear-search {
    position: absolute;
    right: 32px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
    cursor: pointer;
    padding: 5px;
    text-decoration: none;
}

.filter-tabs {
    display: flex;
    gap: 2px;
}

.filter-tab {
    flex: 1;
    padding: 8px 12px;
    text-align: center;
    border-radius: 6px;
    font-size: 0.85rem;
    color: #555;
    text-decoration: none;
    transition: all 0.2s ease;
    background-color: #eef1f5;
}

.filter-tab:hover {
    background-color: #e0e5eb;
}

.filter-tab.active {
    background-color: #3498db;
    color: white;
}

.conversation-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.conversation-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    text-decoration: none;
    margin-bottom: 8px;
    transition: all 0.2s ease;
    background-color: #fff;
    border: 1px solid transparent;
}

.conversation-item:hover {
    background-color: #f0f4f8;
}

.conversation-item.active {
    border-color: #3498db;
    background-color: #ebf5fb;
}

.conversation-item.unread {
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    border-left: 4px solid #3498db;
}

.conversation-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background-color: #3498db;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 600;
    flex-shrink: 0;
}

.conversation-content {
    flex: 1;
    min-width: 0;
}

.conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.visitor-name {
    color: #333;
    font-weight: 600;
    font-size: 0.95rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.last-message-time {
    color: #888;
    font-size: 0.75rem;
}

.conversation-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.visitor-email, .visitor-url {
    color: #777;
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 170px;
}

.unread-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    background-color: #3498db;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0 6px;
}

.sidebar-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-top: 1px solid #e6e9f0;
    background-color: #fff;
}

.pagination-button {
    color: #3498db;
    text-decoration: none;
    font-size: 0.85rem;
    padding: 5px 8px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.pagination-button:hover {
    background-color: #ebf5fb;
}

.pagination-info {
    color: #777;
    font-size: 0.8rem;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px 20px;
    color: #888;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 20px;
    color: #ccc;
}

.empty-state p {
    font-size: 0.95rem;
    margin-bottom: 15px;
}

.btn-outline {
    display: inline-block;
    padding: 8px 16px;
    border: 1px solid #3498db;
    border-radius: 6px;
    color: #3498db;
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.btn-outline:hover {
    background-color: #3498db;
    color: white;
}

/* Messages Content Styles */
.messages-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    background-color: #fff;
    overflow: hidden;
}

.chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e6e9f0;
    background-color: #fff;
}

.visitor-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.visitor-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background-color: #3498db;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 600;
}

.visitor-details h3 {
    margin: 0 0 5px;
    font-size: 1.1rem;
    color: #333;
}

.visitor-email {
    font-size: 0.85rem;
    color: #666;
}

.visitor-widget-id {
    font-size: 0.8rem;
    color: #888;
    margin-top: 2px;
}

.visitor-meta {
    display: flex;
    align-items: center;
    gap: 20px;
}

.meta-item {
    display: flex;
    align-items: center;
    color: #666;
    font-size: 0.85rem;
}

.meta-item i {
    margin-right: 5px;
    color: #3498db;
}

.meta-item a {
    color: #3498db;
    text-decoration: none;
}

.meta-item a:hover {
    text-decoration: underline;
}

.visitor-actions {
    margin-left: 10px;
}

.btn {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.8rem;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-outline {
    border: 1px solid #3498db;
    color: #3498db;
    background-color: transparent;
}

.btn-outline:hover {
    background-color: #ebf5fb;
}

.btn-icon {
    padding: 8px;
    border-radius: 6px;
    color: #666;
    background-color: #f0f4f8;
}

.btn-icon:hover {
    background-color: #e0e5eb;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background-color: #f8f9fa;
    background-image: 
        radial-gradient(circle at 25px 25px, rgba(0,0,0,0.1) 2%, transparent 0%), 
        radial-gradient(circle at 75px 75px, rgba(0,0,0,0.05) 2%, transparent 0%);
    background-size: 100px 100px;
}

.messages-date-divider {
    display: flex;
    align-items: center;
    margin: 20px 0;
    color: #888;
    font-size: 0.8rem;
}

.messages-date-divider::before,
.messages-date-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background-color: #ddd;
}

.messages-date-divider span {
    padding: 0 15px;
}

.message {
    display: flex;
    margin-bottom: 15px;
    max-width: 80%;
}

.message.visitor {
    align-self: flex-start;
}

.message.agent {
    align-self: flex-end;
    flex-direction: row-reverse;
    margin-left: auto;
}

.message-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background-color: #9b59b6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 600;
    margin-right: 10px;
}

.message.agent .message-avatar {
    margin-right: 0;
    margin-left: 10px;
}

.message-bubble {
    position: relative;
    padding: 12px 16px;
    border-radius: 18px;
    max-width: calc(100% - 50px);
}

.message.visitor .message-bubble {
    background-color: white;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.message.agent .message-bubble {
    background-color: #3498db;
    color: white;
    border-bottom-right-radius: 4px;
}

.message-content {
    font-size: 0.95rem;
    line-height: 1.4;
    word-wrap: break-word;
    white-space: pre-wrap;
}

.message-time {
    font-size: 0.7rem;
    opacity: 0.8;
    margin-top: 5px;
    text-align: right;
}

.read-status {
    margin-left: 5px;
}

.chat-input-container {
    padding: 15px 20px;
    border-top: 1px solid #e6e9f0;
    background-color: #fff;
}

.message-status {
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    transition: opacity 0.3s;
}

.message-status i {
    margin-right: 5px;
}

.message-status.success {
    background-color: #e8f8f5;
    color: #27ae60;
}

.message-status.error {
    background-color: #faeae7;
    color: #e74c3c;
}

.chat-form {
    display: flex;
    flex-direction: column;
}

.chat-input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.95rem;
    line-height: 1.4;
    resize: none;
    height: 24px;
    max-height: 150px;
    outline: none;
    margin-bottom: 10px;
    transition: border-color 0.3s;
}

.chat-input:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

.chat-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.send-btn {
    padding: 10px 20px;
}

.widget-info {
    margin-top: 10px;
    font-size: 0.8rem;
    color: #888;
    text-align: center;
}

.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    color: #888;
    padding: 20px;
}

.empty-chat-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #ddd;
}

.empty-chat h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: #666;
}

.empty-chat p {
    font-size: 1rem;
    max-width: 300px;
}

/* Responsive Styles */
@media (max-width: 992px) {
    .messages-container {
        height: calc(100vh - 70px);
    }
    
    .messages-layout {
        flex-direction: column;
    }
    
    .conversations-sidebar {
        width: 100%;
        height: 100%;
        display: <?php echo $visitorId ? 'none' : 'flex'; ?>;
        z-index: 5;
    }
    
    .messages-content {
        display: <?php echo $visitorId ? 'flex' : 'none'; ?>;
        height: 100%;
    }
    
    .chat-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .visitor-meta {
        margin-top: 10px;
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .visitor-actions {
        margin-top: 10px;
        margin-left: 0;
    }
    
    .message {
        max-width: 90%;
    }
}

@media (max-width: 576px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .total-stats {
        margin-top: 10px;
        flex-wrap: wrap;
    }
    
    .message-bubble {
        max-width: calc(100% - 40px);
    }
    
    .filter-tabs {
        overflow-x: auto;
        padding-bottom: 5px;
    }
    
    .filter-tab {
        flex: 0 0 auto;
        min-width: 80px;
    }
    
    .chat-input {
        padding: 10px;
    }
    
    .btn {
        padding: 8px 12px;
    }
}

/* Animation Effects */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message {
    animation: fadeIn 0.3s ease-out;
}

.status-badge {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 5px;
}

.status-badge.online {
    background-color: #2ecc71;
}

.status-badge.offline {
    background-color: #e74c3c;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .conversations-sidebar {
        background-color: #1e1e2d;
        border-color: #2d2d42;
    }
    
    .conversation-item {
        background-color: #262636;
    }
    
    .conversation-item:hover {
        background-color: #2c2c40;
    }
    
    .conversation-item.active {
        background-color: #2f367d;
        border-color: #4a5fc1;
    }
    
    .sidebar-header, .conversation-filters, .sidebar-pagination, .page-header, .chat-header, .chat-input-container {
        background-color: #1e1e2d;
        border-color: #2d2d42;
    }
    
    .chat-messages {
        background-color: #262636;
    }
    
    .filter-tab {
        background-color: #2d2d42;
        color: #a9b7d0;
    }
    
    .filter-tab:hover {
        background-color: #32324a;
    }
    
    .filter-tab.active {
        background-color: #3a57e8;
    }
    
    .message.visitor .message-bubble {
        background-color: #2d2d42;
        color: #e2e5ec;
    }
    
    .chat-input {
        background-color: #262636;
        border-color: #2d2d42;
        color: #e2e5ec;
    }
    
    h1, h2, h3, .visitor-name {
        color: #e2e5ec;
    }
    
    .visitor-email, .meta-item, .last-message-time {
        color: #a9b7d0;
    }
    
    .search-input {
        background-color: #262636;
        border-color: #2d2d42;
        color: #e2e5ec;
    }
    
    .empty-chat, .empty-state {
        color: #a9b7d0;
    }
    
    .empty-chat-icon, .empty-icon {
        color: #32324a;
    }
    
    .messages-date-divider {
        color: #a9b7d0;
    }
    
    .messages-date-divider::before, .messages-date-divider::after {
        background-color: #32324a;
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.05);
}

::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.2);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.3);
}
</style>

<main class="messages-container">
    <div class="page-header">
        <h1>Messages</h1>
        <div class="total-stats">
            <div class="stat-item">
                <span class="count"><?php echo $totalMessagesCount; ?></span>
                <span class="label">Total Messages</span>
            </div>
            <div class="stat-item">
                <span class="count"><?php echo $unreadMessagesCount; ?></span>
                <span class="label">Unread</span>
            </div>
            <!-- Add Widget ID information if available -->
            <?php if ($visitorWidgetId): ?>
            <div class="stat-item">
                <span class="label">Current Widget:</span>
                <span class="id"><?php echo htmlspecialchars($visitorWidgetId); ?></span>
            </div>
            <?php elseif ($widgetId): ?>
            <div class="stat-item">
                <span class="label">Your Widget:</span>
                <span class="id"><?php echo htmlspecialchars($widgetId); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="messages-layout">
        <!-- Left sidebar: Conversation list -->
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2>Conversations</h2>
                <?php if ($visitorId): ?>
                <a href="messages.php" class="btn btn-sm btn-back">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <?php endif; ?>
            </div>
            
            <div class="conversation-filters">
                <form method="get" class="search-form">
                    <div class="search-input-container">
                        <input type="text" name="search" placeholder="Search visitors..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($searchTerm)): ?>
                        <a href="messages.php<?php echo $filter !== 'all' ? '?filter=' . htmlspecialchars($filter) : ''; ?>" class="clear-search">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($visitorId): ?>
                    <input type="hidden" name="visitor" value="<?php echo $visitorId; ?>">
                    <?php endif; ?>
                </form>
                
                <div class="filter-tabs">
                    <a href="messages.php<?php echo !empty($searchTerm) ? '?search=' . urlencode($searchTerm) : ''; ?>" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="messages.php?filter=unread<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                        Unread
                    </a>
                   <a href="messages.php?filter=read<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">
                        Read
                    </a>
                </div>
            </div>
            
            <div class="conversation-list">
                <?php if (empty($visitors)): ?>
                    <div class="empty-state">
                        <?php if (!empty($searchTerm)): ?>
                            <p>No visitors found matching "<?php echo htmlspecialchars($searchTerm); ?>".</p>
                            <a href="messages.php" class="btn-outline">Clear Search</a>
                        <?php elseif ($filter === 'unread'): ?>
                            <div class="empty-icon">📬</div>
                            <p>You have no unread messages.</p>
                        <?php elseif ($filter === 'read'): ?>
                            <div class="empty-icon">📭</div>
                            <p>You have no read conversations.</p>
                        <?php else: ?>
                            <div class="empty-icon">💬</div>
                            <p>No messages yet. When visitors chat with you, they'll appear here.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($visitors as $visitor): ?>
                        <?php 
                        $hasUnread = isset($visitor['unread_count']) && $visitor['unread_count'] > 0;
                        $isActive = $visitorId && $visitorId == $visitor['id'];
                        ?>
                        <a href="messages.php?visitor=<?php echo $visitor['id']; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . htmlspecialchars($filter) : ''; ?>" 
                           class="conversation-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $hasUnread ? 'unread' : ''; ?>">
                            <div class="conversation-avatar">
                                <?php echo substr($visitor['name'] ?? 'A', 0, 1); ?>
                            </div>
                            <div class="conversation-content">
                                <div class="conversation-header">
                                    <span class="visitor-name"><?php echo htmlspecialchars($visitor['name'] ?? 'Anonymous'); ?></span>
                                    <span class="last-message-time">
                                        <?php 
                                        if (isset($visitor['last_message_date'])) {
                                            $lastMessageTime = strtotime($visitor['last_message_date']);
                                            $now = time();
                                            $diff = $now - $lastMessageTime;
                                            
                                            if ($diff < 60) {
                                                echo 'Just now';
                                            } elseif ($diff < 3600) {
                                                echo floor($diff / 60) . 'm ago';
                                            } elseif ($diff < 86400) {
                                                echo floor($diff / 3600) . 'h ago';
                                            } else {
                                                echo date('M j', $lastMessageTime);
                                            }
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="conversation-meta">
                                    <?php if (!empty($visitor['email'])): ?>
                                        <span class="visitor-email"><?php echo htmlspecialchars($visitor['email']); ?></span>
                                    <?php else: ?>
                                        <span class="visitor-url">
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
            
            <?php if ($totalPages > 1): ?>
                <div class="sidebar-pagination">
                    <?php if ($page > 1): ?>
                        <a href="messages.php?page=<?php echo $page - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . htmlspecialchars($filter) : ''; ?><?php echo $visitorId ? '&visitor=' . $visitorId : ''; ?>" class="pagination-button">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    
                    <span class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="messages.php?page=<?php echo $page + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . htmlspecialchars($filter) : ''; ?><?php echo $visitorId ? '&visitor=' . $visitorId : ''; ?>" class="pagination-button">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right content: Messages or empty state -->
        <div class="messages-content">
            <?php if ($visitorId && $visitorInfo): ?>
                <div class="chat-header">
                    <div class="visitor-info">
                        <div class="visitor-avatar">
                            <?php echo substr($visitorInfo['name'] ?? 'A', 0, 1); ?>
                        </div>
                        <div class="visitor-details">
                            <h3><?php echo htmlspecialchars($visitorInfo['name'] ?? 'Anonymous Visitor'); ?></h3>
                            <?php if (!empty($visitorInfo['email'])): ?>
                                <div class="visitor-email"><?php echo htmlspecialchars($visitorInfo['email']); ?></div>
                            <?php endif; ?>
                            <?php if ($visitorWidgetId): ?>
                                <div class="visitor-widget-id">Widget ID: <?php echo htmlspecialchars($visitorWidgetId); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="visitor-meta">
                        <?php if (!empty($visitorInfo['url'])): ?>
                            <div class="meta-item">
                                <i class="fas fa-globe"></i>
                                <a href="<?php echo htmlspecialchars($visitorInfo['url']); ?>" target="_blank"><?php 
                                    $url = parse_url($visitorInfo['url']);
                                    echo isset($url['host']) ? htmlspecialchars($url['host']) : 'Unknown website';
                                ?></a>
                            </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            <?php 
                            $lastActive = strtotime($visitorInfo['last_active']);
                            $now = time();
                            $diff = $now - $lastActive;
                            $isActive = $diff < 300; // Consider active if less than 5 minutes
                            ?>
                            <span class="status-badge <?php echo $isActive ? 'online' : 'offline'; ?>"></span>
                            <?php if ($isActive): ?>
                                Online now
                            <?php else: ?>
                                Last active: <?php 
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo floor($diff / 60) . ' minutes ago';
                                    } elseif ($diff < 86400) {
                                        echo floor($diff / 3600) . ' hours ago';
                                    } else {
                                        echo date('M j, g:i a', $lastActive);
                                    }
                                ?>
                            <?php endif; ?>
                        </div>
                        <div class="visitor-actions">
                            <a href="visitor-details.php?id=<?php echo $visitorId; ?>" class="btn btn-sm btn-outline">
                                <i class="fas fa-user"></i> View Profile
                            </a>
                            <?php if (!empty($messages)): ?>
                                <a href="#" class="btn btn-sm btn-outline toggle-mobile-sidebar">
                                    <i class="fas fa-chevron-left"></i> All Chats
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">💬</div>
                            <p>No messages yet with this visitor.</p>
                            <p>Send a message to start the conversation.</p>
                        </div>
                    <?php else: ?>
                        <div class="messages-date-divider">
                            <span>Conversation started <?php echo date('F j, Y', strtotime($messages[0]['created_at'])); ?></span>
                        </div>
                        
                        <?php 
                        $currentDate = date('Y-m-d', strtotime($messages[0]['created_at']));
                        foreach ($messages as $index => $message): 
                            $messageDate = date('Y-m-d', strtotime($message['created_at']));
                            
                            // Show date divider if date changes
                            if ($currentDate != $messageDate):
                                $currentDate = $messageDate;
                        ?>
                            <div class="messages-date-divider">
                                <span><?php echo date('F j, Y', strtotime($message['created_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php $isAgent = $message['sender_type'] === 'agent'; ?>
                        <div class="message <?php echo $isAgent ? 'agent' : 'visitor'; ?>" data-id="<?php echo $message['id']; ?>">
                            <?php if (!$isAgent): ?>
                            <div class="message-avatar">
                                <?php echo substr($visitorInfo['name'] ?? 'A', 0, 1); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="message-bubble">
                                <div class="message-content"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                <div class="message-time">
                                    <?php echo date('g:i a', strtotime($message['created_at'])); ?>
                                    <?php if ($isAgent): ?>
                                        <?php if (isset($message['read']) && $message['read']): ?>
                                            <span class="read-status" title="Read">
                                                <i class="fas fa-check-double"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="read-status" title="Delivered">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="chat-input-container">
                    <?php if ($messageSent): ?>
                        <div class="message-status success">
                            <i class="fas fa-check-circle"></i> Message sent successfully.
                        </div>
                    <?php elseif ($messageError): ?>
                        <div class="message-status error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($messageError); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="chat-form" id="chat-form">
                        <textarea name="message" class="chat-input" id="message-input" placeholder="Type your message here..." required></textarea>
                        <div class="chat-actions">
                            <div>
                                <button type="button" class="btn btn-icon emoji-btn" title="Add Emoji">
                                    <i class="fas fa-smile"></i>
                                </button>
                            </div>
                            <button type="submit" class="btn btn-primary send-btn">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($visitorWidgetId): ?>
                    <div class="widget-info">
                        <small>Messages will be sent to widget ID: <?php echo htmlspecialchars($visitorWidgetId); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-chat">
                    <div class="empty-chat-icon">💬</div>
                    <h3>Select a conversation</h3>
                    <p>Choose a conversation from the list to view messages</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to bottom of chat messages
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Auto-resize textarea
    const messageInput = document.getElementById('message-input');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = '24px';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Focus the input
        messageInput.focus();
    }
    
    // Hide message status after delay
    const messageStatus = document.querySelector('.message-status');
    if (messageStatus) {
        setTimeout(function() {
            messageStatus.style.opacity = '0';
            setTimeout(function() {
                messageStatus.style.display = 'none';
            }, 300);
        }, 3000);
    }
    
    // Toggle sidebar in mobile view
    const toggleSidebarBtn = document.querySelector('.toggle-mobile-sidebar');
    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const sidebar = document.querySelector('.conversations-sidebar');
            const content = document.querySelector('.messages-content');
            
            sidebar.style.display = 'flex';
            content.style.display = 'none';
        });
    }
    
    // Real-time message checking (simplified)
    const visitorId = <?php echo $visitorId ? $visitorId : 'null'; ?>;
    
    if (visitorId) {
        // Configure polling interval
        const checkInterval = 5000; // 5 seconds
        
        // Start polling for new messages
        setInterval(function() {
            // In a real app, you would add AJAX here to check for new messages
            // For this demo, we'll just log that we're checking
            console.log("Checking for new messages...");
        }, checkInterval);
        
        // Handle form submission with better UX
        const chatForm = document.getElementById('chat-form');
        if (chatForm) {
            chatForm.addEventListener('submit', function(e) {
                // We won't prevent default here to allow normal form submission
                // But we'll enhance the UX
                
                const textarea = this.querySelector('textarea');
                const message = textarea.value.trim();
                
                if (!message) {
                    e.preventDefault();
                    return;
                }
                
                // Show sending indicator
                const sendBtn = this.querySelector('.send-btn');
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                sendBtn.disabled = true;
                
                // Let the form submit normally
            });
        }
    }
    
    // Emoji picker functionality (simplified)
    const emojiBtn = document.querySelector('.emoji-btn');
    if (emojiBtn && messageInput) {
        emojiBtn.addEventListener('click', function() {
            // In a real app, you would show an emoji picker
            // For this demo, we'll just insert a simple emoji
            messageInput.value += ' 😊';
            messageInput.focus();
            
            // Trigger the input event to resize the textarea
            const event = new Event('input');
            messageInput.dispatchEvent(event);
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
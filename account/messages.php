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

// Mark messages as read if viewing a specific conversation
if ($visitorId) {
    // Include widget_id in the filter if the column exists
    if ($hasWidgetIdColumn && $widgetId) {
        $db->query(
            "UPDATE messages SET `read` = 1 
            WHERE user_id = ? AND visitor_id = ? AND sender_type = ? AND widget_id = ?", 
            [$userId, $visitorId, 'visitor', $widgetId]
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
            // Include widget_id in the filter if the column exists
            if ($hasWidgetIdColumn && $widgetId) {
                $messages = $db->fetchAll(
                    "SELECT * FROM messages 
                    WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id = :widget_id
                    ORDER BY created_at ASC", 
                    ['user_id' => $userId, 'visitor_id' => $visitorId, 'widget_id' => $widgetId]
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
            // Include widget_id when inserting new messages
            if ($hasWidgetIdColumn && $widgetId) {
                // Insert message into database with widget_id using backticks for read field
                $db->query(
                    "INSERT INTO messages (user_id, visitor_id, widget_id, message, sender_type, `read`, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $userId,
                        $visitorId,
                        $widgetId,
                        $messageContent,
                        'agent',
                        0 // Not read by default
                    ]
                );
            } else {
                // Insert message into database without widget_id
                $db->query(
                    "INSERT INTO messages (user_id, visitor_id, message, sender_type, `read`, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())",
                    [
                        $userId,
                        $visitorId,
                        $messageContent,
                        'agent',
                        0 // Not read by default
                    ]
                );
            }
            
            // Update visitor's last activity
            $db->query(
                "UPDATE visitors SET last_active = NOW() WHERE id = ?",
                [$visitorId]
            );
            
            $messageSent = true;
            
            // Get the updated conversation
            if ($hasWidgetIdColumn && $widgetId) {
                $messages = $db->fetchAll(
                    "SELECT * FROM messages 
                    WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id = :widget_id
                    ORDER BY created_at ASC", 
                    ['user_id' => $userId, 'visitor_id' => $visitorId, 'widget_id' => $widgetId]
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

// Display widget_id for debugging if needed
$debugWidgetId = $widgetId ? $widgetId : 'Not set';

// Include header
include '../includes/header.php';
?>

<main class="container messages-container">
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
            <!-- Add Widget ID information -->
            <div class="stat-item">
                <span class="label">Widget ID:</span>
                <span class="id"><?php echo htmlspecialchars($debugWidgetId); ?></span>
            </div>
        </div>
    </div>

    <div class="messages-layout">
        <!-- Left sidebar: Conversation list -->
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2>Conversations</h2>
                <?php if ($visitorId): ?>
                <a href="messages.php" class="btn btn-sm btn-back">
                    <i class="fa fa-arrow-left"></i> Back to List
                </a>
                <?php endif; ?>
            </div>
            
            <div class="conversation-filters">
                <form method="get" class="search-form">
                    <div class="search-input-container">
                        <input type="text" name="search" placeholder="Search visitors..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fa fa-search"></i>
                        </button>
                        <?php if (!empty($searchTerm)): ?>
                        <a href="messages.php<?php echo $filter !== 'all' ? '?filter=' . htmlspecialchars($filter) : ''; ?>" class="clear-search">
                            <i class="fa fa-times"></i>
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
                            <a href="messages.php" class="btn btn-outline">Clear Search</a>
                        <?php elseif ($filter === 'unread'): ?>
                            <p>You have no unread messages.</p>
                        <?php elseif ($filter === 'read'): ?>
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
                                <?php echo substr($visitor['name'] ?? 'Anonymous', 0, 1); ?>
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
                            <i class="fa fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="messages.php?page=<?php echo $page + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . htmlspecialchars($filter) : ''; ?><?php echo $visitorId ? '&visitor=' . $visitorId : ''; ?>" class="pagination-button">
                            Next <i class="fa fa-chevron-right"></i>
                        </a>
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
                            <?php echo substr($visitorInfo['name'] ?? 'Anonymous', 0, 1); ?>
                        </div>
                        <div class="visitor-details">
                            <h3><?php echo htmlspecialchars($visitorInfo['name'] ?? 'Anonymous Visitor'); ?></h3>
                            <?php if (!empty($visitorInfo['email'])): ?>
                                <div class="visitor-email"><?php echo htmlspecialchars($visitorInfo['email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="visitor-meta">
                        <?php if (!empty($visitorInfo['url'])): ?>
                            <div class="meta-item">
                                <i class="fa fa-globe"></i>
                                <a href="<?php echo htmlspecialchars($visitorInfo['url']); ?>" target="_blank"><?php 
                                    $url = parse_url($visitorInfo['url']);
                                    echo isset($url['host']) ? htmlspecialchars($url['host']) : 'Unknown website';
                                ?></a>
                            </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            <i class="fa fa-clock"></i>
                            Last active: <?php 
                                $lastActive = strtotime($visitorInfo['last_active']);
                                $now = time();
                                $diff = $now - $lastActive;
                                
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
                        </div>
                        <div class="visitor-actions">
                            <a href="visitor-details.php?id=<?php echo $visitorId; ?>" class="btn btn-sm btn-outline">
                                <i class="fa fa-user"></i> View Profile
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">💬</div>
                            <p>No messages yet with this visitor.</p>
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
                                <?php echo substr($visitorInfo['name'] ?? 'Anonymous', 0, 1); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="message-bubble">
                                <div class="message-content"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                <div class="message-time">
                                    <?php echo date('g:i a', strtotime($message['created_at'])); ?>
                                    <?php if ($isAgent): ?>
                                        <?php if (isset($message['read']) && $message['read']): ?>
                                            <span class="read-status" title="Read">
                                                <i class="fa fa-check-double"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="read-status" title="Delivered">
                                                <i class="fa fa-check"></i>
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
                            <i class="fa fa-check-circle"></i> Message sent successfully.
                        </div>
                    <?php elseif ($messageError): ?>
                        <div class="message-status error">
                            <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($messageError); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="chat-form">
                        <textarea name="message" class="chat-input" placeholder="Type your message here..." required></textarea>
                        <div class="chat-actions">
                            <button type="button" class="btn btn-icon emoji-btn" title="Add Emoji">
                                <i class="fa fa-smile"></i>
                            </button>
                            <button type="submit" class="btn btn-primary send-btn">
                                <i class="fa fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
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
// Enhanced JavaScript for messages.php - Real-time Communication

document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to bottom of chat messages
    const chatMessages = document.querySelector('.chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Auto-resize textarea
    const chatInput = document.querySelector('.chat-input');
    if (chatInput) {
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
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
    
    // Real-time message checking
    const visitorId = getVisitorId();
    const widgetId = getWidgetId();
    const userId = getUserId();
    
    if (visitorId) {
        // Keep track of the latest message timestamp
        let lastMessageTime = getLastMessageTime();
        
        // Configure polling interval (shorter for more "real-time" feel)
        const pollingInterval = 2000; // 2 seconds
        
        // Start polling for new messages
        let messageCheckInterval = setInterval(function() {
            checkForNewMessages();
        }, pollingInterval);
        
        // More responsive polling on tab visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Immediately check for messages when tab becomes visible
                checkForNewMessages();
                
                // Reset polling to regular interval
                clearInterval(messageCheckInterval);
                messageCheckInterval = setInterval(function() {
                    checkForNewMessages();
                }, pollingInterval);
            } else {
                // Slow down polling when tab is not visible
                clearInterval(messageCheckInterval);
                messageCheckInterval = setInterval(function() {
                    checkForNewMessages();
                }, 10000); // 10 seconds when inactive
            }
        });
        
        // Function to check for new messages with error handling and retry
        function checkForNewMessages() {
            const timestamp = Date.now();
            const requestUrl = `check_messages.php?visitor=${visitorId}&since=${lastMessageTime}&_=${timestamp}`;
            
            fetch(requestUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response error: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        handleNewMessages(data);
                    } else {
                        console.error('Error from server:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error checking for messages:', error);
                    // We don't need to stop polling on error - it will try again
                });
        }
        
        // Function to handle new messages response
        function handleNewMessages(data) {
            // Process new messages
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(message => {
                    // Add message to chat if it doesn't already exist
                    addMessageIfNew(message);
                    
                    // Update last message time if this message is newer
                    const messageTime = new Date(message.created_at).getTime();
                    if (messageTime > lastMessageTime) {
                        lastMessageTime = messageTime;
                    }
                });
                
                // Scroll to bottom for new messages
                chatMessages.scrollTop = chatMessages.scrollHeight;
                
                // Play notification sound for visitor messages
                const visitorMessages = data.messages.filter(m => m.sender_type === 'visitor');
                if (visitorMessages.length > 0) {
                    playNotificationSound();
                }
            }
            
            // Update unread count in header
            if (data.unread_count !== undefined) {
                updateUnreadCount(data.unread_count);
            }
            
            // Update visitor status if available
            if (data.visitor_status) {
                updateVisitorStatus(data.visitor_status);
            }
        }
        
        // Function to add a message if it doesn't already exist
        function addMessageIfNew(message) {
            const messageId = message.id;
            
            // Check if message already exists in the DOM
            if (document.querySelector(`.message[data-id="${messageId}"]`)) {
                return; // Skip if already displayed
            }
            
            addMessageToChat(message);
        }
        
        // Function to add message to chat
        function addMessageToChat(message) {
            const isAgent = message.sender_type === 'agent';
            const messageEl = document.createElement('div');
            messageEl.className = `message ${isAgent ? 'agent' : 'visitor'}`;
            messageEl.dataset.id = message.id;
            
            // Get the message date for potential date divider
            const messageDate = new Date(message.created_at);
            const messageDateStr = messageDate.toISOString().split('T')[0];
            
            // Check if we need a new date divider
            const needsDateDivider = shouldAddDateDivider(messageDateStr);
            
            if (needsDateDivider) {
                // Add a date divider before the message
                const dividerEl = document.createElement('div');
                dividerEl.className = 'messages-date-divider';
                dividerEl.innerHTML = `<span>${formatDateForDivider(messageDate)}</span>`;
                chatMessages.appendChild(dividerEl);
            }
            
            // Build the message HTML
            let html = '';
            
            if (!isAgent) {
                // Get visitor initials from the current page
                const visitorInitial = getVisitorInitial();
                html += `<div class="message-avatar">
                    ${visitorInitial || 'A'}
                </div>`;
            }
            
            html += `<div class="message-bubble">
                <div class="message-content">${escapeHtml(message.message).replace(/\n/g, '<br>')}</div>
                <div class="message-time">
                    ${formatTime(messageDate)}
                    ${isAgent ? 
                        `<span class="read-status" title="${message.read ? 'Read' : 'Delivered'}">
                            <i class="fa fa-${message.read ? 'check-double' : 'check'}"></i>
                        </span>` : ''
                    }
                </div>
            </div>`;
            
            messageEl.innerHTML = html;
            chatMessages.appendChild(messageEl);
        }
        
        // Handle message form submission - enhanced for real-time
        const chatForm = document.querySelector('.chat-form');
        if (chatForm) {
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const textarea = this.querySelector('textarea');
                const message = textarea.value.trim();
                
                if (!message) return;
                
                // Clear the textarea
                textarea.value = '';
                textarea.style.height = 'auto';
                
                // Show sending indicator
                const sendBtn = this.querySelector('.send-btn');
                const originalBtnHtml = sendBtn.innerHTML;
                sendBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
                sendBtn.disabled = true;
                
                // Send the message via Ajax to avoid page reload
                const formData = new FormData();
                formData.append('message', message);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    // Re-enable the send button
                    sendBtn.innerHTML = originalBtnHtml;
                    sendBtn.disabled = false;
                    
                    // Force check for new messages which will include our sent message
                    setTimeout(() => {
                        checkForNewMessages();
                    }, 500);
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    sendBtn.innerHTML = originalBtnHtml;
                    sendBtn.disabled = false;
                    
                    // Show error message
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'message-status error';
                    errorMessage.innerHTML = '<i class="fa fa-exclamation-circle"></i> Message failed to send. Please try again.';
                    
                    const container = document.querySelector('.chat-input-container');
                    container.insertBefore(errorMessage, chatForm);
                    
                    // Remove error message after 3 seconds
                    setTimeout(() => {
                        errorMessage.style.opacity = '0';
                        setTimeout(() => {
                            errorMessage.remove();
                        }, 300);
                    }, 3000);
                });
            });
        }
        
        // Realtime visitor list updates - poll for conversation updates
        updateConversationList();
        setInterval(updateConversationList, 10000); // Check every 10 seconds
    }
    
    // Helper functions
    function getVisitorId() {
        // Extract from URL or data attribute
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('visitor') ? parseInt(urlParams.get('visitor')) : null;
    }
    
    function getWidgetId() {
        // Get from page data
        const widgetIdElement = document.querySelector('.stat-item .id');
        return widgetIdElement ? widgetIdElement.textContent.trim() : null;
    }
    
    function getUserId() {
        // This should be set on page load
        return window.userId || null;
    }
    
    function getLastMessageTime() {
        // Get timestamp of last message or current time
        const messages = document.querySelectorAll('.message');
        if (messages.length > 0) {
            const lastMessageTimeElement = messages[messages.length - 1].querySelector('.message-time');
            const timeText = lastMessageTimeElement.textContent.trim();
            // Try to parse time, if can't, return current time
            try {
                // This is an approximation as we only have the time string
                const today = new Date().toISOString().split('T')[0]; // "YYYY-MM-DD"
                const timeMatch = timeText.match(/(\d+):(\d+)\s*(am|pm)/i);
                
                if (timeMatch) {
                    let hours = parseInt(timeMatch[1]);
                    const minutes = parseInt(timeMatch[2]);
                    const ampm = timeMatch[3].toLowerCase();
                    
                    // Convert to 24-hour format
                    if (ampm === 'pm' && hours < 12) {
                        hours += 12;
                    } else if (ampm === 'am' && hours === 12) {
                        hours = 0;
                    }
                    
                    const dateTimeStr = `${today}T${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:00`;
                    return new Date(dateTimeStr).getTime();
                }
            } catch (e) {
                console.error('Error parsing last message time:', e);
            }
        }
        return Date.now();
    }
    
    function getVisitorInitial() {
        const visitorName = document.querySelector('.visitor-details h3')?.textContent.trim();
        return visitorName ? visitorName.charAt(0) : 'A';
    }
    
    function formatTime(date) {
        // Format time as "h:mm AM/PM"
        return date.toLocaleTimeString([], {hour: 'numeric', minute:'2-digit'});
    }
    
    function formatDateForDivider(date) {
        // Format date as "Month Day, Year"
        return date.toLocaleDateString([], {month: 'long', day: 'numeric', year: 'numeric'});
    }
    
    function shouldAddDateDivider(messageDate) {
        // Check if we need a new date divider
        const dividers = document.querySelectorAll('.messages-date-divider');
        if (dividers.length === 0) return true;
        
        // Get the last message element
        const messages = document.querySelectorAll('.message');
        if (messages.length === 0) return true;
        
        const lastMessage = messages[messages.length - 1];
        const lastMessageTime = lastMessage.querySelector('.message-time').textContent;
        
        // This is an approximation - we're checking if the day part of the date has changed
        // In a production system, you would store the full date with each message
        // For simplicity here, we'll just check if the divider contains today's date
        const lastDivider = dividers[dividers.length - 1];
        const lastDividerText = lastDivider.textContent.trim();
        
        // Get today's date for comparison
        const today = formatDateForDivider(new Date(messageDate));
        
        return !lastDividerText.includes(today);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function updateUnreadCount(count) {
        const unreadCounter = document.querySelector('.stat-item:nth-child(2) .count');
        if (unreadCounter) {
            unreadCounter.textContent = count;
        }
    }
    
    function updateVisitorStatus(status) {
        const statusElement = document.querySelector('.meta-item:nth-child(2)');
        if (statusElement) {
            const icon = statusElement.querySelector('i');
            const text = statusElement.querySelector('i').nextSibling;
            
            if (status.is_active) {
                icon.className = 'fa fa-circle text-success';
                text.textContent = ' Online now';
            } else if (status.last_active) {
                icon.className = 'fa fa-clock';
                
                // Format the time
                const lastActive = new Date(status.last_active);
                const now = new Date();
                const diff = Math.floor((now - lastActive) / 1000); // seconds
                
                let timeText = '';
                if (diff < 60) {
                    timeText = 'Just now';
                } else if (diff < 3600) {
                    timeText = Math.floor(diff / 60) + ' minutes ago';
                } else if (diff < 86400) {
                    timeText = Math.floor(diff / 3600) + ' hours ago';
                } else {
                    timeText = lastActive.toLocaleDateString() + ' at ' + lastActive.toLocaleTimeString([], {hour: 'numeric', minute:'2-digit'});
                }
                
                text.textContent = ' Last active: ' + timeText;
            }
        }
    }
    
    function playNotificationSound() {
        // Create audio element for notification sound
        try {
            const audio = new Audio('/sounds/notification.mp3');
            audio.volume = 0.5;
            audio.play().catch(e => console.log('Could not play notification sound:', e));
        } catch (e) {
            console.log('Notification sound not supported');
        }
    }
    
    function updateConversationList() {
        // Don't update if we have a visitor selected - avoid disruption
        if (visitorId) return;
        
        // Get current filter and search params
        const urlParams = new URLSearchParams(window.location.search);
        const filter = urlParams.get('filter') || 'all';
        const search = urlParams.get('search') || '';
        const page = urlParams.get('page') || '1';
        
        fetch(`conversation_list.php?filter=${filter}&search=${search}&page=${page}&_=${Date.now()}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response error');
                return response.text();
            })
            .then(html => {
                // Replace the conversation list content
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                
                const newList = tempDiv.querySelector('.conversation-list');
                const currentList = document.querySelector('.conversation-list');
                
                if (newList && currentList) {
                    // Only update if content has changed
                    if (newList.innerHTML !== currentList.innerHTML) {
                        currentList.innerHTML = newList.innerHTML;
                        
                        // Update unread counts if present
                        const unreadTotal = tempDiv.querySelector('.stat-item:nth-child(2) .count');
                        const currentUnreadTotal = document.querySelector('.stat-item:nth-child(2) .count');
                        
                        if (unreadTotal && currentUnreadTotal && 
                            unreadTotal.textContent !== currentUnreadTotal.textContent) {
                            currentUnreadTotal.textContent = unreadTotal.textContent;
                            
                            // Play notification if unread count increased
                            if (parseInt(unreadTotal.textContent) > parseInt(currentUnreadTotal.textContent)) {
                                playNotificationSound();
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error updating conversation list:', error);
            });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
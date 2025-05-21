<?php
$pageTitle = 'Chat';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Check subscription status
if (!isSubscriptionActive($user)) {
    $_SESSION['message'] = 'Your subscription is inactive. Please subscribe to use the chat feature.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/account/billing.php');
}

// Check if visitor ID is provided
if (!isset($_GET['visitor']) || empty($_GET['visitor'])) {
    redirect(SITE_URL . '/account/dashboard.php');
}

$visitorId = $_GET['visitor'];

// Get visitor information
$visitor = $db->fetch(
    "SELECT * FROM visitors WHERE id = :id AND user_id = :user_id",
    ['id' => $visitorId, 'user_id' => $userId]
);

if (!$visitor) {
    redirect(SITE_URL . '/account/dashboard.php');
}

// Get the widget_id associated with this visitor's messages
$visitorWidget = $db->fetch(
    "SELECT widget_id FROM messages 
     WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id IS NOT NULL
     ORDER BY created_at DESC 
     LIMIT 1",
    ['user_id' => $userId, 'visitor_id' => $visitorId]
);

$widgetId = $visitorWidget ? $visitorWidget['widget_id'] : null;

// Store widget_id in session for use in send-message.php
$_SESSION['current_visitor_widget_id'] = $widgetId;

// Log for debugging
error_log("Chat session with visitor {$visitorId} using widget_id: " . ($widgetId ?? 'unknown'));

// Get all messages for this visitor
$messages = $db->fetchAll(
    "SELECT * FROM messages WHERE user_id = :user_id AND visitor_id = :visitor_id ORDER BY created_at ASC",
    ['user_id' => $userId, 'visitor_id' => $visitorId]
);

// Mark all messages as read
$db->query(
    "UPDATE messages SET `read` = 1 WHERE user_id = :user_id AND visitor_id = :visitor_id AND sender_type = 'visitor'",
    ['user_id' => $userId, 'visitor_id' => $visitorId]
);

// Include header
include '../includes/header.php';
?>

<style>
/* Chat Container Styles */
.chat-container {
    display: flex;
    flex-direction: column;
    max-width: 1000px;
    height: calc(100vh - 120px);
    margin: 0 auto;
    background-color: #f5f7fb;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Chat Header Styles */
.chat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 15px 20px;
    background-color: #ffffff;
    border-bottom: 1px solid #e6e9f0;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.visitor-info h1 {
    margin: 0 0 5px;
    font-size: 1.5rem;
    color: #333;
}

.visitor-email, .visitor-page, .visitor-widget {
    margin: 3px 0;
    font-size: 0.85rem;
    color: #666;
}

.visitor-meta {
    text-align: right;
    font-size: 0.85rem;
    color: #888;
}

.visitor-last-active, .visitor-ip {
    margin: 3px 0;
}

/* Chat Messages Container */
.chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background-color: #f5f7fb;
    background-image: linear-gradient(rgba(255, 255, 255, 0.7) 1px, transparent 1px),
                     linear-gradient(90deg, rgba(255, 255, 255, 0.7) 1px, transparent 1px);
    background-size: 20px 20px;
}

.no-messages {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
    color: #999;
    font-style: italic;
}

/* Message Styles */
.chat-message {
    max-width: 80%;
    margin-bottom: 15px;
    padding: 12px 16px;
    border-radius: 18px;
    position: relative;
    animation: fadeIn 0.3s;
    word-wrap: break-word;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.chat-message.visitor {
    margin-right: auto;
    background-color: #ffffff;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    border-bottom-left-radius: 4px;
}

.chat-message.agent {
    margin-left: auto;
    background-color: #0084ff;
    color: white;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    border-bottom-right-radius: 4px;
}

.message-content {
    margin-bottom: 6px;
    line-height: 1.4;
}

.message-meta {
    display: flex;
    justify-content: flex-end;
    font-size: 0.7rem;
    opacity: 0.8;
    margin-top: 3px;
}

/* Chat Input Area */
.chat-input {
    padding: 15px;
    background-color: #ffffff;
    border-top: 1px solid #e6e9f0;
}

#chat-form {
    display: flex;
    gap: 10px;
}

#message-input {
    flex: 1;
    padding: 12px 15px;
    height: 24px;
    max-height: 120px;
    border: 1px solid #ddd;
    border-radius: 24px;
    resize: none;
    outline: none;
    font-family: inherit;
    font-size: 0.95rem;
    transition: border-color 0.3s, box-shadow 0.3s;
}

#message-input:focus {
    border-color: #0084ff;
    box-shadow: 0 0 0 2px rgba(0, 132, 255, 0.2);
}

#send-button, #refresh-button {
    padding: 0 20px;
    background-color: #0084ff;
    color: white;
    border: none;
    border-radius: 24px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s;
}

#send-button:hover, #refresh-button:hover {
    background-color: #0069d9;
}

#send-button:disabled {
    background-color: #cccccc;
    cursor: not-allowed;
}

.chat-controls {
    display: flex;
    gap: 10px;
}

#refresh-button {
    background-color: #6c757d;
}

#refresh-button:hover {
    background-color: #5a6268;
}

/* Loading Indicator */
.loading-indicator {
    display: none;
    justify-content: center;
    align-items: center;
    height: 40px;
    margin-top: 10px;
}

.loading-dot {
    width: 8px;
    height: 8px;
    margin: 0 3px;
    background-color: #0084ff;
    border-radius: 50%;
    animation: loading-bounce 1.4s infinite ease-in-out both;
}

.loading-dot:nth-child(1) { animation-delay: -0.32s; }
.loading-dot:nth-child(2) { animation-delay: -0.16s; }

@keyframes loading-bounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

/* Responsiveness */
@media (max-width: 768px) {
    .chat-container {
        height: calc(100vh - 80px);
        border-radius: 0;
        margin: 0;
    }
    
    .chat-header {
        flex-direction: column;
    }
    
    .visitor-meta {
        text-align: left;
        margin-top: 10px;
    }
    
    .chat-message {
        max-width: 90%;
    }
}
</style>

<main class="container chat-container">
    <div class="chat-header">
        <div class="visitor-info">
            <h1><?php echo $visitor['name'] ?? 'Anonymous Visitor'; ?></h1>
            <?php if (!empty($visitor['email'])): ?>
                <p class="visitor-email"><?php echo $visitor['email']; ?></p>
            <?php endif; ?>
            <?php if (!empty($visitor['url'])): ?>
                <p class="visitor-page">
                    Current page: <a href="<?php echo $visitor['url']; ?>" target="_blank"><?php echo parse_url($visitor['url'], PHP_URL_PATH); ?></a>
                </p>
            <?php endif; ?>
            <?php if (!empty($widgetId)): ?>
                <p class="visitor-widget">Widget ID: <?php echo $widgetId; ?></p>
            <?php endif; ?>
        </div>
        <div class="visitor-meta">
            <p class="visitor-last-active">
                Last active: 
                <?php 
                $lastActive = strtotime($visitor['last_active']);
                $now = time();
                $diff = $now - $lastActive;
                
                if ($diff < 60) {
                    echo 'Just now';
                } elseif ($diff < 3600) {
                    echo floor($diff / 60) . ' min ago';
                } elseif ($diff < 86400) {
                    echo floor($diff / 3600) . ' hour(s) ago';
                } else {
                    echo date('M j, g:i a', $lastActive);
                }
                ?>
            </p>
            <p class="visitor-ip">IP: <?php echo $visitor['ip_address']; ?></p>
        </div>
    </div>
    
    <div class="chat-messages" id="chat-messages">
        <?php if (empty($messages)): ?>
            <div class="no-messages">
                <p>No messages yet. Send a message to start the conversation.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <div class="chat-message <?php echo $message['sender_type']; ?>">
                    <div class="message-content"><?php echo $message['message']; ?></div>
                    <div class="message-meta">
                        <span class="message-time"><?php echo date('g:i a', strtotime($message['created_at'])); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="loading-indicator" id="loading-indicator">
            <div class="loading-dot"></div>
            <div class="loading-dot"></div>
            <div class="loading-dot"></div>
        </div>
    </div>
    
    <div class="chat-input">
        <form id="chat-form">
            <textarea id="message-input" placeholder="Type your message here..." required></textarea>
            <div class="chat-controls">
                <button type="button" id="refresh-button">Refresh</button>
                <button type="submit" id="send-button">Send</button>
            </div>
        </form>
    </div>
</main>

<script>
// JavaScript for handling real-time chat
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const refreshButton = document.getElementById('refresh-button');
    const loadingIndicator = document.getElementById('loading-indicator');
    const visitorId = "<?php echo $visitorId; ?>";
    let lastMessageId = <?php echo !empty($messages) ? end($messages)['id'] : 0; ?>;
    let isFetching = false;
    
    // Auto resize textarea based on content
    function autoResizeTextarea() {
        messageInput.style.height = 'auto';
        messageInput.style.height = (messageInput.scrollHeight < 120 ? messageInput.scrollHeight : 120) + 'px';
    }
    
    messageInput.addEventListener('input', autoResizeTextarea);
    
    // Scroll to bottom of chat
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    scrollToBottom();
    
    // Show loading indicator
    function showLoading() {
        loadingIndicator.style.display = 'flex';
    }
    
    // Hide loading indicator
    function hideLoading() {
        loadingIndicator.style.display = 'none';
    }
    
    // Send message
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        // Disable button and show loading
        sendButton.disabled = true;
        showLoading();
        
        // Send message to server
        fetch('send-message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                visitor_id: visitorId,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                // Add message to chat
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message agent';
                messageDiv.innerHTML = `
                    <div class="message-content">${message}</div>
                    <div class="message-meta">
                        <span class="message-time">Just now</span>
                    </div>
                `;
                chatMessages.appendChild(messageDiv);
                
                // Clear input and reset height
                messageInput.value = '';
                messageInput.style.height = '24px';
                
                // Scroll to bottom
                scrollToBottom();
                
                // Update last message ID
                lastMessageId = data.message_id;
            } else {
                alert('Failed to send message. Please try again.');
            }
            
            // Enable button
            sendButton.disabled = false;
            messageInput.focus();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            
            // Enable button and hide loading
            sendButton.disabled = false;
            hideLoading();
        });
    });
    
    // Fetch new messages
    function fetchMessages(manual = false) {
        if (isFetching) return;
        
        isFetching = true;
        if (manual) showLoading();
        
        fetch(`get-messages.php?visitor_id=${visitorId}&last_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
            isFetching = false;
            if (manual) hideLoading();
            
            if (data.success && data.messages.length > 0) {
                // Add new messages to chat
                data.messages.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `chat-message ${msg.sender_type}`;
                    messageDiv.innerHTML = `
                        <div class="message-content">${msg.message}</div>
                        <div class="message-meta">
                            <span class="message-time">${formatTime(msg.created_at)}</span>
                        </div>
                    `;
                    chatMessages.appendChild(messageDiv);
                    
                    // Update last message ID
                    lastMessageId = msg.id;
                });
                
                // Scroll to bottom
                scrollToBottom();
                
                // Play notification sound if it's a visitor message and not a manual refresh
                if (!manual && data.messages.some(msg => msg.sender_type === 'visitor')) {
                    playNotificationSound();
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            isFetching = false;
            if (manual) hideLoading();
        });
    }
    
    // Format time
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }
    
    // Play notification sound
    function playNotificationSound() {
        // You can implement a notification sound here
        // Example:
        // const audio = new Audio('/assets/sounds/notification.mp3');
        // audio.play().catch(e => console.log('Audio play error:', e));
    }
    
    // Manual refresh button
    refreshButton.addEventListener('click', function() {
        fetchMessages(true);
    });
    
    // Poll for new messages every 5 seconds
    const pollingInterval = setInterval(fetchMessages, 5000);
    
    // Clear interval when page is unloaded
    window.addEventListener('beforeunload', function() {
        clearInterval(pollingInterval);
    });
    
    // Allow Enter key to send message, Shift+Enter for new line
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!sendButton.disabled) {
                chatForm.dispatchEvent(new Event('submit'));
            }
        }
    });
    
    // Focus input on page load
    messageInput.focus();
    
    // Update visitor activity status periodically
    function updateVisitorStatus() {
        const lastActiveElement = document.querySelector('.visitor-last-active');
        if (!lastActiveElement) return;
        
        const lastActiveText = lastActiveElement.textContent;
        const match = lastActiveText.match(/Last active: (.*)/);
        if (match) {
            fetch(`get-visitor-status.php?visitor_id=${visitorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    lastActiveElement.textContent = `Last active: ${data.last_active}`;
                }
            })
            .catch(error => {
                console.error('Error updating visitor status:', error);
            });
        }
    }
    
    // Update visitor status every 30 seconds
    setInterval(updateVisitorStatus, 30000);
    
    // Handle mobile devices
    function setupMobileView() {
        if (window.innerWidth <= 768) {
            // Adjust for mobile virtual keyboard
            const originalHeight = window.innerHeight;
            
            window.addEventListener('resize', function() {
                // If height is significantly smaller, virtual keyboard is likely open
                if (window.innerHeight < originalHeight * 0.8) {
                    document.body.classList.add('keyboard-open');
                    scrollToBottom();
                } else {
                    document.body.classList.remove('keyboard-open');
                }
            });
            
            // Ensure input is properly focused on mobile
            messageInput.addEventListener('focus', function() {
                setTimeout(scrollToBottom, 300);
            });
        }
    }
    
    setupMobileView();
});
</script>

<?php include '../includes/footer.php'; ?>
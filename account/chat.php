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

// Set extra CSS and JS
$extraCss = ['/assets/css/chat.css'];
$extraJs = ['/assets/js/chat.js'];

// Include header
include '../includes/header.php';
?>

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
    </div>
    
    <div class="chat-input">
        <form id="chat-form">
            <textarea id="message-input" placeholder="Type your message here..." required></textarea>
            <button type="submit" id="send-button">Send</button>
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
    const visitorId = "<?php echo $visitorId; ?>";
    let lastMessageId = <?php echo !empty($messages) ? end($messages)['id'] : 0; ?>;
    
    // Scroll to bottom of chat
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    // Send message
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        // Disable button
        sendButton.disabled = true;
        
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
                
                // Clear input
                messageInput.value = '';
                
                // Scroll to bottom
                chatMessages.scrollTop = chatMessages.scrollHeight;
                
                // Update last message ID
                lastMessageId = data.message_id;
            } else {
                alert('Failed to send message. Please try again.');
            }
            
            // Enable button
            sendButton.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            
            // Enable button
            sendButton.disabled = false;
        });
    });
    
    // Poll for new messages
    function fetchMessages() {
        fetch(`get-messages.php?visitor_id=${visitorId}&last_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
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
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Format time
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }
    
    // Poll for new messages every 5 seconds
    setInterval(fetchMessages, 5000);
    
    // Allow Enter key to send message
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
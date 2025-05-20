<?php
$pageTitle = 'Dashboard';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Fixed error handling for messages
$messages = $db->fetchAll(
    "SELECT * FROM messages WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5", 
    ['user_id' => $userId]
) ?: [];

// Fixed unread messages count
$unreadCount = $db->fetch(
    "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND sender_type = 'visitor' AND `read` = 0", 
    ['user_id' => $userId]
);
$unreadMessages = $unreadCount ? $unreadCount['count'] : 0;

// Fixed visitors retrieval - reducing to 3 for better display
$visitors = $db->fetchAll(
    "SELECT * FROM visitors WHERE user_id = :user_id ORDER BY last_active DESC LIMIT 3", 
    ['user_id' => $userId]
) ?: [];

// Proper subscription handling with null checks
$subscription = null;
$isSubscriptionActive = false;
$subscriptionName = 'Free Plan';
$messageLimit = 0;
$subscriptionExpiry = 'N/A';

if (isset($user['subscription_id']) && !empty($user['subscription_id'])) {
    $subscription = getSubscriptionById($user['subscription_id']);
    $isSubscriptionActive = isSubscriptionActive($user);
    
    if ($subscription) {
        $subscriptionName = $subscription['name'] ?? 'Unknown Plan';
        $messageLimit = $subscription['message_limit'] ?? 0;
    }
    
    if (isset($user['subscription_expiry']) && !empty($user['subscription_expiry'])) {
        $subscriptionExpiry = formatDate($user['subscription_expiry']);
    }
}

// Get message count safely
$messageCount = 0;
try {
    $messageCount = getMessageCount($userId);
} catch (Exception $e) {
    // Handle silently
}

// Get visitor count safely
$visitorCount = 0;
try {
    $visitorCountResult = $db->fetch(
        "SELECT COUNT(*) as count FROM visitors WHERE user_id = :user_id", 
        ['user_id' => $userId]
    );
    $visitorCount = $visitorCountResult ? $visitorCountResult['count'] : 0;
} catch (Exception $e) {
    // Handle silently
}

// Get the full site URL for embedding
$siteUrl = SITE_URL;
// Ensure URL doesn't have trailing slash
$siteUrl = rtrim($siteUrl, '/');

// Include header
include '../includes/header.php';
?>

<style>
/* Concise dashboard styles with fixes for overlapping */
.dashboard-container {
  max-width: 1300px;
  margin: 0 auto;
  padding: 1.5rem;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.widget-status {
  display: flex;
  align-items: center;
  background-color: white;
  padding: 0.5rem 1rem;
  border-radius: 1rem;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.status-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-right: 8px;
}

.status-dot.active { background-color: #28a745; }
.status-dot.inactive { background-color: #dc3545; }

.dashboard-welcome {
  background-color: white;
  border-radius: 0.75rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.subscription-status {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  margin-top: 0.5rem;
}

.subscription-status.active {
  background-color: rgba(40, 167, 69, 0.1);
  border-left: 4px solid #28a745;
}

.subscription-status.inactive {
  background-color: rgba(220, 53, 69, 0.1);
  border-left: 4px solid #dc3545;
}

.status-indicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-right: 10px;
}

.subscription-status.active .status-indicator { background-color: #28a745; }
.subscription-status.inactive .status-indicator { background-color: #dc3545; }

.dashboard-content {
  margin-bottom: 2rem;
  min-height: calc(100vh - 350px);
}

/* Stats Cards */
.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background-color: white;
  border-radius: 0.75rem;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.stat-icon {
  font-size: 2rem;
  margin-right: 1rem;
  opacity: 0.8;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
}

/* Main layout grid */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.dashboard-col {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Cards */
.dashboard-card {
  background-color: white;
  border-radius: 0.75rem;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  background-color: #f8f9fa;
  border-bottom: 1px solid #eaeaea;
}

.card-body {
  padding: 1.5rem;
  flex: 1;
}

/* Fix overflow issues */
.messages-card, .visitors-card {
  max-height: 500px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.messages-card .card-body, .visitors-card .card-body {
  overflow-y: auto;
}

.embed-code {
  padding: 1rem;
  font-family: monospace;
  white-space: pre;
  overflow-x: auto;
  font-size: 0.85rem;
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  margin: 0;
}

.copy-btn {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  background-color: white;
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  z-index: 1;
}

.copy-btn.copied {
  background-color: #28a745;
  color: white;
}

/* Visitor and message items */
.visitor-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-radius: 0.5rem;
  background-color: #f8f9fa;
  margin-bottom: 0.75rem;
}

.visitor-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.visitor-avatar {
  width: 40px;
  height: 40px;
  background-color: #4a6cf7;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.1rem;
}

.status-active::before {
  content: '';
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #28a745;
  margin-right: 0.35rem;
}

.message-item {
  padding: 1rem;
  border-radius: 0.5rem;
  background-color: #f8f9fa;
  position: relative;
  margin-bottom: 0.75rem;
}

.message-item.visitor {
  border-left: 3px solid #4a6cf7;
}

.message-item.unread {
  background-color: rgba(74, 108, 247, 0.05);
  border-left: 3px solid #4a6cf7;
}

/* Quick actions */
.quick-actions {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.quick-action-btn {
  background-color: #f8f9fa;
  border-radius: 0.5rem;
  padding: 1.25rem 1rem;
  text-align: center;
  text-decoration: none;
  color: #212529;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px solid #eaeaea;
}

.quick-action-btn:hover {
  color: #4a6cf7;
  border-color: #4a6cf7;
}

.quick-action-btn i {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
  color: #4a6cf7;
}

/* Responsive adjustments */
@media (max-width: 992px) {
  .dashboard-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
  .dashboard-stats { grid-template-columns: 1fr; }
  .quick-actions { grid-template-columns: 1fr; }
}

/* Widget example preview */
.widget-preview {
  position: relative;
  margin-top: 15px;
  border: 1px dashed #ccc;
  padding: 10px;
  border-radius: 5px;
  background-color: #f9f9f9;
}

.widget-preview-label {
  position: absolute;
  top: -10px;
  left: 10px;
  background: white;
  padding: 0 5px;
  font-size: 12px;
  color: #666;
}

.api-usage-list {
  margin: 10px 0;
  padding-left: 20px;
}

.api-usage-list li {
  margin-bottom: 5px;
  font-size: 0.9rem;
}

.info-box {
  background-color: #e7f3ff;
  border-left: 4px solid #4a6cf7;
  padding: 12px 15px;
  margin-top: 15px;
  border-radius: 4px;
  font-size: 0.9rem;
}
</style>

<main class="container dashboard-container">
    <div class="dashboard-header">
        <h1>Dashboard</h1>
        <div class="widget-status">
            <span class="status-dot <?php echo $isSubscriptionActive ? 'active' : 'inactive'; ?>"></span>
            <span>Widget: <?php echo $isSubscriptionActive ? 'Active' : 'Inactive'; ?></span>
        </div>
    </div>
    
    <div class="dashboard-welcome">
        <h2>Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>!</h2>
        
        <div class="subscription-status <?php echo $isSubscriptionActive ? 'active' : 'inactive'; ?>">
            <span class="status-indicator"></span>
            <span class="status-text">
                <?php if ($isSubscriptionActive): ?>
                    Your subscription is active (<?php echo htmlspecialchars($subscriptionName); ?>) - 
                    Expires on <?php echo htmlspecialchars($subscriptionExpiry); ?>
                <?php else: ?>
                    Your subscription is inactive. 
                    <a href="<?php echo htmlspecialchars(SITE_URL); ?>/account/billing.php" class="btn btn-sm btn-upgrade">Upgrade Now</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    
    <!-- Main content area -->
    <div class="dashboard-content">
        <!-- Stats cards in a single row -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-content">
                    <div class="stat-title">Messages</div>
                    <div class="stat-value"><?php echo number_format($messageCount ?? 0); ?></div>
                    <?php if ($isSubscriptionActive && isset($messageLimit) && $messageLimit > 0): ?>
                        <div class="stat-subtitle">Limit: <?php echo number_format($messageLimit); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔔</div>
                <div class="stat-content">
                    <div class="stat-title">Unread Messages</div>
                    <div class="stat-value"><?php echo number_format($unreadMessages); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <div class="stat-content">
                    <div class="stat-title">Visitors</div>
                    <div class="stat-value"><?php echo number_format($visitorCount); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Two column layout -->
        <div class="dashboard-grid">
            <!-- Column 1: Widget code and recent visitors -->
            <div class="dashboard-col">
                <div class="dashboard-card embed-card">
                    <div class="card-header">
                        <h3><i class="fa fa-code"></i> Widget Embed Code</h3>
                    </div>
                    <div class="card-body">
                        <p>Copy and paste this code into your website just before the closing <code>&lt;/body&gt;</code> tag:</p>
                        
                        <div class="code-container" style="position: relative;">
                            <pre class="embed-code"><code>&lt;script&gt;
var WIDGET_ID = "<?php echo htmlspecialchars($user['widget_id'] ?? ''); ?>";
&lt;/script&gt;
&lt;script src="<?php echo htmlspecialchars($siteUrl); ?>/widget/embed.js" async&gt;&lt;/script&gt;</code></pre>
                            <button class="copy-btn" data-copy="<script>
var WIDGET_ID = &quot;<?php echo htmlspecialchars($user['widget_id'] ?? ''); ?>&quot;;
</script>
<script src=&quot;<?php echo htmlspecialchars($siteUrl); ?>/widget/embed.js&quot; async></script>">
                                <i class="fa fa-copy"></i> Copy
                            </button>
                        </div>
                        
                        <div class="info-box">
                            <strong>Important:</strong> Make sure your website allows loading scripts from <?php echo htmlspecialchars(parse_url($siteUrl, PHP_URL_HOST)); ?>. The chat widget needs to connect to our servers to function properly.
                        </div>
                        
                        <div class="widget-preview">
                            <span class="widget-preview-label">Widget Example</span>
                            <div style="text-align: right; padding: 10px;">
                                <div style="display: inline-block; width: 50px; height: 50px; background-color: #4a6cf7; border-radius: 50%; color: white; text-align: center; line-height: 50px; font-size: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
                                    <i class="fa fa-comments"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card visitors-card">
                    <div class="card-header">
                        <h3><i class="fa fa-users"></i> Recent Visitors</h3>
                        <a href="visitors.php" class="view-all-link">View All <i class="fa fa-arrow-right"></i></a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($visitors)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">👤</div>
                                <p>No visitors yet. Once your widget is installed, visitor data will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="visitor-list">
                                <?php foreach($visitors as $visitor): ?>
                                    <div class="visitor-item">
                                        <div class="visitor-info">
                                            <div class="visitor-avatar">
                                                <?php echo substr($visitor['name'] ?? 'A', 0, 1); ?>
                                            </div>
                                            <div class="visitor-details">
                                                <div class="visitor-name"><?php echo htmlspecialchars($visitor['name'] ?? 'Anonymous'); ?></div>
                                                <?php if (!empty($visitor['email'])): ?>
                                                    <div class="visitor-email"><?php echo htmlspecialchars($visitor['email']); ?></div>
                                                <?php endif; ?>
                                                <div class="visitor-time">
                                                    <?php 
                                                    $lastActive = strtotime($visitor['last_active']);
                                                    $now = time();
                                                    $diff = $now - $lastActive;
                                                    
                                                    if ($diff < 60) {
                                                        echo '<span class="status-active">Active now</span>';
                                                    } elseif ($diff < 3600) {
                                                        echo floor($diff / 60) . ' min ago';
                                                    } elseif ($diff < 86400) {
                                                        echo floor($diff / 3600) . ' hour(s) ago';
                                                    } else {
                                                        echo date('M j, g:i a', $lastActive);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="visitor-actions">
                                            <a href="chat.php?visitor=<?php echo htmlspecialchars($visitor['id'] ?? ''); ?>" class="btn btn-sm btn-primary">
                                                <i class="fa fa-comment"></i> Chat
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Column 2: Recent messages and quick actions -->
            <div class="dashboard-col">
                <div class="dashboard-card messages-card">
                    <div class="card-header">
                        <h3><i class="fa fa-comments"></i> Recent Messages</h3>
                        <a href="messages.php" class="view-all-link">View All <i class="fa fa-arrow-right"></i></a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">💬</div>
                                <p>No messages yet. When visitors chat with you, messages will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="message-list">
                                <?php foreach($messages as $message): ?>
                                    <div class="message-item <?php echo $message['sender_type'] === 'visitor' ? 'visitor' : 'agent'; ?> <?php echo isset($message['read']) && $message['read'] ? 'read' : 'unread'; ?>">
                                        <div class="message-header">
                                            <span class="message-sender"><?php echo $message['sender_type'] === 'visitor' ? 'Visitor' : 'You'; ?></span>
                                            <span class="message-time"><?php echo date('M j, g:i a', strtotime($message['created_at'])); ?></span>
                                        </div>
                                        <div class="message-content"><?php echo htmlspecialchars($message['message'] ?? ''); ?></div>
                                        <?php if ($message['sender_type'] === 'visitor' && isset($message['read']) && !$message['read']): ?>
                                            <a href="chat.php?visitor=<?php echo htmlspecialchars($message['visitor_id'] ?? ''); ?>" class="reply-btn">Reply</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="dashboard-card quick-actions-card">
                    <div class="card-header">
                        <h3><i class="fa fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="chat.php" class="quick-action-btn">
                                <i class="fa fa-comments"></i>
                                <span>Chat Console</span>
                            </a>
                            <a href="widget-settings.php" class="quick-action-btn">
                                <i class="fa fa-cog"></i>
                                <span>Widget Settings</span>
                            </a>
                            <a href="reports.php" class="quick-action-btn">
                                <i class="fa fa-chart-bar"></i>
                                <span>Reports</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if ($isSubscriptionActive): ?>
                <div class="dashboard-card api-card">
                    <div class="card-header">
                        <h3><i class="fa fa-key"></i> API Access</h3>
                    </div>
                    <div class="card-body">
                        <p>Your API key allows you to integrate our chat system with your own applications and services.</p>
                        
                        <div class="api-key-container" style="position: relative;">
                            <code class="api-key"><?php echo htmlspecialchars($user['api_key'] ?? 'No API key available'); ?></code>
                            <button class="copy-btn" data-copy="<?php echo htmlspecialchars($user['api_key'] ?? ''); ?>">
                                <i class="fa fa-copy"></i> Copy
                            </button>
                        </div>
                        
                        <p><strong>What can you do with the API?</strong></p>
                        <ul class="api-usage-list">
                            <li>Retrieve chat history and visitor data</li>
                            <li>Send automated messages to visitors</li>
                            <li>Integrate with your CRM system</li>
                            <li>Build custom reporting dashboards</li>
                            <li>Create your own chat interface</li>
                        </ul>
                        
                        <p class="note">Keep this key secret and don't share it with anyone. <a href="api-docs.php">View API Documentation</a></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copy button functionality
    const copyButtons = document.querySelectorAll('.copy-btn');
    
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const textToCopy = this.getAttribute('data-copy');
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            // Show copied message
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fa fa-check"></i> Copied!';
            this.classList.add('copied');
            
            setTimeout(() => {
                this.innerHTML = originalText;
                this.classList.remove('copied');
            }, 2000);
        });
    });
    
    // Check for new messages periodically
    const checkNewMessages = () => {
        fetch('ajax/check-new-messages.php')
            .then(response => response.json())
            .then(data => {
                if (data.new_messages > 0) {
                    // Update unread count without page refresh
                    const unreadElement = document.querySelector('.stat-card:nth-child(2) .stat-value');
                    if (unreadElement) {
                        unreadElement.textContent = data.new_messages;
                    }
                }
            })
            .catch(error => console.error('Error checking messages:', error));
    };
    
    // Check for new messages every 60 seconds
    setInterval(checkNewMessages, 60000);
});
</script>

<?php include '../includes/footer.php'; ?>
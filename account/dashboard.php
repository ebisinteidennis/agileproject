<?php
$pageTitle = 'Dashboard';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Get user's widget_id - this is important for filtering messages by widget
$widgetId = isset($user['widget_id']) ? $user['widget_id'] : null;

// Check if widget_id column exists in messages table
$hasWidgetIdColumn = false;
try {
    $columnsResult = $db->fetchAll("SHOW COLUMNS FROM messages LIKE 'widget_id'");
    $hasWidgetIdColumn = !empty($columnsResult);
} catch (Exception $e) {
    error_log("Error checking for widget_id column: " . $e->getMessage());
}

// Modified query to include widget_id when fetching messages if the column exists
$messagesQuery = "SELECT * FROM messages WHERE user_id = :user_id";
if ($hasWidgetIdColumn && $widgetId) {
    $messagesQuery .= " AND widget_id = :widget_id";
}
$messagesQuery .= " ORDER BY created_at DESC LIMIT 5";
$messagesParams = ['user_id' => $userId];
if ($hasWidgetIdColumn && $widgetId) {
    $messagesParams['widget_id'] = $widgetId;
}

// Fixed error handling for messages
$messages = $db->fetchAll($messagesQuery, $messagesParams) ?: [];

// Modified query for unread messages to include widget_id if available
$unreadQuery = "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND sender_type = 'visitor' AND `read` = 0";
if ($hasWidgetIdColumn && $widgetId) {
    $unreadQuery .= " AND widget_id = :widget_id";
}
$unreadParams = ['user_id' => $userId];
if ($hasWidgetIdColumn && $widgetId) {
    $unreadParams['widget_id'] = $widgetId;
}

$unreadCount = $db->fetch($unreadQuery, $unreadParams);
$unreadMessages = $unreadCount ? $unreadCount['count'] : 0;

// Fixed visitors retrieval - reducing to 3 for better display
$visitors = $db->fetchAll(
    "SELECT * FROM visitors WHERE user_id = :user_id ORDER BY last_active DESC LIMIT 3", 
    ['user_id' => $userId]
) ?: [];

// For each visitor, get their associated widget_id if available
if ($hasWidgetIdColumn) {
    foreach ($visitors as $key => $visitor) {
        $visitorWidget = $db->fetch(
            "SELECT widget_id FROM messages 
             WHERE user_id = :user_id AND visitor_id = :visitor_id AND widget_id IS NOT NULL
             ORDER BY created_at DESC 
             LIMIT 1",
            ['user_id' => $userId, 'visitor_id' => $visitor['id']]
        );
        $visitors[$key]['widget_id'] = $visitorWidget ? $visitorWidget['widget_id'] : null;
    }
}

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

// Get message count safely - modified to include widget_id if available
$messageCount = 0;
try {
    $messageCountQuery = "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id";
    if ($hasWidgetIdColumn && $widgetId) {
        $messageCountQuery .= " AND widget_id = :widget_id";
    }
    $messageCountParams = ['user_id' => $userId];
    if ($hasWidgetIdColumn && $widgetId) {
        $messageCountParams['widget_id'] = $widgetId;
    }
    
    $messageCountResult = $db->fetch($messageCountQuery, $messageCountParams);
    $messageCount = $messageCountResult ? $messageCountResult['count'] : 0;
} catch (Exception $e) {
    // Handle silently
    error_log("Error getting message count: " . $e->getMessage());
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
    error_log("Error getting visitor count: " . $e->getMessage());
}

// Get the full site URL for embedding
$siteUrl = SITE_URL;
// Ensure URL doesn't have trailing slash
$siteUrl = rtrim($siteUrl, '/');

// Include header
include '../includes/header.php';
?>

<style>
/* Mobile-First Responsive Dashboard Styles */
* {
  box-sizing: border-box;
}

.dashboard-container {
  width: 100%;
  max-width: 1400px;
  margin: 0 auto;
  padding: 0.75rem;
}

/* Header Section */
.dashboard-header {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1rem;
  text-align: center;
}

.dashboard-header h1 {
  font-size: 1.5rem;
  margin: 0;
  color: #333;
}

.widget-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background-color: white;
  padding: 0.5rem 1rem;
  border-radius: 1rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  font-size: 0.9rem;
  align-self: center;
}

.status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 6px;
}

.status-dot.active { background-color: #28a745; }
.status-dot.inactive { background-color: #dc3545; }

/* Welcome Section */
.dashboard-welcome {
  background-color: white;
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.dashboard-welcome h2 {
  font-size: 1.25rem;
  margin: 0 0 0.75rem 0;
  color: #333;
}

.subscription-status {
  display: flex;
  align-items: center;
  padding: 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  line-height: 1.4;
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
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 8px;
  flex-shrink: 0;
}

.subscription-status.active .status-indicator { background-color: #28a745; }
.subscription-status.inactive .status-indicator { background-color: #dc3545; }

/* Stats Cards - Mobile First */
.dashboard-stats {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.stat-card {
  background-color: white;
  border-radius: 0.75rem;
  padding: 1rem;
  display: flex;
  align-items: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  min-height: 70px;
}

.stat-icon {
  font-size: 1.5rem;
  margin-right: 1rem;
  opacity: 0.8;
  flex-shrink: 0;
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-title {
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 0.25rem;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #333;
  line-height: 1.2;
}

.stat-subtitle {
  font-size: 0.75rem;
  color: #888;
  margin-top: 0.25rem;
}

/* Main Grid Layout - Mobile First */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

.dashboard-col {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* Cards */
.dashboard-card {
  background-color: white;
  border-radius: 0.75rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: #f8f9fa;
  border-bottom: 1px solid #eaeaea;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.card-header h3 {
  font-size: 1rem;
  margin: 0;
  color: #333;
  flex: 1;
  min-width: 0;
}

.view-all-link {
  font-size: 0.85rem;
  color: #4a6cf7;
  text-decoration: none;
  white-space: nowrap;
}

.card-body {
  padding: 1rem;
  flex: 1;
  overflow: hidden;
  background-color: white;
}

/* Embed Code Section */
.code-container {
  position: relative;
  margin: 1rem 0;
}

.embed-code {
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.5rem;
  padding: 0.75rem;
  font-family: 'Courier New', monospace;
  font-size: 0.75rem;
  line-height: 1.4;
  overflow-x: auto;
  white-space: pre;
  margin: 0;
  word-break: break-all;
  color: #333;
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
  z-index: 2;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  color: #333;
}

.copy-btn.copied {
  background-color: #28a745;
  color: white;
}

/* Info Box */
.info-box {
  background-color: #e7f3ff;
  border-left: 4px solid #4a6cf7;
  padding: 0.75rem;
  margin-top: 1rem;
  border-radius: 4px;
  font-size: 0.85rem;
  line-height: 1.4;
  color: #333;
}

/* Widget Preview */
.widget-preview {
  position: relative;
  margin-top: 1rem;
  border: 1px dashed #ccc;
  padding: 0.75rem;
  border-radius: 5px;
  background-color: #f9f9f9;
}

.widget-preview-label {
  position: absolute;
  top: -10px;
  left: 10px;
  background: white;
  padding: 0 5px;
  font-size: 0.75rem;
  color: #666;
}

/* Visitor Items */
.visitor-item {
  display: flex;
  align-items: center;
  padding: 0.75rem;
  border-radius: 0.5rem;
  background-color: #f8f9fa;
  margin-bottom: 0.75rem;
  gap: 0.75rem;
}

.visitor-info {
  flex: 1;
  min-width: 0;
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
  flex-shrink: 0;
}

.visitor-details {
  min-width: 0;
}

.visitor-name {
  font-weight: 600;
  color: #333;
  margin-bottom: 0.25rem;
  font-size: 0.9rem;
}

.visitor-email {
  color: #666;
  font-size: 0.8rem;
  margin-bottom: 0.25rem;
  word-break: break-all;
}

.visitor-time {
  color: #888;
  font-size: 0.75rem;
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

.visitor-actions {
  flex-shrink: 0;
}

.btn {
  display: inline-block;
  padding: 0.375rem 0.75rem;
  font-size: 0.8rem;
  border-radius: 0.25rem;
  text-decoration: none;
  text-align: center;
  border: 1px solid transparent;
  cursor: pointer;
  white-space: nowrap;
}

.btn-primary {
  background-color: #4a6cf7;
  color: white;
  border-color: #4a6cf7;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}

/* Message Items */
.message-item {
  padding: 0.75rem;
  border-radius: 0.5rem;
  background-color: #f8f9fa;
  margin-bottom: 0.75rem;
  position: relative;
}

.message-item.visitor {
  border-left: 3px solid #4a6cf7;
}

.message-item.unread {
  background-color: rgba(74, 108, 247, 0.05);
  border-left: 3px solid #4a6cf7;
}

.message-header {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  align-items: center;
}

.message-sender {
  font-weight: 600;
  color: #333;
  font-size: 0.85rem;
}

.message-time {
  color: #888;
  font-size: 0.75rem;
}

.message-content {
  color: #333;
  font-size: 0.9rem;
  line-height: 1.4;
  word-wrap: break-word;
  margin-bottom: 0.5rem;
}

.reply-btn {
  color: #4a6cf7;
  text-decoration: none;
  font-size: 0.8rem;
  font-weight: 500;
}

/* Widget ID Badge */
.widget-id-badge {
  display: inline-block;
  background-color: #e9ecef;
  border-radius: 0.25rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.7rem;
  color: #666;
  font-family: monospace;
}

/* Quick Actions */
.quick-actions {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

.quick-action-btn {
  background-color: #f8f9fa;
  border-radius: 0.5rem;
  padding: 1rem;
  text-align: center;
  text-decoration: none;
  color: #333;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px solid #eaeaea;
  min-height: 80px;
  transition: all 0.2s ease;
}

.quick-action-btn:hover {
  color: #4a6cf7;
  border-color: #4a6cf7;
  transform: translateY(-1px);
  background-color: white;
}

.quick-action-btn i {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
  color: #4a6cf7;
}

.quick-action-btn span {
  font-size: 0.9rem;
  font-weight: 500;
}

/* API Section */
.api-key-container {
  position: relative;
  margin: 1rem 0;
}

.api-key {
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  padding: 0.5rem;
  font-family: monospace;
  font-size: 0.8rem;
  display: block;
  word-break: break-all;
  color: #333;
}

.api-usage-list {
  margin: 0.75rem 0;
  padding-left: 1.25rem;
}

.api-usage-list li {
  margin-bottom: 0.25rem;
  font-size: 0.85rem;
  line-height: 1.4;
  color: #333;
}

/* Empty States */
.empty-state {
  text-align: center;
  padding: 2rem 1rem;
  color: #666;
}

.empty-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  opacity: 0.5;
}

.empty-state p {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.4;
  color: #666;
}

/* Responsive Breakpoints */

/* Small phones (320px and up) */
@media (min-width: 320px) {
  .embed-code {
    font-size: 0.7rem;
  }
}

/* Large phones (480px and up) */
@media (min-width: 480px) {
  .dashboard-container {
    padding: 1rem;
  }
  
  .dashboard-header {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    text-align: left;
  }
  
  .dashboard-header h1 {
    font-size: 1.75rem;
  }
  
  .dashboard-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .quick-actions {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .embed-code {
    font-size: 0.75rem;
  }
}

/* Tablets (768px and up) */
@media (min-width: 768px) {
  .dashboard-container {
    padding: 1.5rem;
  }
  
  .dashboard-header h1 {
    font-size: 2rem;
  }
  
  .dashboard-stats {
    grid-template-columns: repeat(3, 1fr);
  }
  
  .quick-actions {
    grid-template-columns: repeat(3, 1fr);
  }
  
  .visitor-item {
    gap: 1rem;
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .card-header {
    padding: 1.25rem 1.5rem;
  }
  
  .embed-code {
    font-size: 0.8rem;
  }
}

/* Desktop (992px and up) */
@media (min-width: 992px) {
  .dashboard-grid {
    grid-template-columns: 1fr 1fr;
  }
  
  .stat-card {
    padding: 1.25rem;
  }
  
  .stat-icon {
    font-size: 2rem;
  }
  
  .embed-code {
    font-size: 0.85rem;
  }
}

/* Large desktop (1200px and up) */
@media (min-width: 1200px) {
  .dashboard-container {
    padding: 2rem;
  }
  
  .dashboard-welcome {
    padding: 1.5rem;
  }
  
  .dashboard-welcome h2 {
    font-size: 1.5rem;
  }
}

/* Prevent horizontal scroll on small screens */
@media (max-width: 480px) {
  body {
    overflow-x: hidden;
  }
  
  .embed-code {
    font-size: 0.65rem;
    padding: 0.5rem;
  }
  
  .copy-btn {
    position: static;
    margin-top: 0.5rem;
    width: 100%;
    justify-content: center;
    background-color: white;
    color: #333;
  }
  
  .code-container {
    position: static;
  }
  
  .visitor-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .visitor-actions {
    align-self: stretch;
  }
  
  .btn {
    width: 100%;
  }
}

/* Ultra-small screens (under 350px) */
@media (max-width: 350px) {
  .dashboard-container {
    padding: 0.5rem;
  }
  
  .dashboard-welcome,
  .dashboard-card {
    border-radius: 0.5rem;
  }
  
  .card-header,
  .card-body {
    padding: 0.75rem;
  }
  
  .stat-card {
    padding: 0.75rem;
  }
  
  .stat-icon {
    font-size: 1.25rem;
  }
  
  .stat-value {
    font-size: 1.25rem;
  }
  
  .embed-code {
    font-size: 0.6rem;
    line-height: 1.3;
  }
  
  .message-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }
}

/* High DPI screens adjustments */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
  .status-dot,
  .status-indicator {
    border: 0.5px solid rgba(0,0,0,0.05);
  }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
  .quick-action-btn {
    transition: none;
  }
  
  .quick-action-btn:hover {
    transform: none;
  }
}

/* Ensure all text remains dark and readable */
body {
  background-color: #f5f5f5;
  color: #333;
}

.card-body p,
.card-body li,
.card-body span,
.card-body div {
  color: #333;
}

.subscription-status .status-text {
  color: #333;
}

.subscription-status .status-text a {
  color: #4a6cf7;
}
</style>

<main class="dashboard-container">
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
    
    <!-- Stats cards -->
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
    
    <!-- Main content grid -->
    <div class="dashboard-grid">
        <!-- Column 1: Widget code and recent visitors -->
        <div class="dashboard-col">
            <div class="dashboard-card embed-card">
                <div class="card-header">
                    <h3><i class="fa fa-code"></i> Widget Embed Code</h3>
                </div>
                <div class="card-body">
                    <p>Copy and paste this code into your website just before the closing <code>&lt;/body&gt;</code> tag:</p>
                    
                    <div class="code-container">
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
                                    <div class="visitor-avatar">
                                        <?php echo substr($visitor['name'] ?? 'A', 0, 1); ?>
                                    </div>
                                    <div class="visitor-info">
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
                                            <?php if (isset($visitor['widget_id']) && $visitor['widget_id']): ?>
                                                <div class="widget-id-badge">Widget: <?php echo substr($visitor['widget_id'], 0, 8); ?>...</div>
                                            <?php endif; ?>
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
                                        <?php if ($hasWidgetIdColumn && isset($message['widget_id']) && $message['widget_id']): ?>
                                            <span class="widget-id-badge">Widget: <?php echo substr($message['widget_id'], 0, 8); ?>...</span>
                                        <?php endif; ?>
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
                    
                    <div class="api-key-container">
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
                    
                    <p style="font-size: 0.85rem; color: #666;">Keep this key secret and don't share it with anyone. <a href="api-docs.php">View API Documentation</a></p>
                </div>
            </div>
            <?php endif; ?>
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
            
            // Use modern clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showCopiedState(button);
                }, function(err) {
                    // Fallback to legacy method
                    legacyCopy(textToCopy, button);
                });
            } else {
                // Fallback for older browsers
                legacyCopy(textToCopy, button);
            }
        });
    });
    
    function legacyCopy(text, button) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-999999px';
        textarea.style.top = '-999999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        
        try {
            document.execCommand('copy');
            showCopiedState(button);
        } catch (err) {
            console.error('Copy failed:', err);
        }
        
        document.body.removeChild(textarea);
    }
    
    function showCopiedState(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fa fa-check"></i> Copied!';
        button.classList.add('copied');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('copied');
        }, 2000);
    }
    
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
                    
                    // Optionally play notification sound for new messages
                    playNotificationSound();
                }
            })
            .catch(error => console.error('Error checking messages:', error));
    };
    
    // Play notification sound
    function playNotificationSound() {
        try {
            const audio = new Audio('/sounds/notification.mp3');
            audio.volume = 0.5;
            audio.play().catch(e => console.log('Could not play notification sound:', e));
        } catch (e) {
            console.log('Notification sound not supported');
        }
    }
    
    // Check for new messages every 60 seconds
    setInterval(checkNewMessages, 60000);
    
    // Initial check for new messages
    checkNewMessages();
});
</script>

<?php include '../includes/footer.php'; ?>
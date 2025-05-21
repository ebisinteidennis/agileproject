<?php
$pageTitle = 'Visitor Details';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require user to be logged in
requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Check subscription status
if (!isSubscriptionActive($user)) {
    $_SESSION['message'] = 'Your subscription is inactive. Please subscribe to view visitor details.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/account/billing.php');
}

// Check if visitor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = 'No visitor ID specified.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/account/visitors.php');
}

$visitorId = (int)$_GET['id'];

// Get visitor information
$visitor = $db->fetch(
    "SELECT * FROM visitors WHERE id = :id AND user_id = :user_id",
    ['id' => $visitorId, 'user_id' => $userId]
);

if (!$visitor) {
    $_SESSION['message'] = 'Visitor not found or you do not have permission to view this visitor.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/account/visitors.php');
}

// Get visit history (page views)
$pageViews = $db->fetchAll(
    "SELECT * FROM page_views WHERE visitor_id = :visitor_id ORDER BY viewed_at DESC LIMIT 100",
    ['visitor_id' => $visitorId]
);

// Get message history
$messages = $db->fetchAll(
    "SELECT * FROM messages WHERE visitor_id = :visitor_id AND user_id = :user_id ORDER BY created_at ASC",
    ['visitor_id' => $visitorId, 'user_id' => $userId]
);

// Get widget information
$widgetId = null;
if (!empty($messages)) {
    $widgetQuery = $db->fetch(
        "SELECT DISTINCT widget_id FROM messages WHERE visitor_id = :visitor_id AND widget_id IS NOT NULL LIMIT 1",
        ['visitor_id' => $visitorId]
    );
    $widgetId = $widgetQuery ? $widgetQuery['widget_id'] : null;
}

if ($widgetId) {
    $widget = $db->fetch(
        "SELECT * FROM widgets WHERE id = :id AND user_id = :user_id",
        ['id' => $widgetId, 'user_id' => $userId]
    );
}

// Include header
include '../includes/header.php';
?>

<style>
.visitor-details-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 15px;
}

.visitor-details-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
}

.visitor-details-title {
    margin: 0;
}

.visitor-actions {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    text-decoration: none;
}

.btn-secondary {
    background: #f5f7fa;
    color: #333;
    border: 1px solid #ddd;
}

.btn-secondary:hover {
    background: #e5e7ea;
    text-decoration: none;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
    text-decoration: none;
}

.detail-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.detail-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.detail-card h2 {
    margin-top: 0;
    font-size: 18px;
    color: #333;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    margin-bottom: 15px;
}

.detail-item {
    margin-bottom: 12px;
}

.detail-label {
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
}

.detail-value {
    color: #333;
    word-break: break-word;
}

.empty-value {
    color: #999;
    font-style: italic;
}

.section-title {
    margin: 30px 0 20px;
    font-size: 20px;
    color: #333;
}

/* Activity Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
    margin-bottom: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 10px;
    width: 2px;
    background: #eee;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.timeline-item::before {
    content: '';
    position: absolute;
    top: 15px;
    left: -25px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #3498db;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #3498db;
}

.timeline-item.pageview::before {
    background: #2ecc71;
    box-shadow: 0 0 0 2px #2ecc71;
}

.timeline-item.message-visitor::before {
    background: #f39c12;
    box-shadow: 0 0 0 2px #f39c12;
}

.timeline-item.message-agent::before {
    background: #9b59b6;
    box-shadow: 0 0 0 2px #9b59b6;
}

.timeline-time {
    color: #777;
    font-size: 13px;
    margin-bottom: 5px;
}

.timeline-title {
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.timeline-content {
    color: #555;
}

.timeline-url {
    display: block;
    padding: 8px 12px;
    background: #f5f7fa;
    border-radius: 4px;
    margin-top: 10px;
    font-size: 13px;
    word-break: break-all;
}

/* Chat Messages */
.chat-messages {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.message-list {
    max-height: 500px;
    overflow-y: auto;
    margin-bottom: 20px;
    padding-right: 10px;
}

.message-item {
    max-width: 80%;
    margin-bottom: 15px;
    padding: 12px 16px;
    border-radius: 18px;
    position: relative;
    word-wrap: break-word;
}

.message-item.visitor {
    margin-right: auto;
    background-color: #f5f7fa;
    border-bottom-left-radius: 4px;
}

.message-item.agent {
    margin-left: auto;
    background-color: #3498db;
    color: white;
    border-bottom-right-radius: 4px;
}

.message-meta {
    display: flex;
    justify-content: flex-end;
    font-size: 12px;
    opacity: 0.8;
    margin-top: 5px;
}

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #999;
    font-style: italic;
}

/* Widget Information */
.widget-info {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.widget-code {
    padding: 15px;
    background: #f5f7fa;
    border-radius: 4px;
    font-family: monospace;
    overflow-x: auto;
    margin-top: 15px;
}

.tab-container {
    margin-top: 30px;
}

.tab-navigation {
    display: flex;
    border-bottom: 1px solid #ddd;
    margin-bottom: 20px;
}

.tab-link {
    padding: 10px 20px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    color: #777;
    font-weight: 500;
    transition: all 0.2s ease;
}

.tab-link.active {
    border-bottom-color: #3498db;
    color: #3498db;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

@media (max-width: 768px) {
    .visitor-details-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .visitor-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .detail-cards {
        grid-template-columns: 1fr;
    }
    
    .tab-navigation {
        overflow-x: auto;
        white-space: nowrap;
        padding-bottom: 5px;
    }
}
</style>

<main class="visitor-details-container">
    <div class="visitor-details-header">
        <h1 class="visitor-details-title">
            Visitor Details: <?php echo htmlspecialchars($visitor['name'] ?? 'Anonymous Visitor'); ?>
        </h1>
        
        <div class="visitor-actions">
            <a href="<?php echo SITE_URL; ?>/account/chat.php?visitor=<?php echo $visitorId; ?>" class="btn btn-primary">
                <?php echo !empty($messages) ? 'View Chat' : 'Start Chat'; ?>
            </a>
            <a href="<?php echo SITE_URL; ?>/account/visitors.php" class="btn btn-secondary">Back to Visitors</a>
            
            <!-- This would need a confirmation modal in a real implementation -->
            <button type="button" class="btn btn-danger" id="delete-visitor-btn">Delete Visitor Data</button>
        </div>
    </div>
    
    <div class="detail-cards">
        <div class="detail-card">
            <h2>Basic Information</h2>
            
            <div class="detail-item">
                <div class="detail-label">Name</div>
                <div class="detail-value">
                    <?php echo !empty($visitor['name']) ? htmlspecialchars($visitor['name']) : '<span class="empty-value">Not provided</span>'; ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Email</div>
                <div class="detail-value">
                    <?php echo !empty($visitor['email']) ? htmlspecialchars($visitor['email']) : '<span class="empty-value">Not provided</span>'; ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">First Seen</div>
                <div class="detail-value">
                    <?php echo date('F j, Y \a\t g:i a', strtotime($visitor['created_at'])); ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Last Active</div>
                <div class="detail-value">
                    <?php 
                    $lastActive = strtotime($visitor['last_active']);
                    $now = time();
                    $diff = $now - $lastActive;
                    
                    echo date('F j, Y \a\t g:i a', $lastActive);
                    echo ' (';
                    
                    if ($diff < 60) {
                        echo 'Just now';
                    } elseif ($diff < 3600) {
                        echo floor($diff / 60) . ' minutes ago';
                    } elseif ($diff < 86400) {
                        echo floor($diff / 3600) . ' hours ago';
                    } else {
                        echo floor($diff / 86400) . ' days ago';
                    }
                    
                    echo ')';
                    ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Messages</div>
                <div class="detail-value">
                    <?php echo count($messages); ?> message(s)
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Page Views</div>
                <div class="detail-value">
                    <?php echo count($pageViews); ?> page(s) viewed
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <h2>Technical Information</h2>
            
            <div class="detail-item">
                <div class="detail-label">IP Address</div>
                <div class="detail-value">
                    <?php echo htmlspecialchars($visitor['ip_address']); ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Location</div>
                <div class="detail-value">
                    <?php 
                    $location = [];
                    if (!empty($visitor['city'])) $location[] = htmlspecialchars($visitor['city']);
                    if (!empty($visitor['region'])) $location[] = htmlspecialchars($visitor['region']);
                    if (!empty($visitor['country'])) $location[] = htmlspecialchars($visitor['country']);
                    
                    echo !empty($location) ? implode(', ', $location) : '<span class="empty-value">Unknown</span>';
                    ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Browser</div>
                <div class="detail-value">
                    <?php echo !empty($visitor['browser']) ? htmlspecialchars($visitor['browser']) : '<span class="empty-value">Unknown</span>'; ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Operating System</div>
                <div class="detail-value">
                    <?php echo !empty($visitor['os']) ? htmlspecialchars($visitor['os']) : '<span class="empty-value">Unknown</span>'; ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Device Type</div>
                <div class="detail-value">
                    <?php 
                    $deviceType = !empty($visitor['device_type']) ? htmlspecialchars($visitor['device_type']) : 'Unknown';
                    echo ucfirst($deviceType);
                    ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Screen Size</div>
                <div class="detail-value">
                    <?php 
                    if (!empty($visitor['screen_width']) && !empty($visitor['screen_height'])) {
                        echo $visitor['screen_width'] . ' x ' . $visitor['screen_height'];
                    } else {
                        echo '<span class="empty-value">Unknown</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <h2>Current Session</h2>
            
            <div class="detail-item">
                <div class="detail-label">Current URL</div>
                <div class="detail-value">
                    <?php if (!empty($visitor['url'])): ?>
                        <a href="<?php echo htmlspecialchars($visitor['url']); ?>" target="_blank">
                            <?php echo htmlspecialchars($visitor['url']); ?>
                        </a>
                    <?php else: ?>
                        <span class="empty-value">Not available</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Referrer</div>
                <div class="detail-value">
                    <?php if (!empty($visitor['referrer'])): ?>
                        <a href="<?php echo htmlspecialchars($visitor['referrer']); ?>" target="_blank">
                            <?php echo htmlspecialchars($visitor['referrer']); ?>
                        </a>
                    <?php else: ?>
                        <span class="empty-value">Direct visit</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Session Duration</div>
                <div class="detail-value">
                    <?php 
                    if (!empty($visitor['session_start'])) {
                        $sessionStart = strtotime($visitor['session_start']);
                        $now = time();
                        $duration = $now - $sessionStart;
                        
                        $hours = floor($duration / 3600);
                        $minutes = floor(($duration % 3600) / 60);
                        $seconds = $duration % 60;
                        
                        $durationStr = [];
                        if ($hours > 0) $durationStr[] = $hours . ' hour' . ($hours != 1 ? 's' : '');
                        if ($minutes > 0) $durationStr[] = $minutes . ' minute' . ($minutes != 1 ? 's' : '');
                        if ($seconds > 0 && $hours == 0) $durationStr[] = $seconds . ' second' . ($seconds != 1 ? 's' : '');
                        
                        echo implode(', ', $durationStr);
                    } else {
                        echo '<span class="empty-value">Unknown</span>';
                    }
                    ?>
                </div>
            </div>
            
            <?php if (!empty($widget)): ?>
            <div class="detail-item">
                <div class="detail-label">Widget</div>
                <div class="detail-value">
                    <?php echo htmlspecialchars($widget['name']); ?> 
                    <span style="color:#777;">(ID: <?php echo $widgetId; ?>)</span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($visitor['tags'])): ?>
            <div class="detail-item">
                <div class="detail-label">Tags</div>
                <div class="detail-value">
                    <?php 
                    $tags = explode(',', $visitor['tags']);
                    foreach ($tags as $tag) {
                        echo '<span style="display:inline-block;background:#f1f1f1;padding:3px 8px;border-radius:12px;font-size:12px;margin-right:5px;margin-bottom:5px;">' . 
                             htmlspecialchars(trim($tag)) . '</span>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($visitor['custom_data'])): ?>
            <div class="detail-item">
                <div class="detail-label">Custom Data</div>
                <div class="detail-value">
                    <pre style="background:#f5f7fa;padding:10px;border-radius:4px;overflow:auto;font-size:13px;"><?php echo htmlspecialchars($visitor['custom_data']); ?></pre>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="tab-container">
        <div class="tab-navigation">
            <div class="tab-link active" data-tab="activity">Activity Timeline</div>
            <div class="tab-link" data-tab="messages">Chat Messages</div>
            <div class="tab-link" data-tab="pages">Page Views</div>
        </div>
        
        <div class="tab-content active" id="activity-tab">
            <h2 class="section-title">Activity Timeline</h2>
            
            <?php
            // Combine page views and messages into one activity timeline
            $activities = [];
            
            // Add page views
            foreach ($pageViews as $pageView) {
                $activities[] = [
                    'type' => 'pageview',
                    'timestamp' => $pageView['viewed_at'],
                    'data' => $pageView
                ];
            }
            
            // Add messages
            foreach ($messages as $message) {
                $activities[] = [
                    'type' => 'message',
                    'sender' => $message['sender_type'],
                    'timestamp' => $message['created_at'],
                    'data' => $message
                ];
            }
            
            // Sort by timestamp (newest first)
            usort($activities, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            ?>
            
            <?php if (empty($activities)): ?>
            <div class="no-data">No activity recorded for this visitor yet.</div>
            <?php else: ?>
            <div class="timeline">
                <?php foreach ($activities as $activity): ?>
                    <?php if ($activity['type'] == 'pageview'): ?>
                        <div class="timeline-item pageview">
                            <div class="timeline-time">
                                <?php echo date('F j, Y \a\t g:i:s a', strtotime($activity['data']['viewed_at'])); ?>
                            </div>
                            <div class="timeline-title">Page View</div>
                            <div class="timeline-content">
                                Visited a page on your website.
                                <a href="<?php echo htmlspecialchars($activity['data']['url']); ?>" target="_blank" class="timeline-url">
                                    <?php echo htmlspecialchars($activity['data']['url']); ?>
                                </a>
                            </div>
                        </div>
                    <?php elseif ($activity['type'] == 'message'): ?>
                        <div class="timeline-item message-<?php echo $activity['sender']; ?>">
                            <div class="timeline-time">
                                <?php echo date('F j, Y \a\t g:i:s a', strtotime($activity['data']['created_at'])); ?>
                            </div>
                            <div class="timeline-title">
                                <?php echo $activity['sender'] == 'visitor' ? 'Visitor Message' : 'Agent Message'; ?>
                            </div>
                            <div class="timeline-content">
                                <?php echo htmlspecialchars($activity['data']['message']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-content" id="messages-tab">
            <h2 class="section-title">Chat Messages</h2>
            
            <?php if (empty($messages)): ?>
            <div class="no-data">No chat messages with this visitor yet.</div>
            <?php else: ?>
            <div class="chat-messages">
                <div class="message-list">
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item <?php echo $message['sender_type']; ?>">
                            <div class="message-content">
                                <?php echo htmlspecialchars($message['message']); ?>
                            </div>
                            <div class="message-meta">
                                <?php echo date('g:i a', strtotime($message['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <a href="<?php echo SITE_URL; ?>/account/chat.php?visitor=<?php echo $visitorId; ?>" class="btn btn-primary" style="width:100%;">
                    Open Chat
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-content" id="pages-tab">
            <h2 class="section-title">Page Views History</h2>
            
            <?php if (empty($pageViews)): ?>
            <div class="no-data">No page views recorded for this visitor yet.</div>
            <?php else: ?>
            <div class="timeline">
                <?php foreach ($pageViews as $pageView): ?>
                    <div class="timeline-item pageview">
                        <div class="timeline-time">
                            <?php echo date('F j, Y \a\t g:i:s a', strtotime($pageView['viewed_at'])); ?>
                        </div>
                        <div class="timeline-title">Page View</div>
                        <div class="timeline-content">
                            <a href="<?php echo htmlspecialchars($pageView['url']); ?>" target="_blank" class="timeline-url">
                                <?php echo htmlspecialchars($pageView['url']); ?>
                            </a>
                            
                            <?php if (!empty($pageView['referrer'])): ?>
                            <div style="margin-top:8px;font-size:13px;color:#777;">
                                <strong>Referrer:</strong> 
                                <a href="<?php echo htmlspecialchars($pageView['referrer']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($pageView['referrer']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($pageView['time_on_page'])): ?>
                            <div style="margin-top:5px;font-size:13px;color:#777;">
                                <strong>Time on page:</strong> 
                                <?php 
                                $seconds = $pageView['time_on_page'];
                                if ($seconds < 60) {
                                    echo $seconds . ' seconds';
                                } else {
                                    $minutes = floor($seconds / 60);
                                    $remainingSeconds = $seconds % 60;
                                    echo $minutes . ' minute' . ($minutes != 1 ? 's' : '') . 
                                         ($remainingSeconds > 0 ? ', ' . $remainingSeconds . ' second' . ($remainingSeconds != 1 ? 's' : '') : '');
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs
            tabLinks.forEach(tab => tab.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to current tab
            this.classList.add('active');
            document.getElementById(tabId + '-tab').classList.add('active');
        });
    });
    
    // Delete visitor confirmation
    const deleteBtn = document.getElementById('delete-visitor-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete all data for this visitor? This action cannot be undone.')) {
                window.location.href = '<?php echo SITE_URL; ?>/account/delete-visitor.php?id=<?php echo $visitorId; ?>&token=<?php echo generateCSRFToken(); ?>';
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
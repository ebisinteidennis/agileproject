<?php
$pageTitle = 'Recent Visitors';
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
    $_SESSION['message'] = 'Your subscription is inactive. Please subscribe to view visitors.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/account/billing.php');
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtering options
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'all_time';

// Build the query based on filters
$params = ['user_id' => $userId];
$where = "WHERE user_id = :user_id";

// Apply search filter if provided
if (!empty($search)) {
    $where .= " AND (name LIKE :search OR email LIKE :search OR url LIKE :search OR ip_address LIKE :search)";
    $params['search'] = "%{$search}%";
}

// Apply date range filter
$dateRangeWhere = '';
switch ($dateRange) {
    case 'today':
        $dateRangeWhere = " AND DATE(last_active) = CURDATE()";
        break;
    case 'yesterday':
        $dateRangeWhere = " AND DATE(last_active) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'last_7_days':
        $dateRangeWhere = " AND last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'last_30_days':
        $dateRangeWhere = " AND last_active >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'this_month':
        $dateRangeWhere = " AND MONTH(last_active) = MONTH(CURDATE()) AND YEAR(last_active) = YEAR(CURDATE())";
        break;
    case 'last_month':
        $dateRangeWhere = " AND MONTH(last_active) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(last_active) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
}
$where .= $dateRangeWhere;

// Apply visitor type filter
if ($filter === 'with_messages') {
    $hasMessagesSubquery = "(SELECT COUNT(*) FROM messages WHERE messages.visitor_id = visitors.id) > 0";
    $where .= " AND {$hasMessagesSubquery}";
} elseif ($filter === 'without_messages') {
    $hasMessagesSubquery = "(SELECT COUNT(*) FROM messages WHERE messages.visitor_id = visitors.id) = 0";
    $where .= " AND {$hasMessagesSubquery}";
}

// Get total count for pagination
$totalQuery = "SELECT COUNT(*) as total FROM visitors {$where}";
$totalResult = $db->fetch($totalQuery, $params);
$total = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($total / $perPage);

// Get the visitors
$query = "SELECT v.*, 
          (SELECT COUNT(*) FROM messages WHERE visitor_id = v.id) as message_count,
          (SELECT MAX(created_at) FROM messages WHERE visitor_id = v.id) as last_message 
          FROM visitors v 
          {$where} 
          ORDER BY last_active DESC 
          LIMIT {$perPage} OFFSET {$offset}";
$visitors = $db->fetchAll($query, $params);

// Include header
include '../includes/header.php';
?>

<style>
.visitors-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 15px;
}

.page-title-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.filter-bar {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.visitor-card {
    background: white;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.visitor-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.visitor-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.visitor-name {
    font-weight: 600;
    font-size: 16px;
    color: #333;
}

.visitor-time {
    font-size: 13px;
    color: #777;
}

.visitor-content {
    padding: 15px 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.visitor-info {
    flex: 3;
    min-width: 280px;
}

.visitor-meta {
    color: #666;
    font-size: 14px;
    margin-bottom: 5px;
}

.visitor-meta i {
    width: 20px;
    text-align: center;
    margin-right: 8px;
    color: #3498db;
}

.visitor-url {
    display: block;
    margin-top: 10px;
    background: #f5f7fa;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
    word-break: break-all;
}

.visitor-stats {
    flex: 1;
    min-width: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.stat-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.stat-label {
    font-size: 13px;
    color: #777;
    margin-right: 10px;
}

.stat-value {
    font-weight: 600;
    color: #333;
}

.visitor-actions {
    flex: 1;
    min-width: 120px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    justify-content: center;
}

.btn {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    cursor: pointer;
    transition: background 0.2s ease;
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

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
}

.page-link {
    padding: 8px 12px;
    border-radius: 4px;
    background: white;
    border: 1px solid #ddd;
    color: #333;
    text-decoration: none;
    transition: all 0.2s ease;
}

.page-link:hover {
    background: #f5f7fa;
}

.page-link.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.page-link.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 60px 30px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.empty-state-icon {
    font-size: 50px;
    color: #ccc;
    margin-bottom: 15px;
}

.empty-state-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #555;
}

.empty-state-text {
    color: #777;
    max-width: 500px;
    margin: 0 auto 20px;
}

@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        gap: 10px;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .visitor-content {
        flex-direction: column;
        gap: 15px;
    }
    
    .visitor-stats {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .visitor-actions {
        flex-direction: row;
    }
}
</style>

<main class="visitors-container">
    <div class="page-title-wrapper">
        <h1>Recent Visitors</h1>
        <div>
            <a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
    
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" placeholder="Name, email, or IP..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label for="filter">Filter</label>
                <select id="filter" name="filter">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Visitors</option>
                    <option value="with_messages" <?php echo $filter === 'with_messages' ? 'selected' : ''; ?>>With Messages</option>
                    <option value="without_messages" <?php echo $filter === 'without_messages' ? 'selected' : ''; ?>>Without Messages</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_range">Date Range</label>
                <select id="date_range" name="date_range">
                    <option value="all_time" <?php echo $dateRange === 'all_time' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="last_7_days" <?php echo $dateRange === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="last_30_days" <?php echo $dateRange === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="this_month" <?php echo $dateRange === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="last_month" <?php echo $dateRange === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="<?php echo SITE_URL; ?>/account/visitors.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    
    <?php if (empty($visitors)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <h2 class="empty-state-title">No visitors found</h2>
            <p class="empty-state-text">
                <?php if (!empty($search) || $filter !== 'all' || $dateRange !== 'all_time'): ?>
                    No visitors match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    You don't have any visitors yet. Make sure your widget is properly installed on your website.
                <?php endif; ?>
            </p>
            <?php if (!empty($search) || $filter !== 'all' || $dateRange !== 'all_time'): ?>
                <a href="<?php echo SITE_URL; ?>/account/visitors.php" class="btn btn-primary">Clear All Filters</a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/account/widget.php" class="btn btn-primary">Widget Settings</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($visitors as $visitor): ?>
            <div class="visitor-card">
                <div class="visitor-header">
                    <div class="visitor-name">
                        <?php echo htmlspecialchars($visitor['name'] ?? 'Anonymous Visitor'); ?>
                    </div>
                    <div class="visitor-time">
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
                    </div>
                </div>
                <div class="visitor-content">
                    <div class="visitor-info">
                        <?php if (!empty($visitor['email'])): ?>
                            <div class="visitor-meta">
                                <i>✉️</i> <?php echo htmlspecialchars($visitor['email']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="visitor-meta">
                            <i>🌐</i> <?php echo htmlspecialchars($visitor['ip_address']); ?>
                        </div>
                        
                        <?php if (!empty($visitor['country'])): ?>
                            <div class="visitor-meta">
                                <i>🌍</i> <?php echo htmlspecialchars($visitor['country']); ?>
                                <?php if (!empty($visitor['city'])): ?>
                                    (<?php echo htmlspecialchars($visitor['city']); ?>)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($visitor['browser'])): ?>
                            <div class="visitor-meta">
                                <i>💻</i> <?php echo htmlspecialchars($visitor['browser']); ?>
                                <?php if (!empty($visitor['os'])): ?>
                                    / <?php echo htmlspecialchars($visitor['os']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($visitor['url'])): ?>
                            <div class="visitor-url">
                                <strong>Current page:</strong> 
                                <a href="<?php echo htmlspecialchars($visitor['url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($visitor['url']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="visitor-stats">
                        <div class="stat-item">
                            <div class="stat-label">First visit:</div>
                            <div class="stat-value"><?php echo date('M j, Y', strtotime($visitor['created_at'])); ?></div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-label">Messages:</div>
                            <div class="stat-value"><?php echo $visitor['message_count']; ?></div>
                        </div>
                        
                        <?php if ($visitor['message_count'] > 0 && !empty($visitor['last_message'])): ?>
                            <div class="stat-item">
                                <div class="stat-label">Last message:</div>
                                <div class="stat-value">
                                    <?php 
                                    $lastMessageTime = strtotime($visitor['last_message']);
                                    $now = time();
                                    $diff = $now - $lastMessageTime;
                                    
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo floor($diff / 60) . ' min ago';
                                    } elseif ($diff < 86400) {
                                        echo floor($diff / 3600) . ' hour(s) ago';
                                    } else {
                                        echo date('M j, g:i a', $lastMessageTime);
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="visitor-actions">
                        <?php if ($visitor['message_count'] > 0): ?>
                            <a href="<?php echo SITE_URL; ?>/account/chat.php?visitor=<?php echo $visitor['id']; ?>" class="btn btn-primary">View Chat</a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/account/chat.php?visitor=<?php echo $visitor['id']; ?>" class="btn btn-secondary">Start Chat</a>
                        <?php endif; ?>
                        
                        <a href="<?php echo SITE_URL; ?>/account/visitor-details.php?id=<?php echo $visitor['id']; ?>" class="btn btn-secondary">View Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?><?php echo $dateRange !== 'all_time' ? '&date_range=' . $dateRange : ''; ?>" class="page-link">&laquo;</a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?><?php echo $dateRange !== 'all_time' ? '&date_range=' . $dateRange : ''; ?>" class="page-link">&lsaquo;</a>
                <?php else: ?>
                    <span class="page-link disabled">&laquo;</span>
                    <span class="page-link disabled">&lsaquo;</span>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                // Ensure we always show 5 page links if possible
                if ($endPage - $startPage < 4 && $totalPages > 4) {
                    if ($startPage == 1) {
                        $endPage = min($totalPages, 5);
                    } elseif ($endPage == $totalPages) {
                        $startPage = max(1, $totalPages - 4);
                    }
                }
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?><?php echo $dateRange !== 'all_time' ? '&date_range=' . $dateRange : ''; ?>" class="page-link"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?><?php echo $dateRange !== 'all_time' ? '&date_range=' . $dateRange : ''; ?>" class="page-link">&rsaquo;</a>
                    <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?><?php echo $dateRange !== 'all_time' ? '&date_range=' . $dateRange : ''; ?>" class="page-link">&raquo;</a>
                <?php else: ?>
                    <span class="page-link disabled">&rsaquo;</span>
                    <span class="page-link disabled">&raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
<?php
$pageTitle = 'Admin Dashboard';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Get current date range for analytics
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

// Handle date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    
    // Validate dates
    if (!validateDate($startDate) || !validateDate($endDate)) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
    }
}

// Function to validate date format
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Get overall stats
$totalUsers = $db->fetch("SELECT COUNT(*) as count FROM users")['count'];
$activeSubscriptions = $db->fetch("SELECT COUNT(*) as count FROM users WHERE subscription_status = 'active' AND subscription_expiry > NOW()")['count'];
$totalMessages = $db->fetch("SELECT COUNT(*) as count FROM messages")['count'];
$totalPayments = $db->fetch("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'] ?? 0;
$totalVisitors = $db->fetch("SELECT COUNT(*) as count FROM visitors")['count'];
$pendingPayments = $db->fetch("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")['count'];

// Get stats for the selected period
$newUsers = $db->fetch(
    "SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ?", 
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
)['count'];

$periodRevenue = $db->fetch(
    "SELECT SUM(amount) as total FROM payments WHERE status = 'completed' AND created_at BETWEEN ? AND ?", 
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
)['total'] ?? 0;

$newMessages = $db->fetch(
    "SELECT COUNT(*) as count FROM messages WHERE created_at BETWEEN ? AND ?", 
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
)['count'];

$newVisitors = $db->fetch(
    "SELECT COUNT(*) as count FROM visitors WHERE created_at BETWEEN ? AND ?", 
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
)['count'];

// Get conversion rate (percentage of visitors who started a conversation)
$conversationVisitors = $db->fetch(
    "SELECT COUNT(DISTINCT visitor_id) as count FROM messages WHERE created_at BETWEEN ? AND ?", 
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
)['count'];

$conversionRate = $newVisitors > 0 ? round(($conversationVisitors / $newVisitors) * 100, 2) : 0;

// Get daily stats for chart
$dailyUsers = [];
$dailyRevenue = [];
$dailyVisitors = [];
$dailyMessages = [];

$startTimestamp = strtotime($startDate);
$endTimestamp = strtotime($endDate);
$daysCount = ceil(($endTimestamp - $startTimestamp) / 86400);

// Initialize arrays with all dates in range
for ($i = 0; $i < $daysCount; $i++) {
    $currentDate = date('Y-m-d', strtotime("+{$i} days", $startTimestamp));
    $dailyUsers[$currentDate] = 0;
    $dailyRevenue[$currentDate] = 0;
    $dailyVisitors[$currentDate] = 0;
    $dailyMessages[$currentDate] = 0;
}

// Get users by day
$usersByDayQuery = $db->query(
    "SELECT DATE(created_at) as date, COUNT(*) as count FROM users 
     WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
);

while ($row = $usersByDayQuery->fetch(PDO::FETCH_ASSOC)) {
    if (isset($dailyUsers[$row['date']])) {
        $dailyUsers[$row['date']] = (int)$row['count'];
    }
}

// Get revenue by day
$revenueByDayQuery = $db->query(
    "SELECT DATE(created_at) as date, SUM(amount) as total FROM payments 
     WHERE status = 'completed' AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
);

while ($row = $revenueByDayQuery->fetch(PDO::FETCH_ASSOC)) {
    if (isset($dailyRevenue[$row['date']])) {
        $dailyRevenue[$row['date']] = (float)$row['total'];
    }
}

// Get visitors by day
$visitorsByDayQuery = $db->query(
    "SELECT DATE(created_at) as date, COUNT(*) as count FROM visitors 
     WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
);

while ($row = $visitorsByDayQuery->fetch(PDO::FETCH_ASSOC)) {
    if (isset($dailyVisitors[$row['date']])) {
        $dailyVisitors[$row['date']] = (int)$row['count'];
    }
}

// Get messages by day
$messagesByDayQuery = $db->query(
    "SELECT DATE(created_at) as date, COUNT(*) as count FROM messages 
     WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
    [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
);

while ($row = $messagesByDayQuery->fetch(PDO::FETCH_ASSOC)) {
    if (isset($dailyMessages[$row['date']])) {
        $dailyMessages[$row['date']] = (int)$row['count'];
    }
}

// Get subscription distribution
$subscriptionStats = $db->query(
    "SELECT s.name, COUNT(u.id) as user_count 
     FROM subscriptions s 
     LEFT JOIN users u ON s.id = u.subscription_id AND u.subscription_status = 'active'
     GROUP BY s.id"
)->fetchAll(PDO::FETCH_ASSOC);

// Get recent users
$recentUsers = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");

// Get recent payments
$recentPayments = $db->fetchAll(
    "SELECT p.*, u.name as user_name, u.email as user_email, s.name as subscription_name 
     FROM payments p 
     JOIN users u ON p.user_id = u.id 
     JOIN subscriptions s ON p.subscription_id = s.id 
     ORDER BY p.created_at DESC 
     LIMIT 5"
);

// Get recent messages
$recentMessages = $db->fetchAll(
    "SELECT m.*, u.name as user_name, v.name as visitor_name, v.email as visitor_email
     FROM messages m
     JOIN users u ON m.user_id = u.id
     JOIN visitors v ON m.visitor_id = v.id
     ORDER BY m.created_at DESC
     LIMIT 5"
);

// Get system notifications
$systemNotifications = [];

// Check for pending payments
if ($pendingPayments > 0) {
    $systemNotifications[] = [
        'type' => 'payment',
        'message' => "You have $pendingPayments pending payment" . ($pendingPayments > 1 ? 's' : '') . " to review.",
        'time' => date('Y-m-d H:i:s'),
        'link' => 'payments.php?status=pending'
    ];
}

// Check for expiring subscriptions
$expiringSubscriptions = $db->fetch(
    "SELECT COUNT(*) as count FROM users 
     WHERE subscription_status = 'active' 
     AND subscription_expiry BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)"
)['count'];

if ($expiringSubscriptions > 0) {
    $systemNotifications[] = [
        'type' => 'subscription',
        'message' => "$expiringSubscriptions subscription" . ($expiringSubscriptions > 1 ? 's' : '') . " expiring in the next 7 days.",
        'time' => date('Y-m-d H:i:s'),
        'link' => 'subscriptions.php?filter=expiring'
    ];
}

// Get most recent system upgrade
$systemUpgrades = [
    [
        'version' => '1.5.2',
        'date' => '2025-05-15',
        'features' => [
            'Added new analytics dashboard',
            'Fixed widget responsiveness on mobile devices',
            'Improved message handling for large volumes',
            'Added visitor tracking enhancements'
        ]
    ],
    [
        'version' => '1.5.1',
        'date' => '2025-05-01',
        'features' => [
            'Security patches and bug fixes',
            'Performance optimizations',
            'Updated payment processing integrations'
        ]
    ]
];

// Include header
include '../includes/header.php';
?>

<style>
/* Admin Dashboard Styles - Light Theme */
.admin-dashboard {
    display: flex;
    min-height: calc(100vh - 60px);
    background-color: #f9fafb;
}

/* Sidebar */
.admin-sidebar {
    position: fixed;
    top: 60px;
    left: 0;
    width: 250px;
    height: calc(100vh - 60px);
    background-color: #ffffff;
    color: #333333;
    z-index: 100;
    transition: transform 0.3s ease-in-out;
    overflow-y: auto;
    border-right: 1px solid #e5e7eb;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.admin-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
    color: #111827;
}

.admin-info {
    display: flex;
    align-items: center;
    margin-top: 10px;
}

.admin-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-right: 10px;
}

.admin-name {
    font-size: 0.9rem;
    color: #111827;
}

.admin-role {
    font-size: 0.75rem;
    color: #6b7280;
}

.sidebar-menu {
    padding: 15px 0;
}

.menu-category {
    padding: 10px 20px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #6b7280;
    margin-top: 10px;
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #4b5563;
    text-decoration: none;
    transition: background-color 0.2s;
}

.menu-item:hover {
    background-color: #f3f4f6;
}

.menu-item.active {
    background-color: #eef2ff;
    color: #3b82f6;
    border-left: 3px solid #3b82f6;
}

.menu-icon {
    margin-right: 12px;
    width: 20px;
    text-align: center;
    color: #6b7280;
}

.menu-item.active .menu-icon {
    color: #3b82f6;
}

.mobile-sidebar-toggle {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: #3b82f6;
    color: white;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    z-index: 110;
    border: none;
    cursor: pointer;
}

/* Main Content */
.admin-content {
    margin-left: 250px;
    padding: 30px;
    flex: 1;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.page-title h1 {
    font-size: 1.8rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
}

.page-subtitle {
    font-size: 0.9rem;
    color: #6b7280;
    margin-top: 5px;
}

.header-actions {
    display: flex;
    gap: 15px;
}

.date-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.date-filter label {
    font-size: 0.8rem;
    color: #6b7280;
}

.date-filter input {
    padding: 6px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    font-size: 0.85rem;
}

.action-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
    border: none;
}

.btn-primary:hover {
    background-color: #2563eb;
}

.btn-secondary {
    background-color: #ffffff;
    color: #4b5563;
    border: 1px solid #e5e7eb;
}

.btn-secondary:hover {
    background-color: #f9fafb;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stats-card {
    background-color: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
    border: 1px solid #f3f4f6;
}

.stats-card-main {
    position: relative;
    z-index: 1;
}

.stats-card-bg {
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    opacity: 0.05;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    z-index: 0;
}

.stats-title {
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.stats-icon {
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    color: white;
    border-radius: 4px;
}

.stats-icon.blue {
    background-color: #3b82f6;
}

.stats-icon.green {
    background-color: #10b981;
}

.stats-icon.orange {
    background-color: #f59e0b;
}

.stats-icon.purple {
    background-color: #8b5cf6;
}

.stats-icon.pink {
    background-color: #ec4899;
}

.stats-icon.indigo {
    background-color: #6366f1;
}

.stats-value {
    font-size: 1.8rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 10px;
}

.stats-trend {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.85rem;
}

.stats-trend-positive {
    color: #10b981;
}

.stats-trend-negative {
    color: #ef4444;
}

.stats-trend-neutral {
    color: #6b7280;
}

/* Charts Section */
.charts-section {
    margin-bottom: 30px;
}

.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.chart-card {
    background-color: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border: 1px solid #f3f4f6;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.chart-title {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

.chart-actions {
    display: flex;
    gap: 10px;
}

.chart-tab {
    padding: 6px 12px;
    font-size: 0.85rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.chart-tab.active {
    background-color: #3b82f6;
    color: white;
}

.chart-tab:not(.active) {
    background-color: #f3f4f6;
    color: #6b7280;
}

.chart-container {
    position: relative;
    height: 300px;
}

/* Tables */
.tables-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.table-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    border: 1px solid #f3f4f6;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #f3f4f6;
}

.table-title {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

.table-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-blue {
    background-color: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.table-content {
    padding: 0;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px 20px;
    text-align: left;
}

.data-table th {
    background-color: #f9fafb;
    font-weight: 500;
    color: #6b7280;
    font-size: 0.85rem;
}

.data-table tr {
    border-bottom: 1px solid #f3f4f6;
}

.data-table tr:last-child {
    border-bottom: none;
}

.data-table tbody tr:hover {
    background-color: #f9fafb;
}

.table-footer {
    display: flex;
    justify-content: center;
    padding: 12px;
    border-top: 1px solid #f3f4f6;
}

.view-all {
    text-decoration: none;
    color: #3b82f6;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
}

.view-all:hover {
    text-decoration: underline;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-completed {
    background-color: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-pending {
    background-color: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-failed {
    background-color: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-active {
    background-color: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-inactive {
    background-color: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.status-expired {
    background-color: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* System Information */
.system-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 20px;
}

.notification-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    border: 1px solid #f3f4f6;
}

.notification-list {
    padding: 0;
}

.notification-item {
    display: flex;
    padding: 15px 20px;
    border-bottom: 1px solid #f3f4f6;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #ebf5ff;
    color: #3b82f6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.notification-icon.payment {
    background-color: #fef3c7;
    color: #f59e0b;
}

.notification-icon.subscription {
    background-color: #ecfdf5;
    color: #10b981;
}

.notification-content {
    flex: 1;
}

.notification-message {
    font-size: 0.9rem;
    margin-bottom: 5px;
    color: #4b5563;
}

.notification-time {
    font-size: 0.75rem;
    color: #6b7280;
}

.notification-action {
    align-self: center;
    margin-left: 10px;
}

.system-updates {
    padding: 0;
}

.update-item {
    padding: 20px;
    border-bottom: 1px solid #f3f4f6;
}

.update-item:last-child {
    border-bottom: none;
}

.update-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.update-version {
    font-weight: 600;
    color: #4b5563;
}

.update-date {
    font-size: 0.85rem;
    color: #6b7280;
}

.update-features {
    padding-left: 20px;
    margin: 0;
    color: #4b5563;
}

.update-features li {
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.empty-state {
    padding: 30px;
    text-align: center;
    color: #6b7280;
}

/* Responsive Styles */
@media screen and (max-width: 1200px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .tables-section,
    .system-section {
        grid-template-columns: 1fr;
    }
}

@media screen and (max-width: 992px) {
    .admin-sidebar {
        transform: translateX(-100%);
    }
    
    .admin-sidebar.active {
        transform: translateX(0);
    }
    
    .admin-content {
        margin-left: 0;
    }
    
    .mobile-sidebar-toggle {
        display: flex;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media screen and (max-width: 768px) {
    .admin-content {
        padding: 20px;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .date-filter {
        width: 100%;
        justify-content: space-between;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .action-btn {
        flex: 1;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

@media screen and (max-width: 576px) {
    .charts-grid,
    .tables-section,
    .system-section {
        grid-template-columns: 1fr;
    }
    
    .date-filter {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .header-actions {
        flex-direction: column;
    }
}
</style>

<div class="admin-dashboard">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="sidebar-header">
            <h2 class="admin-title">Admin Panel</h2>
            <div class="admin-info">
                <div class="admin-avatar">A</div>
                <div>
                    <div class="admin-name"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></div>
                    <div class="admin-role">Administrator</div>
                </div>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-category">Main</div>
            <a href="index.php" class="menu-item active">
                <div class="menu-icon"><i class="fas fa-tachometer-alt"></i></div>
                Dashboard
            </a>
            
            <div class="menu-category">User Management</div>
            <a href="users.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-users"></i></div>
                Users
            </a>
            <a href="subscriptions.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-tag"></i></div>
                Subscriptions
            </a>
            <a href="payments.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-credit-card"></i></div>
                Payments
            </a>
            
            <div class="menu-category">Content</div>
            <a href="messages.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-comments"></i></div>
                Messages
            </a>
            <a href="visitors.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-eye"></i></div>
                Visitors
            </a>
            
            <div class="menu-category">Settings</div>
            <a href="settings.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-cog"></i></div>
                Site Settings
            </a>
        </div>
    </aside>
    
    <!-- Mobile Sidebar Toggle -->
    <button class="mobile-sidebar-toggle" id="mobile-sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Content -->
    <div class="admin-content">
        <div class="dashboard-header">
            <div class="page-title">
                <h1>Admin Dashboard</h1>
                <div class="page-subtitle">
                    Welcome back! Here's what's happening with your platform.
                </div>
            </div>
            
            <div class="header-actions">
                <form method="get" class="date-filter">
                    <div>
                        <label for="start_date">From</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" max="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    
                    <div>
                        <label for="end_date">To</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="submit" class="action-btn btn-primary">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </form>
                
                <a href="reports.php" class="action-btn btn-secondary">
                    <i class="fas fa-chart-bar"></i> Advanced Reports
                </a>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-card-main">
                    <div class="stats-title">
                        <div class="stats-icon blue"><i class="fas fa-users"></i></div>
                        Total Users
                    </div>
                    <div class="stats-value"><?php echo number_format($totalUsers); ?></div>
                    <div class="stats-trend <?php echo $newUsers > 0 ? 'stats-trend-positive' : 'stats-trend-neutral'; ?>">
                        <i class="fas fa-<?php echo $newUsers > 0 ? 'arrow-up' : 'minus'; ?>"></i>
                        <?php echo number_format($newUsers); ?> new in selected period
                    </div>
                </div>
                <div class="stats-card-bg">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-card-main">
                    <div class="stats-title">
                        <div class="stats-icon green"><i class="fas fa-check-circle"></i></div>
                        Active Subscriptions
                    </div>
                    <div class="stats-value"><?php echo number_format($activeSubscriptions); ?></div>
                    <div class="stats-trend stats-trend-neutral">
                        <i class="fas fa-percent"></i>
                        <?php echo round(($activeSubscriptions / max(1, $totalUsers)) * 100); ?>% of all users
                    </div>
                </div>
                <div class="stats-card-bg">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-card-main">
                    <div class="stats-title">
                        <div class="stats-icon orange"><i class="fas fa-comments"></i></div>
                        Total Messages
                    </div>
                    <div class="stats-value"><?php echo number_format($totalMessages); ?></div>
                    <div class="stats-trend <?php echo $newMessages > 0 ? 'stats-trend-positive' : 'stats-trend-neutral'; ?>">
                        <i class="fas fa-<?php echo $newMessages > 0 ? 'arrow-up' : 'minus'; ?>"></i>
                        <?php echo number_format($newMessages); ?> new in selected period
                    </div>
                </div>
                <div class="stats-card-bg">
                    <i class="fas fa-comments"></i>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-card-main">
                    <div class="stats-title">
                        <div class="stats-icon purple"><i class="fas fa-dollar-sign"></i></div>
                        Total Revenue
                    </div>
                    <div class="stats-value"><?php echo formatCurrency($totalPayments); ?></div>
                    <div class="stats-trend <?php echo $periodRevenue > 0 ? 'stats-trend-positive' : 'stats-trend-neutral'; ?>">
                        <i class="fas fa-<?php echo $periodRevenue > 0 ? 'arrow-up' : 'minus'; ?>"></i>
                        <?php echo formatCurrency($periodRevenue); ?> in selected period
                    </div>
                </div>
                <div class="stats-card-bg">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-card-main">
                    <div class="stats-title">
                        <div class="stats-icon pink"><i class="fas fa-eye"></i></div>
                        Total Visitors
                    </div>
                    <div class="stats-value"><?php echo number_format($totalVisitors); ?></div>
                    <div class="stats-trend <?php echo $newVisitors > 0 ? 'stats-trend-positive' : 'stats-trend-neutral'; ?>">
                        <i class="fas fa-<?php echo $newVisitors > 0 ? 'arrow-up' : 'minus'; ?>"></i>
                        <?php echo number_format($newVisitors); ?> new in selected period
                    </div>
                </div>
                <div class="stats-card-bg">
                    <i class="fas fa-eye"></i>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-card-main">
                    <div class="stats-title">
                        <div class="stats-icon indigo"><i class="fas fa-chart-pie"></i></div>
                        Chat Conversion Rate
                    </div>
                    <div class="stats-value"><?php echo $conversionRate; ?>%</div>
                    <div class="stats-trend stats-trend-neutral">
                        <i class="fas fa-info-circle"></i>
                        Of visitors who started chat
                    </div>
                </div>
                <div class="stats-card-bg">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-section">
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Activity Overview</div>
                        <div class="chart-actions">
                            <div class="chart-tab active" data-chart="visitors">Visitors</div>
                            <div class="chart-tab" data-chart="revenue">Revenue</div>
                            <div class="chart-tab" data-chart="messages">Messages</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Subscription Distribution</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="subscriptionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tables Section -->
        <div class="tables-section">
            <!-- Recent Users Table -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">Recent Users</div>
                    <div class="table-badge badge-blue"><?php echo number_format($totalUsers); ?> total</div>
                </div>
                <div class="table-content">
                    <?php if (empty($recentUsers)): ?>
                        <div class="empty-state">
                            <p>No users found.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php if (isSubscriptionActive($user)): ?>
                                                <span class="status-badge status-active">Active</span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($user['created_at']); ?></td>
                                        <td>
                                            <a href="user-details.php?id=<?php echo $user['id']; ?>" class="view-all">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="table-footer">
                    <a href="users.php" class="view-all">
                        View All Users <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Recent Payments Table -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">Recent Payments</div>
                    <div class="table-badge badge-blue"><?php echo formatCurrency($totalPayments); ?> total</div>
                </div>
                <div class="table-content">
                    <?php if (empty($recentPayments)): ?>
                        <div class="empty-state">
                            <p>No payments found.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['subscription_name']); ?></td>
                                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($payment['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="table-footer">
                    <a href="payments.php" class="view-all">
                        View All Payments <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- System Section -->
        <div class="system-section">
            <!-- Notifications Card -->
            <div class="notification-card">
                <div class="table-header">
                    <div class="table-title">System Notifications</div>
                    <div class="table-badge badge-blue"><?php echo count($systemNotifications); ?> new</div>
                </div>
                <div class="notification-list">
                    <?php if (empty($systemNotifications)): ?>
                        <div class="empty-state">
                            <p>No notifications at this time.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($systemNotifications as $notification): ?>
                            <div class="notification-item">
                                <div class="notification-icon <?php echo $notification['type']; ?>">
                                    <i class="fas fa-<?php echo $notification['type'] === 'payment' ? 'money-bill-wave' : 'tag'; ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-time"><?php echo timeAgo($notification['time']); ?></div>
                                </div>
                                <div class="notification-action">
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="view-all">View</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Updates Card -->
            <div class="notification-card">
                <div class="table-header">
                    <div class="table-title">Recent Updates</div>
                </div>
                <div class="system-updates">
                    <?php foreach ($systemUpgrades as $upgrade): ?>
                        <div class="update-item">
                            <div class="update-header">
                                <div class="update-version">Version <?php echo htmlspecialchars($upgrade['version']); ?></div>
                                <div class="update-date"><?php echo formatDate($upgrade['date']); ?></div>
                            </div>
                            <ul class="update-features">
                                <?php foreach ($upgrade['features'] as $feature): ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebar = document.getElementById('admin-sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            
            if (sidebar.classList.contains('active')) {
                mobileToggle.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
    }
    
    // Activity Chart
    const activityChartElement = document.getElementById('activityChart');
    if (activityChartElement) {
        const ctx = activityChartElement.getContext('2d');
        
        const dates = <?php echo json_encode(array_keys($dailyVisitors)); ?>;
        const formattedDates = dates.map(date => {
            const d = new Date(date);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const visitorsData = <?php echo json_encode(array_values($dailyVisitors)); ?>;
        const revenueData = <?php echo json_encode(array_values($dailyRevenue)); ?>;
        const messagesData = <?php echo json_encode(array_values($dailyMessages)); ?>;
        
        const activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: formattedDates,
                datasets: [
                    {
                        label: 'Visitors',
                        data: visitorsData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(17, 24, 39, 0.8)',
                        padding: 10,
                        cornerRadius: 4,
                        titleColor: '#fff',
                        bodyColor: 'rgba(255, 255, 255, 0.8)',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            color: '#6b7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            color: '#6b7280',
                            padding: 10
                        }
                    }
                }
            }
        });
        
        // Chart tab switching
        const chartTabs = document.querySelectorAll('.chart-tab');
        chartTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const chartType = this.getAttribute('data-chart');
                
                // Update active tab
                chartTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Update chart data
                let chartData, chartColor, chartLabel;
                
                switch (chartType) {
                    case 'visitors':
                        chartData = visitorsData;
                        chartColor = '#3b82f6';
                        chartLabel = 'Visitors';
                        break;
                    case 'revenue':
                        chartData = revenueData;
                        chartColor = '#10b981';
                        chartLabel = 'Revenue';
                        break;
                    case 'messages':
                        chartData = messagesData;
                        chartColor = '#f59e0b';
                        chartLabel = 'Messages';
                        break;
                }
                
                activityChart.data.datasets[0].data = chartData;
                activityChart.data.datasets[0].label = chartLabel;
                activityChart.data.datasets[0].borderColor = chartColor;
                activityChart.data.datasets[0].pointBackgroundColor = chartColor;
                activityChart.data.datasets[0].backgroundColor = `${chartColor}1a`; // 10% opacity
                activityChart.update();
            });
        });
    }
    
    // Subscription Distribution Chart
    const subscriptionChartElement = document.getElementById('subscriptionChart');
    if (subscriptionChartElement) {
        const ctx = subscriptionChartElement.getContext('2d');
        
        const subscriptionData = <?php echo json_encode($subscriptionStats); ?>;
        const subscriptionNames = subscriptionData.map(item => item.name);
        const subscriptionCounts = subscriptionData.map(item => item.user_count);
        const subscriptionColors = [
            '#3b82f6', // Blue
            '#10b981', // Green
            '#f59e0b', // Yellow
            '#8b5cf6', // Purple
            '#ec4899'  // Pink
        ];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: subscriptionNames,
                datasets: [{
                    data: subscriptionCounts,
                    backgroundColor: subscriptionColors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 12
                            },
                            color: '#6b7280'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.8)',
                        padding: 10,
                        cornerRadius: 4,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} users (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
});

// Helper function for time ago formatting (for notifications)
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) {
        return interval + " year" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) {
        return interval + " month" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) {
        return interval + " day" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) {
        return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) {
        return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
    }
    
    return "Just now";
}
</script>

<?php include '../includes/footer.php'; ?>
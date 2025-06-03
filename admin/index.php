<?php
$pageTitle = 'Admin Dashboard';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Helper Functions (only if not already defined in functions.php)
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '$' . number_format($amount, 2);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date('M j, Y', strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($date) {
        return date('M j, Y g:i A', strtotime($date));
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60) . ' min ago';
        if ($time < 86400) return floor($time/3600) . ' hr ago';
        if ($time < 2592000) return floor($time/86400) . ' days ago';
        return formatDate($datetime);
    }
}

if (!function_exists('validateDate')) {
    function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

// Get current date range for analytics
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

// Handle date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $inputStartDate = $_GET['start_date'];
    $inputEndDate = $_GET['end_date'];
    
    if (validateDate($inputStartDate) && validateDate($inputEndDate)) {
        $startDate = $inputStartDate;
        $endDate = $inputEndDate;
    }
}

// Get overall stats with error handling
try {
    $totalUsers = $db->fetch("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
    $activeSubscriptions = $db->fetch("SELECT COUNT(*) as count FROM users WHERE subscription_status = 'active' AND subscription_expiry > NOW()")['count'] ?? 0;
    $totalMessages = $db->fetch("SELECT COUNT(*) as count FROM messages")['count'] ?? 0;
    $totalPayments = $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'")['total'] ?? 0;
    $totalVisitors = $db->fetch("SELECT COUNT(*) as count FROM visitors")['count'] ?? 0;
    $pendingPayments = $db->fetch("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")['count'] ?? 0;
} catch (Exception $e) {
    $totalUsers = $activeSubscriptions = $totalMessages = $totalPayments = $totalVisitors = $pendingPayments = 0;
}

// Get stats for the selected period
try {
    $newUsers = $db->fetch(
        "SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ?", 
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    )['count'] ?? 0;

    $periodRevenue = $db->fetch(
        "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND created_at BETWEEN ? AND ?", 
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    )['total'] ?? 0;

    $newMessages = $db->fetch(
        "SELECT COUNT(*) as count FROM messages WHERE created_at BETWEEN ? AND ?", 
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    )['count'] ?? 0;

    $newVisitors = $db->fetch(
        "SELECT COUNT(*) as count FROM visitors WHERE created_at BETWEEN ? AND ?", 
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    )['count'] ?? 0;

    $conversationVisitors = $db->fetch(
        "SELECT COUNT(DISTINCT visitor_id) as count FROM messages WHERE created_at BETWEEN ? AND ?", 
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    )['count'] ?? 0;
} catch (Exception $e) {
    $newUsers = $periodRevenue = $newMessages = $newVisitors = $conversationVisitors = 0;
}

$conversionRate = $newVisitors > 0 ? round(($conversationVisitors / $newVisitors) * 100, 2) : 0;

// Get daily stats for chart
$dailyUsers = [];
$dailyRevenue = [];
$dailyVisitors = [];
$dailyMessages = [];

$startTimestamp = strtotime($startDate);
$endTimestamp = strtotime($endDate);
$daysCount = ceil(($endTimestamp - $startTimestamp) / 86400) + 1;

// Initialize arrays with all dates in range
for ($i = 0; $i < $daysCount; $i++) {
    $currentDate = date('Y-m-d', strtotime("+{$i} days", $startTimestamp));
    $dailyUsers[$currentDate] = 0;
    $dailyRevenue[$currentDate] = 0;
    $dailyVisitors[$currentDate] = 0;
    $dailyMessages[$currentDate] = 0;
}

// Populate daily stats with error handling
try {
    // Users by day
    $usersByDay = $db->fetchAll(
        "SELECT DATE(created_at) as date, COUNT(*) as count FROM users 
         WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    foreach ($usersByDay as $row) {
        if (isset($dailyUsers[$row['date']])) {
            $dailyUsers[$row['date']] = (int)$row['count'];
        }
    }

    // Revenue by day
    $revenueByDay = $db->fetchAll(
        "SELECT DATE(created_at) as date, COALESCE(SUM(amount), 0) as total FROM payments 
         WHERE status = 'completed' AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    foreach ($revenueByDay as $row) {
        if (isset($dailyRevenue[$row['date']])) {
            $dailyRevenue[$row['date']] = (float)$row['total'];
        }
    }

    // Visitors by day
    $visitorsByDay = $db->fetchAll(
        "SELECT DATE(created_at) as date, COUNT(*) as count FROM visitors 
         WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    foreach ($visitorsByDay as $row) {
        if (isset($dailyVisitors[$row['date']])) {
            $dailyVisitors[$row['date']] = (int)$row['count'];
        }
    }

    // Messages by day
    $messagesByDay = $db->fetchAll(
        "SELECT DATE(created_at) as date, COUNT(*) as count FROM messages 
         WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    foreach ($messagesByDay as $row) {
        if (isset($dailyMessages[$row['date']])) {
            $dailyMessages[$row['date']] = (int)$row['count'];
        }
    }
} catch (Exception $e) {
    // Keep default empty arrays if there's an error
}

// Get subscription distribution
try {
    $subscriptionStats = $db->fetchAll(
        "SELECT 
            COALESCE(s.name, 'No Subscription') as name, 
            COUNT(u.id) as user_count 
         FROM users u
         LEFT JOIN subscriptions s ON u.subscription_id = s.id 
         GROUP BY COALESCE(s.id, 'none'), COALESCE(s.name, 'No Subscription')"
    );
} catch (Exception $e) {
    $subscriptionStats = [['name' => 'No Data', 'user_count' => 0]];
}

// Get recent data
try {
    $recentUsers = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
} catch (Exception $e) {
    $recentUsers = [];
}

try {
    $recentPayments = $db->fetchAll(
        "SELECT p.*, u.name as user_name, u.email as user_email, 
                COALESCE(s.name, 'Unknown Plan') as subscription_name 
         FROM payments p 
         LEFT JOIN users u ON p.user_id = u.id 
         LEFT JOIN subscriptions s ON p.subscription_id = s.id 
         ORDER BY p.created_at DESC 
         LIMIT 5"
    );
} catch (Exception $e) {
    $recentPayments = [];
}

try {
    $recentMessages = $db->fetchAll(
        "SELECT m.*, u.name as user_name, 
                COALESCE(v.name, CONCAT('Visitor #', m.visitor_id)) as visitor_name,
                v.email as visitor_email
         FROM messages m
         LEFT JOIN users u ON m.user_id = u.id
         LEFT JOIN visitors v ON m.visitor_id = v.id
         ORDER BY m.created_at DESC
         LIMIT 5"
    );
} catch (Exception $e) {
    $recentMessages = [];
}

// System notifications
$systemNotifications = [];

if ($pendingPayments > 0) {
    $systemNotifications[] = [
        'type' => 'payment',
        'message' => "You have $pendingPayments pending payment" . ($pendingPayments > 1 ? 's' : '') . " to review.",
        'time' => date('Y-m-d H:i:s'),
        'link' => 'payments.php?status=pending'
    ];
}

try {
    $expiringSubscriptions = $db->fetch(
        "SELECT COUNT(*) as count FROM users 
         WHERE subscription_status = 'active' 
         AND subscription_expiry BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)"
    )['count'] ?? 0;

    if ($expiringSubscriptions > 0) {
        $systemNotifications[] = [
            'type' => 'subscription',
            'message' => "$expiringSubscriptions subscription" . ($expiringSubscriptions > 1 ? 's' : '') . " expiring in the next 7 days.",
            'time' => date('Y-m-d H:i:s'),
            'link' => 'subscriptions.php?filter=expiring'
        ];
    }
} catch (Exception $e) {
    // Ignore errors for expiring subscriptions
}

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            background-color: #f9fafb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar Styles */
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
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .admin-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
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
            background-color: rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 10px;
        }

        .admin-name {
            font-size: 0.9rem;
            color: white;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-role {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
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
            font-weight: 600;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            background-color: #f3f4f6;
            color: #3b82f6;
            text-decoration: none;
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
            flex-shrink: 0;
        }

        .menu-item.active .menu-icon {
            color: #3b82f6;
        }

        /* Main Content */
        .admin-content {
            margin-left: 250px;
            padding: 30px;
            min-height: calc(100vh - 60px);
            transition: margin-left 0.3s ease;
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
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
            flex-wrap: wrap;
        }

        .date-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
        }

        .date-filter label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 0;
        }

        .date-filter input {
            padding: 6px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 0.85rem;
            min-width: 120px;
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
            transition: transform 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stats-title {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 6px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
            flex-shrink: 0;
        }

        .stats-icon.blue { background-color: #3b82f6; }
        .stats-icon.green { background-color: #10b981; }
        .stats-icon.orange { background-color: #f59e0b; }
        .stats-icon.purple { background-color: #8b5cf6; }
        .stats-icon.pink { background-color: #ec4899; }
        .stats-icon.indigo { background-color: #6366f1; }

        .stats-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 10px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .stats-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            overflow: hidden;
        }

        .stats-trend-positive { color: #10b981; }
        .stats-trend-negative { color: #ef4444; }
        .stats-trend-neutral { color: #6b7280; }

        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
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
            flex-wrap: wrap;
            gap: 10px;
        }

        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .chart-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .chart-tab {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            white-space: nowrap;
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
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
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
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-title {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .table-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            white-space: nowrap;
        }

        .table-content {
            max-height: 400px;
            overflow-y: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .data-table th,
        .data-table td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 150px;
        }

        .data-table th {
            background-color: #f9fafb;
            font-weight: 500;
            color: #6b7280;
            font-size: 0.85rem;
            position: sticky;
            top: 0;
            z-index: 10;
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
            color: #2563eb;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .status-completed { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-pending { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .status-failed { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .status-active { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-inactive { background-color: rgba(107, 114, 128, 0.1); color: #6b7280; }

        /* System Information */
        .system-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
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
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            padding: 15px 20px;
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
            gap: 15px;
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
            min-width: 0;
        }

        .notification-message {
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #4b5563;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .notification-action {
            flex-shrink: 0;
        }

        .empty-state {
            padding: 30px;
            text-align: center;
            color: #6b7280;
        }

        /* Mobile Styles */
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 110;
            border: none;
            cursor: pointer;
        }

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
                padding: 20px;
            }
            
            .mobile-sidebar-toggle {
                display: flex;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media screen and (max-width: 768px) {
            .admin-content {
                padding: 15px;
            }
            
            .date-filter {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            
            .header-actions {
                width: 100%;
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .chart-actions {
                width: 100%;
                justify-content: space-around;
            }
        }

        @media screen and (max-width: 576px) {
            .data-table th,
            .data-table td {
                padding: 8px 12px;
                font-size: 0.85rem;
                max-width: 100px;
            }
            
            .tables-section {
                grid-template-columns: 1fr;
            }
            
            .system-section {
                grid-template-columns: 1fr;
            }
        }

        /* Scrollbar Styling */
        .table-content::-webkit-scrollbar,
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }

        .table-content::-webkit-scrollbar-track,
        .notification-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .table-content::-webkit-scrollbar-thumb,
        .notification-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .table-content::-webkit-scrollbar-thumb:hover,
        .notification-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>

<div class="admin-dashboard">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="sidebar-header">
            <h2 class="admin-title">Admin Panel</h2>
            <div class="admin-info">
                <div class="admin-avatar">A</div>
                <div>
                    <div class="admin-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
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
            <a href="../logout.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-sign-out-alt"></i></div>
                Logout
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
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-title">
                    <div class="stats-icon blue"><i class="fas fa-users"></i></div>
                    Total Users
                </div>
                <div class="stats-value"><?php echo number_format($totalUsers); ?></div>
                <div class="stats-trend <?php echo $newUsers > 0 ? 'stats-trend-positive' : 'stats-trend-neutral'; ?>">
                    <i class="fas fa-<?php echo $newUsers > 0 ? 'arrow-up' : 'minus'; ?>"></i>
                    <?php echo number_format($newUsers); ?> new in period
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-title">
                    <div class="stats-icon green"><i class="fas fa-check-circle"></i></div>
                    Active Subscriptions
                </div>
                <div class="stats-value"><?php echo number_format($activeSubscriptions); ?></div>
                <div class="stats-trend stats-trend-neutral">
                    <i class="fas fa-percent"></i>
                    <?php echo $totalUsers > 0 ? round(($activeSubscriptions / $totalUsers) * 100) : 0; ?>% of all users
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-title">
                    <div class="stats-icon orange"><i class="fas fa-comments"></i></div>
                    Total Messages
                </div>
                <div class="stats-value"><?php echo number_format($totalMessages); ?></div>
                <div class="stats-trend <?php echo $newMessages > 0 ? 'stats-trend-positive' : 'stats-trend-neutral'; ?>">
                    <i class="fas fa-<?php echo $newMessages > 0 ? 'arrow-up' : 'minus'; ?>"></i>
                    <?php echo number_format($newMessages); ?> new in period
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-title">
                    <div class="stats-icon purple"><i class="fas fa-dollar-sign"></i></div>
                    Total Revenue
                </div>
                <div class="stats-value"><?php echo formatCurrency($totalPayments); ?></div>
                <div class="stats-trend <?php echo $periodRevenue > 0 ? 'stats-trend-positive' : 'stats-trend-neutral'; ?>">
                    <i class="fas fa-<?php echo $periodRevenue > 0 ? 'arrow-up' : 'minus'; ?>"></i>
                    <?php echo formatCurrency($periodRevenue); ?> in period
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-title">
                    <div class="stats-icon pink"><i class="fas fa-eye"></i></div>
                    Total Visitors
                </div>
                <div class="stats-value"><?php echo number_format($totalVisitors); ?></div>
                <div class="stats-trend <?php echo $newVisitors > 0 ? 'stats-trend-positive' : 'stats-trend-neutral'; ?>">
                    <i class="fas fa-<?php echo $newVisitors > 0 ? 'arrow-up' : 'minus'; ?>"></i>
                    <?php echo number_format($newVisitors); ?> new in period
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-title">
                    <div class="stats-icon indigo"><i class="fas fa-chart-pie"></i></div>
                    Chat Conversion Rate
                </div>
                <div class="stats-value"><?php echo $conversionRate; ?>%</div>
                <div class="stats-trend stats-trend-neutral">
                    <i class="fas fa-info-circle"></i>
                    Visitors who started chat
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
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
        
        <!-- Tables Section -->
        <div class="tables-section">
            <!-- Recent Users Table -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">Recent Users</div>
                    <div class="table-badge"><?php echo number_format($totalUsers); ?> total</div>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td title="<?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>
                                        </td>
                                        <td>
                                            <?php if (isSubscriptionActive($user)): ?>
                                                <span class="status-badge status-active">Active</span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td title="<?php echo formatDate($user['created_at'] ?? date('Y-m-d')); ?>">
                                            <?php echo formatDate($user['created_at'] ?? date('Y-m-d')); ?>
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
                    <div class="table-badge"><?php echo formatCurrency($totalPayments); ?> total</div>
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
                                        <td title="<?php echo htmlspecialchars($payment['user_name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($payment['user_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($payment['subscription_name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($payment['subscription_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td><?php echo formatCurrency($payment['amount'] ?? 0); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $payment['status'] ?? 'pending'; ?>">
                                                <?php echo ucfirst($payment['status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                        <td title="<?php echo formatDate($payment['created_at'] ?? date('Y-m-d')); ?>">
                                            <?php echo formatDate($payment['created_at'] ?? date('Y-m-d')); ?>
                                        </td>
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
                    <div class="table-badge"><?php echo count($systemNotifications); ?> new</div>
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
                                    <div class="notification-message" title="<?php echo htmlspecialchars($notification['message']); ?>">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
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
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (!sidebar.contains(event.target) && !mobileToggle.contains(event.target) && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
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
                activityChart.data.datasets[0].backgroundColor = `${chartColor}1a`;
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
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>
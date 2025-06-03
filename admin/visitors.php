<?php
$pageTitle = 'Visitor Management';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Helper functions
if (!function_exists('formatDate')) {
    function formatDate($date) {
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

if (!function_exists('getCountryFromIP')) {
    function getCountryFromIP($ip) {
        // Simple IP to country detection (you can integrate with a real service)
        if ($ip === '127.0.0.1' || $ip === '::1') return 'Local';
        // You can integrate with services like ipapi.co, ipinfo.io, etc.
        return 'Unknown';
    }
}

if (!function_exists('getBrowserInfo')) {
    function getBrowserInfo($userAgent) {
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        if (strpos($userAgent, 'Opera') !== false) return 'Opera';
        return 'Unknown';
    }
}

if (!function_exists('getDeviceType')) {
    function getDeviceType($userAgent) {
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            if (preg_match('/iPad/', $userAgent)) return 'Tablet';
            return 'Mobile';
        }
        return 'Desktop';
    }
}

// Build filter conditions
$whereConditions = [];
$params = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $whereConditions[] = "v.status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['device_type']) && !empty($_GET['device_type'])) {
    $whereConditions[] = "v.device_type = ?";
    $params[] = $_GET['device_type'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $whereConditions[] = "(v.name LIKE ? OR v.email LIKE ? OR v.ip_address LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $whereConditions[] = "DATE(v.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $whereConditions[] = "DATE(v.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

if (isset($_GET['country']) && !empty($_GET['country'])) {
    $whereConditions[] = "v.country = ?";
    $params[] = $_GET['country'];
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count total visitors
$countQuery = "SELECT COUNT(*) as count FROM visitors v $whereClause";
$totalVisitors = $db->fetch($countQuery, $params)['count'] ?? 0;
$totalPages = ceil($totalVisitors / $limit);

// Get visitors
$query = "SELECT v.*, 
                 COUNT(m.id) as message_count,
                 MAX(m.created_at) as last_message_at
          FROM visitors v 
          LEFT JOIN messages m ON v.id = m.visitor_id 
          $whereClause 
          GROUP BY v.id
          ORDER BY v.created_at DESC 
          LIMIT $limit OFFSET $offset";

$visitors = $db->fetchAll($query, $params);

// Get visitor statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM visitors")['count'] ?? 0,
    'active' => $db->fetch("SELECT COUNT(*) as count FROM visitors WHERE status = 'active'")['count'] ?? 0,
    'today' => $db->fetch("SELECT COUNT(*) as count FROM visitors WHERE DATE(created_at) = CURDATE()")['count'] ?? 0,
    'this_week' => $db->fetch("SELECT COUNT(*) as count FROM visitors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0,
    'with_messages' => $db->fetch("SELECT COUNT(DISTINCT visitor_id) as count FROM messages")['count'] ?? 0
];

// Get top countries
$topCountries = $db->fetchAll(
    "SELECT country, COUNT(*) as count 
     FROM visitors 
     WHERE country IS NOT NULL AND country != '' 
     GROUP BY country 
     ORDER BY count DESC 
     LIMIT 5"
);

// Get device statistics
$deviceStats = $db->fetchAll(
    "SELECT device_type, COUNT(*) as count 
     FROM visitors 
     WHERE device_type IS NOT NULL 
     GROUP BY device_type 
     ORDER BY count DESC"
);

// Get browser statistics
$browserStats = $db->fetchAll(
    "SELECT browser, COUNT(*) as count 
     FROM visitors 
     WHERE browser IS NOT NULL 
     GROUP BY browser 
     ORDER BY count DESC 
     LIMIT 5"
);

// Get recent activity
$recentActivity = $db->fetchAll(
    "SELECT v.name, v.email, v.created_at, v.country, v.device_type,
            COUNT(m.id) as messages_sent
     FROM visitors v 
     LEFT JOIN messages m ON v.id = m.visitor_id 
     WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
     GROUP BY v.id 
     ORDER BY v.created_at DESC 
     LIMIT 10"
);

// Build query string for pagination
function buildQueryString($excludeKey = '') {
    $params = $_GET;
    if ($excludeKey && isset($params[$excludeKey])) {
        unset($params[$excludeKey]);
    }
    return !empty($params) ? '&' . http_build_query($params) : '';
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
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            background-color: #f5f6fa;
            font-family: 'Inter', system-ui, sans-serif;
        }

        .visitors-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .visitors-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .visitors-subtitle {
            opacity: 0.9;
            margin-bottom: 0;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
            transition: transform 0.2s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.total { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-icon.active { background: linear-gradient(135deg, #10b981, #047857); }
        .stat-icon.today { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.week { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-icon.engaged { background: linear-gradient(135deg, #ec4899, #db2777); }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }

        .filter-card, .table-card, .analytics-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }

        .card-header-custom {
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-title-custom {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .custom-table {
            margin: 0;
            font-size: 0.9rem;
        }

        .custom-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #6b7280;
            border: none;
            padding: 1rem;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .custom-table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid #f1f3f5;
            vertical-align: middle;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .custom-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }

        .table-responsive::-webkit-scrollbar {
            width: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-blocked {
            background: #fef3c7;
            color: #92400e;
        }

        .device-badge {
            padding: 0.25rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #e5e7eb;
            color: #374151;
        }

        .device-badge.desktop {
            background: #dcfce7;
            color: #166534;
        }

        .device-badge.mobile {
            background: #dbeafe;
            color: #1e40af;
        }

        .device-badge.tablet {
            background: #fef3c7;
            color: #92400e;
        }

        .visitor-info {
            display: flex;
            flex-direction: column;
        }

        .visitor-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .visitor-email {
            color: #6b7280;
            font-size: 0.8rem;
        }

        .country-flag {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }

        .activity-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .activity-indicator.active {
            background: #10b981;
            animation: pulse 2s infinite;
        }

        .activity-indicator.inactive {
            background: #6b7280;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .mini-chart {
            height: 200px;
        }

        @media (max-width: 768px) {
            .visitors-header {
                padding: 1.5rem 0;
            }

            .visitors-title {
                font-size: 1.5rem;
            }

            .custom-table {
                font-size: 0.8rem;
            }

            .custom-table th,
            .custom-table td {
                padding: 0.75rem 0.5rem;
            }

            .stat-card {
                margin-bottom: 1rem;
            }

            .visitor-info {
                max-width: 120px;
            }
        }

        @media (max-width: 576px) {
            .table-responsive {
                font-size: 0.75rem;
            }

            .visitor-info {
                max-width: 100px;
            }
        }

        .pagination {
            margin-top: 2rem;
        }

        .page-link {
            color: #3b82f6;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 0.75rem;
        }

        .page-link:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            color: #1d4ed8;
        }

        .page-item.active .page-link {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }

        .live-indicator {
            background: #10b981;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="visitors-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="visitors-title">
                    <i class="bi bi-people me-2"></i>Visitor Management
                </h1>
                <p class="visitors-subtitle">
                    Monitor and analyze visitor behavior and engagement
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="live-indicator">
                    <i class="bi bi-broadcast me-1"></i>Live Monitoring
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-md-4">
    <!-- Statistics Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card stat-card">
                <div class="stat-icon total">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <p class="stat-label">Total Visitors</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card stat-card">
                <div class="stat-icon active">
                    <i class="bi bi-person-check"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                <p class="stat-label">Active Visitors</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card stat-card">
                <div class="stat-icon today">
                    <i class="bi bi-calendar-day"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['today']); ?></div>
                <p class="stat-label">Today</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card stat-card">
                <div class="stat-icon week">
                    <i class="bi bi-calendar-week"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['this_week']); ?></div>
                <p class="stat-label">This Week</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-8 col-sm-12">
            <div class="card stat-card">
                <div class="stat-icon engaged">
                    <i class="bi bi-chat-dots"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['with_messages']); ?></div>
                <p class="stat-label">Engaged Visitors (Sent Messages)</p>
            </div>
        </div>
    </div>

    <!-- Analytics Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card analytics-card">
                <div class="card-header-custom">
                    <h5 class="card-title-custom">
                        <i class="bi bi-graph-up me-2"></i>Visitor Activity Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="visitorChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card analytics-card">
                <div class="card-header-custom">
                    <h5 class="card-title-custom">
                        <i class="bi bi-device-hdd me-2"></i>Device Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container mini-chart">
                        <canvas id="deviceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card analytics-card">
                <div class="card-header-custom">
                    <h6 class="card-title-custom">
                        <i class="bi bi-geo-alt me-2"></i>Top Countries
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($topCountries)): ?>
                        <div class="text-center text-muted">
                            <i class="bi bi-globe"></i>
                            <p class="mb-0">No country data available</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topCountries as $country): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <span class="country-flag">🌍</span>
                                    <?php echo htmlspecialchars($country['country']); ?>
                                </span>
                                <span class="badge bg-primary"><?php echo $country['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card analytics-card">
                <div class="card-header-custom">
                    <h6 class="card-title-custom">
                        <i class="bi bi-browser-chrome me-2"></i>Popular Browsers
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($browserStats)): ?>
                        <div class="text-center text-muted">
                            <i class="bi bi-browser-chrome"></i>
                            <p class="mb-0">No browser data available</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($browserStats as $browser): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo htmlspecialchars($browser['browser']); ?></span>
                                <span class="badge bg-info"><?php echo $browser['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card analytics-card">
                <div class="card-header-custom">
                    <h6 class="card-title-custom">
                        <i class="bi bi-clock me-2"></i>Recent Activity
                    </h6>
                </div>
                <div class="card-body">
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php if (empty($recentActivity)): ?>
                            <div class="text-center text-muted">
                                <i class="bi bi-clock"></i>
                                <p class="mb-0">No recent activity</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="activity-indicator active"></div>
                                    <div class="flex-grow-1">
                                        <small class="d-block">
                                            <strong><?php echo htmlspecialchars($activity['name'] ?? 'Anonymous'); ?></strong>
                                            <?php if ($activity['messages_sent'] > 0): ?>
                                                <span class="text-primary">(<?php echo $activity['messages_sent']; ?> messages)</span>
                                            <?php endif; ?>
                                        </small>
                                        <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card mb-4">
        <div class="card-header-custom">
            <h5 class="card-title-custom">
                <i class="bi bi-funnel me-2"></i>Filters & Search
            </h5>
        </div>
        <div class="card-body">
            <form method="get" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Visitor Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="blocked" <?php echo isset($_GET['status']) && $_GET['status'] === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="device_type" class="form-label">Device Type</label>
                        <select name="device_type" id="device_type" class="form-select">
                            <option value="">All Devices</option>
                            <option value="desktop" <?php echo isset($_GET['device_type']) && $_GET['device_type'] === 'desktop' ? 'selected' : ''; ?>>Desktop</option>
                            <option value="mobile" <?php echo isset($_GET['device_type']) && $_GET['device_type'] === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                            <option value="tablet" <?php echo isset($_GET['device_type']) && $_GET['device_type'] === 'tablet' ? 'selected' : ''; ?>>Tablet</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" 
                               value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="country" class="form-label">Country</label>
                        <input type="text" name="country" id="country" class="form-control" 
                               placeholder="e.g. US, UK..." 
                               value="<?php echo isset($_GET['country']) ? htmlspecialchars($_GET['country']) : ''; ?>">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <label for="search" class="form-label">Search Visitors</label>
                        <div class="input-group">
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="Search by name, email, or IP address..." 
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Search
                            </button>
                            <a href="visitors.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Visitors Table -->
    <div class="card table-card">
        <div class="card-header-custom">
            <h5 class="card-title-custom">
                Visitor Records
                <?php if (!empty($visitors)): ?>
                    <span class="badge bg-primary ms-2"><?php echo count($visitors); ?> of <?php echo number_format($totalVisitors); ?></span>
                <?php endif; ?>
            </h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportVisitors()">
                    <i class="bi bi-download"></i> Export
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <?php if (empty($visitors)): ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <h5>No visitors found</h5>
                    <p>Try adjusting your search criteria or filters.</p>
                </div>
            <?php else: ?>
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>Visitor</th>
                            <th>Device</th>
                            <th>Location</th>
                            <th>Messages</th>
                            <th>First Visit</th>
                            <th>Last Activity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitors as $visitor): ?>
                            <tr>
                                <td>
                                    <div class="visitor-info">
                                        <div class="visitor-name" title="<?php echo htmlspecialchars($visitor['name'] ?? 'Anonymous'); ?>">
                                            <?php if (!empty($visitor['name'])): ?>
                                                <?php echo htmlspecialchars($visitor['name']); ?>
                                            <?php else: ?>
                                                <em>Anonymous Visitor</em>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($visitor['email'])): ?>
                                            <div class="visitor-email" title="<?php echo htmlspecialchars($visitor['email']); ?>">
                                                <?php echo htmlspecialchars($visitor['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($visitor['ip_address'] ?? 'Unknown IP'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="device-badge <?php echo strtolower($visitor['device_type'] ?? 'unknown'); ?>">
                                        <?php 
                                        $deviceType = $visitor['device_type'] ?? getDeviceType($visitor['user_agent'] ?? '');
                                        echo ucfirst($deviceType); 
                                        ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo getBrowserInfo($visitor['user_agent'] ?? ''); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="country-flag">🌍</span>
                                    <?php 
                                    $country = $visitor['country'] ?? getCountryFromIP($visitor['ip_address'] ?? '');
                                    echo htmlspecialchars($country);
                                    ?>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <strong class="text-primary"><?php echo (int)$visitor['message_count']; ?></strong>
                                        <?php if ($visitor['message_count'] > 0): ?>
                                            <br>
                                            <small class="text-muted">
                                                Last: <?php echo timeAgo($visitor['last_message_at']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td title="<?php echo formatDate($visitor['created_at']); ?>">
                                    <?php echo date('M j, Y', strtotime($visitor['created_at'])); ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($visitor['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($visitor['last_activity'])): ?>
                                        <?php echo timeAgo($visitor['last_activity']); ?>
                                    <?php else: ?>
                                        <small class="text-muted">No activity</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $visitor['status'] ?? 'active'; ?>">
                                        <?php echo ucfirst($visitor['status'] ?? 'Active'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#visitorModal<?php echo $visitor['id']; ?>"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($visitor['message_count'] > 0): ?>
                                            <a href="messages.php?visitor_id=<?php echo $visitor['id']; ?>" 
                                               class="btn btn-outline-info btn-sm" 
                                               title="View Messages">
                                                <i class="bi bi-chat-dots"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-warning btn-sm" 
                                                onclick="toggleVisitorStatus(<?php echo $visitor['id']; ?>)"
                                                title="Toggle Status">
                                            <i class="bi bi-toggle-on"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Visitors pagination">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo buildQueryString('page'); ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                if ($start > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo buildQueryString('page'); ?>">1</a>
                    </li>
                    <?php if ($start > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif;
                endif;
                
                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo buildQueryString('page'); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor;
                
                if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo buildQueryString('page'); ?>"><?php echo $totalPages; ?></a>
                    </li>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo buildQueryString('page'); ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Visitor Detail Modals -->
<?php foreach ($visitors as $visitor): ?>
    <div class="modal fade" id="visitorModal<?php echo $visitor['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-circle me-2"></i>
                        Visitor Details - ID #<?php echo $visitor['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>Name:</strong> 
                            <?php echo htmlspecialchars($visitor['name'] ?? 'Anonymous'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Email:</strong> 
                            <?php echo htmlspecialchars($visitor['email'] ?? 'Not provided'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>IP Address:</strong> 
                            <?php echo htmlspecialchars($visitor['ip_address'] ?? 'Unknown'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Country:</strong> 
                            <?php echo htmlspecialchars($visitor['country'] ?? getCountryFromIP($visitor['ip_address'] ?? '')); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Device Type:</strong> 
                            <?php echo ucfirst($visitor['device_type'] ?? getDeviceType($visitor['user_agent'] ?? '')); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Browser:</strong> 
                            <?php echo getBrowserInfo($visitor['user_agent'] ?? ''); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> 
                            <span class="status-badge status-<?php echo $visitor['status'] ?? 'active'; ?>">
                                <?php echo ucfirst($visitor['status'] ?? 'Active'); ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Messages Sent:</strong> 
                            <span class="badge bg-primary"><?php echo (int)$visitor['message_count']; ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>First Visit:</strong> 
                            <?php echo formatDate($visitor['created_at']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Last Activity:</strong> 
                            <?php if (!empty($visitor['last_activity'])): ?>
                                <?php echo formatDate($visitor['last_activity']); ?>
                            <?php else: ?>
                                <em>No recent activity</em>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($visitor['user_agent'])): ?>
                            <div class="col-12">
                                <strong>User Agent:</strong>
                                <div class="mt-1 p-2 bg-light rounded">
                                    <small><?php echo htmlspecialchars($visitor['user_agent']); ?></small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($visitor['message_count'] > 0): ?>
                        <a href="messages.php?visitor_id=<?php echo $visitor['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-chat-dots me-1"></i>View Messages
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-warning" onclick="toggleVisitorStatus(<?php echo $visitor['id']; ?>)">
                        <i class="bi bi-toggle-on me-1"></i>Toggle Status
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sample data for charts (replace with real data from PHP)
    const visitorData = {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Visitors',
            data: [12, 19, 3, 5, 2, 3, 7],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4
        }]
    };

    // Visitor activity chart
    const visitorCtx = document.getElementById('visitorChart');
    if (visitorCtx) {
        new Chart(visitorCtx, {
            type: 'line',
            data: visitorData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Device distribution chart
    const deviceCtx = document.getElementById('deviceChart');
    if (deviceCtx) {
        const deviceData = <?php echo json_encode($deviceStats); ?>;
        const labels = deviceData.map(item => item.device_type);
        const data = deviceData.map(item => parseInt(item.count));
        const colors = ['#3b82f6', '#10b981', '#f59e0b'];

        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15 }
                    }
                }
            }
        });
    }
});

// Utility functions
function toggleVisitorStatus(visitorId) {
    if (confirm('Toggle visitor status?')) {
        // Add AJAX call to toggle status
        fetch('toggle_visitor_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ visitor_id: visitorId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error updating status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the status.');
        });
    }
}

function exportVisitors() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'export_visitors.php?' + params.toString();
}

function refreshData() {
    location.reload();
}

// Auto-refresh every 30 seconds
setInterval(function() {
    // Check for new visitors without full page reload
    fetch('check_new_visitors.php')
        .then(response => response.json())
        .then(data => {
            if (data.new_visitors > 0) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-info alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="bi bi-info-circle me-2"></i>
                    ${data.new_visitors} new visitor(s) detected. 
                    <a href="javascript:location.reload()" class="alert-link">Refresh to see updates</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container-fluid').prepend(alertDiv);
            }
        })
        .catch(error => console.log('Auto-refresh error:', error));
}, 30000);
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>
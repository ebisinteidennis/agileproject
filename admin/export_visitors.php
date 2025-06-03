<?php
$pageTitle = 'Export Visitors';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'])) {
    $exportType = $_POST['export_type'];
    
    // Build filter conditions
    $whereConditions = [];
    $params = [];
    
    if (!empty($_POST['status'])) {
        $whereConditions[] = "v.status = ?";
        $params[] = $_POST['status'];
    }
    
    if (!empty($_POST['device_type'])) {
        $whereConditions[] = "v.device_type = ?";
        $params[] = $_POST['device_type'];
    }
    
    if (!empty($_POST['date_from'])) {
        $whereConditions[] = "DATE(v.created_at) >= ?";
        $params[] = $_POST['date_from'];
    }
    
    if (!empty($_POST['date_to'])) {
        $whereConditions[] = "DATE(v.created_at) <= ?";
        $params[] = $_POST['date_to'];
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // Get visitors data
    $query = "SELECT v.*, COUNT(m.id) as message_count 
              FROM visitors v 
              LEFT JOIN messages m ON v.id = m.visitor_id 
              $whereClause 
              GROUP BY v.id 
              ORDER BY v.created_at DESC";
    
    $visitors = $db->fetchAll($query, $params);
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "visitors_export_{$timestamp}";
    
    switch ($exportType) {
        case 'csv':
            exportCSV($visitors, $filename);
            break;
        case 'json':
            exportJSON($visitors, $filename);
            break;
        default:
            $message = 'Invalid export format selected.';
            $messageType = 'error';
    }
}

function exportCSV($visitors, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID', 'Name', 'Email', 'IP Address', 'Country', 'Device Type', 
        'Browser', 'Status', 'Messages Sent', 'First Visit', 'Last Activity'
    ]);
    
    // Data rows
    foreach ($visitors as $visitor) {
        fputcsv($output, [
            $visitor['id'],
            $visitor['name'] ?? 'Anonymous',
            $visitor['email'] ?? 'Not provided',
            $visitor['ip_address'] ?? 'Unknown',
            $visitor['country'] ?? 'Unknown',
            $visitor['device_type'] ?? 'Unknown',
            $visitor['browser'] ?? 'Unknown',
            $visitor['status'] ?? 'active',
            $visitor['message_count'] ?? 0,
            $visitor['created_at'],
            $visitor['last_activity'] ?? 'Never'
        ]);
    }
    
    fclose($output);
    exit;
}

function exportJSON($visitors, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    $exportData = [
        'export_info' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_records' => count($visitors),
            'exported_by' => $_SESSION['user_name'] ?? 'Admin'
        ],
        'visitors' => $visitors
    ];
    
    echo json_encode($exportData, JSON_PRETTY_PRINT);
    exit;
}

// Get visitor statistics
$totalVisitors = $db->fetch("SELECT COUNT(*) as count FROM visitors")['count'] ?? 0;
$activeVisitors = $db->fetch("SELECT COUNT(*) as count FROM visitors WHERE status = 'active'")['count'] ?? 0;

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #f5f6fa; }
        .export-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .card { border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: none; }
        .stat-card { padding: 1.5rem; text-align: center; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #1f2937; }
        .stat-label { color: #6b7280; }
        .format-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .format-card:hover, .format-card.selected {
            border-color: #3b82f6;
            background: #f0f9ff;
        }
        .format-card input[type="radio"] { display: none; }
        .format-icon { font-size: 2rem; color: #3b82f6; margin-bottom: 1rem; }
        .btn-export {
            background: linear-gradient(135deg, #10b981, #047857);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="export-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="bi bi-download me-2"></i>Export Visitors</h1>
                <p class="opacity-75 mb-0">Download visitor data in your preferred format</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="visitors.php" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1"></i>Back to Visitors
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card stat-card">
                <div class="stat-value"><?php echo number_format($totalVisitors); ?></div>
                <p class="stat-label">Total Visitors</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card">
                <div class="stat-value"><?php echo number_format($activeVisitors); ?></div>
                <p class="stat-label">Active Visitors</p>
            </div>
        </div>
    </div>

    <!-- Export Form -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Export Settings</h5>
        </div>
        <div class="card-body">
            <form method="post" id="exportForm">
                <!-- Filters -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="blocked">Blocked</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Device Type</label>
                        <select name="device_type" class="form-select">
                            <option value="">All Devices</option>
                            <option value="desktop">Desktop</option>
                            <option value="mobile">Mobile</option>
                            <option value="tablet">Tablet</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control">
                    </div>
                </div>

                <!-- Export Format -->
                <div class="mb-4">
                    <h6 class="mb-3">Choose Export Format</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="format-card" for="csv">
                                <input type="radio" name="export_type" value="csv" id="csv" required>
                                <div class="format-icon"><i class="bi bi-filetype-csv"></i></div>
                                <h6>CSV Format</h6>
                                <p class="text-muted mb-0">Perfect for Excel and data analysis</p>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="format-card" for="json">
                                <input type="radio" name="export_type" value="json" id="json" required>
                                <div class="format-icon"><i class="bi bi-filetype-json"></i></div>
                                <h6>JSON Format</h6>
                                <p class="text-muted mb-0">Structured data for API integration</p>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Export Button -->
                <div class="text-center">
                    <button type="submit" class="btn btn-export">
                        <i class="bi bi-download me-2"></i>Generate Export
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-eye me-2"></i>Export Preview</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Messages</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="text-muted">
                            <td>123</td>
                            <td>John Doe</td>
                            <td>john@example.com</td>
                            <td>Desktop</td>
                            <td>Active</td>
                            <td>5</td>
                        </tr>
                        <tr class="text-muted">
                            <td colspan="6" class="text-center">
                                <em>... and all other visitors matching your filters</em>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format card selection
    const formatCards = document.querySelectorAll('.format-card');
    
    formatCards.forEach(card => {
        card.addEventListener('click', function() {
            formatCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });

    // Set default date range (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
    
    document.querySelector('input[name="date_to"]').value = today.toISOString().split('T')[0];
    document.querySelector('input[name="date_from"]').value = thirtyDaysAgo.toISOString().split('T')[0];
});
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>
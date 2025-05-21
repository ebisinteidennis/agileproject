<?php
$pageTitle = 'Advanced Reports';
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

// Get report data based on type
$reportType = isset($_GET['type']) ? $_GET['type'] : 'users';
$reportTitle = 'User Growth';
$chartType = 'line';

// Get report data
switch ($reportType) {
    case 'messages':
        $reportTitle = 'Message Volume';
        $data = $db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM messages 
             WHERE created_at BETWEEN ? AND ? 
             GROUP BY DATE(created_at) 
             ORDER BY date",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );
        break;
        
    case 'revenue':
        $reportTitle = 'Revenue';
        $data = $db->fetchAll(
            "SELECT DATE(created_at) as date, SUM(amount) as count 
             FROM payments 
             WHERE status = 'completed' AND created_at BETWEEN ? AND ? 
             GROUP BY DATE(created_at) 
             ORDER BY date",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );
        break;
        
    case 'visitors':
        $reportTitle = 'Visitor Traffic';
        $data = $db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM visitors 
             WHERE created_at BETWEEN ? AND ? 
             GROUP BY DATE(created_at) 
             ORDER BY date",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );
        break;
        
    case 'conversion':
        $reportTitle = 'Conversion Rate';
        $chartType = 'bar';
        
        // Get visitors who started conversations by day
        $conversions = $db->fetchAll(
            "SELECT DATE(v.created_at) as date, 
                COUNT(DISTINCT v.id) as visitors,
                COUNT(DISTINCT m.visitor_id) as converted
             FROM visitors v
             LEFT JOIN messages m ON v.id = m.visitor_id AND m.created_at BETWEEN ? AND ?
             WHERE v.created_at BETWEEN ? AND ?
             GROUP BY DATE(v.created_at)
             ORDER BY date",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59', $startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );
        
        $data = [];
        foreach ($conversions as $row) {
            $rate = $row['visitors'] > 0 ? round(($row['converted'] / $row['visitors']) * 100, 2) : 0;
            $data[] = [
                'date' => $row['date'],
                'count' => $rate
            ];
        }
        break;
        
    case 'subscriptions':
        $reportTitle = 'Subscription Distribution';
        $chartType = 'pie';
        $data = $db->fetchAll(
            "SELECT s.name, COUNT(u.id) as count 
             FROM subscriptions s 
             LEFT JOIN users u ON s.id = u.subscription_id AND u.subscription_status = 'active'
             GROUP BY s.id"
        );
        break;
        
    default: // users
        $reportTitle = 'User Growth';
        $data = $db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM users 
             WHERE created_at BETWEEN ? AND ? 
             GROUP BY DATE(created_at) 
             ORDER BY date",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );
        break;
}

// Format data for charts
$chartLabels = [];
$chartValues = [];

if ($chartType == 'pie') {
    foreach ($data as $row) {
        $chartLabels[] = $row['name'];
        $chartValues[] = $row['count'];
    }
} else {
    // Fill missing dates with zeros
    $dateRange = [];
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    
    while ($current <= $end) {
        $dateRange[date('Y-m-d', $current)] = 0;
        $current = strtotime('+1 day', $current);
    }
    
    foreach ($data as $row) {
        $dateRange[$row['date']] = (float)$row['count'];
    }
    
    foreach ($dateRange as $date => $count) {
        $chartLabels[] = date('M d', strtotime($date));
        $chartValues[] = $count;
    }
}

// Include header
include '../includes/header.php';
?>

<!-- Bootstrap CSS (if not already included in header.php) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="d-flex align-items-center pb-3 mb-3 border-bottom px-3">
                    <span class="fs-5 fw-semibold text-primary">Admin Panel</span>
                </div>
                
                <div class="mb-2 px-3 d-flex align-items-center">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                        <span class="fw-bold"><?php echo substr($_SESSION['user_name'] ?? 'A', 0, 1); ?></span>
                    </div>
                    <div>
                        <div class="fw-medium"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></div>
                        <small class="text-muted">Administrator</small>
                    </div>
                </div>
                
                <ul class="nav flex-column mt-4">
                    <li class="nav-item">
                        <small class="text-uppercase px-3 text-muted">Main</small>
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2 me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item mt-2">
                        <small class="text-uppercase px-3 text-muted">User Management</small>
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people me-2"></i>
                            Users
                        </a>
                        <a class="nav-link" href="subscriptions.php">
                            <i class="bi bi-tags me-2"></i>
                            Subscriptions
                        </a>
                        <a class="nav-link" href="payments.php">
                            <i class="bi bi-credit-card me-2"></i>
                            Payments
                        </a>
                    </li>
                    
                    <li class="nav-item mt-2">
                        <small class="text-uppercase px-3 text-muted">Content</small>
                        <a class="nav-link" href="messages.php">
                            <i class="bi bi-chat-dots me-2"></i>
                            Messages
                        </a>
                        <a class="nav-link" href="visitors.php">
                            <i class="bi bi-eye me-2"></i>
                            Visitors
                        </a>
                    </li>
                    
                    <li class="nav-item mt-2">
                        <small class="text-uppercase px-3 text-muted">Reports</small>
                        <a class="nav-link active" href="reports.php">
                            <i class="bi bi-bar-chart me-2"></i>
                            Advanced Reports
                        </a>
                    </li>
                    
                    <li class="nav-item mt-2">
                        <small class="text-uppercase px-3 text-muted">Settings</small>
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-gear me-2"></i>
                            Site Settings
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2">Advanced Reports</h1>
                    <p class="text-muted">Generate detailed analytics reports for your business</p>
                </div>
                
                <div class="btn-toolbar mb-2 mb-md-0">
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">
                        <div class="input-group me-2">
                            <span class="input-group-text">From</span>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" max="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        
                        <div class="input-group me-2">
                            <span class="input-group-text">To</span>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i> Apply
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Report Type Selection -->
            <div class="card mb-4">
                <div class="card-body p-0">
                    <div class="nav nav-pills nav-fill">
                        <a href="?type=users<?php echo "&start_date=$startDate&end_date=$endDate"; ?>" class="nav-link <?php echo $reportType == 'users' ? 'active' : ''; ?>">
                            <i class="bi bi-people me-1"></i> User Growth
                        </a>
                        <a href="?type=messages<?php echo "&start_date=$startDate&end_date=$endDate"; ?>" class="nav-link <?php echo $reportType == 'messages' ? 'active' : ''; ?>">
                            <i class="bi bi-chat-dots me-1"></i> Message Volume
                        </a>
                        <a href="?type=revenue<?php echo "&start_date=$startDate&end_date=$endDate"; ?>" class="nav-link <?php echo $reportType == 'revenue' ? 'active' : ''; ?>">
                            <i class="bi bi-currency-dollar me-1"></i> Revenue
                        </a>
                        <a href="?type=visitors<?php echo "&start_date=$startDate&end_date=$endDate"; ?>" class="nav-link <?php echo $reportType == 'visitors' ? 'active' : ''; ?>">
                            <i class="bi bi-eye me-1"></i> Visitors
                        </a>
                        <a href="?type=conversion<?php echo "&start_date=$startDate&end_date=$endDate"; ?>" class="nav-link <?php echo $reportType == 'conversion' ? 'active' : ''; ?>">
                            <i class="bi bi-pie-chart me-1"></i> Conversion Rate
                        </a>
                        <a href="?type=subscriptions<?php echo "&start_date=$startDate&end_date=$endDate"; ?>" class="nav-link <?php echo $reportType == 'subscriptions' ? 'active' : ''; ?>">
                            <i class="bi bi-tag me-1"></i> Subscriptions
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Report Chart -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0"><?php echo htmlspecialchars($reportTitle); ?> Report</h5>
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary btn-sm" id="downloadPdf">
                            <i class="bi bi-file-pdf me-1"></i> Export PDF
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="downloadCsv">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 400px;">
                        <canvas id="reportChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Report Summary -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Report Summary</h5>
                </div>
                <div class="card-body">
                    <?php if (count($chartValues) > 0): ?>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">Total</h6>
                                        <h3 class="card-text">
                                            <?php
                                            $total = array_sum($chartValues);
                                            echo $reportType == 'revenue' ? formatCurrency($total) : number_format($total);
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">Average</h6>
                                        <h3 class="card-text">
                                            <?php
                                            $avg = count($chartValues) > 0 ? $total / count($chartValues) : 0;
                                            echo $reportType == 'revenue' ? formatCurrency($avg) : number_format($avg, 2);
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">Highest</h6>
                                        <h3 class="card-text">
                                            <?php
                                            $max = count($chartValues) > 0 ? max($chartValues) : 0;
                                            echo $reportType == 'revenue' ? formatCurrency($max) : number_format($max, 2);
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No data available for the selected period.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Mobile menu toggle (Only visible on small screens) -->
<button class="btn btn-primary position-fixed bottom-0 end-0 m-3 d-md-none rounded-circle shadow" style="width: 50px; height: 50px;" id="mobile-sidebar-toggle">
    <i class="bi bi-list"></i>
</button>

<!-- Include Bootstrap & Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            
            if (sidebar.classList.contains('show')) {
                mobileToggle.innerHTML = '<i class="bi bi-x"></i>';
            } else {
                mobileToggle.innerHTML = '<i class="bi bi-list"></i>';
            }
        });
    }
    
    // Report Chart
    const chartElement = document.getElementById('reportChart');
    if (chartElement) {
        const ctx = chartElement.getContext('2d');
        
        const chartLabels = <?php echo json_encode($chartLabels); ?>;
        const chartValues = <?php echo json_encode($chartValues); ?>;
        const chartType = <?php echo json_encode($chartType); ?>;
        const reportType = <?php echo json_encode($reportType); ?>;
        
        let chartColor = '#0d6efd'; // Bootstrap primary color
        let chartOptions = {};
        
        switch (reportType) {
            case 'revenue':
                chartColor = '#198754'; // Bootstrap success color
                break;
            case 'messages':
                chartColor = '#fd7e14'; // Bootstrap orange color
                break;
            case 'conversion':
                chartColor = '#6f42c1'; // Bootstrap purple color
                chartOptions = {
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                };
                break;
            case 'visitors':
                chartColor = '#d63384'; // Bootstrap pink color
                break;
            case 'subscriptions':
                chartColor = ['#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#d63384'];
                break;
        }
        
        let chartConfig = {
            type: chartType,
            data: {
                labels: chartLabels,
                datasets: [{
                    label: '<?php echo addslashes($reportTitle); ?>',
                    data: chartValues,
                    backgroundColor: chartType === 'pie' ? chartColor : `${chartColor}20`,
                    borderColor: chartType === 'pie' ? '#ffffff' : chartColor,
                    borderWidth: chartType === 'pie' ? 2 : 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: chartColor,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: chartType === 'pie',
                        position: 'bottom'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(33, 37, 41, 0.8)',
                        padding: 10,
                        cornerRadius: 4,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.parsed;
                                
                                if (chartType === 'pie') {
                                    value = context.parsed;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${context.label}: ${value} (${percentage}%)`;
                                } else {
                                    if (reportType === 'revenue') {
                                        return label + ': ' + formatCurrency(value);
                                    } else if (reportType === 'conversion') {
                                        return label + ': ' + value + '%';
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                },
                ...chartOptions
            }
        };
        
        const reportChart = new Chart(ctx, chartConfig);
        
        // Export functions
        document.getElementById('downloadPdf').addEventListener('click', function() {
            alert('PDF export functionality would be implemented here.');
        });
        
        document.getElementById('downloadCsv').addEventListener('click', function() {
            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'Label,Value\n';
            
            for (let i = 0; i < chartLabels.length; i++) {
                csvContent += chartLabels[i] + ',' + chartValues[i] + '\n';
            }
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', '<?php echo $reportTitle; ?>_Report.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }
    
    // Helper function to format currency
    function formatCurrency(value) {
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN'
        }).format(value);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
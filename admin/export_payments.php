<?php
$pageTitle = 'Export Payments';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Helper functions
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '$' . number_format($amount, 2);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date('M j, Y g:i A', strtotime($date));
    }
}

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'])) {
    $exportType = $_POST['export_type'];
    
    // Build filter conditions
    $whereConditions = [];
    $params = [];
    
    if (!empty($_POST['status'])) {
        $whereConditions[] = "p.status = ?";
        $params[] = $_POST['status'];
    }
    
    if (!empty($_POST['payment_method'])) {
        $whereConditions[] = "p.payment_method = ?";
        $params[] = $_POST['payment_method'];
    }
    
    if (!empty($_POST['date_from'])) {
        $whereConditions[] = "DATE(p.created_at) >= ?";
        $params[] = $_POST['date_from'];
    }
    
    if (!empty($_POST['date_to'])) {
        $whereConditions[] = "DATE(p.created_at) <= ?";
        $params[] = $_POST['date_to'];
    }
    
    if (!empty($_POST['user_search'])) {
        $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $searchTerm = '%' . $_POST['user_search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // Get filtered payments
    $query = "SELECT p.*, u.name as user_name, u.email as user_email, 
                     COALESCE(s.name, 'Unknown Plan') as subscription_name 
              FROM payments p 
              LEFT JOIN users u ON p.user_id = u.id 
              LEFT JOIN subscriptions s ON p.subscription_id = s.id 
              $whereClause 
              ORDER BY p.created_at DESC";
    
    $payments = $db->fetchAll($query, $params);
    
    // Generate filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "payments_export_{$timestamp}";
    
    switch ($exportType) {
        case 'csv':
            exportToCSV($payments, $filename);
            break;
        case 'excel':
            exportToExcel($payments, $filename);
            break;
        case 'pdf':
            exportToPDF($payments, $filename);
            break;
        case 'json':
            exportToJSON($payments, $filename);
            break;
        default:
            $message = 'Invalid export format selected.';
            $messageType = 'error';
    }
}

// Export functions
function exportToCSV($payments, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    fputcsv($output, [
        'Payment ID',
        'Date',
        'User Name',
        'User Email',
        'Subscription Plan',
        'Amount',
        'Currency',
        'Payment Method',
        'Transaction Reference',
        'Status',
        'Created At',
        'Updated At'
    ]);
    
    // Write data rows
    foreach ($payments as $payment) {
        fputcsv($output, [
            $payment['id'],
            formatDate($payment['created_at']),
            $payment['user_name'] ?? 'N/A',
            $payment['user_email'] ?? 'N/A',
            $payment['subscription_name'],
            $payment['amount'],
            $payment['currency'] ?? 'USD',
            ucfirst($payment['payment_method']),
            $payment['transaction_reference'],
            ucfirst($payment['status']),
            $payment['created_at'],
            $payment['updated_at'] ?? $payment['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

function exportToJSON($payments, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $exportData = [
        'export_info' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_records' => count($payments),
            'exported_by' => $_SESSION['user_name'] ?? 'Admin'
        ],
        'payments' => []
    ];
    
    foreach ($payments as $payment) {
        $exportData['payments'][] = [
            'id' => (int)$payment['id'],
            'date' => formatDate($payment['created_at']),
            'user' => [
                'name' => $payment['user_name'] ?? 'N/A',
                'email' => $payment['user_email'] ?? 'N/A'
            ],
            'subscription_plan' => $payment['subscription_name'],
            'amount' => (float)$payment['amount'],
            'currency' => $payment['currency'] ?? 'USD',
            'payment_method' => $payment['payment_method'],
            'transaction_reference' => $payment['transaction_reference'],
            'status' => $payment['status'],
            'timestamps' => [
                'created_at' => $payment['created_at'],
                'updated_at' => $payment['updated_at'] ?? $payment['created_at']
            ]
        ];
    }
    
    echo json_encode($exportData, JSON_PRETTY_PRINT);
    exit;
}

function exportToExcel($payments, $filename) {
    // Simple Excel XML format
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<Worksheet ss:Name="Payments">' . "\n";
    echo '<Table>' . "\n";
    
    // Header row
    echo '<Row>' . "\n";
    $headers = ['Payment ID', 'Date', 'User Name', 'User Email', 'Subscription Plan', 'Amount', 'Payment Method', 'Transaction Reference', 'Status'];
    foreach ($headers as $header) {
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
    
    // Data rows
    foreach ($payments as $payment) {
        echo '<Row>' . "\n";
        echo '<Cell><Data ss:Type="Number">' . $payment['id'] . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars(formatDate($payment['created_at'])) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($payment['user_name'] ?? 'N/A') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($payment['user_email'] ?? 'N/A') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($payment['subscription_name']) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="Number">' . $payment['amount'] . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars(ucfirst($payment['payment_method'])) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($payment['transaction_reference']) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars(ucfirst($payment['status'])) . '</Data></Cell>' . "\n";
        echo '</Row>' . "\n";
    }
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>' . "\n";
    exit;
}

function exportToPDF($payments, $filename) {
    // Simple HTML to PDF conversion (requires browser print)
    header('Content-Type: text/html');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Payment Export</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .export-info { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .status-completed { color: #28a745; font-weight: bold; }
            .status-pending { color: #ffc107; font-weight: bold; }
            .status-failed { color: #dc3545; font-weight: bold; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
                .page-break { page-break-before: always; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Payment Export Report</h1>
            <p>Generated on ' . date('F j, Y g:i A') . '</p>
        </div>
        
        <div class="export-info">
            <h3>Export Summary</h3>
            <p><strong>Total Records:</strong> ' . count($payments) . '</p>
            <p><strong>Export Date:</strong> ' . date('Y-m-d H:i:s') . '</p>
            <p><strong>Exported By:</strong> ' . ($_SESSION['user_name'] ?? 'Admin') . '</p>
        </div>
        
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Print/Save as PDF</button>
            <button onclick="window.history.back()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-left: 10px;">Back</button>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>User</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
            
    foreach ($payments as $payment) {
        echo '<tr>
                <td>' . $payment['id'] . '</td>
                <td>' . formatDate($payment['created_at']) . '</td>
                <td>' . htmlspecialchars($payment['user_name'] ?? 'N/A') . '<br><small>' . htmlspecialchars($payment['user_email'] ?? 'N/A') . '</small></td>
                <td>' . htmlspecialchars($payment['subscription_name']) . '</td>
                <td>' . formatCurrency($payment['amount']) . '</td>
                <td>' . ucfirst($payment['payment_method']) . '</td>
                <td style="font-family: monospace; font-size: 10px;">' . htmlspecialchars($payment['transaction_reference']) . '</td>
                <td class="status-' . $payment['status'] . '">' . ucfirst($payment['status']) . '</td>
              </tr>';
    }
    
    echo '</tbody>
        </table>
    </body>
    </html>';
    exit;
}

// Get export statistics
$totalPayments = $db->fetch("SELECT COUNT(*) as count FROM payments")['count'] ?? 0;
$totalAmount = $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'")['total'] ?? 0;
$pendingCount = $db->fetch("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")['count'] ?? 0;

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
    
    <style>
        body {
            background-color: #f5f6fa;
            font-family: 'Inter', system-ui, sans-serif;
        }

        .export-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .export-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .export-subtitle {
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
        .stat-icon.amount { background: linear-gradient(135deg, #10b981, #047857); }
        .stat-icon.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }

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

        .export-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }

        .export-card-header {
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .export-card-title {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .export-format-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .format-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .format-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.1);
        }

        .format-card.selected {
            border-color: #3b82f6;
            background: #f0f9ff;
        }

        .format-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .format-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #3b82f6;
        }

        .format-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }

        .format-description {
            font-size: 0.85rem;
            color: #6b7280;
            margin: 0;
        }

        .preview-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .preview-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn-export {
            background: linear-gradient(135deg, #10b981, #047857);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            font-size: 1rem;
        }

        .btn-export:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            color: white;
        }

        .btn-export:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .export-progress {
            display: none;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .export-header {
                padding: 1.5rem 0;
            }

            .export-title {
                font-size: 1.5rem;
            }

            .export-format-grid {
                grid-template-columns: 1fr;
            }

            .format-card {
                padding: 1rem;
            }

            .format-icon {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="export-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="export-title">
                    <i class="bi bi-download me-2"></i>Export Payments
                </h1>
                <p class="export-subtitle">
                    Download payment data in your preferred format
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="payments.php" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1"></i>Back to Payments
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-md-4">
    <!-- Alert Messages -->
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-4">
            <div class="card stat-card">
                <div class="stat-icon total">
                    <i class="bi bi-credit-card-2-front"></i>
                </div>
                <div class="stat-value"><?php echo number_format($totalPayments); ?></div>
                <p class="stat-label">Total Payments Available</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-4">
            <div class="card stat-card">
                <div class="stat-icon amount">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($totalAmount); ?></div>
                <p class="stat-label">Total Completed Revenue</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-4">
            <div class="card stat-card">
                <div class="stat-icon pending">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-value"><?php echo number_format($pendingCount); ?></div>
                <p class="stat-label">Pending Payments</p>
            </div>
        </div>
    </div>

    <!-- Export Form -->
    <div class="card export-card">
        <div class="export-card-header">
            <h5 class="export-card-title">
                <i class="bi bi-gear me-2"></i>Configure Export Settings
            </h5>
        </div>
        <div class="card-body">
            <form method="post" id="exportForm">
                <!-- Filters Section -->
                <div class="filter-section">
                    <h6 class="mb-3">
                        <i class="bi bi-funnel me-2"></i>Filter Data (Optional)
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Payment Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="form-select">
                                <option value="">All Methods</option>
                                <option value="paystack">Paystack</option>
                                <option value="flutterwave">Flutterwave</option>
                                <option value="moniepoint">Moniepoint</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" name="date_from" id="date_from" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" name="date_to" id="date_to" class="form-control">
                        </div>
                        <div class="col-12">
                            <label for="user_search" class="form-label">Search User</label>
                            <input type="text" name="user_search" id="user_search" class="form-control" 
                                   placeholder="Enter user name or email to filter results...">
                        </div>
                    </div>
                </div>

                <!-- Export Format Selection -->
                <div class="mb-4">
                    <h6 class="mb-3">
                        <i class="bi bi-file-earmark me-2"></i>Choose Export Format
                    </h6>
                    <div class="export-format-grid">
                        <label class="format-card" for="csv">
                            <input type="radio" name="export_type" value="csv" id="csv" required>
                            <div class="format-icon">
                                <i class="bi bi-filetype-csv"></i>
                            </div>
                            <div class="format-title">CSV</div>
                            <p class="format-description">
                                Comma-separated values. Perfect for Excel and data analysis.
                            </p>
                        </label>

                        <label class="format-card" for="excel">
                            <input type="radio" name="export_type" value="excel" id="excel" required>
                            <div class="format-icon">
                                <i class="bi bi-file-earmark-excel"></i>
                            </div>
                            <div class="format-title">Excel</div>
                            <p class="format-description">
                                Microsoft Excel format with formatted columns and styling.
                            </p>
                        </label>

                        <label class="format-card" for="pdf">
                            <input type="radio" name="export_type" value="pdf" id="pdf" required>
                            <div class="format-icon">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </div>
                            <div class="format-title">PDF</div>
                            <p class="format-description">
                                Portable document format. Great for reports and presentations.
                            </p>
                        </label>

                        <label class="format-card" for="json">
                            <input type="radio" name="export_type" value="json" id="json" required>
                            <div class="format-icon">
                                <i class="bi bi-filetype-json"></i>
                            </div>
                            <div class="format-title">JSON</div>
                            <p class="format-description">
                                Structured data format. Perfect for API integration and development.
                            </p>
                        </label>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_metadata" name="include_metadata" checked>
                            <label class="form-check-label" for="include_metadata">
                                Include metadata (export info, timestamps, etc.)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_user_details" name="include_user_details" checked>
                            <label class="form-check-label" for="include_user_details">
                                Include detailed user information
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Export Button -->
                <div class="d-flex justify-content-center">
                    <button type="submit" class="btn btn-export" id="exportBtn">
                        <i class="bi bi-download me-2"></i>Generate Export
                    </button>
                </div>

                <!-- Progress Indicator -->
                <div class="export-progress" id="exportProgress">
                    <div class="d-flex align-items-center justify-content-center">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span>Generating export file...</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Section -->
    <div class="preview-section">
        <h6 class="mb-3">
            <i class="bi bi-eye me-2"></i>Export Preview
        </h6>
        <p class="text-muted mb-3">
            This preview shows the structure of your export. The actual export will include all filtered data.
        </p>
        <div class="preview-table">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>User Name</th>
                            <th>User Email</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="text-muted">
                            <td>12345</td>
                            <td>Jan 15, 2024 2:30 PM</td>
                            <td>John Doe</td>
                            <td>john@example.com</td>
                            <td>Premium Plan</td>
                            <td>$29.99</td>
                            <td>Paystack</td>
                            <td>Completed</td>
                        </tr>
                        <tr class="text-muted">
                            <td>12346</td>
                            <td>Jan 15, 2024 3:45 PM</td>
                            <td>Jane Smith</td>
                            <td>jane@example.com</td>
                            <td>Basic Plan</td>
                            <td>$19.99</td>
                            <td>Flutterwave</td>
                            <td>Pending</td>
                        </tr>
                        <tr class="text-muted">
                            <td colspan="8" class="text-center">
                                <em>... and all other payments matching your filters</em>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format card selection
    const formatCards = document.querySelectorAll('.format-card');
    const radioButtons = document.querySelectorAll('input[name="export_type"]');
    
    formatCards.forEach(card => {
        card.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            updateSelectedCard();
        });
    });
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', updateSelectedCard);
    });
    
    function updateSelectedCard() {
        formatCards.forEach(card => {
            card.classList.remove('selected');
        });
        
        const selectedRadio = document.querySelector('input[name="export_type"]:checked');
        if (selectedRadio) {
            selectedRadio.closest('.format-card').classList.add('selected');
        }
    }
    
    // Form submission
    const exportForm = document.getElementById('exportForm');
    const exportBtn = document.getElementById('exportBtn');
    const exportProgress = document.getElementById('exportProgress');
    
    exportForm.addEventListener('submit', function() {
        exportBtn.disabled = true;
        exportProgress.style.display = 'block';
        
        // Re-enable button after 5 seconds (in case of issues)
        setTimeout(() => {
            exportBtn.disabled = false;
            exportProgress.style.display = 'none';
        }, 5000);
    });
    
    // Set default date range (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
    
    document.getElementById('date_to').value = today.toISOString().split('T')[0];
    document.getElementById('date_from').value = thirtyDaysAgo.toISOString().split('T')[0];
    
    // Auto-save form preferences
    const formElements = document.querySelectorAll('#exportForm input, #exportForm select');
    formElements.forEach(element => {
        // Load saved value
        const savedValue = localStorage.getItem('export_' + element.name);
        if (savedValue && element.type !== 'radio') {
            element.value = savedValue;
        } else if (savedValue && element.type === 'radio' && element.value === savedValue) {
            element.checked = true;
        }
        
        // Save on change
        element.addEventListener('change', function() {
            if (this.type === 'radio') {
                if (this.checked) {
                    localStorage.setItem('export_' + this.name, this.value);
                }
            } else {
                localStorage.setItem('export_' + this.name, this.value);
            }
        });
    });
    
    // Update selected card on page load
    updateSelectedCard();
});

// Real-time filter count update
function updateFilterCount() {
    // This would make an AJAX call to get the count of filtered results
    // For now, just show that filtering is working
    console.log('Filters updated - would show live count here');
}

// Add event listeners to all filter inputs
document.querySelectorAll('#exportForm input, #exportForm select').forEach(element => {
    element.addEventListener('change', updateFilterCount);
});
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>
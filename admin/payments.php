<?php
$pageTitle = 'Payment Management';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/payments.php';

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

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_payment']) && is_numeric($_POST['approve_payment'])) {
        $result = approveManualPayment($_POST['approve_payment']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif (isset($_POST['reject_payment']) && is_numeric($_POST['reject_payment']) && !empty($_POST['rejection_reason'])) {
        $result = rejectManualPayment($_POST['reject_payment'], $_POST['rejection_reason']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif (isset($_POST['verify_payment']) && is_numeric($_POST['verify_payment'])) {
        // Add verification logic here
        $paymentId = $_POST['verify_payment'];
        // This would integrate with your payment gateway to verify
        $message = "Payment verification initiated for Payment ID: $paymentId";
        $messageType = 'info';
    }
}

// Build filter conditions
$whereConditions = [];
$params = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $whereConditions[] = "p.status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['payment_method']) && !empty($_GET['payment_method'])) {
    $whereConditions[] = "p.payment_method = ?";
    $params[] = $_GET['payment_method'];
}

if (isset($_GET['user_search']) && !empty($_GET['user_search'])) {
    $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $searchTerm = '%' . $_GET['user_search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $whereConditions[] = "DATE(p.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $whereConditions[] = "DATE(p.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Count total payments
$countQuery = "SELECT COUNT(*) as count FROM payments p 
               LEFT JOIN users u ON p.user_id = u.id 
               LEFT JOIN subscriptions s ON p.subscription_id = s.id 
               $whereClause";
$totalPayments = $db->fetch($countQuery, $params)['count'] ?? 0;
$totalPages = ceil($totalPayments / $limit);

// Get payments
$query = "SELECT p.*, u.name as user_name, u.email as user_email, 
                 COALESCE(s.name, 'Unknown Plan') as subscription_name 
          FROM payments p 
          LEFT JOIN users u ON p.user_id = u.id 
          LEFT JOIN subscriptions s ON p.subscription_id = s.id 
          $whereClause 
          ORDER BY p.created_at DESC 
          LIMIT $limit OFFSET $offset";

$payments = $db->fetchAll($query, $params);

// Get payment statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM payments")['count'] ?? 0,
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")['count'] ?? 0,
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM payments WHERE status = 'completed'")['count'] ?? 0,
    'failed' => $db->fetch("SELECT COUNT(*) as count FROM payments WHERE status = 'failed'")['count'] ?? 0,
    'total_amount' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'")['total'] ?? 0
];

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
    
    <style>
        body {
            background-color: #f5f6fa;
            font-family: 'Inter', system-ui, sans-serif;
        }

        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .payment-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .payment-subtitle {
            opacity: 0.9;
            margin-bottom: 0;
        }

        .stats-row {
            margin-bottom: 2rem;
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
        .stat-icon.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.completed { background: linear-gradient(135deg, #10b981, #047857); }
        .stat-icon.failed { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-icon.revenue { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

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

        .filter-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 2rem;
        }

        .filter-header {
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .filter-title {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
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

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .payment-method-badge {
            padding: 0.25rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #e5e7eb;
            color: #374151;
        }

        .payment-method-badge.paystack {
            background: #dcfce7;
            color: #166534;
        }

        .payment-method-badge.flutterwave {
            background: #fef3c7;
            color: #92400e;
        }

        .payment-method-badge.manual {
            background: #e0e7ff;
            color: #3730a3;
        }

        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .btn-verify {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
            border: none;
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
        }

        .btn-verify:hover {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            color: white;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .user-email {
            color: #6b7280;
            font-size: 0.8rem;
        }

        .amount-display {
            font-weight: 700;
            color: #1f2937;
            font-size: 1rem;
        }

        .reference-code {
            font-family: 'Monaco', 'Menlo', monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #374151;
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

        .verification-section {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .verification-title {
            font-weight: 600;
            color: #0c4a6e;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .verification-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .payment-header {
                padding: 1.5rem 0;
            }

            .payment-title {
                font-size: 1.5rem;
            }

            .custom-table {
                font-size: 0.8rem;
            }

            .custom-table th,
            .custom-table td {
                padding: 0.75rem 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .stat-card {
                margin-bottom: 1rem;
            }

            .filter-card .row {
                margin: 0;
            }

            .filter-card .col-md-3,
            .filter-card .col-md-2 {
                padding: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .table-responsive {
                font-size: 0.75rem;
            }

            .user-info {
                max-width: 120px;
            }

            .reference-code {
                font-size: 0.7rem;
                max-width: 80px;
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
    </style>
</head>
<body>

<!-- Header -->
<div class="payment-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="payment-title">
                    <i class="bi bi-credit-card me-2"></i>Payment Management
                </h1>
                <p class="payment-subtitle">
                    Monitor, verify, and manage all payment transactions
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#bulkActionsModal">
                    <i class="bi bi-tools me-1"></i>Bulk Actions
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-md-4">
    <!-- Alert Messages -->
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Row -->
    <div class="row stats-row g-3">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card stat-card">
                <div class="stat-icon total">
                    <i class="bi bi-credit-card-2-front"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <p class="stat-label">Total Payments</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card stat-card">
                <div class="stat-icon pending">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                <p class="stat-label">Pending</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card stat-card">
                <div class="stat-icon completed">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
                <p class="stat-label">Completed</p>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card stat-card">
                <div class="stat-icon failed">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['failed']); ?></div>
                <p class="stat-label">Failed</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-8 col-sm-12">
            <div class="card stat-card">
                <div class="stat-icon revenue">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_amount']); ?></div>
                <p class="stat-label">Total Revenue (Completed)</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="filter-header">
            <h5 class="filter-title">
                <i class="bi bi-funnel me-2"></i>Filters & Search
            </h5>
        </div>
        <div class="card-body">
            <form method="get" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Payment Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo isset($_GET['status']) && $_GET['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-select">
                            <option value="">All Methods</option>
                            <option value="paystack" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'paystack' ? 'selected' : ''; ?>>Paystack</option>
                            <option value="flutterwave" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'flutterwave' ? 'selected' : ''; ?>>Flutterwave</option>
                            <option value="moniepoint" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'moniepoint' ? 'selected' : ''; ?>>Moniepoint</option>
                            <option value="manual" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'manual' ? 'selected' : ''; ?>>Manual</option>
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
                        <label for="user_search" class="form-label">Search User</label>
                        <input type="text" name="user_search" id="user_search" class="form-control" 
                               placeholder="Name or email..." 
                               value="<?php echo isset($_GET['user_search']) ? htmlspecialchars($_GET['user_search']) : ''; ?>">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Apply Filters
                            </button>
                            <a href="payments.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Reset
                            </a>
                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#verifyAllModal">
                                <i class="bi bi-shield-check me-1"></i>Verify All Pending
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card table-card">
        <div class="table-header">
            <h5 class="table-title">
                Payment Transactions
                <?php if (!empty($payments)): ?>
                    <span class="badge bg-primary ms-2"><?php echo count($payments); ?> of <?php echo number_format($totalPayments); ?></span>
                <?php endif; ?>
            </h5>
            <div class="table-actions">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <i class="bi bi-credit-card-2-front"></i>
                    <h5>No payments found</h5>
                    <p>Try adjusting your search criteria or filters.</p>
                </div>
            <?php else: ?>
                <table class="table custom-table">
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $payment['id']; ?></strong>
                                </td>
                                <td title="<?php echo formatDate($payment['created_at']); ?>">
                                    <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($payment['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name" title="<?php echo htmlspecialchars($payment['user_name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($payment['user_name'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="user-email" title="<?php echo htmlspecialchars($payment['user_email'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($payment['user_email'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </td>
                                <td title="<?php echo htmlspecialchars($payment['subscription_name']); ?>">
                                    <?php echo htmlspecialchars($payment['subscription_name']); ?>
                                </td>
                                <td>
                                    <div class="amount-display">
                                        <?php echo formatCurrency($payment['amount']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="payment-method-badge <?php echo $payment['payment_method']; ?>">
                                        <?php echo ucfirst($payment['payment_method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="reference-code" title="<?php echo htmlspecialchars($payment['transaction_reference']); ?>">
                                        <?php echo htmlspecialchars(substr($payment['transaction_reference'], 0, 12) . '...'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Details Button -->
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailsModal<?php echo $payment['id']; ?>"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Verification Button (for all payment types) -->
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="verify_payment" value="<?php echo $payment['id']; ?>">
                                            <button type="submit" class="btn btn-verify btn-sm" title="Verify Transaction">
                                                <i class="bi bi-shield-check"></i>
                                            </button>
                                        </form>

                                        <!-- Manual Payment Actions -->
                                        <?php if ($payment['status'] === 'pending' && $payment['payment_method'] === 'manual' && !empty($payment['payment_proof'])): ?>
                                            <button type="button" class="btn btn-outline-info btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#proofModal<?php echo $payment['id']; ?>"
                                                    title="View Proof">
                                                <i class="bi bi-file-image"></i>
                                            </button>
                                            
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="approve_payment" value="<?php echo $payment['id']; ?>">
                                                <button type="submit" class="btn btn-outline-success btn-sm" 
                                                        onclick="return confirm('Approve this payment?')"
                                                        title="Approve">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal<?php echo $payment['id']; ?>"
                                                    title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php endif; ?>
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
        <nav aria-label="Payments pagination">
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

<!-- Modals for each payment -->
<?php foreach ($payments as $payment): ?>
    <!-- Payment Details Modal -->
    <div class="modal fade" id="detailsModal<?php echo $payment['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2"></i>Payment Details #<?php echo $payment['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>User:</strong> <?php echo htmlspecialchars($payment['user_name'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Email:</strong> <?php echo htmlspecialchars($payment['user_email'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Amount:</strong> <?php echo formatCurrency($payment['amount']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> 
                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Payment Method:</strong> <?php echo ucfirst($payment['payment_method']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Date:</strong> <?php echo formatDate($payment['created_at']); ?>
                        </div>
                        <div class="col-12">
                            <strong>Transaction Reference:</strong> 
                            <div class="reference-code d-inline-block mt-1">
                                <?php echo htmlspecialchars($payment['transaction_reference']); ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <strong>Subscription Plan:</strong> <?php echo htmlspecialchars($payment['subscription_name']); ?>
                        </div>
                        
                        <!-- Verification Section -->
                        <div class="col-12">
                            <div class="verification-section">
                                <div class="verification-title">
                                    <i class="bi bi-shield-check me-1"></i>Transaction Verification
                                </div>
                                <p class="text-muted small mb-2">
                                    Verify this transaction with the payment provider to ensure authenticity.
                                </p>
                                <div class="verification-actions">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="verify_payment" value="<?php echo $payment['id']; ?>">
                                        <button type="submit" class="btn btn-verify btn-sm">
                                            <i class="bi bi-shield-check me-1"></i>Verify with Provider
                                        </button>
                                    </form>
                                    <?php if ($payment['payment_method'] !== 'manual'): ?>
                                        <button type="button" class="btn btn-outline-info btn-sm" onclick="checkWithProvider('<?php echo $payment['transaction_reference']; ?>', '<?php echo $payment['payment_method']; ?>')">
                                            <i class="bi bi-search me-1"></i>Check Provider Status
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Proof Modal (for manual payments) -->
    <?php if ($payment['payment_method'] === 'manual' && !empty($payment['payment_proof'])): ?>
        <div class="modal fade" id="proofModal<?php echo $payment['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-file-image me-2"></i>Payment Proof
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <?php 
                        $fileExt = pathinfo($payment['payment_proof'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif'])): 
                        ?>
                            <img src="<?php echo SITE_URL . '/' . $payment['payment_proof']; ?>" 
                                 alt="Payment Proof" 
                                 class="img-fluid rounded"
                                 style="max-height: 500px;">
                        <?php elseif (strtolower($fileExt) === 'pdf'): ?>
                            <embed src="<?php echo SITE_URL . '/' . $payment['payment_proof']; ?>" 
                                   type="application/pdf" 
                                   width="100%" 
                                   height="600px"
                                   class="rounded">
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-file-earmark me-2"></i>
                                Unsupported file format. 
                                <a href="<?php echo SITE_URL . '/' . $payment['payment_proof']; ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm ms-2">
                                    <i class="bi bi-download me-1"></i>Download File
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="<?php echo SITE_URL . '/' . $payment['payment_proof']; ?>" 
                           target="_blank" 
                           class="btn btn-primary">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reject Payment Modal -->
    <?php if ($payment['status'] === 'pending' && $payment['payment_method'] === 'manual'): ?>
        <div class="modal fade" id="rejectModal<?php echo $payment['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="bi bi-x-circle me-2"></i>Reject Payment
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post">
                        <div class="modal-body">
                            <input type="hidden" name="reject_payment" value="<?php echo $payment['id']; ?>">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                You are about to reject this payment. Please provide a clear reason.
                            </div>
                            <div class="mb-3">
                                <label for="rejection_reason<?php echo $payment['id']; ?>" class="form-label">Reason for Rejection</label>
                                <textarea name="rejection_reason" 
                                          id="rejection_reason<?php echo $payment['id']; ?>" 
                                          class="form-control" 
                                          rows="4" 
                                          required
                                          placeholder="Please explain why this payment is being rejected..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-lg me-1"></i>Reject Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-tools me-2"></i>Bulk Actions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <button type="button" class="list-group-item list-group-item-action" onclick="verifyAllPending()">
                        <i class="bi bi-shield-check me-2"></i>Verify All Pending Payments
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="exportPayments()">
                        <i class="bi bi-download me-2"></i>Export Payment Data
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="reconcilePayments()">
                        <i class="bi bi-arrow-repeat me-2"></i>Reconcile with Providers
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Verification functions
function checkWithProvider(reference, method) {
    // This would integrate with your payment gateway APIs
    alert(`Checking ${method} for transaction: ${reference}`);
    // Add actual API integration here
}

function verifyAllPending() {
    if (confirm('Verify all pending payments? This may take a while.')) {
        // Add bulk verification logic
        alert('Bulk verification initiated. You will be notified when complete.');
    }
}

function exportPayments() {
    // Export functionality
    window.location.href = 'export_payments.php';
}

function reconcilePayments() {
    if (confirm('Reconcile all payments with providers? This may take several minutes.')) {
        // Add reconciliation logic
        alert('Reconciliation started. You will receive a report when complete.');
    }
}

// Auto-refresh for real-time updates
setInterval(function() {
    // Check for new payments every 30 seconds
    fetch('check_new_payments.php')
        .then(response => response.json())
        .then(data => {
            if (data.new_payments > 0) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-info alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="bi bi-info-circle me-2"></i>
                    ${data.new_payments} new payment(s) received. 
                    <a href="javascript:location.reload()" class="alert-link">Refresh to see updates</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container-fluid').prepend(alertDiv);
            }
        })
        .catch(error => console.log('Auto-refresh error:', error));
}, 30000);

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>
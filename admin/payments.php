<?php
$pageTitle = 'Payment Management';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/payments.php';

requireAdmin();

// Handle payment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_payment']) && is_numeric($_POST['approve_payment'])) {
        $result = approveManualPayment($_POST['approve_payment']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif (isset($_POST['reject_payment']) && is_numeric($_POST['reject_payment']) && !empty($_POST['rejection_reason'])) {
        $result = rejectManualPayment($_POST['reject_payment'], $_POST['rejection_reason']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// Get all payments with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$totalPayments = $db->fetch("SELECT COUNT(*) as count FROM payments")['count'];
$totalPages = ceil($totalPayments / $limit);

// FIXED: MariaDB doesn't support named parameters for LIMIT and OFFSET
$query = "SELECT p.*, u.name as user_name, u.email as user_email, s.name as subscription_name 
         FROM payments p 
         JOIN users u ON p.user_id = u.id 
         JOIN subscriptions s ON p.subscription_id = s.id 
         ORDER BY p.created_at DESC 
         LIMIT $limit OFFSET $offset";

$payments = $db->fetchAll($query, []);  // No need to pass limit/offset as params

// Include header
include '../includes/header.php';
?>

<!-- Rest of the file remains the same -->

<main class="container admin-container">
    <h1>Payment Management</h1>
    
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="admin-actions">
        <div class="filter-options">
            <form method="get" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Payment Status</label>
                        <select name="status" id="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo isset($_GET['status']) && $_GET['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method">
                            <option value="">All Methods</option>
                            <option value="paystack" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'paystack' ? 'selected' : ''; ?>>Paystack</option>
                            <option value="flutterwave" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'flutterwave' ? 'selected' : ''; ?>>Flutterwave</option>
                            <option value="moniepoint" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'moniepoint' ? 'selected' : ''; ?>>Moniepoint</option>
                            <option value="manual" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'manual' ? 'selected' : ''; ?>>Manual</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="payments.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="payments-table-container">
        <table class="data-table payments-table">
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
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="9">No payments found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo $payment['id']; ?></td>
                            <td><?php echo formatDate($payment['created_at']); ?></td>
                            <td>
                                <?php echo $payment['user_name']; ?><br>
                                <small><?php echo $payment['user_email']; ?></small>
                            </td>
                            <td><?php echo $payment['subscription_name']; ?></td>
                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                            <td><?php echo $payment['transaction_reference']; ?></td>
                            <td class="status-<?php echo $payment['status']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </td>
                            <td>
                                <?php if ($payment['status'] === 'pending' && $payment['payment_method'] === 'manual' && !empty($payment['payment_proof'])): ?>
                                    <a href="#" class="btn btn-sm btn-info view-proof" data-toggle="modal" data-target="#proofModal<?php echo $payment['id']; ?>">View Proof</a>
                                    
                                    <form method="post" class="approve-form">
                                        <input type="hidden" name="approve_payment" value="<?php echo $payment['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    
                                    <a href="#" class="btn btn-sm btn-danger reject-btn" data-toggle="modal" data-target="#rejectModal<?php echo $payment['id']; ?>">Reject</a>
                                    
                                    <!-- Proof Modal -->
                                    <div class="modal fade" id="proofModal<?php echo $payment['id']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Payment Proof</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <?php 
                                                    $fileExt = pathinfo($payment['payment_proof'], PATHINFO_EXTENSION);
                                                    if (in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif'])): 
                                                    ?>
                                                        <img src="<?php echo SITE_URL . '/' . $payment['payment_proof']; ?>" alt="Payment Proof" class="img-fluid">
                                                    <?php elseif (strtolower($fileExt) === 'pdf'): ?>
                                                        <embed src="<?php echo SITE_URL . '/' . $payment['payment_proof']; ?>" type="application/pdf" width="100%" height="600px">
                                                    <?php else: ?>
                                                        <p>Unsupported file format. <a href="<?php echo SITE_URL . '/' . $payment['payment_proof']; ?>" target="_blank">Download file</a></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?php echo $payment['id']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reject Payment</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="reject_payment" value="<?php echo $payment['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="rejection_reason">Reason for Rejection</label>
                                                            <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Reject Payment</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . $_GET['payment_method'] : ''; ?>" class="prev">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . $_GET['payment_method'] : ''; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . $_GET['payment_method'] : ''; ?>" class="next">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
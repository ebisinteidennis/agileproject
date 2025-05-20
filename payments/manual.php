<?php
$pageTitle = 'Manual Payment';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/payments.php';

requireLogin();

// Validate payment ID
if (!isset($_GET['payment_id']) || !is_numeric($_GET['payment_id'])) {
    redirect(SITE_URL . '/account/billing.php');
}

$paymentId = (int)$_GET['payment_id'];
$payment = getPaymentById($paymentId);

if (!$payment || $payment['user_id'] != $_SESSION['user_id']) {
    redirect(SITE_URL . '/account/billing.php');
}

$subscription = getSubscriptionById($payment['subscription_id']);
$settings = getSiteSettings();

// Process payment proof upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $result = processManualPayment($paymentId, $_FILES['payment_proof']);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Please upload proof of payment.';
    }
}

// Include header
include '../includes/header.php';
?>

<main class="container">
    <h1>Manual Payment / Bank Transfer</h1>
    
    <div class="manual-payment-instructions">
        <h2>Payment Instructions</h2>
        <div class="instructions-box">
            <?php echo nl2br($settings['manual_payment_instructions'] ?? 'Please contact admin for payment instructions.'); ?>
        </div>
        
        <div class="payment-details">
            <h3>Payment Details</h3>
            <p><strong>Amount:</strong> <?php echo formatCurrency($payment['amount']); ?></p>
            <p><strong>Reference:</strong> <?php echo $payment['transaction_reference']; ?></p>
            <p><strong>Plan:</strong> <?php echo $subscription['name']; ?></p>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($payment['status'] === 'pending' && !isset($success)): ?>
        <div class="upload-proof">
            <h2>Upload Payment Proof</h2>
            <p>After making the payment, please upload a screenshot or photo of your payment receipt.</p>
            
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="payment_proof">Payment Proof (Image or PDF)</label>
                    <input type="file" name="payment_proof" id="payment_proof" required accept="image/jpeg,image/png,image/gif,application/pdf">
                </div>
                
                <button type="submit" class="btn btn-primary">Submit Payment Proof</button>
            </form>
        </div>
    <?php elseif ($payment['status'] === 'pending'): ?>
        <div class="pending-verification">
            <h2>Payment Under Verification</h2>
            <p>Your payment proof has been submitted and is pending verification by our team. You will be notified once your payment is verified.</p>
        </div>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="<?php echo SITE_URL; ?>/account/billing.php">Back to Billing</a>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
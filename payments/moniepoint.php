<?php
$pageTitle = 'Moniepoint Payment';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/payments.php';

requireLogin();

// Validate reference
if (!isset($_GET['reference'])) {
    redirect(SITE_URL . '/account/billing.php');
}

$reference = $_GET['reference'];
$payment = $db->fetch(
    "SELECT p.*, s.name as subscription_name, s.price, u.email as user_email, u.name as user_name
     FROM payments p 
     JOIN subscriptions s ON p.subscription_id = s.id 
     JOIN users u ON p.user_id = u.id 
     WHERE p.transaction_reference = :reference AND p.user_id = :user_id", 
    ['reference' => $reference, 'user_id' => $_SESSION['user_id']]
);

if (!$payment) {
    redirect(SITE_URL . '/account/billing.php');
}

$settings = getSiteSettings();

// Handle callback from Moniepoint
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $result = verifyMoniepointPayment($reference);
        
        if ($result['success']) {
            $success = $result['message'];
            // Redirect to billing page after 3 seconds
            header("refresh:3;url=" . SITE_URL . "/account/billing.php");
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Payment was not successful';
    }
}

// Include header
include '../includes/header.php';
?>

<main class="container">
    <h1>Moniepoint Payment</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php else: ?>
        <div class="payment-details">
            <h2>Payment Details</h2>
            <p><strong>Plan:</strong> <?php echo $payment['subscription_name']; ?></p>
            <p><strong>Amount:</strong> <?php echo formatCurrency($payment['price']); ?></p>
            <p><strong>Reference:</strong> <?php echo $payment['transaction_reference']; ?></p>
        </div>
        
        <div class="moniepoint-payment">
            <h2>Pay with Moniepoint</h2>
            <p>Click the button below to initiate payment via Moniepoint.</p>
            
            <form id="moniepoint-form" method="post" action="https://api.moniepoint.com/v1/payments/initiate">
                <input type="hidden" name="merchant_id" value="<?php echo $settings['moniepoint_merchant_id']; ?>">
                <input type="hidden" name="api_key" value="<?php echo $settings['moniepoint_api_key']; ?>">
                <input type="hidden" name="amount" value="<?php echo $payment['price']; ?>">
                <input type="hidden" name="currency" value="<?php echo $settings['currency']; ?>">
                <input type="hidden" name="reference" value="<?php echo $payment['transaction_reference']; ?>">
                <input type="hidden" name="customer_email" value="<?php echo $payment['user_email']; ?>">
                <input type="hidden" name="customer_name" value="<?php echo $payment['user_name']; ?>">
                <input type="hidden" name="description" value="Payment for <?php echo $payment['subscription_name']; ?> Plan">
                <input type="hidden" name="callback_url" value="<?php echo SITE_URL; ?>/payments/moniepoint.php?reference=<?php echo $payment['transaction_reference']; ?>&status=success">
                <input type="hidden" name="cancel_url" value="<?php echo SITE_URL; ?>/payments/moniepoint.php?reference=<?php echo $payment['transaction_reference']; ?>&status=cancelled">
                
                <button type="submit" class="btn btn-primary">Pay Now</button>
            </form>
            
            <p class="note">Note: This is a placeholder implementation. Actual integration may require Moniepoint's API documentation.</p>
        </div>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="<?php echo SITE_URL; ?>/account/billing.php">Back to Billing</a>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
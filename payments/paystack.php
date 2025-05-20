<?php
$pageTitle = 'Paystack Payment';
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
    "SELECT p.*, s.name as subscription_name, s.price, u.email as user_email 
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

// Handle callback from Paystack
if (isset($_GET['trxref'])) {
    $result = verifyPaystackPayment($_GET['trxref']);
    
    if ($result['success']) {
        $success = $result['message'];
        // Redirect to billing page after 3 seconds
        header("refresh:3;url=" . SITE_URL . "/account/billing.php");
    } else {
        $error = $result['message'];
    }
}

// Include header
include '../includes/header.php';
?>

<main class="container">
    <h1>Paystack Payment</h1>
    
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
        
        <div id="paystack-button-container">
            <button id="pay-button" class="btn btn-primary">Pay with Paystack</button>
        </div>
        
        <script src="https://js.paystack.co/v1/inline.js"></script>
        <script>
            document.getElementById('pay-button').addEventListener('click', function() {
                payWithPaystack();
            });
            
            function payWithPaystack() {
                var handler = PaystackPop.setup({
                    key: '<?php echo $settings['paystack_public_key']; ?>',
                    email: '<?php echo $payment['user_email']; ?>',
                    amount: <?php echo $payment['price'] * 100; ?>, // Paystack expects amount in kobo
                    currency: '<?php echo $settings['currency']; ?>',
                    ref: '<?php echo $payment['transaction_reference']; ?>',
                    callback: function(response) {
                        window.location.href = '<?php echo SITE_URL; ?>/payments/paystack.php?reference=<?php echo $payment['transaction_reference']; ?>&trxref=' + response.reference;
                    },
                    onClose: function() {
                        // User closed the payment window
                    }
                });
                handler.openIframe();
            }
        </script>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="<?php echo SITE_URL; ?>/account/billing.php">Back to Billing</a>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
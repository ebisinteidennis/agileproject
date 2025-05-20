<?php
$pageTitle = 'Flutterwave Payment';
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

// Handle callback from Flutterwave
if (isset($_GET['status']) && isset($_GET['transaction_id'])) {
    if ($_GET['status'] === 'successful') {
        $result = verifyFlutterwavePayment($_GET['transaction_id']);
        
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
    <h1>Flutterwave Payment</h1>
    
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
        
        <div id="flutterwave-button-container">
            <button id="pay-button" class="btn btn-primary">Pay with Flutterwave</button>
        </div>
        
        <script src="https://checkout.flutterwave.com/v3.js"></script>
        <script>
            document.getElementById('pay-button').addEventListener('click', function() {
                makePayment();
            });
            
            function makePayment() {
                FlutterwaveCheckout({
                    public_key: "<?php echo $settings['flutterwave_public_key']; ?>",
                    tx_ref: "<?php echo $payment['transaction_reference']; ?>",
                    amount: <?php echo $payment['price']; ?>,
                    currency: "<?php echo $settings['currency']; ?>",
                    customer: {
                        email: "<?php echo $payment['user_email']; ?>",
                        name: "<?php echo $payment['user_name']; ?>"
                    },
                    callback: function(data) {
                        window.location.href = '<?php echo SITE_URL; ?>/payments/flutterwave.php?reference=<?php echo $payment['transaction_reference']; ?>&status=' + data.status + '&transaction_id=' + data.transaction_id;
                    },
                    onclose: function() {
                        // User closed the payment modal
                    },
                    customizations: {
                        title: "<?php echo $settings['site_name']; ?>",
                        description: "Payment for <?php echo $payment['subscription_name']; ?> Plan",
                        logo: "<?php echo SITE_URL . '/' . $settings['site_logo']; ?>"
                    }
                });
            }
        </script>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="<?php echo SITE_URL; ?>/account/billing.php">Back to Billing</a>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
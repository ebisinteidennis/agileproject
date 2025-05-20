<?php
$pageTitle = 'Make Payment';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/payments.php';

requireLogin();

// Validate plan ID
if (!isset($_GET['plan']) || !is_numeric($_GET['plan'])) {
    redirect(SITE_URL . '/account/billing.php');
}

$planId = (int)$_GET['plan'];
$subscription = getSubscriptionById($planId);

if (!$subscription) {
    redirect(SITE_URL . '/account/billing.php');
}

$userId = $_SESSION['user_id'];
$user = getUserById($userId);
$settings = getSiteSettings();
$enabledPaymentMethods = getPaymentMethodsEnabled();

// Process payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $paymentMethod = $_POST['payment_method'];
    
    if (!in_array($paymentMethod, $enabledPaymentMethods)) {
        $error = 'Invalid payment method selected.';
    } else {
        // Create payment record
        $payment = createPayment($userId, $planId, $paymentMethod);
        
        if (!$payment['success']) {
            $error = $payment['message'];
        } else {
            // Handle different payment methods
            switch ($paymentMethod) {
                case 'paystack':
                    // Redirect to Paystack payment page
                    redirect(SITE_URL . '/payments/paystack.php?reference=' . $payment['reference']);
                    break;
                    
                case 'flutterwave':
                    // Redirect to Flutterwave payment page
                    redirect(SITE_URL . '/payments/flutterwave.php?reference=' . $payment['reference']);
                    break;
                    
                case 'moniepoint':
                    // Redirect to Moniepoint payment page
                    redirect(SITE_URL . '/payments/moniepoint.php?reference=' . $payment['reference']);
                    break;
                    
                case 'manual':
                    // Redirect to manual payment page
                    redirect(SITE_URL . '/payments/manual.php?payment_id=' . $payment['payment_id']);
                    break;
                    
                default:
                    $error = 'Invalid payment method.';
                    break;
            }
        }
    }
}


// Include header
include '../includes/header.php';
?>

<main class="container">
    <h1>Make Payment</h1>
    
    <div class="subscription-details">
        <h2>Subscription Details</h2>
        <div class="plan-summary">
            <p><strong>Plan:</strong> <?php echo $subscription['name']; ?></p>
            <p><strong>Duration:</strong> <?php echo $subscription['duration']; ?> days</p>
            <p><strong>Message Limit:</strong> <?php echo number_format($subscription['message_limit']); ?> messages</p>
            <p><strong>Amount:</strong> <?php echo formatCurrency($subscription['price']); ?></p>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <div class="payment-methods">
        <h2>Select Payment Method</h2>
        
        <form method="post" action="">
            <div class="payment-options">
                <?php if (in_array('paystack', $enabledPaymentMethods)): ?>
                    <div class="payment-option">
                        <input type="radio" name="payment_method" id="paystack" value="paystack">
                        <label for="paystack">
                            <img src="<?php echo SITE_URL; ?>/assets/images/paystack.png" alt="Paystack">
                            <span>Pay with Paystack</span>
                        </label>
                    </div>
                <?php endif; ?>
                
                <?php if (in_array('flutterwave', $enabledPaymentMethods)): ?>
                    <div class="payment-option">
                        <input type="radio" name="payment_method" id="flutterwave" value="flutterwave">
                        <label for="flutterwave">
                            <img src="<?php echo SITE_URL; ?>/assets/images/flutterwave.png" alt="Flutterwave">
                            <span>Pay with Flutterwave</span>
                        </label>
                    </div>
                <?php endif; ?>
                
                <?php if (in_array('moniepoint', $enabledPaymentMethods)): ?>
                    <div class="payment-option">
                        <input type="radio" name="payment_method" id="moniepoint" value="moniepoint">
                        <label for="moniepoint">
                            <img src="<?php echo SITE_URL; ?>/assets/images/moniepoint.png" alt="Moniepoint">
                            <span>Pay with Moniepoint</span>
                        </label>
                    </div>
                <?php endif; ?>
                
                <?php if (in_array('manual', $enabledPaymentMethods)): ?>
                    <div class="payment-option">
                        <input type="radio" name="payment_method" id="manual" value="manual">
                        <label for="manual">
                            <img src="<?php echo SITE_URL; ?>/assets/images/bank-transfer.png" alt="Manual Payment">
                            <span>Manual Payment/Bank Transfer</span>
                        </label>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn btn-primary">Proceed to Payment</button>
        </form>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
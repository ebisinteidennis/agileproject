<?php
$pageTitle = 'Billing & Subscriptions';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/payments.php';

requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);
$subscriptions = getSubscriptionPlans();
$payments = getUserPayments($userId);
$settings = getSiteSettings();
$enabledPaymentMethods = getPaymentMethodsEnabled();

// Properly handle subscription data with null checks
$subscription = null;
$isSubscriptionActive = false;
$subscriptionName = 'Free Plan';
$messageLimit = 0;
$subscriptionExpiry = 'N/A';

if (isset($user['subscription_id']) && !empty($user['subscription_id'])) {
    $subscription = getSubscriptionById($user['subscription_id']);
    $isSubscriptionActive = isSubscriptionActive($user);
    
    if ($subscription) {
        $subscriptionName = $subscription['name'] ?? 'Unknown Plan';
        $messageLimit = $subscription['message_limit'] ?? 0;
    }
    
    if (isset($user['subscription_expiry']) && !empty($user['subscription_expiry'])) {
        $subscriptionExpiry = formatDate($user['subscription_expiry']);
    }
}

// Get message count safely
$messageCount = 0;
try {
    $messageCount = getMessageCount($userId);
} catch (Exception $e) {
    // Handle silently
}

// Include header
include '../includes/header.php';
?>

<main class="container billing-container">
    <div class="page-header">
        <h1>Billing & Subscriptions</h1>
    </div>
    
    <section class="subscription-status">
        <h2>Current Subscription</h2>
        <?php if ($isSubscriptionActive): ?>
            <div class="subscription-card active">
                <div class="subscription-header">
                    <h3><?php echo htmlspecialchars($subscriptionName); ?></h3>
                    <div class="status-badge active">Active</div>
                </div>
                <div class="subscription-details">
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <span class="status-dot active"></span> Active
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Expires:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($subscriptionExpiry); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Message Limit:</div>
                        <div class="detail-value"><?php echo number_format($messageLimit); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Messages Used:</div>
                        <div class="detail-value"><?php echo number_format($messageCount); ?></div>
                    </div>
                    
                    <div class="usage-meter">
                        <?php 
                        $usagePercent = $messageLimit > 0 ? min(100, ($messageCount / $messageLimit) * 100) : 0;
                        ?>
                        <div class="usage-label">
                            <span>Usage</span>
                            <span><?php echo round($usagePercent); ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $usagePercent; ?>%;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="subscription-actions">
                    <a href="cancel-subscription.php" class="btn btn-outline">Cancel Subscription</a>
                    <a href="#available-plans" class="btn btn-secondary">Change Plan</a>
                </div>
            </div>
        <?php else: ?>
            <div class="subscription-card inactive">
                <div class="subscription-header">
                    <h3>No Active Subscription</h3>
                    <div class="status-badge inactive">Inactive</div>
                </div>
                <div class="empty-subscription">
                    <div class="empty-icon">🔔</div>
                    <p>You don't have an active subscription. Choose a plan below to get started with premium features.</p>
                </div>
                <div class="subscription-actions">
                    <a href="#available-plans" class="btn btn-primary">Choose a Plan</a>
                </div>
            </div>
        <?php endif; ?>
    </section>
    
    <section id="available-plans" class="subscription-plans">
        <h2>Available Plans</h2>
        <div class="plans-grid">
            <?php if (empty($subscriptions)): ?>
                <div class="empty-state">
                    <p>No subscription plans are currently available. Please check back later.</p>
                </div>
            <?php else: ?>
                <?php foreach($subscriptions as $plan): ?>
                    <div class="plan-card <?php echo isset($user['subscription_id']) && $user['subscription_id'] == $plan['id'] ? 'current' : ''; ?>">
                        <?php if (isset($user['subscription_id']) && $user['subscription_id'] == $plan['id']): ?>
                            <div class="current-plan-badge">Current Plan</div>
                        <?php endif; ?>
                        
                        <div class="plan-header">
                            <h3><?php echo htmlspecialchars($plan['name'] ?? 'Unnamed Plan'); ?></h3>
                            <div class="plan-price">
                                <span class="currency"><?php echo htmlspecialchars($settings['currency_symbol'] ?? 'N'); ?></span>
                                <span class="amount"><?php echo number_format(($plan['price'] ?? 0), 2); ?></span>
                            </div>
                            <div class="plan-duration">For <?php echo htmlspecialchars($plan['duration'] ?? 30); ?> days</div>
                        </div>
                        
                        <div class="plan-features">
                            <div class="feature-item highlight">
                                <i class="fa fa-check-circle"></i>
                                <span><?php echo number_format($plan['message_limit'] ?? 0); ?> messages</span>
                            </div>
                            
                            <?php if (!empty($plan['features'])): ?>
                                <?php 
                                $features = explode("\n", $plan['features']);
                                foreach($features as $feature): 
                                    if (empty(trim($feature))) continue;
                                ?>
                                    <div class="feature-item">
                                        <i class="fa fa-check"></i>
                                        <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="plan-action">
                            <?php if (isset($user['subscription_id']) && $user['subscription_id'] == $plan['id']): ?>
                                <button class="btn btn-outline" disabled>Current Plan</button>
                            <?php else: ?>
                                <a href="payment.php?plan=<?php echo htmlspecialchars($plan['id'] ?? ''); ?>" class="btn btn-primary">
                                    Subscribe Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    
    <section class="payment-methods">
        <h2>Payment Methods</h2>
        <div class="payment-methods-container">
            <?php if (empty($enabledPaymentMethods)): ?>
                <div class="empty-state">
                    <p>No payment methods are currently available. Please contact support.</p>
                </div>
            <?php else: ?>
                <div class="payment-methods-list">
                    <?php foreach($enabledPaymentMethods as $method): ?>
                        <div class="payment-method-item">
                            <?php if ($method === 'paypal'): ?>
                                <div class="method-icon paypal"></div>
                                <div class="method-name">PayPal</div>
                            <?php elseif ($method === 'stripe'): ?>
                                <div class="method-icon stripe"></div>
                                <div class="method-name">Credit Card</div>
                            <?php elseif ($method === 'bank'): ?>
                                <div class="method-icon bank"></div>
                                <div class="method-name">Bank Transfer</div>
                            <?php else: ?>
                                <div class="method-icon"></div>
                                <div class="method-name"><?php echo htmlspecialchars(ucfirst($method)); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <section class="payment-history">
        <div class="section-header">
            <h2>Payment History</h2>
            <?php if (!empty($payments)): ?>
                <a href="download-invoices.php" class="btn btn-sm btn-outline">
                    <i class="fa fa-download"></i> Download All Invoices
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-icon">📃</div>
                <p>You have no payment history yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(formatDate($payment['created_at'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($payment['subscription_name'] ?? ''); ?></td>
                                <td>
                                    <?php 
                                    $amount = isset($payment['amount']) ? formatCurrency($payment['amount']) : '0.00';
                                    echo htmlspecialchars($amount); 
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $method = isset($payment['payment_method']) ? ucfirst($payment['payment_method']) : 'Unknown';
                                    echo htmlspecialchars($method); 
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars(strtolower($payment['status'] ?? 'pending')); ?>">
                                        <?php 
                                        $status = isset($payment['status']) ? ucfirst($payment['status']) : 'Unknown';
                                        echo htmlspecialchars($status); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="transaction-reference" title="<?php echo htmlspecialchars($payment['transaction_reference'] ?? ''); ?>">
                                        <?php 
                                        $ref = isset($payment['transaction_reference']) ? $payment['transaction_reference'] : '';
                                        echo htmlspecialchars(substr($ref, 0, 10) . (strlen($ref) > 10 ? '...' : '')); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <?php if (isset($payment['invoice_id'])): ?>
                                            <a href="download-invoice.php?id=<?php echo htmlspecialchars($payment['invoice_id']); ?>" class="btn btn-sm btn-icon" title="Download Invoice">
                                                <i class="fa fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="payment-details.php?id=<?php echo htmlspecialchars($payment['id'] ?? ''); ?>" class="btn btn-sm btn-icon" title="View Details">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include '../includes/footer.php'; ?>
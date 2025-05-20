<?php
$pageTitle = 'User Details';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/payments.php';

requireAdmin();

// Get user ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = 'Invalid user ID.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/admin/users.php');
}

$userId = (int)$_GET['id'];
$user = getUserById($userId);

if (!$user) {
    $_SESSION['message'] = 'User not found.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/admin/users.php');
}

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_subscription':
                $subscriptionId = isset($_POST['subscription_id']) ? (int)$_POST['subscription_id'] : null;
                $status = isset($_POST['subscription_status']) ? $_POST['subscription_status'] : null;
                $expiry = isset($_POST['subscription_expiry']) ? $_POST['subscription_expiry'] : null;
                
                if ($subscriptionId && $status && $expiry) {
                    $db->update(
                        'users',
                        [
                            'subscription_id' => $subscriptionId,
                            'subscription_status' => $status,
                            'subscription_expiry' => $expiry
                        ],
                        'id = :id',
                        ['id' => $userId]
                    );
                    
                    $_SESSION['message'] = 'Subscription updated successfully.';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Please fill in all subscription fields.';
                    $_SESSION['message_type'] = 'error';
                }
                break;
            
            case 'reset_password':
                $newPassword = generateRandomPassword(12);
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $db->update(
                    'users',
                    ['password' => $hashedPassword],
                    'id = :id',
                    ['id' => $userId]
                );
                
                $_SESSION['message'] = "Password reset successfully. New password: $newPassword";
                $_SESSION['message_type'] = 'success';
                break;
                
            case 'regenerate_api_key':
                $newApiKey = generateApiKey();
                
                $db->update(
                    'users',
                    ['api_key' => $newApiKey],
                    'id = :id',
                    ['id' => $userId]
                );
                
                $_SESSION['message'] = 'API key regenerated successfully.';
                $_SESSION['message_type'] = 'success';
                break;
                
            case 'regenerate_widget_id':
                $newWidgetId = generateWidgetId();
                
                $db->update(
                    'users',
                    ['widget_id' => $newWidgetId],
                    'id = :id',
                    ['id' => $userId]
                );
                
                $_SESSION['message'] = 'Widget ID regenerated successfully.';
                $_SESSION['message_type'] = 'success';
                break;
                
            case 'delete_user':
                $confirm = isset($_POST['confirm_delete']) ? $_POST['confirm_delete'] : '';
                
                if ($confirm === 'DELETE') {
                    $db->delete('users', 'id = :id', ['id' => $userId]);
                    
                    $_SESSION['message'] = 'User deleted successfully.';
                    $_SESSION['message_type'] = 'success';
                    redirect(SITE_URL . '/admin/users.php');
                } else {
                    $_SESSION['message'] = 'Confirmation text does not match. User not deleted.';
                    $_SESSION['message_type'] = 'error';
                }
                break;
        }
        
        // Refresh user data after action
        $user = getUserById($userId);
    }
}

// Get user's subscription info
$subscription = null;
if ($user['subscription_id']) {
    $subscription = getSubscriptionById($user['subscription_id']);
}

// Get user's payment history
$payments = $db->fetchAll(
    "SELECT p.*, s.name as subscription_name 
     FROM payments p 
     JOIN subscriptions s ON p.subscription_id = s.id 
     WHERE p.user_id = :user_id 
     ORDER BY p.created_at DESC 
     LIMIT 10", 
    ['user_id' => $userId]
);

// Get user's message stats
$totalMessages = $db->fetch(
    "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id", 
    ['user_id' => $userId]
)['count'];

$totalVisitorMessages = $db->fetch(
    "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND sender_type = 'visitor'", 
    ['user_id' => $userId]
)['count'];

$totalAgentMessages = $db->fetch(
    "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND sender_type = 'agent'", 
    ['user_id' => $userId]
)['count'];

$unreadMessages = $db->fetch(
    "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND sender_type = 'visitor' AND `read` = 0", 
    ['user_id' => $userId]
)['count'];

// Get user's visitors
$visitors = $db->fetch(
    "SELECT COUNT(*) as count FROM visitors WHERE user_id = :user_id", 
    ['user_id' => $userId]
)['count'];

// Get all available subscription plans
$subscriptionPlans = getSubscriptionPlans();

// Include header
include '../includes/header.php';

// Function to generate random password
function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}
?>

<main class="container admin-container">
    <div class="admin-header">
        <h1>User Details</h1>
        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-secondary">Back to Users</a>
    </div>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] === 'success' ? 'success' : 'danger'; ?>">
            <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="user-details-container">
        <div class="user-details-card">
            <div class="user-info-header">
                <h2>User Information</h2>
                <span class="user-id">ID: <?php echo $user['id']; ?></span>
            </div>
            
            <div class="user-info-content">
                <div class="info-group">
                    <label>Name:</label>
                    <span><?php echo $user['name']; ?></span>
                </div>
                
                <div class="info-group">
                    <label>Email:</label>
                    <span><?php echo $user['email']; ?></span>
                </div>
                
                <div class="info-group">
                    <label>Registered:</label>
                    <span><?php echo formatDate($user['created_at']); ?></span>
                </div>
                
                <div class="info-group">
                    <label>API Key:</label>
                    <div class="code-container">
                        <code class="api-key"><?php echo $user['api_key']; ?></code>
                        <form method="post" class="inline-form" onsubmit="return confirm('Are you sure you want to regenerate the API key? This will invalidate the current key.');">
                            <input type="hidden" name="action" value="regenerate_api_key">
                            <button type="submit" class="btn btn-sm btn-warning">Regenerate</button>
                        </form>
                    </div>
                </div>
                
                <div class="info-group">
                    <label>Widget ID:</label>
                    <div class="code-container">
                        <code class="widget-id"><?php echo $user['widget_id']; ?></code>
                        <form method="post" class="inline-form" onsubmit="return confirm('Are you sure you want to regenerate the Widget ID? This will break any existing widget integrations.');">
                            <input type="hidden" name="action" value="regenerate_widget_id">
                            <button type="submit" class="btn btn-sm btn-warning">Regenerate</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="user-details-card">
            <h2>Subscription Information</h2>
            
            <div class="subscription-status-container">
                <h3>Current Status</h3>
                
                <?php if (isSubscriptionActive($user)): ?>
                    <div class="subscription-status active">
                        <span class="status-indicator"></span>
                        <div class="status-details">
                            <p class="status-text">Active<?php echo $subscription ? ' - ' . $subscription['name'] : ''; ?></p>
                            <p class="status-expiry">Expires on <?php echo formatDate($user['subscription_expiry']); ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="subscription-status inactive">
                        <span class="status-indicator"></span>
                        <div class="status-details">
                            <p class="status-text">Inactive</p>
                            <?php if (!empty($user['subscription_expiry'])): ?>
                                <p class="status-expiry">Expired on <?php echo formatDate($user['subscription_expiry']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="subscription-usage">
                    <div class="usage-stat">
                        <span class="stat-label">Messages Used:</span>
                        <span class="stat-value"><?php echo number_format($totalMessages); ?></span>
                    </div>
                    
                    <?php if ($subscription): ?>
                        <div class="usage-stat">
                            <span class="stat-label">Message Limit:</span>
                            <span class="stat-value"><?php echo number_format($subscription['message_limit']); ?></span>
                        </div>
                        
                        <div class="usage-progress">
                            <?php $percentage = min(100, ($totalMessages / $subscription['message_limit']) * 100); ?>
                            <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                            <span class="progress-text"><?php echo number_format($percentage, 1); ?>% used</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="update-subscription">
                <h3>Update Subscription</h3>
                
                <form method="post" class="subscription-form">
                    <input type="hidden" name="action" value="update_subscription">
                    
                    <div class="form-group">
                        <label for="subscription_id">Subscription Plan</label>
                        <select id="subscription_id" name="subscription_id" class="form-control">
                            <option value="">Select a plan</option>
                            <?php foreach ($subscriptionPlans as $plan): ?>
                                <option value="<?php echo $plan['id']; ?>" <?php echo $user['subscription_id'] == $plan['id'] ? 'selected' : ''; ?>>
                                    <?php echo $plan['name']; ?> - <?php echo formatCurrency($plan['price']); ?> / <?php echo $plan['duration']; ?> days
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subscription_status">Status</label>
                        <select id="subscription_status" name="subscription_status" class="form-control">
                            <option value="active" <?php echo $user['subscription_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $user['subscription_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="expired" <?php echo $user['subscription_status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subscription_expiry">Expiry Date</label>
                        <input type="date" id="subscription_expiry" name="subscription_expiry" class="form-control" value="<?php echo $user['subscription_expiry'] ? date('Y-m-d', strtotime($user['subscription_expiry'])) : date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Subscription</button>
                </form>
            </div>
        </div>
        
        <div class="user-details-card">
            <h2>Usage Statistics</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">💬</div>
                    <div class="stat-content">
                        <div class="stat-title">Total Messages</div>
                        <div class="stat-value"><?php echo number_format($totalMessages); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📥</div>
                    <div class="stat-content">
                        <div class="stat-title">Received Messages</div>
                        <div class="stat-value"><?php echo number_format($totalVisitorMessages); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📤</div>
                    <div class="stat-content">
                        <div class="stat-title">Sent Messages</div>
                        <div class="stat-value"><?php echo number_format($totalAgentMessages); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👤</div>
                    <div class="stat-content">
                        <div class="stat-title">Visitors</div>
                        <div class="stat-value"><?php echo number_format($visitors); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="action-links">
                <a href="<?php echo SITE_URL; ?>/admin/messages.php?user_id=<?php echo $userId; ?>" class="btn btn-secondary">View User's Messages</a>
            </div>
        </div>
        
        <div class="user-details-card">
            <h2>Payment History</h2>
            
            <?php if (empty($payments)): ?>
                <p class="empty-state">No payment history found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo formatDate($payment['created_at']); ?></td>
                                <td><?php echo $payment['subscription_name']; ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td class="status-<?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </td>
                                <td><?php echo $payment['transaction_reference']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="action-links">
                    <a href="<?php echo SITE_URL; ?>/admin/payments.php?user_id=<?php echo $userId; ?>" class="btn btn-secondary">View All Payments</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="user-details-card">
            <h2>Account Actions</h2>
            
            <div class="action-buttons">
                <form method="post" class="action-form" onsubmit="return confirm('Are you sure you want to reset this user\'s password? A new random password will be generated.');">
                    <input type="hidden" name="action" value="reset_password">
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </form>
                
                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteUserModal">Delete User</button>
            </div>
            
            <!-- Delete User Modal -->
            <div class="modal fade" id="deleteUserModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete User</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="delete_user">
                                
                                <p><strong>Warning:</strong> This action cannot be undone. All user data, including messages, visitors, and payment history, will be permanently deleted.</p>
                                
                                <div class="form-group">
                                    <label for="confirm_delete">To confirm, type "DELETE" below:</label>
                                    <input type="text" id="confirm_delete" name="confirm_delete" class="form-control" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.user-details-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
}

.user-details-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.user-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.user-id {
    font-size: 0.875rem;
    color: #777;
    background-color: var(--light-bg);
    padding: 3px 8px;
    border-radius: 4px;
}

.info-group {
    margin-bottom: 15px;
}

.info-group label {
    font-weight: 600;
    display: inline-block;
    width: 120px;
    margin-bottom: 0;
}

.code-container {
    position: relative;
    margin: 10px 0;
    padding: 10px;
    background-color: var(--light-bg);
    border-radius: 4px;
}

.code-container code {
    display: block;
    font-family: monospace;
    word-break: break-all;
    padding-right: 100px;
}

.inline-form {
    position: absolute;
    top: 5px;
    right: 5px;
}

.subscription-status-container {
    margin-bottom: 25px;
}

.subscription-status {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.subscription-status.active {
    background-color: rgba(46, 204, 113, 0.1);
}

.subscription-status.inactive {
    background-color: rgba(231, 76, 60, 0.1);
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 15px;
}

.active .status-indicator {
    background-color: var(--success-color);
}

.inactive .status-indicator {
    background-color: var(--error-color);
}

.status-details {
    flex: 1;
}

.status-text {
    font-weight: 600;
    margin-bottom: 5px;
}

.status-expiry {
    font-size: 0.875rem;
    color: #666;
}

.subscription-usage {
    margin-top: 20px;
}

.usage-stat {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.usage-progress {
    position: relative;
    height: 10px;
    background-color: #eee;
    border-radius: 5px;
    margin: 10px 0 20px;
    overflow: hidden;
}

.progress-bar {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background-color: var(--primary-color);
}

.progress-text {
    position: absolute;
    top: 15px;
    right: 0;
    font-size: 0.75rem;
    color: #666;
}

.update-subscription {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    display: flex;
    align-items: center;
    background-color: var(--light-bg);
    padding: 15px;
    border-radius: 8px;
}

.stat-icon {
    font-size: 2rem;
    margin-right: 15px;
    color: var(--primary-color);
}

.stat-title {
    font-size: 0.875rem;
    color: #666;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 600;
}

.action-links {
    margin-top: 20px;
    text-align: center;
}

.action-buttons {
    display: flex;
    gap: 15px;
}

.action-form {
    display: inline-block;
}

@media (min-width: 992px) {
    .user-details-container {
        grid-template-columns: 1fr 1fr;
    }
    
    .user-details-container > div:nth-child(1),
    .user-details-container > div:nth-child(5) {
        grid-column: span 2;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
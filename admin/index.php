<?php
$pageTitle = 'Admin Dashboard';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Get stats
$totalUsers = $db->fetch("SELECT COUNT(*) as count FROM users")['count'];
$activeSubscriptions = $db->fetch("SELECT COUNT(*) as count FROM users WHERE subscription_status = 'active' AND subscription_expiry > NOW()")['count'];
$totalMessages = $db->fetch("SELECT COUNT(*) as count FROM messages")['count'];
$totalPayments = $db->fetch("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'] ?? 0;

// Recent users
$recentUsers = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");

// Recent payments
$recentPayments = $db->fetchAll(
    "SELECT p.*, u.name as user_name, u.email as user_email, s.name as subscription_name 
     FROM payments p 
     JOIN users u ON p.user_id = u.id 
     JOIN subscriptions s ON p.subscription_id = s.id 
     ORDER BY p.created_at DESC 
     LIMIT 5"
);

// Include header
include '../includes/header.php';
?>

<main class="container admin-container">
    <h1>Admin Dashboard</h1>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-content">
                <div class="stat-title">Total Users</div>
                <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🔔</div>
            <div class="stat-content">
                <div class="stat-title">Active Subscriptions</div>
                <div class="stat-value"><?php echo number_format($activeSubscriptions); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">💬</div>
            <div class="stat-content">
                <div class="stat-title">Total Messages</div>
                <div class="stat-value"><?php echo number_format($totalMessages); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <div class="stat-title">Total Revenue</div>
                <div class="stat-value"><?php echo formatCurrency($totalPayments); ?></div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-row">
        <div class="dashboard-col">
            <div class="dashboard-card">
                <h2>Recent Users</h2>
                <?php if (empty($recentUsers)): ?>
                    <p class="empty-state">No users found.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subscription</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo $user['name']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td>
                                        <?php if (isSubscriptionActive($user)): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                    <td>
                                        <a href="user-details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="users.php" class="view-all">View All Users</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-col">
            <div class="dashboard-card">
                <h2>Recent Payments</h2>
                <?php if (empty($recentPayments)): ?>
                    <p class="empty-state">No payments found.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['user_name']; ?></td>
                                    <td><?php echo $payment['subscription_name']; ?></td>
                                    <td><?php echo formatCurrency($payment['amount']); ?></td>
                                    <td class="status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </td>
                                    <td><?php echo formatDate($payment['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="payments.php" class="view-all">View All Payments</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="admin-actions">
        <h2>Quick Actions</h2>
        <div class="action-buttons">
            <a href="users.php" class="action-button">
                <div class="action-icon">👤</div>
                <div class="action-text">Manage Users</div>
            </a>
            <a href="subscriptions.php" class="action-button">
                <div class="action-icon">💼</div>
                <div class="action-text">Manage Subscriptions</div>
            </a>
            <a href="payments.php" class="action-button">
                <div class="action-icon">💰</div>
                <div class="action-text">Manage Payments</div>
            </a>
            <a href="settings.php" class="action-button">
                <div class="action-icon">⚙️</div>
                <div class="action-text">Site Settings</div>
            </a>
            <a href="messages.php" class="action-button">
                <div class="action-icon">💬</div>
                <div class="action-text">View Messages</div>
            </a>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
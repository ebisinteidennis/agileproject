<?php
$pageTitle = 'Manage Subscriptions';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subscription']) || isset($_POST['edit_subscription'])) {
        $name = sanitizeInput($_POST['name']);
        $price = (float)$_POST['price'];
        $duration = (int)$_POST['duration'];
        $message_limit = (int)$_POST['message_limit'];
        $features = $_POST['features'];
        
        // Validate inputs
        if (empty($name) || $price <= 0 || $duration <= 0 || $message_limit <= 0) {
            $error = 'Please fill in all fields with valid values.';
        } else {
            $subscriptionData = [
                'name' => $name,
                'price' => $price,
                'duration' => $duration,
                'message_limit' => $message_limit,
                'features' => $features
            ];
            
            if (isset($_POST['add_subscription'])) {
                // Add new subscription
                $db->insert('subscriptions', $subscriptionData);
                $success = 'Subscription plan added successfully.';
            } elseif (isset($_POST['edit_subscription'])) {
                // Update existing subscription
                $id = (int)$_POST['id'];
                $db->update('subscriptions', $subscriptionData, 'id = :id', ['id' => $id]);
                $success = 'Subscription plan updated successfully.';
            }
        }
    } elseif (isset($_POST['delete_subscription'])) {
        $id = (int)$_POST['id'];
        
        // Check if any users are using this subscription
        $usersCount = $db->fetch(
            "SELECT COUNT(*) as count FROM users WHERE subscription_id = :id", 
            ['id' => $id]
        )['count'];
        
        if ($usersCount > 0) {
            $error = 'Cannot delete this subscription plan as it is being used by ' . $usersCount . ' user(s).';
        } else {
            $db->delete('subscriptions', 'id = :id', ['id' => $id]);
            $success = 'Subscription plan deleted successfully.';
        }
    }
}

// Get subscription for editing
$editSubscription = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editSubscription = getSubscriptionById($_GET['edit']);
    if (!$editSubscription) {
        redirect(SITE_URL . '/admin/subscriptions.php');
    }
}

// Get all subscriptions
$subscriptions = getSubscriptionPlans();

// Include header
include '../includes/header.php';
?>

<main class="container admin-container">
    <h1>Manage Subscription Plans</h1>
    
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
    
    <div class="admin-content">
        <div class="subscription-form-container">
            <h2><?php echo $editSubscription ? 'Edit Subscription Plan' : 'Add New Subscription Plan'; ?></h2>
            
            <form method="post" class="subscription-form">
                <?php if ($editSubscription): ?>
                    <input type="hidden" name="id" value="<?php echo $editSubscription['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Plan Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo $editSubscription ? $editSubscription['name'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="price">Price (<?php echo getSiteSettings()['currency']; ?>)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" class="form-control" value="<?php echo $editSubscription ? $editSubscription['price'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="duration">Duration (days)</label>
                    <input type="number" id="duration" name="duration" min="1" class="form-control" value="<?php echo $editSubscription ? $editSubscription['duration'] : '30'; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="message_limit">Message Limit</label>
                    <input type="number" id="message_limit" name="message_limit" min="1" class="form-control" value="<?php echo $editSubscription ? $editSubscription['message_limit'] : '1000'; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="features">Features (one per line)</label>
                    <textarea id="features" name="features" class="form-control" rows="5"><?php echo $editSubscription ? $editSubscription['features'] : ''; ?></textarea>
                    <small class="form-text text-muted">Enter each feature on a new line.</small>
                </div>
                
                <div class="form-actions">
                    <?php if ($editSubscription): ?>
                        <button type="submit" name="edit_subscription" class="btn btn-primary">Update Subscription</button>
                        <a href="subscriptions.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_subscription" class="btn btn-primary">Add Subscription</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="subscriptions-list">
            <h2>Existing Subscription Plans</h2>
            
            <?php if (empty($subscriptions)): ?>
                <p class="empty-state">No subscription plans found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Duration</th>
                            <th>Message Limit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <tr>
                                <td><?php echo $subscription['name']; ?></td>
                                <td><?php echo formatCurrency($subscription['price']); ?></td>
                                <td><?php echo $subscription['duration']; ?> days</td>
                                <td><?php echo number_format($subscription['message_limit']); ?></td>
                                <td class="actions">
                                    <a href="subscriptions.php?edit=<?php echo $subscription['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                    
                                    <form method="post" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this subscription plan?');">
                                        <input type="hidden" name="id" value="<?php echo $subscription['id']; ?>">
                                        <button type="submit" name="delete_subscription" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
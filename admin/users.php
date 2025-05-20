<?php
$pageTitle = 'Manage Users';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Process actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $userId = (int)$_GET['id'];
    
    switch ($action) {
        case 'activate':
            $db->update('users', ['subscription_status' => 'active'], 'id = :id', ['id' => $userId]);
            $message = 'User subscription activated successfully.';
            $messageType = 'success';
            break;
            
        case 'deactivate':
            $db->update('users', ['subscription_status' => 'inactive'], 'id = :id', ['id' => $userId]);
            $message = 'User subscription deactivated successfully.';
            $messageType = 'success';
            break;
            
        case 'delete':
            $db->delete('users', 'id = :id', ['id' => $userId]);
            $message = 'User deleted successfully.';
            $messageType = 'success';
            break;
            
        default:
            $message = 'Invalid action.';
            $messageType = 'error';
            break;
    }
}

// Search and filter
$whereClause = '';
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = sanitizeInput($_GET['search']);
    $whereClause = "WHERE name LIKE :search OR email LIKE :search";
    $params['search'] = "%$search%";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    if (empty($whereClause)) {
        $whereClause = "WHERE subscription_status = :status";
    } else {
        $whereClause .= " AND subscription_status = :status";
    }
    $params['status'] = $_GET['status'];
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$countQuery = "SELECT COUNT(*) as count FROM users $whereClause";
$totalUsers = $db->fetch($countQuery, $params)['count'];
$totalPages = ceil($totalUsers / $limit);

// Get users
$query = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$users = $db->fetchAll($query, $params);

// Include header
include '../includes/header.php';
?>

<main class="container admin-container">
    <h1>Manage Users</h1>
    
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="admin-actions">
        <form method="get" class="search-form">
            <div class="search-row">
                <div class="search-field">
                    <input type="text" name="search" placeholder="Search users..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                </div>
                <div class="filter-field">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="expired" <?php echo isset($_GET['status']) && $_GET['status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <div class="search-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="users.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
    
    <div class="users-table-container">
        <table class="data-table users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Subscription</th>
                    <th>Expiry</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['name']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td>
                                <?php if (isSubscriptionActive($user)): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($user['subscription_expiry'])): ?>
                                    <?php echo formatDate($user['subscription_expiry']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td class="actions">
                                <a href="user-details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">View</a>
                                
                                <?php if ($user['subscription_status'] === 'active'): ?>
                                    <a href="users.php?action=deactivate&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to deactivate this user\'s subscription?')">Deactivate</a>
                                <?php else: ?>
                                    <a href="users.php?action=activate&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success">Activate</a>
                                <?php endif; ?>
                                
                                <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">Delete</a>
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
                <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" class="prev">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" class="next">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
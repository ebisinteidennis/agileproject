<?php
$pageTitle = 'Message Management';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Search and filter
$whereClause = '';

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $whereClause = "WHERE m.user_id = " . (int)$_GET['user_id'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = sanitizeInput($_GET['search']);
    if (empty($whereClause)) {
        $whereClause = "WHERE m.message LIKE '%" . $db->escape($search) . "%'";
    } else {
        $whereClause .= " AND m.message LIKE '%" . $db->escape($search) . "%'";
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count total messages - using fetch() instead of fetchRow()
$countQuery = "SELECT COUNT(*) as count FROM messages m $whereClause";
$countResult = $db->fetch($countQuery);
$totalMessages = isset($countResult['count']) ? $countResult['count'] : 0;
$totalPages = ceil($totalMessages / $limit);

// Basic query with limit and offset as integers
$query = "SELECT m.*, u.name as user_name, u.email as user_email 
          FROM messages m 
          JOIN users u ON m.user_id = u.id 
          $whereClause 
          ORDER BY m.created_at DESC 
          LIMIT $limit OFFSET $offset";

// Execute simple query
$messages = $db->fetchAll($query);

// Get users for filter
$users = $db->fetchAll("SELECT id, name, email FROM users ORDER BY name ASC");

// Include header
include '../includes/header.php';
?>

<main class="container admin-container">
    <h1>Message Management</h1>
    
    <div class="admin-actions">
        <form method="get" class="search-form">
            <div class="search-row">
                <div class="filter-field">
                    <select name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo isset($_GET['user_id']) && $_GET['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="search-field">
                    <input type="text" name="search" placeholder="Search in messages..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                <div class="search-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="messages.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
    
    <div class="messages-container">
        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <p>No messages found. Try changing your search criteria.</p>
            </div>
        <?php else: ?>
            <div class="message-list admin-message-list">
                <?php foreach ($messages as $message): ?>
                    <div class="message-item admin-message-item <?php echo htmlspecialchars($message['sender_type']); ?>">
                        <div class="message-meta">
                            <span class="message-user">
                                <?php echo htmlspecialchars($message['user_name']); ?> (<?php echo htmlspecialchars($message['user_email']); ?>)
                            </span>
                            <span class="message-visitor">
                                Visitor ID: <?php echo (int)$message['visitor_id']; ?>
                            </span>
                            <?php if(isset($message['widget_id']) && !empty($message['widget_id'])): ?>
                            <span class="message-widget">
                                Widget ID: <?php echo htmlspecialchars($message['widget_id']); ?>
                            </span>
                            <?php endif; ?>
                            <span class="message-time">
                                <?php echo date('M j, Y g:i a', strtotime($message['created_at'])); ?>
                            </span>
                            <span class="message-type">
                                <?php echo $message['sender_type'] === 'visitor' ? 'From Visitor' : 'From Agent'; ?>
                            </span>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . (int)$_GET['user_id'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="prev">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . (int)$_GET['user_id'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . (int)$_GET['user_id'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="next">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
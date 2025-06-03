<?php
$pageTitle = 'Message Management';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Function to validate date format
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Search and filter using prepared statements
$whereClause = '';
$params = [];
$whereConditions = [];

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $whereConditions[] = "m.user_id = ?";
    $params[] = (int)$_GET['user_id'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = sanitizeInput($_GET['search']);
    $whereConditions[] = "m.message LIKE ?";
    $params[] = '%' . $search . '%';
}

if (isset($_GET['sender_type']) && !empty($_GET['sender_type'])) {
    $senderType = sanitizeInput($_GET['sender_type']);
    $whereConditions[] = "m.sender_type = ?";
    $params[] = $senderType;
}

// Date filter
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $dateFrom = sanitizeInput($_GET['date_from']);
    if (validateDate($dateFrom)) {
        $whereConditions[] = "DATE(m.created_at) >= ?";
        $params[] = $dateFrom;
    }
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $dateTo = sanitizeInput($_GET['date_to']);
    if (validateDate($dateTo)) {
        $whereConditions[] = "DATE(m.created_at) <= ?";
        $params[] = $dateTo;
    }
}

// Build WHERE clause
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Count total messages using prepared statement
$countQuery = "SELECT COUNT(*) as count FROM messages m LEFT JOIN users u ON m.user_id = u.id $whereClause";
$countResult = $db->fetch($countQuery, $params);
$totalMessages = isset($countResult['count']) ? $countResult['count'] : 0;
$totalPages = ceil($totalMessages / $limit);

// Main query with prepared statement (LIMIT and OFFSET as integers, not parameters)
$query = "SELECT m.*, u.name as user_name, u.email as user_email 
          FROM messages m 
          LEFT JOIN users u ON m.user_id = u.id 
          $whereClause 
          ORDER BY m.created_at DESC 
          LIMIT $limit OFFSET $offset";

// Use only the WHERE clause parameters, not LIMIT/OFFSET
$messages = $db->fetchAll($query, $params);

// Get users for filter
$users = $db->fetchAll("SELECT id, name, email FROM users ORDER BY name ASC");

// Get users for filter
$users = $db->fetchAll("SELECT id, name, email FROM users ORDER BY name ASC");

// Build query string for pagination
function buildQueryString($excludeKey = '') {
    $params = $_GET;
    if ($excludeKey && isset($params[$excludeKey])) {
        unset($params[$excludeKey]);
    }
    return !empty($params) ? '&' . http_build_query($params) : '';
}

// Include header
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .filter-card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            margin-bottom: 2rem;
        }
        
        .message-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dee2e6;
            margin-bottom: 1.5rem; /* Increased spacing between messages */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .message-card.visitor {
            border-left-color: #0d6efd;
        }
        
        .message-card.agent {
            border-left-color: #198754;
        }
        
        .message-meta {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.25rem; /* Better padding */
        }
        
        .message-content {
            background-color: white;
            padding: 1.25rem; /* Better padding for content */
            line-height: 1.6; /* Better line spacing */
        }
        
        .badge-visitor {
            background-color: #0d6efd;
        }
        
        .badge-agent {
            background-color: #198754;
        }
        
        .stats-card {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 10px;
        }
        
        .filter-toggle {
            border-radius: 25px;
        }
        
        /* Improved spacing for message list */
        .messages-section {
            padding: 0 0.5rem;
        }
        
        .message-list-container {
            padding: 1rem 0;
        }
        
        /* Better modal message display */
        .modal-message-content {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            line-height: 1.6;
            white-space: pre-wrap; /* Preserve line breaks */
            word-wrap: break-word;
        }
        
        /* Better badge spacing */
        .badge-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        
        .badge-container .badge {
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .admin-header h1 {
                font-size: 1.75rem;
            }
            
            .message-card {
                margin-bottom: 1rem;
            }
            
            .btn {
                font-size: 0.875rem;
            }
            
            .message-meta,
            .message-content {
                padding: 0.75rem;
            }
            
            .messages-section {
                padding: 0;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Better text truncation */
        .message-preview {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.5;
        }
    </style>
</head>
<body class="bg-light">

<div class="admin-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2"><i class="bi bi-chat-dots me-2"></i>Message Management</h1>
                <p class="mb-0 opacity-75">Monitor and manage all user messages</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="card stats-card mt-3 mt-md-0">
                    <div class="card-body text-center">
                        <h3 class="text-primary mb-0"><?php echo number_format($totalMessages); ?></h3>
                        <small class="text-muted">Total Messages</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-md-4">
    <!-- Filter Section -->
    <div class="row">
        <div class="col-12">
            <div class="card filter-card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters & Search</h5>
                        <button class="btn btn-outline-primary btn-sm filter-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse show" id="filterCollapse">
                    <div class="card-body">
                        <form method="get" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <!-- User Filter -->
                                <div class="col-md-6 col-lg-3">
                                    <label for="user_id" class="form-label">
                                        <i class="bi bi-person me-1"></i>Filter by User
                                    </label>
                                    <select name="user_id" id="user_id" class="form-select">
                                        <option value="">All Users</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo isset($_GET['user_id']) && $_GET['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Sender Type Filter -->
                                <div class="col-md-6 col-lg-3">
                                    <label for="sender_type" class="form-label">
                                        <i class="bi bi-chat-square me-1"></i>Message Type
                                    </label>
                                    <select name="sender_type" id="sender_type" class="form-select">
                                        <option value="">All Types</option>
                                        <option value="visitor" <?php echo isset($_GET['sender_type']) && $_GET['sender_type'] == 'visitor' ? 'selected' : ''; ?>>From Visitor</option>
                                        <option value="agent" <?php echo isset($_GET['sender_type']) && $_GET['sender_type'] == 'agent' ? 'selected' : ''; ?>>From Agent</option>
                                    </select>
                                </div>
                                
                                <!-- Date From -->
                                <div class="col-md-6 col-lg-3">
                                    <label for="date_from" class="form-label">
                                        <i class="bi bi-calendar me-1"></i>From Date
                                    </label>
                                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                                </div>
                                
                                <!-- Date To -->
                                <div class="col-md-6 col-lg-3">
                                    <label for="date_to" class="form-label">
                                        <i class="bi bi-calendar-check me-1"></i>To Date
                                    </label>
                                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                                </div>
                                
                                <!-- Search -->
                                <div class="col-12">
                                    <label for="search" class="form-label">
                                        <i class="bi bi-search me-1"></i>Search in Messages
                                    </label>
                                    <div class="input-group">
                                        <input type="text" name="search" id="search" class="form-control" placeholder="Search in message content..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="bi bi-search me-1"></i>Search
                                        </button>
                                        <a href="messages.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Messages Section -->
    <div class="row">
        <div class="col-12">
            <div class="messages-section">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="bi bi-chat-dots"></i>
                        <h4>No messages found</h4>
                        <p>Try adjusting your search criteria or filters to find messages.</p>
                        <a href="messages.php" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset Filters
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Results Info -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="text-muted">
                            Showing <?php echo count($messages); ?> of <?php echo number_format($totalMessages); ?> messages
                            <?php if ($page > 1): ?>
                                (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
                            <?php endif; ?>
                        </div>
                        <div class="d-none d-md-block">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Click on any message to view details
                            </small>
                        </div>
                    </div>
                    
                    <!-- Messages List -->
                    <div class="message-list-container">
                        <?php foreach ($messages as $message): ?>
                            <div class="card message-card <?php echo htmlspecialchars($message['sender_type']); ?>" data-bs-toggle="modal" data-bs-target="#messageModal<?php echo $message['id']; ?>" style="cursor: pointer;">
                                <div class="message-meta">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="badge-container">
                                                <span class="badge <?php echo $message['sender_type'] === 'visitor' ? 'badge-visitor' : 'badge-agent'; ?>">
                                                    <i class="bi bi-<?php echo $message['sender_type'] === 'visitor' ? 'person' : 'headset'; ?> me-1"></i>
                                                    <?php echo $message['sender_type'] === 'visitor' ? 'Visitor' : 'Agent'; ?>
                                                </span>
                                                
                                                <?php if (!empty($message['user_name'])): ?>
                                                    <span class="text-primary fw-semibold">
                                                        <i class="bi bi-person-circle me-1"></i>
                                                        <?php echo htmlspecialchars($message['user_name']); ?>
                                                    </span>
                                                    <small class="text-muted"><?php echo htmlspecialchars($message['user_email']); ?></small>
                                                <?php endif; ?>
                                                
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-eye me-1"></i>Visitor ID: <?php echo (int)$message['visitor_id']; ?>
                                                </span>
                                                
                                                <?php if(isset($message['widget_id']) && !empty($message['widget_id'])): ?>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-widget me-1"></i>Widget: <?php echo htmlspecialchars($message['widget_id']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo date('M j, Y g:i a', strtotime($message['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="message-content">
                                    <div class="message-preview">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Message Modal -->
                            <div class="modal fade" id="messageModal<?php echo $message['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <span class="badge <?php echo $message['sender_type'] === 'visitor' ? 'badge-visitor' : 'badge-agent'; ?> me-2">
                                                    <i class="bi bi-<?php echo $message['sender_type'] === 'visitor' ? 'person' : 'headset'; ?> me-1"></i>
                                                    <?php echo $message['sender_type'] === 'visitor' ? 'Visitor' : 'Agent'; ?>
                                                </span>
                                                Message Details
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <?php if (!empty($message['user_name'])): ?>
                                                    <div class="col-md-6">
                                                        <strong>User:</strong> <?php echo htmlspecialchars($message['user_name']); ?>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Email:</strong> <?php echo htmlspecialchars($message['user_email']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="col-md-6">
                                                    <strong>Visitor ID:</strong> <?php echo (int)$message['visitor_id']; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Date:</strong> <?php echo date('M j, Y g:i a', strtotime($message['created_at'])); ?>
                                                </div>
                                                <?php if(isset($message['widget_id']) && !empty($message['widget_id'])): ?>
                                                    <div class="col-12">
                                                        <strong>Widget ID:</strong> <?php echo htmlspecialchars($message['widget_id']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="col-12">
                                                    <strong>Message:</strong>
                                                    <div class="modal-message-content mt-2">
                                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Messages pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo buildQueryString('page'); ?>">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                
                                if ($start > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?php echo buildQueryString('page'); ?>">1</a>
                                    </li>
                                    <?php if ($start > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif;
                                endif;
                                
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo buildQueryString('page'); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor;
                                
                                if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo buildQueryString('page'); ?>"><?php echo $totalPages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo buildQueryString('page'); ?>">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        const closeButton = alert.querySelector('.btn-close');
        if (closeButton) {
            closeButton.click();
        }
    });
}, 5000);

// Smooth scrolling for pagination
document.querySelectorAll('.pagination a').forEach(link => {
    link.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>
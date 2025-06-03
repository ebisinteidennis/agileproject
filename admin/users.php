<?php
$pageTitle = 'Manage Users';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Process bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_users'])) {
    $selectedUsers = $_POST['selected_users'];
    $bulkAction = $_POST['bulk_action'];
    
    foreach ($selectedUsers as $userId) {
        $userId = (int)$userId;
        switch ($bulkAction) {
            case 'activate':
                $db->update('users', ['subscription_status' => 'active'], 'id = :id', ['id' => $userId]);
                break;
            case 'deactivate':
                $db->update('users', ['subscription_status' => 'inactive'], 'id = :id', ['id' => $userId]);
                break;
            case 'delete':
                $db->delete('users', 'id = :id', ['id' => $userId]);
                break;
        }
    }
    $message = 'Bulk action completed successfully.';
    $messageType = 'success';
}

// Process individual actions
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

// Search and filter logic
$whereClause = '';
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = sanitizeInput($_GET['search']);
    $whereClause = "WHERE (name LIKE :search OR email LIKE :search OR widget_id LIKE :search)";
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

// Date range filter
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    if (empty($whereClause)) {
        $whereClause = "WHERE created_at >= :date_from";
    } else {
        $whereClause .= " AND created_at >= :date_from";
    }
    $params['date_from'] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    if (empty($whereClause)) {
        $whereClause = "WHERE created_at <= :date_to";
    } else {
        $whereClause .= " AND created_at <= :date_to";
    }
    $params['date_to'] = $_GET['date_to'];
}

// Sort options
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sortOrder = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$allowedSortColumns = ['id', 'name', 'email', 'subscription_status', 'created_at', 'subscription_expiry'];
if (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 'created_at';
}
$sortOrder = ($sortOrder === 'ASC') ? 'ASC' : 'DESC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$offset = ($page - 1) * $limit;

// Get statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'active' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE subscription_status = 'active'")['count'],
    'inactive' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE subscription_status = 'inactive'")['count'],
    'expired' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE subscription_expiry < NOW() AND subscription_status = 'active'")['count']
];

$countQuery = "SELECT COUNT(*) as count FROM users $whereClause";
$totalUsers = $db->fetch($countQuery, $params)['count'];
$totalPages = ceil($totalUsers / $limit);

// Get users with additional info
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM visitors WHERE user_id = u.id) as visitor_count,
          (SELECT COUNT(*) FROM messages WHERE user_id = u.id) as message_count
          FROM users u $whereClause ORDER BY $sortBy $sortOrder LIMIT $limit OFFSET $offset";
$users = $db->fetchAll($query, $params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #fd7e14;
            --info-color: #0dcaf0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .stat-card {
            border-left: 4px solid;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        }

        .stat-card.primary { border-left-color: var(--primary-color); }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background-color: rgba(111, 66, 193, 0.1); color: var(--primary-color); }
        .stat-icon.success { background-color: rgba(25, 135, 84, 0.1); color: var(--success-color); }
        .stat-icon.warning { background-color: rgba(253, 126, 20, 0.1); color: var(--warning-color); }
        .stat-icon.danger { background-color: rgba(220, 53, 69, 0.1); color: var(--danger-color); }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .widget-id {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background-color: #f1f3f4;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #5f6368;
        }

        .table > :not(caption) > * > * {
            padding: 1rem 0.75rem;
            border-bottom-width: 1px;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .badge {
            font-size: 0.75em;
            font-weight: 500;
            padding: 0.5em 0.75em;
            border-radius: 50px;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 8px;
        }

        .pagination .page-link {
            border: none;
            color: #6f42c1;
            margin: 0 2px;
            border-radius: 8px;
        }

        .pagination .page-item.active .page-link {
            background-color: #6f42c1;
            border-color: #6f42c1;
        }

        .filter-collapse {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #dee2e6;
        }

        @media (max-width: 768px) {
            .table-responsive {
                border: none;
            }
            
            .mobile-stack {
                display: none;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }

        .alert {
            border: none;
            border-radius: 12px;
            border-left: 4px solid;
        }

        .alert-success { border-left-color: var(--success-color); }
        .alert-danger { border-left-color: var(--danger-color); }

        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .sortable:hover {
            color: var(--primary-color);
        }

        .sortable.active {
            color: var(--primary-color);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-users me-1"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title h3 mb-2">
                            <i class="fas fa-users text-primary me-2"></i>User Management
                        </h1>
                        <p class="card-text text-muted mb-0">Manage and monitor all registered users</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($message)): ?>
            <div class="row mb-4">
                <div class="col">
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card primary h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                            <p class="text-muted mb-0 small text-uppercase fw-bold">Total Users</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card success h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon success me-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['active']); ?></h3>
                            <p class="text-muted mb-0 small text-uppercase fw-bold">Active Users</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card warning h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon warning me-3">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['inactive']); ?></h3>
                            <p class="text-muted mb-0 small text-uppercase fw-bold">Inactive Users</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card danger h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon danger me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['expired']); ?></h3>
                            <p class="text-muted mb-0 small text-uppercase fw-bold">Expired</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-decoration-none p-0 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                                <i class="fas fa-filter me-2"></i>Filters
                                <i class="fas fa-chevron-down ms-2"></i>
                            </button>
                        </h5>
                    </div>
                    <div class="collapse show" id="filterCollapse">
                        <div class="card-body">
                            <form method="get" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-uppercase">Search</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Name, email, or widget ID..." 
                                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-uppercase">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo (isset($_GET['status']) && $_GET['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-uppercase">Date From</label>
                                    <input type="date" name="date_from" class="form-control" 
                                           value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-uppercase">Date To</label>
                                    <input type="date" name="date_to" class="form-control" 
                                           value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i> Apply
                                    </button>
                                    <a href="?" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>Users (<?php echo number_format($totalUsers); ?>)
                            </h5>
                            
                            <div class="d-flex flex-column flex-md-row gap-2 align-items-start align-items-md-center">
                                <!-- Bulk Actions -->
                                <form method="post" id="bulkForm" class="d-flex gap-2">
                                    <select name="bulk_action" class="form-select form-select-sm" id="bulkAction" style="width: auto;">
                                        <option value="">Bulk Actions</option>
                                        <option value="activate">Activate</option>
                                        <option value="deactivate">Deactivate</option>
                                        <option value="delete">Delete</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary" id="bulkSubmit" disabled>
                                        Apply
                                    </button>
                                </form>

                                <!-- Per Page -->
                                <div class="d-flex align-items-center gap-2">
                                    <label class="small text-nowrap">Show</label>
                                    <select class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                                        <option value="10" <?php echo ($limit == 10) ? 'selected' : ''; ?>>10</option>
                                        <option value="20" <?php echo ($limit == 20) ? 'selected' : ''; ?>>20</option>
                                        <option value="50" <?php echo ($limit == 50) ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo ($limit == 100) ? 'selected' : ''; ?>>100</option>
                                    </select>
                                </div>

                                <!-- Add User -->
                                <a href="add-user.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Add User
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th class="sortable <?php echo ($sortBy === 'id') ? 'active' : ''; ?>" onclick="sortTable('id')">
                                            ID <i class="fas fa-sort ms-1"></i>
                                        </th>
                                        <th class="sortable <?php echo ($sortBy === 'name') ? 'active' : ''; ?>" onclick="sortTable('name')">
                                            User <i class="fas fa-sort ms-1"></i>
                                        </th>
                                        <th class="d-none d-md-table-cell">Widget ID</th>
                                        <th class="sortable <?php echo ($sortBy === 'subscription_status') ? 'active' : ''; ?>" onclick="sortTable('subscription_status')">
                                            Status <i class="fas fa-sort ms-1"></i>
                                        </th>
                                        <th class="d-none d-lg-table-cell">Stats</th>
                                        <th class="sortable d-none d-lg-table-cell <?php echo ($sortBy === 'created_at') ? 'active' : ''; ?>" onclick="sortTable('created_at')">
                                            Registered <i class="fas fa-sort ms-1"></i>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                                                    <h5>No Users Found</h5>
                                                    <p class="mb-0">Try adjusting your filters or search criteria</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input user-checkbox" type="checkbox" 
                                                               name="selected_users[]" value="<?php echo $user['id']; ?>">
                                                    </div>
                                                </td>
                                                <td><strong>#<?php echo $user['id']; ?></strong></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3">
                                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    <?php if (!empty($user['widget_id'])): ?>
                                                        <span class="widget-id"><?php echo htmlspecialchars($user['widget_id']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $isActive = isSubscriptionActive($user);
                                                    $isExpired = !empty($user['subscription_expiry']) && strtotime($user['subscription_expiry']) < time();
                                                    ?>
                                                    <?php if ($isActive && !$isExpired): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($isExpired): ?>
                                                        <span class="badge bg-warning">Expired</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="d-none d-lg-table-cell">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users text-primary me-1"></i><?php echo $user['visitor_count'] ?? 0; ?>
                                                        <i class="fas fa-comments text-info ms-2 me-1"></i><?php echo $user['message_count'] ?? 0; ?>
                                                    </small>
                                                </td>
                                                <td class="d-none d-lg-table-cell">
                                                    <small class="text-muted"><?php echo formatDate($user['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="user-details.php?id=<?php echo $user['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="edit-user.php?id=<?php echo $user['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Edit User
                                                            </a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <?php if ($user['subscription_status'] === 'active'): ?>
                                                                <li><a class="dropdown-item text-warning" 
                                                                       href="?action=deactivate&id=<?php echo $user['id']; ?>" 
                                                                       onclick="return confirm('Deactivate this user\'s subscription?')">
                                                                    <i class="fas fa-pause me-2"></i>Deactivate
                                                                </a></li>
                                                            <?php else: ?>
                                                                <li><a class="dropdown-item text-success" 
                                                                       href="?action=activate&id=<?php echo $user['id']; ?>">
                                                                    <i class="fas fa-check me-2"></i>Activate
                                                                </a></li>
                                                            <?php endif; ?>
                                                            <li><a class="dropdown-item text-danger" 
                                                                   href="?action=delete&id=<?php echo $user['id']; ?>" 
                                                                   onclick="return confirm('Delete this user? This cannot be undone.')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                                <div class="text-muted">
                                    Showing <?php echo (($page - 1) * $limit) + 1; ?> to 
                                    <?php echo min($page * $limit, $totalUsers); ?> of 
                                    <?php echo number_format($totalUsers); ?> entries
                                </div>
                                
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($totalPages, $page + 2);
                                        
                                        if ($start > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">1</a>
                                            </li>
                                            <?php if ($start > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $start; $i <= $end; $i++): ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($end < $totalPages): ?>
                                            <?php if ($end < $totalPages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $totalPages; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                                    <?php echo $totalPages; ?>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all functionality
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });

        // Update bulk actions
        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkSubmit = document.getElementById('bulkSubmit');
            const bulkAction = document.getElementById('bulkAction');
            
            if (checkedBoxes.length > 0) {
                bulkSubmit.disabled = false;
                bulkAction.required = true;
            } else {
                bulkSubmit.disabled = true;
                bulkAction.required = false;
            }
        }

        // Listen to individual checkbox changes
        document.querySelectorAll('.user-checkbox').forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });

        // Bulk form submission
        document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const bulkAction = document.getElementById('bulkAction').value;
            if (!bulkAction) {
                alert('Please select a bulk action');
                return;
            }
            
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one user');
                return;
            }
            
            let message = `Are you sure you want to ${bulkAction} ${checkedBoxes.length} user(s)?`;
            if (bulkAction === 'delete') {
                message += ' This action cannot be undone.';
            }
            
            if (confirm(message)) {
                // Create form data
                const formData = new FormData();
                formData.append('bulk_action', bulkAction);
                
                checkedBoxes.forEach(cb => {
                    formData.append('selected_users[]', cb.value);
                });
                
                // Submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname;
                
                for (let [key, value] of formData.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Sort table
        function sortTable(column) {
            const url = new URL(window.location);
            const currentSort = url.searchParams.get('sort_by');
            const currentOrder = url.searchParams.get('sort_order');
            
            url.searchParams.set('sort_by', column);
            
            if (currentSort === column && currentOrder === 'DESC') {
                url.searchParams.set('sort_order', 'ASC');
            } else {
                url.searchParams.set('sort_order', 'DESC');
            }
            
            window.location.href = url.toString();
        }

        // Change per page
        function changePerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActions();
        });
    </script>
</body>
</html>
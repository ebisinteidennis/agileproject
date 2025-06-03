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
        $name = trim($_POST['name']);
        $price = (float)$_POST['price'];
        $duration = (int)$_POST['duration'];
        $message_limit = (int)$_POST['message_limit'];
        $features = trim($_POST['features']);
        $status = isset($_POST['status']) ? $_POST['status'] : 'active';
        
        // Validate inputs
        if (empty($name) || $price <= 0 || $duration <= 0 || $message_limit <= 0) {
            $error = 'Please fill in all fields with valid values.';
        } else {
            $subscriptionData = [
                'name' => $name,
                'price' => $price,
                'duration' => $duration,
                'message_limit' => $message_limit,
                'features' => $features,
                'status' => $status
            ];
            
            if (isset($_POST['add_subscription'])) {
                // Add new subscription
                $subscriptionData['created_at'] = date('Y-m-d H:i:s');
                $subscriptionData['updated_at'] = date('Y-m-d H:i:s');
                $db->insert('subscriptions', $subscriptionData);
                $success = 'Subscription plan added successfully.';
            } elseif (isset($_POST['edit_subscription'])) {
                // Update existing subscription
                $id = (int)$_POST['id'];
                $subscriptionData['updated_at'] = date('Y-m-d H:i:s');
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
    $editSubscription = $db->fetch("SELECT * FROM subscriptions WHERE id = :id", ['id' => $_GET['edit']]);
}

// Get all subscriptions with usage stats
$subscriptions = $db->fetchAll("
    SELECT s.*, 
           COUNT(DISTINCT u.id) as total_users,
           COALESCE(SUM(CASE WHEN u.subscription_status = 'active' THEN 1 ELSE 0 END), 0) as active_users
    FROM subscriptions s
    LEFT JOIN users u ON u.subscription_id = s.id
    GROUP BY s.id
    ORDER BY s.price ASC
");

// Get subscription statistics
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT CASE WHEN u.subscription_status = 'active' THEN u.id END) as active_users,
        COUNT(DISTINCT CASE WHEN u.subscription_status = 'inactive' OR u.subscription_expiry < NOW() THEN u.id END) as expired_users,
        COALESCE(SUM(CASE WHEN u.subscription_expiry IS NOT NULL AND u.subscription_expiry < NOW() THEN 1 ELSE 0 END), 0) as expired_count
    FROM users u
    WHERE u.id > 0
");

// Get message stats
$messageStats = $db->fetch("
    SELECT 
        COUNT(*) as total_messages,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as messages_30_days
    FROM messages
");

$stats = array_merge($stats, $messageStats);
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
        .stat-card.info { border-left-color: var(--info-color); }

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
        .stat-icon.info { background-color: rgba(13, 202, 240, 0.1); color: var(--info-color); }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
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

        .alert {
            border: none;
            border-radius: 12px;
            border-left: 4px solid;
        }

        .alert-success { border-left-color: var(--success-color); }
        .alert-danger { border-left-color: var(--danger-color); }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 1rem;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: #6c757d;
        }

        .features-text {
            font-size: 0.875rem;
            color: #6c757d;
            line-height: 1.4;
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

        .enforcement-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #e1bee7;
            border-radius: 12px;
        }

        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
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
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-1"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-credit-card me-1"></i>Subscriptions
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
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Subscription Plans</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title h3 mb-2">
                            <i class="fas fa-credit-card text-primary me-2"></i>Subscription Management
                        </h1>
                        <p class="card-text text-muted mb-0">Create and manage subscription plans for your live support service</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($error)): ?>
            <div class="row mb-4">
                <div class="col">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="row mb-4">
                <div class="col">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
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
                            <h3 class="mb-0"><?php echo number_format($stats['total_users']); ?></h3>
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
                            <h3 class="mb-0"><?php echo number_format($stats['active_users']); ?></h3>
                            <p class="text-muted mb-0 small text-uppercase fw-bold">Active Subscriptions</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card warning h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon warning me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['expired_users']); ?></h3>
                            <p class="text-muted mb-0 small text-uppercase fw-bold">Expired</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card info h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon info me-3">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['messages_30_days']); ?></h3>
                            <p class="text-muted mb-0 small text-uppercase fw-bold">Messages (30d)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enforcement Info -->
        <div class="row mb-4">
            <div class="col">
                <div class="card enforcement-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-shield-alt text-primary me-2"></i>Subscription Enforcement
                        </h5>
                        <p class="card-text mb-3">The live support widget automatically enforces subscription limits:</p>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Hides widget when subscription expires</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Blocks messages when limit reached</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Checks status every 5 minutes</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Shows subscription notices to users</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Add/Edit Form -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $editSubscription ? 'edit' : 'plus'; ?> me-2"></i>
                            <?php echo $editSubscription ? 'Edit Plan' : 'Add New Plan'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <?php if ($editSubscription): ?>
                                <input type="hidden" name="id" value="<?php echo $editSubscription['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Plan Name</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo $editSubscription ? htmlspecialchars($editSubscription['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (NGN)</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" class="form-control" 
                                       value="<?php echo $editSubscription ? $editSubscription['price'] : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duration" class="form-label">Duration (days)</label>
                                <input type="number" id="duration" name="duration" min="1" class="form-control" 
                                       value="<?php echo $editSubscription ? $editSubscription['duration'] : '30'; ?>" required>
                                <div class="form-text">Widget will stop working after this period</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message_limit" class="form-label">Message Limit</label>
                                <input type="number" id="message_limit" name="message_limit" min="1" class="form-control" 
                                       value="<?php echo $editSubscription ? $editSubscription['message_limit'] : '1000'; ?>" required>
                                <div class="form-text">Widget will block new messages after this limit</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="active" <?php echo (!$editSubscription || $editSubscription['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($editSubscription && $editSubscription['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="features" class="form-label">Features</label>
                                <textarea id="features" name="features" class="form-control" rows="4"><?php echo $editSubscription ? htmlspecialchars($editSubscription['features']) : ''; ?></textarea>
                                <div class="form-text">Enter each feature on a new line</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <?php if ($editSubscription): ?>
                                    <button type="submit" name="edit_subscription" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Update Plan
                                    </button>
                                    <a href="subscriptions.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </a>
                                <?php else: ?>
                                    <button type="submit" name="add_subscription" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Add Plan
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Subscriptions List -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Subscription Plans (<?php echo count($subscriptions); ?>)
                        </h5>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($subscriptions)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No subscription plans found</h5>
                                <p class="text-muted">Create your first subscription plan to get started</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Plan</th>
                                            <th>Price</th>
                                            <th>Duration</th>
                                            <th>Messages</th>
                                            <th>Users</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subscriptions as $subscription): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($subscription['name']); ?></strong>
                                                        <?php if ($subscription['features']): ?>
                                                            <div class="features-text">
                                                                <?php 
                                                                $features = explode("\n", $subscription['features']);
                                                                echo htmlspecialchars(implode(" • ", array_slice($features, 0, 2)));
                                                                if (count($features) > 2) echo "...";
                                                                ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><strong>₦<?php echo number_format($subscription['price'], 2); ?></strong></td>
                                                <td><?php echo $subscription['duration']; ?> days</td>
                                                <td><?php echo number_format($subscription['message_limit']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo $subscription['active_users']; ?> active
                                                    </span>
                                                    <?php if ($subscription['total_users'] > $subscription['active_users']): ?>
                                                        <span class="badge bg-secondary">
                                                            <?php echo ($subscription['total_users'] - $subscription['active_users']); ?> inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $subscription['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($subscription['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="subscriptions.php?edit=<?php echo $subscription['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($subscription['total_users'] == 0): ?>
                                                            <form method="post" class="d-inline" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this subscription plan?');">
                                                                <input type="hidden" name="id" value="<?php echo $subscription['id']; ?>">
                                                                <button type="submit" name="delete_subscription" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-secondary" disabled 
                                                                    title="Cannot delete - has active users">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const duration = parseInt(document.getElementById('duration').value);
            const messageLimit = parseInt(document.getElementById('message_limit').value);
            
            if (price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0');
                return;
            }
            
            if (duration <= 0) {
                e.preventDefault();
                alert('Duration must be greater than 0');
                return;
            }
            
            if (messageLimit <= 0) {
                e.preventDefault();
                alert('Message limit must be greater than 0');
                return;
            }
        });
    </script>
</body>
</html>
<?php
$pageTitle = 'Edit User';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

// Get user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    header('Location: users.php');
    exit;
}

// Get user data
$user = $db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);

if (!$user) {
    header('Location: users.php?error=User not found');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subscription_status = $_POST['subscription_status'] ?? '';
    $subscription_expiry = $_POST['subscription_expiry'] ?? null;
    $widget_id = trim($_POST['widget_id'] ?? '');
    $subscription_id = !empty($_POST['subscription_id']) ? (int)$_POST['subscription_id'] : null;
    
    // Basic validation
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } else {
        // Check if email exists for another user
        $existingUser = $db->fetch("SELECT id FROM users WHERE email = :email AND id != :id", 
            ['email' => $email, 'id' => $userId]);
        if ($existingUser) {
            $errors[] = 'Email already exists for another user';
        }
    }
    
    if (!in_array($subscription_status, ['active', 'inactive', 'expired'])) {
        $errors[] = 'Invalid subscription status';
    }
    
    if (!empty($subscription_expiry) && !strtotime($subscription_expiry)) {
        $errors[] = 'Invalid subscription expiry date';
    }
    
    // If no errors, update user
    if (empty($errors)) {
        try {
            $updateData = [
                'name' => $name,
                'email' => $email,
                'subscription_status' => $subscription_status,
                'subscription_expiry' => !empty($subscription_expiry) ? $subscription_expiry : null,
                'widget_id' => !empty($widget_id) ? $widget_id : $user['widget_id'],
                'subscription_id' => $subscription_id,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update('users', $updateData, 'id = :id', ['id' => $userId]);
            
            $message = 'User updated successfully!';
            $messageType = 'success';
            
            // Refresh user data
            $user = $db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
            
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get available subscriptions
$subscriptions = $db->fetchAll("SELECT * FROM subscriptions WHERE status = 'active' ORDER BY price ASC");

// Get user statistics
$userStats = [
    'visitors' => $db->fetch("SELECT COUNT(*) as count FROM visitors WHERE user_id = :id", ['id' => $userId])['count'] ?? 0,
    'messages' => $db->fetch("SELECT COUNT(*) as count FROM messages WHERE user_id = :id", ['id' => $userId])['count'] ?? 0,
    'last_activity' => $user['last_activity'] ?? null
];
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

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2rem;
            margin: 0 auto;
        }

        .stat-card {
            border-left: 4px solid;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        }

        .stat-card.primary { border-left-color: var(--primary-color); }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.info { border-left-color: var(--info-color); }

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

        .widget-id {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background-color: #f1f3f4;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #5f6368;
        }

        @media (max-width: 768px) {
            .user-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
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
                <li class="breadcrumb-item"><a href="users.php" class="text-decoration-none">Users</a></li>
                <li class="breadcrumb-item active">Edit User</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between">
                            <div class="d-flex align-items-center mb-3 mb-md-0">
                                <div class="user-avatar me-3">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h1 class="card-title h3 mb-1">Edit User</h1>
                                    <p class="card-text text-muted mb-0"><?php echo htmlspecialchars($user['name']); ?> (ID: #<?php echo $user['id']; ?>)</p>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="users.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Users
                                </a>
                                <a href="user-details.php?id=<?php echo $user['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($message)): ?>
            <div class="row mb-4">
                <div class="col">
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="row mb-4">
                <div class="col">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- User Statistics -->
            <div class="col-lg-4 mb-4">
                <div class="card stat-card primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-bar me-2"></i>User Statistics
                        </h5>
                        <div class="row text-center">
                            <div class="col-4">
                                <h4 class="text-primary"><?php echo number_format($userStats['visitors']); ?></h4>
                                <small class="text-muted">Visitors</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success"><?php echo number_format($userStats['messages']); ?></h4>
                                <small class="text-muted">Messages</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-info">
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
                                </h4>
                                <small class="text-muted">Status</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-info-circle me-2"></i>Account Information
                        </h6>
                        <div class="mb-2">
                            <small class="text-muted">Registered:</small>
                            <div><?php echo formatDate($user['created_at']); ?></div>
                        </div>
                        <?php if ($user['widget_id']): ?>
                        <div class="mb-2">
                            <small class="text-muted">Widget ID:</small>
                            <div><span class="widget-id"><?php echo htmlspecialchars($user['widget_id']); ?></span></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($userStats['last_activity']): ?>
                        <div class="mb-2">
                            <small class="text-muted">Last Activity:</small>
                            <div><?php echo formatDate($userStats['last_activity']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($user['subscription_expiry']): ?>
                        <div>
                            <small class="text-muted">Subscription Expires:</small>
                            <div><?php echo formatDate($user['subscription_expiry']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Edit User Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="editUserForm">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <!-- Subscription Details -->
                                <div class="col-md-6 mb-3">
                                    <label for="subscription_id" class="form-label">Subscription Plan</label>
                                    <select class="form-select" id="subscription_id" name="subscription_id">
                                        <option value="">No Subscription</option>
                                        <?php foreach ($subscriptions as $subscription): ?>
                                            <option value="<?php echo $subscription['id']; ?>" 
                                                    <?php echo ($user['subscription_id'] == $subscription['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subscription['name']); ?> - ₦<?php echo number_format($subscription['price']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="subscription_status" class="form-label">Subscription Status *</label>
                                    <select class="form-select" id="subscription_status" name="subscription_status" required>
                                        <option value="active" <?php echo ($user['subscription_status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($user['subscription_status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="expired" <?php echo ($user['subscription_status'] === 'expired') ? 'selected' : ''; ?>>Expired</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="subscription_expiry" class="form-label">Subscription Expiry</label>
                                    <input type="date" class="form-control" id="subscription_expiry" name="subscription_expiry" 
                                           value="<?php echo $user['subscription_expiry'] ? date('Y-m-d', strtotime($user['subscription_expiry'])) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="widget_id" class="form-label">Widget ID</label>
                                    <input type="text" class="form-control font-monospace" id="widget_id" name="widget_id" 
                                           value="<?php echo htmlspecialchars($user['widget_id'] ?? ''); ?>"
                                           placeholder="Leave empty to keep current">
                                    <div class="form-text">The unique identifier for this user's widget</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update User
                                    </button>
                                    <a href="users.php" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </a>
                                </div>
                                
                                <div class="dropdown">
                                    <button class="btn btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-cog me-1"></i>Actions
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="user-details.php?id=<?php echo $user['id']; ?>">
                                            <i class="fas fa-eye me-2"></i>View Full Details
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if ($user['subscription_status'] === 'active'): ?>
                                            <li><a class="dropdown-item text-warning" 
                                                   href="users.php?action=deactivate&id=<?php echo $user['id']; ?>" 
                                                   onclick="return confirm('Deactivate this user\'s subscription?')">
                                                <i class="fas fa-pause me-2"></i>Deactivate Account
                                            </a></li>
                                        <?php else: ?>
                                            <li><a class="dropdown-item text-success" 
                                                   href="users.php?action=activate&id=<?php echo $user['id']; ?>">
                                                <i class="fas fa-check me-2"></i>Activate Account
                                            </a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item text-danger" 
                                               href="users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                               onclick="return confirm('Delete this user? This cannot be undone.')">
                                            <i class="fas fa-trash me-2"></i>Delete User
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!name) {
                e.preventDefault();
                alert('Please enter a name');
                document.getElementById('name').focus();
                return;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Please enter an email address');
                document.getElementById('email').focus();
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                document.getElementById('email').focus();
                return;
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Confirm before leaving if form has changes
        let formChanged = false;
        const form = document.getElementById('editUserForm');
        const initialFormData = new FormData(form);
        
        form.addEventListener('input', function() {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        form.addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>
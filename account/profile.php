<?php
$pageTitle = 'Profile Settings';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email is already taken by another user
        $existingUser = getUserByEmail($email);
        if ($existingUser && $existingUser['id'] != $userId) {
            $error = 'Email address is already in use by another account.';
        } else {
            // Update user data
            $userData = [
                'name' => $name,
                'email' => $email
            ];
            
            // Update password if provided
            if (!empty($currentPassword) && !empty($newPassword)) {
                if (strlen($newPassword) < 8) {
                    $error = 'New password must be at least 8 characters long.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match.';
                } elseif (!password_verify($currentPassword, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $userData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
            }
            
            // If no errors, update user
            if (!isset($error)) {
                $db->update('users', $userData, 'id = :id', ['id' => $userId]);
                $success = 'Profile updated successfully.';
                
                // Update session data
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                // Refresh user data
                $user = getUserById($userId);
            }
        }
    }
}

// Include header
include '../includes/header.php';
?>

<main class="container profile-container">
    <h1>Profile Settings</h1>
    
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
    
    <div class="profile-card">
        <h2>Personal Information</h2>
        
        <form method="post" class="profile-form">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?php echo $user['name']; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
            </div>
            
            <h3>Change Password</h3>
            <p class="password-note">Leave blank to keep your current password.</p>
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" minlength="8">
                <small class="form-text text-muted">Password must be at least 8 characters long.</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
    
    <div class="profile-card danger-zone">
        <h2>Danger Zone</h2>
        
        <div class="danger-action">
            <div class="danger-info">
                <h3>Regenerate API Key</h3>
                <p>This will invalidate your current API key and generate a new one. Any integrations using your current API key will stop working.</p>
            </div>
            <form method="post" action="regenerate-api-key.php" onsubmit="return confirm('Are you sure you want to regenerate your API key?');">
                <button type="submit" class="btn btn-warning">Regenerate API Key</button>
            </form>
        </div>
        
        <div class="danger-action">
            <div class="danger-info">
                <h3>Delete Account</h3>
                <p>This will permanently delete your account and all associated data. This action cannot be undone.</p>
            </div>
            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteAccountModal">Delete Account</button>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete your account? This action cannot be undone and all your data will be permanently deleted.</p>
                    <form id="deleteAccountForm" method="post" action="delete-account.php">
                        <div class="form-group">
                            <label for="delete_password">Please enter your password to confirm:</label>
                            <input type="password" id="delete_password" name="password" class="form-control" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" form="deleteAccountForm" class="btn btn-danger">Delete Account</button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
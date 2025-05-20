<?php
$pageTitle = 'Reset Password';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/account/dashboard.php');
}

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    redirect(SITE_URL . '/account/forgot-password.php');
}

$token = $_GET['token'];

// Check if token is valid
$user = $db->fetch(
    "SELECT * FROM users WHERE reset_token = :token AND reset_expires > NOW()",
    ['token' => $token]
);

if (!$user) {
    $tokenInvalid = true;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($tokenInvalid)) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Update password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $db->update(
            'users',
            [
                'password' => $hashedPassword,
                'reset_token' => null,
                'reset_expires' => null
            ],
            'id = :id',
            ['id' => $user['id']]
        );
        
        $success = 'Your password has been reset successfully. You can now login with your new password.';
    }
}

// Include header
include '../includes/header.php';
?>

<main class="container account-container">
    <div class="auth-form-container">
        <h1>Reset Password</h1>
        
        <?php if (isset($tokenInvalid)): ?>
            <div class="alert alert-danger">
                The password reset link is invalid or has expired. Please request a new one.
            </div>
            <div class="auth-links">
                <p><a href="forgot-password.php">Request New Link</a></p>
                <p><a href="login.php">Back to Login</a></p>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php elseif (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
            <div class="auth-links">
                <p><a href="login.php">Login Now</a></p>
            </div>
        <?php else: ?>
            <p>Please enter your new password below.</p>
            
            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                    <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
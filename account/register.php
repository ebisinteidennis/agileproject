<?php
$pageTitle = 'Register';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/account/dashboard.php');
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Register user
        $result = registerUser($name, $email, $password);
        
        if ($result['success']) {
            $success = $result['message'];
            // Redirect to login page after 3 seconds
            header("refresh:3;url=" . SITE_URL . "/account/login.php");
        } else {
            $error = $result['message'];
        }
    }
}

// Include header
include '../includes/header.php';
?>

<main class="container account-container">
    <div class="auth-form-container">
        <h1>Create an Account</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php else: ?>
            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo isset($_POST['name']) ? $_POST['name'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                    <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                
                <div class="form-group terms-checkbox">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the <a href="../terms.php" target="_blank">Terms of Service</a> and <a href="../privacy.php" target="_blank">Privacy Policy</a></label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </form>
        <?php endif; ?>
        
        <div class="auth-separator">
            <span>or</span>
        </div>
        
        <div class="auth-links">
            <p>Already have an account? <a href="login.php">Login</a></p>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
<?php
$pageTitle = 'Login';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/account/dashboard.php');
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Attempt login
        $result = loginUser($email, $password);
        
        if ($result['success']) {
            redirect(SITE_URL . '/account/dashboard.php');
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
        <h1>Login to Your Account</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" class="auth-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-group remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
                <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        
        <div class="auth-separator">
            <span>or</span>
        </div>
        
        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Register now</a></p>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
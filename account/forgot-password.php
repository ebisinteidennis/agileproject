<?php
$pageTitle = 'Forgot Password';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/account/dashboard.php');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $user = getUserByEmail($email);
        
        if ($user) {
            // Generate password reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // Token expires in 1 hour
            
            // Store token in database
            $db->query(
                "UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id",
                ['token' => $token, 'expires' => $expires, 'id' => $user['id']]
            );
            
            // Send password reset email
            $resetLink = SITE_URL . '/account/reset-password.php?token=' . $token;
            $settings = getSiteSettings();
            
            $to = $email;
            $subject = 'Password Reset Request';
            $headers = "From: " . $settings['site_name'] . " <" . $settings['admin_email'] . ">\r\n";
            $headers .= "Reply-To: " . $settings['admin_email'] . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $message = '
                <html>
                <head>
                    <title>Password Reset Request</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>You have requested to reset your password. Click the link below to reset your password:</p>
                    <p><a href="' . $resetLink . '">' . $resetLink . '</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you did not request a password reset, please ignore this email.</p>
                    <p>Regards,<br>' . $settings['site_name'] . ' Team</p>
                </body>
                </html>
            ';
            
            mail($to, $subject, $message, $headers);
            
            $success = 'Password reset instructions have been sent to your email address.';
        } else {
            // Don't reveal if the email exists or not
            $success = 'If your email address is registered, you will receive password reset instructions.';
        }
    }
}

// Include header
include '../includes/header.php';
?>

<main class="container account-container">
    <div class="auth-form-container">
        <h1>Forgot Password</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
            <div class="auth-links">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        <?php else: ?>
            <p>Enter your email address below and we'll send you instructions to reset your password.</p>
            
            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </form>
            
            <div class="auth-links">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
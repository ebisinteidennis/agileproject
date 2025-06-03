<?php
$pageTitle = 'Contact Us';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$settings = getSiteSettings();

// Process contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Send email
        $to = $settings['admin_email'];
        $headers = "From: $name <$email>" . "\r\n";
        $headers .= "Reply-To: $email" . "\r\n";
        $headers .= "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
        
        $emailBody = "
            <html>
            <head>
                <title>$subject</title>
            </head>
            <body>
                <h2>Contact Form Submission</h2>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br($message) . "</p>
            </body>
            </html>
        ";
        
        if (mail($to, "Contact Form: $subject", $emailBody, $headers)) {
            $success = 'Your message has been sent successfully. We will get back to you soon.';
        } else {
            $error = 'There was an error sending your message. Please try again later.';
        }
    }
}

// Include header
include 'includes/header.php';
?>

<main class="container contact-page">
    <section class="page-header">
        <h1>Contact Us</h1>
    </section>
    
    <section class="contact-content">
        <div class="contact-info">
            <h2>Get in Touch</h2>
            <p>Have questions about our services? Need help with your account? We're here to help!</p>
            
            <div class="contact-methods">
                <div class="contact-method">
                    <div class="method-icon">📧</div>
                    <h3>Email</h3>
                    <p><a href="mailto:<?php echo $settings['admin_email']; ?>"><?php echo $settings['admin_email']; ?></a></p>
                </div>
                
                <div class="contact-method">
                    <div class="method-icon">📱</div>
                    <h3>Phone</h3>
                    <p>+2348029074091</p>
                    <p>Monday - Friday, 9am - 5pm WAT</p>
                </div>
                
                <div class="contact-method">
                    <div class="method-icon">📍</div>
                    <h3>Address</h3>
                    <p>123 Main Street</p>
                    <p>Port Harcourt, Rivers State</p>
                    <p>Nigeria</p>
                </div>
            </div>
            
            <div class="social-links">
                <h3>Follow Us</h3>
                <div class="social-icons">
                    <a href="#" target="_blank" class="social-icon">
                        <img src="<?php echo SITE_URL; ?>/assets/images/facebook.png" alt="Facebook">
                    </a>
                    <a href="#" target="_blank" class="social-icon">
                        <img src="<?php echo SITE_URL; ?>/assets/images/twitter.png" alt="Twitter">
                    </a>
                    <a href="#" target="_blank" class="social-icon">
                        <img src="<?php echo SITE_URL; ?>/assets/images/instagram.png" alt="Instagram">
                    </a>
                    <a href="#" target="_blank" class="social-icon">
                        <img src="<?php echo SITE_URL; ?>/assets/images/linkedin.png" alt="LinkedIn">
                    </a>
                </div>
            </div>
        </div>
        
        <div class="contact-form-container">
            <h2>Send Us a Message</h2>
            
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
                <form method="post" class="contact-form">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo isset($_POST['name']) ? $_POST['name'] : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" value="<?php echo isset($_POST['subject']) ? $_POST['subject'] : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" rows="5" required><?php echo isset($_POST['message']) ? $_POST['message'] : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
    
    <section class="map-section">
        <h2>Our Location</h2>
        <div class="map-container">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d63692.73650478064!2d6.970578335815407!3d4.8174004999999955!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1069cea39faef885%3A0x75334a78ef323634!2sPort%20Harcourt!5e0!3m2!1sen!2sng!4v1621445762335!5m2!1sen!2sng" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </section>
</main>


<script>
var WIDGET_ID = "450a6c5bead35a8c3648a923a33da5a5";
</script>
<script src="https://agileproject.site/widget/embed.js" async></script>

<?php include 'includes/footer.php'; ?>
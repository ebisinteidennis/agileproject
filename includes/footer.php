



<footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <a href="<?php echo SITE_URL; ?>">
                        <img src="<?php echo SITE_URL . '/' . $settings['site_logo']; ?>" alt="<?php echo $settings['site_name']; ?>">
                    </a>
                    <p><?php echo $settings['site_name']; ?> - Live Support Chat Solution</p>
                </div>
                
                <div class="footer-links">
                    <div class="footer-column">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="<?php echo SITE_URL; ?>">Home</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/about.php">About Us</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/contact.php">Contact Us</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h3>Account</h3>
                        <ul>
                            <?php if (isLoggedIn()): ?>
                                <li><a href="<?php echo SITE_URL; ?>/account/dashboard.php">Dashboard</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/account/profile.php">Profile</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/account/billing.php">Billing</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/account/logout.php">Logout</a></li>
                            <?php else: ?>
                                <li><a href="<?php echo SITE_URL; ?>/account/login.php">Login</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/account/register.php">Register</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h3>Resources</h3>
                        <ul>
                            <li><a href="<?php echo SITE_URL; ?>/docs/api.php">API Documentation</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/docs/integration.php">Integration Guide</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/docs/faq.php">FAQs</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="copyright">
                    &copy; <?php echo date('Y'); ?> <?php echo $settings['site_name']; ?>. All rights reserved.
                </div>
                <div class="payment-methods">
                    <img src="<?php echo SITE_URL; ?>/assets/images/paystack-sm.png" alt="Paystack">
                    <img src="<?php echo SITE_URL; ?>/assets/images/flutterwave-sm.png" alt="Flutterwave">
                    <img src="<?php echo SITE_URL; ?>/assets/images/moniepoint-sm.png" alt="Moniepoint">
                </div>
            </div>
        </div>
    </footer>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach($extraJs as $js): ?>
            <script src="<?php echo SITE_URL . $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
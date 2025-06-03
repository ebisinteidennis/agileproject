<?php
$pageTitle = 'Home';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$settings = getSiteSettings();

// Include header
include 'includes/header.php';
?>

<main>
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Live Support Chat for Your Website</h1>
                <p>Connect with your visitors in real-time and provide instant support</p>
                <div class="hero-buttons">
                    <?php if (isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/account/register.php" class="btn btn-primary">Get Started</a>
                        <a href="<?php echo SITE_URL; ?>/account/login.php" class="btn btn-secondary">Login</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-image">
                <img src="<?php echo SITE_URL; ?>/assets/images/chat-illustration.png" alt="Live Chat Illustration">
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <h2 class="section-title">Key Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>Real-time Chat</h3>
                    <p>Engage with your website visitors in real-time to answer questions and provide support.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💻</div>
                    <h3>Easy Integration</h3>
                    <p>Add our chat widget to your website with just a simple code snippet. No technical skills required.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Visitor Insights</h3>
                    <p>Get valuable insights about your visitors to provide personalized support.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Mobile Friendly</h3>
                    <p>Our chat widget works perfectly on all devices, including smartphones and tablets.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h3>Notifications</h3>
                    <p>Get notified when visitors send messages so you never miss an important inquiry.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3>Secure & Reliable</h3>
                    <p>Your conversations are secure and your data is protected with our reliable service.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Pricing Section -->
    <section class="pricing">
        <div class="container">
            <h2 class="section-title">Subscription Plans</h2>
            <p class="section-description">Choose the plan that fits your needs</p>
            
            <div class="pricing-grid">
                <?php 
                $subscriptions = getSubscriptionPlans();
                foreach($subscriptions as $subscription): 
                ?>
                    <div class="pricing-card">
                        <h3 class="plan-name"><?php echo $subscription['name']; ?></h3>
                        <div class="plan-price"><?php echo formatCurrency($subscription['price']); ?></div>
                        <div class="plan-duration"><?php echo $subscription['duration']; ?> days</div>
                        <ul class="plan-features">
                            <li><strong><?php echo number_format($subscription['message_limit']); ?></strong> messages</li>
                            <?php if (!empty($subscription['features'])): ?>
                                <?php 
                                $features = explode("\n", $subscription['features']);
                                foreach($features as $feature): 
                                ?>
                                    <li><?php echo $feature; ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <?php if (isLoggedIn()): ?>
                            <a href="<?php echo SITE_URL; ?>/account/payment.php?plan=<?php echo $subscription['id']; ?>" class="btn btn-primary">Subscribe</a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/account/register.php" class="btn btn-primary">Get Started</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="payment-methods">
                <h3>Payment Methods</h3>
                <div class="payment-logos">
                    <img src="<?php echo SITE_URL; ?>/assets/images/paystack.png" alt="Paystack">
                    <img src="<?php echo SITE_URL; ?>/assets/images/flutterwave.png" alt="Flutterwave">
                    <img src="<?php echo SITE_URL; ?>/assets/images/moniepoint.png" alt="Moniepoint">
                    <img src="<?php echo SITE_URL; ?>/assets/images/bank-transfer.png" alt="Bank Transfer">
                </div>
            </div>
        </div>
    </section>
    
    <!-- Testimonials Section -->
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">What Our Customers Say</h2>
            
            <div class="testimonial-slider">
                <div class="testimonial-item">
                    <div class="testimonial-content">
                        <p>"This live chat solution has helped us improve our customer service significantly. It's easy to use and our visitors love the instant support."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-name">John Doe</div>
                        <div class="author-company">E-commerce Store Owner</div>
                    </div>
                </div>
                
                <div class="testimonial-item">
                    <div class="testimonial-content">
                        <p>"The integration was super simple, and we started seeing results immediately. Our conversion rates have increased by 25% since adding the chat widget."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-name">Jane Smith</div>
                        <div class="author-company">Marketing Manager</div>
                    </div>
                </div>
                
                <div class="testimonial-item">
                    <div class="testimonial-content">
                        <p>"I highly recommend this service. The subscription plans are affordable, and the features are exactly what our business needed for customer support."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-name">Michael Johnson</div>
                        <div class="author-company">Startup Founder</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to Action -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Enhance Your Website with Live Support?</h2>
            <p>Join thousands of businesses that trust our platform for their customer support needs.</p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/account/register.php" class="btn btn-primary btn-lg">Get Started Today</a>
            <?php endif; ?>
        </div>
    </section>
</main>


<script>
var WIDGET_ID = "450a6c5bead35a8c3648a923a33da5a5";
</script>
<script src="https://agileproject.site/widget/embed.js" async></script>

 <script src="diagnostic-embed.js"></script>

<?php include 'includes/footer.php'; ?>
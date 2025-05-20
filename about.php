<?php
$pageTitle = 'About Us';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$settings = getSiteSettings();

// Include header
include 'includes/header.php';
?>

<main class="container about-page">
    <section class="page-header">
        <h1>About Us</h1>
    </section>
    
    <section class="about-content">
        <div class="about-image">
            <img src="<?php echo SITE_URL; ?>/assets/images/about-us.jpg" alt="About <?php echo $settings['site_name']; ?>">
        </div>
        
        <div class="about-text">
            <h2>Our Mission</h2>
            <p>At <?php echo $settings['site_name']; ?>, we're dedicated to helping businesses connect with their website visitors in real-time. Our mission is to provide a simple, affordable, and effective live chat solution that enhances customer experience and drives business growth.</p>
            
            <h2>Our Story</h2>
            <p>Founded in 2025, <?php echo $settings['site_name']; ?> was born out of the need for a more accessible live support solution for small and medium-sized businesses. We noticed that most existing solutions were either too complex or too expensive for many businesses, so we set out to create a platform that combines simplicity with powerful features at an affordable price.</p>
            
            <h2>Why Choose Us?</h2>
            <ul class="feature-list">
                <li><strong>Easy to Use:</strong> Our platform is designed to be intuitive and user-friendly, requiring no technical expertise.</li>
                <li><strong>Affordable:</strong> We offer flexible subscription plans to fit businesses of all sizes.</li>
                <li><strong>Reliable:</strong> Our system is built on a robust infrastructure to ensure high availability and performance.</li>
                <li><strong>Local Payment Options:</strong> We support Nigerian payment gateways including Paystack, Flutterwave, and Moniepoint.</li>
                <li><strong>Excellent Support:</strong> Our team is always ready to assist you with any questions or issues.</li>
            </ul>
            
            <h2>Our Team</h2>
            <p>We are a team of passionate professionals with a background in web development, customer service, and business operations. Together, we work tirelessly to improve our platform and provide the best possible experience for our users.</p>
        </div>
    </section>
    
    <section class="team-section">
        <h2>Meet Our Team</h2>
        <div class="team-grid">
            <div class="team-member">
                <div class="member-photo">
                    <img src="<?php echo SITE_URL; ?>/assets/images/team-1.jpg" alt="Team Member">
                </div>
                <h3>John Doe</h3>
                <p class="member-role">Founder & CEO</p>
                <p class="member-bio">John has over 10 years of experience in software development and customer service. He is passionate about creating tools that help businesses grow.</p>
            </div>
            
            <div class="team-member">
                <div class="member-photo">
                    <img src="<?php echo SITE_URL; ?>/assets/images/team-2.jpg" alt="Team Member">
                </div>
                <h3>Jane Smith</h3>
                <p class="member-role">CTO</p>
                <p class="member-bio">Jane leads our technical team and ensures that our platform is secure, fast, and reliable. She has a background in cloud infrastructure and web applications.</p>
            </div>
            
            <div class="team-member">
                <div class="member-photo">
                    <img src="<?php echo SITE_URL; ?>/assets/images/team-3.jpg" alt="Team Member">
                </div>
                <h3>Michael Johnson</h3>
                <p class="member-role">Head of Customer Success</p>
                <p class="member-bio">Michael is dedicated to ensuring that our customers get the most out of our platform. He has extensive experience in customer support and success management.</p>
            </div>
        </div>
    </section>
    
    <section class="cta-section">
        <h2>Ready to enhance your customer support?</h2>
        <p>Join thousands of businesses that use <?php echo $settings['site_name']; ?> to connect with their website visitors.</p>
        <?php if (isLoggedIn()): ?>
            <a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        <?php else: ?>
            <a href="<?php echo SITE_URL; ?>/account/register.php" class="btn btn-primary">Get Started</a>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
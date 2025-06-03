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

<style>
/* About Page Styles with Mobile Responsiveness */
.about-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 50px;
    position: relative;
}

.page-header h1 {
    font-size: 2.5rem;
    color: #333;
    margin: 0;
    position: relative;
    display: inline-block;
}

.page-header h1:after {
    content: '';
    position: absolute;
    bottom: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 3px;
    background-color: #3b82f6;
}

/* About Section */
.about-section {
    display: flex;
    flex-wrap: wrap;
    gap: 40px;
    margin-bottom: 60px;
    align-items: center;
}

.about-image-container {
    flex: 1;
    min-width: 300px;
}

.about-image {
    width: 100%;
    height: auto;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.about-image:hover {
    transform: scale(1.02);
}

.about-content {
    flex: 1.5;
    min-width: 300px;
}

.section-title {
    font-size: 1.8rem;
    color: #333;
    margin-bottom: 20px;
    position: relative;
    padding-bottom: 15px;
}

.section-title:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background-color: #3b82f6;
}

.about-text {
    font-size: 1.05rem;
    line-height: 1.7;
    color: #4b5563;
    margin-bottom: 25px;
}

/* Feature List */
.feature-list {
    margin: 25px 0;
    padding: 0;
    list-style-type: none;
}

.feature-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.feature-item:last-child {
    border-bottom: none;
}

.feature-icon {
    background-color: #ebf5ff;
    color: #3b82f6;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.feature-content {
    flex: 1;
}

.feature-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.feature-description {
    color: #4b5563;
    font-size: 0.95rem;
    line-height: 1.5;
}

/* Team Section */
.team-section {
    margin-bottom: 60px;
}

.team-header {
    text-align: center;
    margin-bottom: 40px;
}

.team-title {
    font-size: 2rem;
    color: #333;
    margin-bottom: 15px;
}

.team-subtitle {
    font-size: 1.1rem;
    color: #6b7280;
    max-width: 600px;
    margin: 0 auto;
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
}

.team-card {
    background-color: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.team-image-container {
    position: relative;
    overflow: hidden;
    height: 250px;
}

.team-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.team-card:hover .team-image {
    transform: scale(1.05);
}

.team-info {
    padding: 20px;
    text-align: center;
}

.team-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.team-role {
    color: #3b82f6;
    font-weight: 500;
    margin-bottom: 15px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.team-bio {
    color: #4b5563;
    font-size: 0.95rem;
    line-height: 1.6;
}

.team-social {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 15px;
}

.social-link {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #f3f4f6;
    color: #4b5563;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.social-link:hover {
    background-color: #3b82f6;
    color: white;
}

/* Mission Section */
.mission-section {
    background-color: #f9fafb;
    padding: 60px 30px;
    border-radius: 10px;
    margin-bottom: 60px;
    text-align: center;
}

.mission-title {
    font-size: 2rem;
    color: #333;
    margin-bottom: 20px;
}

.mission-text {
    font-size: 1.2rem;
    line-height: 1.7;
    color: #4b5563;
    max-width: 800px;
    margin: 0 auto 30px;
}

.mission-stats {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
}

.stat-item {
    flex: 1;
    min-width: 200px;
    max-width: 250px;
    padding: 20px;
    text-align: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #3b82f6;
    margin-bottom: 10px;
}

.stat-label {
    font-size: 1rem;
    color: #4b5563;
}

/* CTA Section */
.cta-section {
    background-color: #ebf5ff;
    padding: 60px 30px;
    border-radius: 10px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.cta-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0.05;
    background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSIjMDAwIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0zNiAxOGMwLTkuOTQtOC4wNi0xOC0xOC0xOFYyYzcuNzMyIDAgMTQgNi4yNjggMTQgMTRoNHptLTIyIDQyaDRjMC05Ljk0LTguMDYtMTgtMTgtMTh2NGM3LjczMiAwIDE0IDYuMjY4IDE0IDE0em00Mi0xNGMwIDcuNzMyLTYuMjY4IDE0LTE0IDE0djRjOS45NCAwIDE4LTguMDYgMTgtMThoLTR6bS0xNC00NnY0YzcuNzMyIDAgMTQgNi4yNjggMTQgMTRoNGMwLTkuOTQtOC4wNi0xOC0xOC0xOHoiLz48L2c+PC9zdmc+');
    z-index: 0;
}

.cta-content {
    position: relative;
    z-index: 1;
}

.cta-title {
    font-size: 2rem;
    color: #1a202c;
    margin-bottom: 15px;
}

.cta-text {
    font-size: 1.1rem;
    color: #4b5563;
    margin-bottom: 30px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.cta-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background-color: #2563eb;
    transform: translateY(-2px);
}

.btn-secondary {
    background-color: white;
    color: #3b82f6;
    border: 1px solid #3b82f6;
}

.btn-secondary:hover {
    background-color: #f0f7ff;
    transform: translateY(-2px);
}

/* Media Queries for Responsiveness */
@media (max-width: 992px) {
    .about-section {
        flex-direction: column;
    }
    
    .about-image-container {
        width: 100%;
        margin-bottom: 30px;
    }
    
    .team-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
    
    .mission-stats {
        gap: 20px;
    }
    
    .stat-item {
        min-width: 150px;
    }
}

@media (max-width: 768px) {
    .page-header h1 {
        font-size: 2rem;
    }
    
    .section-title {
        font-size: 1.5rem;
    }
    
    .about-text {
        font-size: 1rem;
    }
    
    .team-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
    
    .team-image-container {
        height: 220px;
    }
    
    .mission-title, .cta-title {
        font-size: 1.8rem;
    }
    
    .mission-text, .cta-text {
        font-size: 1rem;
    }
    
    .stat-value {
        font-size: 2rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .about-container {
        padding: 30px 15px;
    }
    
    .page-header h1 {
        font-size: 1.8rem;
    }
    
    .page-header h1:after {
        width: 60px;
    }
    
    .team-grid {
        grid-template-columns: 1fr;
        max-width: 320px;
        margin: 0 auto;
    }
    
    .mission-section, .cta-section {
        padding: 40px 20px;
    }
    
    .mission-stats {
        gap: 15px;
    }
    
    .stat-item {
        min-width: 130px;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .cta-buttons {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<main class="about-container">
    <section class="page-header">
        <h1>About Us</h1>
    </section>
    
    <section class="mission-section">
        <div class="mission-content">
            <h2 class="mission-title">Our Mission</h2>
            <p class="mission-text">At <?php echo $settings['site_name']; ?>, we're dedicated to helping businesses connect with their website visitors in real-time. Our goal is to provide a simple, affordable, and effective live chat solution that enhances customer experience and drives business growth.</p>
            
            <div class="mission-stats">
                <div class="stat-item">
                    <div class="stat-value">1000+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">10M+</div>
                    <div class="stat-label">Chats Served</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">99.9%</div>
                    <div class="stat-label">Uptime</div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="about-section">
        <div class="about-image-container">
            <img src="<?php echo SITE_URL; ?>/assets/images/chat-illustration.png" alt="About <?php echo $settings['site_name']; ?>" class="about-image">
        </div>
        
        <div class="about-content">
            <h2 class="section-title">Our Story</h2>
            <p class="about-text">Founded in 2025, <?php echo $settings['site_name']; ?> was born out of the need for a more accessible live support solution for small and medium-sized businesses. We noticed that most existing solutions were either too complex or too expensive for many businesses, so we set out to create a platform that combines simplicity with powerful features at an affordable price.</p>
            
            <p class="about-text">Today, we serve thousands of businesses around the world, helping them provide exceptional customer support and increase their conversion rates. Our team continues to innovate and improve our platform based on user feedback and industry trends.</p>
        </div>
    </section>
    
    <section class="about-section">
        <div class="about-content">
            <h2 class="section-title">Why Choose Us?</h2>
            <p class="about-text">We understand that there are many live chat solutions available in the market. Here's why businesses choose us:</p>
            
            <ul class="feature-list">
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="feature-content">
                        <div class="feature-title">Easy to Use</div>
                        <div class="feature-description">Our platform is designed to be intuitive and user-friendly, requiring no technical expertise to set up and use.</div>
                    </div>
                </li>
                
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="feature-content">
                        <div class="feature-title">Affordable</div>
                        <div class="feature-description">We offer flexible subscription plans to fit businesses of all sizes, with transparent pricing and no hidden fees.</div>
                    </div>
                </li>
                
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="feature-content">
                        <div class="feature-title">Reliable</div>
                        <div class="feature-description">Our system is built on a robust infrastructure to ensure high availability and performance, even during peak traffic times.</div>
                    </div>
                </li>
                
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="feature-content">
                        <div class="feature-title">Local Payment Options</div>
                        <div class="feature-description">We support Nigerian payment gateways including Paystack, Flutterwave, and Moniepoint for hassle-free transactions.</div>
                    </div>
                </li>
                
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="feature-content">
                        <div class="feature-title">Excellent Support</div>
                        <div class="feature-description">Our team is always ready to assist you with any questions or issues, ensuring you get the most out of our platform.</div>
                    </div>
                </li>
            </ul>
        </div>
    </section>
    
    <!--<section class="team-section">-->
    <!--    <div class="team-header">-->
    <!--        <h2 class="team-title">Meet Our Team</h2>-->
    <!--        <p class="team-subtitle">We are a team of passionate professionals with a background in web development, customer service, and business operations.</p>-->
    <!--    </div>-->
        
    <!--    <div class="team-grid">-->
    <!--        <div class="team-card">-->
    <!--            <div class="team-image-container">-->
    <!--                <img src="<?php echo SITE_URL; ?>/assets/images/team-1.jpg" alt="John Doe" class="team-image">-->
    <!--            </div>-->
    <!--            <div class="team-info">-->
    <!--                <h3 class="team-name">John Doe</h3>-->
    <!--                <p class="team-role">Founder & CEO</p>-->
    <!--                <p class="team-bio">John has over 10 years of experience in software development and customer service. He is passionate about creating tools that help businesses grow.</p>-->
    <!--                <div class="team-social">-->
    <!--                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>-->
    <!--                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>-->
    <!--                </div>-->
    <!--            </div>-->
    <!--        </div>-->
            
    <!--        <div class="team-card">-->
    <!--            <div class="team-image-container">-->
    <!--                <img src="<?php echo SITE_URL; ?>/assets/images/team-2.jpg" alt="Jane Smith" class="team-image">-->
    <!--            </div>-->
    <!--            <div class="team-info">-->
    <!--                <h3 class="team-name">Jane Smith</h3>-->
    <!--                <p class="team-role">CTO</p>-->
    <!--                <p class="team-bio">Jane leads our technical team and ensures that our platform is secure, fast, and reliable. She has a background in cloud infrastructure and web applications.</p>-->
    <!--                <div class="team-social">-->
    <!--                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>-->
    <!--                    <a href="#" class="social-link"><i class="fab fa-github"></i></a>-->
    <!--                </div>-->
    <!--            </div>-->
    <!--        </div>-->
            
    <!--        <div class="team-card">-->
    <!--            <div class="team-image-container">-->
    <!--                <img src="<?php echo SITE_URL; ?>/assets/images/team-3.jpg" alt="Michael Johnson" class="team-image">-->
    <!--            </div>-->
    <!--            <div class="team-info">-->
    <!--                <h3 class="team-name">Michael Johnson</h3>-->
    <!--                <p class="team-role">Head of Customer Success</p>-->
    <!--                <p class="team-bio">Michael is dedicated to ensuring that our customers get the most out of our platform. He has extensive experience in customer support and success management.</p>-->
    <!--                <div class="team-social">-->
    <!--                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>-->
    <!--                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>-->
    <!--                </div>-->
    <!--            </div>-->
    <!--        </div>-->
    <!--    </div>-->
    <!--</section>-->
    
    <section class="cta-section">
        <div class="cta-bg"></div>
        <div class="cta-content">
            <h2 class="cta-title">Ready to enhance your customer support?</h2>
            <p class="cta-text">Join thousands of businesses that use <?php echo $settings['site_name']; ?> to connect with their website visitors and increase conversions.</p>
            
            <div class="cta-buttons">
                <?php if (isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                    <a href="<?php echo SITE_URL; ?>/pricing.php" class="btn btn-secondary">View Plans</a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/account/register.php" class="btn btn-primary">Get Started</a>
                    <a href="<?php echo SITE_URL; ?>/pricing.php" class="btn btn-secondary">View Plans</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>


<script>
var WIDGET_ID = "450a6c5bead35a8c3648a923a33da5a5";
</script>
<script src="https://agileproject.site/widget/embed.js" async></script>

<?php include 'includes/footer.php'; ?>
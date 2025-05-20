<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if admin already exists
$adminExists = $db->fetch("SELECT COUNT(*) as count FROM users WHERE id = 1")['count'] > 0;

if (!$adminExists) {
    // Create tables if they don't exist
    
    // Users table
    $db->query("
        CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `email` varchar(100) NOT NULL,
          `password` varchar(255) NOT NULL,
          `api_key` varchar(64) NOT NULL,
          `widget_id` varchar(32) NOT NULL,
          `subscription_id` int(11) DEFAULT NULL,
          `subscription_status` enum('active','inactive','expired') DEFAULT 'inactive',
          `subscription_expiry` date DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`),
          UNIQUE KEY `api_key` (`api_key`),
          UNIQUE KEY `widget_id` (`widget_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Subscriptions table
    $db->query("
        CREATE TABLE IF NOT EXISTS `subscriptions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `price` decimal(10,2) NOT NULL,
          `duration` int(11) NOT NULL COMMENT 'in days',
          `message_limit` int(11) NOT NULL,
          `features` text,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Messages table
    $db->query("
        CREATE TABLE IF NOT EXISTS `messages` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `visitor_id` varchar(64) NOT NULL,
          `message` text NOT NULL,
          `sender_type` enum('visitor','agent') NOT NULL,
          `read` tinyint(1) NOT NULL DEFAULT '0',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `visitor_id` (`visitor_id`),
          CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Visitors table
    $db->query("
        CREATE TABLE IF NOT EXISTS `visitors` (
          `id` varchar(64) NOT NULL,
          `user_id` int(11) NOT NULL,
          `name` varchar(100) DEFAULT NULL,
          `email` varchar(100) DEFAULT NULL,
          `url` varchar(255) DEFAULT NULL,
          `ip_address` varchar(45) DEFAULT NULL,
          `user_agent` text,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `last_active` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          CONSTRAINT `visitors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Settings table
    $db->query("
        CREATE TABLE IF NOT EXISTS `settings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `site_name` varchar(100) NOT NULL DEFAULT 'Live Support',
          `site_logo` varchar(255) DEFAULT NULL,
          `admin_email` varchar(100) NOT NULL,
          `smtp_host` varchar(100) DEFAULT NULL,
          `smtp_port` int(11) DEFAULT NULL,
          `smtp_user` varchar(100) DEFAULT NULL,
          `smtp_pass` varchar(255) DEFAULT NULL,
          `currency` varchar(10) NOT NULL DEFAULT 'NGN',
          `paystack_public_key` varchar(255) DEFAULT NULL,
          `paystack_secret_key` varchar(255) DEFAULT NULL,
          `flutterwave_public_key` varchar(255) DEFAULT NULL,
          `flutterwave_secret_key` varchar(255) DEFAULT NULL,
          `moniepoint_api_key` varchar(255) DEFAULT NULL,
          `moniepoint_merchant_id` varchar(255) DEFAULT NULL,
          `manual_payment_instructions` text DEFAULT NULL,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Payments table
    $db->query("
        CREATE TABLE IF NOT EXISTS `payments` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `subscription_id` int(11) NOT NULL,
          `payment_method` enum('paystack', 'flutterwave', 'moniepoint', 'manual') NOT NULL,
          `transaction_reference` varchar(255) DEFAULT NULL,
          `amount` decimal(10,2) NOT NULL,
          `status` enum('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
          `payment_proof` varchar(255) DEFAULT NULL COMMENT 'For manual payments',
          `admin_notes` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `subscription_id` (`subscription_id`),
          CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Set up default admin account
    $adminName = 'Admin';
    $adminEmail = 'admin@example.com';
    $adminPassword = 'admin123'; // Should be changed immediately after first login
    
    // Hash password
    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    // Generate API key and Widget ID
    $apiKey = generateApiKey();
    $widgetId = generateWidgetId();
    
    // Insert admin user
    $userData = [
        'name' => $adminName,
        'email' => $adminEmail,
        'password' => $hashedPassword,
        'api_key' => $apiKey,
        'widget_id' => $widgetId,
        'subscription_status' => 'active',
        'subscription_expiry' => date('Y-m-d', strtotime('+10 years')) // Set a far future date for admin
    ];
    
    $db->insert('users', $userData);
    
    // Create default site settings
    $settingsData = [
        'site_name' => 'Live Support',
        'site_logo' => 'assets/images/logo.png',
        'admin_email' => $adminEmail,
        'currency' => 'NGN',
        'manual_payment_instructions' => 'Please make a bank transfer to the following account:

Account Name: Your Company Name
Bank: Your Bank
Account Number: 0123456789
Sort Code: 123456

After making the payment, please upload a screenshot or photo of your payment receipt as proof of payment.'
    ];
    
    $db->insert('settings', $settingsData);
    
    // Create sample subscription plans
    $subscriptionPlans = [
        [
            'name' => 'Basic',
            'price' => 5000.00,
            'duration' => 30,
            'message_limit' => 1000,
            'features' => "Basic chat widget\nEmail notifications\n1,000 monthly messages\n24/7 chat availability"
        ],
        [
            'name' => 'Standard',
            'price' => 10000.00,
            'duration' => 30,
            'message_limit' => 5000,
            'features' => "Advanced chat widget\nEmail & SMS notifications\n5,000 monthly messages\nVisitor tracking\nFile sharing"
        ],
        [
            'name' => 'Premium',
            'price' => 20000.00,
            'duration' => 30,
            'message_limit' => 20000,
            'features' => "Premium chat widget\nPriority support\n20,000 monthly messages\nAdvanced analytics\nMultiple agents\nAPI access"
        ]
    ];
    
    foreach ($subscriptionPlans as $plan) {
        $db->insert('subscriptions', $plan);
    }
    
    echo "Installation completed successfully!<br>";
    echo "Admin account created:<br>";
    echo "Email: " . $adminEmail . "<br>";
    echo "Password: " . $adminPassword . "<br>";
    echo "<strong>Please change your password after first login!</strong><br>";
    echo "<a href='admin/index.php'>Go to Admin Dashboard</a>";
} else {
    echo "Installation already completed. Admin user already exists.<br>";
    echo "<a href='admin/index.php'>Go to Admin Dashboard</a>";
}
?>
<?php
require_once 'db.php';

function generateApiKey() {
    return bin2hex(random_bytes(32));
}

function generateWidgetId() {
    return bin2hex(random_bytes(16));
}

function generateReference() {
    return 'LVS-' . strtoupper(bin2hex(random_bytes(8)));
}

function getSiteSettings() {
    global $db;
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");
    
    if (!$settings) {
        // Create default settings if not exists
        $defaultSettings = [
            'site_name' => 'Live Support',
            'site_logo' => 'assets/images/logo.png',
            'admin_email' => ADMIN_EMAIL,
            'currency' => 'NGN',
            'manual_payment_instructions' => 'Please make payment to our bank account and upload proof of payment.'
        ];
        
        $db->insert('settings', $defaultSettings);
        $settings = $defaultSettings;
    }
    
    return $settings;
}

function getSubscriptionPlans() {
    global $db;
    return $db->fetchAll("SELECT * FROM subscriptions ORDER BY price ASC");
}

function getUserById($id) {
    global $db;
    return $db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
}

function getUserByEmail($email) {
    global $db;
    return $db->fetch("SELECT * FROM users WHERE email = :email", ['email' => $email]);
}

function getUserByApiKey($apiKey) {
    global $db;
    return $db->fetch("SELECT * FROM users WHERE api_key = :api_key", ['api_key' => $apiKey]);
}

function getUserByWidgetId($widgetId) {
    global $db;
    return $db->fetch("SELECT * FROM users WHERE widget_id = :widget_id", ['widget_id' => $widgetId]);
}

function getSubscriptionById($id) {
    global $db;
    return $db->fetch("SELECT * FROM subscriptions WHERE id = :id", ['id' => $id]);
}

function getPaymentById($id) {
    global $db;
    return $db->fetch("SELECT * FROM payments WHERE id = :id", ['id' => $id]);
}

function getUserPayments($userId) {
    global $db;
    return $db->fetchAll(
        "SELECT p.*, s.name as subscription_name 
         FROM payments p 
         JOIN subscriptions s ON p.subscription_id = s.id 
         WHERE p.user_id = :user_id 
         ORDER BY p.created_at DESC",
        ['user_id' => $userId]
    );
}

function getMessageCount($userId) {
    global $db;
    $result = $db->fetch(
        "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id", 
        ['user_id' => $userId]
    );
    return $result['count'];
}

function isSubscriptionActive($user) {
    // Check if user array and required fields exist
    if (!is_array($user) || 
        !isset($user['subscription_status']) || 
        !isset($user['subscription_expiry']) ||
        empty($user['subscription_expiry'])) {
        return false;
    }
    
    return $user['subscription_status'] === 'active' && 
           strtotime($user['subscription_expiry']) > time();
}

function canSendMessage($userId) {
    global $db;
    
    $user = getUserById($userId);
    
    if (!isSubscriptionActive($user)) {
        return false;
    }
    
    $subscription = getSubscriptionById($user['subscription_id']);
    $messageCount = getMessageCount($userId);
    
    return $messageCount < $subscription['message_limit'];
}

function formatCurrency($amount) {
    $settings = getSiteSettings();
    $currency = $settings['currency'] ?? 'NGN';
    
    if ($currency === 'NGN') {
        return '₦' . number_format($amount, 2);
    }
    
    return $currency . ' ' . number_format($amount, 2);
}

function uploadFile($file, $directory = 'uploads', $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']) {
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    // Check if file is valid
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }
    
    // Check file type
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)];
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $destination = $directory . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $destination];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file'];
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function formatDate($date) {
    // Handle null or empty date values
    if (empty($date)) {
        return 'N/A';
    }
    return date("F j, Y", strtotime($date));
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
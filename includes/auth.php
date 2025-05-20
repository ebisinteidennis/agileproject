<?php
require_once 'db.php';
require_once 'functions.php';

function registerUser($name, $email, $password) {
    global $db;
    
    // Check if email already exists
    $existingUser = getUserByEmail($email);
    if ($existingUser) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate API key and Widget ID
    $apiKey = generateApiKey();
    $widgetId = generateWidgetId();
    
    // Create user
    $userData = [
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'api_key' => $apiKey,
        'widget_id' => $widgetId,
        'subscription_status' => 'inactive'
    ];
    
    $userId = $db->insert('users', $userData);
    
    if ($userId) {
        return [
            'success' => true, 
            'user_id' => $userId,
            'message' => 'Registration successful. Please log in.'
        ];
    } else {
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

function loginUser($email, $password) {
    global $db;
    
    $user = getUserByEmail($email);
    
    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    
    return ['success' => true, 'message' => 'Login successful'];
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/account/login.php');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        redirect(SITE_URL);
    }
}

function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    redirect(SITE_URL . '/account/login.php');
}
?>
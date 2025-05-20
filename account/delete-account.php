<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Verify password if provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'];
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Delete account and all associated data
        // This will cascade delete due to foreign key constraints
        $db->delete('users', 'id = :id', ['id' => $userId]);
        
        // Log out the user
        session_destroy();
        
        // Redirect to homepage with message
        header('Location: ' . SITE_URL . '?message=account_deleted');
        exit;
    } else {
        // Incorrect password, redirect back to profile
        $_SESSION['message'] = 'Incorrect password. Account deletion cancelled.';
        $_SESSION['message_type'] = 'error';
        redirect(SITE_URL . '/account/profile.php');
    }
} else {
    // Redirect if accessed directly
    redirect(SITE_URL . '/account/profile.php');
}
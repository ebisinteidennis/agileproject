<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$userId = $_SESSION['user_id'];

// Generate new API key
$newApiKey = generateApiKey();

// Update API key in database
$db->update(
    'users',
    ['api_key' => $newApiKey],
    'id = :id',
    ['id' => $userId]
);

// Set success message in session
$_SESSION['message'] = 'API key regenerated successfully.';
$_SESSION['message_type'] = 'success';

// Redirect to profile page
redirect(SITE_URL . '/account/profile.php');
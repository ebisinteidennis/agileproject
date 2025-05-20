<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Log out the user
logoutUser();

// Redirect to homepage
redirect(SITE_URL);
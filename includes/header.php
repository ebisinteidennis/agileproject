<?php
// Determine if we're in a subdirectory to adjust include paths
$currentScript = $_SERVER['SCRIPT_FILENAME'];
$rootPath = '';

if (strpos($currentScript, '/account/') !== false || 
    strpos($currentScript, '/admin/') !== false || 
    strpos($currentScript, '/payments/') !== false ||
    strpos($currentScript, '/widget/') !== false) {
    $rootPath = '../';
}

require_once $rootPath . 'includes/config.php';
require_once $rootPath . 'includes/functions.php';
require_once $rootPath . 'includes/auth.php';

$settings = getSiteSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? $settings['site_name']; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <?php if (isset($extraCss)): ?>
        <?php foreach($extraCss as $css): ?>
            <link rel="stylesheet" href="<?php echo SITE_URL . $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>">
                    <img src="<?php echo SITE_URL . '/' . $settings['site_logo']; ?>" alt="<?php echo $settings['site_name']; ?>">
                </a>
            </div>
            <div class="mobile-menu-toggle" id="mobile-menu-toggle">
                <span>☰</span>
            </div>
            <nav id="main-nav">
                <ul>
                    <li><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/about.php">About</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/contact.php">Contact</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?php echo SITE_URL; ?>/account/dashboard.php">Dashboard</a></li>
                        <?php if (isAdmin()): ?>
                            <li><a href="<?php echo SITE_URL; ?>/admin">Admin</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo SITE_URL; ?>/account/profile.php">Profile</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/account/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo SITE_URL; ?>/account/login.php">Login</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/account/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
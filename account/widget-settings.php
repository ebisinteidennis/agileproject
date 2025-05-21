<?php
$pageTitle = 'Widget Settings';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require user to be logged in
requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Check subscription status
if (!isSubscriptionActive($user)) {
    $_SESSION['message'] = 'Your subscription is inactive. Please subscribe to access widget settings.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/account/billing.php');
}

// Get widget ID
$widgetId = $user['widget_id'] ?? null;

// Check if the settings table exists
$hasWidgetSettingsTable = false;
try {
    $tablesResult = $db->query("SHOW TABLES LIKE 'widget_settings'");
    $hasWidgetSettingsTable = $tablesResult->rowCount() > 0;
} catch (Exception $e) {
    error_log("Error checking for widget_settings table: " . $e->getMessage());
}

// Create the table if it doesn't exist
if (!$hasWidgetSettingsTable) {
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS `widget_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `widget_id` varchar(64) NOT NULL,
                `theme_color` varchar(20) NOT NULL DEFAULT '#3498db',
                `text_color` varchar(20) NOT NULL DEFAULT '#ffffff',
                `position` enum('bottom_right','bottom_left','top_right','top_left') NOT NULL DEFAULT 'bottom_right',
                `welcome_message` text DEFAULT NULL,
                `offline_message` text DEFAULT NULL,
                `display_name` varchar(100) DEFAULT NULL,
                `logo_url` varchar(255) DEFAULT NULL,
                `custom_css` text DEFAULT NULL,
                `mobile_enabled` tinyint(1) NOT NULL DEFAULT 1,
                `show_branding` tinyint(1) NOT NULL DEFAULT 1,
                `auto_popup` tinyint(1) NOT NULL DEFAULT 0,
                `auto_popup_delay` int(11) DEFAULT 10,
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_widget` (`user_id`,`widget_id`),
                CONSTRAINT `widget_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        $hasWidgetSettingsTable = true;
    } catch (Exception $e) {
        error_log("Error creating widget_settings table: " . $e->getMessage());
    }
}

// Get current widget settings
$settings = null;
if ($hasWidgetSettingsTable && $widgetId) {
    try {
        $settings = $db->fetch(
            "SELECT * FROM widget_settings WHERE user_id = :user_id AND widget_id = :widget_id",
            ['user_id' => $userId, 'widget_id' => $widgetId]
        );
    } catch (Exception $e) {
        error_log("Error fetching widget settings: " . $e->getMessage());
    }
}

// If no settings exist yet, create default settings
if (!$settings && $hasWidgetSettingsTable && $widgetId) {
    try {
        $defaultSettings = [
            'user_id' => $userId,
            'widget_id' => $widgetId,
            'theme_color' => '#3498db',
            'text_color' => '#ffffff',
            'position' => 'bottom_right',
            'welcome_message' => 'Hello! How can we help you today?',
            'offline_message' => 'Sorry, we\'re currently offline. Please leave a message and we\'ll get back to you soon.',
            'display_name' => $user['name'] ?? 'Customer Support',
            'mobile_enabled' => 1,
            'show_branding' => 1,
            'auto_popup' => 0,
            'auto_popup_delay' => 10
        ];
        
        $db->insert('widget_settings', $defaultSettings);
        
        $settings = $defaultSettings;
    } catch (Exception $e) {
        error_log("Error creating default widget settings: " . $e->getMessage());
    }
}

// Process form submission
$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasWidgetSettingsTable && $widgetId) {
    try {
        // Validate inputs
        $themeColor = filter_input(INPUT_POST, 'theme_color', FILTER_SANITIZE_STRING) ?: '#3498db';
        $textColor = filter_input(INPUT_POST, 'text_color', FILTER_SANITIZE_STRING) ?: '#ffffff';
        $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING) ?: 'bottom_right';
        $welcomeMessage = filter_input(INPUT_POST, 'welcome_message', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES) ?: 'Hello! How can we help you today?';
        $offlineMessage = filter_input(INPUT_POST, 'offline_message', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES) ?: 'Sorry, we\'re currently offline. Please leave a message and we\'ll get back to you soon.';
        $displayName = filter_input(INPUT_POST, 'display_name', FILTER_SANITIZE_STRING) ?: 'Customer Support';
        $mobileEnabled = isset($_POST['mobile_enabled']) ? 1 : 0;
        $showBranding = isset($_POST['show_branding']) ? 1 : 0;
        $autoPopup = isset($_POST['auto_popup']) ? 1 : 0;
        $autoPopupDelay = (int)($_POST['auto_popup_delay'] ?? 10);
        $customCSS = filter_input(INPUT_POST, 'custom_css', FILTER_UNSAFE_RAW) ?: null;
        
        // Validate position
        $validPositions = ['bottom_right', 'bottom_left', 'top_right', 'top_left'];
        if (!in_array($position, $validPositions)) {
            $position = 'bottom_right';
        }
        
        // Validate colors (basic validation)
        if (!preg_match('/^#[a-f0-9]{6}$/i', $themeColor)) {
            $themeColor = '#3498db';
        }
        if (!preg_match('/^#[a-f0-9]{6}$/i', $textColor)) {
            $textColor = '#ffffff';
        }
        
        // Validate auto popup delay
        if ($autoPopupDelay < 0 || $autoPopupDelay > 60) {
            $autoPopupDelay = 10;
        }
        
        // Prepare update data
        $updateData = [
            'theme_color' => $themeColor,
            'text_color' => $textColor,
            'position' => $position,
            'welcome_message' => $welcomeMessage,
            'offline_message' => $offlineMessage,
            'display_name' => $displayName,
            'mobile_enabled' => $mobileEnabled,
            'show_branding' => $showBranding,
            'auto_popup' => $autoPopup,
            'auto_popup_delay' => $autoPopupDelay,
            'custom_css' => $customCSS
        ];
        
        // Handle logo upload if present
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoName = $_FILES['logo']['name'];
            $logoTmpName = $_FILES['logo']['tmp_name'];
            $logoSize = $_FILES['logo']['size'];
            $logoType = $_FILES['logo']['type'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml'];
            if (!in_array($logoType, $allowedTypes)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and SVG files are allowed.');
            }
            
            // Validate file size (max 2MB)
            if ($logoSize > 2 * 1024 * 1024) {
                throw new Exception('File size too large. Maximum file size is 2MB.');
            }
            
            // Generate unique filename
            $logoExt = pathinfo($logoName, PATHINFO_EXTENSION);
            $newLogoName = uniqid('widget_logo_') . '.' . $logoExt;
            $uploadDir = '../uploads/widget_logos/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $logoPath = $uploadDir . $newLogoName;
            
            // Move uploaded file
            if (move_uploaded_file($logoTmpName, $logoPath)) {
                // Remove old logo if exists
                if (!empty($settings['logo_url']) && file_exists('../' . $settings['logo_url'])) {
                    unlink('../' . $settings['logo_url']);
                }
                
                $updateData['logo_url'] = 'uploads/widget_logos/' . $newLogoName;
            } else {
                throw new Exception('Failed to upload logo. Please try again.');
            }
        } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] == 1) {
            // Remove logo if requested
            if (!empty($settings['logo_url']) && file_exists('../' . $settings['logo_url'])) {
                unlink('../' . $settings['logo_url']);
            }
            $updateData['logo_url'] = null;
        }
        
        // Check if settings already exist
        if ($settings) {
            // Update existing settings
            $db->update(
                'widget_settings',
                $updateData,
                ['user_id' => $userId, 'widget_id' => $widgetId]
            );
        } else {
            // Insert new settings
            $updateData['user_id'] = $userId;
            $updateData['widget_id'] = $widgetId;
            $db->insert('widget_settings', $updateData);
        }
        
        // Get updated settings
        $settings = $db->fetch(
            "SELECT * FROM widget_settings WHERE user_id = :user_id AND widget_id = :widget_id",
            ['user_id' => $userId, 'widget_id' => $widgetId]
        );
        
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Error updating widget settings: " . $e->getMessage());
    }
}

// Generate embed code
$embedCode = '';
if ($widgetId) {
    $siteUrl = SITE_URL;
    $embedCode = "<script>\n";
    $embedCode .= "  (function(w,d,s,o,f,js,fjs){\n";
    $embedCode .= "    w['LiveSupportWidget']=o;\n";
    $embedCode .= "    w[o] = w[o] || function() { (w[o].q = w[o].q || []).push(arguments) };\n";
    $embedCode .= "    js = d.createElement(s), fjs = d.getElementsByTagName(s)[0];\n";
    $embedCode .= "    js.id = o; js.src = f; js.async = 1; fjs.parentNode.insertBefore(js, fjs);\n";
    $embedCode .= "  }(window, document, 'script', 'LSW', '{$siteUrl}/widget/loader.js'));\n";
    $embedCode .= "  LSW('init', '{$widgetId}');\n";
    $embedCode .= "</script>";
}

// Generate preview snippet
$previewSnippet = '';
if ($settings) {
    $themeColor = htmlspecialchars($settings['theme_color']);
    $textColor = htmlspecialchars($settings['text_color']);
    $position = htmlspecialchars($settings['position']);
    $displayName = htmlspecialchars($settings['display_name'] ?? 'Customer Support');
    
    $previewPositionClass = '';
    switch ($position) {
        case 'bottom_right':
            $previewPositionClass = 'bottom: 20px; right: 20px;';
            break;
        case 'bottom_left':
            $previewPositionClass = 'bottom: 20px; left: 20px;';
            break;
        case 'top_right':
            $previewPositionClass = 'top: 20px; right: 20px;';
            break;
        case 'top_left':
            $previewPositionClass = 'top: 20px; left: 20px;';
            break;
        default:
            $previewPositionClass = 'bottom: 20px; right: 20px;';
    }
    
    $logoHtml = '';
    if (!empty($settings['logo_url'])) {
        $logoUrl = htmlspecialchars(SITE_URL . '/' . $settings['logo_url']);
        $logoHtml = "<img src=\"{$logoUrl}\" alt=\"Logo\" class=\"preview-logo\">";
    }
    
    $previewSnippet = "<div class=\"widget-preview-container\">";
    $previewSnippet .= "  <div class=\"widget-preview\" style=\"{$previewPositionClass}\">";
    $previewSnippet .= "    <div class=\"widget-button\" style=\"background-color: {$themeColor}; color: {$textColor};\">";
    $previewSnippet .= "      <div class=\"widget-icon\">💬</div>";
    $previewSnippet .= "    </div>";
    $previewSnippet .= "    <div class=\"widget-popup\">";
    $previewSnippet .= "      <div class=\"widget-header\" style=\"background-color: {$themeColor}; color: {$textColor};\">";
    
    if ($logoHtml) {
        $previewSnippet .= "        <div class=\"widget-header-content\">";
        $previewSnippet .= "          {$logoHtml}";
        $previewSnippet .= "          <div>";
        $previewSnippet .= "            <div class=\"widget-title\">{$displayName}</div>";
        $previewSnippet .= "            <div class=\"widget-status\">Online</div>";
        $previewSnippet .= "          </div>";
        $previewSnippet .= "        </div>";
    } else {
        $previewSnippet .= "        <div class=\"widget-title\">{$displayName}</div>";
        $previewSnippet .= "        <div class=\"widget-status\">Online</div>";
    }
    
    $previewSnippet .= "        <div class=\"widget-close\">✕</div>";
    $previewSnippet .= "      </div>";
    $previewSnippet .= "      <div class=\"widget-body\">";
    $previewSnippet .= "        <div class=\"widget-messages\">";
    $previewSnippet .= "          <div class=\"widget-message agent\">";
    $previewSnippet .= "            <div class=\"message-content\">" . htmlspecialchars($settings['welcome_message'] ?? 'Hello! How can we help you today?') . "</div>";
    $previewSnippet .= "            <div class=\"message-time\">Just now</div>";
    $previewSnippet .= "          </div>";
    $previewSnippet .= "        </div>";
    $previewSnippet .= "      </div>";
    $previewSnippet .= "      <div class=\"widget-footer\">";
    $previewSnippet .= "        <textarea class=\"widget-input\" placeholder=\"Type a message...\"></textarea>";
    $previewSnippet .= "        <button class=\"widget-send\" style=\"background-color: {$themeColor}; color: {$textColor};\">Send</button>";
    $previewSnippet .= "      </div>";
    
    if ($settings['show_branding']) {
        $previewSnippet .= "      <div class=\"widget-branding\">Powered by Live Support</div>";
    }
    
    $previewSnippet .= "    </div>";
    $previewSnippet .= "  </div>";
    $previewSnippet .= "</div>";
}

// Include header
include '../includes/header.php';
?>

<style>
/* Widget Settings Page Styles */
.settings-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 15px;
}

.settings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.settings-header h1 {
    margin: 0;
    font-size: 1.8rem;
    color: #333;
}

.settings-layout {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
}

.settings-form {
    flex: 1;
    min-width: 550px;
}

.preview-container {
    flex: 1;
    min-width: 350px;
}

.settings-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 30px;
}

.settings-card-header {
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e6e9f0;
}

.settings-card-header h2 {
    margin: 0;
    font-size: 1.2rem;
    color: #333;
}

.settings-card-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.color-picker-group {
    display: flex;
    align-items: center;
}

.color-picker {
    width: 60px;
    height: 30px;
    padding: 0;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-right: 10px;
    cursor: pointer;
}

.color-value {
    font-family: monospace;
    font-size: 0.9rem;
    color: #555;
}

.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.form-check:last-child {
    margin-bottom: 0;
}

.form-check-input {
    margin-right: 10px;
}

.form-check-label {
    font-weight: normal;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 0.85rem;
    color: #777;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 0.95rem;
    font-weight: 500;
    text-align: center;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-secondary {
    background-color: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
}

.btn-secondary:hover {
    background-color: #e5e5e5;
}

.btn-danger {
    background-color: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background-color: #c0392b;
}

.form-actions {
    margin-top: 30px;
    display: flex;
    justify-content: flex-end;
    gap: 15px;
}

.alert {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.code-container {
    position: relative;
    background-color: #f8f9fa;
    border: 1px solid #e6e9f0;
    border-radius: 6px;
    padding: 15px;
    margin-top: 20px;
}

.code-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.code-title {
    font-weight: 600;
    color: #555;
}

.copy-btn {
    background: none;
    border: none;
    color: #3498db;
    cursor: pointer;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.copy-btn:hover {
    color: #2980b9;
}

.code-block {
    background-color: #2d3748;
    color: #e2e8f0;
    padding: 15px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.9rem;
    overflow-x: auto;
    white-space: pre;
}

.current-logo {
    display: block;
    max-width: 200px;
    max-height: 80px;
    margin-bottom: 10px;
}

.logo-preview {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 15px;
}

.logo-actions {
    display: flex;
    gap: 10px;
}

/* Widget Preview Styles */
.widget-preview-container {
    position: relative;
    width: 100%;
    height: 500px;
    background-color: #f5f5f5;
    border-radius: 8px;
    overflow: hidden;
    margin-top: 20px;
}

.widget-preview {
    position: absolute;
    width: 350px;
}

.widget-button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    position: absolute;
    bottom: 20px;
    right: 0;
    z-index: 10;
}

.widget-icon {
    font-size: 24px;
}

.widget-popup {
    width: 350px;
    height: 450px;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    margin-bottom: 70px;
}

.widget-header {
    padding: 15px;
    position: relative;
}

.widget-header-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.preview-logo {
    width: 30px;
    height: 30px;
    object-fit: contain;
}

.widget-title {
    font-weight: 600;
    font-size: 1rem;
}

.widget-status {
    font-size: 0.8rem;
    opacity: 0.8;
}

.widget-close {
    position: absolute;
    top: 15px;
    right: 15px;
    cursor: pointer;
    font-size: 1rem;
}

.widget-body {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    background-color: #f9f9f9;
}

.widget-messages {
    display: flex;
    flex-direction: column;
}

.widget-message {
    max-width: 80%;
    padding: 10px 12px;
    border-radius: 15px;
    margin-bottom: 10px;
}

.widget-message.agent {
    align-self: flex-start;
    background-color: #3498db;
    color: white;
    border-bottom-left-radius: 4px;
}

.widget-message.visitor {
    align-self: flex-end;
    background-color: #e6e6e6;
    color: #333;
    border-bottom-right-radius: 4px;
}

.message-content {
    margin-bottom: 5px;
}

.message-time {
    font-size: 0.7rem;
    opacity: 0.8;
    text-align: right;
}

.widget-footer {
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-top: 1px solid #e6e6e6;
}

.widget-input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 20px;
    outline: none;
    resize: none;
    height: 40px;
}

.widget-send {
    width: 60px;
    height: 40px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.widget-branding {
    padding: 5px 10px;
    text-align: center;
    font-size: 0.75rem;
    color: #999;
    background-color: #f5f5f5;
}

/* CSS Editor Styles */
.css-editor {
    font-family: monospace;
    min-height: 150px;
    line-height: 1.5;
}

@media (max-width: 992px) {
    .settings-layout {
        flex-direction: column;
    }
    
    .settings-form, .preview-container {
        min-width: 100%;
    }
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .widget-preview {
        width: 300px;
    }
    
    .widget-popup {
        width: 300px;
    }
}
</style>

<main class="settings-container">
    <div class="settings-header">
        <h1>Widget Settings</h1>
        <?php if ($widgetId): ?>
        <div>
            <a href="javascript:void(0)" onclick="openPreview()" class="btn btn-secondary">Open Widget Preview</a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Success!</strong> Your widget settings have been updated.
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$widgetId): ?>
        <div class="alert alert-danger">
            <strong>Widget ID not found!</strong> Please contact support for assistance.
        </div>
    <?php else: ?>
    
    <div class="settings-layout">
        <div class="settings-form">
            <form method="post" enctype="multipart/form-data">
                <!-- Appearance Settings Card -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2>Appearance</h2>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="theme_color">Theme Color</label>
                                <div class="color-picker-group">
                                    <input type="color" id="theme_color" name="theme_color" class="color-picker" value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#3498db'); ?>" />
                                    <span class="color-value" id="theme_color_value"><?php echo htmlspecialchars($settings['theme_color'] ?? '#3498db'); ?></span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="text_color">Text Color</label>
                                <div class="color-picker-group">
                                    <input type="color" id="text_color" name="text_color" class="color-picker" value="<?php echo htmlspecialchars($settings['text_color'] ?? '#ffffff'); ?>" />
                                    <span class="color-value" id="text_color_value"><?php echo htmlspecialchars($settings['text_color'] ?? '#ffffff'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="position">Widget Position</label>
                            <select id="position" name="position" class="form-control">
                                <option value="bottom_right" <?php echo ($settings['position'] ?? 'bottom_right') === 'bottom_right' ? 'selected' : ''; ?>>Bottom Right</option>
                                <option value="bottom_left" <?php echo ($settings['position'] ?? 'bottom_right') === 'bottom_left' ? 'selected' : ''; ?>>Bottom Left</option>
                                <option value="top_right" <?php echo ($settings['position'] ?? 'bottom_right') === 'top_right' ? 'selected' : ''; ?>>Top Right</option>
                                <option value="top_left" <?php echo ($settings['position'] ?? 'bottom_right') === 'top_left' ? 'selected' : ''; ?>>Top Left</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="display_name">Display Name</label>
                            <input type="text" id="display_name" name="display_name" class="form-control" value="<?php echo htmlspecialchars($settings['display_name'] ?? 'Customer Support'); ?>" placeholder="e.g. Customer Support" />
                            <small class="form-text">The name displayed in the chat header.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="logo">Logo (Optional)</label>
                            <?php if (!empty($settings['logo_url'])): ?>
                                <div class="logo-preview">
                                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($settings['logo_url']); ?>" alt="Current Logo" class="current-logo" />
                                    <div class="logo-actions">
                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('logo').click()">Change Logo</button>
                                        <label class="btn btn-danger">
                                            <input type="checkbox" name="remove_logo" value="1" style="display: none;" onchange="handleRemoveLogo(this)" /> 
                                            Remove Logo
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="logo" name="logo" class="form-control" style="<?php echo !empty($settings['logo_url']) ? 'display: none;' : ''; ?>" accept="image/jpeg,image/png,image/svg+xml" />
                            <small class="form-text">Upload a logo to display in your chat widget. Recommended size: 60x60px. Max file size: 2MB.</small>
                        </div>
                    </div>
                </div>
                
                <!-- Content Settings Card -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2>Messages</h2>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-group">
                            <label for="welcome_message">Welcome Message</label>
                            <textarea id="welcome_message" name="welcome_message" class="form-control" placeholder="Hello! How can we help you today?"><?php echo htmlspecialchars($settings['welcome_message'] ?? 'Hello! How can we help you today?'); ?></textarea>
                            <small class="form-text">This message appears when a visitor opens the chat widget.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="offline_message">Offline Message</label>
                            <textarea id="offline_message" name="offline_message" class="form-control" placeholder="Sorry, we're currently offline. Please leave a message and we'll get back to you soon."><?php echo htmlspecialchars($settings['offline_message'] ?? 'Sorry, we\'re currently offline. Please leave a message and we\'ll get back to you soon.'); ?></textarea>
                            <small class="form-text">This message appears when you're offline.</small>
                        </div>
                    </div>
                </div>
                
                <!-- Behavior Settings Card -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2>Behavior</h2>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-check">
                            <input type="checkbox" id="mobile_enabled" name="mobile_enabled" class="form-check-input" <?php echo ($settings['mobile_enabled'] ?? 1) ? 'checked' : ''; ?> />
                            <label for="mobile_enabled" class="form-check-label">Enable on Mobile Devices</label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="show_branding" name="show_branding" class="form-check-input" <?php echo ($settings['show_branding'] ?? 1) ? 'checked' : ''; ?> />
                            <label for="show_branding" class="form-check-label">Show "Powered by" Branding</label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="auto_popup" name="auto_popup" class="form-check-input" <?php echo ($settings['auto_popup'] ?? 0) ? 'checked' : ''; ?> onchange="toggleAutoPopupDelay()" />
                            <label for="auto_popup" class="form-check-label">Automatically Open Chat Widget</label>
                        </div>
                        
                        <div class="form-group" id="auto_popup_delay_container" style="display: <?php echo ($settings['auto_popup'] ?? 0) ? 'block' : 'none'; ?>; margin-top: 15px; margin-left: 25px;">
                            <label for="auto_popup_delay">Auto-open Delay (seconds)</label>
                            <input type="number" id="auto_popup_delay" name="auto_popup_delay" class="form-control" min="0" max="60" value="<?php echo htmlspecialchars($settings['auto_popup_delay'] ?? 10); ?>" />
                            <small class="form-text">How long to wait before automatically opening the chat widget (in seconds).</small>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Settings Card -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2>Advanced Customization</h2>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-group">
                            <label for="custom_css">Custom CSS</label>
                            <textarea id="custom_css" name="custom_css" class="form-control css-editor" placeholder=".live-support-widget { /* your custom styles */ }"><?php echo htmlspecialchars($settings['custom_css'] ?? ''); ?></textarea>
                            <small class="form-text">Add custom CSS to further customize your widget's appearance.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
        
        <div class="preview-container">
            <!-- Embed Code Card -->
            <div class="settings-card">
                <div class="settings-card-header">
                    <h2>Embed Code</h2>
                </div>
                <div class="settings-card-body">
                    <p>Add this code to your website to display the chat widget:</p>
                    
                    <div class="code-container">
                        <div class="code-header">
                            <span class="code-title">Widget Embed Code</span>
                            <button type="button" class="copy-btn" onclick="copyEmbedCode()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <pre class="code-block" id="embed-code"><?php echo htmlspecialchars($embedCode); ?></pre>
                    </div>
                </div>
            </div>
            
            <!-- Preview Card -->
            <div class="settings-card">
                <div class="settings-card-header">
                    <h2>Widget Preview</h2>
                </div>
                <div class="settings-card-body">
                    <p>This is a preview of how your chat widget will appear on your website:</p>
                    
                    <?php echo $previewSnippet; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Color picker value display update
    const themeColorPicker = document.getElementById('theme_color');
    const themeColorValue = document.getElementById('theme_color_value');
    const textColorPicker = document.getElementById('text_color');
    const textColorValue = document.getElementById('text_color_value');
    
    if (themeColorPicker && themeColorValue) {
        themeColorPicker.addEventListener('input', function() {
            themeColorValue.textContent = this.value;
            updatePreview();
        });
    }
    
    if (textColorPicker && textColorValue) {
        textColorPicker.addEventListener('input', function() {
            textColorValue.textContent = this.value;
            updatePreview();
        });
    }
    
    // Update preview elements when form values change
    const formControls = document.querySelectorAll('.form-control, .form-check-input');
    formControls.forEach(function(control) {
        control.addEventListener('change', updatePreview);
    });
    
    // Trigger initial preview update
    updatePreview();
});

// Function to toggle auto popup delay field
function toggleAutoPopupDelay() {
    const autoPopup = document.getElementById('auto_popup');
    const autoPopupDelayContainer = document.getElementById('auto_popup_delay_container');
    
    if (autoPopup && autoPopupDelayContainer) {
        autoPopupDelayContainer.style.display = autoPopup.checked ? 'block' : 'none';
    }
}

// Function to handle logo removal
function handleRemoveLogo(checkbox) {
    const logoInput = document.getElementById('logo');
    const logoPreview = checkbox.closest('.logo-preview');
    
    if (checkbox.checked) {
        if (logoPreview) {
            logoPreview.style.opacity = '0.5';
        }
    } else {
        if (logoPreview) {
            logoPreview.style.opacity = '1';
        }
    }
}

// Function to copy embed code
function copyEmbedCode() {
    const embedCode = document.getElementById('embed-code');
    const copyBtn = document.querySelector('.copy-btn');
    
    if (embedCode && copyBtn) {
        const textArea = document.createElement('textarea');
        textArea.value = embedCode.textContent;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Change button text temporarily
        const originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        
        setTimeout(function() {
            copyBtn.innerHTML = originalText;
        }, 2000);
    }
}

// Function to update preview based on form values
function updatePreview() {
    const themeColor = document.getElementById('theme_color').value;
    const textColor = document.getElementById('text_color').value;
    const displayName = document.getElementById('display_name').value || 'Customer Support';
    const welcomeMessage = document.getElementById('welcome_message').value || 'Hello! How can we help you today?';
    const showBranding = document.getElementById('show_branding').checked;
    const position = document.getElementById('position').value;
    
    // Update the preview elements
    const widgetButton = document.querySelector('.widget-button');
    const widgetHeader = document.querySelector('.widget-header');
    const widgetSend = document.querySelector('.widget-send');
    const widgetTitle = document.querySelector('.widget-title');
    const widgetBranding = document.querySelector('.widget-branding');
    const messageContent = document.querySelector('.widget-message .message-content');
    const widgetPreview = document.querySelector('.widget-preview');
    
    if (widgetButton) widgetButton.style.backgroundColor = themeColor;
    if (widgetButton) widgetButton.style.color = textColor;
    if (widgetHeader) widgetHeader.style.backgroundColor = themeColor;
    if (widgetHeader) widgetHeader.style.color = textColor;
    if (widgetSend) widgetSend.style.backgroundColor = themeColor;
    if (widgetSend) widgetSend.style.color = textColor;
    if (widgetTitle) widgetTitle.textContent = displayName;
    if (messageContent) messageContent.textContent = welcomeMessage;
    if (widgetBranding) widgetBranding.style.display = showBranding ? 'block' : 'none';
    
    // Update position
    if (widgetPreview) {
        switch (position) {
            case 'bottom_right':
                widgetPreview.style.bottom = '20px';
                widgetPreview.style.right = '20px';
                widgetPreview.style.top = 'auto';
                widgetPreview.style.left = 'auto';
                break;
            case 'bottom_left':
                widgetPreview.style.bottom = '20px';
                widgetPreview.style.left = '20px';
                widgetPreview.style.top = 'auto';
                widgetPreview.style.right = 'auto';
                break;
            case 'top_right':
                widgetPreview.style.top = '20px';
                zz.style.right = '20px';
                widgetPreview.style.bottom = 'auto';
                widgetPreview.style.left = 'auto';
                break;
            case 'top_left':a
                widgetPreview.style.top = '20px';
                widgetPreview.style.left = '20px';aa
                widgetPreview.style.bottom = 'auto';
                widgetPreview.style.right = 'auto';a
                break;
        }
    }
}

// Function to open widget in a new window for full preview
function openPreview() {
    const widgetId = '<?php echo $widgetId; ?>';
    const url = '<?php echo SITE_URL; ?>/widget/preview.php?id=' + widgetId;
    window.open(url, 'widget_preview', 'width=800,height=600');
}
</script>

<?php include '../includes/footer.php'; ?>
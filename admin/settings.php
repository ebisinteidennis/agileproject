<?php
$pageTitle = 'Site Settings';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$settings = getSiteSettings();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updatedSettings = [
        'site_name' => sanitizeInput($_POST['site_name']),
        'admin_email' => sanitizeInput($_POST['admin_email']),
        'smtp_host' => sanitizeInput($_POST['smtp_host']),
        'smtp_port' => (int)$_POST['smtp_port'],
        'smtp_user' => sanitizeInput($_POST['smtp_user']),
        'currency' => sanitizeInput($_POST['currency']),
        'paystack_public_key' => sanitizeInput($_POST['paystack_public_key']),
        'paystack_secret_key' => sanitizeInput($_POST['paystack_secret_key']),
        'flutterwave_public_key' => sanitizeInput($_POST['flutterwave_public_key']),
        'flutterwave_secret_key' => sanitizeInput($_POST['flutterwave_secret_key']),
        'moniepoint_api_key' => sanitizeInput($_POST['moniepoint_api_key']),
        'moniepoint_merchant_id' => sanitizeInput($_POST['moniepoint_merchant_id']),
        'manual_payment_instructions' => $_POST['manual_payment_instructions']
    ];
    
    // Handle SMTP password (only update if provided)
    if (!empty($_POST['smtp_pass'])) {
        $updatedSettings['smtp_pass'] = $_POST['smtp_pass'];
    }
    
    // Handle logo upload
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['site_logo'], 'assets/images', ['image/jpeg', 'image/png', 'image/gif']);
        
        if ($uploadResult['success']) {
            $updatedSettings['site_logo'] = $uploadResult['filename'];
        } else {
            $error = $uploadResult['message'];
        }
    }
    
    // Update settings in database
    if (!isset($error)) {
        $db->update('settings', $updatedSettings, 'id = :id', ['id' => 1]);
        $settings = getSiteSettings(); // Refresh settings
        $success = 'Settings updated successfully.';
    }
}

// Include header
include '../includes/header.php';
?>

<main class="container admin-container">
    <h1>Site Settings</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data" class="settings-form">
        <div class="settings-card">
            <h2>General Settings</h2>
            
            <div class="form-group">
                <label for="site_name">Site Name</label>
                <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo $settings['site_name']; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="admin_email">Admin Email</label>
                <input type="email" id="admin_email" name="admin_email" class="form-control" value="<?php echo $settings['admin_email']; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="site_logo">Site Logo</label>
                <?php if (!empty($settings['site_logo'])): ?>
                    <div class="current-logo">
                        <img src="<?php echo SITE_URL . '/' . $settings['site_logo']; ?>" alt="Current Logo" style="max-height: 100px;">
                        <p>Current Logo</p>
                    </div>
                <?php endif; ?>
                <input type="file" id="site_logo" name="site_logo" class="form-control-file" accept="image/*">
                <small class="form-text text-muted">Leave empty to keep the current logo.</small>
            </div>
            
            <div class="form-group">
                <label for="currency">Currency</label>
                <select id="currency" name="currency" class="form-control">
                    <option value="NGN" <?php echo $settings['currency'] === 'NGN' ? 'selected' : ''; ?>>Nigerian Naira (NGN)</option>
                    <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                    <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                    <option value="GBP" <?php echo $settings['currency'] === 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                </select>
            </div>
        </div>
        
        <div class="settings-card">
            <h2>Email Settings</h2>
            
            <div class="form-group">
                <label for="smtp_host">SMTP Host</label>
                <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="<?php echo $settings['smtp_host']; ?>">
            </div>
            
            <div class="form-group">
                <label for="smtp_port">SMTP Port</label>
                <input type="number" id="smtp_port" name="smtp_port" class="form-control" value="<?php echo $settings['smtp_port']; ?>">
            </div>
            
            <div class="form-group">
                <label for="smtp_user">SMTP Username</label>
                <input type="text" id="smtp_user" name="smtp_user" class="form-control" value="<?php echo $settings['smtp_user']; ?>">
            </div>
            
            <div class="form-group">
                <label for="smtp_pass">SMTP Password</label>
                <input type="password" id="smtp_pass" name="smtp_pass" class="form-control">
                <small class="form-text text-muted">Leave empty to keep the current password.</small>
            </div>
        </div>
        
        <div class="settings-card">
            <h2>Payment Gateway Settings</h2>
            
            <div class="settings-subsection">
                <h3>Paystack</h3>
                <div class="form-group">
                    <label for="paystack_public_key">Paystack Public Key</label>
                    <input type="text" id="paystack_public_key" name="paystack_public_key" class="form-control" value="<?php echo $settings['paystack_public_key']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="paystack_secret_key">Paystack Secret Key</label>
                    <input type="text" id="paystack_secret_key" name="paystack_secret_key" class="form-control" value="<?php echo $settings['paystack_secret_key']; ?>">
                </div>
            </div>
            
            <div class="settings-subsection">
                <h3>Flutterwave</h3>
                <div class="form-group">
                    <label for="flutterwave_public_key">Flutterwave Public Key</label>
                    <input type="text" id="flutterwave_public_key" name="flutterwave_public_key" class="form-control" value="<?php echo $settings['flutterwave_public_key']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="flutterwave_secret_key">Flutterwave Secret Key</label>
                    <input type="text" id="flutterwave_secret_key" name="flutterwave_secret_key" class="form-control" value="<?php echo $settings['flutterwave_secret_key']; ?>">
                </div>
            </div>
            
            <div class="settings-subsection">
                <h3>Moniepoint</h3>
                <div class="form-group">
                    <label for="moniepoint_api_key">Moniepoint API Key</label>
                    <input type="text" id="moniepoint_api_key" name="moniepoint_api_key" class="form-control" value="<?php echo $settings['moniepoint_api_key']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="moniepoint_merchant_id">Moniepoint Merchant ID</label>
                    <input type="text" id="moniepoint_merchant_id" name="moniepoint_merchant_id" class="form-control" value="<?php echo $settings['moniepoint_merchant_id']; ?>">
                </div>
            </div>
            
            <div class="settings-subsection">
                <h3>Manual Payment</h3>
                <div class="form-group">
                    <label for="manual_payment_instructions">Manual Payment Instructions</label>
                    <textarea id="manual_payment_instructions" name="manual_payment_instructions" class="form-control" rows="5"><?php echo $settings['manual_payment_instructions']; ?></textarea>
                    <small class="form-text text-muted">Provide detailed instructions for manual payments (bank account details, etc.)</small>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</main>

<?php include '../includes/footer.php'; ?>
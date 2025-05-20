<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/payments.php';

// This file handles webhooks from various payment gateways
// It should be registered with each payment provider's webhook settings

// Get request body
$input = file_get_contents('php://input');
$event = json_decode($input, true);

// Set initial response
$response = ['status' => 'unknown', 'message' => 'No action taken'];

// Determine which provider sent the webhook
$provider = null;
$signature = null;

// Check for Paystack
if (isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
    $provider = 'paystack';
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];
} 
// Check for Flutterwave
elseif (isset($_SERVER['HTTP_VERIF_HASH'])) {
    $provider = 'flutterwave';
    $signature = $_SERVER['HTTP_VERIF_HASH'];
}
// Check for Moniepoint (this would depend on their specific webhook format)
elseif (isset($_SERVER['HTTP_X_MONIEPOINT_SIGNATURE'])) {
    $provider = 'moniepoint';
    $signature = $_SERVER['HTTP_X_MONIEPOINT_SIGNATURE'];
}

// Log the webhook for debugging (optional)
$logFile = '../logs/webhook_' . date('Y-m-d') . '.log';
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'provider' => $provider,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'payload' => $input
];

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0755, true);
}

// Append to log
file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND);

// Process based on provider
if ($provider === 'paystack') {
    $settings = getSiteSettings();
    $secretKey = $settings['paystack_secret_key'];
    
    // Verify signature
    $computedSignature = hash_hmac('sha512', $input, $secretKey);
    if ($computedSignature !== $signature) {
        $response = ['status' => 'error', 'message' => 'Invalid signature'];
    } else {
        // Process Paystack webhook
        if ($event['event'] === 'charge.success') {
            $reference = $event['data']['reference'];
            $result = verifyPaystackPayment($reference);
            $response = ['status' => $result['success'] ? 'success' : 'error', 'message' => $result['message']];
        }
    }
} elseif ($provider === 'flutterwave') {
    $settings = getSiteSettings();
    $secretHash = $settings['flutterwave_secret_key'];
    
    // Verify signature (this would depend on Flutterwave's verification method)
    if ($signature !== $secretHash) {
        $response = ['status' => 'error', 'message' => 'Invalid signature'];
    } else {
        // Process Flutterwave webhook
        if (isset($event['event']) && $event['event'] === 'charge.completed') {
            $transactionId = $event['data']['id'];
            $result = verifyFlutterwavePayment($transactionId);
            $response = ['status' => $result['success'] ? 'success' : 'error', 'message' => $result['message']];
        }
    }
} elseif ($provider === 'moniepoint') {
    // Process Moniepoint webhook (placeholder - implement based on their documentation)
    // This is a simplified example
    if (isset($event['status']) && $event['status'] === 'successful') {
        $reference = $event['reference'];
        $result = verifyMoniepointPayment($reference);
        $response = ['status' => $result['success'] ? 'success' : 'error', 'message' => $result['message']];
    }
}

// Return response
header('Content-Type: application/json');
echo json_encode($response);
exit;
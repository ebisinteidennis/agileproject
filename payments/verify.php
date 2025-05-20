<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/payments.php';

// This file handles verification of payments from various gateways
// It can be called programmatically or via AJAX

// Prevent direct access without proper parameters
if (!isset($_GET['provider']) || !isset($_GET['reference'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$provider = $_GET['provider'];
$reference = $_GET['reference'];
$result = [];

// Verify payment based on provider
switch ($provider) {
    case 'paystack':
        $result = verifyPaystackPayment($reference);
        break;
        
    case 'flutterwave':
        if (!isset($_GET['transaction_id'])) {
            $result = ['success' => false, 'message' => 'Transaction ID required for Flutterwave'];
        } else {
            $result = verifyFlutterwavePayment($_GET['transaction_id']);
        }
        break;
        
    case 'moniepoint':
        $result = verifyMoniepointPayment($reference);
        break;
        
    default:
        $result = ['success' => false, 'message' => 'Invalid payment provider'];
        break;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($result);
exit;
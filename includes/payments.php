<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

function createPayment($userId, $subscriptionId, $paymentMethod) {
    global $db;
    
    $user = getUserById($userId);
    $subscription = getSubscriptionById($subscriptionId);
    
    if (!$user || !$subscription) {
        return ['success' => false, 'message' => 'Invalid user or subscription'];
    }
    
    $reference = generateReference();
    
    $paymentData = [
        'user_id' => $userId,
        'subscription_id' => $subscriptionId,
        'payment_method' => $paymentMethod,
        'transaction_reference' => $reference,
        'amount' => $subscription['price'],
        'status' => 'pending'
    ];
    
    $paymentId = $db->insert('payments', $paymentData);
    
    if (!$paymentId) {
        return ['success' => false, 'message' => 'Failed to create payment record'];
    }
    
    return [
        'success' => true,
        'payment_id' => $paymentId,
        'reference' => $reference,
        'amount' => $subscription['price']
    ];
}

function processManualPayment($paymentId, $file) {
    global $db;
    
    $payment = getPaymentById($paymentId);
    
    if (!$payment) {
        return ['success' => false, 'message' => 'Payment not found'];
    }
    
    // Upload proof of payment
    $uploadResult = uploadFile($file, 'uploads/payments', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);
    
    if (!$uploadResult['success']) {
        return $uploadResult;
    }
    
    // Update payment record
    $db->update(
        'payments',
        ['payment_proof' => $uploadResult['filename']],
        'id = :id',
        ['id' => $paymentId]
    );
    
    return [
        'success' => true,
        'message' => 'Payment proof uploaded successfully. Your payment will be verified by admin.'
    ];
}

function verifyPaystackPayment($reference) {
    global $db;
    $settings = getSiteSettings();
    
    $secretKey = $settings['paystack_secret_key'];
    
    if (!$secretKey) {
        return ['success' => false, 'message' => 'Paystack is not configured'];
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $reference,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $secretKey
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return ['success' => false, 'message' => 'Curl error: ' . $err];
    }
    
    $result = json_decode($response, true);
    
    if (!$result['status'] || !isset($result['data'])) {
        return ['success' => false, 'message' => 'Payment verification failed'];
    }
    
    if ($result['data']['status'] === 'success') {
        // Get payment by reference
        $payment = $db->fetch(
            "SELECT * FROM payments WHERE transaction_reference = :reference", 
            ['reference' => $reference]
        );
        
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment record not found'];
        }
        
        // Update payment status
        $db->update(
            'payments',
            ['status' => 'completed'],
            'id = :id',
            ['id' => $payment['id']]
        );
        
        // Update user subscription
        activateUserSubscription($payment['user_id'], $payment['subscription_id']);
        
        return ['success' => true, 'message' => 'Payment verified successfully'];
    }
    
    return ['success' => false, 'message' => 'Payment failed or pending'];
}

function verifyFlutterwavePayment($transactionId) {
    global $db;
    $settings = getSiteSettings();
    
    $secretKey = $settings['flutterwave_secret_key'];
    
    if (!$secretKey) {
        return ['success' => false, 'message' => 'Flutterwave is not configured'];
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/" . $transactionId . "/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $secretKey,
            "Content-Type: application/json"
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return ['success' => false, 'message' => 'Curl error: ' . $err];
    }
    
    $result = json_decode($response, true);
    
    if (!$result['status'] || $result['status'] !== 'success') {
        return ['success' => false, 'message' => 'Payment verification failed'];
    }
    
    // Get payment by reference (tx_ref in Flutterwave)
    $txRef = $result['data']['tx_ref'];
    $payment = $db->fetch(
        "SELECT * FROM payments WHERE transaction_reference = :reference", 
        ['reference' => $txRef]
    );
    
    if (!$payment) {
        return ['success' => false, 'message' => 'Payment record not found'];
    }
    
    // Update payment status
    $db->update(
        'payments',
        ['status' => 'completed'],
        'id = :id',
        ['id' => $payment['id']]
    );
    
    // Update user subscription
    activateUserSubscription($payment['user_id'], $payment['subscription_id']);
    
    return ['success' => true, 'message' => 'Payment verified successfully'];
}

function verifyMoniepointPayment($reference) {
    global $db;
    $settings = getSiteSettings();
    
    $apiKey = $settings['moniepoint_api_key'];
    $merchantId = $settings['moniepoint_merchant_id'];
    
    if (!$apiKey || !$merchantId) {
        return ['success' => false, 'message' => 'Moniepoint is not configured'];
    }
    
    // Moniepoint API implementation would go here
    // This is a placeholder since there's no public documentation available
    
    // For now, we'll simulate a successful verification
    $payment = $db->fetch(
        "SELECT * FROM payments WHERE transaction_reference = :reference", 
        ['reference' => $reference]
    );
    
    if (!$payment) {
        return ['success' => false, 'message' => 'Payment record not found'];
    }
    
    return ['success' => true, 'message' => 'Payment verification pending (Moniepoint integration)'];
}

function approveManualPayment($paymentId) {
    global $db;
    
    $payment = getPaymentById($paymentId);
    
    if (!$payment) {
        return ['success' => false, 'message' => 'Payment not found'];
    }
    
    // Update payment status
    $db->update(
        'payments',
        ['status' => 'completed'],
        'id = :id',
        ['id' => $paymentId]
    );
    
    // Update user subscription
    activateUserSubscription($payment['user_id'], $payment['subscription_id']);
    
    return ['success' => true, 'message' => 'Payment approved successfully'];
}

function rejectManualPayment($paymentId, $notes) {
    global $db;
    
    $payment = getPaymentById($paymentId);
    
    if (!$payment) {
        return ['success' => false, 'message' => 'Payment not found'];
    }
    
    // Update payment status
    $db->update(
        'payments',
        [
            'status' => 'failed',
            'admin_notes' => $notes
        ],
        'id = :id',
        ['id' => $paymentId]
    );
    
    return ['success' => true, 'message' => 'Payment rejected'];
}

function activateUserSubscription($userId, $subscriptionId) {
    global $db;
    
    $subscription = getSubscriptionById($subscriptionId);
    
    if (!$subscription) {
        return false;
    }
    
    // Calculate expiry date
    $expiryDate = date('Y-m-d H:i:s', strtotime('+' . $subscription['duration'] . ' days'));
    
    // Update user subscription
    $db->update(
        'users',
        [
            'subscription_id' => $subscriptionId,
            'subscription_status' => 'active',
            'subscription_expiry' => $expiryDate
        ],
        'id = :id',
        ['id' => $userId]
    );
    
    return true;
}

function getPaymentMethodsEnabled() {
    $settings = getSiteSettings();
    
    $methods = [];
    
    // Check Paystack
    if (!empty($settings['paystack_public_key']) && !empty($settings['paystack_secret_key'])) {
        $methods[] = 'paystack';
    }
    
    // Check Flutterwave
    if (!empty($settings['flutterwave_public_key']) && !empty($settings['flutterwave_secret_key'])) {
        $methods[] = 'flutterwave';
    }
    
    // Check Moniepoint
    if (!empty($settings['moniepoint_api_key']) && !empty($settings['moniepoint_merchant_id'])) {
        $methods[] = 'moniepoint';
    }
    
    // Manual payment is always available
    $methods[] = 'manual';
    
    return $methods;
}
?>
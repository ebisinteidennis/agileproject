// api/send-push-notification.php
<?php
require_once 'db.php';

function sendPushNotification($user_id, $visitor_id, $message) {
    $pdo = getDB();
    
    // Get user's FCM token
    $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['fcm_token']) {
        return false;
    }
    
    $serverKey = 'BJQ74cKMuPp0i5ZGXLPzKTp0ddEIG89QIb1dNt20tUneWNAk25PVWwQ2V38jArGAVZlLKOvd7m8j91hDsZSGUSM';
    $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    
    $notification = [
        'title' => 'New Message from Visitor #' . $visitor_id,
        'body' => substr($message, 0, 100),
        'sound' => 'default',
        'badge' => 1
    ];
    
    $data = [
        'visitor_id' => $visitor_id,
        'message' => $message,
        'timestamp' => time()
    ];
    
    $fcmNotification = [
        'to' => $user['fcm_token'],
        'notification' => $notification,
        'data' => $data,
        'priority' => 'high'
    ];
    
    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcmUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// Add this to your message sending logic
// Call sendPushNotification($user_id, $visitor_id, $message);
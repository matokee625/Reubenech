<?php
// SMS Gateway Helper for Cooperative Notifications using SMS.to API
// Recipient: 0799031535 (or site_phone from settings)

function sendSMSAlert($message) {
    global $conn;
    
    // Fallback if global connection is not initialized
    if (!isset($conn)) {
        require_once __DIR__ . '/../connection.php';
    }
    
    // Configured primary recipient phone number
    $phone = '0799031535'; 
    
    try {
        // Fetch the configured phone number from settings table if available
        $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'site_phone' LIMIT 1");
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_OBJ);
        if ($setting && !empty($setting->value)) {
            $phone = $setting->value;
        }
    } catch (Exception $e) {
        // Settings table might not be initialized, use fallback
    }
    
    // Log the message locally first
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] SMS TO: $phone | MESSAGE: $message\n";
    $log_file = __DIR__ . '/../sms_log.txt';
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // SMS.to API configuration settings
    // Replace '•••••••' with your real SMS.to API Key
    $api_key = '•••••••'; 
    $sender_id = 'smsto';
    
    // Build query array. We send to the specific phone number using the 'to' parameter.
    // If you ever want to send to your predefined campaign list, you can replace 'to' => $phone with 'list_id' => '1852'.
    $params = [
        'api_key' => $api_key,
        'to' => $phone,
        'message' => $message,
        'sender_id' => $sender_id,
        'bypass_optout' => 'true'
    ];
    
    $url = 'https://api.sms.to/sms/send?' . http_build_query($params);
    
    // Initialize cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log API response details for debugging
    if ($response === false) {
        $api_log = "[$timestamp] SMS.to API Error: $error\n";
    } else {
        $api_log = "[$timestamp] SMS.to API HTTP Code: $http_code | Response: $response\n";
    }
    file_put_contents(__DIR__ . '/../sms_api_log.txt', $api_log, FILE_APPEND);
}
?>

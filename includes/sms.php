<?php
// SMS Gateway Helper for Cooperative Notifications
// Simulates sending SMS by writing to a local log file, which can be connected to Twilio or Africa's Talking API.

function sendSMSAlert($message) {
    global $conn;
    
    // Fallback if global connection is not initialized
    if (!isset($conn)) {
        require_once __DIR__ . '/../connection.php';
    }
    
    // Configured primary recipient WhatsApp / phone number fallback
    $phone = '0799031535'; 
    
    try {
        // Fetch the configured phone number from settings table
        $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'site_phone' LIMIT 1");
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_OBJ);
        if ($setting && !empty($setting->value)) {
            $phone = $setting->value;
        }
    } catch (Exception $e) {
        // Settings table might not be initialized, use fallback
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] SMS TO: $phone | MESSAGE: $message\n";
    
    // Save to local SMS log file in the project root
    $log_file = __DIR__ . '/../sms_log.txt';
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Under a production environment, this is where Twilio or Africa's Talking API would be called.
    // E.g.
    // $sms_api->send($phone, $message);
}
?>

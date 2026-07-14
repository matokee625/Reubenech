<?php
require 'connection.php';
try {
    require_once 'includes/sms.php';
    sendSMSAlert("Test message");
    echo "SMS Success";
} catch (Exception $e) {
    echo "SMS Error: " . $e->getMessage();
}

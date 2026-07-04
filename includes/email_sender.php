<?php
// includes/email_sender.php
// Simple wrapper around PHP's built-in mail() function.
// Usage: sendEmail($to, $subject, $body);

function sendEmail(string $to, string $subject, string $body, string $from = null): bool {
    $headers = [];
    // Set From header
    if ($from) {
        $headers[] = "From: $from";
    } else {
        // Default From address if not provided
        $headers[] = "From: no-reply@yourdomain.com";
    }
    // Set content type for HTML email
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headerString = implode("\r\n", $headers);
    // Use mail() function
    return mail($to, $subject, $body, $headerString);
}
?>

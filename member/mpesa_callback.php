<?php
// Webhook Callback for Safaricom Daraja API STK Push Confirmation
require_once '../connection.php';
require_once '../includes/sms.php';

// Set response headers
header('Content-Type: application/json');

// Retrieve JSON payload from Safaricom
$callbackJSONData = file_get_contents('php://input');
$log_file = __DIR__ . '/../sms_log.txt';

// Log raw callback response for audit trail
$timestamp = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$timestamp] M-PESA WEBHOOK CALLBACK: " . $callbackJSONData . "\n", FILE_APPEND);

$data = json_decode($callbackJSONData, true);

if (!$data || !isset($data['Body']['stkCallback'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Callback Data']);
    exit();
}

$callback = $data['Body']['stkCallback'];
$resultCode = $callback['ResultCode'];
$resultDesc = $callback['ResultDesc'];
$checkoutRequestID = $callback['CheckoutRequestID'];

try {
    if ($resultCode == 0) {
        // Payment was successful
        $metadata = $callback['CallbackMetadata']['Item'];
        $amount = 0;
        $mpesaReceiptNumber = '';
        $phoneNumber = '';

        foreach ($metadata as $item) {
            if ($item['Name'] === 'Amount') {
                $amount = $item['Value'];
            } elseif ($item['Name'] === 'MpesaReceiptNumber') {
                $mpesaReceiptNumber = $item['Value'];
            } elseif ($item['Name'] === 'PhoneNumber') {
                $phoneNumber = $item['Value'];
            }
        }

        // Find the user with the matching CheckoutRequestID (currently stored in payment_ref)
        $user_stmt = $conn->prepare("SELECT id, username FROM users WHERE payment_ref = ? AND has_paid = 2 LIMIT 1");
        $user_stmt->execute([$checkoutRequestID]);
        $user = $user_stmt->fetch(PDO::FETCH_OBJ);

        if ($user) {
            $conn->beginTransaction();

            // Process based on amount
            if (round($amount) == 250) {
                // Posting fee: update phone number only, revert has_paid status back to unpaid (0) since we set it to 2 temporarily
                $update_stmt = $conn->prepare("UPDATE users SET has_paid = 0, payment_ref = ?, payment_amount = ?, phone = ? WHERE id = ?");
                $update_stmt->execute([$mpesaReceiptNumber, $amount, $phoneNumber, $user->id]);

                $notifMsg = "M-Pesa Express verified: Member '{$user->username}' successfully paid Ksh " . number_format($amount) . " posting fee (Ref: $mpesaReceiptNumber, Mobile: $phoneNumber).";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link) VALUES ('success', 'Posting Fee Paid', ?, 'posts.php')");
                $notif_stmt->execute([$notifMsg]);

                $smsMsg = "Payment Verified: Member '{$user->username}' paid Ksh $amount posting fee (Ref: $mpesaReceiptNumber). Market listing active.";
            } else {
                // Verification fee: Update user status to paid (has_paid = 1), record receipt number, and update phone number
                $update_stmt = $conn->prepare("UPDATE users SET has_paid = 1, payment_ref = ?, payment_amount = ?, phone = ? WHERE id = ?");
                $update_stmt->execute([$mpesaReceiptNumber, $amount, $phoneNumber, $user->id]);

                // Add notification for admin
                $notifMsg = "M-Pesa Express verified: Member '{$user->username}' successfully paid Ksh " . number_format($amount) . " (Ref: $mpesaReceiptNumber, Mobile: $phoneNumber).";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link) VALUES ('success', 'M-Pesa Payment Confirmed', ?, 'users.php')");
                $notif_stmt->execute([$notifMsg]);

                $smsMsg = "Payment Verified: Member '{$user->username}' paid Ksh $amount via M-Pesa (Ref: $mpesaReceiptNumber). Account activated.";
            }

            $conn->commit();

            // Send SMS alert to admin/recipient
            sendSMSAlert($smsMsg);
            
            echo json_encode(['status' => 'success', 'message' => 'Callback processed.']);
        } else {
            echo json_encode(['status' => 'warning', 'message' => 'CheckoutRequestID not matched to pending user.']);
        }
    } else {
        // Payment failed or was cancelled by user
        $user_stmt = $conn->prepare("SELECT id, username FROM users WHERE payment_ref = ? LIMIT 1");
        $user_stmt->execute([$checkoutRequestID]);
        $user = $user_stmt->fetch(PDO::FETCH_OBJ);

        if ($user) {
            // Revert status to unpaid
            $update_stmt = $conn->prepare("UPDATE users SET has_paid = 0, payment_ref = NULL WHERE id = ?");
            $update_stmt->execute([$user->id]);

            sendSMSAlert("Payment Failed: Member '{$user->username}' M-Pesa transaction failed/cancelled ($resultDesc).");
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Failed callback logged and status reset.']);
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    file_put_contents($log_file, "[$timestamp] M-PESA WEBHOOK CALLBACK ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}
exit();

<?php
session_start();
header('Content-Type: application/json');

require_once '../connection.php';
require_once '../includes/sms.php';
require_once '../includes/mpesa.php';

// Verify user is logged in as member
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'member') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'stk_push') {
        $phone = trim($input['phone'] ?? '');
        $amount = floatval($input['amount'] ?? 500); // Default registration fee
        $type = trim($input['type'] ?? 'mpesa');

        if (empty($phone)) {
            echo json_encode(['status' => 'error', 'message' => 'Phone number is required.']);
            exit();
        }

        $mpesa = new MpesaClient();
        $ref = '1115252'; // Co-op Bank Account Number
        $desc = 'REUBEN MATOKE'; // Business Name
        
        $response = $mpesa->initiateStkPush($phone, $amount, $ref, $desc);
        
        if (isset($response['status']) && $response['status'] === 'success') {
            try {
                // Save checkout request ID as temporary payment ref, set pending status, and register phone number
                $stmt = $conn->prepare("UPDATE users SET has_paid = 2, payment_ref = ?, payment_amount = ?, phone = ? WHERE id = ?");
                $stmt->execute([$response['CheckoutRequestID'], $amount, $phone, $userId]);
            } catch (PDOException $ex) {
                // Ignore database errors during logging
            }
        }
        
        echo json_encode($response);
        exit();
    }
    
    if ($action === 'stk_simulate_callback') {
        // This is only called in Mock Mode by the simulator when the user inputs a PIN
        $ref = strtoupper(trim($input['reference'] ?? ''));
        $amount = floatval($input['amount'] ?? 500);
        $phone = trim($input['phone'] ?? '');
        $pin = trim($input['pin'] ?? '');

        // Fetch test PIN from database settings (default to '2026')
        $correctPin = '2026';
        try {
            $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'mpesa_test_pin' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val !== false) {
                $correctPin = trim($val);
            }
        } catch (Exception $e) {}

        if ($pin !== $correctPin) {
            echo json_encode(['status' => 'error', 'message' => 'Incorrect M-Pesa PIN. Please try again.']);
            exit();
        }
        
        if (empty($ref)) {
            // Generate a random transaction code if empty
            $ref = 'MP' . strtoupper(substr(md5(time() . rand()), 0, 8));
        }

        try {
            $conn->beginTransaction();

            // 1. Process payment based on amount
            if (round($amount) == 250) {
                // Posting fee: update phone number only, do not change has_paid
                $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $stmt->execute([$phone, $userId]);
                
                // 2. Insert admin notification for posting fee
                $notifMsg = "Member '$username' paid Ksh " . number_format($amount) . " posting fee via M-Pesa Express (Ref: $ref, Phone: $phone). Market listing published.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link) VALUES ('success', 'Posting Fee Paid', ?, 'posts.php')");
                $notif_stmt->execute([$notifMsg]);

                $smsMsg = "Payment Confirmed: Member '$username' paid Ksh $amount posting fee (Ref: $ref). Market listing published.";
            } else {
                // Verification fee: Update the user account to fully active/paid and register/update phone number
                $stmt = $conn->prepare("UPDATE users SET has_paid = 1, payment_ref = ?, payment_amount = ?, phone = ? WHERE id = ?");
                $stmt->execute([$ref, $amount, $phone, $userId]);

                // 2. Insert admin notification
                $notifMsg = "Member '$username' paid Ksh " . number_format($amount) . " via M-Pesa Express (Ref: $ref, Phone: $phone). Account activated.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link) VALUES ('success', 'M-Pesa Payment Received', ?, 'users.php')");
                $notif_stmt->execute([$notifMsg]);

                $smsMsg = "Payment Confirmed: Member '$username' paid Ksh $amount (Ref: $ref). Account activated for live trading.";
            }

            $conn->commit();

            // 3. Send SMS notification
            sendSMSAlert($smsMsg);

            echo json_encode([
                'status' => 'success',
                'message' => 'Payment received and verified successfully!',
                'reference' => $ref
            ]);
            exit();
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action or request method.']);
exit();

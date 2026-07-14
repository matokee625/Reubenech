<?php
<?php
// Set JSON response header
header('Content-Type: application/json');
// Ensure request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['Success' => false, 'Message' => 'Method Not Allowed']);
    exit();
}
// For payment initiation, we allow unauthenticated access but need a user identifier.
// Expect 'user_id' in POST payload if session not available.
$payload = $input ?? [];
$userId = $_SESSION['user_id'] ?? $payload['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['Success' => false, 'Message' => 'Initiation failed: Unauthorized access']);
    exit();
}
?>
?>
$mpesaConfig = require '../config/mpesa.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
$input = json_decode(file_get_contents('php://input'), true);
// Determine user identifier either from session or supplied payload
$userId = $_SESSION['user_id'] ?? $input['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['Success' => false, 'Message' => 'User identifier required']);
    exit();
}// Determine user identifier either from session or supplied payload
$userId = $_SESSION['user_id'] ?? $input['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['Success' => false, 'Message' => 'User identifier required']);
    exit();
}
$phone = $input['phone'] ?? '';
// format phone to 254...
$phone = preg_replace('/^0/', '254', $phone);
$phone = preg_replace('/^\+/', '', $phone);

if (empty($phone)) {
    echo json_encode(['Success'=>false, 'Message'=>'Phone number required']);
    exit;
}

$amount = 500; // Updated registration fee

// If callback contains sandbox, force standard sandbox shortcode to ensure the prompt successfully triggers
if (strpos($mpesaConfig['callback_url'] ?? '', 'sandbox') !== false) {
    $shortcode = "174379";
    $passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
} else {
    $shortcode = $mpesaConfig['shortcode'] ?? "400200";
    $passkey = $mpesaConfig['passkey'] ?? "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
}

$account_ref = $mpesaConfig['account_ref'] ?? "1115252";
$callback_url = $mpesaConfig['callback_url'] ?? "https://sandbox.safaricom.co.ke/mpesa-callback";

$consumerKey = $mpesaConfig['consumer_key'];
$consumerSecret = $mpesaConfig['consumer_secret'];

// 1. Generate Token
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$ch = curl_init('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($response);
$access_token = $token_data->access_token ?? '';

if (!$access_token) {
    echo json_encode(['Success'=>false, 'Message'=>'Failed to authenticate with MPesa API.']);
    exit;
}

// 2. STK Push
$timestamp = date('YmdHis');
$password = base64_encode($shortcode . $passkey . $timestamp);

$stk_data = [
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callback_url,
    'AccountReference' => $account_ref, // routes to Reuben Matoke account 1115252
    'TransactionDesc' => 'REUBEN MATOKE'
];

$stk_ch = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
curl_setopt($stk_ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);
curl_setopt($stk_ch, CURLOPT_POST, true);
curl_setopt($stk_ch, CURLOPT_POSTFIELDS, json_encode($stk_data));
curl_setopt($stk_ch, CURLOPT_RETURNTRANSFER, true);
$stk_response = curl_exec($stk_ch);
curl_close($stk_ch);

$stk_result = json_decode($stk_response);

if (isset($stk_result->ResponseCode) && $stk_result->ResponseCode == "0") {
    // Prompt sent successfully. Mark as pending (has_paid = 2) and record checkout request ID
    try {
        $upd = $conn->prepare('UPDATE users SET has_paid = 2, payment_ref = ? WHERE id = ?');
        $upd->execute([$stk_result->CheckoutRequestID, $userId]);
        
        echo json_encode([
            'Success' => true, 
            'CheckoutRequestID' => $stk_result->CheckoutRequestID,
            'Message' => 'MPesa prompt sent to your phone! Please enter your PIN.'
        ]);
    } catch (Exception $e) {
        echo json_encode(['Success'=>false, 'Message'=>'Prompt sent, but database error: ' . $e->getMessage()]);
    }
} else {
    // STK Push Error
    $errMessage = $stk_result->errorMessage ?? 'Failed to send prompt';
    echo json_encode(['Success'=>false, 'Message'=>'MPesa API Error: ' . $errMessage]);
}
?>

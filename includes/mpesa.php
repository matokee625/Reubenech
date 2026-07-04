<?php
// M-Pesa Daraja API Express (STK Push) Integration Client

class MpesaClient {
    private $consumerKey;
    private $consumerSecret;
    private $businessShortCode;
    private $passkey;
    private $callbackUrl;
    private $environment;
    private $mockMode;

    public function __construct() {
        global $conn;

        // Default configurations (Safaricom Sandbox test credentials)
        $this->consumerKey = '0ndG3QQ5J3Lpu9DDql0vQjxyZ0v0GR7f8iaQKtnFJpOfSqmn';
        $this->consumerSecret = 'cwUrRrprxZAMsILXl1ANKKApl3yrIs8Mln3ubAfxFDfA2slfc3A744gjPjwyAxTw';
        $this->businessShortCode = '174379'; // Lipa Na Mpesa Online Sandbox Shortcode
        $this->passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; // Sandbox Passkey
        $this->callbackUrl = 'http://localhost/milkproject/member/mpesa_callback.php';
        $this->environment = 'sandbox'; // 'sandbox' or 'live'
        $this->mockMode = false; // Default to FALSE to run real API calls

        // Attempt to load settings dynamically from settings table
        if (isset($conn)) {
            try {
                $stmt = $conn->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'mpesa_%'");
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                if (isset($settings['mpesa_consumer_key'])) $this->consumerKey = $settings['mpesa_consumer_key'];
                if (isset($settings['mpesa_consumer_secret'])) $this->consumerSecret = $settings['mpesa_consumer_secret'];
                if (isset($settings['mpesa_shortcode'])) $this->businessShortCode = $settings['mpesa_shortcode'];
                if (isset($settings['mpesa_passkey'])) $this->passkey = $settings['mpesa_passkey'];
                if (isset($settings['mpesa_callback_url'])) $this->callbackUrl = $settings['mpesa_callback_url'];
                if (isset($settings['mpesa_environment'])) $this->environment = $settings['mpesa_environment'];
                if (isset($settings['mpesa_mock_mode'])) $this->mockMode = (bool)$settings['mpesa_mock_mode'];
            } catch (Exception $e) {
                // Table might not exist or connection failed; fallback to defaults
            }
        }
    }

    /**
     * Formats Kenyan phone number to Safaricom standard (2547XXXXXXXX or 2541XXXXXXXX)
     */
    public function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) === '254') {
            // Already standard format
        } elseif (strlen($phone) === 9) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }

    /**
     * Initiates STK Push transaction
     */
    public function initiateStkPush($phone, $amount, $reference, $description = 'Cooperative Verification') {
        $formattedPhone = $this->formatPhoneNumber($phone);
        $amount = round(floatval($amount));

        if ($this->mockMode) {
            // Log the simulated STK Push trigger
            $timestamp = date('Y-m-d H:i:s');
            $checkoutRequestId = 'ws_CO_' . md5(uniqid(rand(), true)) . '_' . time();
            $log_entry = "[$timestamp] M-PESA MOCK STK PUSH: Phone: $formattedPhone | Amount: Ksh $amount | Ref: $reference | CheckoutRequestID: $checkoutRequestId\n";
            file_put_contents(__DIR__ . '/../sms_log.txt', $log_entry, FILE_APPEND);

            return [
                'status' => 'success',
                'mode' => 'mock',
                'CheckoutRequestID' => $checkoutRequestId,
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing'
            ];
        }

        // Real Safaricom API Connection
        $accessToken = $this->generateAccessToken();
        if (!$accessToken) {
            return [
                'status' => 'error',
                'message' => 'Failed to generate Safaricom access token.'
            ];
        }

        $url = $this->environment === 'live' 
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $timestamp = date('YmdHis');
        $password = base64_encode($this->businessShortCode . $this->passkey . $timestamp);

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        // Safaricom Daraja API requires a valid public HTTPS URL and explicitly rejects localhost/127.0.0.1.
        // If testing locally, we dynamically rewrite it to a placeholder HTTPS domain to pass Safaricom's validation rules,
        // while allowing production/ngrok callback URLs to be used as-is.
        $callbackUrl = $this->callbackUrl;
        if (strpos($callbackUrl, 'localhost') !== false || strpos($callbackUrl, '127.0.0.1') !== false) {
            $callbackUrl = 'https://reubentech.co.ke/milkproject/member/mpesa_callback.php';
        }

        $curl_post_data = [
            'BusinessShortCode' => $this->businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $formattedPhone,
            'PartyB' => $this->businessShortCode,
            'PhoneNumber' => $formattedPhone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $reference,
            'TransactionDesc' => $description
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing setup

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return [
                'status' => 'error',
                'message' => 'Curl Error: ' . $err
            ];
        }

        $data = json_decode($response, true);
        if (isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
            return [
                'status' => 'success',
                'mode' => 'production',
                'CheckoutRequestID' => $data['CheckoutRequestID'],
                'ResponseCode' => $data['ResponseCode'],
                'ResponseDescription' => $data['ResponseDescription']
            ];
        } else {
            return [
                'status' => 'error',
                'message' => $data['errorMessage'] ?? ($data['ResponseDescription'] ?? 'M-Pesa push failed')
            ];
        }
    }

    /**
     * Generates OAuth token from Safaricom credentials
     */
    private function generateAccessToken() {
        $url = $this->environment === 'live'
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
    }
}

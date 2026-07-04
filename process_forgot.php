<?php
// process_forgot.php - Handles forgot password submissions
require_once __DIR__ . '/includes/password_reset.php';
require_once __DIR__ . '/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit();
}

$identifier = trim($_POST['identifier'] ?? '');
if ($identifier === '') {
    header('Location: forgot_password.php?error=empty');
    exit();
}

// Determine if identifier is an email or phone
if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    $stmt = $conn->prepare('SELECT id, email, phone FROM users WHERE email = :id LIMIT 1');
    $stmt->execute(['id' => $identifier]);
} else {
    // Normalize phone number (remove non-digits)
    $phone = preg_replace('/\D+/', '', $identifier);
    $stmt = $conn->prepare('SELECT id, email, phone FROM users WHERE REPLACE(phone, "+", "") = :phone LIMIT 1');
    $stmt->execute(['phone' => $phone]);
}

$user = $stmt->fetch(PDO::FETCH_OBJ);
if ($user) {
    // Create a secure token and store hashed version
    $token = createPasswordResetToken((int)$user->id);
    // Send reset link via email
    if (!empty($user->email)) {
        sendPasswordResetEmail($user->email, $token);
    }
    // Send reset link via SMS if phone exists
    if (!empty($user->phone)) {
        sendPasswordResetSMS($user->phone, $token);
    }
}

// Redirect to the same page with a flag to avoid user enumeration
header('Location: forgot_password.php?sent=1');
exit();
?>

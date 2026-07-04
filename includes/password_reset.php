<?php
// includes/password_reset.php
// Helper functions for the forgot password workflow.
// Requires connection.php, email_sender.php and sms.php.

require_once __DIR__ . '/email_sender.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../connection.php';

/**
 * Generate a secure random token and store its hash in the password_resets table.
 * Returns the plain token (to be sent to the user).
 */
function createPasswordResetToken(int $userId): string {
    // Generate 32 bytes (64 hex characters)
    $token = bin2hex(random_bytes(32));
    $hashedToken = password_hash($token, PASSWORD_DEFAULT);
    // Token expiry set to 15 minutes for security
$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    // Insert into DB
    $stmt = $GLOBALS['conn']->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires)');
    $stmt->execute([
        'user_id' => $userId,
        'token'   => $hashedToken,
        'expires' => $expiresAt,
    ]);
    return $token;
}

/**
 * Verify a token supplied by the user.
 * Returns the associated user ID if valid, otherwise false.
 */
function verifyPasswordResetToken(string $token): int|false {
    $stmt = $GLOBALS['conn']->prepare('SELECT id, user_id, token, expires_at FROM password_resets WHERE expires_at > NOW()');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (password_verify($token, $row['token'])) {
            return (int)$row['user_id'];
        }
    }
    return false;
}

/**
 * Delete a token after successful reset.
 */
function deletePasswordResetToken(string $token): void {
    // Find the hashed token first
    $stmt = $GLOBALS['conn']->prepare('SELECT token FROM password_resets');
    $stmt->execute();
    $hashedTokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($hashedTokens as $hashed) {
        if (password_verify($token, $hashed)) {
            $del = $GLOBALS['conn']->prepare('DELETE FROM password_resets WHERE token = :hashed');
            $del->execute(['hashed' => $hashed]);
            break;
        }
    }
}

/**
 * Send password reset link via email.
 */
function sendPasswordResetEmail(string $email, string $token): bool {
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/milkproject/reset_password.php?token=" . urlencode($token);
    $subject = "Password Reset Request";
    $body = "<p>You requested a password reset. Click the link below to set a new password. This link expires in 15 minutes.</p>" .
            "<p><a href='$resetLink'>Reset Password</a></p>";
    return sendEmail($email, $subject, $body);
}

/**
 * Send password reset link via SMS (logged via sms.php).
 */
function sendPasswordResetSMS(string $phone, string $token): void {
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/milkproject/reset_password.php?token=" . urlencode($token);
    $message = "Password reset link: $resetLink";
    // sms.php uses a configured site phone; we just log the message.
    sendSMSAlert($message);
}
?>

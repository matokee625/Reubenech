<?php
// reset_password.php - Handles password reset using token
require_once __DIR__ . '/includes/password_reset.php';
require_once __DIR__ . '/connection.php';

$token = $_GET['token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form submission to set new password
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (empty($newPassword) || $newPassword !== $confirmPassword) {
        $error = 'Passwords do not match or are empty.';
    } else {
        $userId = verifyPasswordResetToken($token);
        if ($userId) {
            // Update user password
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = :pwd WHERE id = :id');
            $stmt->execute(['pwd' => $hashed, 'id' => $userId]);
            // Delete used token
            deletePasswordResetToken($token);
            // Redirect to login with success flag
            header('Location: login.php?reset=success');
            exit();
        } else {
            $error = 'Invalid or expired token.';
        }
    }
}

// If GET request, verify token validity for display
if (!empty($token)) {
    $validUserId = verifyPasswordResetToken($token);
    if (!$validUserId) {
        $error = 'Invalid or expired token.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
<div class="login-card" style="max-width:400px;margin:auto;margin-top:5rem;">
    <div class="login-header-bar">Reset Your Password</div>
    <div class="login-body">
        <?php if (isset($error)): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (empty($token) || !$validUserId): ?>
            <div class="error-box">Invalid request.</div>
        <?php else: ?>
            <form method="POST" action="reset_password.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="input-container">
                    <input type="password" name="new_password" placeholder="New Password" required>
                </div>
                <div class="input-container">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                <button type="submit" class="btn-login">Set New Password</button>
            </form>
        <?php endif; ?>
        <div class="bottom-links"><a href="login.php">Back to login</a></div>
    </div>
</div>
</body>
</html>

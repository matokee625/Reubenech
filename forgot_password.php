<?php
// forgot_password.php
require_once __DIR__ . '/includes/password_reset.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="login-card">
        <div class="login-header-bar">Reset Your Password</div>
        <div class="login-body">
            <?php
            if (isset($_GET['sent'])) {
                echo '<div class="success-box">A reset link has been sent if the account exists.</div>';
            }
            ?>
            <form method="POST" action="process_forgot.php">
                <div class="input-container">
                    <input type="text" name="identifier" placeholder="Email or Phone" required>
                </div>
                <button type="submit" class="btn-login">Send Reset Link</button>
            </form>
            <div class="bottom-links"><a href="login.php">Back to login</a></div>
        </div>
    </div>
</body>
</html>
?>

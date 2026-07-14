<?php
session_start();
require_once 'connection.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['user_role'] === 'admin' ? "admin/users.php" : "member/dashboard.php"));
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $milk_type = trim($_POST['milk_type'] ?? '');
    $liters = trim($_POST['liters'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($username) && !empty($email) && !empty($password) && !empty($confirm_password)) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else if (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            // Check if username or email exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                try {
                    $conn->beginTransaction();

                    // Insert user
                    $insert = $conn->prepare("INSERT INTO users (username, email, phone, password, role, status, has_paid) VALUES (?, ?, ?, ?, 'member', 'suspended', 0)");
                    $insert->execute([$username, $email, $phone, $hash]);
                    
                    $new_user_id = $conn->lastInsertId();

                    // Optional: Auto-seed active milk supply posting if user provided initial supply info
                    if (!empty($milk_type) && !empty($liters) && is_numeric($liters) && $liters > 0) {
                        $post_stmt = $conn->prepare("INSERT INTO milk_postings (user_id, liters, milk_type, asking_price, status) VALUES (?, ?, ?, 40.00, 'active')");
                        $post_stmt->execute([$new_user_id, $liters, $milk_type]);
                    }

                    $conn->commit();

                    // Send registration notification SMS
                    require_once 'includes/sms.php';
                    sendSMSAlert("PLEASE APROVE SOMEONE WHO HAS CREATED AN ACCOUNT");

                    $success = "Registration successful! Your account is pending administrator approval. You will be able to login once approved.";
                } catch (PDOException $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    $error = "An error occurred during registration. Please try again.";
                }
            }
        }
    } else {
        $error = "Please fill out all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an Account - REUBENTECH MILK SOLUTIONS</title>
    <link rel="icon" href="favicon.php" type="image/x-icon">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --font-heading: 'Outfit', sans-serif;
            --font-body: 'Inter', sans-serif;
            --color-brand: #3b5998;
            --color-brand-hover: #2d4373;
            --color-bg-start: #667eea;
            --color-bg-end: #764ba2;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #6b11cb 100%);
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(16px) saturate(120%);
            -webkit-backdrop-filter: blur(16px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.45);
        }

        .login-header-bar {
            background: #3b5998;
            background: linear-gradient(90deg, #3b5998 0%, #4c70ba 100%);
            color: white;
            font-family: var(--font-heading);
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-align: center;
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-body {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .avatar-circle {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            color: rgba(255, 255, 255, 0.7);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .avatar-circle svg {
            width: 50px;
            height: 50px;
            opacity: 0.8;
        }

        .input-container {
            position: relative;
            width: 100%;
            margin-bottom: 1.25rem;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .input-icon svg {
            width: 18px;
            height: 18px;
        }

        .input-container input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.75rem;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            font-size: 0.95rem;
            color: #1e293b;
            outline: none;
            transition: all 0.2s ease;
        }

        .input-container input:focus {
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.4);
        }

        .input-container input::placeholder {
            color: #94a3b8;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            background: rgba(255, 255, 255, 0.8);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }

        .error-box {
            width: 100%;
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .success-box {
            width: 100%;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #a7f3d0;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .extras-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 2rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.85);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .remember-me input {
            cursor: pointer;
            accent-color: var(--color-brand);
        }

        .forgot-link {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: white;
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 0.9rem;
            background: #3b5998;
            color: white;
            border: none;
            border-radius: 4px;
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            text-align: center;
        }

        .btn-login:hover {
            background: #2d4373;
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-box {
            width: 100%;
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .success-box {
            width: 100%;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #a7f3d0;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .bottom-links {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .bottom-links a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        .bottom-links a:hover {
            text-decoration: underline;
        }

        .btn-home {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 1.5rem;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-home:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
    <script>
        // Handle local file protocol (file://) redirection to prevent downloads
        document.addEventListener("DOMContentLoaded", function() {
            var isFile = window.location.protocol === 'file:';
            var isLiveServer = window.location.port !== '' && window.location.port !== '80' && window.location.port !== '443';

            if (isFile || isLiveServer) {
                // 1. Rewrite Form Action to target the secure local server
                var form = document.querySelector("form");
                if (form) {
                    form.action = "http://localhost/milkproject/register.php";
                }

                // 2. Rewrite Links to open through Apache local server
                document.querySelectorAll("a").forEach(function(link) {
                    var href = link.getAttribute("href");
                    if (href && !href.startsWith("http://") && !href.startsWith("https://") && !href.startsWith("#") && !href.startsWith("javascript:")) {
                        if (href.indexOf(".php") !== -1 || href === "login.html") {
                            var target = href;
                            if (href.startsWith("../")) {
                                target = href.substring(3);
                            }
                            link.setAttribute("href", "http://localhost/milkproject/" + target);
                        }
                    }
                });
            }
        });
    </script>
</head>
<body>

    <!-- Back to Homepage -->
    <a href="index.php" class="btn-home">← Back to Homepage</a>

    <!-- Glassmorphic Card -->
    <div class="login-card">
        <!-- Mockup Title Bar -->
        <div class="login-header-bar">
            REGISTER FOR REUBENTECH COOPERATIVE
        </div>

        <div class="login-body">
            <!-- Profile Avatar Silhouette -->
            <div class="avatar-circle">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>

            <!-- Notifications -->
            <?php if($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="success-box"><?= $success ?></div>
            <?php endif; ?>

            <?php if(!$success): ?>
            <!-- Registration Form -->
            <form method="POST" action="register.php" style="width: 100%;">
                <!-- Username Input -->
                <div class="input-container">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    <input type="text" id="username" name="username" placeholder="Username" value="<?= htmlspecialchars($username ?? '') ?>" required>
                </div>

                <!-- Email Input -->
                <div class="input-container">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </span>
                    <input type="email" id="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($email ?? '') ?>" required>
                </div>

                <!-- Phone Input -->
                <div class="input-container">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    </span>
                    <input type="tel" id="phone" name="phone" placeholder="Phone Number (+254...)" value="<?= htmlspecialchars($phone ?? '') ?>">
                </div>

                <!-- Milk Type & Volume Row -->
                <div style="display: flex; gap: 1rem; margin-bottom: 1.25rem;">
                    <!-- Milk Type -->
                    <div class="input-container" style="flex: 1; margin-bottom: 0;">
                        <select id="milk_type" name="milk_type" style="width: 100%; padding: 0.9rem 1rem; background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 4px; font-size: 0.95rem; color: #1e293b; outline: none; appearance: none;">
                            <option value="" disabled selected>Select Milk Type...</option>
                            <option value="Cow" <?= (isset($milk_type) && $milk_type === 'Cow') ? 'selected' : '' ?>>Cow Milk</option>
                            <option value="Goat" <?= (isset($milk_type) && $milk_type === 'Goat') ? 'selected' : '' ?>>Goat Milk</option>
                            <option value="Camel" <?= (isset($milk_type) && $milk_type === 'Camel') ? 'selected' : '' ?>>Camel Milk</option>
                        </select>
                    </div>

                    <!-- Initial Volume -->
                    <div class="input-container" style="flex: 1; margin-bottom: 0;">
                        <input type="number" id="liters" name="liters" placeholder="Initial Volume" value="<?= htmlspecialchars($liters ?? '') ?>" step="0.1" min="0" style="width: 100%; padding: 0.9rem 1rem; background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 4px; font-size: 0.95rem; color: #1e293b; outline: none;">
                    </div>
                </div>

                <!-- Password Input -->
                <div class="input-container">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password" placeholder="Password (Min. 8 characters)" required>
                    <span class="toggle-password" onclick="togglePassword('password')">Show</span>
                </div>

                <!-- Confirm Password Input -->
                <div class="input-container">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                    <span class="toggle-password" onclick="togglePassword('confirm_password')">Show</span>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login">REGISTER</button>
            </form>
            <?php endif; ?>

            <!-- Bottom Link -->
            <div class="bottom-links">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            var passwordField = document.getElementById(fieldId);
            var toggleText = passwordField.nextElementSibling;
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleText.textContent = "Hide";
            } else {
                passwordField.type = "password";
                toggleText.textContent = "Show";
            }
        }
    </script>
</body>
</html>
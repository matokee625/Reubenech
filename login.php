<?php
session_start();
require_once 'connection.php';

// If user is already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin/homepage.php");
    } else {
        header("Location: member/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identity = trim($_POST['email'] ?? ''); // Maps to username/email field
    $password = $_POST['password'] ?? '';

    if (!empty($login_identity) && !empty($password)) {
        try {
            // Support login with either Email or Username
            $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE email = :login OR username = :login LIMIT 1");
            $stmt->execute(['login' => $login_identity]);
            $user = $stmt->fetch(PDO::FETCH_OBJ);

            if ($user && password_verify($password, $user->password)) {
                if ($user->status === 'suspended') {
                    $error = "Your account has been suspended.";
                } else if ($user->status === 'trash') {
                    $error = "Your account has been deleted.";
                } else {
                    // Success
                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['username'] = $user->username;
                    $_SESSION['user_role'] = $user->role;

                    // Update last login
                    $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user->id]);

                    // Log activity
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $log_stmt = $conn->prepare("INSERT INTO access_logs (user_id, username, action, ip_address, user_agent) VALUES (?, ?, 'login', ?, ?)");
                    $log_stmt->execute([$user->id, $user->username, $ip_address, $user_agent]);

                    // Trigger SMS alert on successful login to configured phone
                    require_once 'includes/sms.php';
                    sendSMSAlert("Security Notice: User '{$user->username}' (Role: {$user->role}) logged in successfully from IP: {$ip_address}.");

                    if ($user->role === 'admin') {
                        header("Location: admin/homepage.php");
                    } else {
                        header("Location: member/dashboard.php");
                    }
                    exit();
                }
            } else {
                $error = "Invalid username/email or password.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGIN TO REUBENTECH MILK SOLUTIONS</title>
    <meta name="description" content="Sign in to your Reubentech Hub account to access the milk production marketplace.">
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
                    form.action = "http://localhost/milkproject/login.php";
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

    <!-- Glassmorphic Login Card -->
    <div class="login-card">
        <!-- Mockup Title Bar -->
        <div class="login-header-bar">
            LOGIN TO REUBENTECH MILK SOLUTIONS
        </div>

        <div class="login-body">
            <!-- Profile Silhouette Avatar -->
            <div class="avatar-circle">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>

            <!-- Error Notification -->
            <?php if($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php" style="width: 100%;">
                <!-- Username Input (accepts username or email on backend) -->
                <div class="input-container">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    <input type="text" id="email" name="email" placeholder="Username" autocomplete="username" value="<?= htmlspecialchars($login_identity ?? '') ?>" required>
                </div>

                <!-- Password Input -->
                <div class="input-container">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password" placeholder="************" autocomplete="current-password" required>
                    <span class="toggle-password" onclick="togglePassword()">Show</span>
                </div>

                <!-- Remember & Forgot Password -->
                <div class="extras-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login">LOGIN</button>
            </form>

            <!-- Bottom Links -->
            <div class="bottom-links">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            var passwordField = document.getElementById("password");
            var toggleText = document.querySelector(".toggle-password");
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

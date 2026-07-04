<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'member';
    $status   = $_POST['status'] ?? 'active';

    if (!empty($username) && !empty($email) && !empty($password)) {
        try {
            // Check if username/email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->rowCount() > 0) {
                $error = "Username or Email already registered.";
            } else {
                // Insert new user
                $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, role, status, registered_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $email, $phone, $hashed_pass, $role, $status]);
                $success = "User created successfully! <a href='users.php'>View users list</a>.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "All fields are required.";
    }
}

adminHeader('users', 'User List');
?>

<div class="page-header">
    <h1>➕ ADD NEW USER</h1>
    <a href="users.php" class="btn btn-outline">← Back to List</a>
</div>

<?php if($success): ?>
<div class="alert alert-success">✅ <?= $success ?></div>
<?php endif; ?>
<?php if($error): ?>
<div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card" style="max-width: 600px; padding: 2rem;">
    <form method="POST" class="settings-form">
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Username</label>
            <input type="text" name="username" required style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px;">
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Email Address</label>
            <input type="email" name="email" required style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px;">
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Phone Number</label>
            <input type="text" name="phone" placeholder="e.g. 0799031535" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px;">
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Password</label>
            <input type="password" name="password" required style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px;">
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label style="display:block; font-weight:600; margin-bottom:0.5rem;">System Role</label>
            <select name="role" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px; background:white;">
                <option value="member">Member</option>
                <option value="admin">Administrator</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Initial Status</label>
            <select name="status" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px; background:white;">
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;">💾 Save User</button>
    </form>
</div>

<?php adminFooter(); ?>

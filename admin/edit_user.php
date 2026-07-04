<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

$id    = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($id <= 0) {
    header("Location: users.php");
    exit();
}

// Fetch user
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) { header("Location: users.php"); exit(); }
} catch(PDOException $e) { header("Location: users.php"); exit(); }

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = $_POST['role'] ?? 'member';
    $status   = $_POST['status'] ?? 'active';
    $new_pass = $_POST['new_password'] ?? '';

    try {
        if (!empty($new_pass)) {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $conn->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, status=?, password=? WHERE id=?")
                 ->execute([$username, $email, $phone, $role, $status, $hash, $id]);
        } else {
            $conn->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, status=? WHERE id=?")
                 ->execute([$username, $email, $phone, $role, $status, $id]);
        }
        $success = "User updated successfully.";
        $user->username = $username;
        $user->email = $email;
        $user->phone = $phone;
        $user->role = $role;
        $user->status = $status;
    } catch(PDOException $e) {
        $error = $e->getMessage();
    }
}

adminHeader('users');
?>

<div class="page-header">
    <h1>✏️ EDIT USER</h1>
    <a href="users.php" class="btn btn-outline">← Back to Users</a>
</div>

<div style="max-width:600px">
    <div class="table-card" style="padding:1.5rem">
        <?php if($error): ?><div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div><br><?php endif; ?>
        <?php if($success): ?><div style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:.75rem 1rem; border-radius:6px; margin-bottom:1rem">✅ <?= $success ?></div><?php endif; ?>

        <form method="POST">
            <div style="margin-bottom:1rem">
                <label style="display:block; font-weight:600; margin-bottom:.4rem; font-size:.875rem">Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user->username) ?>" required
                    style="width:100%; padding:.6rem .75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:.875rem">
            </div>
            <div style="margin-bottom:1rem">
                <label style="display:block; font-weight:600; margin-bottom:.4rem; font-size:.875rem">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user->email) ?>" required
                    style="width:100%; padding:.6rem .75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:.875rem">
            </div>
            <div style="margin-bottom:1rem">
                <label style="display:block; font-weight:600; margin-bottom:.4rem; font-size:.875rem">Phone Number</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user->phone ?? '') ?>" placeholder="e.g. 0799031535"
                    style="width:100%; padding:.6rem .75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:.875rem">
            </div>
            <div style="margin-bottom:1rem">
                <label style="display:block; font-weight:600; margin-bottom:.4rem; font-size:.875rem">Role</label>
                <select name="role" style="width:100%; padding:.6rem .75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:.875rem">
                    <option value="member" <?= $user->role==='member'?'selected':'' ?>>Member</option>
                    <option value="admin"  <?= $user->role==='admin' ?'selected':'' ?>>Admin</option>
                </select>
            </div>
            <div style="margin-bottom:1rem">
                <label style="display:block; font-weight:600; margin-bottom:.4rem; font-size:.875rem">Status</label>
                <select name="status" style="width:100%; padding:.6rem .75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:.875rem">
                    <option value="active"    <?= $user->status==='active'   ?'selected':'' ?>>Active</option>
                    <option value="suspended" <?= $user->status==='suspended'?'selected':'' ?>>Suspended</option>
                    <option value="trash"     <?= $user->status==='trash'    ?'selected':'' ?>>Trash</option>
                </select>
            </div>
            <div style="margin-bottom:1.5rem">
                <label style="display:block; font-weight:600; margin-bottom:.4rem; font-size:.875rem">New Password <small style="color:#94a3b8">(leave blank to keep current)</small></label>
                <input type="password" name="new_password" placeholder="Enter new password"
                    style="width:100%; padding:.6rem .75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:.875rem">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">💾 Save Changes</button>
        </form>
    </div>
</div>

<?php adminFooter(); ?>

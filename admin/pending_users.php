<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// Fetch pending users
try {
    $stmt = $conn->prepare('SELECT id, username, email, phone, registered_at FROM users WHERE status = ?');
    $stmt->execute(['pending']);
    $pendingUsers = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $pendingUsers = [];
    $error = $e->getMessage();
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid > 0) {
        try {
            if ($action === 'approve') {
                $conn->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['active', $uid]);
            } elseif ($action === 'reject') {
                $conn->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            }
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
        // Refresh page after action
        header('Location: pending_users.php');
        exit();
    }
}

adminHeader('pending_users', 'Pending Registrations');
?>

<div class="page-header">
    <h1>🚦 Pending User Registrations</h1>
</div>

<?php if (isset($error)): ?>
    <div class="error-msg" style="color:#dc2626;">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (count($pendingUsers) > 0): ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingUsers as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u->username) ?></td>
                    <td><?= htmlspecialchars($u->email) ?></td>
                    <td><?= htmlspecialchars($u->phone ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($u->registered_at) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?= $u->id ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" style="border:none;background:none;color:#16a34a;cursor:pointer;" onclick="return confirm('Approve this user?');">✅ Approve</button>
                        </form>
                        <form method="POST" style="display:inline; margin-left:0.5rem;">
                            <input type="hidden" name="user_id" value="<?= $u->id ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" style="border:none;background:none;color:#dc2626;cursor:pointer;" onclick="return confirm('Reject and delete this user?');">✖️ Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>No pending registrations at the moment.</p>
<?php endif; ?>

<?php adminFooter(); ?>

<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// Fetch last 50 login events from the real access_logs table
try {
    $logs = $conn->query("
        SELECT al.id, al.username, al.action, al.ip_address, al.user_agent, al.created_at, u.email, u.role, u.status
        FROM access_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 50
    ")->fetchAll();
} catch(PDOException $e) { $logs = []; }

adminHeader('users', 'Access Logs');
?>

<div class="page-header">
    <h1>🔒 ACCESS LOGS</h1>
</div>

<div class="table-card">
    <div class="table-toolbar"><strong>Last Login Activity (most recent first)</strong></div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>IP Address</th>
                <th>User Agent</th>
                <th>Login Time</th>
            </tr>
        </thead>
        <tbody>
        <?php if(count($logs) > 0): ?>
            <?php foreach($logs as $l): ?>
            <tr>
                <td><strong><?= htmlspecialchars($l->username) ?></strong></td>
                <td><?= htmlspecialchars($l->email ?? 'N/A') ?></td>
                <td>
                    <?php if ($l->role): ?>
                        <span class="badge <?= $l->role==='admin'?'badge-admin':'badge-member' ?>"><?= ucfirst($l->role) ?></span>
                    <?php else: ?>
                        <span class="badge badge-member">N/A</span>
                    <?php endif; ?>
                </td>
                <td><code><?= htmlspecialchars($l->ip_address ?? 'Unknown') ?></code></td>
                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($l->user_agent ?? '') ?>">
                    <?= htmlspecialchars($l->user_agent ?? 'Unknown') ?>
                </td>
                <td><?= htmlspecialchars($l->created_at) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6"><div class="empty-state"><span class="icon">🔒</span>No login activity found.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div class="table-footer">
        <div>Showing: <strong><?= count($logs) ?></strong> recent login event<?= count($logs)!==1?'s':'' ?></div>
    </div>
</div>

<?php adminFooter(); ?>

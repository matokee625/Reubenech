<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// Fetch all dashboard metrics
try {
    $total_users     = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_members   = $conn->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();
    $active_users    = $conn->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    $total_postings  = $conn->query("SELECT COUNT(*) FROM milk_postings")->fetchColumn();
    $active_postings = $conn->query("SELECT COUNT(*) FROM milk_postings WHERE status='active'")->fetchColumn();
    $total_volume    = $conn->query("SELECT SUM(liters) FROM milk_postings")->fetchColumn() ?? 0;
    $total_revenue   = $conn->query("SELECT SUM(liters * asking_price) FROM milk_postings WHERE status='sold'")->fetchColumn() ?? 0;
    $total_ads       = $conn->query("SELECT COUNT(*) FROM advertisements")->fetchColumn();
    $total_trans     = $conn->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $pending_trans   = $conn->query("SELECT COUNT(*) FROM transactions WHERE status='pending'")->fetchColumn();

    // Recent users
    $recent_users = $conn->query("SELECT username, email, role, status, registered_at FROM users ORDER BY registered_at DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

    // Recent postings
    $recent_posts = $conn->query("SELECT mp.milk_type, mp.liters, mp.asking_price, mp.status, mp.posted_at, u.username
        FROM milk_postings mp JOIN users u ON mp.user_id = u.id
        ORDER BY mp.posted_at DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

    // Monthly registration counts (last 6 months)
    $monthly = $conn->query("
        SELECT DATE_FORMAT(registered_at,'%b') as mon, COUNT(*) as cnt
        FROM users
        WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(registered_at), MONTH(registered_at)
        ORDER BY registered_at ASC
    ")->fetchAll();

    // Unread notifications count
    $unread_notifs = 0;
    try {
        $unread_notifs = $conn->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn();
    } catch(Exception $e) {}

} catch(PDOException $e) {
    $total_users=$total_members=$active_users=$total_postings=$active_postings=0;
    $total_volume=$total_revenue=$total_ads=$total_trans=$pending_trans=0;
    $recent_users=$recent_posts=$monthly=[];
    $unread_notifs=0;
}

adminHeader('homepage', '');
?>

<div class="page-header">
    <div>
        <h1>🏠 ADMIN DASHBOARD</h1>
        <p style="color:#64748b;font-size:0.875rem;margin-top:0.25rem;">
            Welcome back, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong> — <?= date('l, d F Y') ?>
        </p>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center;">
        <?php if($unread_notifs > 0): ?>
        <a href="notifications.php" class="btn btn-outline" style="position:relative;">
            🔔 Notifications
            <span class="notif-badge"><?= $unread_notifs ?></span>
        </a>
        <?php endif; ?>
        <a href="reports.php" class="btn btn-primary">📈 View Reports</a>
    </div>
</div>

<!-- KPI Stats Row -->
<div class="stats-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
    <div class="stat-card blue">
        <div class="stat-card-label">Total Users</div>
        <div class="stat-card-value"><?= $total_users ?></div>
        <div class="stat-card-sub"><?= $active_users ?> active</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Members</div>
        <div class="stat-card-value"><?= $total_members ?></div>
        <div class="stat-card-sub">Registered members</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-card-label">Milk Postings</div>
        <div class="stat-card-value"><?= $total_postings ?></div>
        <div class="stat-card-sub"><?= $active_postings ?> active listings</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-label">Total Volume</div>
        <div class="stat-card-value"><?= number_format($total_volume) ?>L</div>
        <div class="stat-card-sub">All posted litres</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Revenue (Ksh)</div>
        <div class="stat-card-value"><?= number_format($total_revenue) ?></div>
        <div class="stat-card-sub">From sold postings</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Transactions</div>
        <div class="stat-card-value"><?= $total_trans ?></div>
        <div class="stat-card-sub"><?= $pending_trans ?> pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Advertisements</div>
        <div class="stat-card-value"><?= $total_ads ?></div>
        <div class="stat-card-sub">Community posts</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="section-heading">⚡ Quick Actions</div>
<div class="quick-actions-grid">
    <a href="users.php" class="quick-action-card">
        <span class="qa-icon">👥</span>
        <span class="qa-label">Manage Users</span>
    </a>
    <a href="posts.php" class="quick-action-card">
        <span class="qa-icon">🥛</span>
        <span class="qa-label">Milk Postings</span>
    </a>
    <a href="transactions.php" class="quick-action-card">
        <span class="qa-icon">💳</span>
        <span class="qa-label">Transactions</span>
    </a>
    <a href="reports.php" class="quick-action-card">
        <span class="qa-icon">📈</span>
        <span class="qa-label">Reports</span>
    </a>
    <a href="notifications.php" class="quick-action-card">
        <span class="qa-icon">🔔</span>
        <span class="qa-label">Notifications</span>
    </a>
    <a href="settings.php" class="quick-action-card">
        <span class="qa-icon">⚙️</span>
        <span class="qa-label">Settings</span>
    </a>
</div>

<!-- Recent Activity: Two columns -->
<div class="dashboard-grid">

    <!-- Recent Users -->
    <div class="table-card">
        <div class="table-toolbar">
            <strong>🆕 Recent Registrations</strong>
            <a href="users.php" class="btn btn-outline" style="font-size:0.75rem;padding:0.3rem 0.75rem;">View All</a>
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
            <?php if(count($recent_users)): ?>
                <?php foreach($recent_users as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u->username) ?></strong><br>
                        <small style="color:#94a3b8;"><?= htmlspecialchars($u->email) ?></small>
                    </td>
                    <td>
                        <?php if($u->role==='admin'): ?>
                            <span class="badge badge-admin">Admin</span>
                        <?php else: ?>
                            <span class="badge badge-member">Member</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($u->status==='active'): ?>
                            <span class="badge badge-active">Active</span>
                        <?php elseif($u->status==='suspended'): ?>
                            <span class="badge badge-suspended">Suspended</span>
                        <?php else: ?>
                            <span class="badge badge-trash">Trash</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#64748b;font-size:0.75rem;"><?= date('d M Y', strtotime($u->registered_at)) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4"><div class="empty-state"><span class="icon">👥</span>No users yet.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Milk Postings -->
    <div class="table-card">
        <div class="table-toolbar">
            <strong>🥛 Recent Milk Postings</strong>
            <a href="posts.php" class="btn btn-outline" style="font-size:0.75rem;padding:0.3rem 0.75rem;">View All</a>
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Seller</th>
                    <th>Type</th>
                    <th>Vol (L)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if(count($recent_posts)): ?>
                <?php foreach($recent_posts as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p->username) ?></strong><br>
                        <small style="color:#94a3b8;"><?= date('d M', strtotime($p->posted_at)) ?></small>
                    </td>
                    <td><?= htmlspecialchars($p->milk_type) ?></td>
                    <td><?= number_format($p->liters, 1) ?></td>
                    <td>
                        <?php if($p->status==='active'): ?>
                            <span class="badge badge-active">Active</span>
                        <?php elseif($p->status==='sold'): ?>
                            <span class="badge badge-completed">Sold</span>
                        <?php else: ?>
                            <span class="badge badge-cancelled">Cancelled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4"><div class="empty-state"><span class="icon">🥛</span>No postings yet.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php adminFooter(); ?>

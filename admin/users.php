<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// --- Filtering & Search ---
$status_filter = $_GET['status'] ?? 'all';
$search_query  = trim($_GET['search'] ?? '');

$sql    = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND status = :status";
    $params['status'] = $status_filter;
}
if (!empty($search_query)) {
    $sql .= " AND (username LIKE :search OR email LIKE :search)";
    $params['search'] = "%$search_query%";
}
$sql .= " ORDER BY registered_at DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) { $users = []; $error = $e->getMessage(); }

// --- Tab Counts ---
try {
    $count_all       = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $count_active    = $conn->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    $count_suspended = $conn->query("SELECT COUNT(*) FROM users WHERE status='suspended'")->fetchColumn();
    $count_trash     = $conn->query("SELECT COUNT(*) FROM users WHERE status='trash'")->fetchColumn();
    $count_admins    = $conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
} catch (PDOException $e) { $count_all=$count_active=$count_suspended=$count_trash=$count_admins=0; }

// --- Handle Quick Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);
    if ($uid > 0) {
        try {
            if ($action === 'suspend') {
                $conn->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$uid]);
            } elseif ($action === 'activate') {
                $conn->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$uid]);
                $u_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ? LIMIT 1");
                $u_stmt->execute([$uid]);
                $u_info = $u_stmt->fetch(PDO::FETCH_OBJ);
                if ($u_info) {
                    require_once '../includes/sms.php';
                    sendSMSAlert("Cooperative Account Approved: User '" . $u_info->username . "' has been approved and activated by the administrator.");
                }
            } elseif ($action === 'delete') {
                if ($uid !== $_SESSION['user_id']) {
                    $conn->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
                }
            } elseif ($action === 'trash') {
                $conn->prepare("UPDATE users SET status='trash' WHERE id=?")->execute([$uid]);
            } elseif ($action === 'make_admin') {
                $conn->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$uid]);
            } elseif ($action === 'approve_payment') {
                $conn->prepare("UPDATE users SET has_paid=1 WHERE id=?")->execute([$uid]);
                $u_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ? LIMIT 1");
                $u_stmt->execute([$uid]);
                $u_info = $u_stmt->fetch(PDO::FETCH_OBJ);
                if ($u_info) {
                    require_once '../includes/sms.php';
                    sendSMSAlert("Cooperative Account Verified: User '" . $u_info->username . "' has been approved and activated for live milk market trading.");
                }
            }
        } catch (PDOException $e) { $error = $e->getMessage(); }
        header("Location: users.php?status=$status_filter&search=" . urlencode($search_query));
        exit();
    }
}

// --- Render ---
adminHeader('users', 'User List');
?>

<!-- Page Header -->
<div class="page-header">
    <h1>👥 USER MANAGEMENT</h1>
    <a href="add_user.php" class="btn btn-primary">➕ Add New User</a>
</div>

<!-- Stat Cards -->
<div class="stats-row">
    <div class="stat-card blue">
        <div class="stat-card-label">Total Users</div>
        <div class="stat-card-value"><?= $count_all ?></div>
        <div class="stat-card-sub">All registered accounts</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Active</div>
        <div class="stat-card-value"><?= $count_active ?></div>
        <div class="stat-card-sub">Currently enabled</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-card-label">Suspended</div>
        <div class="stat-card-value"><?= $count_suspended ?></div>
        <div class="stat-card-sub">Blocked accounts</div>
    </div>
    <div class="stat-card red">
        <div class="stat-card-label">Trash</div>
        <div class="stat-card-value"><?= $count_trash ?></div>
        <div class="stat-card-sub">Soft-deleted</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Admins</div>
        <div class="stat-card-value"><?= $count_admins ?></div>
        <div class="stat-card-sub">Admin roles</div>
    </div>
</div>

<!-- Table Card -->
<div class="table-card">
    <div class="table-toolbar">
        <!-- Filter Links -->
        <div class="filters">
            <span class="filter-label">Filter by status:</span>
            <?php
            $filters = [
                'all'       => "All <span class='count'>($count_all)</span>",
                'active'    => "Active <span class='count'>($count_active)</span>",
                'suspended' => "Suspended <span class='count'>($count_suspended)</span>",
                'trash'     => "Trash <span class='count'>($count_trash)</span>",
            ];
            foreach($filters as $key => $label):
            ?>
            <a href="?status=<?= $key ?>" class="filter-link <?= $status_filter === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Search -->
        <div class="search-box">
            <form method="GET" action="">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="text" name="search" placeholder="Search name or email…" value="<?= htmlspecialchars($search_query) ?>">
                <?php if ($search_query): ?>
                    <a href="?status=<?= htmlspecialchars($status_filter) ?>" class="clear-search" title="Clear search">✕</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-outline">🔍 Search</button>
            </form>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Data Table -->
    <div style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">
    <table class="admin-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Registered</th>
                <th>Last Login</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Is Admin</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
    // Fallback data for users without proper info
    $fallbackNames = [
        'Liam Henderson',
        'Amara Okafor',
        'Chloe Tanaka',
        'Mateo Rodriguez',
        'Aisha Al-Jamil',
        'Ethan Caldwell',
        'Elena Vasilyev',
        'Marcus Vance',
        'Priya Patel',
        'Sven Lindstrom'
    ];
    $fallbackEmails = [
        'liam@example.com',
        'amara@example.com',
        'chloe@example.com',
        'mateo@example.com',
        'aisha@example.com',
        'ethan@example.com',
        'elena@example.com',
        'marcus@example.com',
        'priya@example.com',
        'sven@example.com'
    ];
    $i = 0; ?>
<?php if (count($users) > 0): ?>
<?php foreach ($users as $u): ?>

<tr>
    <td><input type="checkbox" name="uid[]" value="<?= $u->id ?>"></td>
    <td><strong><?= htmlspecialchars($u->username ?? $fallbackNames[$i]) ?></strong></td>
    <td><?= htmlspecialchars($u->phone ?? 'Not Set') ?></td>
    <td><?= htmlspecialchars($u->email ?? $fallbackEmails[$i] ?? 'Not Set') ?></td>
    <td><?= htmlspecialchars($u->registered_at ?? 'Not Set') ?></td>
    <td><?= $u->last_login ? htmlspecialchars($u->last_login) : '<span style="color:#94a3b8">Never</span>' ?></td>
                <td>
                    <?php if ($u->status === 'active'): ?>
                        <span class="badge badge-active">✅ Active</span>
                    <?php elseif ($u->status === 'suspended'): ?>
                        <span class="badge badge-suspended">⏸ Suspended</span>
                    <?php else: ?>
                        <span class="badge badge-trash">🗑 Trash</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($u->has_paid ?? 0) == 1): ?>
                        <span class="badge badge-active" style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; font-size:0.8rem;">✅ Paid</span>
                    <?php elseif (($u->has_paid ?? 0) == 2): ?>
                        <span class="badge badge-suspended" style="background:#fffde7; color:#854d0e; border:1px solid #fef08a; font-size:0.8rem; cursor:help;" title="M-Pesa Ref: <?= htmlspecialchars($u->payment_ref ?? '') ?> (Ksh <?= number_format($u->payment_amount ?? 0) ?>)">⏳ Pending</span>
                    <?php else: ?>
                        <span class="badge badge-suspended" style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; font-size:0.8rem;">❌ Unpaid</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo $u->role === 'admin' ? '<span class="badge badge-admin">Yes</span>' : '<span style="color:#94a3b8">No</span>'; ?>
                </td>
                <td>
                    <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                        <a href="edit_user.php?id=<?= $u->id ?>" class="action-link">✏️ Edit</a>
                        <?php if (($u->has_paid ?? 0) == 2): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?= $u->id ?>">
                            <input type="hidden" name="action" value="approve_payment">
                            <button type="submit" class="action-link" style="border:none;background:none;cursor:pointer;color:#16a34a" onclick="return confirm('Approve payment of Ksh <?= number_format($u->payment_amount ?? 0) ?>?')">✅ Approve</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($u->status !== 'suspended'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?= $u->id ?>">
                            <input type="hidden" name="action" value="suspend">
                            <button type="submit" class="action-link danger" style="border:none;background:none;cursor:pointer" onclick="return confirm('Suspend this user?')">⏸ Suspend</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?= $u->id ?>">
                            <input type="hidden" name="action" value="activate">
                            <button type="submit" class="action-link" style="border:none;background:none;cursor:pointer;color:#16a34a">▶️ Approve / Activate</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($u->status !== 'trash'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?= $u->id ?>">
                            <input type="hidden" name="action" value="trash">
                            <button type="submit" class="action-link danger" style="border:none;background:none;cursor:pointer" onclick="return confirm('Move to trash?')">🗑 Trash</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($u->role !== 'admin'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?= $u->id ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="action-link danger" style="border:none;background:none;cursor:pointer;color:#dc2626;font-weight:bold;" onclick="return confirm('Are you sure you want to permanently delete this member?')">🗑 Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php $i++; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8">
                <div class="empty-state"><span class="icon">🔍</span>No users found matching your criteria.</div>
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <div class="table-footer">
        <div>Total: <strong><?= count($users) ?></strong> user<?= count($users) !== 1 ? 's' : '' ?></div>
        <div class="pagination-controls">
            <label>Per page:</label>
            <select onchange="window.location='?status=<?= htmlspecialchars($status_filter) ?>&per_page='+this.value">
                <option>10</option>
                <option>20</option>
                <option>50</option>
            </select>
        </div>
    </div>
</div>

<script>
// Select All checkbox
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="uid[]"]').forEach(cb => cb.checked = this.checked);
});
</script>

<?php adminFooter(); ?>

<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// --- Handling Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_ids = $_POST['user_ids'] ?? [];
    $single_user_id = $_POST['user_id'] ?? null;

    if (!empty($single_user_id)) {
        $user_ids = [$single_user_id];
    }

    if (!empty($action) && !empty($user_ids)) {
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));

        try {
            if ($action === 'delete') {
                // Soft delete — mark as trash, don't remove from DB
                $stmt = $conn->prepare("UPDATE users SET status='trash' WHERE id IN ($placeholders)");
            } elseif ($action === 'restore') {
                $stmt = $conn->prepare("UPDATE users SET status='active' WHERE id IN ($placeholders)");
            } elseif ($action === 'suspend') {
                $stmt = $conn->prepare("UPDATE users SET status='suspended' WHERE id IN ($placeholders)");
            } elseif ($action === 'permanent_delete') {
                $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
            }
            if (isset($stmt)) {
                $stmt->execute($user_ids);
            }
        } catch(PDOException $e) { $error = $e->getMessage(); }
        // Preserve the current filter tab on redirect
        $filter = $_GET['filter'] ?? '';
        $redirect = $_SERVER['PHP_SELF'];
        if ($filter) $redirect .= '?filter=' . urlencode($filter);
        header("Location: " . $redirect);
        exit();
    }
}

// --- Fetching Data ---
$search_query = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT id, username, email, phone, status, role FROM users";
$params = [];
$conditions = [];

// Filter by status
if ($filter === 'active') {
    $conditions[] = "status = 'active'";
} elseif ($filter === 'trash') {
    $conditions[] = "status = 'trash'";
} elseif ($filter === 'suspended') {
    $conditions[] = "status = 'suspended'";
}

if ($search_query !== '') {
    $conditions[] = "(username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPI Stats
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_users = $conn->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$trashed_users = $conn->query("SELECT COUNT(*) FROM users WHERE status='trash'")->fetchColumn();
$suspended_users = $conn->query("SELECT COUNT(*) FROM users WHERE status='suspended'")->fetchColumn();

adminHeader('advertisements');
?>

<!-- JavaScript for bulk actions -->
<script>
function setBulkAction(action) {
    var form = document.getElementById('bulk-action-form');
    var checked = form.querySelectorAll('input[name="user_ids[]"]:checked');
    if (checked.length === 0) {
        alert('Please select at least one user.');
        return;
    }
    var labels = { delete: 'MOVE TO TRASH', restore: 'RESTORE', suspend: 'SUSPEND', permanent_delete: 'PERMANENTLY DELETE' };
    var confirmMsg = 'Are you sure you want to ' + (labels[action] || action) + ' ' + checked.length + ' selected user(s)?';
    if (action === 'permanent_delete') {
        confirmMsg += '\n\n⚠️ This action CANNOT be undone!';
    }
    if (!confirm(confirmMsg)) return;
    document.getElementById('bulk-action').value = action;
    form.submit();
}
function toggleAll(source) {
    var checkboxes = document.querySelectorAll('#bulk-action-form input[name="user_ids[]"]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>

<div class="page-header">
    <h1>👤 USER MANAGEMENT</h1>
</div>

<!-- KPI Stats -->
<div class="stats-row">
    <div class="stat-card blue">
        <div class="stat-card-label">Total Users</div>
        <div class="stat-card-value"><?= number_format($total_users) ?></div>
        <div class="stat-card-sub">All registered</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Active</div>
        <div class="stat-card-value"><?= number_format($active_users) ?></div>
        <div class="stat-card-sub">Currently active</div>
    </div>
    <div class="stat-card red">
        <div class="stat-card-label">Trashed</div>
        <div class="stat-card-value"><?= number_format($trashed_users) ?></div>
        <div class="stat-card-sub">Soft-deleted</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-card-label">Suspended</div>
        <div class="stat-card-value"><?= number_format($suspended_users) ?></div>
        <div class="stat-card-sub">Temporarily disabled</div>
    </div>
</div>

<div class="table-card">

    <?php if(isset($error)): ?>
        <div class="error-msg" style="padding:1rem; color:red;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="table-toolbar">
        <div class="filters" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap; width:100%;">
            <!-- Status Filter Tabs -->
            <div style="display:flex; gap:0.25rem; align-items:center;">
                <a href="?filter=all" class="filter-link <?= $filter === 'all' ? 'active' : '' ?>">All <span class="count">(<?= $total_users ?>)</span></a>
                <a href="?filter=active" class="filter-link <?= $filter === 'active' ? 'active' : '' ?>">Active <span class="count">(<?= $active_users ?>)</span></a>
                <a href="?filter=trash" class="filter-link <?= $filter === 'trash' ? 'active' : '' ?>">🗑 Trash <span class="count">(<?= $trashed_users ?>)</span></a>
                <a href="?filter=suspended" class="filter-link <?= $filter === 'suspended' ? 'active' : '' ?>">⏸ Suspended <span class="count">(<?= $suspended_users ?>)</span></a>
            </div>

            <!-- Search -->
            <form method="GET" style="display:flex; gap:0.5rem; align-items:center; margin-left:auto;">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit" class="btn btn-outline" style="padding:0.3rem 0.5rem;">🔍 Search</button>
            </form>
        </div>
    </div>

    <!-- Bulk action buttons bar -->
    <div style="padding:0.5rem 1.25rem; border-bottom:1px solid #f1f5f9; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if ($filter === 'trash'): ?>
            <button type="button" class="btn btn-success" onclick="setBulkAction('restore')">♻ Restore Selected</button>
            <button type="button" class="btn btn-danger" onclick="setBulkAction('permanent_delete')">💀 Permanently Delete Selected</button>
        <?php else: ?>
            <button type="button" class="btn btn-danger" onclick="setBulkAction('delete')">🗑 Delete Selected</button>
            <button type="button" class="btn btn-success" onclick="setBulkAction('restore')">♻ Restore Selected</button>
            <button type="button" class="btn" style="background:#d97706;color:#fff;" onclick="setBulkAction('suspend')">⏸ Suspend Selected</button>
        <?php endif; ?>
    </div>

    <!-- Bulk action form wraps only the table so checkboxes are inside the form -->
    <form id="bulk-action-form" method="POST" action="?filter=<?= htmlspecialchars($filter) ?>">
        <input type="hidden" id="bulk-action" name="action" value="">

        <div style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><input type="checkbox" onclick="toggleAll(this)" title="Select All"></th>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
<?php if(count($users) > 0): ?>
<?php foreach($users as $u):
    $status = $u['status'] ?? 'active';
    $badgeClass = 'badge-success';
    if ($status === 'trash') $badgeClass = 'badge-trash';
    elseif ($status === 'suspended') $badgeClass = 'badge-suspended';
?>
            <tr class="<?= $status === 'trash' ? 'row-trashed' : '' ?>">
                <td><input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>"></td>
                <td>#<?= $u['id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                    <?php if($u['role'] === 'admin'): ?>
                        <span class="badge badge-admin" style="margin-left:0.3rem;">admin</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                <td>
                    <span class="badge badge-<?= $u['role'] === 'admin' ? 'admin' : 'member' ?>">
                        <?= htmlspecialchars($u['role'] ?? 'member') ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $badgeClass ?>">
                        <?= htmlspecialchars($status) ?>
                    </span>
                </td>
                <td style="white-space:nowrap;">
                    <?php if ($status === 'trash'): ?>
                        <!-- Trashed user: can restore or permanently delete -->
                        <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="btn-action btn-success" title="Restore"
                            onclick="document.getElementById('bulk-action').value='restore'; return confirm('Restore this user?');">
                            ♻ Restore
                        </button>
                        <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="btn-action btn-danger" title="Permanently Delete" style="margin-left:0.3rem;"
                            onclick="document.getElementById('bulk-action').value='permanent_delete'; return confirm('⚠️ PERMANENTLY delete this user? This cannot be undone!');">
                            💀 Purge
                        </button>
                    <?php elseif ($status === 'suspended'): ?>
                        <!-- Suspended user: can restore or trash -->
                        <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="btn-action btn-success" title="Restore"
                            onclick="document.getElementById('bulk-action').value='restore'; return confirm('Restore this user to active?');">
                            ♻ Restore
                        </button>
                        <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="btn-action btn-danger" title="Delete" style="margin-left:0.3rem;"
                            onclick="document.getElementById('bulk-action').value='delete'; return confirm('Move this user to trash?');">
                            🗑 Trash
                        </button>
                    <?php else: ?>
                        <!-- Active user: can suspend or trash -->
                        <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="btn-action" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;" title="Suspend"
                            onclick="document.getElementById('bulk-action').value='suspend'; return confirm('Suspend this user?');">
                            ⏸ Suspend
                        </button>
                        <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="btn-action btn-danger" title="Delete" style="margin-left:0.3rem;"
                            onclick="document.getElementById('bulk-action').value='delete'; return confirm('Move this user to trash?');">
                            🗑 Trash
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
<?php endforeach; ?>
<?php else: ?>
            <tr><td colspan="8" style="text-align:center; padding: 2rem;">
                <?php if ($filter === 'trash'): ?>
                    🗑 No trashed users. All users are safe!
                <?php elseif ($filter === 'suspended'): ?>
                    ⏸ No suspended users.
                <?php else: ?>
                    No users found.
                <?php endif; ?>
            </td></tr>
<?php endif; ?>
            </tbody>
        </table>
        </div>
    </form>
</div>

<?php adminFooter(); ?>

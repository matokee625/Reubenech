<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// --- Handling Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tid    = (int)($_POST['transaction_id'] ?? 0);
    
    if ($action === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Seller', 'Buyer', 'Milk Type', 'Volume(L)', 'Price(Ksh)', 'Commission(Ksh)', 'Status', 'Date']);
        
        $exp_stmt = $conn->query("
            SELECT t.id, s.username as seller, COALESCE(b.username, 'N/A') as buyer, m.milk_type, t.volume, t.price, (t.price * 0.05) as comm, t.status, t.transaction_date 
            FROM transactions t JOIN users s ON t.seller_id=s.id LEFT JOIN users b ON t.buyer_id=b.id JOIN milk_postings m ON t.posting_id=m.id 
            ORDER BY t.transaction_date DESC
        ");
        while ($row = $exp_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
    
    if ($tid > 0) {
        try {
            if ($action === 'verify') {
                $conn->prepare("UPDATE transactions SET status='completed' WHERE id=?")->execute([$tid]);
                // Fetch transaction details to send alert
                $t_stmt = $conn->prepare("
                    SELECT t.id, t.price, t.volume, s.username as seller_name, b.username as buyer_name
                    FROM transactions t
                    JOIN users s ON t.seller_id = s.id
                    LEFT JOIN users b ON t.buyer_id = b.id
                    WHERE t.id = ?
                    LIMIT 1
                ");
                $t_stmt->execute([$tid]);
                $tx = $t_stmt->fetch(PDO::FETCH_OBJ);
                if ($tx) {
                    require_once '../includes/sms.php';
                    sendSMSAlert("Payment Confirmed! Tx #" . $tx->id . ": Approved payment of Ksh " . number_format($tx->price) . " for seller " . $tx->seller_name . " (" . $tx->volume . "L sold to " . $tx->buyer_name . ").");
                }
            } elseif ($action === 'void') {
                $conn->prepare("UPDATE transactions SET status='cancelled' WHERE id=?")->execute([$tid]);
            } elseif ($action === 'escalate') {
                // We'll use a special status or just log a notification. Let's just create an admin notification.
                $conn->prepare("INSERT INTO notifications (type, title, message) VALUES ('danger', 'Dispute Raised', ?)")
                     ->execute(["Transaction #$tid has been escalated to dispute by Admin."]);
            }
        } catch(PDOException $e) { $error = $e->getMessage(); }
        header("Location: transactions.php");
        exit();
    }
}

// --- Filtering & Search ---
$status_filter = $_GET['status'] ?? 'all';
$search_query  = trim($_GET['search'] ?? '');

$sql = "
    SELECT t.*, s.username as seller_name, b.username as buyer_name, m.milk_type
    FROM transactions t
    JOIN users s ON t.seller_id = s.id
    LEFT JOIN users b ON t.buyer_id = b.id
    JOIN milk_postings m ON t.posting_id = m.id
    WHERE 1=1
";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND t.status = :status";
    $params['status'] = $status_filter;
}
if (!empty($search_query)) {
    $sql .= " AND (s.username LIKE :search OR b.username LIKE :search)";
    $params['search'] = "%$search_query%";
}
$sql .= " ORDER BY t.transaction_date DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_OBJ);

    $stats = $conn->query("
        SELECT 
            COUNT(*) as total_count,
            SUM(CASE WHEN status='completed' THEN price ELSE 0 END) as total_revenue,
            SUM(CASE WHEN status='completed' THEN price * 0.05 ELSE 0 END) as total_commission,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count
        FROM transactions
    ")->fetch(PDO::FETCH_OBJ);
} catch(PDOException $e) {
    $transactions = [];
    $stats = (object)['total_count'=>0, 'total_revenue'=>0, 'total_commission'=>0, 'pending_count'=>0];
    $error = $e->getMessage();
}

adminHeader('transactions', 'Transactions');
?>

<div class="page-header">
    <h1>💳 TRANSACTIONS & FINANCE</h1>
    <div style="display:flex; gap:0.5rem;">
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="btn btn-outline">📥 Export Ledger</button>
        </form>
    </div>
</div>

<div class="stats-row">
    <div class="stat-card blue">
        <div class="stat-card-label">Total Transactions</div>
        <div class="stat-card-value"><?= $stats->total_count ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Gross Revenue</div>
        <div class="stat-card-value">Ksh <?= number_format($stats->total_revenue) ?></div>
        <div class="stat-card-sub">Completed sales</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-card-label">Pending Verifications</div>
        <div class="stat-card-value"><?= $stats->pending_count ?></div>
        <div class="stat-card-sub">Require admin action</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-label">Platform Commission (5%)</div>
        <div class="stat-card-value">Ksh <?= number_format($stats->total_commission, 2) ?></div>
        <div class="stat-card-sub">Estimated earnings</div>
    </div>
</div>

<div class="table-card">
    <div class="table-toolbar">
        <div class="filters" style="display:flex; gap:1rem; align-items:center;">
            <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
                <select name="status" onchange="this.form.submit()" style="padding:0.3rem;">
                    <option value="all" <?= $status_filter==='all'?'selected':'' ?>>All Status</option>
                    <option value="pending" <?= $status_filter==='pending'?'selected':'' ?>>Pending</option>
                    <option value="completed" <?= $status_filter==='completed'?'selected':'' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
                <input type="text" name="search" placeholder="Search buyer or seller..." value="<?= htmlspecialchars($search_query) ?>" style="padding:0.3rem;">
                <button type="submit" class="btn btn-outline" style="padding:0.3rem 0.5rem;">🔍 Search</button>
            </form>
        </div>
    </div>
    
    <?php if(isset($error)): ?>
        <div class="error-msg" style="padding:1rem; color:red;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">
    <table class="admin-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Tx ID</th>
                <th>Seller</th>
                <th>Buyer</th>
                <th>Item</th>
                <th>Price (Ksh)</th>
                <th>Commission</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if(count($transactions)): ?>
            <?php foreach($transactions as $t): ?>
            <tr>
                <td><input type="checkbox" name="tid[]" value="<?= $t->id ?>"></td>
                <td>#<?= str_pad($t->id, 5, '0', STR_PAD_LEFT) ?></td>
                <td><strong><?= htmlspecialchars($t->seller_name) ?></strong></td>
                <td><?= $t->buyer_name ? '<strong>'.htmlspecialchars($t->buyer_name).'</strong>' : '<span style="color:#94a3b8">Guest/NA</span>' ?></td>
                <td><?= htmlspecialchars($t->milk_type) ?><br><small><?= number_format($t->volume, 1) ?> L</small></td>
                <td><?= number_format($t->price, 2) ?></td>
                <td style="color:green;">+<?= number_format($t->price * 0.05, 2) ?></td>
                <td>
                    <?php if($t->status === 'completed'): ?><span class="badge badge-completed">Completed</span>
                    <?php elseif($t->status === 'pending'): ?><span class="badge badge-pending">Pending</span>
                    <?php else: ?><span class="badge badge-cancelled">Cancelled</span><?php endif; ?>
                </td>
                <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($t->transaction_date)) ?></td>
                <td>
                    <div style="display:flex; gap:0.25rem;">
                        <?php if($t->status === 'pending'): ?>
                        <form method="POST"><input type="hidden" name="transaction_id" value="<?= $t->id ?>"><input type="hidden" name="action" value="verify"><button type="submit" class="action-link" style="border:none;background:none;color:green;cursor:pointer;">✅ Verify</button></form>
                        <form method="POST"><input type="hidden" name="transaction_id" value="<?= $t->id ?>"><input type="hidden" name="action" value="void"><button type="submit" class="action-link" style="border:none;background:none;color:orange;cursor:pointer;">🚫 Void</button></form>
                        <?php endif; ?>
                        
                        <a href="invoice.php?id=<?= $t->id ?>" class="action-link" style="color:#2563eb;" title="View Invoice">📄 Inv</a>
                        <form method="POST"><input type="hidden" name="transaction_id" value="<?= $t->id ?>"><input type="hidden" name="action" value="escalate"><button type="submit" class="action-link" style="border:none;background:none;color:red;cursor:pointer;" title="Escalate to Dispute">⚠️ Disp</button></form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="10"><div class="empty-state"><span class="icon">💳</span>No transactions match your search.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <div class="table-footer">
        <div>Showing <strong><?= count($transactions) ?></strong> records.</div>
    </div>
</div>

<script>
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="tid[]"]').forEach(cb => cb.checked = this.checked);
});
</script>

<?php adminFooter(); ?>

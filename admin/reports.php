<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

try {
    // Generate some basic report data
    
    // Revenue by month (last 6 months)
    $revenue_chart = $conn->query("
        SELECT DATE_FORMAT(transaction_date, '%b %Y') as month_name,
               SUM(price) as rev,
               COUNT(*) as tx_count
        FROM transactions
        WHERE status = 'completed'
          AND transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(transaction_date), MONTH(transaction_date)
        ORDER BY transaction_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Sales by milk type
    $type_chart = $conn->query("
        SELECT m.milk_type, SUM(t.volume) as total_volume, SUM(t.price) as total_revenue
        FROM transactions t
        JOIN milk_postings m ON t.posting_id = m.id
        WHERE t.status = 'completed'
        GROUP BY m.milk_type
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $revenue_chart = [];
    $type_chart = [];
}

adminHeader('reports', '');
?>

<div class="page-header">
    <h1>📈 SYSTEM REPORTS</h1>
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print Report</button>
</div>

<div class="dashboard-grid">

    <!-- Revenue Report -->
    <div class="table-card" style="grid-column: 1 / -1;">
        <div class="table-toolbar">
            <strong>Revenue over Last 6 Months</strong>
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Completed Transactions</th>
                    <th>Total Revenue (Ksh)</th>
                </tr>
            </thead>
            <tbody>
            <?php if(count($revenue_chart)): ?>
                <?php foreach($revenue_chart as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['month_name']) ?></strong></td>
                    <td><?= $r['tx_count'] ?></td>
                    <td><strong><?= number_format($r['rev'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3"><div class="empty-state"><span class="icon">📉</span>No revenue data to display.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Sales by Type -->
    <div class="table-card" style="grid-column: 1 / -1;">
        <div class="table-toolbar">
            <strong>Sales by Milk Type</strong>
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Milk Type</th>
                    <th>Total Volume Sold (L)</th>
                    <th>Total Revenue (Ksh)</th>
                </tr>
            </thead>
            <tbody>
            <?php if(count($type_chart)): ?>
                <?php foreach($type_chart as $t): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['milk_type']) ?></strong></td>
                    <td><?= number_format($t['total_volume'], 1) ?></td>
                    <td><strong><?= number_format($t['total_revenue'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3"><div class="empty-state"><span class="icon">🥛</span>No sales data to display.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<link rel="stylesheet" href="../css/reports.css">

<?php adminFooter(); ?>

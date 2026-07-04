<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

$new_notifications = [
    [
        'type' => 'success',
        'title' => 'Successful Milk Delivery',
        'message' => 'Habari! Your delivery of 15 Liters has been successfully recorded at the collection center today. Accepted: 15L. Rejected: 0L. Thank you for your continued partnership!',
        'link' => 'data.php',
    ],
    [
        'type' => 'danger',
        'title' => 'Quality and Testing Rejection',
        'message' => 'Alert: Your morning milk batch failed the alcohol and resazurin quality test due to high acidity levels. Please ensure proper cleaning of your storage cans before the next milking.',
        'link' => 'posts.php',
    ],
    [
        'type' => 'success',
        'title' => 'Payment Disbursement',
        'message' => 'M-Pesa payment of Kenya Shillings 6,800 for your bi-weekly milk supply has been successfully processed and sent. Transaction Reference: QRC123XYZ.',
        'link' => 'transactions.php',
    ],
    [
        'type' => 'warning',
        'title' => 'Cooling Tank Capacity Warning',
        'message' => 'Warning: Cooling Tank B at the Eldoret Hub has reached 85 percent capacity. Please schedule a dispatch to the main processor as soon as possible to avoid overflow.',
        'link' => 'data.php',
    ],
    [
        'type' => 'danger',
        'title' => 'Critical Temperature Spike',
        'message' => 'Critical System Alert: The temperature in Silo Number 1 has risen to 7 degrees Celsius, which is above the standard threshold of 4 degrees Celsius. Please inspect the cooling systems immediately.',
        'link' => 'data.php',
    ],
    [
        'type' => 'info',
        'title' => 'Daily Collection Summary',
        'message' => 'Daily Summary Report: Total milk collection across all stations reached 4,200 Liters today, surpassing yesterday\'s target by 12 percent.',
        'link' => 'reports.php',
    ],
    [
        'type' => 'info',
        'title' => 'Route Assignment',
        'message' => 'Driver Kamau has been assigned to Route B covering Limuru to Githunguri. Expected farm pickup time is scheduled for exactly 6:00 AM.',
        'link' => 'jobs.php',
    ],
    [
        'type' => 'danger',
        'title' => 'Breakdown and Delay Alert',
        'message' => 'Logistics Emergency: Truck registration number KBZ 123X has reported a mechanical breakdown near Nakuru. Dispatching a backup vehicle immediately for milk transfer to prevent spoilage.',
        'link' => 'jobs.php',
    ],
    [
        'type' => 'warning',
        'title' => 'Supply Shortage Warning',
        'message' => 'Notice: Expected milk intake from the Nyandarua hub is short by 500 Liters today due to heavy rains delaying morning farm pickups.',
        'link' => 'reports.php',
    ],
    [
        'type' => 'warning',
        'title' => 'Market Price Adjustments',
        'message' => 'System Alert: The minimum milk purchase price has been adjusted to Kenya Shillings 52 per liter, effective today at midnight.',
        'link' => 'settings.php',
    ]
];

// Seed notifications table if needed
try {
    // Ensure column 'is_read' exists (in case table was setup via setup_db.php)
    try {
        $conn->query("SELECT is_read FROM notifications LIMIT 1");
    } catch(PDOException $ex) {
        $conn->query("ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
    }

    $check = $conn->query("SELECT COUNT(*) FROM notifications WHERE title = 'Successful Milk Delivery'")->fetchColumn();
    if ($check == 0) {
        $conn->query("DELETE FROM notifications");
        $insert_stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        foreach ($new_notifications as $n) {
            $insert_stmt->execute([$n['type'], $n['title'], $n['message'], $n['link']]);
        }
    }
} catch(PDOException $e) {}

try {
    $notifications = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll(PDO::FETCH_OBJ);
    
    // Count unread
    $unread = 0;
    foreach($notifications as $n) {
        if(!$n->is_read) $unread++;
    }
} catch(PDOException $e) {
    $notifications = [];
    $unread = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    try {
        $conn->query("UPDATE notifications SET is_read = 1");
        header("Location: notifications.php");
        exit;
    } catch(PDOException $e) {}
}

adminHeader('notifications', '');
?>

<div class="page-header">
    <h1>🔔 NOTIFICATIONS</h1>
    <?php if($unread > 0): ?>
    <form method="POST">
        <input type="hidden" name="mark_read" value="1">
        <button type="submit" class="btn btn-primary">Mark All as Read</button>
    </form>
    <?php endif; ?>
</div>

<div class="table-card">
    <div class="table-toolbar">
        <strong>All System Notifications</strong>
        <span class="badge badge-pending"><?= $unread ?> Unread</span>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Status</th>
                <th>Type</th>
                <th>Notification</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php if(count($notifications)): ?>
            <?php foreach($notifications as $n): ?>
            <tr style="<?= !$n->is_read ? 'background:#f8fafc;' : '' ?>">
                <td>
                    <?php if(!$n->is_read): ?>
                        <span style="color:#2563eb;font-size:1.5rem;">•</span>
                    <?php else: ?>
                        <span style="color:#cbd5e1;font-size:1.5rem;">◦</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-<?= htmlspecialchars($n->type) ?>"><?= ucfirst(htmlspecialchars($n->type)) ?></span>
                </td>
                <td>
                    <strong><?= htmlspecialchars($n->title) ?></strong><br>
                    <span style="color:#64748b;font-size:0.875rem;"><?= htmlspecialchars($n->message) ?></span>
                    <?php if($n->link): ?>
                        <br><a href="http://localhost/milkproject/<?= ltrim(htmlspecialchars($n->link), '/') ?>" style="font-size:0.8rem;color:#2563eb;">View Details &rarr;</a>
                    <?php endif; ?>
                </td>
                <td style="color:#64748b;font-size:0.875rem;">
                    <?= date('d M Y, H:i', strtotime($n->created_at)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="4"><div class="empty-state"><span class="icon">🔔</span>No notifications found.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>

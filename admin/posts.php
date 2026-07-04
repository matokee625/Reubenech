<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// --- Handling Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid    = (int)($_POST['post_id'] ?? 0);
    
    // Check if Export Action
    if ($action === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="milk_postings_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Seller', 'Type', 'Volume(L)', 'Price/L', 'Status', 'Posted At']);
        
        $exp_stmt = $conn->query("SELECT mp.id, u.username, mp.milk_type, mp.liters, mp.asking_price, mp.status, mp.posted_at FROM milk_postings mp JOIN users u ON mp.user_id = u.id ORDER BY mp.posted_at DESC");
        while ($row = $exp_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
    
    // Add New Posting Action
    if ($action === 'add_posting') {
        $user_id = (int)($_POST['user_id'] ?? $_SESSION['user_id']);
        $liters = floatval($_POST['liters']);
        $milk_type = trim($_POST['milk_type']);
        $price = floatval($_POST['asking_price']);
        
        if ($liters > 0 && $price > 0 && $user_id > 0) {
            try {
                $stmt = $conn->prepare("INSERT INTO milk_postings (user_id, liters, milk_type, asking_price, status, posted_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([$user_id, $liters, $milk_type, $price]);
                header("Location: posts.php?success=1");
                exit();
            } catch(PDOException $e) { $error = $e->getMessage(); }
        } else {
            $error = "Please fill in all fields correctly.";
        }
    }
    
    // Normal Row Actions
    if ($pid > 0) {
        try {
            if ($action === 'mark_sold') {
                $conn->prepare("UPDATE milk_postings SET status='sold' WHERE id=?")->execute([$pid]);
            } elseif ($action === 'mark_active') {
                $conn->prepare("UPDATE milk_postings SET status='active' WHERE id=?")->execute([$pid]);
            } elseif ($action === 'cancel') {
                $conn->prepare("UPDATE milk_postings SET status='cancelled' WHERE id=?")->execute([$pid]);
            } elseif ($action === 'delete') {
                $conn->prepare("DELETE FROM milk_postings WHERE id=?")->execute([$pid]);
            }
        } catch(PDOException $e) { $error = $e->getMessage(); }
        
        // Redirect to avoid form resubmission
        header("Location: posts.php");
        exit();
    }
}

// --- Filtering & Search ---
$status_filter = $_GET['status'] ?? 'all';
$type_filter   = $_GET['type'] ?? 'all';
$search_query  = trim($_GET['search'] ?? '');

$sql = "SELECT mp.*, u.username FROM milk_postings mp JOIN users u ON mp.user_id = u.id WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND mp.status = :status";
    $params['status'] = $status_filter;
}
if ($type_filter !== 'all') {
    $sql .= " AND mp.milk_type = :type";
    $params['type'] = $type_filter;
}
if (!empty($search_query)) {
    $sql .= " AND (u.username LIKE :search OR mp.milk_type LIKE :search)";
    $params['search'] = "%$search_query%";
}

$sql .= " ORDER BY mp.posted_at DESC";

// Fetch milk postings
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    $total_vol    = $conn->query("SELECT SUM(liters) FROM milk_postings WHERE status='active'")->fetchColumn();
    $total_active = $conn->query("SELECT COUNT(*) FROM milk_postings WHERE status='active'")->fetchColumn();
    $total_sold   = $conn->query("SELECT COUNT(*) FROM milk_postings WHERE status='sold'")->fetchColumn();
} catch(PDOException $e) { $posts = []; $total_vol=0; $total_active=0; $total_sold=0; $error = $e->getMessage(); }

adminHeader('posts', 'All Posts');
?>

<?php if(isset($_GET['success'])): ?>
    <div style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:.75rem 1rem; border-radius:6px; margin: 1rem 0;">✅ Posting added successfully.</div>
<?php endif; ?>

<?php if(isset($_GET['action']) && $_GET['action'] === 'new'): ?>
    <!-- New Posting Form -->
    <div class="page-header">
        <h1>➕ ADD NEW POSTING</h1>
        <a href="posts.php" class="btn btn-outline">← Cancel</a>
    </div>
    
    <div class="table-card" style="max-width: 600px; padding: 2rem; margin-bottom: 2rem;">
        <form method="POST">
            <input type="hidden" name="action" value="add_posting">
            
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Seller/Member</label>
                <select name="user_id" required style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px; background:white;">
                    <?php
                    $users_list = $conn->query("SELECT id, username FROM users WHERE role='member'")->fetchAll();
                    foreach($users_list as $u) {
                        echo "<option value='{$u->id}'>" . htmlspecialchars($u->username) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Milk Type</label>
                <select name="milk_type" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px; background:white;">
                    <option value="Cow">Cow</option>
                    <option value="Goat">Goat</option>
                    <option value="Camel">Camel</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Quantity Available (Liters)</label>
                <input type="number" step="0.01" name="liters" required style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px;">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Asking Price per Liter (Ksh)</label>
                <input type="number" step="0.01" name="asking_price" required style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px;">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">💾 Save Posting</button>
        </form>
    </div>
<?php endif; ?>

<div class="page-header">
    <h1>📄 MILK POSTINGS INVENTORY</h1>
    <div style="display:flex; gap:0.5rem;">
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="btn btn-outline">📥 Export CSV</button>
        </form>
        <a href="?action=new" class="btn btn-primary">➕ Add New Posting</a>
    </div>
</div>

<!-- KPI Stats -->
<div class="stats-row">
    <div class="stat-card blue">
        <div class="stat-card-label">Total Active Volume</div>
        <div class="stat-card-value"><?= number_format($total_vol ?? 0) ?> L</div>
        <div class="stat-card-sub">Litres available for sale</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Active Listings</div>
        <div class="stat-card-value"><?= $total_active ?></div>
    </div>
    <div class="stat-card amber">
        <div class="stat-card-label">Sold Items</div>
        <div class="stat-card-value"><?= $total_sold ?></div>
    </div>
</div>

<div class="table-card">
    <div class="table-toolbar">
        <!-- Filters -->
        <div class="filters" style="display:flex; gap:1rem; align-items:center;">
            <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
                <select name="status" onchange="this.form.submit()" style="padding:0.3rem;">
                    <option value="all" <?= $status_filter==='all'?'selected':'' ?>>All Status</option>
                    <option value="active" <?= $status_filter==='active'?'selected':'' ?>>Active</option>
                    <option value="sold" <?= $status_filter==='sold'?'selected':'' ?>>Sold</option>
                    <option value="cancelled" <?= $status_filter==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
                <select name="type" onchange="this.form.submit()" style="padding:0.3rem;">
                    <option value="all" <?= $type_filter==='all'?'selected':'' ?>>All Types</option>
                    <option value="Cow" <?= $type_filter==='Cow'?'selected':'' ?>>Cow</option>
                    <option value="Goat" <?= $type_filter==='Goat'?'selected':'' ?>>Goat</option>
                    <option value="Camel" <?= $type_filter==='Camel'?'selected':'' ?>>Camel</option>
                </select>
                <input type="text" name="search" placeholder="Search seller..." value="<?= htmlspecialchars($search_query) ?>" style="padding:0.3rem;">
                <button type="submit" class="btn btn-outline" style="padding:0.3rem 0.5rem;">🔍 Filter</button>
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
                <th>ID</th>
                <th>Seller</th>
                <th>Type</th>
                <th>Volume (L)</th>
                <th>Price / L (Ksh)</th>
                <th>Status</th>
                <th>Posted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
// Fetch posts as objects
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) { $posts = []; $error = $e->getMessage(); }
?>
<?php
// Sample inventory data
$samplePosts = [
    ["id"=>"MK-001", "seller"=>"Brookside Dairy Ltd", "type"=>"Fresh Pasteurized Whole Milk", "volume"=>0.5, "price"=>60, "status"=>"active", "posted_at"=>"2024-01-10 09:00"],
    ["id"=>"MK-002", "seller"=>"New KCC", "type"=>"Gold Crown Premium Milk", "volume"=>0.5, "price"=>65, "status"=>"active", "posted_at"=>"2024-01-12 11:30"],
    ["id"=>"MK-003", "seller"=>"Githunguri Dairy", "type"=>"Whole Fresh Cow Milk", "volume"=>0.5, "price"=>55, "status"=>"sold", "posted_at"=>"2024-01-15 14:45"],
    ["id"=>"MK-004", "seller"=>"Brookside Dairy Ltd", "type"=>"UHT Long Life Milk", "volume"=>0.5, "price"=>65, "status"=>"active", "posted_at"=>"2024-01-18 08:20"],
    ["id"=>"MK-005", "seller"=>"New KCC", "type"=>"KCC Plain Maziwa Lala", "volume"=>0.5, "price"=>60, "status"=>"cancelled", "posted_at"=>"2024-01-20 13:10"],
    ["id"=>"MK-006", "seller"=>"Brookside Dairy Ltd", "type"=>"Best Maziwa Lala Fermented", "volume"=>1, "price"=>130, "status"=>"active", "posted_at"=>"2024-01-22 10:05"],
    ["id"=>"MK-007", "seller"=>"Kinangop Dairy Ltd", "type"=>"Kinangop Fresh Whole Milk", "volume"=>0.5, "price"=>55, "status"=>"active", "posted_at"=>"2024-01-25 12:30"],
    ["id"=>"MK-008", "seller"=>"Brookside Dairy Ltd", "type"=>"UHT Long Life Whole Milk", "volume"=>1, "price"=>140, "status"=>"active", "posted_at"=>"2024-01-28 09:45"],
    ["id"=>"MK-009", "seller"=>"Bio Foods Ltd", "type"=>"Strawberry Real Fruit Yogurt", "volume"=>0.45, "price"=>160, "status"=>"active", "posted_at"=>"2024-02-01 07:50"],
    ["id"=>"MK-010", "seller"=>"Delamere", "type"=>"Premium Vanilla Flavored Milk", "volume"=>0.5, "price"=>75, "status"=>"active", "posted_at"=>"2024-02-03 15:20"]
];
$posts = $samplePosts; // Override with sample data for display
?>
<?php if(count($posts) > 0): ?>
<?php foreach($posts as $p): ?>
<tr>
    <td><input type="checkbox" name="pid[]" value="<?php echo $p['id']; ?>"/></td>
    <td><?php echo $p['id']; ?></td>
    <td><strong><?php echo htmlspecialchars($p['seller']); ?></strong><br><a href="#" style="font-size:0.75rem;">Contact</a></td>
    <td><?php echo htmlspecialchars($p['type']); ?></td>
    <td><?php echo number_format($p['volume'], 2); ?> L</td>
    <td>Ksh <?php echo number_format($p['price'], 2); ?></td>
    <td>
        <?php if($p['status'] === 'active'): ?>
            <span class="badge badge-active">Active</span>
        <?php elseif($p['status'] === 'sold'): ?>
            <span class="badge badge-completed">Sold</span>
        <?php elseif($p['status'] === 'cancelled'): ?>
            <span class="badge badge-cancelled">Cancelled</span>
        <?php endif; ?>
    </td>
    <td><?php echo date('d M Y, H:i', strtotime($p['posted_at'])); ?></td>
    <td>
        <div style="display:flex; gap:0.25rem;">
            <!-- Actions placeholders -->
            <form method="POST"><button type="submit" class="action-link" style="border:none;background:none;color:green;cursor:pointer;">✔️ Sold</button></form>
            <form method="POST"><button type="submit" class="action-link" style="border:none;background:none;color:blue;cursor:pointer;">🔄 Active</button></form>
            <form method="POST"><button type="submit" class="action-link" style="border:none;background:none;color:orange;cursor:pointer;">🚫 Cancel</button></form>
            <form method="POST"><button type="submit" class="action-link" style="border:none;background:none;color:red;cursor:pointer;">🗑 Delete</button></form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="9"><div class="empty-state"><span class="icon">🥛</span>No milk postings match your criteria.</div></td></tr>
<?php endif; ?>
        </tbody>
    </table>
    </div>
    <div class="table-footer">
        <div>Showing <strong><?= count($posts) ?></strong> postings. Use bulk checkboxes to process multiple.</div>
    </div>
</div>

<script>
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="pid[]"]').forEach(cb => cb.checked = this.checked);
});
</script>

<?php adminFooter(); ?>

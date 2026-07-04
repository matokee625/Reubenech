<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// Check system health
$checks = [];

// 1. DB Connection
try {
    $conn->query("SELECT 1");
    $checks[] = ['label'=>'Database Connection', 'status'=>'ok', 'msg'=>'Connected to milkproject.db'];
} catch(PDOException $e) {
    $checks[] = ['label'=>'Database Connection', 'status'=>'fail', 'msg'=>$e->getMessage()];
}

// 2. Tables exist
foreach(['users','milk_postings','advertisements','transactions'] as $tbl) {
    try {
        $conn->query("SELECT 1 FROM `$tbl` LIMIT 1");
        $checks[] = ['label'=>"Table: $tbl", 'status'=>'ok', 'msg'=>'Table exists and is accessible'];
    } catch(PDOException $e) {
        $checks[] = ['label'=>"Table: $tbl", 'status'=>'fail', 'msg'=>'Table missing or inaccessible'];
    }
}

// 3. PHP version
$php_ok = version_compare(PHP_VERSION, '7.4', '>=');
$checks[] = ['label'=>'PHP Version', 'status'=>$php_ok?'ok':'warn', 'msg'=>'PHP ' . PHP_VERSION . ($php_ok?' (OK)':' — requires 7.4+')];

// 4. Folders
$writable = is_writable('../uploads/');
$checks[] = ['label'=>'Uploads Folder', 'status'=>$writable?'ok':'warn', 'msg'=> $writable ? 'Writable' : 'Not writable — needed for ad images'];

adminHeader('install', 'Installation');
?>

<div class="page-header">
    <h1>⚙️ INSTALLATION & HEALTH CHECK</h1>
</div>

<div class="table-card">
    <div class="table-toolbar"><strong>System Health</strong></div>
    <table class="admin-table">
        <thead><tr><th>Check</th><th>Status</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach($checks as $chk): ?>
            <tr>
                <td><strong><?= $chk['label'] ?></strong></td>
                <td>
                    <?php if($chk['status']==='ok'): ?>
                        <span class="badge badge-active">✅ OK</span>
                    <?php elseif($chk['status']==='warn'): ?>
                        <span class="badge badge-suspended">⚠️ Warning</span>
                    <?php else: ?>
                        <span class="badge badge-trash">❌ Failed</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($chk['msg']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<br>

<div class="table-card" style="padding:1.5rem">
    <h3 style="margin-bottom:1rem">📦 Setup Instructions</h3>
    <ol style="color:#475569; line-height:2; padding-left:1.25rem; font-size:0.875rem">
        <li>Ensure XAMPP is running (Apache + MySQL).</li>
        <li>Import <code>milkproduction.sql</code> into phpMyAdmin.</li>
        <li>Verify <code>connection.php</code> has the correct DB name (<strong>milkproject.db</strong>).</li>
        <li>Visit <a href="http://localhost/milkproject/login.php">http://localhost/milkproject/login.php</a> to log in.</li>
        <li>Default admin credentials: <strong>reubenmatoke2005@gmail.com / password</strong></li>
    </ol>
</div>

<?php adminFooter(); ?>

<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

adminHeader('docs', 'Documentation');
?>

<div class="page-header">
    <h1>📖 DOCUMENTATION</h1>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px,1fr)); gap:1.5rem;">

    <?php
    $sections = [
        ['🔐 Authentication', 'Users log in via <code>/login.php</code> with email + password. Passwords are hashed with PHP <strong>bcrypt</strong>. Sessions store <code>user_id</code>, <code>username</code>, and <code>user_role</code>.'],
        ['👥 Role-Based Access', '<strong>Admin</strong>: Full access to Admin Panel. <strong>Member</strong>: Access only to Member Dashboard. Unauthorized access is automatically redirected.'],
        ['🥛 Milk Postings', 'Members can post their milk supply from the Dashboard. Each posting stores: Liters, Milk Type (Cow / Goat / Camel), and Asking Price per litre.'],
        ['📊 Transactions', 'When a sale is confirmed, a transaction record is created linking the <code>seller_id</code>, <code>buyer_id</code>, and <code>posting_id</code>.'],
        ['🌐 Public Site', 'The homepage <code>index.php</code> is publicly accessible. It displays community advertisements pulled from the <code>advertisements</code> table.'],
        ['🗃️ Database', 'Database: <strong>milkproject.db</strong>. Tables: <code>users</code>, <code>milk_postings</code>, <code>advertisements</code>, <code>transactions</code>. All relations use InnoDB foreign keys with CASCADE on delete.'],
    ];
    foreach($sections as [$title, $content]):
    ?>
    <div class="table-card" style="padding:1.5rem">
        <h3 style="margin-bottom:0.75rem; color:#0f172a"><?= $title ?></h3>
        <p style="color:#475569; line-height:1.6; font-size:0.875rem"><?= $content ?></p>
    </div>
    <?php endforeach; ?>

</div>

<?php adminFooter(); ?>

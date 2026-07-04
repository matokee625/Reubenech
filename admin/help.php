<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

adminHeader('users', 'Help');
?>

<div class="page-header">
    <h1>❓ HELP CENTER</h1>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px,1fr)); gap:1.5rem;">
<?php
$faqs = [
    ['Q: How do I add a new admin?', 'Log in as an admin, go to Users, find the user, click Edit, and change their role to "admin".'],
    ['Q: How do I reset a user\'s password?', 'Currently, admin can re-register the user or directly update the password_hash in the database.'],
    ['Q: Why can\'t I log in?', 'Make sure XAMPP is running, the database milkproject.db exists, and your credentials are correct. Default: reubenmatoke2005@gmail.com / password.'],
    ['Q: How do I add advertisements?', 'Members can post ads from their dashboard. Admins can view all ads on the Posts page.'],
    ['Q: How do I see all transactions?', 'Navigate to Jobs List in the sidebar to see all recorded transactions.'],
    ['Q: What is the health check page?', 'The Installation page (⚙️) runs a live diagnostic on your database tables, PHP version, and folder permissions.'],
];
foreach($faqs as [$q, $a]):
?>
    <div class="table-card" style="padding:1.25rem">
        <p style="font-weight:700; color:#0f172a; margin-bottom:0.5rem"><?= $q ?></p>
        <p style="color:#475569; font-size:0.875rem; line-height:1.6"><?= $a ?></p>
    </div>
<?php endforeach; ?>
</div>

<?php adminFooter(); ?>

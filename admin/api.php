<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

adminHeader('api', 'API Registry');
?>

<div class="page-header">
    <h1>🔌 API REGISTRY</h1>
</div>

<div class="table-card">
    <div class="table-toolbar"><strong>Available Internal Endpoints</strong></div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Description</th>
                <th>Auth Required</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $apis = [
                ['GET',  '/milkproject/login.php',            'Login page',                             'No',  'Public'],
                ['POST', '/milkproject/login.php',            'Authenticate user and start session',    'No',  'Public'],
                ['GET',  '/milkproject/register.php',         'Registration form',                      'No',  'Public'],
                ['POST', '/milkproject/register.php',         'Create new member account',              'No',  'Public'],
                ['GET',  '/milkproject/logout.php',           'Destroy session and redirect',           'Yes', 'Any'],
                ['GET',  '/milkproject/member/dashboard.php', 'Member dashboard',                       'Yes', 'Member'],
                ['POST', '/milkproject/member/dashboard.php', 'Submit milk posting',                    'Yes', 'Member'],
                ['GET',  '/milkproject/admin/users.php',      'Admin user list',                        'Yes', 'Admin'],
                ['POST', '/milkproject/admin/users.php',      'Suspend / activate / trash user',        'Yes', 'Admin'],
                ['GET',  '/milkproject/admin/posts.php',      'Admin view milk postings',               'Yes', 'Admin'],
                ['GET',  '/milkproject/admin/data.php',       'Admin data analytics overview',          'Yes', 'Admin'],
                ['GET',  '/milkproject/admin/jobs.php',       'Admin transactions / jobs list',         'Yes', 'Admin'],
                ['GET',  '/milkproject/index.php',            'Public homepage & advertisements',       'No',  'Public'],
            ];
            foreach($apis as [$method, $endpoint, $desc, $auth, $role]):
                $methodColor = match($method) { 'POST'=>'#16a34a', 'GET'=>'#2563eb', default=>'#d97706' };
                $roleClass = match($role) { 'Admin'=>'badge-admin', 'Member'=>'badge-member', default=>'' };
            ?>
            <tr>
                <td><span style="font-weight:700; color:<?= $methodColor ?>"><?= $method ?></span></td>
                <td><code style="background:#f1f5f9; padding:0.2rem 0.4rem; border-radius:4px; font-size:0.75rem"><?= $endpoint ?></code></td>
                <td><?= $desc ?></td>
                <td><?= $auth === 'Yes' ? '<span class="badge badge-active">Yes</span>' : '<span style="color:#94a3b8">No</span>' ?></td>
                <td><?= $role !== 'Public' ? "<span class='badge $roleClass'>$role</span>" : '<span style="color:#94a3b8">Public</span>' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>

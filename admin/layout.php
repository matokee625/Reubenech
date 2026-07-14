<?php
// Admin shared layout helper
// Usage: include 'layout.php'; then call adminHeader() and adminFooter()

function adminHeader($activePage = 'homepage', $activeTab = '') {
    global $conn;
    $pages = [
        'homepage' => ['label' => 'Dashboard',     'icon' => '🏠', 'href' => 'homepage.php'],
        'posts'    => ['label' => 'Posts',         'icon' => '📄', 'href' => 'posts.php'],
        'advertisements' => ['label' => 'User Control', 'icon' => '👤', 'href' => 'advertisements.php'],
        'users'    => ['label' => 'Users',         'icon' => '👥', 'href' => 'users.php'],
        'transactions'=>['label'=> 'Transactions',  'icon' => '💳', 'href' => 'transactions.php'],
        'data'     => ['label' => 'Data Metrics',  'icon' => '📊', 'href' => 'data.php'],
        'reports'  => ['label' => 'Reports',       'icon' => '📈', 'href' => 'reports.php'],
        'jobs'     => ['label' => 'Jobs List',     'icon' => '🕒', 'href' => 'jobs.php'],
        'notifications'=>['label'=> 'Notifications','icon' => '🔔', 'href' => 'notifications.php'],
        'api'      => ['label' => 'API Registry',  'icon' => '🔌', 'href' => 'api.php'],
        'docs'     => ['label' => 'Documentation', 'icon' => '📖', 'href' => 'docs.php'],
        'settings' => ['label' => 'Settings',      'icon' => '⚙️',  'href' => 'settings.php'],
        'install'  => ['label' => 'Installation',  'icon' => '🔧', 'href' => 'install.php'],
    ];

    $tabMap = [
        'users'    => [['label'=>'User List','icon'=>'👥','href'=>'users.php'],['label'=>'Access Logs','icon'=>'🔒','href'=>'access_logs.php'],['label'=>'Help','icon'=>'❓','href'=>'help.php']],
        'posts'    => [['label'=>'All Posts','icon'=>'📄','href'=>'posts.php'],['label'=>'Add Post','icon'=>'✏️','href'=>'posts.php?action=new']],
        'data'     => [['label'=>'Overview','icon'=>'📊','href'=>'data.php'],['label'=>'Reports','icon'=>'📈','href'=>'data.php?tab=reports']],
        'settings' => [
            ['label'=>'General','icon'=>'🌐','href'=>'settings.php?tab=general'],
            ['label'=>'Security','icon'=>'🔒','href'=>'settings.php?tab=security'],
            ['label'=>'Email','icon'=>'📧','href'=>'settings.php?tab=email'],
            ['label'=>'Appearance','icon'=>'🎨','href'=>'settings.php?tab=appearance'],
            ['label'=>'Backup','icon'=>'💾','href'=>'settings.php?tab=backup']
        ],
    ];
    $currentTabs = $tabMap[$activePage] ?? [];
    $username = htmlspecialchars($_SESSION['username'] ?? 'Admin');
    $role = ucfirst($_SESSION['user_role'] ?? 'admin');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milk Admin - <?= ucfirst($activePage) ?></title>
    <link rel="stylesheet" href="admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="admin-wrapper">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">🍼</div>
            <span class="brand-label">Milk Admin</span>
        </div>
        <nav class="sidebar-nav">
            <?php foreach($pages as $key => $page): ?>
            <a href="<?= $page['href'] ?>" class="nav-item <?= $activePage === $key ? 'active' : '' ?>">
                <span class="icon"><?= $page['icon'] ?></span>
                <span><?= $page['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item text-danger">
                <span class="icon">🚪</span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <?php if(!empty($currentTabs)): ?>
                    <?php foreach($currentTabs as $tab): ?>
                        <a href="<?= $tab['href'] ?>" class="topbar-link <?= ($activeTab === $tab['label'] || (empty($activeTab) && $tab === $currentTabs[0])) ? 'active' : '' ?>">
                            <?= $tab['icon'] ?> <?= $tab['label'] ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="topbar-right">
                <div class="topbar-user">
                    <div class="topbar-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
                    <span class="topbar-username"><?= $username ?></span>
                    <span class="topbar-role-badge"><?= $role ?></span>
                </div>
                <a href="../logout.php" class="topbar-link danger-link">🚪 Logout</a>
            </div>
        </header>

        <div class="page-content">
    <?php
}

function adminFooter() {
    $year = date('Y');
    echo <<<HTML
        </div><!-- end page-content -->
        <footer class="admin-footer">
            <span>&copy; {$year} Project by <strong>Milk Admin</strong></span>
            <span>Reubentech Hub &mdash; All rights reserved</span>
        </footer>
    </div><!-- end main-content -->
</div><!-- end admin-wrapper -->
</body>
</html>
HTML;
}
?>

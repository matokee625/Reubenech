<?php
session_start();
require_once 'connection.php';

// Fetch recent advertisements for member interaction
try {
    $stmt = $conn->query("SELECT a.*, u.username FROM advertisements a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 6");
    $adverts = $stmt->fetchAll();
} catch(PDOException $e) {
    $adverts = [];
}

// Check logged in states
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? '';
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reubentech Hub - Cooperative Homepage</title>
    <meta name="description" content="Connect dairy farmers with local and commercial buyers. Post and trade fresh milk production daily on Reubentech Hub.">
    <link rel="icon" href="favicon.php" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-heading: 'Outfit', sans-serif;
            --font-body: 'Inter', sans-serif;
            --color-primary: #059669;
            --color-primary-hover: #047857;
            --color-secondary: #0284c7;
            --color-bg: #f8fafc;
            --color-surface: #ffffff;
            --color-text: #1e293b;
            --color-text-muted: #64748b;
            --color-border: #e2e8f0;
            --radius-md: 12px;
            --radius-lg: 20px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
        }

        header {
            background-color: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--color-border);
            padding: 1.25rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .logo {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 800;
            text-decoration: none;
            color: var(--color-primary);
            letter-spacing: -0.025em;
        }

        .logo span {
            color: #0f172a;
        }

        .nav-grid {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .nav-btn {
            color: var(--color-text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.5rem 0.85rem;
            border-radius: var(--radius-md);
            transition: all 0.2s;
        }

        .nav-btn:hover {
            color: var(--color-primary);
            background-color: #f1f5f9;
        }

        .nav-btn-highlight {
            background-color: var(--color-primary);
            color: white !important;
            box-shadow: 0 4px 8px rgba(5, 150, 105, 0.2);
        }

        .nav-btn-highlight:hover {
            background-color: var(--color-primary-hover);
        }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, rgba(240, 253, 244, 0.92) 0%, rgba(224, 242, 254, 0.88) 100%),
                url('logomilk.avif') center/cover;
            padding: 7rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--color-border);
        }

        .hero-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-family: var(--font-heading);
            font-size: 3.5rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-bottom: 1.5rem;
        }

        .hero h1 mark {
            background: none;
            color: var(--color-primary);
        }

        .hero p {
            font-size: 1.15rem;
            color: var(--color-text-muted);
            margin-bottom: 2.5rem;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-large {
            padding: 0.85rem 1.75rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .btn-green {
            background-color: var(--color-primary);
            color: white;
            box-shadow: 0 6px 15px rgba(5, 150, 105, 0.25);
        }

        .btn-green:hover {
            background-color: var(--color-primary-hover);
            transform: translateY(-2px);
        }

        .btn-sky {
            background-color: var(--color-secondary);
            color: white;
            box-shadow: 0 6px 15px rgba(2, 132, 199, 0.25);
        }

        .btn-sky:hover {
            background-color: #0270a5;
            transform: translateY(-2px);
        }

        .btn-white {
            background-color: white;
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }

        .btn-white:hover {
            background-color: var(--color-bg);
            transform: translateY(-2px);
        }

        /* Container Section */
        .main-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 5rem 2rem 2rem 2rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2.5rem;
            margin-top: 3rem;
        }

        .card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
            padding: 2.25rem;
            box-shadow: var(--shadow-md);
            transition: all 0.25s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(5, 150, 105, 0.2);
        }

        .card-title {
            font-family: var(--font-heading);
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #0f172a;
        }

        .card p {
            color: var(--color-text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .section-header {
            text-align: center;
        }

        .section-header h2 {
            font-family: var(--font-heading);
            font-size: 2.25rem;
            font-weight: 800;
            color: #0f172a;
        }

        .section-header p {
            color: var(--color-text-muted);
            margin-top: 0.5rem;
        }

        /* Ad Cards Specific Styling */
        .ad-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
        }

        .ad-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(5, 150, 105, 0.2);
        }

        .ad-card-img-wrapper {
            position: relative;
            width: 100%;
            height: 220px;
            background: #f1f5f9;
        }

        .ad-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.2s ease;
        }

        .ad-card:hover img {
            transform: scale(1.03);
        }

        .ad-card-body {
            padding: 1.75rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .ad-card-title {
            font-family: var(--font-heading);
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #0f172a;
        }

        .ad-card-meta {
            font-size: 0.8rem;
            color: var(--color-text-muted);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ad-card-desc {
            color: #475569;
            font-size: 0.925rem;
            line-height: 1.6;
            flex: 1;
        }

        footer {
            background: #0f172a;
            color: #94a3b8;
            text-align: center;
            padding: 3rem 2rem;
            border-top: 1px solid #1e293b;
            margin-top: 4rem;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header>
        <a href="index.php" class="logo">🥛 Reubentech<span>Hub</span></a>

        <nav class="nav-grid">
            <a href="index.php" class="nav-btn">Public Board</a>

            <?php if ($isLoggedIn): ?>
                <?php if ($userRole === 'admin'): ?>
                    <a href="admin/homepage.php" class="nav-btn">Admin Portal</a>
                    <a href="admin/install.php" class="nav-btn">Setup Diagnostics</a>
                    <a href="admin/reports.php" class="nav-btn">Coop Reports</a>
                    <a href="admin/api.php" class="nav-btn">System APIs</a>
                <?php else: ?>
                    <a href="member/dashboard.php" class="nav-btn">Producer Dashboard</a>
                    <a href="member/dashboard.php?tab=post_supply" class="nav-btn">Post Supply</a>
                    <a href="member/dashboard.php?tab=markets" class="nav-btn">Markets</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-btn nav-btn-highlight">Logout (<?= htmlspecialchars($username) ?>)</a>
            <?php else: ?>
                <a href="admin/homepage.php" class="nav-btn">Admin Portal</a>
                <a href="login.php" class="nav-btn">Sign In</a>
                <a href="register.php" class="nav-btn nav-btn-highlight">Register</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-container">
            <h1>Farm-Fresh Dairy cooperative <mark>Digital Marketplace</mark></h1>
            <p>Welcome to the cooperative network dashboard. Access real-time milk postings, sell supply directly to
                processing plants, publish advertisements, and review financial ledger invoices.</p>
            <div class="hero-action-buttons">
                <?php if ($isLoggedIn): ?>
                    <?php if ($userRole === 'admin'): ?>
                        <a href="admin/homepage.php" class="btn-large btn-green">Access Admin Portal</a>
                    <?php else: ?>
                        <a href="member/dashboard.php" class="btn-large btn-green">Go to Dashboard Portal</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-large btn-white">Sign Out</a>
                <?php else: ?>
                    <a href="login.php" class="btn-large btn-green">Access Dashboard Portal</a>
                    <a href="register.php" class="btn-large btn-white">Create Producer Account</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- WORKSPACES CONTENT -->
    <main class="main-section">
        <div class="section-header">
            <h2>Digital Cooperative Workspaces</h2>
            <p>Direct routes to individual platform components and cooperative subsystems.</p>
        </div>

        <div class="grid">
            <!-- Card 1 -->
            <div class="card">
                <h3 class="card-title">🥛 Milk Marketplace</h3>
                <p>Register as a member to list cow, goat, or camel milk liters, asking price rates, and publish
                    directly to local processors.</p>
                <?php if ($isLoggedIn): ?>
                    <a href="member/dashboard.php?tab=post_supply" class="btn-large btn-white" style="font-size:0.85rem; padding:0.5rem 1rem;">Post Supply</a>
                <?php else: ?>
                    <a href="login.php" class="btn-large btn-white" style="font-size:0.85rem; padding:0.5rem 1rem;">Post Supply</a>
                <?php endif; ?>
            </div>

            <!-- Card 2 -->
            <div class="card">
                <h3 class="card-title">🏢 Commercial Buyers</h3>
                <p>Processors like Brookside Plant, New KCC Cooperative, and Githunguri Dairy purchase fresh stock daily
                    from registered producers.</p>
                <?php if ($isLoggedIn): ?>
                    <a href="member/dashboard.php?tab=markets" class="btn-large btn-white" style="font-size:0.85rem; padding:0.5rem 1rem;">Explore Buyers</a>
                <?php else: ?>
                    <a href="login.php" class="btn-large btn-white" style="font-size:0.85rem; padding:0.5rem 1rem;">Explore Buyers</a>
                <?php endif; ?>
            </div>

            <!-- Card 3 -->
            <div class="card">
                <h3 class="card-title">📈 Cooperative Reports</h3>
                <p>Review gross transaction volumes, estimated commission ledger reports, sales analytics, and download
                    SQL ledger backups.</p>
                <?php if ($isLoggedIn): ?>
                    <?php if ($userRole === 'admin'): ?>
                        <a href="admin/reports.php" class="btn-large btn-white" style="font-size:0.85rem; padding:0.5rem 1rem;">View Reports</a>
                    <?php else: ?>
                        <a href="member/dashboard.php?tab=transactions" class="btn-large btn-white" style="font-size:0.85rem; padding:0.5rem 1rem;">View Reports</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="btn-large btn-white" style="font-size:0.85rem; padding:0.5rem 1rem;">View Reports</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- NOTICES & ADVERTISEMENTS SECTION -->
    <main class="main-section" style="padding-top: 2rem;">
        <div class="section-header">
            <h2>Community Notices & Advertisements</h2>
            <p>Explore open notices, equipment listings, and announcements posted by members of our cooperative.</p>
        </div>

        <div class="grid">
            <?php if (count($adverts) > 0): ?>
                <?php foreach ($adverts as $ad): ?>
                    <div class="ad-card">
                        <div class="ad-card-img-wrapper">
                            <?php if ($ad->image_url): ?>
                                <img src="<?= htmlspecialchars($ad->image_url) ?>" alt="Advert">
                            <?php else: ?>
                                <div style="width:100%; height:100%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-weight: 500; font-size: 0.9rem;">
                                    🖼️ No Image Provided
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="ad-card-body">
                            <h3 class="ad-card-title"><?= htmlspecialchars($ad->title) ?></h3>
                            <div class="ad-card-meta">
                                <span>👤 Posted by <strong><?= htmlspecialchars($ad->username) ?></strong></span>
                                <span>•</span>
                                <span>📅 <?= date('M d, Y', strtotime($ad->created_at)) ?></span>
                            </div>
                            <p class="ad-card-desc"><?= nl2br(htmlspecialchars($ad->description)) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align:center; color:#64748b; padding: 5rem 2rem; background: white; border: 1px dashed #cbd5e1; border-radius: 16px;">
                    <span style="font-size: 2.5rem; display:block; margin-bottom:1rem;">📢</span>
                    <p style="font-weight: 600; color: #334155; margin-bottom: 0.25rem;">No advertisements have been posted yet</p>
                    <p style="font-size: 0.9rem; color: #64748b;">Join the cooperative and publish your first listing to get noticed!</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> Reubentech Hub — Dairy Cooperative System. All rights reserved.</p>
    </footer>

</body>

</html>
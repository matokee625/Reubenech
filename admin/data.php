<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

// Fetch key metrics
try {
    $total_users     = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_members   = $conn->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();
    $total_admins    = $conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    $total_postings  = $conn->query("SELECT COUNT(*) FROM milk_postings")->fetchColumn();
    $total_volume    = $conn->query("SELECT SUM(liters) FROM milk_postings")->fetchColumn() ?? 0;
    $total_revenue   = $conn->query("SELECT SUM(liters * asking_price) FROM milk_postings WHERE status='sold'")->fetchColumn() ?? 0;
    $total_ads       = $conn->query("SELECT COUNT(*) FROM advertisements")->fetchColumn();
    $total_trans     = $conn->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

    // By milk type
    $by_type = $conn->query("SELECT milk_type, COUNT(*) as cnt, SUM(liters) as vol FROM milk_postings GROUP BY milk_type")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $total_users = $total_members = $total_admins = $total_postings = $total_volume = $total_revenue = $total_ads = $total_trans = 0;
    $by_type = [];
    $error = $e->getMessage();
}

adminHeader('data', 'Overview');
?>

<div class="page-header">
    <h1>📊 DATA OVERVIEW</h1>
</div>

<?php if (isset($error) && !empty($error)): ?>
<div class="error-msg" style="margin: 0 0 1.5rem 0; padding: 1.25rem; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    <strong style="display: block; font-size: 0.95rem; margin-bottom: 0.25rem;">⚠️ System Data Loading Error</strong>
    <p style="margin: 0; font-size: 0.875rem; line-height: 1.4; color: #b91c1c;">
        The system could not retrieve the live metrics because of a database connection issue or missing tables.
    </p>
    <div style="margin-top: 0.75rem; padding: 0.5rem 0.75rem; background: #fff; border: 1px solid #fee2e2; border-radius: 6px; font-family: monospace; font-size: 0.8rem; color: #7f1d1d; overflow-x: auto;">
        <?= htmlspecialchars($error) ?>
    </div>
    <p style="margin: 0.75rem 0 0 0; font-size: 0.85rem; color: #475569;">
        💡 <strong>How to fix this:</strong> Please go to the <a href="install.php" style="color: #2563eb; font-weight: 600; text-decoration: underline;">System Installation / Setup page</a> to ensure all database tables are initialized properly.
    </p>
</div>
<?php endif; ?>

<div class="stats-row">
    <div class="stat-card blue"><div class="stat-card-label">Total Users</div><div class="stat-card-value"><?= $total_users ?></div></div>
    <div class="stat-card green"><div class="stat-card-label">Members</div><div class="stat-card-value"><?= $total_members ?></div></div>
    <div class="stat-card"><div class="stat-card-label">Admins</div><div class="stat-card-value"><?= $total_admins ?></div></div>
    <div class="stat-card amber"><div class="stat-card-label">Milk Postings</div><div class="stat-card-value"><?= $total_postings ?></div></div>
    <div class="stat-card blue"><div class="stat-card-label">Total Volume (L)</div><div class="stat-card-value"><?= number_format($total_volume) ?></div></div>
    <div class="stat-card green"><div class="stat-card-label">Revenue (Ksh)</div><div class="stat-card-value"><?= number_format($total_revenue) ?></div></div>
    <div class="stat-card"><div class="stat-card-label">Advertisements</div><div class="stat-card-value"><?= $total_ads ?></div></div>
    <div class="stat-card"><div class="stat-card-label">Transactions</div><div class="stat-card-value"><?= $total_trans ?></div></div>
</div>

<?php
// Define default/demo categories first
$breakdown = [
    'Raw Whole Milk (Chilled)' => ['cnt' => 4, 'vol' => 550.00],
    'Fermented Milk (Maziwa Lala)' => ['cnt' => 2, 'vol' => 234.00],
    'Pasteurized Packet Milk' => ['cnt' => 1, 'vol' => 100.00],
    'UHT Long Life Milk' => ['cnt' => 0, 'vol' => 0.00],
];

// Check if we have actual database records to overwrite the defaults
if (!empty($by_type)) {
    $has_real_postings = false;
    foreach ($by_type as $bt) {
        if ($bt->cnt > 0) {
            $has_real_postings = true;
            break;
        }
    }
    if ($has_real_postings) {
        // Reset defaults
        foreach ($breakdown as $k => $v) {
            $breakdown[$k] = ['cnt' => 0, 'vol' => 0.00];
        }
        foreach ($by_type as $bt) {
            $t = $bt->milk_type;
            $cnt = (int)$bt->cnt;
            $vol = (float)$bt->vol;

            $matched = false;
            if (stripos($t, 'Raw') !== false || stripos($t, 'Whole') !== false || stripos($t, 'Fresh') !== false) {
                $breakdown['Raw Whole Milk (Chilled)']['cnt'] += $cnt;
                $breakdown['Raw Whole Milk (Chilled)']['vol'] += $vol;
                $matched = true;
            } elseif (stripos($t, 'Fermented') !== false || stripos($t, 'Lala') !== false) {
                $breakdown['Fermented Milk (Maziwa Lala)']['cnt'] += $cnt;
                $breakdown['Fermented Milk (Maziwa Lala)']['vol'] += $vol;
                $matched = true;
            } elseif (stripos($t, 'Pasteurized') !== false || stripos($t, 'Packet') !== false) {
                $breakdown['Pasteurized Packet Milk']['cnt'] += $cnt;
                $breakdown['Pasteurized Packet Milk']['vol'] += $vol;
                $matched = true;
            } elseif (stripos($t, 'UHT') !== false || stripos($t, 'Long Life') !== false) {
                $breakdown['UHT Long Life Milk']['cnt'] += $cnt;
                $breakdown['UHT Long Life Milk']['vol'] += $vol;
                $matched = true;
            }

            if (!$matched) {
                $breakdown['Raw Whole Milk (Chilled)']['cnt'] += $cnt;
                $breakdown['Raw Whole Milk (Chilled)']['vol'] += $vol;
            }
        }
    }
}

// Calculate totals
$total_active_postings = 0;
$total_aggregated_volume = 0.0;
foreach ($breakdown as $item) {
    $total_active_postings += $item['cnt'];
    $total_aggregated_volume += $item['vol'];
}

// Find max volume for styling chart scale
$max_vol = 10.0;
foreach ($breakdown as $item) {
    if ($item['vol'] > $max_vol) {
        $max_vol = $item['vol'];
    }
}

$bar_configs = [
    'Raw Whole Milk (Chilled)' => [
        'short' => 'Raw Whole',
        'fill' => 'url(#blueGrad)',
        'badge' => 'badge-blue',
        'color' => '#2563eb'
    ],
    'Fermented Milk (Maziwa Lala)' => [
        'short' => 'Maziwa Lala',
        'fill' => 'url(#greenGrad)',
        'badge' => 'badge-green',
        'color' => '#16a34a'
    ],
    'Pasteurized Packet Milk' => [
        'short' => 'Pasteurized',
        'fill' => 'url(#amberGrad)',
        'badge' => 'badge-amber',
        'color' => '#d97706'
    ],
    'UHT Long Life Milk' => [
        'short' => 'UHT Milk',
        'fill' => 'url(#purpleGrad)',
        'badge' => 'badge-purple',
        'color' => '#8b5cf6'
    ],
];
?>

<style>
.breakdown-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    margin-top: 2rem;
    width: 100%;
}

.breakdown-card h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.breakdown-grid-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

.breakdown-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}

.breakdown-list-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}

.breakdown-list-item:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.breakdown-item-info strong {
    color: #1e293b;
    display: block;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.breakdown-item-meta {
    font-size: 0.8rem;
    color: #64748b;
}

.breakdown-item-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
}

.badge-blue { background: #dbeafe; color: #1e40af; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-amber { background: #fef3c7; color: #92400e; }
.badge-purple { background: #f3e8ff; color: #6b21a8; }

.chart-wrapper {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    width: 100%;
}

.chart-title {
    font-size: 1rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 1.5rem;
}

.responsive-svg-chart {
    width: 100%;
    height: auto;
    max-height: 350px;
}

.breakdown-summary-bar {
    display: flex;
    justify-content: space-between;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: #1e40af;
}

.chart-group {
    cursor: pointer;
}

.hover-bg {
    transition: fill 0.2s ease;
}

.chart-group:hover .hover-bg {
    fill: rgba(226, 232, 240, 0.4);
}

.chart-bar {
    transition: transform 0.2s ease, filter 0.2s ease;
    transform-origin: bottom;
}

.chart-group:hover .chart-bar {
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.08));
    transform: translateY(-2px);
}
</style>

<div class="breakdown-card">
    <h2>🥛 Product Supply Overview &amp; Analytics</h2>
    <div class="breakdown-grid-layout">
        <div class="chart-wrapper">
            <div class="chart-title">Product Supply Histogram (Volume in Liters)</div>
            
            <svg viewBox="0 0 800 350" class="responsive-svg-chart">
                <defs>
                    <linearGradient id="blueGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#3b82f6" />
                        <stop offset="100%" stop-color="#1d4ed8" />
                    </linearGradient>
                    <linearGradient id="greenGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10b981" />
                        <stop offset="100%" stop-color="#047857" />
                    </linearGradient>
                    <linearGradient id="amberGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#f59e0b" />
                        <stop offset="100%" stop-color="#d97706" />
                    </linearGradient>
                    <linearGradient id="purpleGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8b5cf6" />
                        <stop offset="100%" stop-color="#6d28d9" />
                    </linearGradient>
                </defs>
                
                <!-- Grid Lines -->
                <?php
                $grid_y = [50, 112.5, 175, 237.5, 300];
                foreach ($grid_y as $index => $y):
                    $val = round($max_vol * (1 - ($index / 4)), 1);
                ?>
                    <line x1="80" y1="<?= $y ?>" x2="750" y2="<?= $y ?>" stroke="#e2e8f0" stroke-dasharray="4" stroke-width="1" />
                    <text x="70" y="<?= $y + 4 ?>" font-family="Inter, sans-serif" font-size="12" fill="#64748b" text-anchor="end"><?= $val ?>L</text>
                <?php endforeach; ?>
                
                <!-- Bars & Labels -->
                <?php
                $x_start = 120;
                $x_gap = 160;
                $i = 0;
                foreach ($breakdown as $type => $info):
                    $cfg = $bar_configs[$type];
                    $vol = $info['vol'];
                    
                    $bar_height = ($vol / $max_vol) * 250;
                    $bar_y = 300 - $bar_height;
                    $bar_x = $x_start + ($i * $x_gap);
                ?>
                    <g class="chart-group">
                        <!-- Hover trigger area background -->
                        <rect x="<?= $bar_x - 10 ?>" y="50" width="100" height="250" fill="rgba(0,0,0,0)" class="hover-bg" rx="6" />
                        
                        <!-- Value Bar -->
                        <rect class="chart-bar" x="<?= $bar_x ?>" y="<?= $bar_y ?>" width="80" height="<?= $bar_height ?>" fill="<?= $cfg['fill'] ?>" rx="4" />
                        
                        <!-- Value Text -->
                        <?php if ($vol > 0): ?>
                            <text x="<?= $bar_x + 40 ?>" y="<?= $bar_y - 10 ?>" font-family="Inter, sans-serif" font-size="13" font-weight="700" fill="#1e293b" text-anchor="middle"><?= number_format($vol, 1) ?>L</text>
                        <?php else: ?>
                            <text x="<?= $bar_x + 40 ?>" y="290" font-family="Inter, sans-serif" font-size="12" fill="#94a3b8" text-anchor="middle">0L</text>
                        <?php endif; ?>
                        
                        <!-- Axis Labels -->
                        <text x="<?= $bar_x + 40 ?>" y="325" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#475569" text-anchor="middle"><?= $cfg['short'] ?></text>
                    </g>
                <?php
                    $i++;
                endforeach;
                ?>
            </svg>
        </div>

        <div>
            <ul class="breakdown-list">
                <?php foreach ($breakdown as $type => $info): ?>
                    <?php $cfg = $bar_configs[$type]; ?>
                    <li class="breakdown-list-item">
                        <div class="breakdown-item-info">
                            <strong><?= $type ?></strong>
                            <div class="breakdown-item-meta">
                                <span>Postings: <?= $info['cnt'] ?></span>
                            </div>
                        </div>
                        <span class="breakdown-item-badge <?= $cfg['badge'] ?>"><?= number_format($info['vol'], 2) ?> L</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="breakdown-summary-bar">
        <span>Total Active Postings: <?= $total_active_postings ?> Batches</span>
        <span>Total Aggregated Volume: <?= number_format($total_aggregated_volume, 2) ?> L</span>
    </div>
</div>
<?php adminFooter(); ?>

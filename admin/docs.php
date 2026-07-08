<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

adminHeader('docs', 'Documentation');
?>

<div class="page-header">
    <h1>📋 DAIRY STANDARD OPERATING PROCEDURES</h1>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px,1fr)); gap:1.5rem;">

    <?php
    $sections = [
        ['🧪 Quality Testing & Grading', 'Every delivery undergoes strict testing: Lactometer testing for density/water addition, Butterfat/SNF tests for grading, and antibiotic residue screening before acceptance.'],
        ['❄️ Cold Chain Management', 'Raw milk must be chilled to below 4°C within 2 hours of milking. Bulk Milk Chillers (BMCs) at collection hubs maintain this temperature to preserve freshness.'],
        ['🌡️ Pasteurization & Processing', 'Standard processing includes HTST (High Temperature Short Time) pasteurization to kill pathogens, homogenization to distribute fat, and ultra-clean packaging.'],
        ['💰 Weight Verification & Payments', 'Accurate digital weighing scales verify the milk volume delivered by members. Payouts are calculated based on quality grade and quantity, processed securely.'],
        ['🚜 Collection & Route Logistics', 'Scheduled milk collection routes ensure timely transit from individual farms to the processing plant, minimizing exposure to ambient temperatures.'],
        ['🧼 Hygiene & Sanitation (CIP)', 'Strict Clean-In-Place (CIP) protocols are enforced for bulk tankers, storage tanks, and pipelines using food-safe sanitizers to maintain zero contamination.'],
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

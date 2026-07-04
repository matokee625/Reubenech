<?php
require_once '../includes/auth.php';
requireAdmin();
require_once 'layout.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    adminHeader('transactions', 'Invoice');
    echo '<div class="alert alert-danger">⚠️ Invalid transaction ID.</div>';
    adminFooter();
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT t.*, 
               s.username as seller_name, s.email as seller_email,
               b.username as buyer_name, b.email as buyer_email,
               m.milk_type, m.asking_price
        FROM transactions t
        JOIN users s ON t.seller_id = s.id
        LEFT JOIN users b ON t.buyer_id = b.id
        JOIN milk_postings m ON t.posting_id = m.id
        WHERE t.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $t = $stmt->fetch(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $t = null;
    $error = $e->getMessage();
}

if (!$t) {
    adminHeader('transactions', 'Invoice');
    echo '<div class="alert alert-danger">⚠️ Transaction not found.</div>';
    adminFooter();
    exit();
}

adminHeader('transactions', 'Invoice');
?>

<style>
    .invoice-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        max-width: 800px;
        margin: 0 auto 2rem auto;
        overflow: hidden;
    }
    .invoice-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white;
        padding: 2.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .invoice-header-left h2 {
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
        font-weight: 700;
        letter-spacing: -0.025em;
    }
    .invoice-header-left p {
        margin: 0;
        opacity: 0.8;
        font-size: 0.95rem;
    }
    .invoice-header-right {
        text-align: right;
    }
    .invoice-status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .status-completed {
        background-color: #dcfce7;
        color: #166534;
    }
    .status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }
    .status-cancelled {
        background-color: #fee2e2;
        color: #991b1b;
    }
    .invoice-body {
        padding: 2.5rem;
    }
    .invoice-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2.5rem;
    }
    .details-section h3 {
        margin-top: 0;
        margin-bottom: 1rem;
        color: #0f172a;
        font-size: 1.1rem;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 0.5rem;
    }
    .details-section p {
        margin: 0.5rem 0;
        color: #475569;
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .details-section strong {
        color: #0f172a;
    }
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 2.5rem;
    }
    .invoice-table th {
        background-color: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        color: #475569;
        text-align: left;
        padding: 1rem;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .invoice-table td {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        color: #0f172a;
        font-size: 0.95rem;
    }
    .invoice-totals {
        display: flex;
        justify-content: flex-end;
    }
    .totals-box {
        width: 300px;
    }
    .totals-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        font-size: 0.95rem;
        color: #475569;
        border-bottom: 1px solid #f1f5f9;
    }
    .totals-row.grand-total {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        border-bottom: none;
        padding-top: 1rem;
    }
    .invoice-actions {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-bottom: 3rem;
    }
    
    @media print {
        body {
            background: white !important;
            color: black !important;
        }
        .sidebar, .topbar, .admin-footer, .invoice-actions, .page-header {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .page-content {
            padding: 0 !important;
        }
        .invoice-card {
            box-shadow: none !important;
            border: none !important;
            max-width: 100% !important;
            margin: 0 !important;
        }
        .invoice-header {
            background: #fff !important;
            color: #000 !important;
            border-bottom: 3px solid #000;
            padding: 1.5rem 0;
        }
        .invoice-body {
            padding: 2rem 0;
        }
        .invoice-header-left h2 {
            color: #000 !important;
        }
        .invoice-status-badge {
            border: 1px solid #000;
        }
        .totals-row.grand-total {
            border-top: 2px solid #000;
        }
    }
</style>

<div class="page-header no-print">
    <h1>📄 TRANSACTION INVOICE</h1>
    <a href="transactions.php" class="btn btn-outline">← Back to Transactions</a>
</div>

<div class="invoice-card">
    <div class="invoice-header">
        <div class="invoice-header-left">
            <h2>Reubentech Hub</h2>
            <p>Milk Production System Invoice</p>
        </div>
        <div class="invoice-header-right">
            <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem; opacity: 0.8;">Status</p>
            <span class="invoice-status-badge status-<?= htmlspecialchars($t->status) ?>">
                <?= htmlspecialchars($t->status) ?>
            </span>
        </div>
    </div>
    
    <div class="invoice-body">
        <div class="invoice-details-grid">
            <div class="details-section">
                <h3>Invoice Details</h3>
                <p><strong>Invoice No:</strong> #<?= str_pad($t->id, 5, '0', STR_PAD_LEFT) ?></p>
                <p><strong>Date Issued:</strong> <?= date('d M Y, H:i', strtotime($t->transaction_date)) ?></p>
                <p><strong>Payment Term:</strong> Direct Cash / Transfer</p>
            </div>
            
            <div class="details-section">
                <h3>Parties Involved</h3>
                <p><strong>Seller:</strong> <?= htmlspecialchars($t->seller_name) ?> (<?= htmlspecialchars($t->seller_email) ?>)</p>
                <p><strong>Buyer:</strong> <?= $t->buyer_name ? htmlspecialchars($t->buyer_name) . ' (' . htmlspecialchars($t->buyer_email) . ')' : '<span style="color:#94a3b8">N/A</span>' ?></p>
            </div>
        </div>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Rate / Liter</th>
                    <th>Volume</th>
                    <th style="text-align: right;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Fresh Milk (<?= htmlspecialchars($t->milk_type) ?>)</strong>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: #64748b;">Post Reference ID: #<?= $t->posting_id ?></p>
                    </td>
                    <td>Ksh <?= number_format($t->price / $t->volume, 2) ?></td>
                    <td><?= number_format($t->volume, 1) ?> Liters</td>
                    <td style="text-align: right; font-weight: 600;">Ksh <?= number_format($t->price, 2) ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="invoice-totals">
            <div class="totals-box">
                <div class="totals-row">
                    <span>Subtotal</span>
                    <span>Ksh <?= number_format($t->price, 2) ?></span>
                </div>
                <div class="totals-row">
                    <span>Platform Fee (5%)</span>
                    <span>Ksh <?= number_format($t->price * 0.05, 2) ?></span>
                </div>
                <div class="totals-row grand-total">
                    <span>Grand Total</span>
                    <span>Ksh <?= number_format($t->price, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="invoice-actions no-print">
    <button onclick="window.print()" class="btn btn-primary" style="padding: 0.8rem 2rem; border-radius: 8px;">
        🖨️ Print Invoice
    </button>
</div>

<?php adminFooter(); ?>

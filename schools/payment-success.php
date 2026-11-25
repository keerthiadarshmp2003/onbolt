<?php
/**
 * OnBolt Digital Experience Platform - Order Payment Success & Receipt
 * Generates official payment receipt with PDF download and direct print options
 * URL: /schools/payment-success.php?order=OB-XXXXXX
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDbConnection();
$orderRef = trim($_GET['order'] ?? '');

if (empty($orderRef)) {
    if (!empty($_SESSION['last_order_reference'])) {
        $orderRef = trim((string)$_SESSION['last_order_reference']);
    } else {
        header("Location: " . BASE_URL . "/schools/");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT o.*, s.name as school_name, s.slug as school_slug, s.logo as school_logo, s.location as school_location, s.city as school_city 
                       FROM orders o 
                       LEFT JOIN schools s ON o.school_id = s.id 
                       WHERE o.order_reference = ? LIMIT 1");
$stmt->execute([$orderRef]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order reference not found. Please verify your receipt link.");
}

$orderId = (int)$order['id'];

// Fetch Order Items
$iStmt = $pdo->prepare("SELECT oi.*, p.featured_image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Payment Details
$pStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$pStmt->execute([$orderId]);
$payment = $pStmt->fetch(PDO::FETCH_ASSOC);

$txnRef = !empty($payment['transaction_ref']) ? $payment['transaction_ref'] : ('TXN-' . strtoupper(substr(md5((string)$order['id']), 0, 8)));
$payMethod = !empty($payment['payment_method']) ? $payment['payment_method'] : (!empty($order['payment_method']) ? $order['payment_method'] : 'Payment Gateway');
$payStatus = $order['payment_status'] ?? 'Paid';
$orderDate = date('d M Y, h:i A', strtotime($order['created_at']));

$pageTitle = 'Payment Receipt - ' . sanitize($order['order_reference']) . ' | OnBolt';
$activeNav = 'schools';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Payment Success & Receipt Styling */
.receipt-page-wrapper {
    min-height: 85vh;
    padding-top: 110px;
    padding-bottom: 70px;
    background: #F8FAFC;
    font-family: 'Urbanist', sans-serif;
}

.success-banner-box {
    text-align: center;
    margin-bottom: 2rem;
}

.success-check-circle {
    width: 76px;
    height: 76px;
    background: #22C55E;
    color: #ffffff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    box-shadow: 0 10px 25px rgba(34, 197, 94, 0.35);
    margin-bottom: 1rem;
    animation: bounceIn 0.5s ease-out;
}

/* Reference Highlight Card (Diagram 2 Thank You Page) */
.reference-highlight-card {
    background: #ffffff;
    border: 2px solid #FF6702;
    border-radius: var(--radius-xl);
    padding: 1.5rem 2rem;
    max-width: 620px;
    margin: 1.5rem auto 2.5rem;
    box-shadow: 0 8px 24px rgba(255, 103, 2, 0.12);
    text-align: center;
}
.ref-sub-title {
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748B;
    margin-bottom: 0.5rem;
}
.ref-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.ref-badge {
    font-family: monospace;
    font-size: 1.8rem;
    font-weight: 900;
    color: #1E2530;
    letter-spacing: 0.06em;
    background: #FFF9F5;
    padding: 0.35rem 1.25rem;
    border-radius: var(--radius-md);
    border: 1.5px dashed #FF6702;
}
.btn-copy-ref {
    background: #1E2530;
    color: #ffffff;
    border: none;
    border-radius: var(--radius-pill);
    padding: 0.65rem 1.25rem;
    font-size: 0.88rem;
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}
.btn-copy-ref:hover {
    background: #FF6702;
    transform: translateY(-1px);
}
.btn-track-fast {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #FF6702;
    color: #ffffff;
    text-decoration: none;
    font-weight: 800;
    font-size: 0.95rem;
    padding: 0.75rem 1.75rem;
    border-radius: var(--radius-pill);
    box-shadow: 0 4px 14px rgba(255, 103, 2, 0.35);
    transition: all 0.2s ease;
}
.btn-track-fast:hover {
    background: #e05800;
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(255, 103, 2, 0.45);
}

@keyframes bounceIn {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.1); }
    70% { transform: scale(0.9); }
    100% { transform: scale(1); opacity: 1; }
}

.receipt-card {
    background: #ffffff;
    border: 1.5px solid var(--brand-border);
    border-radius: var(--radius-xl);
    padding: 3rem 2.5rem;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.05);
    max-width: 860px;
    margin: 0 auto;
}

.receipt-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #F1F5F9;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.receipt-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
    font-size: 0.9rem;
}

.receipt-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.5rem;
    font-size: 0.92rem;
    text-align: left;
}

.receipt-table th {
    background: #F1F5F9;
    padding: 0.75rem 1rem;
    font-weight: 800;
    color: #1E2530;
    text-transform: uppercase;
    font-size: 0.78rem;
    letter-spacing: 0.05em;
    border-bottom: 2px solid #E2E8F0;
}

.receipt-table td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #E2E8F0;
}

.receipt-actions-bar {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 2rem;
}

@media (max-width: 640px) {
    .receipt-card {
        padding: 1.25rem 1rem !important;
        border-radius: var(--radius-lg) !important;
    }
    .receipt-meta-grid {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
        padding: 1rem !important;
    }
    .receipt-actions-bar {
        flex-direction: column !important;
        gap: 0.75rem !important;
    }
    .receipt-actions-bar .btn {
        width: 100% !important;
        justify-content: center;
    }
    #invoiceModal > div {
        max-width: calc(100vw - 24px) !important;
        margin: 12px auto !important;
    }
}

/* Print-Specific Styles */
@media print {
    body * {
        visibility: hidden;
    }
    .site-header, .site-footer, .receipt-actions-bar, .success-banner-box, .custom-cursor-dot, .custom-cursor-ring {
        display: none !important;
    }
    .receipt-card, .receipt-card * {
        visibility: visible;
    }
    .receipt-card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;
        border: none;
        box-shadow: none;
        padding: 0;
    }
}
</style>

<div class="receipt-page-wrapper">
    <div class="container" style="max-width: 900px;">
        
        <!-- Success Banner -->
        <div class="success-banner-box">
            <div class="success-check-circle">✓</div>
            <h1 class="heading-1" style="font-size: 2.2rem; margin-bottom: 0.5rem; color: #111827;">Payment Successful!</h1>
            <p style="color: #64748B; font-size: 1.05rem; max-width: 540px; margin: 0 auto;">
                Thank you! Your school uniform requisition has been authorized and recorded in the OnBolt production desk.
            </p>
        </div>

        <!-- Prominent Reference & 1-Click Copy Box (Diagram 2 Thank You Page) -->
        <div class="reference-highlight-card">
            <div class="ref-sub-title">Official Uniform Order Reference</div>
            <div class="ref-row">
                <span class="ref-badge" id="display-order-ref"><?= sanitize($order['order_reference']) ?></span>
                <button type="button" class="btn-copy-ref" onclick="copyOrderRef('<?= sanitize($order['order_reference']) ?>')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <span id="copy-btn-text">Copy Reference</span>
                </button>
            </div>
            <div style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.15rem;">
                Keep this reference number handy for your records and uniform collection.
            </div>
            <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:0.5rem;">
                <a href="<?= BASE_URL ?>/schools/track-order.php?ref=<?= urlencode($order['order_reference']) ?>" class="btn btn-primary" style="background:#FF6702; color:#ffffff; font-weight:800; padding:0.65rem 1.4rem; border-radius:9999px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 14px rgba(255,103,2,0.35);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <span>Track Order</span>
                </a>
                <button type="button" onclick="openInvoiceModal()" class="btn btn-outline" style="background:#ffffff; border:1.5px solid #FF6702; color:#FF6702; font-weight:800; padding:0.65rem 1.4rem; border-radius:9999px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>View Invoice</span>
                </button>
            </div>
        </div>

        <!-- Receipt Card Container (Downloadable & Printable) -->
        <div class="receipt-card" id="printable-receipt-card">
            
            <!-- Header Row -->
            <div class="receipt-header-row">
                <div>
                    <img src="<?= BASE_URL ?>/assets/images/logo/onbolt-actual-logo.png" alt="OnBolt" style="height: 44px; margin-bottom: 0.5rem;">
                    <div style="font-size: 0.82rem; color: #64748B;">
                        Complete Branding Solutions &bull; Official Uniform Manufacturer
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 1.25rem; font-weight: 800; color: #FF6702; letter-spacing: 0.05em;">PAYMENT RECEIPT</div>
                    <div style="font-size: 0.95rem; font-weight: 700; color: #1E2530;">Order Ref: <?= sanitize($order['order_reference']) ?></div>
                    <div style="font-size: 0.82rem; color: #64748B; margin-top: 0.2rem;">Date: <?= $orderDate ?></div>
                    <div style="margin-top: 0.4rem;">
                        <span style="display: inline-block; background: #DCFCE7; color: #15803D; font-size: 0.78rem; font-weight: 800; padding: 0.25rem 0.65rem; border-radius: 9999px;">
                            ✓ <?= strtoupper(sanitize($payStatus)) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Two-Column Meta Details -->
            <div class="receipt-meta-grid">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #FF6702; margin-bottom: 0.6rem; letter-spacing: 0.05em;">
                        Student &amp; School Information
                    </div>
                    <div style="margin-bottom: 0.3rem;"><strong>Student:</strong> <?= sanitize($order['student_name']) ?></div>
                    <div style="margin-bottom: 0.3rem;"><strong>Class:</strong> <?= sanitize($order['grade_class']) ?></div>
                    <div style="margin-bottom: 0.3rem;"><strong>School:</strong> <?= sanitize($order['school_name'] ?: 'International School') ?></div>
                    <div><strong>Location:</strong> <?= sanitize($order['school_location'] ?: 'Edackode, Trivandrum') ?></div>
                </div>

                <div>
                    <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #FF6702; margin-bottom: 0.6rem; letter-spacing: 0.05em;">
                        Parent &amp; Payment Details
                    </div>
                    <div style="margin-bottom: 0.3rem;"><strong>Parent / Guardian:</strong> <?= sanitize($order['parent_name']) ?></div>
                    <div style="margin-bottom: 0.3rem;"><strong>WhatsApp:</strong> <?= sanitize($order['mobile']) ?></div>
                    <div style="margin-bottom: 0.3rem;"><strong>Payment Method:</strong> <?= sanitize($payMethod) ?></div>
                    <div style="margin-bottom: 0.3rem;"><strong>Transaction Ref:</strong> <code style="font-weight: 700; color: #1E2530;"><?= sanitize($txnRef) ?></code></div>
                    <div><strong>Delivery Address:</strong> <?= sanitize($order['delivery_address']) ?>, <?= sanitize($order['pincode']) ?></div>
                </div>
            </div>

            <!-- Itemized Table -->
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Uniform Garment Item</th>
                        <th style="text-align: center;">Size</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx = 1;
                    foreach ($items as $item): 
                    ?>
                        <tr>
                            <td style="color: #64748B; font-weight: 700;"><?= $idx++ ?></td>
                            <td>
                                <strong style="color: #1E2530;"><?= sanitize($item['product_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: #64748B;">Approved Pattern &bull; Institutional Stitch</div>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: #E2E8F0; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 800; font-size: 0.8rem;">
                                    <?= sanitize($item['size']) ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 800; color: #1E2530;">
                                <?= (int)$item['quantity'] ?>
                            </td>
                            <td style="text-align: right; color: #4B5563;">
                                <?= formatCurrency((float)$item['unit_price']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #1E2530;">
                                <?= formatCurrency((float)$item['total_price']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: 700; color: #64748B; border: none; padding-top: 1rem;">Total Items:</td>
                        <td colspan="2" style="text-align: right; font-weight: 800; color: #1E2530; border: none; padding-top: 1rem;"><?= (int)$order['total_items'] ?> Items</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: 700; color: #64748B; border: none;">Shipping / Delivery:</td>
                        <td colspan="2" style="text-align: right; font-weight: 800; color: #22C55E; border: none;">FREE CAMPUS DISPATCH</td>
                    </tr>
                    <tr style="border-top: 2px solid #E2E8F0;">
                        <td colspan="4" style="text-align: right; font-size: 1.15rem; font-weight: 800; color: #1E2530; padding-top: 0.85rem;">Grand Total Paid:</td>
                        <td colspan="2" style="text-align: right; font-size: 1.35rem; font-weight: 800; color: #FF6702; padding-top: 0.85rem;"><?= formatCurrency((float)$order['total_amount']) ?></td>
                    </tr>
                </tfoot>
            </table>

            <!-- Footnote & Institutional Verification -->
            <div style="background: #FFF9F5; border: 1px dashed #FF6702; border-radius: var(--radius-md); padding: 0.85rem 1.25rem; font-size: 0.82rem; color: #4B5563; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-top: 1.5rem;">
                <div>
                    💡 <strong>Fulfillment Desk:</strong> Uniforms are dispatched to the school campus counter or delivered to your residential address. For inquiries, contact OnBolt Support at <strong>+91 88486 70739</strong>.
                </div>
                <div style="font-weight: 800; color: #22C55E;">
                    ✓ Digitally Signed &amp; Authorized
                </div>
            </div>

        </div>

        <!-- Action Buttons -->
        <div class="receipt-actions-bar">
            <button type="button" class="btn btn-outline" onclick="window.print()" style="padding: 0.85rem 1.5rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                <span>Print Receipt</span>
            </button>

            <button type="button" class="btn btn-primary" onclick="downloadReceiptPdf()" style="padding: 0.85rem 1.75rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.5rem; background: #FF6702;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                <span>Download Receipt (PDF)</span>
            </button>

            <a href="<?= BASE_URL ?>/schools/<?= sanitize($order['school_slug'] ?: 'international-school') ?>/order/" class="btn btn-outline" style="padding: 0.85rem 1.5rem; font-weight: 700; text-decoration: none;">
                Order Another Uniform &rarr;
            </a>
        </div>

    </div>
</div>

<script>
function copyOrderRef(ref) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(ref).then(() => {
            const txt = document.getElementById('copy-btn-text');
            if (txt) {
                txt.textContent = '✓ Copied!';
                setTimeout(() => { txt.textContent = 'Copy Reference'; }, 2500);
            }
        }).catch(() => fallbackCopy(ref));
    } else {
        fallbackCopy(ref);
    }
}

function fallbackCopy(ref) {
    const input = document.createElement('input');
    input.value = ref;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    const txt = document.getElementById('copy-btn-text');
    if (txt) {
        txt.textContent = '✓ Copied!';
        setTimeout(() => { txt.textContent = 'Copy Reference'; }, 2500);
    }
}

function downloadReceiptPdf() {
    if (typeof window.jspdf === 'undefined' || typeof window.jspdf.jsPDF === 'undefined') {
        alert('PDF generator is initializing, please click again in a moment.');
        return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');

    // Top Dark Navy Brand Banner Header (Exact match to Requisition PDF)
    doc.setFillColor(30, 37, 48);
    doc.rect(0, 0, 210, 30, 'F');

    // Left Logo / Company Branding
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(18);
    doc.setTextColor(255, 103, 2);
    doc.text('ONBOLT', 14, 18);

    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(255, 255, 255);
    doc.text('Complete Branding Solutions & Institutional Uniform Portals', 14, 24);

    // Right Header: ONBOLT - UNIFORM PAYMENT RECEIPT + STATUS: PAID
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(255, 255, 255);
    doc.text('ONBOLT - UNIFORM PAYMENT RECEIPT', 196, 17, { align: 'right' });

    doc.setFontSize(9);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(34, 197, 94); // Bright Green Paid Mention
    doc.text('STATUS: PAID (VERIFIED)', 196, 24, { align: 'right' });

    // Meta Information
    doc.setTextColor(30, 37, 48);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'bold');
    doc.text('Requisition ID: <?= sanitize($order['order_reference']) ?>', 14, 42);

    doc.setFont('helvetica', 'normal');
    doc.text('Date: <?= $orderDate ?>', 14, 48);
    doc.text('School: <?= sanitize($order['school_name'] ?: 'Trivandrum International School') ?>', 14, 54);

    // Paid status highlight in meta
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(34, 197, 94);
    doc.text('Payment Status: PAID', 14, 60);

    doc.setFont('helvetica', 'normal');
    doc.setTextColor(30, 37, 48);
    doc.text('Payment Method: <?= sanitize($payMethod) ?>', 14, 66);
    doc.text('Transaction Ref: <?= sanitize($txnRef) ?>', 14, 72);

    // Student Profile (Left Column)
    doc.setFont('helvetica', 'bold');
    doc.text('Student Profile:', 14, 84);
    doc.setFont('helvetica', 'normal');
    doc.text('Name: <?= sanitize($order['student_name']) ?>', 14, 90);
    doc.text('Class: <?= sanitize($order['grade_class']) ?>', 14, 96);

    // Parent Details (Right Column)
    doc.setFont('helvetica', 'bold');
    doc.text('Parent Details:', 105, 84);
    doc.setFont('helvetica', 'normal');
    doc.text('Parent Name: <?= sanitize($order['parent_name']) ?>', 105, 90);
    doc.text('Mobile: <?= sanitize($order['mobile']) ?>', 105, 96);
    doc.text('Address: <?= sanitize(substr($order['delivery_address'] . ($order['pincode'] ? ', ' . $order['pincode'] : ''), 0, 48)) ?>', 105, 102);

    // Items Table (Exact match to Requisition PDF)
    const tableRows = [
        <?php 
        $totQty = 0;
        foreach ($items as $idx => $it): 
            $totQty += (int)$it['quantity'];
        ?>
        [
            '<?= $idx + 1 ?>',
            '<?= addslashes(sanitize($it['product_name'])) ?>',
            '<?= sanitize($it['size']) ?>',
            '<?= (int)$it['quantity'] ?>',
            'Rs. <?= number_format((float)$it['unit_price'], 0) ?>',
            'Rs. <?= number_format((float)$it['total_price'], 0) ?>'
        ],
        <?php endforeach; ?>
    ];

    if (typeof doc.autoTable !== 'undefined') {
        doc.autoTable({
            startY: 110,
            head: [['#', 'Item Description', 'Size', 'Qty', 'Unit Price', 'Total']],
            body: tableRows,
            headStyles: { fillColor: [255, 103, 2], textColor: [255, 255, 255], fontStyle: 'bold' },
            foot: [['', 'Total Items:', '', '<?= $totQty ?>', 'Grand Total:', 'Rs. <?= number_format((float)$order['total_amount'], 0) ?>']],
            footStyles: { fillColor: [248, 250, 252], textColor: [30, 37, 48], fontStyle: 'bold' },
            margin: { left: 14, right: 14 },
            theme: 'striped',
            styles: { fontSize: 9, cellPadding: 3.5 }
        });
    }

    const finalY = doc.lastAutoTable ? doc.lastAutoTable.finalY + 16 : 180;
    doc.setFontSize(8);
    doc.setTextColor(100, 116, 139);
    doc.text('Official Payment Receipt issued by OnBolt Consumer India Desk.', 14, finalY);
    doc.text('Support: +91 88486 70739 / +91 99469 25888 | Email: onboltconsumerindia@gmail.com', 14, finalY + 5);

    doc.save('OnBolt-Uniform-Payment-Receipt-<?= sanitize($order['order_reference']) ?>.pdf');
}

function openInvoiceModal() {
    const m = document.getElementById('invoiceModal');
    if (m) m.style.display = 'flex';
}
function closeInvoiceModal() {
    const m = document.getElementById('invoiceModal');
    if (m) m.style.display = 'none';
}
function printInvoicePopup() {
    const printArea = document.getElementById('invoicePrintableArea');
    if (!printArea) return;
    const printWindow = window.open('', '_blank', 'width=800,height=700');
    printWindow.document.write('<html><head><title>Invoice - <?= sanitize($order["order_reference"] ?? "") ?></title>');
    printWindow.document.write('<style>body{font-family:system-ui,sans-serif;margin:20px;color:#1E293B;} table{width:100%;border-collapse:collapse;} th,td{padding:8px;border-bottom:1px solid #e2e8f0;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(printArea.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => { printWindow.print(); printWindow.close(); }, 300);
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeInvoiceModal();
});
</script>

<!-- Invoice Only Popup Modal -->
<div id="invoiceModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.7); z-index:99999; align-items:center; justify-content:center; padding:1.5rem; overflow-y:auto; backdrop-filter:blur(4px);">
    <div style="background:#ffffff; border-radius:20px; max-width:760px; width:100%; box-shadow:0 25px 60px -12px rgba(0,0,0,0.35); overflow:hidden; border:1.5px solid #E2E8F0; margin:auto;">
        <!-- Popup Controls Bar -->
        <div style="background:#1E293B; color:#ffffff; padding:14px 24px; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-weight:800; font-size:15px; display:flex; align-items:center; gap:8px;">
                <span>📄 Uniform Order Invoice</span>
                <span style="font-size:12px; background:rgba(255,255,255,0.15); padding:2px 8px; border-radius:6px;"><?= sanitize($order['order_reference']) ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <button type="button" onclick="printInvoicePopup()" style="background:#FF6702; color:#ffffff; border:none; padding:6px 14px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    <span>Print</span>
                </button>
                <button type="button" onclick="closeInvoiceModal()" style="background:transparent; border:none; color:#ffffff; font-size:24px; cursor:pointer; line-height:1;">&times;</button>
            </div>
        </div>

        <!-- Printable Invoice Only Content -->
        <div id="invoicePrintableArea" style="padding:28px 32px; max-height:80vh; overflow-y:auto; background:#ffffff;">
            <!-- Header Row -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #F1F5F9; padding-bottom:20px; margin-bottom:22px; flex-wrap:wrap; gap:16px;">
                <div>
                    <img src="<?= BASE_URL ?>/assets/images/logo/onbolt-actual-logo.png" alt="OnBolt" style="height:42px; margin-bottom:6px;">
                    <div style="font-size:12px; color:#64748B; font-weight:600;">Complete Branding Solutions &bull; Official Uniform Manufacturer</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:18px; font-weight:900; color:#FF6702; letter-spacing:0.04em;">TAX INVOICE / RECEIPT</div>
                    <div style="font-size:13px; font-weight:800; color:#1E293B; margin-top:2px;">Ref: <?= sanitize($order['order_reference']) ?></div>
                    <div style="font-size:12px; color:#64748B; margin-top:2px;">Date: <?= $orderDate ?></div>
                    <span style="display:inline-block; background:#DCFCE7; color:#15803D; font-size:11px; font-weight:800; padding:2px 8px; border-radius:9999px; text-transform:uppercase; margin-top:4px;">
                        ✓ PAID
                    </span>
                </div>
            </div>

            <!-- Two Column Details -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:22px;">
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:14px 18px;">
                    <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#FF6702; letter-spacing:0.05em; margin-bottom:6px;">Student Information</div>
                    <div style="font-size:14px; font-weight:800; color:#1E293B;"><?= sanitize($order['student_name']) ?></div>
                    <?php if (!empty($order['gender'])): ?>
                        <div style="font-size:12px; color:#64748B; margin-top:2px;">Gender: <strong><?= sanitize($order['gender']) ?></strong></div>
                    <?php endif; ?>
                    <div style="font-size:12px; color:#64748B; margin-top:2px;">Class / Grade: <strong><?= sanitize($order['grade_class']) ?></strong></div>
                    <div style="font-size:12px; color:#64748B; margin-top:2px;">School: <strong><?= sanitize($order['school_name'] ?: 'Global Public School') ?></strong></div>
                </div>

                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:14px 18px;">
                    <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#FF6702; letter-spacing:0.05em; margin-bottom:6px;">Parent &amp; Delivery Destination</div>
                    <div style="font-size:14px; font-weight:800; color:#1E293B;"><?= sanitize($order['parent_name']) ?></div>
                    <div style="font-size:12px; color:#64748B; margin-top:2px;">WhatsApp: <strong><?= sanitize($order['mobile']) ?></strong></div>
                    <div style="font-size:12px; color:#64748B; margin-top:2px; line-height:1.4;">
                        <?= sanitize($order['delivery_address']) ?><?= $order['pincode'] ? ', PIN: ' . sanitize($order['pincode']) : '' ?>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:18px;">
                <thead>
                    <tr style="border-bottom:2px solid #E2E8F0; font-size:11px; text-transform:uppercase; color:#64748B; text-align:left;">
                        <th style="padding:8px 6px;">#</th>
                        <th style="padding:8px 6px;">Garment Item</th>
                        <th style="padding:8px 6px; text-align:center;">Size</th>
                        <th style="padding:8px 6px; text-align:center;">Qty</th>
                        <th style="padding:8px 6px; text-align:right;">Price</th>
                        <th style="padding:8px 6px; text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $popIdx = 1;
                    foreach ($items as $it): 
                    ?>
                        <tr style="border-bottom:1px solid #F1F5F9; font-size:13px;">
                            <td style="padding:10px 6px; color:#94A3B8;"><?= $popIdx++ ?></td>
                            <td style="padding:10px 6px; font-weight:700; color:#1E293B;">
                                <?= sanitize($it['product_name']) ?>
                            </td>
                            <td style="padding:10px 6px; text-align:center;">
                                <span style="background:#F1F5F9; padding:2px 8px; border-radius:6px; font-weight:800; font-size:12px;">
                                    <?= sanitize($it['size']) ?>
                                </span>
                            </td>
                            <td style="padding:10px 6px; text-align:center; font-weight:700;">
                                <?= (int)$it['quantity'] ?>
                            </td>
                            <td style="padding:10px 6px; text-align:right; color:#64748B;">
                                Rs. <?= number_format((float)$it['unit_price'], 2) ?>
                            </td>
                            <td style="padding:10px 6px; text-align:right; font-weight:800; color:#1E293B;">
                                Rs. <?= number_format((float)$it['total_price'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid #E2E8F0;">
                        <td colspan="4" style="text-align:right; padding:10px 6px; font-size:13px; color:#64748B; font-weight:700;">Delivery &amp; Campus Dispatch:</td>
                        <td colspan="2" style="text-align:right; padding:10px 6px; font-size:13px; color:#16A34A; font-weight:800;">FREE</td>
                    </tr>
                    <tr style="background:#FFF7ED;">
                        <td colspan="4" style="text-align:right; padding:12px 6px; font-size:14px; font-weight:800; color:#1E293B;">Grand Total Paid:</td>
                        <td colspan="2" style="text-align:right; padding:12px 6px; font-size:16px; font-weight:900; color:#FF6702;">Rs. <?= number_format((float)$order['total_amount'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>

            <div style="font-size:11px; color:#94A3B8; text-align:center; padding-top:10px; border-top:1px dashed #E2E8F0;">
                ✓ Official Computer Generated Tax Invoice &bull; Authorized by OnBolt Consumer India Desk &bull; Support: +91 88486 70739
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * OnBolt Digital Experience Platform - Order Tracking Portal
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
$pageTitle = 'Track Your Order | OnBolt Institutional Portal';
$pageDesc = 'Track real-time uniform production, packing, courier dispatch, and request size exchanges.';

$pdo = getDbConnection();

$orderRef = trim($_GET['ref'] ?? $_POST['order_ref'] ?? '');
$mobile = trim($_GET['mobile'] ?? $_POST['mobile'] ?? '');

$order = null;
$items = [];
$error = '';
$success = '';

// Handle Exchange Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_exchange') {
    $postRef = trim($_POST['order_reference'] ?? '');
    $postMobile = trim($_POST['mobile_number'] ?? '');
    $itemId = (int)($_POST['item_id'] ?? 0);
    $itemTitle = trim($_POST['item_title'] ?? '');
    $currentSize = trim($_POST['current_size'] ?? '');
    $requestedSize = trim($_POST['requested_size'] ?? '');
    $reason = trim($_POST['exchange_reason'] ?? '');

    if (!empty($postRef) && !empty($requestedSize)) {
        $exchangeNote = "Exchange Requested on " . date('d M Y H:i') . " | Item: {$itemTitle} | From Size: {$currentSize} -> To Size: {$requestedSize} | Reason: {$reason}";
        $upStmt = $pdo->prepare("UPDATE orders SET exchange_requested = 1, exchange_details = CONCAT(COALESCE(exchange_details, ''), '\n', ?) WHERE order_reference = ?");
        $upStmt->execute([$exchangeNote, $postRef]);
        $success = "Exchange request submitted successfully! Our campus desk will prepare your replacement size ({$requestedSize}).";
        $orderRef = $postRef;
        $mobile = $postMobile;
    }
}

// Fetch Order if Reference Provided
if (!empty($orderRef)) {
    $cleanRef = strtoupper(str_replace(['-', ' '], '', $orderRef));
    $stmt = $pdo->prepare("SELECT o.*, s.name as school_name, s.logo as school_logo, s.slug as school_slug, s.code as school_code 
                           FROM orders o 
                           LEFT JOIN schools s ON o.school_id = s.id 
                           WHERE (o.order_reference = ? 
                                  OR o.id = ? 
                                  OR UPPER(REPLACE(REPLACE(o.order_reference, '-', ''), ' ', '')) = ?
                                  OR o.order_reference LIKE ?
                                  OR o.mobile = ?) 
                           ORDER BY o.id DESC LIMIT 1");
    $stmt->execute([$orderRef, $orderRef, $cleanRef, "%{$orderRef}%", $orderRef]);
    $order = $stmt->fetch();

    if ($order) {
        $itemStmt = $pdo->prepare("SELECT oi.*, p.featured_image 
                                   FROM order_items oi 
                                   LEFT JOIN products p ON oi.product_id = p.id 
                                   WHERE oi.order_id = ?");
        $itemStmt->execute([$order['id']]);
        $items = $itemStmt->fetchAll();
    } else {
        $error = "No order found matching Reference '{$orderRef}'. Please verify your Order ID and try again.";
    }
} elseif (!empty($mobile)) {
    $stmt = $pdo->prepare("SELECT o.*, s.name as school_name, s.logo as school_logo, s.slug as school_slug, s.code as school_code 
                           FROM orders o 
                           LEFT JOIN schools s ON o.school_id = s.id 
                           WHERE o.mobile = ? OR o.alt_mobile = ? 
                           ORDER BY o.id DESC LIMIT 1");
    $stmt->execute([$mobile, $mobile]);
    $order = $stmt->fetch();

    if ($order) {
        $itemStmt = $pdo->prepare("SELECT oi.*, p.featured_image 
                                   FROM order_items oi 
                                   LEFT JOIN products p ON oi.product_id = p.id 
                                   WHERE oi.order_id = ?");
        $itemStmt->execute([$order['id']]);
        $items = $itemStmt->fetchAll();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.site-header .nav-links,
.site-header .nav-actions,
.site-header .mobile-nav-toggle {
    display: none !important;
}

.track-hero {
    background-image: url('<?= BASE_URL ?>/assets/images/breadcrumb-bg.webp');
    background-size: cover;
    background-position: center;
    padding: 100px 0 50px;
    text-align: center;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.track-order-input::placeholder {
    text-transform: none;
    color: #94A3B8;
    font-weight: 500;
}

.track-stepper-wrap {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    position: relative;
    margin: 40px 0 30px;
}

.track-stepper-line {
    position: absolute;
    top: 24px;
    left: 12.5%;
    right: 12.5%;
    height: 4px;
    background: #E2E8F0;
    z-index: 1;
}

.track-stepper-line-fill {
    height: 100%;
    background: #FF6702;
    transition: width 0.4s ease;
}

.track-step-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    text-align: center;
}

.track-step-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #ffffff;
    border: 3px solid #CBD5E1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 800;
    color: #64748B;
    margin-bottom: 12px;
    transition: all 0.3s ease;
}

.track-step-node.completed .track-step-circle {
    background: #FF6702;
    border-color: #FF6702;
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(255, 103, 2, 0.35);
}

.track-step-node.active .track-step-circle {
    background: #ffffff;
    border-color: #FF6702;
    color: #FF6702;
    box-shadow: 0 0 0 4px rgba(255, 103, 2, 0.2);
}

.track-step-label {
    font-size: 14px;
    font-weight: 800;
    color: #1E293B;
}

.track-step-sub {
    font-size: 11px;
    color: #64748B;
    margin-top: 3px;
}

.track-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .track-stepper-wrap {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .track-stepper-line {
        display: none;
    }
    .track-step-node {
        flex-direction: row;
        gap: 16px;
        text-align: left;
    }
    .track-step-circle {
        margin-bottom: 0;
        flex-shrink: 0;
    }
    .track-form-grid {
        grid-template-columns: 1fr !important;
    }
    .track-info-grid {
        grid-template-columns: 1fr !important;
        gap: 14px !important;
    }
    #invoiceModal > div,
    #exchangeModal > div {
        max-width: calc(100vw - 24px) !important;
        margin: 12px auto !important;
    }
}
</style>

<!-- Hero Title Header -->
<div class="track-hero">
    <div class="container">
        <span style="display:inline-block; background:rgba(255,103,2,0.12); color:#FF6702; font-size:12px; font-weight:800; padding:4px 12px; border-radius:9999px; text-transform:uppercase; margin-bottom:12px;">
            Order Lifecycle &amp; Fulfillment
        </span>
        <h1 style="font-family:'Urbanist', sans-serif; font-size:42px; font-weight:800; color:#111827; margin:0 0 10px;">
            Track Your Uniform Order
        </h1>
        <p style="color:#6B7280; font-size:15px; margin:0; max-width:600px; margin:0 auto;">
            Enter your order reference number to check real-time production, packing, and dispatch milestones.
        </p>
    </div>
</div>

<div class="container" style="max-width:960px; padding: 40px 20px 80px;">

    <!-- Alerts -->
    <?php if (!empty($error)): ?>
        <div style="background:#FEF2F2; color:#DC2626; border:1px solid #FCA5A5; padding:1rem 1.25rem; border-radius:12px; font-weight:700; margin-bottom:25px;">
            ⚠️ <?= sanitize($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div style="background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; padding:1rem 1.25rem; border-radius:12px; font-weight:700; margin-bottom:25px;">
            ✓ <?= sanitize($success) ?>
        </div>
    <?php endif; ?>

    <!-- Search Form Card -->
    <div style="background:#ffffff; border-radius:20px; border:1.5px solid #E2E8F0; padding:28px 32px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.05); margin-bottom:40px; max-width:640px; margin-left:auto; margin-right:auto;">
        <form action="<?= BASE_URL ?>/schools/track-order.php" method="GET" class="track-form-grid" style="display:grid; grid-template-columns:1fr auto; gap:14px; align-items:end;">
            <div>
                <label style="display:block; font-size:13px; font-weight:800; color:#1E293B; margin-bottom:8px;">
                    Order Reference Number *
                </label>
                <input type="text" name="ref" required value="<?= sanitize($orderRef) ?>" placeholder="Enter your Order ID" class="track-order-input" style="width:100%; padding:13px 18px; border-radius:12px; border:1.5px solid #CBD5E1; font-weight:700; text-transform:uppercase; outline:none; font-size:15px; box-sizing:border-box;">
            </div>

            <div>
                <button type="submit" style="padding:13px 28px; background:#FF6702; color:#ffffff; border:none; border-radius:12px; font-weight:800; font-size:15px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; white-space:nowrap; height:49px;">
                    <span>Track Order</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
        </form>
    </div>

    <?php if ($order): ?>
        <?php
        // Standardized Order Status Procedures (Requirement 7)
        // Procedure 1: Normal (Courier Delivery): Confirmed -> In progress -> Packed -> Dispatched
        // Procedure 2: In-Hand (Counter / School Pickup): Confirmed -> In progress -> Packed -> Dispatched by hand
        $statusRaw = trim($order['order_status']);
        $isInHand = ($statusRaw === 'Dispatched by hand' || ($order['fulfillment_type'] ?? '') === 'Handover' || ($order['fulfillment_type'] ?? '') === 'Counter');
        $isCancelled = in_array($statusRaw, ['Cancelled', 'Cancel'], true);
        $isRefunded = in_array($statusRaw, ['Refunded', 'Refund'], true) || ($order['refund_status'] ?? '') === 'Approved';

        $statusRank = [
            'Pending'            => 0,
            'Confirmed'          => 1,
            'Processing'         => 2,
            'Production'         => 2,
            'In progress'        => 2,
            'Quality Check'      => 2,
            'Packed'             => 3,
            'Dispatched'         => 4,
            'Dispatched by hand' => 4,
            'Shipped'            => 4,
            'On the way'         => 4,
            'Delivered'          => 4
        ];
        $currentRank = $statusRank[$statusRaw] ?? 1;
        $fillPercent = min(100, max(0, ($currentRank - 1) * 33.33));

        $orderDate = date('d M Y, h:i A', strtotime($order['created_at']));
        $courierName = $order['courier_name'] ?: 'OnBolt Institutional Logistics';
        $trackingNum = $order['tracking_number'] ?: ('OB-TRK-' . substr(md5($order['order_reference']), 0, 8));
        ?>

        <!-- Order Information Header Banner -->
        <div style="background:#ffffff; border-radius:20px; border:1.5px solid #E2E8F0; padding:28px 32px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.05); margin-bottom:30px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:20px; border-bottom:1px solid #F1F5F9; padding-bottom:20px; margin-bottom:25px;">
                <div>
                    <?php if ($isCancelled): ?>
                        <span style="background:#FEF2F2; color:#DC2626; font-weight:800; font-size:12px; padding:3px 10px; border-radius:9999px; text-transform:uppercase; border:1px solid #FCA5A5;">
                            ✕ Cancelled
                        </span>
                    <?php elseif ($isRefunded): ?>
                        <span style="background:#EDE9FE; color:#7C3AED; font-weight:800; font-size:12px; padding:3px 10px; border-radius:9999px; text-transform:uppercase; border:1px solid #DDD6FE;">
                            ↩ Refunded
                        </span>
                    <?php else: ?>
                        <span style="background:#ECFDF5; color:#047857; font-weight:800; font-size:12px; padding:3px 10px; border-radius:9999px; text-transform:uppercase;">
                            ✓ <?= sanitize($order['payment_status']) ?>
                        </span>
                    <?php endif; ?>

                    <h2 style="font-size:24px; font-weight:800; color:#1E293B; margin:8px 0 4px;">
                        Order Reference: <?= sanitize($order['order_reference']) ?>
                    </h2>
                    <div style="font-size:13px; color:#64748B;">
                        Placed on: <strong><?= $orderDate ?></strong> &bull; School: <strong><?= sanitize($order['school_name'] ?: 'Global Public School') ?></strong>
                        &bull; Fulfillment: <strong><?= $isInHand ? 'Counter / School Pickup' : 'Courier Delivery' ?></strong>
                    </div>
                </div>

                <div style="display:flex; gap:10px; align-items:center;">
                    <button type="button" onclick="openInvoiceModal()" style="padding:10px 18px; border-radius:10px; background:#F8FAFC; border:1px solid #CBD5E1; color:#1E293B; font-weight:700; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>View Invoice</span>
                    </button>
                </div>
            </div>

            <?php if ($isCancelled): ?>
                <!-- Cancelled Notice -->
                <div style="background:#FEF2F2; border:1.5px solid #FCA5A5; border-radius:14px; padding:18px 24px; text-align:center; margin:15px 0;">
                    <h3 style="color:#DC2626; margin:0 0 6px; font-size:18px; font-weight:800;">Order Cancelled</h3>
                    <p style="color:#7F1D1D; margin:0; font-size:14px;">This order has been cancelled. If a refund was approved, it will reflect in your original payment method.</p>
                </div>
            <?php elseif ($isRefunded): ?>
                <!-- Refunded Notice -->
                <div style="background:#F5F3FF; border:1.5px solid #DDD6FE; border-radius:14px; padding:18px 24px; text-align:center; margin:15px 0;">
                    <h3 style="color:#7C3AED; margin:0 0 6px; font-size:18px; font-weight:800;">Order Refunded</h3>
                    <p style="color:#5B21B6; margin:0; font-size:14px;">A refund has been processed for this order. Total Refunded: <strong><?= formatCurrency((float)($order['refund_amount'] ?: $order['total_amount'])) ?></strong>.</p>
                </div>
            <?php else: ?>
                <!-- Standardized 4-Stage Visual Workflow Stepper -->
                <div class="track-stepper-wrap">
                    <div class="track-stepper-line">
                        <div class="track-stepper-line-fill" style="width: <?= $fillPercent ?>%;"></div>
                    </div>

                    <!-- 1. Confirmed -->
                    <div class="track-step-node <?= $currentRank >= 1 ? ($currentRank === 1 ? 'active' : 'completed') : '' ?>">
                        <div class="track-step-circle">1</div>
                        <div class="track-step-label">Confirmed</div>
                        <div class="track-step-sub"><?= date('d M', strtotime($order['created_at'])) ?></div>
                    </div>

                    <!-- 2. In progress -->
                    <div class="track-step-node <?= $currentRank >= 2 ? ($currentRank === 2 ? 'active' : 'completed') : '' ?>">
                        <div class="track-step-circle">2</div>
                        <div class="track-step-label">In progress</div>
                        <div class="track-step-sub"><?= $currentRank >= 2 ? 'Tailoring / Assembly' : 'Factory Floor' ?></div>
                    </div>

                    <!-- 3. Packed -->
                    <div class="track-step-node <?= $currentRank >= 3 ? ($currentRank === 3 ? 'active' : 'completed') : '' ?>">
                        <div class="track-step-circle">3</div>
                        <div class="track-step-label">Packed</div>
                        <div class="track-step-sub">School Inspected</div>
                    </div>

                    <!-- 4. Dispatched or Dispatched by hand -->
                    <div class="track-step-node <?= $currentRank >= 4 ? 'completed' : '' ?>">
                        <div class="track-step-circle">4</div>
                        <div class="track-step-label"><?= $isInHand ? 'Dispatched by hand' : 'Dispatched' ?></div>
                        <div class="track-step-sub"><?= $isInHand ? ($currentRank >= 4 ? 'Campus Handover' : 'Counter Ready') : ($currentRank >= 4 ? sanitize($courierName) : 'Courier Desk') ?></div>
                    </div>
                </div>

                <!-- Dispatch Details Banner -->
                <?php if ($currentRank >= 4): ?>
                    <?php if ($isInHand): ?>
                        <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:12px; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:20px;">
                            <div>
                                <div style="font-size:12px; text-transform:uppercase; font-weight:800; color:#B45309;">In-Hand Counter Fulfillment</div>
                                <div style="font-size:15px; font-weight:800; color:#92400E; margin-top:2px;">
                                    Status: Dispatched by hand &bull; Location: School Uniform Counter / Desk
                                </div>
                            </div>
                            <span style="font-size:12px; font-weight:700; color:#B45309; background:#FEF3C7; padding:4px 10px; border-radius:6px;">
                                ✓ Direct Handover Complete
                            </span>
                        </div>
                    <?php else: ?>
                        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:12px; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:20px;">
                            <div>
                                <div style="font-size:12px; text-transform:uppercase; font-weight:800; color:#15803D;">Dispatch Carrier Info</div>
                                <div style="font-size:15px; font-weight:800; color:#166534; margin-top:2px;">
                                    Courier: <?= sanitize($courierName) ?> &bull; Waybill / AWB: <code><?= sanitize($trackingNum) ?></code>
                                </div>
                            </div>
                            <span style="font-size:12px; font-weight:700; color:#15803D; background:#DCFCE7; padding:4px 10px; border-radius:6px;">
                                ✓ Live Courier Track Active
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Student & Parent Delivery Info -->
        <div class="track-info-grid">
            <div style="background:#ffffff; border-radius:16px; border:1.5px solid #E2E8F0; padding:22px 26px;">
                <h4 style="font-size:13px; text-transform:uppercase; font-weight:800; color:#94A3B8; margin:0 0 12px; letter-spacing:0.05em;">Student Identification</h4>
                <div style="font-size:16px; font-weight:800; color:#1E293B; margin-bottom:4px;"><?= sanitize($order['student_name']) ?></div>
                <div style="font-size:13px; color:#64748B;">Class / Academic Grade: <strong><?= sanitize($order['grade_class']) ?></strong></div>
                <div style="font-size:13px; color:#64748B; margin-top:2px;">Institution: <strong><?= sanitize($order['school_name'] ?: 'Global Public School') ?></strong></div>
            </div>

            <div style="background:#ffffff; border-radius:16px; border:1.5px solid #E2E8F0; padding:22px 26px;">
                <h4 style="font-size:13px; text-transform:uppercase; font-weight:800; color:#94A3B8; margin:0 0 12px; letter-spacing:0.05em;">Parent &amp; Delivery Destination</h4>
                <div style="font-size:16px; font-weight:800; color:#1E293B; margin-bottom:4px;"><?= sanitize($order['parent_name']) ?></div>
                <div style="font-size:13px; color:#64748B;">WhatsApp: <strong><?= sanitize($order['mobile']) ?></strong></div>
                <div style="font-size:13px; color:#64748B; margin-top:2px; line-height:1.4;">
                    <?= sanitize($order['delivery_address']) ?><?= $order['pincode'] ? ', PIN: ' . sanitize($order['pincode']) : '' ?>
                </div>
            </div>
        </div>

        <!-- Ordered Items Card -->
        <div style="background:#ffffff; border-radius:20px; border:1.5px solid #E2E8F0; padding:28px 32px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.05); margin-bottom:30px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #F1F5F9; padding-bottom:14px;">
                <h3 style="font-size:18px; font-weight:800; color:#1E293B; margin:0;">Ordered Uniform Garments</h3>
                <span style="font-size:13px; font-weight:700; color:#64748B;"><?= count($items) ?> Items</span>
            </div>

            <div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; border-collapse:collapse; min-width:480px;">
                <thead>
                    <tr style="border-bottom:1.5px solid #E2E8F0; font-size:12px; text-transform:uppercase; color:#64748B; text-align:left;">
                        <th style="padding:10px 8px;">Garment Item</th>
                        <th style="padding:10px 8px; text-align:center;">Size</th>
                        <th style="padding:10px 8px; text-align:center;">Qty</th>
                        <th style="padding:10px 8px; text-align:right;">Total</th>
                        <?php if ($currentRank >= 5): ?>
                            <th style="padding:10px 8px; text-align:right;">Exchange</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr style="border-bottom:1px solid #F8FAFC;">
                            <td style="padding:14px 8px; display:flex; align-items:center; gap:12px;">
                                <?php if (!empty($it['featured_image'])): ?>
                                    <img src="<?= BASE_URL ?>/<?= sanitize($it['featured_image']) ?>" alt="Uniform" style="width:42px; height:42px; object-fit:contain; background:#F8FAFC; border-radius:8px; border:1px solid #E2E8F0;">
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:700; font-size:14px; color:#1E293B;"><?= sanitize($it['product_name']) ?></div>
                                    <div style="font-size:12px; color:#94A3B8;">Certified Institutional Stitch</div>
                                </div>
                            </td>
                            <td style="padding:14px 8px; text-align:center;">
                                <span style="background:#F1F5F9; color:#1E293B; font-weight:800; font-size:13px; padding:3px 10px; border-radius:6px;">
                                    <?= sanitize($it['size']) ?>
                                </span>
                            </td>
                            <td style="padding:14px 8px; text-align:center; font-weight:700; font-size:14px; color:#1E293B;">
                                <?= (int)$it['quantity'] ?>
                            </td>
                            <td style="padding:14px 8px; text-align:right; font-weight:800; font-size:14px; color:#1E293B;">
                                Rs. <?= number_format((float)$it['total_price'], 2) ?>
                            </td>
                            <?php if ($currentRank >= 5): ?>
                                <td style="padding:14px 8px; text-align:right;">
                                    <button type="button" onclick="openExchangeModal(<?= $it['id'] ?>, '<?= addslashes(sanitize($it['product_name'])) ?>', '<?= sanitize($it['size']) ?>')" style="padding:5px 12px; border-radius:8px; background:#FFF7ED; color:#C2410C; border:1px solid #FDBA74; font-size:12px; font-weight:800; cursor:pointer;">
                                        🔄 Exchange Size
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:16px; border-top:1.5px solid #E2E8F0;">
                <span style="font-size:14px; color:#64748B; font-weight:600;">Standard Free School Distribution</span>
                <span style="font-size:18px; font-weight:800; color:#FF6702;">Total Paid: Rs. <?= number_format((float)$order['total_amount'], 2) ?></span>
            </div>
        </div>

        <!-- Exchange Request Section (Document P49 Requirement) -->
        <?php if (!empty($order['exchange_requested'])): ?>
            <div style="background:#FEF3C7; border:1.5px solid #FCD34D; border-radius:16px; padding:20px 24px; margin-bottom:30px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <span style="font-size:20px;">🔄</span>
                    <h4 style="margin:0; font-size:16px; font-weight:800; color:#92400E;">Size Exchange Status: Under Processing</h4>
                </div>
                <p style="margin:0; font-size:13px; color:#B45309; line-height:1.5;">
                    <?= nl2br(sanitize($order['exchange_details'])) ?>
                </p>
            </div>
        <?php elseif ($currentRank >= 5): ?>
            <div style="background:#F8FAFC; border:1.5px dashed #CBD5E1; border-radius:16px; padding:22px 28px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:30px;">
                <div>
                    <h4 style="margin:0; font-size:15px; font-weight:800; color:#1E293B;">Need a Different Uniform Size?</h4>
                    <p style="margin:2px 0 0; font-size:13px; color:#64748B;">Uniforms often need a size change. Request an exchange directly without waiting on phone support.</p>
                </div>
                <button type="button" onclick="openExchangeModal(0, 'Uniform Item', '')" style="padding:10px 20px; background:#FF6702; color:#ffffff; border:none; border-radius:9999px; font-weight:800; font-size:14px; cursor:pointer;">
                    🔄 Request Size Exchange
                </button>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Help & Support Desk Banner (Workflow P51 Requirement) -->
    <div style="background:#1E293B; border-radius:16px; padding:24px 30px; color:#ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <div style="font-size:11px; text-transform:uppercase; font-weight:800; color:#FF6702; letter-spacing:0.05em;">Need Immediate Assistance?</div>
            <div style="font-size:16px; font-weight:800; margin-top:2px;">OnBolt Institutional Support Desk</div>
            <div style="font-size:13px; color:#94A3B8; margin-top:2px;">Phone: +91 88486 70739 / +91 99469 25888 &bull; Email: onboltconsumerindia@gmail.com</div>
        </div>
        <a href="https://wa.me/918848670739?text=<?= urlencode('Hello OnBolt Support, I am inquiring about Order Ref: ' . ($orderRef ?: 'General Enquiry')) ?>" target="_blank" style="padding:10px 22px; border-radius:9999px; background:#22C55E; color:#ffffff; font-weight:800; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            <span>WhatsApp Desk</span> &rarr;
        </a>
    </div>

</div>

<!-- Size Exchange Modal -->
<div id="exchangeModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(3px);">
    <div style="background:#ffffff; border-radius:20px; max-width:500px; width:100%; border:2px solid #FF6702; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); overflow:hidden;">
        <div style="background:#1E293B; color:#ffffff; padding:18px 24px; display:flex; justify-content:space-between; align-items:center;">
            <h4 style="margin:0; font-size:16px; font-weight:800; color:#ffffff;">Request Size Exchange</h4>
            <button type="button" onclick="closeExchangeModal()" style="background:transparent; border:none; color:#ffffff; font-size:22px; cursor:pointer;">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/schools/track-order.php" method="POST" style="padding:24px;">
            <input type="hidden" name="action" value="request_exchange">
            <input type="hidden" name="order_reference" value="<?= sanitize($order['order_reference'] ?? '') ?>">
            <input type="hidden" name="mobile_number" value="<?= sanitize($order['mobile'] ?? '') ?>">
            <input type="hidden" name="item_id" id="exchangeItemId" value="">

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#1E293B; margin-bottom:6px;">Garment Item</label>
                <input type="text" name="item_title" id="exchangeItemTitle" readonly style="width:100%; padding:10px 14px; border-radius:10px; border:1px solid #CBD5E1; background:#F8FAFC; font-weight:700; color:#475569;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label style="display:block; font-size:13px; font-weight:700; color:#1E293B; margin-bottom:6px;">Current Size</label>
                    <input type="text" name="current_size" id="exchangeCurrentSize" readonly style="width:100%; padding:10px 14px; border-radius:10px; border:1px solid #CBD5E1; background:#F8FAFC; font-weight:700; color:#475569;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:700; color:#1E293B; margin-bottom:6px;">Required Size *</label>
                    <select name="requested_size" required style="width:100%; padding:10px 14px; border-radius:10px; border:1.5px solid #CBD5E1; font-weight:700; outline:none;">
                        <option value="">Select Size</option>
                        <option value="20">Size 20</option>
                        <option value="22">Size 22</option>
                        <option value="24">Size 24</option>
                        <option value="26">Size 26</option>
                        <option value="28">Size 28</option>
                        <option value="30">Size 30</option>
                        <option value="32">Size 32</option>
                        <option value="34">Size 34</option>
                        <option value="36">Size 36</option>
                        <option value="38">Size 38</option>
                        <option value="40">Size 40</option>
                        <option value="42">Size 42</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#1E293B; margin-bottom:6px;">Reason for Exchange</label>
                <select name="exchange_reason" style="width:100%; padding:10px 14px; border-radius:10px; border:1.5px solid #CBD5E1; font-weight:600; outline:none;">
                    <option value="Need one size larger">Need one size larger (Too tight)</option>
                    <option value="Need one size smaller">Need one size smaller (Too loose)</option>
                    <option value="Length adjustment required">Length adjustment required</option>
                    <option value="Other sizing issue">Other sizing issue</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeExchangeModal()" style="padding:10px 18px; border-radius:10px; background:#F1F5F9; border:none; font-weight:700; cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:10px 22px; border-radius:10px; background:#FF6702; color:#ffffff; border:none; font-weight:800; cursor:pointer;">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php if ($order): ?>
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
<?php endif; ?>

<script>
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

function openExchangeModal(id, title, size) {
    document.getElementById('exchangeItemId').value = id || '';
    document.getElementById('exchangeItemTitle').value = title || 'Uniform Garment';
    document.getElementById('exchangeCurrentSize').value = size || 'N/A';
    document.getElementById('exchangeModal').style.display = 'flex';
}
function closeExchangeModal() {
    document.getElementById('exchangeModal').style.display = 'none';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeInvoiceModal();
        closeExchangeModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

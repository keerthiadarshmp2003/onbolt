<?php
/**
 * OnBolt Digital Experience Platform - Admin Walk-in / Counter Direct Ordering Module
 * URL: /admin/create_order.php
 * 
 * Specifically designed for parents/customers who come in person to order uniforms
 * or pay at the counter (Cash, Direct UPI/QR, Card/POS).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/edviron.php';

$admin = requireAdminAuth();
if (!adminCan('create_order_manage')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to access Walk-in Direct Ordering Desk.');
}
$adminName = sanitize($admin['full_name']);
$adminRole = sanitize($admin['role_name']);
$adminTitle = 'Direct Walk-in Order Desk';
$adminActive = 'create_order';

$pdo = getDbConnection();

// ==================== AJAX GATEWAY STATUS CHECKER ====================
if (isset($_GET['ajax_check_gateway_status'])) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $collectId = trim($_GET['collect_id'] ?? '');
    $orderId = (int)($_GET['order_id'] ?? 0);

    if (empty($collectId)) {
        echo json_encode(['success' => false, 'is_paid' => false, 'message' => 'Missing collect id']);
        exit;
    }

    $statusRes = edviron_get_status_by_collect_id($collectId);
    $data = $statusRes['data'] ?? [];
    $rawStatus = strtoupper(trim((string)($data['status'] ?? 'PENDING')));

    $isSuccess = ($rawStatus === 'SUCCESS' || (isset($data['status_code']) && (int)$data['status_code'] === 200 && $rawStatus !== 'FAILED' && $rawStatus !== 'NOT INITIATED'));

    if ($isSuccess && $orderId > 0) {
        $pdo->prepare("UPDATE orders SET payment_status = 'Paid', order_status = 'Confirmed' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare("UPDATE payments SET status = 'Completed' WHERE order_id = ?")->execute([$orderId]);
    }

    echo json_encode([
        'success'    => true,
        'is_paid'    => $isSuccess,
        'status'     => $rawStatus,
        'collect_id' => $collectId,
        'order_id'   => $orderId
    ]);
    exit;
}

// ==================== AJAX MANUAL GATEWAY VERIFICATION ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_verify_gateway_paid') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($orderId > 0) {
        $pdo->prepare("UPDATE orders SET payment_status = 'Paid', order_status = 'Confirmed' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare("UPDATE payments SET status = 'Completed' WHERE order_id = ?")->execute([$orderId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    }
    exit;
}

// ==================== AJAX PRODUCT FETCHER ====================
if (isset($_GET['ajax_get_school_products'])) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $targetSchoolId = (int)$_GET['ajax_get_school_products'];
    $showAll = !empty($_GET['show_all']) && $_GET['show_all'] === '1';

    $schoolStmt = $pdo->prepare("SELECT id, name, code, parent_id, has_sub_schools, sub_school_label, logo, city FROM schools WHERE id = ?");
    $schoolStmt->execute([$targetSchoolId]);
    $currentSchool = $schoolStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentSchool && $targetSchoolId > 0) {
        echo json_encode(['success' => false, 'message' => 'School not found']);
        exit;
    }

    $products = [];
    if ($targetSchoolId > 0 && !$showAll) {
        // Query products assigned to this school
        $pStmt = $pdo->prepare("SELECT p.*, 
                COALESCE(sp.custom_price, p.price) as effective_price,
                sp.is_mandatory,
                c.name as category_name
                FROM products p
                JOIN school_products sp ON p.id = sp.product_id
                LEFT JOIN product_categories c ON p.category_id = c.id
                WHERE sp.school_id = ? AND sp.status = 1 AND p.status = 1
                ORDER BY sp.is_mandatory DESC, p.sort_order ASC, p.name ASC");
        $pStmt->execute([$targetSchoolId]);
        $products = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        // If this school has 0 products and has a parent, try the parent school products
        if (empty($products) && !empty($currentSchool['parent_id'])) {
            $pStmt->execute([(int)$currentSchool['parent_id']]);
            $products = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // If still empty or showAll requested, load all active products
    if (empty($products) || $showAll) {
        $pStmt = $pdo->query("SELECT p.*, 
                p.price as effective_price,
                0 as is_mandatory,
                c.name as category_name
                FROM products p
                LEFT JOIN product_categories c ON p.category_id = c.id
                WHERE p.status = 1
                ORDER BY p.sort_order ASC, p.name ASC");
        $products = $pStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Attach variants for each product
    foreach ($products as &$prod) {
        $vStmt = $pdo->prepare("SELECT id, product_id, size, color, sku, price_override, stock_quantity 
                                FROM product_variants 
                                WHERE product_id = ? AND status = 1 
                                ORDER BY id ASC");
        $vStmt->execute([$prod['id']]);
        $prodVariants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

        // If no rows in product_variants, generate variants from available_sizes
        if (empty($prodVariants) && !empty($prod['available_sizes'])) {
            $sizes = array_map('trim', explode(',', (string)$prod['available_sizes']));
            foreach ($sizes as $idx => $s) {
                if ($s !== '') {
                    $prodVariants[] = [
                        'id' => null,
                        'product_id' => $prod['id'],
                        'size' => $s,
                        'color' => null,
                        'sku' => $prod['sku'] ? $prod['sku'] . '-' . $s : null,
                        'price_override' => null,
                        'stock_quantity' => 999
                    ];
                }
            }
        }
        $prod['variants'] = $prodVariants;
        $prod['formatted_price'] = formatCurrency((float)$prod['effective_price']);
    }

    echo json_encode([
        'success' => true,
        'school' => $currentSchool,
        'products_count' => count($products),
        'products' => $products
    ]);
    exit;
}

// ==================== PRINTABLE RECEIPT / INVOICE VIEW ====================
if (isset($_GET['print_receipt'])) {
    if (ob_get_level()) ob_end_clean();
    $orderId = (int)$_GET['print_receipt'];

    $oStmt = $pdo->prepare("SELECT o.*, s.name as school_name, s.code as school_code, s.city as school_city, s.logo as school_logo,
                            sub.name as sub_school_name
                            FROM orders o
                            LEFT JOIN schools s ON o.school_id = s.id
                            LEFT JOIN schools sub ON o.sub_school_id = sub.id
                            WHERE o.id = ?");
    $oStmt->execute([$orderId]);
    $order = $oStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die('Order not found.');
    }

    $iStmt = $pdo->prepare("SELECT oi.*, p.sku FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $iStmt->execute([$orderId]);
    $orderItems = $iStmt->fetchAll(PDO::FETCH_ASSOC);

    $siteLogo = BASE_URL . '/assets/images/logo/cropped-favicon-1-96x96.png';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt #<?= htmlspecialchars($order['order_reference']) ?> | OnBolt Direct Orders</title>
        <style>
            * { box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
                background: #f1f5f9;
                color: #0f172a;
                margin: 0;
                padding: 20px;
                font-size: 13px;
                line-height: 1.4;
            }
            .receipt-container {
                max-width: 680px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.06);
                border: 1px solid #e2e8f0;
            }
            .no-print-bar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                max-width: 680px;
                margin: 0 auto 15px auto;
                gap: 10px;
            }
            .btn-action {
                padding: 8px 16px;
                border-radius: 6px;
                font-weight: 700;
                font-size: 13px;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                border: none;
            }
            .btn-print { background: #FF6702; color: #ffffff; }
            .btn-close { background: #64748b; color: #ffffff; }
            .receipt-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                border-bottom: 2px solid #0f172a;
                padding-bottom: 16px;
                margin-bottom: 20px;
            }
            .brand-name {
                font-size: 20px;
                font-weight: 800;
                letter-spacing: -0.02em;
                color: #0f172a;
            }
            .brand-tag {
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #FF6702;
                font-weight: 800;
            }
            .receipt-meta {
                text-align: right;
            }
            .order-title {
                font-size: 16px;
                font-weight: 800;
                color: #FF6702;
                margin: 0 0 4px 0;
            }
            .meta-line {
                font-size: 12px;
                color: #475569;
                margin: 2px 0;
            }
            .details-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 14px;
                margin-bottom: 20px;
            }
            .detail-block h4 {
                margin: 0 0 6px 0;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: #64748b;
            }
            .detail-row {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                margin-bottom: 3px;
            }
            .detail-label { color: #64748b; }
            .detail-val { font-weight: 700; color: #0f172a; text-align: right; }
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .items-table th {
                background: #0f172a;
                color: #ffffff;
                text-align: left;
                padding: 8px 10px;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .items-table td {
                padding: 10px;
                border-bottom: 1px solid #e2e8f0;
                font-size: 12px;
            }
            .items-table tr:nth-child(even) td {
                background: #f8fafc;
            }
            .total-section {
                margin-left: auto;
                width: 260px;
                margin-bottom: 20px;
            }
            .total-row {
                display: flex;
                justify-content: space-between;
                padding: 4px 0;
                font-size: 12px;
            }
            .grand-total-row {
                border-top: 2px solid #0f172a;
                margin-top: 6px;
                padding-top: 8px;
                font-size: 16px;
                font-weight: 800;
                color: #FF6702;
            }
            .payment-badge-stamp {
                display: inline-block;
                padding: 6px 14px;
                background: #dcfce7;
                color: #15803d;
                border: 2px solid #86efac;
                border-radius: 6px;
                font-weight: 800;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .receipt-footer {
                border-top: 1px dashed #cbd5e1;
                padding-top: 14px;
                margin-top: 20px;
                text-align: center;
                font-size: 11px;
                color: #64748b;
            }
            @media print {
                body { background: #ffffff; padding: 0; }
                .no-print-bar { display: none !important; }
                .receipt-container { box-shadow: none; border: none; padding: 10px; max-width: 100%; }
            }
        </style>
    </head>
    <body>
        <div class="no-print-bar">
            <div>
                <a href="<?= BASE_URL ?>/admin/create_order.php" class="btn-action btn-close">&larr; Back to Order Desk</a>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" onclick="window.print()" class="btn-action btn-print">🖨️ Print Receipt</button>
                <button type="button" onclick="window.close()" class="btn-action btn-close">Close</button>
            </div>
        </div>

        <div class="receipt-container">
            <div class="receipt-header">
                <div>
                    <div class="brand-name">ONBOLT</div>
                    <div class="brand-tag">Complete Branding Solutions</div>
                    <div style="font-size:11px; color:#475569; margin-top:4px;">
                        Official School Uniforms &amp; Academic Merchandise<br>
                        Helpdesk: +91 94470 51100 | info@onbolt.in
                    </div>
                </div>
                <div class="receipt-meta">
                    <div class="order-title">COUNTER TAX INVOICE</div>
                    <div class="meta-line"><strong>Order ID:</strong> <?= htmlspecialchars($order['order_reference']) ?></div>
                    <div class="meta-line"><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
                    <div style="margin-top:8px;">
                        <span class="payment-badge-stamp">✓ <?= strtoupper(htmlspecialchars($order['payment_status'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="details-grid">
                <div class="detail-block">
                    <h4>School &amp; Student Details</h4>
                    <div class="detail-row">
                        <span class="detail-label">School:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['school_name'] ?: 'Partner School') ?></span>
                    </div>
                    <?php if (!empty($order['sub_school_name'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Campus/Unit:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['sub_school_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label">Student:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['student_name']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Grade / Class:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['grade_class']) ?></span>
                    </div>
                    <?php if (!empty($order['gender'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Gender:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['gender']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="detail-block">
                    <h4>Parent &amp; Counter Fulfillment</h4>
                    <div class="detail-row">
                        <span class="detail-label">Parent:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['parent_name'] ?: 'Walk-in Customer') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Mobile:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['mobile']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Fulfillment:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['fulfillment_type'] ?: 'Counter Handover') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Mode:</span>
                        <span class="detail-val"><?= htmlspecialchars(formatDisplayPaymentMethod($order['payment_method'])) ?></span>
                    </div>
                    <?php if (!empty($order['gateway_transaction_id'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Transaction ID:</span>
                        <span class="detail-val" style="font-family:monospace;"><?= htmlspecialchars($order['gateway_transaction_id']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['handover_staff'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Issued By:</span>
                        <span class="detail-val"><?= htmlspecialchars($order['handover_staff']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Itemized Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Item Description</th>
                        <th style="width:70px; text-align:center;">Size</th>
                        <th style="width:50px; text-align:center;">Qty</th>
                        <th style="width:90px; text-align:right;">Unit Price</th>
                        <th style="width:100px; text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $idx => $it): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($it['product_name']) ?></strong>
                            <?php if (!empty($it['sku'])): ?>
                                <div style="font-size:10px; color:#64748b;">SKU: <?= htmlspecialchars($it['sku']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; font-weight:700;"><?= htmlspecialchars($it['size']) ?></td>
                        <td style="text-align:center; font-weight:700;"><?= (int)$it['quantity'] ?></td>
                        <td style="text-align:right;"><?= formatCurrency((float)$it['unit_price']) ?></td>
                        <td style="text-align:right; font-weight:700;"><?= formatCurrency((float)$it['total_price']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span><?= formatCurrency((float)$order['subtotal']) ?></span>
                </div>
                <?php if ((float)$order['shipping_fee'] > 0): ?>
                <div class="total-row">
                    <span>Shipping Fee:</span>
                    <span><?= formatCurrency((float)$order['shipping_fee']) ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row grand-total-row">
                    <span>Amount Paid:</span>
                    <span><?= formatCurrency((float)$order['total_amount']) ?></span>
                </div>
            </div>

            <div class="receipt-footer">
                <div>Thank you for choosing OnBolt Uniforms &amp; Merchandise!</div>
                <div style="font-size:10px; margin-top:4px;">
                    Exchange Policy: Size exchanges are permitted within 7 days of purchase in original unused condition with tags attached.<br>
                    Official Portal: <strong><?= BASE_URL ?></strong>
                </div>
            </div>
        </div>

        <script>
            window.addEventListener('load', () => {
                setTimeout(() => { window.print(); }, 400);
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ==================== PROCESS ORDER CREATION (POST) ====================
$successData = null;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_direct_order') {
    $isAjaxPost = (!empty($_POST['is_ajax'])) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    try {
        $schoolId = (int)($_POST['school_id'] ?? 0);
        $subSchoolId = !empty($_POST['sub_school_id']) ? (int)$_POST['sub_school_id'] : null;
        $studentName = trim($_POST['student_name'] ?? '');
        $gradeClass = trim($_POST['grade_class'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        if (empty($gender) || !in_array($gender, ['Boy', 'Girl', 'Unisex'], true)) {
            throw new Exception('Please select Gender.');
        }
        $parentName = trim($_POST['parent_name'] ?? '');
        $mobile = preg_replace('/[^0-9]/', '', trim($_POST['mobile'] ?? ''));
        $altMobile = trim($_POST['alt_mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fulfillmentType = trim($_POST['fulfillment_type'] ?? '');
        if (empty($fulfillmentType) || !in_array($fulfillmentType, ['Counter', 'Handover', 'Courier'], true)) {
            throw new Exception('Please select Fulfillment Handover Mode.');
        }
        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '695001');
        $state = trim($_POST['state'] ?? 'Kerala');

        $paymentMethod = trim($_POST['payment_method'] ?? '');
        if (empty($paymentMethod) || !in_array($paymentMethod, ['Cash (Counter)', 'Direct UPI / QR', 'Card / POS', 'Bank Transfer / NEFT'], true)) {
            throw new Exception('Please select a Payment Method.');
        }
        $paymentStatus = trim($_POST['payment_status'] ?? '');
        if (empty($paymentStatus) || !in_array($paymentStatus, ['Paid', 'Pending'], true)) {
            throw new Exception('Please select Payment Status.');
        }
        $orderStatus = trim($_POST['order_status'] ?? '');
        if (empty($orderStatus) || !in_array($orderStatus, ['Completed', 'Confirmed'], true)) {
            throw new Exception('Please select Order Status.');
        }
        $paymentRef = trim($_POST['payment_reference'] ?? '');
        $cashTendered = (float)($_POST['cash_tendered'] ?? 0.0);
        $discountAmount = max(0.0, (float)($_POST['discount_amount'] ?? 0.0));
        $adminNotes = trim($_POST['admin_notes'] ?? '');

        $itemsJson = $_POST['items_json'] ?? '';
        $items = json_decode($itemsJson, true);

        // Validation
        if ($schoolId <= 0) {
            throw new Exception('Please select a school.');
        }
        if (empty($studentName)) {
            throw new Exception('Student name is required.');
        }
        if (empty($gradeClass)) {
            throw new Exception('Grade / Class is required.');
        }
        if (empty($mobile) || strlen($mobile) < 10) {
            throw new Exception('A valid 10-digit mobile number is required.');
        }
        if (empty($items) || !is_array($items)) {
            throw new Exception('Please add at least one uniform item to the order.');
        }

        // Resolve School Code for Order Reference ID
        $targetSchoolIdForCode = $subSchoolId ?: $schoolId;
        $codeStmt = $pdo->prepare("SELECT code, parent_id, name FROM schools WHERE id = ?");
        $codeStmt->execute([$targetSchoolIdForCode]);
        $targetSchoolRow = $codeStmt->fetch();

        $schoolCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $targetSchoolRow['code'] ?? ''));
        if (empty($schoolCode) && !empty($targetSchoolRow['parent_id'])) {
            $pCodeStmt = $pdo->prepare("SELECT code FROM schools WHERE id = ?");
            $pCodeStmt->execute([(int)$targetSchoolRow['parent_id']]);
            $pCode = $pCodeStmt->fetchColumn() ?: '';
            $schoolCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $pCode));
        }
        if (empty($schoolCode)) {
            $schoolCode = 'OB';
        }

        // Generate unique order reference
        $orderRef = '';
        for ($i = 0; $i < 10; $i++) {
            $randNum = rand(100000, 999999);
            $candidateRef = $schoolCode . $randNum;
            $chk = $pdo->prepare("SELECT id FROM orders WHERE order_reference = ?");
            $chk->execute([$candidateRef]);
            if (!$chk->fetch()) {
                $orderRef = $candidateRef;
                break;
            }
        }
        if (empty($orderRef)) {
            $orderRef = $schoolCode . time();
        }

        // Compute Totals
        $totalItems = 0;
        $subtotal = 0.0;
        foreach ($items as $it) {
            $qty = max(1, (int)($it['quantity'] ?? 1));
            $uPrice = (float)($it['price'] ?? 0.0);
            $totalItems += $qty;
            $subtotal += ($uPrice * $qty);
        }

        $totalAmount = max(0.0, $subtotal - $discountAmount);

        // Check if Parent exists, if so link parent_id
        $parentId = null;
        $parentCheck = $pdo->prepare("SELECT id FROM parents WHERE phone = ? LIMIT 1");
        $parentCheck->execute([$mobile]);
        $parentRow = $parentCheck->fetch();
        if ($parentRow) {
            $parentId = (int)$parentRow['id'];
        }

        if (empty($parentName)) {
            $parentName = 'Parent of ' . $studentName;
        }

        if (empty($deliveryAddress)) {
            $deliveryAddress = ($fulfillmentType === 'Counter') 
                ? 'Direct Counter Handover - OnBolt Desk' 
                : 'Direct School Handover';
        }

        // Append administrative notes
        $compiledNotes = "Walk-in Order created by Admin {$adminName}. ";
        if (!empty($paymentRef)) {
            $compiledNotes .= "Ref/Txn: {$paymentRef}. ";
        }
        if ($cashTendered > 0 && $paymentMethod === 'Cash (Counter)') {
            $changeGiven = max(0.0, $cashTendered - $totalAmount);
            $compiledNotes .= "Cash Tendered: ₹" . number_format($cashTendered, 2) . ", Change: ₹" . number_format($changeGiven, 2) . ". ";
        }
        if (!empty($adminNotes)) {
            $compiledNotes .= $adminNotes;
        }

        $handoverAt = ($fulfillmentType === 'Counter' && $orderStatus === 'Completed') ? date('Y-m-d H:i:s') : null;
        $handoverStaff = $adminName;
        $handoverRecipient = $parentName . " (" . $studentName . ")";

        // Real Gateway Integration using Edviron details for UPI / QR Scan
        $isGatewayPayment = ($paymentMethod === 'Direct UPI / QR');
        $collectUrl = '';
        $collectId = '';

        if ($isGatewayPayment) {
            $callbackUrl = BASE_URL . '/schools/payment_callback.php?order=' . urlencode($orderRef);
            $collectRes = edviron_create_collect_request([
                'amount'           => $totalAmount,
                'order_reference'  => $orderRef,
                'order_id'         => $orderRef,
                'custom_order_id'  => $orderRef,
                'callback_url'     => $callbackUrl,
                'student_id'       => '-',
                'student_name'     => $studentName,
                'student_email'    => !empty($email) ? $email : 'parent@onbolt.in',
                'student_phone_no' => !empty($mobile) ? $mobile : '9876543210',
                'additional_data'  => [
                    'grade_class'  => $gradeClass,
                    'order_ref'    => $orderRef,
                    'parent_name'  => $parentName,
                    'school_id'    => $schoolCode
                ]
            ]);

            if ($collectRes['success'] && !empty($collectRes['collect_request_url'])) {
                $collectUrl = $collectRes['collect_request_url'];
                $collectId = $collectRes['collect_request_id'] ?? '';
                $finalWalkinTxn = !empty($collectId) ? $collectId : ('TXN-' . strtoupper(bin2hex(random_bytes(6))));
            } else {
                throw new Exception('Payment Gateway Error: ' . ($collectRes['error'] ?? 'Unable to connect to gateway'));
            }
        } else {
            $finalWalkinTxn = !empty($paymentRef) ? $paymentRef : ('WALKIN-' . strtoupper(bin2hex(random_bytes(4))));
        }

        // Begin Transaction
        $pdo->beginTransaction();

        $orderInsert = $pdo->prepare("INSERT INTO orders (
            order_reference, gateway_transaction_id, school_id, sub_school_id, parent_id, parent_name, student_name, gender, grade_class,
            mobile, alt_mobile, email, delivery_address, pincode, state, total_items,
            subtotal, shipping_fee, tax_amount, total_amount, payment_status, payment_method,
            order_status, admin_notes, fulfillment_type, handover_staff, handover_recipient, handover_at, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

        $orderInsert->execute([
            $orderRef,
            $finalWalkinTxn,
            $schoolId,
            $subSchoolId,
            $parentId,
            $parentName,
            $studentName,
            $gender,
            $gradeClass,
            $mobile,
            $altMobile,
            $email,
            $deliveryAddress,
            $pincode,
            $state,
            $totalItems,
            $subtotal,
            $totalAmount,
            $paymentStatus,
            $paymentMethod,
            $orderStatus,
            $compiledNotes,
            $fulfillmentType,
            $handoverStaff,
            $handoverRecipient,
            $handoverAt
        ]);

        $orderId = (int)$pdo->lastInsertId();

        // Automatically capture and store payment transaction record
        $payStatus = ($paymentStatus === 'Paid') ? 'Completed' : 'Pending';
        $payInsert = $pdo->prepare("INSERT INTO payments (
            order_id, transaction_ref, payment_method, amount, currency, status, created_at
        ) VALUES (?, ?, ?, ?, 'INR', ?, NOW())");
        $payInsert->execute([
            $orderId,
            $finalWalkinTxn,
            $paymentMethod,
            $totalAmount,
            $payStatus
        ]);

        // Insert Order Items
        $itemInsert = $pdo->prepare("INSERT INTO order_items (
            order_id, product_id, product_name, variant_id, size, color, quantity, unit_price, total_price
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stockUpdate = $pdo->prepare("UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");

        foreach ($items as $it) {
            $prodId = (int)($it['product_id'] ?? 0);
            $prodName = trim((string)($it['name'] ?? 'Uniform Item'));
            $size = trim((string)($it['size'] ?? 'Standard'));
            $color = !empty($it['color']) ? trim((string)$it['color']) : null;
            $qty = max(1, (int)($it['quantity'] ?? 1));
            $uPrice = (float)($it['price'] ?? 0.0);
            $lTotal = $uPrice * $qty;
            $variantId = !empty($it['variant_id']) ? (int)$it['variant_id'] : null;

            $itemInsert->execute([
                $orderId,
                $prodId,
                $prodName,
                $variantId,
                $size,
                $color,
                $qty,
                $uPrice,
                $lTotal
            ]);

            // Deduct variant stock if variant id exists
            if ($variantId) {
                $stockUpdate->execute([$qty, $variantId]);
            }
        }

        $pdo->commit();

        logActivity('create_walkin_order', 'orders', (int)$admin['id'], 'admin', "Direct walk-in order {$orderRef} created for student {$studentName} ({$targetSchoolRow['name']}), total: ₹{$totalAmount}");

        $successData = [
            'order_id' => $orderId,
            'order_reference' => $orderRef,
            'total_amount' => $totalAmount,
            'total_formatted' => formatCurrency($totalAmount),
            'student_name' => $studentName,
            'school_name' => $targetSchoolRow['name'] ?? 'School',
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'gateway_transaction_id' => $finalWalkinTxn,
            'is_gateway' => $isGatewayPayment,
            'collect_url' => $collectUrl,
            'collect_id' => $collectId,
            'receipt_url' => BASE_URL . "/admin/create_order.php?print_receipt={$orderId}"
        ];

        if ($isAjaxPost) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => $successData,
                'message' => "Order {$orderRef} created successfully!"
            ]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMsg = $e->getMessage();
        if ($isAjaxPost) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $errorMsg]);
            exit;
        }
    }
}

// ==================== FETCH SCHOOLS FOR INITIAL VIEW ====================
// Fetch all active schools
$allSchoolsStmt = $pdo->query("SELECT id, name, slug, code, location, city, parent_id, has_sub_schools, sub_school_label, logo 
                               FROM schools 
                               WHERE status = 1 
                               ORDER BY parent_id IS NOT NULL, sort_order ASC, name ASC");
$allSchools = $allSchoolsStmt->fetchAll(PDO::FETCH_ASSOC);

// Separate main schools and sub-schools
$mainSchools = [];
$subSchoolsMap = [];
foreach ($allSchools as $s) {
    if (empty($s['parent_id'])) {
        $mainSchools[] = $s;
    } else {
        $pid = (int)$s['parent_id'];
        if (!isset($subSchoolsMap[$pid])) {
            $subSchoolsMap[$pid] = [];
        }
        $subSchoolsMap[$pid][] = $s;
    }
}

// Fetch all active categories
$categories = $pdo->query("SELECT id, name FROM product_categories WHERE status = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/header.php';
?>

<style>
/* Modern Walk-in Order Desk Styles */
.desk-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 1.5rem;
    align-items: flex-start;
}
@media (max-width: 1200px) {
    .desk-layout {
        grid-template-columns: 1fr;
    }
}
.desk-card {
    background: #ffffff;
    border-radius: var(--radius-xl);
    border: 1px solid var(--brand-border);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
}
.desk-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 1rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid var(--brand-border);
}
.desk-card-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--brand-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}
.desk-field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
.desk-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.desk-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--brand-dark);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.desk-input, .desk-select, .desk-textarea {
    width: 100%;
    padding: 0.65rem 0.85rem;
    border-radius: var(--radius-md);
    border: 1.5px solid #CBD5E1;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
    background: #ffffff;
}
.desk-input:focus, .desk-select:focus, .desk-textarea:focus {
    border-color: var(--brand-primary);
    box-shadow: 0 0 0 3px rgba(255, 103, 2, 0.15);
}

/* Gender Pills */
.gender-pill-group {
    display: flex;
    gap: 0.5rem;
}
.gender-pill {
    flex: 1;
    text-align: center;
    padding: 0.55rem;
    border: 1.5px solid #CBD5E1;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    background: #ffffff;
    color: #475569;
    transition: all 0.2s;
    user-select: none;
}
.gender-pill input { display: none; }
.gender-pill.active {
    border-color: var(--brand-primary);
    background: #FFF7ED;
    color: var(--brand-primary);
}

/* Product Catalog Grid */
.catalog-filter-bar {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}
.product-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
    max-height: 580px;
    overflow-y: auto;
    padding-right: 0.4rem;
}
.prod-card {
    background: #ffffff;
    border: 1.5px solid #E2E8F0;
    border-radius: var(--radius-lg);
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
    position: relative;
}
.prod-card:hover {
    border-color: #CBD5E1;
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}
.prod-thumb-wrap {
    width: 100%;
    height: 140px;
    background: #F8FAFC;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 0.65rem;
    position: relative;
}
.prod-thumb-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.prod-cat-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    background: rgba(15, 23, 42, 0.85);
    color: #ffffff;
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
    text-transform: uppercase;
}
.prod-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--brand-dark);
    margin: 0 0 0.35rem 0;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.3em;
}
.prod-price-tag {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--brand-primary);
    margin-bottom: 0.6rem;
}
.prod-size-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 0.65rem;
}
.size-btn {
    padding: 3px 7px;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid #CBD5E1;
    background: #F8FAFC;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
}
.size-btn:hover, .size-btn.active {
    background: var(--brand-primary);
    border-color: var(--brand-primary);
    color: #ffffff;
}
.prod-add-btn {
    margin-top: auto;
    width: 100%;
    padding: 0.55rem;
    background: #F1F5F9;
    color: var(--brand-dark);
    border: 1px solid #CBD5E1;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    font-weight: 800;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
}
.prod-add-btn:hover {
    background: var(--brand-primary);
    border-color: var(--brand-primary);
    color: #ffffff;
}

/* Sticky Bill Cart Drawer */
.cart-sticky-panel {
    position: sticky;
    top: 1rem;
}
.cart-items-container {
    max-height: 260px;
    overflow-y: auto;
    border: 1px solid #E2E8F0;
    border-radius: var(--radius-md);
    background: #F8FAFC;
    margin-bottom: 1rem;
}
.cart-item-row {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 0.5rem;
    align-items: center;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #E2E8F0;
    background: #ffffff;
    font-size: 0.85rem;
}
.cart-item-row:last-child { border-bottom: none; }
.cart-item-title { font-weight: 700; color: var(--brand-dark); }
.cart-item-size-badge {
    display: inline-block;
    padding: 1px 5px;
    background: #E2E8F0;
    color: #334155;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: 700;
    margin-top: 2px;
}
.cart-qty-ctrl {
    display: inline-flex;
    align-items: center;
    border: 1px solid #CBD5E1;
    border-radius: 4px;
    overflow: hidden;
}
.cart-qty-btn {
    background: #F8FAFC;
    border: none;
    padding: 2px 7px;
    font-weight: 800;
    cursor: pointer;
    font-size: 0.85rem;
}
.cart-qty-btn:hover { background: #E2E8F0; }
.cart-qty-num {
    padding: 2px 6px;
    font-weight: 700;
    font-size: 0.82rem;
    min-width: 22px;
    text-align: center;
}
.cart-remove-btn {
    background: none;
    border: none;
    color: #EF4444;
    cursor: pointer;
    padding: 4px;
    font-size: 1rem;
}
.cart-remove-btn:hover { color: #DC2626; }

/* Payment Methods Grid */
.payment-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
    margin-bottom: 1rem;
}
.payment-option-card {
    border: 1.5px solid #CBD5E1;
    border-radius: var(--radius-md);
    padding: 0.75rem 0.6rem;
    text-align: center;
    cursor: pointer;
    background: #ffffff;
    transition: all 0.2s;
    user-select: none;
}
.payment-option-card input { display: none; }
.payment-option-card.active {
    border-color: #10B981;
    background: #ECFDF5;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}
.pay-icon { font-size: 1.3rem; margin-bottom: 2px; }
.pay-label { font-size: 0.8rem; font-weight: 800; color: var(--brand-dark); }

/* Bill Summary Box */
.bill-summary-box {
    background: #FFF7ED;
    border: 1.5px solid #FFEDD5;
    border-radius: var(--radius-lg);
    padding: 1rem;
    margin-bottom: 1.25rem;
}
.bill-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.88rem;
    margin-bottom: 0.4rem;
}
.bill-grand-row {
    border-top: 1.5px solid #FED7AA;
    padding-top: 0.6rem;
    margin-top: 0.6rem;
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--brand-primary);
}

/* Post Order Success Modal */
.desk-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.desk-modal-card {
    background: #ffffff;
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 520px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    animation: deskPop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes deskPop {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
@keyframes gwPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.35; transform: scale(1.3); }
}
.gw-pulse {
    animation: gwPulse 1.5s infinite ease-in-out;
}
</style>

<!-- Top Notification Bar -->
<?php if (!empty($errorMsg)): ?>
<div style="background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:1rem 1.25rem; border-radius:var(--radius-md); margin-bottom:1.5rem; font-weight:700;">
    ⚠️ <?= htmlspecialchars($errorMsg) ?>
</div>
<?php endif; ?>

<!-- Page Header Controls -->
<div style="margin-bottom:1.5rem;">
    <p style="font-size:0.85rem; color:var(--brand-muted); margin:0;">
        Direct in-person counter ordering desk for walk-in parents &amp; offline payment collection.
    </p>
</div>

<form id="directOrderForm" method="POST" action="" onsubmit="return handleOrderSubmit(event)">
    <input type="hidden" name="action" value="create_direct_order">
    <input type="hidden" name="items_json" id="itemsJsonInput" value="[]">

    <div class="desk-layout">
        <!-- LEFT COLUMN: SCHOOL, STUDENT & UNIFORM SELECTION -->
        <div>
            <!-- CARD 1: SCHOOL SELECTION & STUDENT DETAILS -->
            <div class="desk-card">
                <div class="desk-card-header">
                    <h3 class="desk-card-title">
                        <span>🏫</span> Step 1: School &amp; Student Information
                    </h3>
                    <span id="schoolBadge" style="display:none; font-size:0.75rem; font-weight:800; padding:3px 8px; border-radius:9999px; background:#FFEDD5; color:#C2410C;">
                        <!-- dynamic school code -->
                    </span>
                </div>

                <div class="desk-field-grid" style="grid-template-columns: 1fr 1fr; margin-bottom:1.25rem;">
                    <!-- School Selection Dropdown -->
                    <div class="desk-form-group">
                        <label class="desk-label">Select Partner School *</label>
                        <select name="school_id" id="schoolSelect" class="desk-select" required onchange="handleSchoolChange(this.value)">
                            <option value="">-- Choose Partner School --</option>
                            <?php foreach ($mainSchools as $ms): ?>
                                <option value="<?= $ms['id'] ?>" data-code="<?= htmlspecialchars($ms['code']) ?>" data-hassub="<?= $ms['has_sub_schools'] ?>">
                                    <?= htmlspecialchars($ms['name']) ?> (<?= htmlspecialchars($ms['code'] ?: 'ID: ' . $ms['id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sub-School / Campus Selector (Shows if school has branches) -->
                    <div class="desk-form-group" id="subSchoolGroup" style="display:none;">
                        <label class="desk-label" id="subSchoolLabel">Campus / Academic Unit</label>
                        <select name="sub_school_id" id="subSchoolSelect" class="desk-select" onchange="handleSubSchoolChange(this.value)">
                            <option value="">-- Main Campus / All Units --</option>
                        </select>
                    </div>
                </div>

                <div class="desk-field-grid" style="grid-template-columns: 1fr 1fr; margin-bottom:1rem;">
                    <!-- Student Full Name -->
                    <div class="desk-form-group">
                        <label class="desk-label">Student Full Name *</label>
                        <input type="text" name="student_name" id="studentName" class="desk-input" placeholder="e.g. Rahul Sharma" required>
                    </div>

                    <!-- Grade / Class -->
                    <div class="desk-form-group">
                        <label class="desk-label">Grade / Class *</label>
                        <input type="text" name="grade_class" id="gradeClass" class="desk-input" placeholder="e.g. Grade 4-B / Class 1" required>
                    </div>
                </div>

                <div class="desk-field-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom:1rem;">
                    <!-- Student Gender -->
                    <div class="desk-form-group">
                        <label class="desk-label">Gender *</label>
                        <div class="gender-pill-group">
                            <label class="gender-pill" onclick="setGender('Boy', this)">
                                <input type="radio" name="gender" value="Boy"> 👦 Boy
                            </label>
                            <label class="gender-pill" onclick="setGender('Girl', this)">
                                <input type="radio" name="gender" value="Girl"> 👧 Girl
                            </label>
                            <label class="gender-pill" onclick="setGender('Unisex', this)">
                                <input type="radio" name="gender" value="Unisex"> ⚧ Unisex
                            </label>
                        </div>
                    </div>

                    <!-- Parent / Guardian Name -->
                    <div class="desk-form-group">
                        <label class="desk-label">Parent / Guardian Name</label>
                        <input type="text" name="parent_name" id="parentName" class="desk-input" placeholder="e.g. Rajesh Sharma">
                    </div>

                    <!-- Parent Mobile Number -->
                    <div class="desk-form-group">
                        <label class="desk-label">Parent Mobile Number *</label>
                        <input type="tel" name="mobile" id="parentMobile" class="desk-input" placeholder="10-digit mobile number" maxlength="10" required>
                    </div>
                </div>

                <!-- Fulfillment Preference -->
                <div class="desk-field-grid" style="grid-template-columns: 1fr 1fr; margin-bottom:0.5rem;">
                    <div class="desk-form-group">
                        <label class="desk-label">Fulfillment Handover Mode *</label>
                        <select name="fulfillment_type" id="fulfillmentType" class="desk-select" required onchange="handleFulfillmentChange(this.value)">
                            <option value="" selected disabled>Select</option>
                            <option value="Counter">🏢 Immediate Counter Handover (Handed over now)</option>
                            <option value="Handover">🏫 School Campus Handover (Deliver to student at school)</option>
                            <option value="Courier">📦 Courier / Home Delivery (Ship to address)</option>
                        </select>
                    </div>

                    <div class="desk-form-group" id="addressFieldWrap" style="display:none;">
                        <label class="desk-label">Delivery Address &amp; Pincode</label>
                        <input type="text" name="delivery_address" id="deliveryAddress" class="desk-input" placeholder="Street Address, House No, Pincode">
                    </div>
                </div>
            </div>

            <!-- CARD 2: UNIFORM / PRODUCT SELECTION -->
            <div class="desk-card">
                <div class="desk-card-header">
                    <h3 class="desk-card-title">
                        <span>👕</span> Step 2: Choose Uniforms &amp; Apparel
                    </h3>
                </div>

                <!-- Catalog Filter Bar -->
                <div class="catalog-filter-bar">
                    <input type="text" id="catalogSearchInput" placeholder="🔍 Search uniform by name or SKU..." class="desk-input" style="max-width:320px;" oninput="filterCatalogItems()">
                    <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                        <button type="button" class="btn btn-outline btn-sm active-cat-pill" data-cat="" onclick="filterByCategory('', this)" style="border-radius:9999px; padding:0.25rem 0.75rem; font-size:0.78rem;">
                            All
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" data-cat="academic" onclick="filterByCategory('academic', this)" style="border-radius:9999px; padding:0.25rem 0.75rem; font-size:0.78rem;">
                            Academic Wear
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" data-cat="sports" onclick="filterByCategory('sports', this)" style="border-radius:9999px; padding:0.25rem 0.75rem; font-size:0.78rem;">
                            Sports Wear
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" data-cat="accessories" onclick="filterByCategory('accessories', this)" style="border-radius:9999px; padding:0.25rem 0.75rem; font-size:0.78rem;">
                            Accessories
                        </button>
                    </div>
                </div>

                <!-- Catalog Loading State -->
                <div id="catalogLoading" style="display:none; text-align:center; padding:3rem; color:var(--brand-muted);">
                    <div style="font-size:1.8rem; margin-bottom:0.5rem; animation:spin 1s infinite linear;">⏳</div>
                    <div>Loading school uniform catalog...</div>
                </div>

                <!-- Catalog Empty State (When school not selected) -->
                <div id="catalogEmptyPrompt" style="text-align:center; padding:3rem 1.5rem; border:2px dashed #CBD5E1; border-radius:var(--radius-lg); background:#F8FAFC;">
                    <div style="font-size:2.5rem; margin-bottom:0.5rem;">🏫</div>
                    <h4 style="margin:0 0 0.35rem 0; font-size:1.1rem; color:var(--brand-dark);">Select a School to View Uniforms</h4>
                    <p style="margin:0; font-size:0.85rem; color:var(--brand-muted);">
                        Choose a partner school from the dropdown above to load approved school kits and available sizes.
                    </p>
                </div>

                <!-- Dynamic Uniform Items Grid -->
                <div id="productGrid" class="product-items-grid" style="display:none;">
                    <!-- Injected by JavaScript -->
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: STICKY BILL CART, PAYMENT METHOD & CHECKOUT -->
        <div class="cart-sticky-panel">
            <div class="desk-card" style="box-shadow:var(--shadow-md); border-color:#FED7AA;">
                <div class="desk-card-header" style="border-bottom-color:#FFEDD5;">
                    <h3 class="desk-card-title">
                        <span>🧾</span> Order Bill Summary
                    </h3>
                    <span id="cartCountBadge" style="background:var(--brand-primary); color:#ffffff; font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:9999px;">
                        0 items
                    </span>
                </div>

                <!-- Cart Line Items -->
                <div id="cartItemsContainer" class="cart-items-container">
                    <div id="cartEmptyState" style="text-align:center; padding:2.5rem 1rem; color:var(--brand-muted); font-size:0.85rem;">
                        <div style="font-size:2rem; margin-bottom:0.4rem;">🛒</div>
                        <div>No uniform items added yet.</div>
                        <div style="font-size:0.75rem; margin-top:2px;">Select sizes and click "+ Add to Order".</div>
                    </div>
                    <div id="cartItemsList"></div>
                </div>

                <!-- Bill Calculations -->
                <div class="bill-summary-box">
                    <div class="bill-row">
                        <span style="color:#64748b;">Subtotal:</span>
                        <strong id="billSubtotal">₹ 0.00</strong>
                    </div>
                    <div class="bill-row" style="align-items:center;">
                        <span style="color:#64748b;">Counter Discount (₹):</span>
                        <input type="number" name="discount_amount" id="discountInput" class="desk-input" style="width:90px; padding:2px 6px; font-size:0.85rem; text-align:right;" value="0" min="0" step="any" oninput="calculateBillTotal()">
                    </div>
                    <div class="bill-row">
                        <span style="color:#64748b;">Fulfillment Fee:</span>
                        <span style="color:#10B981; font-weight:700;">FREE (Counter)</span>
                    </div>
                    <div class="bill-row bill-grand-row">
                        <span>Grand Total:</span>
                        <span id="billGrandTotal">₹ 0.00</span>
                    </div>
                </div>

                <!-- STEP 3: PAYMENT METHOD -->
                <div style="margin-bottom:1.25rem;">
                    <label class="desk-label" style="margin-bottom:0.5rem; display:block;">
                        💳 Step 3: Payment Method *
                    </label>
                    <div class="payment-grid">
                        <label class="payment-option-card" onclick="setPaymentMethod('Cash (Counter)', this)">
                            <input type="radio" name="payment_method" value="Cash (Counter)">
                            <div class="pay-icon">💵</div>
                            <div class="pay-label">Cash at Counter</div>
                        </label>
                        <label class="payment-option-card" onclick="setPaymentMethod('Direct UPI / QR', this)">
                            <input type="radio" name="payment_method" value="Direct UPI / QR">
                            <div class="pay-icon">📱</div>
                            <div class="pay-label">UPI / QR Scan</div>
                        </label>
                        <label class="payment-option-card" onclick="setPaymentMethod('Card / POS', this)">
                            <input type="radio" name="payment_method" value="Card / POS">
                            <div class="pay-icon">💳</div>
                            <div class="pay-label">Card / POS</div>
                        </label>
                        <label class="payment-option-card" onclick="setPaymentMethod('Bank Transfer / NEFT', this)">
                            <input type="radio" name="payment_method" value="Bank Transfer / NEFT">
                            <div class="pay-icon">🏦</div>
                            <div class="pay-label">Bank Transfer</div>
                        </label>
                    </div>

                    <!-- UPI Gateway Info Box -->
                    <div id="upiGatewayInfo" style="display:none; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:var(--radius-md); padding:0.85rem; margin-top:0.75rem; margin-bottom:1rem; text-align:left;">
                        <div style="font-size:0.75rem; font-weight:800; color:#1E40AF; text-transform:uppercase; margin-bottom:0.25rem; display:flex; align-items:center; gap:6px;">
                            <span>⚡</span> Live Payment Gateway &amp; UPI QR Desk
                        </div>
                        <p style="font-size:0.8rem; color:#1E3A8A; margin:0; line-height:1.4;">
                            Submitting will automatically connect with the payment gateway to generate a dynamic UPI QR code. The customer can scan directly from the screen, and the transaction ID will be verified in real time.
                        </p>
                    </div>

                    <!-- Cash Tender Calculator -->
                    <div id="cashCalculatorWrap" style="display:none; background:#F0FDF4; border:1px solid #BBF7D0; border-radius:var(--radius-md); padding:0.85rem; margin-bottom:1rem;">
                        <div style="font-size:0.75rem; font-weight:800; color:#166534; text-transform:uppercase; margin-bottom:0.4rem;">
                            Cash Change Calculator
                        </div>
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <div style="flex:1;">
                                <label style="font-size:0.72rem; color:#15803d; font-weight:700;">Cash Given (₹):</label>
                                <input type="number" name="cash_tendered" id="cashTenderedInput" class="desk-input" style="padding:0.4rem 0.6rem; font-size:0.9rem;" placeholder="e.g. 1000" oninput="calculateCashChange()">
                            </div>
                            <div style="flex:1; text-align:right;">
                                <div style="font-size:0.72rem; color:#15803d; font-weight:700;">Change Return:</div>
                                <div id="cashChangeDisplay" style="font-size:1.1rem; font-weight:800; color:#15803d;">₹ 0.00</div>
                            </div>
                        </div>
                    </div>

                    <!-- Reference Input (For UPI UTR / Card Auth) -->
                    <div id="paymentRefWrap" style="display:none; margin-bottom:1rem;">
                        <label class="desk-label" id="paymentRefLabel">Transaction Reference / UTR</label>
                        <input type="text" name="payment_reference" id="paymentRefInput" class="desk-input" placeholder="e.g. UPI Ref / UTR / Card Slip No">
                    </div>

                    <!-- Payment Status & Order Status -->
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.6rem; margin-bottom:1rem;">
                        <div class="desk-form-group">
                            <label class="desk-label">Payment Status *</label>
                            <select name="payment_status" id="paymentStatus" class="desk-select" required style="font-weight:700;">
                                <option value="" selected disabled>Select</option>
                                <option value="Paid">✓ Paid in Full</option>
                                <option value="Pending">⏳ Payment Pending</option>
                            </select>
                        </div>
                        <div class="desk-form-group">
                            <label class="desk-label">Order Status *</label>
                            <select name="order_status" id="orderStatus" class="desk-select" required style="font-weight:700;">
                                <option value="" selected disabled>Select</option>
                                <option value="Completed">✓ Completed (Handed Over)</option>
                                <option value="Confirmed">📦 Confirmed (In Progress)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Optional Staff Remarks -->
                    <div class="desk-form-group" style="margin-bottom:1.25rem;">
                        <label class="desk-label">Staff Remarks (Optional)</label>
                        <input type="text" name="admin_notes" class="desk-input" placeholder="e.g. Size exchange note, receipt given to parent">
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="btnPlaceOrder" class="btn btn-primary" style="width:100%; padding:0.9rem; font-size:1rem; font-weight:800; border-radius:var(--radius-lg); display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 14px rgba(255, 103, 2, 0.35);">
                    <span>💳 Complete Order &amp; Generate Receipt</span>
                </button>
            </div>
        </div>
    </div>
</form>

<!-- POPUP MODAL: LIVE GATEWAY PAYMENT & QR SCAN -->
<div id="gatewayPaymentModal" class="desk-modal-overlay" style="display:none;">
    <div class="desk-modal-card" style="max-width:480px; padding:1.75rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #E2E8F0; padding-bottom:0.75rem;">
            <div style="text-align:left;">
                <span style="font-size:0.72rem; font-weight:800; text-transform:uppercase; color:var(--brand-primary); letter-spacing:0.5px;">Live Payment Desk</span>
                <h3 style="font-size:1.15rem; font-weight:800; color:var(--brand-dark); margin:0;">UPI &amp; QR Payment</h3>
            </div>
            <button type="button" onclick="closeGatewayModal()" style="background:none; border:none; font-size:1.4rem; line-height:1; color:#94A3B8; cursor:pointer;" title="Close">&times;</button>
        </div>

        <div style="text-align:center; margin-bottom:1.25rem;">
            <div style="background:#F8FAFC; border:1.5px dashed #CBD5E1; border-radius:16px; padding:1.25rem; display:inline-block; margin-bottom:0.75rem;">
                <img id="gwQrImg" src="" alt="UPI QR Code" style="width:210px; height:210px; display:block; border-radius:8px; background:#fff;">
            </div>
            <div style="font-size:0.82rem; color:#64748B;">
                Scan with any UPI App: <strong>GPay, PhonePe, Paytm, BHIM</strong>
            </div>
        </div>

        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:var(--radius-md); padding:0.85rem 1rem; margin-bottom:1rem; text-align:left; font-size:0.85rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span style="color:#64748B;">Order Ref:</span>
                <strong id="gwOrderRef" style="color:var(--brand-primary);">-</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span style="color:#64748B;">Payable Amount:</span>
                <strong id="gwAmount" style="color:#16A34A; font-size:1rem;">-</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span style="color:#64748B;">Student:</span>
                <span id="gwStudent" style="font-weight:700; color:#1E293B;">-</span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#64748B;">Gateway Txn ID:</span>
                <span id="gwTxnId" style="font-family:monospace; font-size:0.75rem; background:#E2E8F0; color:#0F172A; padding:2px 6px; border-radius:4px; max-width:210px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">-</span>
            </div>
        </div>

        <div id="gwStatusBox" style="margin-bottom:1.25rem; padding:0.6rem 1rem; border-radius:9999px; font-size:0.82rem; font-weight:700; display:inline-flex; align-items:center; gap:8px; background:#FEF3C7; color:#92400E;">
            <span class="gw-pulse" style="width:8px; height:8px; border-radius:50%; background:#D97706; display:inline-block;"></span>
            <span id="gwStatusText">Waiting for parent to scan &amp; pay...</span>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.5rem;">
            <a id="gwOpenLinkBtn" href="#" target="_blank" class="btn btn-outline" style="padding:0.6rem; font-size:0.85rem; font-weight:700; display:flex; align-items:center; justify-content:center; gap:6px;">
                <span>🔗 Open Live Payment Checkout Page</span>
            </a>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem;">
                <button type="button" id="gwManualVerifyBtn" onclick="manualVerifyGatewayPaid()" class="btn btn-primary" style="padding:0.65rem; font-size:0.82rem; font-weight:800; background:#10B981; border-color:#10B981;">
                    ✓ Mark as Paid
                </button>
                <button type="button" onclick="completeGatewayOrder()" class="btn btn-primary" style="padding:0.65rem; font-size:0.82rem; font-weight:800;">
                    🖨️ View Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- POPUP MODAL: ORDER CREATED SUCCESSFULLY -->
<div id="orderSuccessModal" class="desk-modal-overlay" style="display:none;">
    <div class="desk-modal-card">
        <div style="width:64px; height:64px; border-radius:50%; background:#DCFCE7; color:#16A34A; display:flex; align-items:center; justify-content:center; font-size:2rem; margin:0 auto 1rem auto; box-shadow:0 0 0 8px #F0FDF4;">
            ✓
        </div>
        <h3 style="font-size:1.4rem; font-weight:800; color:var(--brand-dark); margin:0 0 0.35rem 0;">
            Order Placed Successfully!
        </h3>
        <p style="font-size:0.85rem; color:var(--brand-muted); margin:0 0 1.25rem 0;">
            The direct counter order has been registered in the system.
        </p>

        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:var(--radius-md); padding:1rem; margin-bottom:1.5rem; text-align:left;">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.85rem;">
                <span style="color:#64748b;">Order Reference:</span>
                <strong id="modalOrderRef" style="color:var(--brand-primary); font-size:0.95rem;">-</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.85rem;">
                <span style="color:#64748b;">Student:</span>
                <strong id="modalStudentName">-</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.85rem;">
                <span style="color:#64748b;">Total Amount:</span>
                <strong id="modalTotalAmount" style="color:#16A34A; font-size:1.05rem;">-</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem;">
                <span style="color:#64748b;">Payment Method:</span>
                <strong id="modalPaymentMethod">-</strong>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.6rem;">
            <button type="button" id="btnModalPrint" class="btn btn-primary" style="padding:0.75rem; font-weight:800; display:flex; align-items:center; justify-content:center; gap:6px;">
                <span>🖨️ Print Customer Receipt / Invoice</span>
            </button>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.6rem;">
                <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline" style="padding:0.6rem; font-size:0.85rem; font-weight:700;">
                    View in Orders List
                </a>
                <button type="button" onclick="startNewOrder()" class="btn btn-outline" style="padding:0.6rem; font-size:0.85rem; font-weight:700; color:var(--brand-primary); border-color:var(--brand-primary);">
                    + New Order
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Master Client State
const SUB_SCHOOLS_MAP = <?= json_encode($subSchoolsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let CURRENT_PRODUCTS = [];
let ORDER_CART = [];
let ACTIVE_CATEGORY_FILTER = '';
let ACTIVE_RECEIPT_URL = '';

// Handle School Selection Change
function handleSchoolChange(schoolId) {
    const subSelect = document.getElementById('subSchoolSelect');
    const subGroup = document.getElementById('subSchoolGroup');
    const schoolBadge = document.getElementById('schoolBadge');
    const selOpt = document.querySelector('#schoolSelect option[value="' + schoolId + '"]');

    subSelect.innerHTML = '<option value="">-- Main Campus / All Units --</option>';

    if (!schoolId) {
        subGroup.style.display = 'none';
        schoolBadge.style.display = 'none';
        showEmptyPrompt(true);
        return;
    }

    const schoolCode = selOpt ? selOpt.getAttribute('data-code') : '';
    if (schoolCode) {
        schoolBadge.textContent = 'CODE: ' + schoolCode;
        schoolBadge.style.display = 'inline-block';
    } else {
        schoolBadge.style.display = 'none';
    }

    // Check if school has sub-schools
    const subSchools = SUB_SCHOOLS_MAP[schoolId] || [];
    if (subSchools.length > 0) {
        subSchools.forEach(sub => {
            const opt = document.createElement('option');
            opt.value = sub.id;
            opt.textContent = sub.name + (sub.code ? ' (' + sub.code + ')' : '');
            opt.setAttribute('data-code', sub.code || '');
            subSelect.appendChild(opt);
        });
        subGroup.style.display = 'block';
    } else {
        subGroup.style.display = 'none';
    }

    // Fetch and render products for this school
    loadSchoolProducts(schoolId);
}

function handleSubSchoolChange(subSchoolId) {
    const mainSchoolId = document.getElementById('schoolSelect').value;
    const targetId = subSchoolId || mainSchoolId;
    loadSchoolProducts(targetId);
}

function toggleShowAllCatalog(isChecked) {
    const mainSchoolId = document.getElementById('schoolSelect').value;
    const subSchoolId = document.getElementById('subSchoolSelect').value;
    const targetId = subSchoolId || mainSchoolId || 0;
    loadSchoolProducts(targetId, isChecked);
}

// Fetch products from backend via AJAX
function loadSchoolProducts(schoolId, forceAll = false) {
    const loader = document.getElementById('catalogLoading');
    const grid = document.getElementById('productGrid');
    const emptyPrompt = document.getElementById('catalogEmptyPrompt');

    loader.style.display = 'block';
    grid.style.display = 'none';
    emptyPrompt.style.display = 'none';

    const url = '<?= BASE_URL ?>/admin/create_order.php?ajax_get_school_products=' + schoolId + (forceAll ? '&show_all=1' : '');

    fetch(url)
        .then(res => res.json())
        .then(data => {
            loader.style.display = 'none';
            if (data.success && data.products) {
                CURRENT_PRODUCTS = data.products;
                renderProductCatalog(CURRENT_PRODUCTS);
            } else {
                CURRENT_PRODUCTS = [];
                renderProductCatalog([]);
            }
        })
        .catch(err => {
            loader.style.display = 'none';
            console.error('Failed to load products:', err);
            CURRENT_PRODUCTS = [];
            renderProductCatalog([]);
        });
}

function showEmptyPrompt(show) {
    document.getElementById('catalogEmptyPrompt').style.display = show ? 'block' : 'none';
    document.getElementById('productGrid').style.display = show ? 'none' : 'grid';
}

// Render Uniform Cards in Step 2
function renderProductCatalog(products) {
    const grid = document.getElementById('productGrid');
    grid.innerHTML = '';

    if (!products || products.length === 0) {
        grid.style.display = 'none';
        const emptyPrompt = document.getElementById('catalogEmptyPrompt');
        emptyPrompt.querySelector('h4').textContent = 'No Uniforms Found For This School';
        emptyPrompt.querySelector('p').textContent = 'Please choose another school or contact the catalog administrator.';
        emptyPrompt.style.display = 'block';
        return;
    }

    document.getElementById('catalogEmptyPrompt').style.display = 'none';
    grid.style.display = 'grid';

    products.forEach(p => {
        const card = document.createElement('div');
        card.className = 'prod-card';
        card.setAttribute('data-id', p.id);
        card.setAttribute('data-group', (p.uniform_group || '').toLowerCase());
        card.setAttribute('data-name', (p.name || '').toLowerCase());

        const imgUrl = p.featured_image ? (p.featured_image.startsWith('http') ? p.featured_image : '<?= BASE_URL ?>/' + p.featured_image.replace(/^\/+/, '')) : '<?= BASE_URL ?>/assets/images/placeholder-product.png';
        const groupLabel = p.uniform_group ? p.uniform_group.toUpperCase() : (p.category_name || 'UNIFORM');

        // Variants / Sizes Buttons - Do NOT auto-select
        let sizeButtonsHtml = '';
        const variants = p.variants || [];
        if (variants.length > 0) {
            variants.forEach((v) => {
                sizeButtonsHtml += `<button type="button" class="size-btn" data-size="${v.size}" data-vid="${v.id || ''}" onclick="selectCardSize(this, ${p.id})">${v.size}</button>`;
            });
        } else {
            sizeButtonsHtml = `<button type="button" class="size-btn" data-size="Standard" data-vid="" onclick="selectCardSize(this, ${p.id})">Standard</button>`;
        }

        card.innerHTML = `
            <div class="prod-thumb-wrap">
                <span class="prod-cat-badge">${groupLabel}</span>
                <img src="${imgUrl}" alt="${p.name}" class="prod-thumb-img" onerror="this.src='<?= BASE_URL ?>/assets/images/placeholder-product.png'">
            </div>
            <h4 class="prod-name" title="${p.name}">${p.name}</h4>
            <div class="prod-price-tag">${p.formatted_price}</div>
            
            <div style="font-size:0.7rem; font-weight:700; color:#64748b; margin-bottom:3px;">Select Size:</div>
            <div class="prod-size-picker" id="sizePicker-${p.id}">
                ${sizeButtonsHtml}
            </div>

            <button type="button" class="prod-add-btn" onclick="addToOrder(${p.id})">
                <span>+ Add to Order</span>
            </button>
        `;

        grid.appendChild(card);
    });
}

function selectCardSize(btn, prodId) {
    const parent = document.getElementById('sizePicker-' + prodId);
    if (!parent) return;
    parent.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// Filter Catalog by Category
function filterByCategory(cat, btn) {
    ACTIVE_CATEGORY_FILTER = cat.toLowerCase();
    document.querySelectorAll('.catalog-filter-bar button').forEach(b => b.classList.remove('active-cat-pill'));
    btn.classList.add('active-cat-pill');
    filterCatalogItems();
}

function filterCatalogItems() {
    const q = (document.getElementById('catalogSearchInput').value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.prod-card');

    cards.forEach(c => {
        const name = c.getAttribute('data-name') || '';
        const group = c.getAttribute('data-group') || '';

        const matchesQuery = !q || name.includes(q);
        const matchesCategory = !ACTIVE_CATEGORY_FILTER || group === ACTIVE_CATEGORY_FILTER;

        if (matchesQuery && matchesCategory) {
            c.style.display = 'flex';
        } else {
            c.style.display = 'none';
        }
    });
}

// Add Item to Order Cart
function addToOrder(prodId) {
    const prod = CURRENT_PRODUCTS.find(p => parseInt(p.id) === parseInt(prodId));
    if (!prod) return;

    // Resolve selected size - require explicit user selection
    const picker = document.getElementById('sizePicker-' + prodId);
    const activeSizeBtn = picker ? picker.querySelector('.size-btn.active') : null;
    
    if (!activeSizeBtn) {
        alert('Please select a size for "' + prod.name + '" first.');
        if (picker) {
            picker.style.outline = '2px solid #FF6702';
            picker.style.borderRadius = '4px';
            setTimeout(() => { picker.style.outline = 'none'; }, 1800);
        }
        return;
    }

    const selectedSize = activeSizeBtn.getAttribute('data-size');
    const variantId = activeSizeBtn.getAttribute('data-vid');

    // Check if identical item (same product ID & size) already in cart
    const existingIndex = ORDER_CART.findIndex(item => item.product_id === prod.id && item.size === selectedSize);
    if (existingIndex > -1) {
        ORDER_CART[existingIndex].quantity += 1;
    } else {
        ORDER_CART.push({
            product_id: prod.id,
            name: prod.name,
            size: selectedSize,
            variant_id: variantId ? parseInt(variantId) : null,
            price: parseFloat(prod.effective_price),
            quantity: 1,
            image: prod.featured_image || ''
        });
    }

    renderCart();

    // Visual button feedback
    const btn = picker.parentElement.querySelector('.prod-add-btn');
    if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = '<span>✓ Added!</span>';
        btn.style.background = '#10B981';
        btn.style.borderColor = '#10B981';
        btn.style.color = '#fff';
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.style.background = '';
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 600);
    }
}

// Change Quantity in Cart
function updateCartQty(index, delta) {
    if (!ORDER_CART[index]) return;
    ORDER_CART[index].quantity += delta;
    if (ORDER_CART[index].quantity <= 0) {
        ORDER_CART.splice(index, 1);
    }
    renderCart();
}

function removeCartItem(index) {
    if (!ORDER_CART[index]) return;
    ORDER_CART.splice(index, 1);
    renderCart();
}

// Render Order Cart & Calculations
function renderCart() {
    const container = document.getElementById('cartItemsList');
    const emptyState = document.getElementById('cartEmptyState');
    const countBadge = document.getElementById('cartCountBadge');
    container.innerHTML = '';

    let totalCount = 0;
    let subtotal = 0.0;

    if (ORDER_CART.length === 0) {
        emptyState.style.display = 'block';
    } else {
        emptyState.style.display = 'none';

        ORDER_CART.forEach((item, idx) => {
            totalCount += item.quantity;
            const lineTotal = item.price * item.quantity;
            subtotal += lineTotal;

            const row = document.createElement('div');
            row.className = 'cart-item-row';
            row.innerHTML = `
                <div>
                    <div class="cart-item-title">${item.name}</div>
                    <span class="cart-item-size-badge">Size: ${item.size}</span>
                    <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">₹ ${item.price.toFixed(2)} each</div>
                </div>
                <div style="text-align:right;">
                    <div class="cart-qty-ctrl">
                        <button type="button" class="cart-qty-btn" onclick="updateCartQty(${idx}, -1)">-</button>
                        <span class="cart-qty-num">${item.quantity}</span>
                        <button type="button" class="cart-qty-btn" onclick="updateCartQty(${idx}, 1)">+</button>
                    </div>
                    <div style="font-size:0.85rem; font-weight:800; color:var(--brand-dark); margin-top:2px;">
                        ₹ ${lineTotal.toFixed(2)}
                    </div>
                </div>
                <div>
                    <button type="button" class="cart-remove-btn" title="Remove Item" onclick="removeCartItem(${idx})">&times;</button>
                </div>
            `;
            container.appendChild(row);
        });
    }

    countBadge.textContent = totalCount + (totalCount === 1 ? ' item' : ' items');
    document.getElementById('itemsJsonInput').value = JSON.stringify(ORDER_CART);

    calculateBillTotal(subtotal);
}

function calculateBillTotal(computedSubtotal = null) {
    if (computedSubtotal === null) {
        computedSubtotal = ORDER_CART.reduce((sum, it) => sum + (it.price * it.quantity), 0);
    }
    const discount = Math.max(0, parseFloat(document.getElementById('discountInput').value) || 0);
    const grandTotal = Math.max(0, computedSubtotal - discount);

    document.getElementById('billSubtotal').textContent = '₹ ' + computedSubtotal.toFixed(2);
    document.getElementById('billGrandTotal').textContent = '₹ ' + grandTotal.toFixed(2);

    calculateCashChange();
}

function calculateCashChange() {
    const grandText = document.getElementById('billGrandTotal').textContent.replace(/[^0-9.]/g, '');
    const grandTotal = parseFloat(grandText) || 0.0;
    const tendered = parseFloat(document.getElementById('cashTenderedInput').value) || 0.0;

    const change = Math.max(0, tendered - grandTotal);
    document.getElementById('cashChangeDisplay').textContent = '₹ ' + change.toFixed(2);
}

// Payment Method Card Selector
function setPaymentMethod(method, cardEl) {
    document.querySelectorAll('.payment-option-card').forEach(c => c.classList.remove('active'));
    cardEl.classList.add('active');
    cardEl.querySelector('input').checked = true;

    const cashWrap = document.getElementById('cashCalculatorWrap');
    const refWrap = document.getElementById('paymentRefWrap');
    const refLabel = document.getElementById('paymentRefLabel');
    const upiInfo = document.getElementById('upiGatewayInfo');

    if (method === 'Cash (Counter)') {
        cashWrap.style.display = 'block';
        refWrap.style.display = 'none';
        if (upiInfo) upiInfo.style.display = 'none';
    } else if (method === 'Direct UPI / QR') {
        cashWrap.style.display = 'none';
        refWrap.style.display = 'none';
        if (upiInfo) upiInfo.style.display = 'block';
    } else {
        cashWrap.style.display = 'none';
        refWrap.style.display = 'block';
        if (upiInfo) upiInfo.style.display = 'none';
        if (method === 'Card / POS') {
            refLabel.textContent = 'Card Slip / Auth Code (Optional)';
        } else {
            refLabel.textContent = 'Bank Transaction Reference (Optional)';
        }
    }
}

// Gender Pill Selector
function setGender(val, pillEl) {
    document.querySelectorAll('.gender-pill').forEach(p => p.classList.remove('active'));
    pillEl.classList.add('active');
    pillEl.querySelector('input').checked = true;
}

// Fulfillment Change
function handleFulfillmentChange(mode) {
    const addrWrap = document.getElementById('addressFieldWrap');
    if (mode === 'Courier') {
        addrWrap.style.display = 'block';
    } else {
        addrWrap.style.display = 'none';
    }
}

// Submit Order via AJAX
function handleOrderSubmit(e) {
    e.preventDefault();

    const schoolId = document.getElementById('schoolSelect').value;
    const studentName = document.getElementById('studentName').value.trim();
    const gradeClass = document.getElementById('gradeClass').value.trim();
    const mobile = document.getElementById('parentMobile').value.trim();
    const fulfillmentType = document.getElementById('fulfillmentType').value;
    const paymentStatus = document.getElementById('paymentStatus').value;
    const orderStatus = document.getElementById('orderStatus').value;

    if (!schoolId) {
        alert('Please choose a partner school.');
        document.getElementById('schoolSelect').focus();
        return false;
    }
    if (!studentName) {
        alert('Please enter student full name.');
        document.getElementById('studentName').focus();
        return false;
    }
    if (!gradeClass) {
        alert('Please enter grade / class.');
        document.getElementById('gradeClass').focus();
        return false;
    }
    if (!mobile || mobile.length < 10) {
        alert('Please enter a valid 10-digit mobile number.');
        document.getElementById('parentMobile').focus();
        return false;
    }
    const genderChecked = document.querySelector('input[name="gender"]:checked');
    if (!genderChecked) {
        alert('Please select Gender (Boy, Girl, or Unisex).');
        return false;
    }
    if (!fulfillmentType) {
        alert('Please select Fulfillment Handover Mode.');
        document.getElementById('fulfillmentType').focus();
        return false;
    }
    if (ORDER_CART.length === 0) {
        alert('Your order bill is empty. Please add at least one uniform item.');
        return false;
    }
    const paymentMethodChecked = document.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethodChecked) {
        alert('Please select a Payment Method.');
        return false;
    }
    if (!paymentStatus) {
        alert('Please select Payment Status.');
        document.getElementById('paymentStatus').focus();
        return false;
    }
    if (!orderStatus) {
        alert('Please select Order Status.');
        document.getElementById('orderStatus').focus();
        return false;
    }

    const btn = document.getElementById('btnPlaceOrder');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳ Processing Direct Order...</span>';

    const formData = new FormData(document.getElementById('directOrderForm'));
    formData.append('is_ajax', '1');

    fetch('<?= BASE_URL ?>/admin/create_order.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (data.success && data.data) {
            if (data.data.is_gateway && data.data.collect_url) {
                showGatewayModal(data.data);
            } else {
                showSuccessModal(data.data);
            }
        } else {
            alert('Error creating order: ' + (data.error || 'Unknown error occurred.'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        console.error(err);
        alert('Connection error occurred while processing order.');
    });

    return false;
}

let GW_ACTIVE_DATA = null;
let GW_POLL_TIMER = null;

function showGatewayModal(data) {
    GW_ACTIVE_DATA = data;
    ACTIVE_RECEIPT_URL = data.receipt_url;

    document.getElementById('gwOrderRef').textContent = '#' + data.order_reference;
    document.getElementById('gwAmount').textContent = data.total_formatted;
    document.getElementById('gwStudent').textContent = data.student_name;
    document.getElementById('gwTxnId').textContent = data.gateway_transaction_id || data.collect_id || '-';
    document.getElementById('gwOpenLinkBtn').href = data.collect_url;

    // Generate dynamic QR code
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' + encodeURIComponent(data.collect_url);
    document.getElementById('gwQrImg').src = qrUrl;

    // Reset status box
    const statusBox = document.getElementById('gwStatusBox');
    const statusText = document.getElementById('gwStatusText');
    statusBox.style.background = '#FEF3C7';
    statusBox.style.color = '#92400E';
    statusText.textContent = 'Waiting for parent to scan & pay...';

    const verifyBtn = document.getElementById('gwManualVerifyBtn');
    verifyBtn.disabled = false;
    verifyBtn.textContent = '✓ Mark as Paid';

    document.getElementById('gatewayPaymentModal').style.display = 'flex';

    // Start auto polling every 3 seconds
    clearInterval(GW_POLL_TIMER);
    if (data.collect_id && data.order_id) {
        GW_POLL_TIMER = setInterval(() => {
            fetch('<?= BASE_URL ?>/admin/create_order.php?ajax_check_gateway_status=1&collect_id=' + encodeURIComponent(data.collect_id) + '&order_id=' + data.order_id)
                .then(res => res.json())
                .then(res => {
                    if (res && res.is_paid) {
                        clearInterval(GW_POLL_TIMER);
                        gwPaymentSuccess();
                    }
                })
                .catch(() => {});
        }, 3000);
    }
}

function gwPaymentSuccess() {
    const statusBox = document.getElementById('gwStatusBox');
    const statusText = document.getElementById('gwStatusText');
    statusBox.style.background = '#DCFCE7';
    statusBox.style.color = '#166534';
    statusText.innerHTML = '✓ Payment Confirmed via Gateway!';
    if (GW_ACTIVE_DATA) {
        GW_ACTIVE_DATA.payment_status = 'Paid';
    }
    const verifyBtn = document.getElementById('gwManualVerifyBtn');
    verifyBtn.textContent = '✓ Verified Paid';
    verifyBtn.disabled = true;
}

function manualVerifyGatewayPaid() {
    if (!GW_ACTIVE_DATA || !GW_ACTIVE_DATA.order_id) return;
    const btn = document.getElementById('gwManualVerifyBtn');
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    const formData = new FormData();
    formData.append('action', 'manual_verify_gateway_paid');
    formData.append('order_id', GW_ACTIVE_DATA.order_id);

    fetch('<?= BASE_URL ?>/admin/create_order.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res && res.success) {
            clearInterval(GW_POLL_TIMER);
            gwPaymentSuccess();
        } else {
            btn.disabled = false;
            btn.textContent = '✓ Mark as Paid';
            alert('Could not update status: ' + (res.error || 'Server error'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = '✓ Mark as Paid';
        alert('Network error while verifying payment.');
    });
}

function completeGatewayOrder() {
    clearInterval(GW_POLL_TIMER);
    document.getElementById('gatewayPaymentModal').style.display = 'none';
    if (GW_ACTIVE_DATA) {
        showSuccessModal(GW_ACTIVE_DATA);
    }
}

function closeGatewayModal() {
    clearInterval(GW_POLL_TIMER);
    document.getElementById('gatewayPaymentModal').style.display = 'none';
    if (GW_ACTIVE_DATA) {
        showSuccessModal(GW_ACTIVE_DATA);
    }
}

function showSuccessModal(data) {
    ACTIVE_RECEIPT_URL = data.receipt_url;
    document.getElementById('modalOrderRef').textContent = '#' + data.order_reference;
    document.getElementById('modalStudentName').textContent = data.student_name;
    document.getElementById('modalTotalAmount').textContent = data.total_formatted;
    document.getElementById('modalPaymentMethod').textContent = data.payment_method + ' (' + data.payment_status + ')';

    document.getElementById('btnModalPrint').onclick = () => {
        window.open(ACTIVE_RECEIPT_URL, '_blank', 'width=800,height=900');
    };

    document.getElementById('orderSuccessModal').style.display = 'flex';
}

function startNewOrder() {
    clearInterval(GW_POLL_TIMER);
    GW_ACTIVE_DATA = null;
    document.getElementById('orderSuccessModal').style.display = 'none';
    document.getElementById('gatewayPaymentModal').style.display = 'none';
    document.getElementById('directOrderForm').reset();
    document.querySelectorAll('.payment-option-card').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.gender-pill').forEach(p => p.classList.remove('active'));
    const upiInfo = document.getElementById('upiGatewayInfo');
    if (upiInfo) upiInfo.style.display = 'none';
    ORDER_CART = [];
    renderCart();
    document.getElementById('schoolBadge').style.display = 'none';
    document.getElementById('subSchoolGroup').style.display = 'none';
    showEmptyPrompt(true);
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

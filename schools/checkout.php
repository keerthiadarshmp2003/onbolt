<?php
/**
 * OnBolt Digital Experience Platform - School Kit Checkout
 * URL: /schools/{school-slug}/checkout/
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$schoolSlug = trim($_GET['school'] ?? '');
$school = getSchoolBySlug($schoolSlug);

if (!$school) {
    header("Location: " . BASE_URL . "/schools/");
    exit;
}

$parent = requireParentAuth((int)$school['id'], $school['slug']);
$students = getStudentsForParent((int)$parent['id']);
$cartTotals = getCartTotals((int)$school['id']);

if (empty($cartTotals['items'])) {
    header("Location: " . BASE_URL . "/schools/{$schoolSlug}/cart/");
    exit;
}

$error = '';

// Handle Order Placement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Session security token expired. Please resubmit.';
    } else {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $parentName = trim($_POST['parent_name'] ?? $parent['parent_name']);
        $mobile = trim($_POST['mobile'] ?? $parent['phone']);
        $altMobile = trim($_POST['alt_mobile'] ?? '');
        $email = trim($_POST['email'] ?? ($parent['email'] ?? ''));
        $address = trim($_POST['delivery_address'] ?? 'Campus Pickup / Direct Delivery');
        $pincode = trim($_POST['pincode'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? 'WhatsApp / Offline Requisition');

        // Find selected student details
        $selectedStudentName = 'Student';
        $selectedGradeClass = 'Class';
        foreach ($students as $stu) {
            if ($stu['id'] == $studentId) {
                $selectedStudentName = $stu['student_name'];
                $selectedGradeClass = $stu['grade_class'] . (!empty($stu['section']) ? '-' . $stu['section'] : '');
                break;
            }
        }

        if (empty($parentName) || empty($mobile) || empty($address)) {
            $error = 'Please fill in all required parent and delivery fields.';
        } else {
            $pdo = getDbConnection();
            try {
                $pdo->beginTransaction();

                // Unique Order Reference in (schoolcode)number format
                $schoolCode = 'TIS';
                if (!empty($school['code'])) {
                    $schoolCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $school['code']));
                }
                $orderRef = $schoolCode . rand(100000, 999999);

                $stmt = $pdo->prepare("INSERT INTO orders (
                    order_reference, school_id, parent_id, parent_name, student_name, grade_class,
                    mobile, alt_mobile, email, delivery_address, pincode, state, total_items,
                    subtotal, shipping_fee, tax_amount, total_amount, payment_status, payment_method,
                    order_status, admin_notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Kerala', ?, ?, ?, 0.00, ?, 'Pending', ?, 'Pending', 'Placed through unified school ordering portal', NOW())");

                $stmt->execute([
                    $orderRef,
                    $school['id'],
                    $parent['id'],
                    $parentName,
                    $selectedStudentName,
                    $selectedGradeClass,
                    $mobile,
                    $altMobile,
                    $email,
                    $address,
                    $pincode,
                    $cartTotals['count'],
                    $cartTotals['subtotal'],
                    $cartTotals['shipping'],
                    $cartTotals['total'],
                    $paymentMethod
                ]);

                $orderId = (int)$pdo->lastInsertId();

                // Insert Order Items
                $itemStmt = $pdo->prepare("INSERT INTO order_items (
                    order_id, product_id, product_name, size, color, quantity, unit_price, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($cartTotals['items'] as $item) {
                    $itemTotal = $item['price'] * $item['quantity'];
                    $itemStmt->execute([
                        $orderId,
                        $item['product_id'],
                        $item['name'],
                        $item['size'],
                        $item['color'] ?? null,
                        $item['quantity'],
                        $item['price'],
                        $itemTotal
                    ]);
                }

                // Insert History
                $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, NULL, 'Pending', 'Order requisition logged successfully', 'Parent', NOW())");
                $histStmt->execute([$orderId]);

                // Create Notification for Admins
                $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type, link, created_at) VALUES (?, ?, 'order', ?, NOW())");
                $notifStmt->execute([
                    "New Order Requisition: {$orderRef}",
                    "{$parentName} placed order for {$selectedStudentName} ({$selectedGradeClass}) at {$school['name']}. Total: " . formatCurrency($cartTotals['total']),
                    "orders.php?search=" . urlencode($orderRef)
                ]);

                // Log Activity
                logActivity('place_order', 'orders', (int)$parent['id'], 'parent', "Order {$orderRef} placed for {$school['name']}");

                // Commit Transaction
                $pdo->commit();

                // Clear the Cart
                clearCart((int)$school['id']);

                // If Online Gateway chosen, redirect to Edviron Payment Gateway
                if (strpos($paymentMethod, 'Online') !== false || strpos($paymentMethod, 'Gateway') !== false || strpos($paymentMethod, 'UPI') !== false) {
                    require_once __DIR__ . '/../includes/edviron.php';
                    $collectRes = edviron_create_collect_request([
                        'amount'           => $cartTotals['total'],
                        'order_reference'  => $orderRef,
                        'order_id'         => $orderRef,
                        'custom_order_id'  => $orderRef,
                        'student_id'       => '-',
                        'student_name'     => $selectedStudentName,
                        'student_email'    => $email,
                        'student_phone_no' => $mobile,
                        'additional_data'  => [
                            'grade_class' => $selectedGradeClass,
                            'order_ref'   => '-',
                            'parent_name' => $parentName,
                            'school_id'   => !empty($school['code']) ? $school['code'] : 'TIS'
                        ]
                    ], (int)$school['id']);

                    if ($collectRes['success'] && !empty($collectRes['collect_request_url'])) {
                        header("Location: " . $collectRes['collect_request_url']);
                        exit;
                    }
                }

                // Redirect to Order History with success status
                header("Location: " . BASE_URL . "/schools/{$schoolSlug}/orders/?ref={$orderRef}&placed=1");
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Order Placement Error: " . $e->getMessage());
                $error = 'Failed to process order: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Final Checkout | ' . sanitize($school['name']);
$activeSchoolSlug = $school['slug'];

require_once __DIR__ . '/../includes/header.php';
?>

<div style="background: var(--brand-surface-alt); padding-top: 120px; padding-bottom: 2rem; border-bottom: 1px solid var(--brand-border);">
    <div class="container">
        <span class="text-eyebrow gold" style="font-size:0.7rem; padding:0.2rem 0.6rem;"><?= sanitize($school['name']) ?></span>
        <h1 class="heading-1" style="margin-top:0.25rem;">Finalize Uniform Requisition</h1>
        <p style="color:var(--brand-muted); font-size:0.9rem;">Review student details and confirm institutional delivery.</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <?php if (!empty($error)): ?>
            <div style="background: rgba(255, 107, 107, 0.12); color: #dc2626; border: 1px solid rgba(255, 107, 107, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
                ⚠️ <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div style="display:grid; grid-template-columns: 1.8fr 1fr; gap:3rem; align-items:flex-start;">
                <!-- Form Inputs -->
                <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2.5rem; box-shadow:var(--shadow-sm);">
                    
                    <!-- 1. Student Selection -->
                    <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:28px; height:28px; border-radius:50%; background:var(--brand-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.85rem;">1</span>
                        <span>Select Student</span>
                    </h3>

                    <div class="form-group">
                        <label class="form-label">Ordering for Student *</label>
                        <select name="student_id" required class="form-control" style="font-weight:700;">
                            <?php foreach ($students as $stu): ?>
                                <option value="<?= $stu['id'] ?>">
                                    <?= sanitize($stu['student_name']) ?> (<?= sanitize($stu['grade_class']) ?> <?= !empty($stu['section']) ? '- ' . sanitize($stu['section']) : '' ?> &bull; Adm: <?= sanitize($stu['admission_no']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Parent & Delivery Contact -->
                    <h3 class="heading-2" style="font-size:1.25rem; margin-top:2.5rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:28px; height:28px; border-radius:50%; background:var(--brand-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.85rem;">2</span>
                        <span>Parent Information &amp; Delivery Address</span>
                    </h3>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Parent / Guardian Name *</label>
                            <input type="text" name="parent_name" required value="<?= sanitize($parent['parent_name']) ?>" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Primary Mobile Phone *</label>
                            <input type="tel" name="mobile" required value="<?= sanitize($parent['phone']) ?>" class="form-control">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Alternate Phone (WhatsApp)</label>
                            <input type="tel" name="alt_mobile" placeholder="+91 00000 00000" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Notification Address</label>
                            <input type="email" name="email" value="<?= sanitize($parent['email'] ?? '') ?>" class="form-control" placeholder="parent@example.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Delivery Address / School Campus Pickup Instructions *</label>
                        <textarea name="delivery_address" required class="form-control" placeholder="House/Flat No, Residential Street, or specify 'Collect at School Uniform Counter'">Villa 14, Green Valley Enclave, Kazhakkoottam</textarea>
                    </div>

                    <div class="form-group" style="max-width:240px;">
                        <label class="form-label">Postal Pincode</label>
                        <input type="text" name="pincode" placeholder="Enter 6-digit pincode" maxlength="6" class="form-control">
                    </div>

                    <!-- 3. Payment Method -->
                    <h3 class="heading-2" style="font-size:1.25rem; margin-top:2.5rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:28px; height:28px; border-radius:50%; background:var(--brand-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.85rem;">3</span>
                        <span>Payment &amp; Verification Method</span>
                    </h3>

                    <div style="display:flex; flex-direction:column; gap:1rem;">
                        <label style="display:flex; align-items:center; gap:1rem; padding:1.25rem; border:2px solid var(--brand-secondary); border-radius:var(--radius-md); background:var(--brand-secondary-light); cursor:pointer;">
                            <input type="radio" name="payment_method" value="WhatsApp / Offline Order" checked style="accent-color:var(--brand-primary); width:20px; height:20px;">
                            <div>
                                <div style="font-weight:800; color:var(--brand-dark);">Instant WhatsApp / School Counter Requisition</div>
                                <div style="font-size:0.85rem; color:var(--brand-muted);">Requisition logged instantly into school system. Fast confirmation via OnBolt WhatsApp desk.</div>
                            </div>
                        </label>

                        <label style="display:flex; align-items:center; gap:1rem; padding:1.25rem; border:1px solid var(--brand-border); border-radius:var(--radius-md); background:#fff; cursor:pointer;">
                            <input type="radio" name="payment_method" value="UPI / Online Net Banking" style="accent-color:var(--brand-primary); width:20px; height:20px;">
                            <div>
                                <div style="font-weight:800; color:var(--brand-dark);">UPI / Net Banking / Gateway Ready</div>
                                <div style="font-size:0.85rem; color:var(--brand-muted);">Pay securely with GPay, PhonePe, Paytm, or Net Banking after production confirmation.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Order Summary Sidebar -->
                <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-lg); position:sticky; top:100px;">
                    <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:1.5rem; border-bottom:1px solid var(--brand-border); padding-bottom:0.75rem;">Selected Items (<?= $cartTotals['count'] ?>)</h3>

                    <div style="max-height:300px; overflow-y:auto; margin-bottom:1.5rem; padding-right:0.5rem;">
                        <?php foreach ($cartTotals['items'] as $item): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; font-size:0.9rem;">
                                <div>
                                    <div style="font-weight:700; color:var(--brand-dark);"><?= sanitize($item['name']) ?></div>
                                    <div style="font-size:0.8rem; color:var(--brand-muted);">Size: <?= sanitize($item['size']) ?> &bull; Qty: <?= $item['quantity'] ?></div>
                                </div>
                                <div style="font-weight:800; color:var(--brand-dark);">
                                    <?= formatCurrency($item['price'] * $item['quantity']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="border-top:1px solid var(--brand-border); padding-top:1rem; margin-bottom:1rem; font-size:0.95rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                            <span style="color:var(--brand-muted);">Subtotal:</span>
                            <span style="font-weight:700;"><?= formatCurrency($cartTotals['subtotal']) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                            <span style="color:var(--brand-muted);">School Delivery:</span>
                            <span style="font-weight:700; color:#15803d;">Free Campus Dispatch</span>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:2rem; padding-top:1rem; border-top:1.5px dashed var(--brand-border); font-size:1.35rem; font-weight:800;">
                        <span>Total Payable:</span>
                        <span style="color:var(--brand-primary);"><?= formatCurrency($cartTotals['total']) ?></span>
                    </div>

                    <button type="submit" class="btn btn-secondary btn-lg" style="width:100%;">
                        <span>Confirm &amp; Place Requisition</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

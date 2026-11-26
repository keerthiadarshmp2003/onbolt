<?php
/**
 * OnBolt Digital Experience Platform - Admin Dashboard Overview
 * URL: /admin/
 */

declare(strict_types=1);

$adminActive = 'dashboard';
$adminTitle = 'Dashboard';
require_once __DIR__ . '/header.php';

if (!adminCan('dashboard_view')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to access the Dashboard.');
}

$pdo = getDbConnection();

// Compute Stat Metrics
$totalSchools = (int)$pdo->query("SELECT COUNT(*) FROM schools WHERE parent_id IS NULL OR parent_id = 0")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")->fetchColumn();
$inProgressOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('In progress', 'Production')")->fetchColumn();
$deliveredOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Delivered', 'Dispatched', 'Dispatched by hand')")->fetchColumn();
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status NOT IN ('Cancelled', 'Refunded')")->fetchColumn();

// Additional Statistics Overview Queries
$totalUnitsSold = (int)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM order_items")->fetchColumn();
$avgOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0.0;

// Order Status Breakdown
$statusBreakdown = $pdo->query("
    SELECT order_status, COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total 
    FROM orders 
    GROUP BY order_status 
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// School Performance Breakdown
$schoolBreakdown = $pdo->query("
    SELECT 
        CASE WHEN s.parent_id IS NOT NULL THEN p.name ELSE s.name END as school_name,
        COUNT(DISTINCT o.id) as orders_count,
        COALESCE(SUM(o.total_amount), 0) as revenue
    FROM orders o
    JOIN schools s ON o.school_id = s.id
    LEFT JOIN schools p ON s.parent_id = p.id
    GROUP BY school_name
    ORDER BY revenue DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Uniform Category Breakdown
$categoryBreakdown = $pdo->query("
    SELECT p.uniform_group, COUNT(oi.id) as item_sales, COALESCE(SUM(oi.total_price), 0) as group_revenue 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    GROUP BY p.uniform_group
    ORDER BY group_revenue DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Top Selling Uniforms
$topSellingUniforms = $pdo->query("
    SELECT p.name, COALESCE(SUM(oi.quantity), 0) as total_qty, COALESCE(SUM(oi.total_price), 0) as total_sales 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    GROUP BY p.id, p.name 
    ORDER BY total_sales DESC 
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

// Payment Methods & Statuses
$paymentBreakdown = $pdo->query("
    SELECT payment_status, COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total 
    FROM orders 
    GROUP BY payment_status
")->fetchAll(PDO::FETCH_ASSOC);

$paymentMethods = $pdo->query("
    SELECT payment_method, COUNT(*) as cnt 
    FROM orders 
    WHERE payment_method IS NOT NULL AND payment_method != '' 
    GROUP BY payment_method 
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- KPI Stat Cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    <div class="stat-card">
        <span class="stat-label">Total Purchasing Value</span>
        <div class="stat-num" style="color:var(--brand-primary);"><?= formatCurrency($totalRevenue) ?></div>
        <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:0.4rem;">Across all partner school orders</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Parent Purchases</span>
        <div class="stat-num"><?= $totalOrders ?></div>
        <div style="font-size:0.75rem; color:#b45309; margin-top:0.4rem; font-weight:700;"><?= $pendingOrders ?> Pending Verification</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Partner Schools</span>
        <div class="stat-num"><?= $totalSchools ?></div>
        <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:0.4rem;">With uploaded logos &amp; banners</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Uniform Catalog</span>
        <div class="stat-num"><?= $totalProducts ?></div>
        <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:0.4rem;">Tailored school garments &amp; kits</div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem; margin-bottom:1rem;">
    <a href="<?= BASE_URL ?>/admin/orders.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:48px; height:48px; border-radius:50%; background:rgba(255,103,2,0.1); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
            📦
        </div>
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark);">Orders</div>
    </a>

    <a href="<?= BASE_URL ?>/admin/create_order.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1.5px solid #FED7AA; box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:48px; height:48px; border-radius:50%; background:rgba(255,103,2,0.12); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
            🛍️
        </div>
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark);">New Order</div>
    </a>

    <a href="<?= BASE_URL ?>/admin/schools.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:48px; height:48px; border-radius:50%; background:rgba(37,99,235,0.1); color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
            🏫
        </div>
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark);">Schools</div>
    </a>

    <a href="<?= BASE_URL ?>/admin/products.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:48px; height:48px; border-radius:50%; background:rgba(22,163,74,0.1); color:#16a34a; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
            👕
        </div>
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark);">Products</div>
    </a>
</div>

<!-- Statistics Overview Section -->
<div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-sm); margin-top:1.5rem;">
    <!-- Section Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; border-bottom:1px solid #F1F5F9; padding-bottom:1.25rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <h3 class="heading-2" style="font-size:1.35rem; margin:0; color:var(--brand-dark);">Statistics Overview</h3>
                <span style="display:inline-flex; align-items:center; gap:5px; font-size:0.72rem; font-weight:800; background:#ECFDF5; color:#047857; padding:3px 8px; border-radius:9999px; border:1px solid #A7F3D0;">
                    <span style="width:6px; height:6px; border-radius:50%; background:#10B981;"></span> LIVE
                </span>
            </div>
            <p style="font-size:0.83rem; color:var(--brand-muted); margin:0.25rem 0 0 0;">Comprehensive operational metrics, fulfillment progress, and revenue distribution across schools.</p>
        </div>
        <div style="display:flex; gap:0.6rem; align-items:center;">
            <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline btn-sm" style="font-size:0.8rem; font-weight:700;">View All Orders &rarr;</a>
        </div>
    </div>

    <!-- Mini KPI Highlights Bar -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.75rem; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:var(--radius-lg); padding:1.25rem;">
        <div>
            <div style="font-size:0.75rem; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.04em;">Average Order Value</div>
            <div style="font-size:1.3rem; font-weight:800; color:var(--brand-dark); margin-top:0.25rem;"><?= formatCurrency($avgOrderValue) ?></div>
        </div>
        <div>
            <div style="font-size:0.75rem; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.04em;">Total Uniforms Ordered</div>
            <div style="font-size:1.3rem; font-weight:800; color:var(--brand-dark); margin-top:0.25rem;"><?= $totalUnitsSold ?> <span style="font-size:0.85rem; font-weight:600; color:#64748B;">Units</span></div>
        </div>
        <div>
            <div style="font-size:0.75rem; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.04em;">Confirmed Order Ratio</div>
            <div style="font-size:1.3rem; font-weight:800; color:#047857; margin-top:0.25rem;">
                <?= $totalOrders > 0 ? number_format((($totalOrders - $pendingOrders) / $totalOrders) * 100, 1) : 0 ?>%
            </div>
        </div>
        <div>
            <div style="font-size:0.75rem; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.04em;">Processed For Dispatch</div>
            <div style="font-size:1.3rem; font-weight:800; color:var(--brand-primary); margin-top:0.25rem;">
                <?= $inProgressOrders + $deliveredOrders ?> <span style="font-size:0.85rem; font-weight:600; color:#64748B;">Orders</span>
            </div>
        </div>
    </div>

    <!-- 2x2 Grid of Detailed Analytics Cards -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap:1.5rem;">
        
        <!-- Card 1: Order Pipeline & Fulfillment Status -->
        <div style="border:1px solid #E2E8F0; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; background:#ffffff;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
                <h4 style="font-size:0.95rem; font-weight:800; color:var(--brand-dark); margin:0;">Order Fulfillment Pipeline</h4>
                <span style="font-size:0.75rem; font-weight:700; color:#64748B;"><?= $totalOrders ?> Orders</span>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:0.95rem;">
                <?php
                $statusColors = [
                    'Confirmed' => ['#1D4ED8', '#DBEAFE', '#2563EB'],
                    'Pending' => ['#B45309', '#FEF3C7', '#F59E0B'],
                    'In progress' => ['#7E22CE', '#F3E8FF', '#9333EA'],
                    'Production' => ['#7E22CE', '#F3E8FF', '#9333EA'],
                    'Dispatched' => ['#0369A1', '#E0F2FE', '#0284C7'],
                    'Dispatched by Hand / Delivered' => ['#15803D', '#DCFCE7', '#16A34A'],
                    'Completed' => ['#15803D', '#DCFCE7', '#16A34A'],
                    'Cancelled' => ['#DC2626', '#FEE2E2', '#EF4444'],
                    'Refunded' => ['#9333EA', '#FAF5FF', '#A855F7'],
                ];
                
                foreach ($statusBreakdown as $sb):
                    $stName = $sb['order_status'];
                    $stCnt = (int)$sb['cnt'];
                    $stTotal = (float)$sb['total'];
                    $pct = $totalOrders > 0 ? ($stCnt / $totalOrders) * 100 : 0;
                    $cfg = $statusColors[$stName] ?? ['#475569', '#F1F5F9', '#64748B'];
                ?>
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.82rem; margin-bottom:0.35rem;">
                            <span style="font-weight:700; color:#1E293B; display:flex; align-items:center; gap:6px;">
                                <span style="width:8px; height:8px; border-radius:50%; background:<?= $cfg[2] ?>;"></span>
                                <?= htmlspecialchars($stName) ?>
                            </span>
                            <span style="font-weight:700; color:#64748B;">
                                <?= $stCnt ?> <span style="font-weight:500; font-size:0.75rem;">(<?= number_format($pct, 1) ?>%)</span> &bull; <?= formatCurrency($stTotal) ?>
                            </span>
                        </div>
                        <div style="height:7px; background:#F1F5F9; border-radius:9999px; overflow:hidden;">
                            <div style="height:100%; width:<?= min(100, max(3, $pct)) ?>%; background:<?= $cfg[2] ?>; border-radius:9999px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Card 2: Partner School Revenue & Order Distribution -->
        <div style="border:1px solid #E2E8F0; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; background:#ffffff;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
                <h4 style="font-size:0.95rem; font-weight:800; color:var(--brand-dark); margin:0;">Partner Schools Distribution</h4>
                <a href="<?= BASE_URL ?>/admin/schools.php" style="font-size:0.75rem; font-weight:700; color:var(--brand-primary); text-decoration:none;">Schools &rarr;</a>
            </div>

            <div style="display:flex; flex-direction:column; gap:1.15rem;">
                <?php
                $schoolColors = ['#FF6702', '#2563EB', '#10B981', '#8B5CF6'];
                $sIdx = 0;
                foreach ($schoolBreakdown as $sc):
                    $scName = $sc['school_name'] ?: 'Other Partner School';
                    $scOrders = (int)$sc['orders_count'];
                    $scRev = (float)$sc['revenue'];
                    $scPct = $totalRevenue > 0 ? ($scRev / $totalRevenue) * 100 : 0;
                    $barColor = $schoolColors[$sIdx % count($schoolColors)];
                    $sIdx++;
                ?>
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-end; font-size:0.83rem; margin-bottom:0.35rem;">
                            <div>
                                <div style="font-weight:800; color:var(--brand-dark);"><?= htmlspecialchars($scName) ?></div>
                                <div style="font-size:0.75rem; color:#64748B; font-weight:600;"><?= $scOrders ?> Orders Recorded</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:800; color:var(--brand-primary);"><?= formatCurrency($scRev) ?></div>
                                <div style="font-size:0.72rem; color:#64748B; font-weight:700;"><?= number_format($scPct, 1) ?>% Share</div>
                            </div>
                        </div>
                        <div style="height:8px; background:#F1F5F9; border-radius:9999px; overflow:hidden;">
                            <div style="height:100%; width:<?= min(100, max(4, $scPct)) ?>%; background:<?= $barColor ?>; border-radius:9999px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Card 3: Uniform Categories & Top Selling Garments -->
        <div style="border:1px solid #E2E8F0; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; background:#ffffff;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
                <h4 style="font-size:0.95rem; font-weight:800; color:var(--brand-dark); margin:0;">Category &amp; Product Demand</h4>
                <a href="<?= BASE_URL ?>/admin/products.php" style="font-size:0.75rem; font-weight:700; color:var(--brand-primary); text-decoration:none;">Products &rarr;</a>
            </div>

            <!-- Categories pills -->
            <div style="display:flex; gap:0.6rem; flex-wrap:wrap; margin-bottom:1.2rem;">
                <?php foreach ($categoryBreakdown as $cb): 
                    $grp = ucfirst(strtolower($cb['uniform_group'] ?: 'General'));
                    $grpRev = (float)$cb['group_revenue'];
                    $grpSales = (int)$cb['item_sales'];
                ?>
                    <div style="flex:1; min-width:105px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.6rem 0.75rem;">
                        <div style="font-size:0.7rem; font-weight:700; color:#64748B; text-transform:uppercase;"><?= htmlspecialchars($grp) ?></div>
                        <div style="font-size:0.9rem; font-weight:800; color:var(--brand-dark); margin-top:2px;"><?= formatCurrency($grpRev) ?></div>
                        <div style="font-size:0.72rem; color:#64748B; font-weight:600;"><?= $grpSales ?> sold</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Top Products List -->
            <div style="font-size:0.75rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:0.5rem;">Top Uniform Demand</div>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                <?php foreach ($topSellingUniforms as $tpu): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem; background:#FAFAFA; border:1px solid #F1F5F9; border-radius:6px; padding:0.45rem 0.7rem;">
                        <span style="font-weight:700; color:#1E293B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:210px;">
                            <?= htmlspecialchars($tpu['name']) ?>
                        </span>
                        <div style="display:flex; align-items:center; gap:0.6rem;">
                            <span style="font-size:0.72rem; font-weight:700; background:#E0F2FE; color:#0369A1; padding:1px 6px; border-radius:4px;"><?= (int)$tpu['total_qty'] ?> pcs</span>
                            <span style="font-weight:800; color:var(--brand-dark);"><?= formatCurrency((float)$tpu['total_sales']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Card 4: Payment Methods & Collection Status -->
        <div style="border:1px solid #E2E8F0; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; background:#ffffff;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
                <h4 style="font-size:0.95rem; font-weight:800; color:var(--brand-dark); margin:0;">Payment Collections &amp; Gateways</h4>
                <a href="<?= BASE_URL ?>/admin/pending_transactions.php" style="font-size:0.75rem; font-weight:700; color:#DC2626; text-decoration:none;">Issues &rarr;</a>
            </div>

            <div style="display:flex; flex-direction:column; gap:1rem;">
                <!-- Payment Status Blocks -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem;">
                    <?php
                    $paidStats = ['cnt' => 0, 'total' => 0.0];
                    $pendingPayStats = ['cnt' => 0, 'total' => 0.0];
                    foreach ($paymentBreakdown as $pb) {
                        if ($pb['payment_status'] === 'Paid') {
                            $paidStats = $pb;
                        } elseif ($pb['payment_status'] === 'Pending') {
                            $pendingPayStats = $pb;
                        }
                    }
                    ?>
                    <div style="background:#ECFDF5; border:1px solid #A7F3D0; border-radius:8px; padding:0.75rem;">
                        <div style="font-size:0.72rem; font-weight:800; color:#047857; text-transform:uppercase;">Settled / Verified</div>
                        <div style="font-size:1.15rem; font-weight:800; color:#065F46; margin-top:2px;"><?= formatCurrency((float)$paidStats['total']) ?></div>
                        <div style="font-size:0.72rem; color:#047857; font-weight:700; margin-top:2px;"><?= $paidStats['cnt'] ?> verified orders</div>
                    </div>
                    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:0.75rem;">
                        <div style="font-size:0.72rem; font-weight:800; color:#B45309; text-transform:uppercase;">Awaiting Settlement</div>
                        <div style="font-size:1.15rem; font-weight:800; color:#92400E; margin-top:2px;"><?= formatCurrency((float)$pendingPayStats['total']) ?></div>
                        <div style="font-size:0.72rem; color:#B45309; font-weight:700; margin-top:2px;"><?= $pendingPayStats['cnt'] ?> pending attempts</div>
                    </div>
                </div>

                <!-- Payment Methods Distribution -->
                <div>
                    <div style="font-size:0.75rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:0.5rem;">Payment Channel Distribution</div>
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <?php foreach ($paymentMethods as $pm): 
                            $mName = $pm['payment_method'];
                            $mCnt = (int)$pm['cnt'];
                            $mPct = $totalOrders > 0 ? ($mCnt / $totalOrders) * 100 : 0;
                        ?>
                            <div>
                                <div style="display:flex; justify-content:space-between; font-size:0.78rem; font-weight:700; margin-bottom:0.25rem;">
                                    <span style="color:#1E293B;"><?= htmlspecialchars($mName) ?></span>
                                    <span style="color:#64748B;"><?= $mCnt ?> transactions (<?= number_format($mPct, 1) ?>%)</span>
                                </div>
                                <div style="height:6px; background:#F1F5F9; border-radius:9999px; overflow:hidden;">
                                    <div style="height:100%; width:<?= min(100, max(5, $mPct)) ?>%; background:var(--brand-primary); border-radius:9999px;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

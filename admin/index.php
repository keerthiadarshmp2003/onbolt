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

// Financial Overview Metrics (Income, Expense, Profit)
$financialIncome = $totalRevenue;
$financialExpense = (float)$pdo->query("
    SELECT COALESCE(SUM(oi.quantity * ROUND(oi.unit_price * 0.30, 2)), 0) 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.order_status NOT IN ('Cancelled', 'Refunded')
")->fetchColumn();
$financialProfit = max(0, $financialIncome - $financialExpense);

// Schools Overview Donut Data
$schoolsDonutData = $pdo->query("
    SELECT 
        CASE WHEN s.parent_id IS NOT NULL THEN p.name ELSE s.name END as school_name,
        COUNT(DISTINCT o.id) as orders_count
    FROM orders o
    JOIN schools s ON o.school_id = s.id
    LEFT JOIN schools p ON s.parent_id = p.id
    GROUP BY school_name
    ORDER BY orders_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

$donutLabels = [];
$donutCounts = [];
foreach ($schoolsDonutData as $sd) {
    $donutLabels[] = $sd['school_name'] ?: 'Partner School';
    $donutCounts[] = (int)$sd['orders_count'];
}
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

<!-- Statistics Overview Section (Matching Reference Design) -->
<div class="overview-charts-grid" style="display:grid; grid-template-columns: 1.75fr 1.15fr; gap:1.5rem; margin-top:1.5rem; margin-bottom:2rem;">
    
    <!-- Left Card: Financial Overview (Bar Chart) -->
    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:1.75rem 2rem; box-shadow:var(--shadow-sm); display:flex; flex-direction:column;">
        <div style="margin-bottom:1.5rem;">
            <h3 class="heading-2" style="font-size:1.3rem; margin:0; color:var(--brand-dark); font-weight:800;">Financial Overview</h3>
            <p style="font-size:0.82rem; color:var(--brand-muted); margin:0.25rem 0 0 0;">Overview of total income, vendor expenses, and net profit.</p>
        </div>
        <div style="position:relative; width:100%; height:320px; flex:1;">
            <canvas id="financialOverviewChart"></canvas>
        </div>
    </div>

    <!-- Right Card: Schools Overview (Donut Chart) -->
    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:1.75rem 2rem; box-shadow:var(--shadow-sm); display:flex; flex-direction:column;">
        <div style="margin-bottom:1.5rem;">
            <h3 class="heading-2" style="font-size:1.3rem; margin:0; color:var(--brand-dark); font-weight:800;">Schools Overview</h3>
            <p style="font-size:0.82rem; color:var(--brand-muted); margin:0.25rem 0 0 0;">Comparison of schools to total orders.</p>
        </div>
        <div style="position:relative; width:100%; height:320px; flex:1; display:flex; align-items:center; justify-content:center;">
            <canvas id="schoolsOverviewDonut"></canvas>
        </div>
    </div>

</div>

<style>
@media (max-width: 960px) {
    .overview-charts-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script src="<?= BASE_URL ?>/assets/js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Financial Overview Bar Chart
    const finCanvas = document.getElementById('financialOverviewChart');
    if (finCanvas && typeof Chart !== 'undefined') {
        const finCtx = finCanvas.getContext('2d');
        const incomeVal = <?= json_encode((float)$financialIncome) ?>;
        const expenseVal = <?= json_encode((float)$financialExpense) ?>;
        const profitVal = <?= json_encode((float)$financialProfit) ?>;

        new Chart(finCtx, {
            type: 'bar',
            data: {
                labels: ['Income', 'Expense', 'Profit'],
                datasets: [{
                    data: [incomeVal, expenseVal, profitVal],
                    backgroundColor: [
                        '#0F172A', // Dark Navy for Income
                        '#EF4444', // Red/Coral for Expense
                        '#10B981'  // Emerald Green for Profit
                    ],
                    borderRadius: 4,
                    borderSkipped: false,
                    maxBarThickness: 55,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0F172A',
                        titleColor: '#FFFFFF',
                        bodyColor: '#FFFFFF',
                        titleFont: { size: 13, weight: '700' },
                        bodyFont: { size: 13, weight: '800' },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return ' ₹ ' + Number(context.raw).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            color: '#475569',
                            font: { size: 13, weight: '700' },
                            padding: 10
                        }
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: 40000,
                        grid: {
                            color: '#F1F5F9',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#94A3B8',
                            font: { size: 11, weight: '600' },
                            padding: 8,
                            callback: function(val) {
                                if (val >= 100000) {
                                    return '₹' + (val / 100000).toFixed(1) + 'L';
                                } else if (val >= 1000) {
                                    return '₹' + (val / 1000).toFixed(0) + 'K';
                                }
                                return '₹' + val;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Schools Overview Donut Chart
    const schoolCanvas = document.getElementById('schoolsOverviewDonut');
    if (schoolCanvas && typeof Chart !== 'undefined') {
        const schoolCtx = schoolCanvas.getContext('2d');
        const labels = <?= json_encode($donutLabels) ?>;
        const counts = <?= json_encode($donutCounts) ?>;

        new Chart(schoolCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: [
                        '#0F172A', // Dark navy
                        '#2563EB', // Blue
                        '#FF6702', // Orange (if extra school)
                        '#10B981'  // Green (if extra school)
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 26,
                            boxHeight: 14,
                            padding: 20,
                            color: '#1E293B',
                            font: { size: 12, weight: '700' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0F172A',
                        titleColor: '#FFFFFF',
                        bodyColor: '#FFFFFF',
                        titleFont: { size: 13, weight: '700' },
                        bodyFont: { size: 12, weight: '700' },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const val = context.raw || 0;
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return ' ' + val + ' Orders (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

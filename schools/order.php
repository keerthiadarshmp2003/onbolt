<?php
/**
 * OnBolt Digital Experience Platform - School Uniform Ordering Portal
 * 4-Step Interactive Wizard: 
 * 1. SELECT CATEGORY (with configurable class selection per Admin Panel)
 * 2. CHOOSE PRODUCTS
 * 3. PARENT DETAILS
 * 4. VERIFY ORDER
 * 
 * URL: /schools/order.php or /schools/{school-slug}/order/
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDbConnection();

// Resolve active category / school
$categorySlug = trim($_GET['category'] ?? $_GET['school'] ?? '');
if (empty($categorySlug) && !empty($_SESSION['parent_school_id'])) {
    $sessionSchool = getSchoolById((int)$_SESSION['parent_school_id']);
    if ($sessionSchool) {
        $categorySlug = $sessionSchool['slug'];
    }
}

$isExplicitCategory = !empty($categorySlug);
if (empty($categorySlug)) {
    $categorySlug = 'international-school';
}

$school = getSchoolBySlug($categorySlug);
if (!$school) {
    $school = $pdo->query("SELECT * FROM schools WHERE status = 1 ORDER BY id ASC LIMIT 1")->fetch();
}

$schoolId = (int)($school['id'] ?? 1);
$hasSubSchools = (int)($school['has_sub_schools'] ?? 0);
$parentId = !empty($school['parent_id']) ? (int)$school['parent_id'] : null;

// Determine category / sub-school list dynamically based on admin configuration:
$subSchoolUnitLabel = 'School';
if ($hasSubSchools) {
    // This is a Main School Group (e.g. Global Public School). Fetch all active sub-schools:
    $subSchoolUnitLabel = !empty($school['sub_school_label']) ? trim($school['sub_school_label']) : 'School';
    $subStmt = $pdo->prepare("SELECT id, name, slug, code, location, city, logo FROM schools WHERE parent_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC");
    $subStmt->execute([$school['id']]);
    $categoriesList = $subStmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($parentId) {
    // A Sub-School was selected. Fetch sister sub-schools under the same parent group:
    $parentStmt = $pdo->prepare("SELECT sub_school_label FROM schools WHERE id = ?");
    $parentStmt->execute([$parentId]);
    $parentGroupLabel = $parentStmt->fetchColumn();
    if (!empty($parentGroupLabel)) {
        $subSchoolUnitLabel = trim($parentGroupLabel);
    }
    $subStmt = $pdo->prepare("SELECT id, name, slug, code, location, city, logo FROM schools WHERE parent_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC");
    $subStmt->execute([$parentId]);
    $categoriesList = $subStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Unique / Standalone School (e.g. Trivandrum International School) -> no sub-schools
    $categoriesList = [$school];
}

// School Code for (schoolcode)number Order ID Format
$schoolOrderCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $school['code'] ?? ''));
if (empty($schoolOrderCode) && !empty($parentId)) {
    $parentCodeStmt = $pdo->prepare("SELECT code FROM schools WHERE id = ?");
    $parentCodeStmt->execute([$parentId]);
    $parentRawCode = $parentCodeStmt->fetchColumn() ?: '';
    $schoolOrderCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $parentRawCode));
}
if (empty($schoolOrderCode)) {
    $schoolOrderCode = 'TIS';
}

// Authentication Gate: Require Parent Login with Demo Password TRINS01 or GPS01 before reaching products
if (!isParentLoggedIn($schoolId)) {
    if (!loginParentByLoginId('TRINS01', $schoolId)) {
        header("Location: " . BASE_URL . "/schools/{$school['slug']}/login/?prompt=1");
        exit;
    }
}

// Fetch products for this school with sizes
$schoolProducts = getProductsForSchool($schoolId);
if (empty($schoolProducts)) {
    // If this is a main group, fallback to first sub-school's products or School 1
    if (!empty($categoriesList)) {
        $schoolProducts = getProductsForSchool((int)$categoriesList[0]['id']);
    } else {
        $schoolProducts = getProductsForSchool(1);
    }
}

// If user is authenticated as parent, pre-fill parent & student details
$parent = isParentLoggedIn($schoolId) ? getLoggedInParent() : null;
$student = null;
if ($parent) {
    $stuList = getStudentsForParent((int)$parent['id']);
    if (!empty($stuList)) {
        $student = $stuList[0];
    }
}

// Initial start step based on admin hierarchy configuration:
// if school has sub-schools: first select school (Step 1), then only go to product!
// if not (standalone unique school or sub-school already selected): directly go to product (Step 2)!
if ($hasSubSchools) {
    $initialStep = 1;
} else {
    $initialStep = 2;
}

$pageTitle = 'Order Uniforms | ' . sanitize($school['name']) . ' | OnBolt';
$pageDesc = 'Official uniform ordering portal for ' . sanitize($school['name']) . '. Select approved uniform items, configure sizes, and order securely via WhatsApp.';
$activeSchoolSlug = $school['slug'];
$activeNav = 'schools';
$hideSiteHeader = true;

// Release session lock early for high-concurrency performance
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ==================== STYLES FOR THE 4-STEP WIZARD ==================== -->
<style>
  .site-header {
    display: none !important;
  }

  :root {
    --wizard-accent: #FF6702;
    --wizard-accent-hover: #e05800;
    --wizard-dark: #1E2530;
    --wizard-surface: #ffffff;
    --wizard-bg: #F8FAFC;
    --wizard-border: #E2E8F0;
    --wizard-muted: #64748B;
  }

  .wizard-wrapper {
    min-height: 100vh;
    background: var(--wizard-bg);
    padding-top: 35px;
    padding-bottom: 80px;
    font-family: 'Urbanist', var(--font-sans), sans-serif;
  }

  /* Support Banner in Header */
  .portal-support-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    border: 1px solid var(--wizard-border);
    border-radius: var(--radius-lg);
    padding: 0.85rem 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
  }

  /* Stepper Progress Bar - Pixel-Perfect Center Alignment */
  .stepper-container {
    background: #ffffff;
    border: 1px solid var(--wizard-border);
    border-radius: var(--radius-xl);
    padding: 2rem 2.5rem;
    margin-bottom: 2.5rem;
    box-shadow: var(--shadow-sm);
  }

  .stepper-nav {
    display: flex;
    position: relative;
    max-width: 860px;
    margin: 0 auto;
    width: 100%;
  }

  /* Connecting Track Line: Spans precisely between center of Node 1 (12.5%) and center of Node 4 (87.5%) */
  .stepper-track-bg {
    position: absolute;
    top: 19px;
    left: 12.5%;
    width: 75%;
    height: 3px;
    background: var(--wizard-border);
    transform: translateY(-50%);
    z-index: 1;
    border-radius: 3px;
    overflow: hidden;
  }

  .stepper-progress-fill {
    height: 100%;
    background: var(--wizard-accent);
    transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 3px;
  }

  .step-node {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 3;
    background: transparent;
    border: none;
    cursor: pointer;
    font-family: inherit;
    padding: 0;
  }

  .step-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid var(--wizard-border);
    color: var(--wizard-muted);
    font-weight: 800;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-sizing: border-box;
  }

  .step-node.active .step-circle {
    border-color: var(--wizard-accent);
    background: var(--wizard-accent);
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(255, 103, 2, 0.2);
  }

  .step-node.completed .step-circle {
    border-color: #22C55E;
    background: #22C55E;
    color: #ffffff;
  }

  .step-label {
    margin-top: 0.75rem;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--wizard-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    transition: color 0.3s ease;
    text-align: center;
    white-space: nowrap;
  }

  .step-node.active .step-label {
    color: var(--wizard-dark);
    font-weight: 800;
  }

  /* Wizard Step Cards */
  .wizard-step {
    display: none;
  }

  .wizard-step.active {
    display: block;
    animation: fadeIn 0.35s ease forwards;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .step-header-box {
    margin-bottom: 2rem;
  }

  .step-counter-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(255, 103, 2, 0.1);
    color: var(--wizard-accent);
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius-pill);
    margin-bottom: 0.65rem;
  }

  /* School Selection Cards (Step 1: Exactly 3 visible per row, others wrap to next line) */
  .category-choice-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
    margin-bottom: 2rem;
  }

  @media (max-width: 900px) {
    .category-choice-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
    }
  }

  @media (max-width: 600px) {
    .category-choice-grid {
      grid-template-columns: 1fr;
    }
  }

  .category-choice-card {
    background: #ffffff;
    border: 2px solid var(--wizard-border);
    border-radius: var(--radius-xl);
    padding: 1.35rem 1.25rem;
    cursor: pointer;
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    box-shadow: var(--shadow-sm);
    min-height: 82px;
  }

  .category-choice-card:hover {
    border-color: rgba(255, 103, 2, 0.6);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
  }

  .category-choice-card.selected {
    border-color: var(--wizard-accent);
    background: #FFF9F5;
    box-shadow: 0 0 0 3px rgba(255, 103, 2, 0.18);
  }

  .category-choice-card .radio-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid var(--wizard-border);
    display: flex;
    align-items: center;
    justify-content: center;
    position: absolute;
    top: 1.1rem;
    right: 1.1rem;
    transition: all 0.2s ease;
  }

  .category-choice-card.selected .radio-indicator {
    border-color: var(--wizard-accent);
  }

  .category-choice-card.selected .radio-indicator::after {
    content: '';
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--wizard-accent);
  }

  .category-school-title {
    font-size: 0.94rem;
    font-weight: 800;
    margin: 0;
    color: var(--wizard-dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.015em;
    line-height: 1.3;
  }

  /* Class Selection Section */
  .class-selection-box {
    background: #ffffff;
    border: 2px solid var(--wizard-border);
    border-radius: var(--radius-xl);
    padding: 1.75rem 2rem;
    margin-top: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
  }

  .class-pills-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.25rem;
  }

  .class-pill-chip {
    padding: 0.75rem 1.4rem;
    border-radius: var(--radius-pill);
    border: 2px solid var(--wizard-border);
    background: #ffffff;
    color: var(--wizard-dark);
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .class-pill-chip:hover {
    border-color: var(--wizard-accent);
    color: var(--wizard-accent);
    background: rgba(255, 103, 2, 0.04);
  }

  .class-pill-chip.selected {
    border-color: var(--wizard-accent);
    background: var(--wizard-accent);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(255, 103, 2, 0.3);
  }

  /* Product Catalog Cards (Step 2) */
  .products-order-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.75rem;
    margin-bottom: 3.5rem;
    align-items: start;
  }

  .product-order-card {
    background: #ffffff;
    border: 2px solid var(--wizard-border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    position: relative;
    height: fit-content;
    align-self: start;
  }

  .product-order-card:hover {
    box-shadow: var(--shadow-lg);
    border-color: rgba(255, 103, 2, 0.4);
  }

  .product-order-card.selected {
    border-color: var(--wizard-accent);
    box-shadow: 0 0 0 3px rgba(255, 103, 2, 0.18);
  }

  .product-media-wrap {
    aspect-ratio: 1/1;
    background: #F1F5F9;
    position: relative;
    overflow: hidden;
  }

  .product-media-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
  }

  .product-order-card:hover .product-media-wrap img {
    transform: scale(1.05);
  }

  .product-card-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
  }

  .product-top-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
  }

  .product-item-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--wizard-dark);
    margin: 0;
    line-height: 1.3;
  }

  .size-chart-trigger-btn {
    background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
    border: 1.5px solid #FF8A3D;
    border-radius: var(--radius-pill);
    padding: 0.28rem 0.75rem;
    font-size: 0.76rem;
    font-weight: 800;
    color: #EA580C;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 6px rgba(255, 103, 2, 0.14);
  }

  .size-chart-trigger-btn:hover {
    border-color: #FF6702;
    color: #FFFFFF;
    background: #FF6702;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 103, 2, 0.3);
  }

  @media (max-width: 860px) {
    .size-chart-modal-grid {
      grid-template-columns: 1fr !important;
    }
  }

  .product-select-toggle-btn {
    width: 100%;
    padding: 0.65rem 1rem;
    border-radius: var(--radius-md);
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    margin-top: 1rem;
    background: #ffffff;
    border: 1.5px solid var(--wizard-border);
    color: var(--wizard-dark);
  }

  .product-select-toggle-btn:hover {
    border-color: var(--wizard-accent);
    color: var(--wizard-accent);
  }

  .product-order-card.selected .product-select-toggle-btn {
    background: rgba(34, 197, 94, 0.12);
    border-color: #22c55e;
    color: #15803d;
  }

  .product-configuration-panel {
    display: none;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--wizard-border);
  }

  .product-order-card.selected .product-configuration-panel {
    display: block;
    animation: fadeIn 0.25s ease forwards;
  }

  .size-pills-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.85rem;
  }

  .size-chip {
    padding: 0.35rem 0.65rem;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--wizard-border);
    background: #ffffff;
    color: var(--wizard-dark);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .size-chip:hover {
    border-color: var(--wizard-accent);
  }

  .size-chip.active {
    background: var(--wizard-accent);
    border-color: var(--wizard-accent);
    color: #ffffff;
  }

  .size-status-msg {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    padding: 0.15rem 0.45rem;
    border-radius: var(--radius-sm);
    display: inline-block;
  }

  .size-status-msg.error {
    color: #DC2626;
    background: #FEF2F2;
    border: 1px solid #FCA5A5;
  }

  .size-status-msg.ok {
    color: #16A34A;
    background: #F0FDF4;
    border: 1px solid #BBF7D0;
  }

  .product-order-card.missing-size-alert {
    border-color: #DC2626 !important;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2) !important;
  }

  /* Payment Modal & Button Styling */
  .btn-pay-now {
    background: #FF6702;
    color: #ffffff;
    border: none;
    border-radius: var(--radius-pill);
    padding: 0.85rem 1.85rem;
    font-size: 1rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    box-shadow: 0 4px 14px rgba(255, 103, 2, 0.35);
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .btn-pay-now:hover {
    background: #E55A00;
    box-shadow: 0 6px 18px rgba(255, 103, 2, 0.45);
    transform: translateY(-1px);
  }
  .pay-tab-btn {
    padding: 0.65rem 0.4rem;
    border: 1.5px solid var(--wizard-border);
    border-radius: var(--radius-md);
    background: #ffffff;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--wizard-dark);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
  }
  .pay-tab-btn:hover {
    border-color: var(--wizard-accent);
  }
  .pay-tab-btn.active {
    border-color: var(--wizard-accent);
    background: #FFF9F5;
    color: var(--wizard-accent);
  }
  .upi-app-chip {
    padding: 0.4rem 0.85rem;
    border: 1.5px solid var(--wizard-border);
    border-radius: var(--radius-pill);
    background: #ffffff;
    cursor: pointer;
    font-size: 0.82rem;
    transition: all 0.2s ease;
  }
  .upi-app-chip:hover {
    border-color: var(--wizard-accent);
  }
  .upi-app-chip.active {
    border-color: var(--wizard-accent);
    background: #FFF9F5;
    color: var(--wizard-accent);
  }

  .qty-stepper-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #F8FAFC;
    border: 1px solid var(--wizard-border);
    border-radius: var(--radius-md);
    padding: 0.35rem 0.5rem;
  }

  .stepper-btn {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--wizard-border);
    background: #ffffff;
    color: var(--wizard-dark);
    font-weight: 800;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .stepper-btn:hover {
    background: var(--wizard-accent);
    color: #ffffff;
    border-color: var(--wizard-accent);
  }

  .stepper-val {
    font-weight: 800;
    font-size: 0.95rem;
    color: var(--wizard-dark);
  }

  .wizard-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid var(--wizard-border);
    border-radius: var(--radius-xl);
    padding: 1.25rem 2rem;
    margin-top: 2rem;
    box-shadow: var(--shadow-lg);
  }

  .form-grid-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
  }

  @media (max-width: 768px) {
    .form-grid-two-col {
      grid-template-columns: 1fr;
    }
  }

  .verify-order-card {
    background: #ffffff;
    border: 1.5px solid var(--wizard-border);
    border-radius: var(--radius-xl);
    padding: 2.5rem;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
  }

  .order-meta-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--wizard-border);
    margin-bottom: 1.75rem;
  }

  .order-id-badge {
    font-family: monospace;
    font-weight: 800;
    font-size: 1.15rem;
    background: rgba(255, 103, 2, 0.1);
    color: var(--wizard-accent);
    padding: 0.4rem 0.9rem;
    border-radius: var(--radius-md);
    letter-spacing: 0.05em;
  }

  .order-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
  }

  .order-items-table th {
    background: #F8FAFC;
    padding: 0.85rem 1rem;
    text-align: left;
    font-weight: 800;
    font-size: 0.85rem;
    color: var(--wizard-dark);
    border-bottom: 2px solid var(--wizard-border);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .order-items-table td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--wizard-border);
    font-size: 0.92rem;
    color: var(--wizard-dark);
  }

  /* Inline Quantity Edit Pencil Button & Editable Box */
  .btn-pencil-qty {
    border: none;
    background: rgba(255, 103, 2, 0.1);
    color: #FF6702;
    border-radius: 6px;
    padding: 3px 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .btn-pencil-qty:hover {
    background: #FF6702;
    color: #ffffff;
    transform: scale(1.12);
  }
  .inline-edit-qty-input {
    width: 52px;
    height: 32px;
    padding: 2px 6px;
    border: 2px solid #FF6702;
    border-radius: 6px;
    font-weight: 800;
    font-size: 0.95rem;
    text-align: center;
    background: #ffffff;
    color: #1E293B;
    outline: none;
    box-shadow: 0 0 0 3px rgba(255, 103, 2, 0.18);
  }
  .inline-edit-qty-input::-webkit-inner-spin-button,
  .inline-edit-qty-input::-webkit-outer-spin-button {
    opacity: 1;
  }

  /* Cancel / Delete Cross (X) Button */
  .btn-check-qty {
    border: none;
    background: #10B981;
    color: #ffffff;
    border-radius: 6px;
    width: 24px;
    height: 24px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .btn-check-qty:hover {
    background: #059669;
    transform: scale(1.1);
  }

  .btn-cancel-item {
    border: none;
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    line-height: 1;
  }
  .btn-cancel-item:hover {
    background: #EF4444;
    color: #ffffff;
    transform: scale(1.15);
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.35);
  }

  .btn-whatsapp-order {
    background: #25D366;
    color: #ffffff;
    border: none;
    padding: 0.9rem 1.8rem;
    border-radius: var(--radius-pill);
    font-weight: 800;
    font-size: 1.05rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35);
    transition: all 0.2s ease;
    text-decoration: none;
  }

  .btn-whatsapp-order:hover {
    background: #1eb956;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 211, 102, 0.45);
  }

  .btn-pdf-download {
    background: #ffffff;
    border: 2px solid var(--wizard-dark);
    color: var(--wizard-dark);
    padding: 0.85rem 1.6rem;
    border-radius: var(--radius-pill);
    font-weight: 800;
    font-size: 0.95rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
  }

  .btn-pdf-download:hover {
    background: var(--wizard-dark);
    color: #ffffff;
  }

  /* Seasonal Bulk Deadline Banner */
  .seasonal-deadline-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
    border: 1.5px solid #FDBA74;
    border-radius: var(--radius-lg);
    padding: 0.9rem 1.4rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.08);
    flex-wrap: wrap;
    gap: 0.75rem;
  }
  .deadline-wa-btn {
    background: #16A34A;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 800;
    padding: 0.45rem 1rem;
    border-radius: var(--radius-pill);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25);
    transition: all 0.2s ease;
  }
  .deadline-wa-btn:hover {
    background: #15803D;
    color: #ffffff;
  }

  /* Selection Control Bar (Diagram 1: Grade & Gender) */
  .selection-control-bar {
    background: #ffffff;
    border: 2px solid var(--wizard-border);
    border-radius: var(--radius-xl);
    padding: 1.15rem 1.6rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.25rem;
    box-shadow: var(--shadow-sm);
  }
  .selection-control-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
  }
  .selection-control-label {
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--wizard-muted);
    letter-spacing: 0.05em;
  }
  .gender-toggle-group {
    display: inline-flex;
    background: #F1F5F9;
    padding: 3px;
    border-radius: var(--radius-pill);
    border: 1px solid var(--wizard-border);
  }
  .gender-btn {
    border: none;
    background: transparent;
    padding: 0.45rem 1.1rem;
    border-radius: var(--radius-pill);
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--wizard-dark);
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }
  .gender-btn:hover {
    color: var(--wizard-accent);
  }
  .gender-btn.active {
    background: #ffffff;
    color: var(--wizard-accent);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  /* Uniform Group Section Banners */
  .uniform-group-banner {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 2.25rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--wizard-border);
    flex-wrap: wrap;
    gap: 1rem;
  }
  .group-tag {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.6rem;
    border-radius: var(--radius-pill);
    display: inline-block;
    margin-bottom: 0.35rem;
  }
  .group-tag.required {
    background: #FEF3C7;
    color: #B45309;
    border: 1px solid #FCD34D;
  }
  .group-tag.optional {
    background: #E0F2FE;
    color: #0369A1;
    border: 1px solid #BAE6FD;
  }
  .group-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--wizard-dark);
    margin: 0;
  }
  .group-desc {
    font-size: 0.86rem;
    color: var(--wizard-muted);
    margin: 0.2rem 0 0;
  }
  .btn-full-set {
    background: #FF6702;
    color: #ffffff;
    border: none;
    border-radius: var(--radius-pill);
    padding: 0.55rem 1.25rem;
    font-size: 0.85rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(255, 103, 2, 0.25);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
  }
  .btn-full-set:hover {
    background: #e05800;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(255, 103, 2, 0.35);
  }

  /* Sticky Cart / Summary Bar */
  .sticky-order-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(30, 37, 48, 0.95);
    backdrop-filter: blur(10px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 0.85rem 1.5rem;
    z-index: 1000;
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.2);
    display: none;
  }
  .sticky-order-inner {
    max-width: 1080px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
  }
  .sticky-order-stats {
    display: flex;
    align-items: center;
    gap: 1.25rem;
  }
  .sticky-badge {
    background: rgba(255, 103, 2, 0.25);
    color: #FF6702;
    border: 1px solid #FF6702;
    font-size: 0.82rem;
    font-weight: 800;
    padding: 0.3rem 0.8rem;
    border-radius: var(--radius-pill);
  }
  .sticky-pricing-label {
    font-size: 0.72rem;
    color: #94A3B8;
    text-transform: uppercase;
    font-weight: 700;
    display: block;
  }
  .sticky-pricing-val {
    font-size: 1.35rem;
    font-weight: 900;
    color: #ffffff;
  }

  /* PIN Code Validation Indicator */
  .pincode-validation-msg {
    margin-top: 0.4rem;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.35rem 0.65rem;
    border-radius: var(--radius-sm);
  }
  .pincode-validation-msg.ok {
    background: #F0FDF4;
    color: #16A34A;
    border: 1px solid #BBF7D0;
  }
  .pincode-validation-msg.warn {
    background: #FFFBEB;
    color: #D97706;
    border: 1px solid #FDE68A;
  }
  .pincode-validation-msg.error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FCA5A5;
  }

  /* Inline Size Select in Step 4 */
  .inline-size-select {
    padding: 0.35rem 0.65rem;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--wizard-border);
    font-weight: 800;
    font-size: 0.85rem;
    background: #ffffff;
    color: var(--wizard-dark);
    cursor: pointer;
  }
  .inline-size-select:focus {
    border-color: var(--wizard-accent);
    outline: none;
  }

  /* ==================== ORDER WIZARD MOBILE OPTIMIZATIONS (320px - 768px) ==================== */
  .verify-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
  }

  @media (max-width: 768px) {
    .wizard-wrapper {
      padding-top: 15px;
      padding-bottom: 40px;
    }
    .portal-support-bar {
      flex-direction: column;
      align-items: stretch;
      gap: 0.85rem;
      padding: 0.85rem 1rem;
    }
    .portal-support-bar > div:last-child {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }
    .verify-meta-grid {
      grid-template-columns: 1fr !important;
      gap: 1rem !important;
    }
    .order-meta-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
    }
    .order-meta-header div:last-child {
      text-align: left !important;
    }
  }

  @media (max-width: 640px) {
    .stepper-container {
      padding: 1rem 0.6rem;
      margin-bottom: 1.5rem;
      border-radius: var(--radius-lg);
    }
    .step-circle {
      width: 32px;
      height: 32px;
      font-size: 0.8rem;
    }
    .stepper-track-bg {
      top: 16px;
    }
    .step-label {
      margin-top: 0.35rem;
      font-size: 0.65rem;
      line-height: 1.1;
      max-width: 60px;
      letter-spacing: 0;
      white-space: normal;
    }
    .products-order-grid {
      grid-template-columns: 1fr !important;
      gap: 1.25rem !important;
      align-items: start !important;
    }
    .uniform-group-banner {
      flex-direction: column;
      align-items: stretch !important;
      gap: 0.85rem !important;
    }
    .academic-gender-filters {
      width: 100%;
      justify-content: flex-start;
      flex-wrap: wrap;
    }
    .wizard-action-bar {
      padding: 1rem;
      flex-direction: column-reverse;
      gap: 0.75rem;
      align-items: stretch;
    }
    .wizard-action-bar .btn,
    .wizard-action-bar .btn-pay-now {
      width: 100% !important;
      justify-content: center;
    }
    .wizard-action-bar > div {
      width: 100%;
      justify-content: space-between;
    }
    .size-chart-modal-grid {
      grid-template-columns: 1fr !important;
      gap: 1rem !important;
    }
    .product-top-row {
      flex-wrap: wrap;
      gap: 0.4rem;
    }
    .size-btn {
      min-width: 38px;
      min-height: 38px;
      font-size: 0.85rem;
    }
    .step-header-box h1 {
      font-size: 1.65rem !important;
    }
  }

  @media (max-width: 380px) {
    .step-label {
      display: none;
    }
    .step-node.active .step-label {
      display: block;
      font-size: 0.62rem;
    }
  }
</style>

<div class="wizard-wrapper">
    <div class="container" style="max-width: 1180px;">

        <!-- Header Support Contact Widget -->
        <div class="portal-support-bar">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 1.3rem;">⚡</span>
                <div>
                    <span style="font-weight: 800; color: var(--brand-dark); font-size: 0.95rem;">Official School Uniform Requisition Portal</span>
                    <span style="font-size: 0.8rem; color: var(--wizard-muted); display: block;">Approved patterns, standardized stitching &amp; institutional delivery</span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="tel:+918848670739" style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 800; color: var(--wizard-dark); text-decoration: none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    <span>+91 88486 70739</span>
                </a>
                <a href="https://wa.me/918848670739" target="_blank" rel="noopener" style="background: rgba(37,211,102,0.12); color: #16a34a; font-weight: 800; font-size: 0.8rem; padding: 0.35rem 0.8rem; border-radius: var(--radius-pill); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span>WhatsApp Desk</span>
                </a>
                <a href="<?= BASE_URL ?>/schools/track-order.php" target="_blank" style="background: rgba(255,103,2,0.12); color: #FF6702; font-weight: 800; font-size: 0.8rem; padding: 0.35rem 0.8rem; border-radius: var(--radius-pill); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span>📦 Track Order</span>
                </a>
            </div>
        </div>

        <!-- 4-Step Progress Navigation (Node 1 is SELECT CATEGORY) -->
        <div class="stepper-container">
            <div class="stepper-nav">
                <!-- Precision Connecting Track -->
                <div class="stepper-track-bg">
                    <div class="stepper-progress-fill" id="stepper-progress" style="width: <?= (($initialStep - 1) / 3) * 100 ?>%;"></div>
                </div>

                <div class="step-node <?= ($initialStep === 1) ? 'active' : 'completed' ?>" id="node-step-1" onclick="<?= ($hasSubSchools || !empty($parentId)) ? 'goToStep(1)' : "window.location.href='" . BASE_URL . "/schools/'" ?>">
                    <div class="step-circle"><?= ($initialStep > 1) ? '✓' : '1' ?></div>
                    <span class="step-label">Select <?= ($hasSubSchools || !empty($parentId)) ? sanitize($subSchoolUnitLabel) : 'School' ?></span>
                </div>

                <div class="step-node <?= ($initialStep === 2) ? 'active' : '' ?>" id="node-step-2" onclick="goToStep(2)">
                    <div class="step-circle">2</div>
                    <span class="step-label">Choose Products</span>
                </div>

                <div class="step-node" id="node-step-3" onclick="goToStep(3)">
                    <div class="step-circle">3</div>
                    <span class="step-label">Details</span>
                </div>

                <div class="step-node" id="node-step-4" onclick="goToStep(4)">
                    <div class="step-circle">4</div>
                    <span class="step-label">Verify Order</span>
                </div>
            </div>
        </div>

        <!-- ==================== STEP 1: SELECT <?= strtoupper(sanitize($subSchoolUnitLabel)) ?> ==================== -->
        <div class="wizard-step <?= ($initialStep === 1) ? 'active' : '' ?>" id="wizard-step-1">
            <div class="step-header-box">
                <span class="step-counter-badge">Step 1 of 4</span>
                <h1 class="display-2" style="font-size: 2.2rem; margin-bottom: 0.5rem;">Select <?= sanitize($subSchoolUnitLabel) ?></h1>
                <p style="color: var(--wizard-muted); font-size: 1.05rem;">
                    Choose your <?= strtolower(sanitize($subSchoolUnitLabel)) ?> to proceed with the uniform requisition.
                </p>
            </div>

            <!-- School Cards Grid (One line titles, no icon thumbnail, no bottom badge) -->
            <div class="category-choice-grid">
                <?php foreach ($categoriesList as $cat): ?>
                    <?php 
                        $isSelected = ($cat['id'] == $schoolId);
                    ?>
                    <div class="category-choice-card <?= $isSelected ? 'selected' : '' ?>" 
                         id="cat-card-<?= $cat['id'] ?>"
                         data-id="<?= $cat['id'] ?>"
                         data-slug="<?= sanitize($cat['slug']) ?>"
                         data-name="<?= sanitize($cat['name']) ?>"
                         data-requires-class="0"
                         data-code="<?= sanitize($cat['code'] ?? '') ?>"
                         data-uniform-mode="<?= sanitize($cat['uniform_mode'] ?? 'common') ?>"
                         data-classes="<?= sanitize($cat['applicable_classes'] ?? '') ?>"
                         onclick="selectCategory(<?= $cat['id'] ?>, '<?= sanitize($cat['slug']) ?>', 0, this)">
                        
                        <div class="radio-indicator"></div>

                        <div style="padding-right: 28px;">
                            <h3 class="category-school-title" title="<?= sanitize($cat['name']) ?>"><?= sanitize($cat['name']) ?></h3>
                            <span style="font-size: 0.78rem; color: var(--wizard-muted); display: block; margin-top: 4px; font-weight: 600;"><?= sanitize($cat['location'] ?: $cat['city']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="wizard-action-bar">
                <a href="<?= BASE_URL ?>/schools/" class="btn btn-outline" style="text-decoration:none;">
                    &larr; Back
                </a>
                <button type="button" class="btn btn-primary btn-lg" id="step1-next-btn" onclick="handleStep1Next()">
                    <span>Next &rarr;</span>
                </button>
            </div>
        </div>

        <!-- ==================== STEP 2: CHOOSE PRODUCTS ==================== -->
        <div class="wizard-step <?= ($initialStep === 2) ? 'active' : '' ?>" id="wizard-step-2">
            
            <!-- School & Class Confirmation Ribbon (Class badge only shown when school has class-wise selection) -->
            <div style="background: #ffffff; border: 1.5px solid var(--wizard-border); border-radius: var(--radius-lg); padding: 0.85rem 1.4rem; margin-bottom: 2rem; display: flex; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-sm);">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size: 1.25rem;">🏫</span>
                        <div>
                            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: var(--wizard-muted); display: block;">Selected School</span>
                            <strong style="color: var(--wizard-dark); font-size: 0.95rem;" id="summary-category-name"><?= sanitize($school['name']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="step-header-box" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span class="step-counter-badge">Step 2 of 4</span>
                    <h1 class="display-2" style="font-size: 2.2rem; margin-bottom: 0.5rem;">Choose your products</h1>
                    <p style="color: var(--wizard-muted); font-size: 1.05rem;">Select approved uniform garments, choose sizes, and specify quantities.</p>
                </div>
                <button type="button" class="btn btn-outline btn-sm open-size-chart-btn" style="border-radius: var(--radius-pill); border: 1.5px solid #FF8A3D; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); color: #EA580C; font-weight: 800; box-shadow: 0 2px 8px rgba(255,103,2,0.12); display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h20M7 12v-3M12 12v-5M17 12v-3"/><rect x="2" y="7" width="20" height="10" rx="2"/></svg>
                    <span>Full Sizing Chart</span>
                </button>
            </div>

            <?php
            // Helper function to render product cards
            $renderCard = function($prod) {
                $grp = strtolower($prod['uniform_group'] ?? 'academic');
                $isAccessory = ($grp === 'accessories') || preg_match('/\b(belt|socks|sock|cap|tie|bag|badge)\b/i', $prod['name']);
                $needsSize = !$isAccessory && !empty($prod['available_sizes']) && trim($prod['available_sizes']) !== 'NONE';
            ?>
                <div class="product-order-card" 
                     id="card-prod-<?= $prod['id'] ?>" 
                     data-product-id="<?= $prod['id'] ?>" 
                     data-product-name="<?= sanitize($prod['name']) ?>" 
                     data-product-price="<?= $prod['effective_price'] ?>" 
                     data-product-image="<?= BASE_URL ?>/<?= sanitize($prod['featured_image']) ?>"
                     data-gender="<?= sanitize($prod['gender'] ?? 'all') ?>"
                     data-group="<?= sanitize($prod['uniform_group'] ?? 'academic') ?>"
                     data-has-size="<?= $needsSize ? '1' : '0' ?>"
                     data-sizes="<?= $needsSize ? sanitize($prod['available_sizes'] ?? '24,26,28,30,32,34,36') : '' ?>">
                    <div class="product-media-wrap">
                        <img src="<?= BASE_URL ?>/<?= sanitize($prod['featured_image']) ?>" alt="<?= sanitize($prod['name']) ?>" loading="lazy" decoding="async">
                    </div>
                    <div class="product-card-body">
                        <div class="product-top-row">
                            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: var(--wizard-muted); letter-spacing: 0.05em;">
                                <?= sanitize($prod['category_name'] ?: 'School Uniform') ?>
                                <?php if (($prod['gender'] ?? 'all') === 'boy'): ?>
                                    <span style="color:#0284C7; font-weight:800; margin-left:0.25rem;">(Boy)</span>
                                <?php elseif (($prod['gender'] ?? 'all') === 'girl'): ?>
                                    <span style="color:#D946EF; font-weight:800; margin-left:0.25rem;">(Girl)</span>
                                <?php endif; ?>
                            </span>
                            <?php if ($needsSize): ?>
                            <button type="button" class="size-chart-trigger-btn open-size-chart-btn" data-category="<?= sanitize($prod['category_name']) ?>" title="Inspect size diagram & body measurements">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M2 12h20M7 12v-3M12 12v-5M17 12v-3"/><rect x="2" y="7" width="20" height="10" rx="2"/></svg>
                                <span>Size Guide</span>
                            </button>
                            <?php endif; ?>
                        </div>

                        <h3 class="product-item-title"><?= sanitize($prod['name']) ?></h3>
                        <div style="font-size: 1.15rem; font-weight: 800; color: var(--wizard-accent); margin-top: 0.4rem;">
                            <?= formatCurrency($prod['effective_price']) ?>
                        </div>

                        <!-- Selection Toggle Button -->
                        <button type="button" class="product-select-toggle-btn" onclick="toggleProductSelection(<?= $prod['id'] ?>)">
                            <span class="select-btn-label">Select</span>
                        </button>

                        <!-- Size & Quantity Configuration Drawer -->
                        <div class="product-configuration-panel">
                            <?php if ($needsSize): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                    <span style="font-size: 0.75rem; font-weight: 800; color: var(--wizard-muted); text-transform: uppercase;">Available Sizes:</span>
                                    <span class="size-status-msg error" id="size-status-<?= $prod['id'] ?>" style="display: none;">Size didn't select</span>
                                </div>
                                <div class="size-pills-row">
                                    <?php 
                                        $sizesArray = !empty($prod['available_sizes']) ? array_map('trim', explode(',', $prod['available_sizes'])) : ['24', '26', '28', '30', '32', '34', '36'];
                                        foreach ($sizesArray as $sz): 
                                    ?>
                                        <button type="button" class="size-chip" onclick="pickSize(<?= $prod['id'] ?>, '<?= sanitize($sz) ?>', this)">
                                            <?= sanitize($sz) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" id="selected-size-<?= $prod['id'] ?>" value="">
                            <?php else: ?>
                                <input type="hidden" id="selected-size-<?= $prod['id'] ?>" value="">
                            <?php endif; ?>

                            <div style="display: flex; justify-content: space-between; align-items: center; <?= $needsSize ? 'margin-top: 0.75rem;' : '' ?>">
                                <span style="font-size: 0.75rem; font-weight: 800; color: var(--wizard-muted); text-transform: uppercase;">Quantity:</span>
                                <div class="qty-stepper-box">
                                    <button type="button" class="stepper-btn" onclick="adjustProductQty(<?= $prod['id'] ?>, -1)">&minus;</button>
                                    <span class="stepper-val" id="qty-val-<?= $prod['id'] ?>" style="min-width: 28px; text-align: center;">1</span>
                                    <button type="button" class="stepper-btn" onclick="adjustProductQty(<?= $prod['id'] ?>, 1)">&plus;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            };

            // Separate products into the 3 specification groups
            $academicList = [];
            $sportsList = [];
            $accessoriesList = [];

            foreach ($schoolProducts as $p) {
                $grp = $p['uniform_group'] ?? 'academic';
                if ($grp === 'sports') {
                    $sportsList[] = $p;
                } elseif ($grp === 'accessories') {
                    $accessoriesList[] = $p;
                } else {
                    $academicList[] = $p;
                }
            }
            ?>

            <?php 
                $backStep1Action = ($hasSubSchools || !empty($parentId)) ? 'goToStep(1)' : "window.location.href='" . BASE_URL . "/schools/'";
            ?>
            <!-- 1. ACADEMIC UNIFORM SET (Required) & Top Step Navigation -->
            <div class="uniform-group-banner" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.75rem; margin-bottom: 1.25rem; padding-bottom: 0.85rem; border-bottom: 2px solid var(--wizard-border); flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-outline" onclick="<?= $backStep1Action ?>" style="border-radius: var(--radius-pill); padding: 0.65rem 1.4rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                        &larr; Back
                    </button>
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                            <h2 class="group-title" style="margin: 0;">Academic Uniforms</h2>
                            
                            <!-- Gender Filter Checkbox Buttons (ALL / BOY / GIRL) -->
                            <div class="academic-gender-filters" style="display: inline-flex; align-items: center; gap: 4px; background: #F1F5F9; padding: 3px 6px; border-radius: 9999px; border: 1.5px solid #E2E8F0;">
                                <label class="gender-filter-opt active" id="gender-btn-all" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 0.8rem; font-weight: 800; letter-spacing: 0.04em; user-select: none; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); background: #ffffff; color: #FF6702; border: 1.5px solid #FF6702; box-shadow: 0 2px 6px rgba(255, 103, 2, 0.16);">
                                    <input type="checkbox" id="chk-gender-all" checked onchange="handleGenderFilter('all')" style="cursor: pointer; accent-color: #FF6702; width: 14px; height: 14px; margin: 0;">
                                    <span>ALL</span>
                                </label>
                                <label class="gender-filter-opt" id="gender-btn-boy" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 0.8rem; font-weight: 800; letter-spacing: 0.04em; user-select: none; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); background: transparent; color: #4B5563; border: 1.5px solid transparent;">
                                    <input type="checkbox" id="chk-gender-boy" onchange="handleGenderFilter('boy')" style="cursor: pointer; accent-color: #FF6702; width: 14px; height: 14px; margin: 0;">
                                    <span>BOY</span>
                                </label>
                                <label class="gender-filter-opt" id="gender-btn-girl" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 0.8rem; font-weight: 800; letter-spacing: 0.04em; user-select: none; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); background: transparent; color: #4B5563; border: 1.5px solid transparent;">
                                    <input type="checkbox" id="chk-gender-girl" onchange="handleGenderFilter('girl')" style="cursor: pointer; accent-color: #FF6702; width: 14px; height: 14px; margin: 0;">
                                    <span>GIRL</span>
                                </label>
                            </div>
                        </div>
                        <p class="group-desc" style="margin: 0.2rem 0 0;">Daily school shirts, trousers, shorts &amp; pinafores approved for <?= sanitize($school['name']) ?>.</p>
                    </div>
                </div>
                <button type="button" class="btn btn-primary" onclick="goToStep(3)" style="border-radius: var(--radius-pill); padding: 0.7rem 1.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; box-shadow: 0 4px 14px rgba(255, 103, 2, 0.35);">
                    <span>Next &rarr;</span>
                </button>
            </div>

            <div class="products-order-grid" id="grid-academic">
                <?php foreach ($academicList as $prod): ?>
                    <?php $renderCard($prod); ?>
                <?php endforeach; ?>
            </div>

            <!-- 2. SPORTS & HOUSE DRESS (Optional / School Activity) -->
            <div class="uniform-group-banner">
                <div>
                    <span class="group-tag optional">Activity Dress</span>
                    <h2 class="group-title">Sports Dress &amp; House Uniforms</h2>
                    <p class="group-desc">Physical education track pants, sports shorts, and house-colored tees.</p>
                </div>
            </div>

            <div class="products-order-grid" id="grid-sports">
                <?php foreach ($sportsList as $prod): ?>
                    <?php $renderCard($prod); ?>
                <?php endforeach; ?>
            </div>

            <!-- 3. ACCESSORIES & SOCKS (Belt, Socks, Cap) -->
            <div class="uniform-group-banner">
                <div>
                    <span class="group-tag optional">Essentials</span>
                    <h2 class="group-title">Accessories &amp; Socks</h2>
                    <p class="group-desc">Official branded belt, caps, and approved rib socks.</p>
                </div>
            </div>

            <div class="products-order-grid" id="grid-accessories">
                <?php foreach ($accessoriesList as $prod): ?>
                    <?php $renderCard($prod); ?>
                <?php endforeach; ?>
            </div>

            <div class="wizard-action-bar">
                <button type="button" class="btn btn-outline" onclick="<?= $backStep1Action ?>">
                    &larr; Back
                </button>
                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <div style="text-align: right;">
                        <span style="font-size: 0.75rem; color: var(--wizard-muted); text-transform: uppercase; font-weight: 800; display: block;">Items Selected</span>
                        <strong style="color: var(--wizard-accent); font-size: 1.1rem;" id="step-two-items-summary">0 items</strong>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" onclick="goToStep(3)">
                        <span>Next &rarr;</span>
                    </button>
                </div>
            </div>

            <!-- Sticky Cart Bar (Diagram 1: Visible on phones & desktop) -->
            <div class="sticky-order-bar" id="sticky-order-bar">
                <div class="sticky-order-inner">
                    <div class="sticky-order-stats">
                        <span class="sticky-badge" id="sticky-item-count">0 items</span>
                        <div>
                            <span class="sticky-pricing-label">Cart Subtotal:</span>
                            <span class="sticky-pricing-val" id="sticky-total-price">₹0.00</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" onclick="goToStep(3)" style="box-shadow: 0 4px 14px rgba(255,103,2,0.4);">
                        <span>Proceed to Details &rarr;</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== STEP 3: DETAILS ==================== -->
        <div class="wizard-step" id="wizard-step-3">
            <div class="step-header-box">
                <span class="step-counter-badge">Step 3 of 4</span>
                <h1 class="display-2" style="font-size: 2.2rem; margin-bottom: 0.5rem;">Details</h1>
                <p style="color: var(--wizard-muted); font-size: 1.05rem;">Provide student and contact information for uniform delivery and institutional validation.</p>
            </div>

            <div style="background: #ffffff; border: 1.5px solid var(--wizard-border); border-radius: var(--radius-xl); padding: 2.5rem; box-shadow: var(--shadow-sm); margin-bottom: 2rem;">
                <form id="parent-details-form" onsubmit="event.preventDefault(); goToStep(4);">
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--wizard-dark); border-bottom: 1.5px solid var(--wizard-border); padding-bottom: 0.75rem; margin-bottom: 1.5rem;">
                        1. Student Identification
                    </h3>
                    
                    <div class="form-grid-two-col" style="margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Student Full Name <span style="color: var(--wizard-accent);">*</span></label>
                            <input type="text" id="input-student-name" class="form-control" placeholder="Enter student full name" required value="">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Gender <span style="color: var(--wizard-accent);">*</span></label>
                            <div style="display: flex; gap: 12px; margin-top: 4px;">
                                <label class="gender-radio-card active" id="gender-opt-boy" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 16px; border: 2px solid #FF6702; background: #FFF9F5; border-radius: 12px; cursor: pointer; font-weight: 700; transition: all 0.2s ease;">
                                    <input type="radio" name="student_gender" value="Boy" style="accent-color: #FF6702; cursor: pointer;" checked onchange="updateGenderChoice('Boy')">
                                    <span>👦 Boy</span>
                                </label>
                                <label class="gender-radio-card" id="gender-opt-girl" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 16px; border: 2px solid #E2E8F0; background: #ffffff; border-radius: 12px; cursor: pointer; font-weight: 700; transition: all 0.2s ease;">
                                    <input type="radio" name="student_gender" value="Girl" style="accent-color: #FF6702; cursor: pointer;" onchange="updateGenderChoice('Girl')">
                                    <span>👧 Girl</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-two-col" style="margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Class <span style="color: var(--wizard-accent);">*</span></label>
                            <input type="text" id="input-student-class" class="form-control" placeholder="Enter class (e.g. Class 5, Playgroup, LKG, UKG)" required value="">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Grade <span style="color: var(--wizard-accent);">*</span></label>
                            <input type="text" id="input-student-grade" class="form-control" placeholder="Enter grade (e.g. Grade A, Section B, Grade 5)" required value="">
                        </div>
                    </div>

                    <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--wizard-dark); border-bottom: 1.5px solid var(--wizard-border); padding-bottom: 0.75rem; margin-bottom: 1.5rem; margin-top: 2rem;">
                        2. Parent Contact &amp; Delivery Information
                    </h3>

                    <div class="form-grid-two-col" style="margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Parent / Guardian Name <span style="color: var(--wizard-accent);">*</span></label>
                            <input type="text" id="input-parent-name" class="form-control" placeholder="Enter your name" required value="">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Primary WhatsApp Number <span style="color: var(--wizard-accent);">*</span></label>
                            <input type="tel" id="input-mobile" class="form-control" placeholder="Enter your WhatsApp number" required value="">
                            <div style="font-size: 0.75rem; color: var(--wizard-muted); margin-top: 0.25rem;">Used for 1-click WhatsApp order verification and status notifications.</div>
                        </div>
                    </div>

                    <div class="form-grid-two-col" style="margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Alternate Phone (Optional)</label>
                            <input type="tel" id="input-alt-mobile" class="form-control" placeholder="Enter alternate phone number" value="">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Parent Email Address <span style="color: var(--wizard-accent);">*</span></label>
                            <input type="email" id="input-email" class="form-control" placeholder="Enter your email address" required value="">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label" style="font-weight: 700;">Delivery Address <span style="color: var(--wizard-accent);">*</span></label>
                        <textarea id="input-address" class="form-control" rows="2" placeholder="Enter your delivery address" required></textarea>
                    </div>

                    <div class="form-grid-two-col">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">Pincode <span style="color: var(--wizard-accent);">*</span></label>
                            <input type="text" id="input-pincode" class="form-control" placeholder="Enter 6-digit pincode" required maxlength="6" value="" oninput="validatePincode(this.value)">
                            <div id="pincode-validation-msg" class="pincode-validation-msg" style="display: none;"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">City / State <span style="color: var(--wizard-accent);">*</span></label>
                            <input type="text" id="input-district-state" class="form-control" placeholder="Enter your city and state" required value="">
                        </div>
                    </div>
                </form>
            </div>

            <div class="wizard-action-bar">
                <button type="button" class="btn btn-outline" onclick="goToStep(2)">
                    &larr; Back
                </button>
                <button type="button" class="btn btn-primary btn-lg" onclick="saveParentDetailsAndProceed()">
                    <span>Next &rarr;</span>
                </button>
            </div>
        </div>

        <!-- ==================== STEP 4: VERIFY ORDER ==================== -->
        <div class="wizard-step" id="wizard-step-4">
            <div class="step-header-box">
                <span class="step-counter-badge">Step 4 of 4</span>
                <h1 class="display-2" style="font-size: 2.2rem; margin-bottom: 0.5rem;">Verify your order</h1>
                <p style="color: var(--wizard-muted); font-size: 1.05rem;">Review student details and uniform selections before proceeding to secure payment.</p>
            </div>

            <div class="verify-order-card">
                <div class="order-meta-header" style="justify-content: flex-end; margin-bottom: 1.25rem;">
                    <div style="text-align: right;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: var(--wizard-muted); display: block;">Requisition Date</span>
                        <strong style="color: var(--wizard-dark);" id="review-order-date"><?= date('d M Y') ?></strong>
                    </div>
                </div>

                <!-- Student & Delivery Summary Cards -->
                <div class="verify-meta-grid" style="margin-bottom: 2rem;">
                    <div style="background: #F8FAFC; border: 1px solid var(--wizard-border); border-radius: var(--radius-lg); padding: 1.25rem;">
                        <h4 style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: var(--wizard-accent); letter-spacing: 0.06em; margin-bottom: 0.75rem;">Student Profile</h4>
                        <div style="margin-bottom: 0.4rem;">
                            <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">Student Name:</span>
                            <strong id="review-student-name" style="font-size: 1rem; color: var(--wizard-dark);">-</strong>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; margin-bottom: 0.4rem;">
                            <div>
                                <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">Gender:</span>
                                <strong id="review-gender">-</strong>
                            </div>
                            <div>
                                <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">Class:</span>
                                <strong id="review-class">-</strong>
                            </div>
                            <div>
                                <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">Grade:</span>
                                <strong id="review-grade">-</strong>
                            </div>
                        </div>
                        <div>
                            <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">Selected School:</span>
                            <strong id="review-school-name"><?= sanitize($school['name']) ?></strong>
                        </div>
                    </div>

                    <div style="background: #F8FAFC; border: 1px solid var(--wizard-border); border-radius: var(--radius-lg); padding: 1.25rem;">
                        <h4 style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: var(--wizard-accent); letter-spacing: 0.06em; margin-bottom: 0.75rem;">Parent Contact</h4>
                        <div style="margin-bottom: 0.4rem;">
                            <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">Parent Name:</span>
                            <strong id="review-parent-name" style="font-size: 1rem; color: var(--wizard-dark);">-</strong>
                        </div>
                        <div style="margin-bottom: 0.4rem;">
                            <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">WhatsApp Phone:</span>
                            <strong id="review-mobile">-</strong>
                        </div>
                        <div style="margin-bottom: 0.4rem;">
                            <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">Email:</span>
                            <strong id="review-email">-</strong>
                        </div>
                        <div>
                            <span style="color: var(--wizard-muted); font-size: 0.8rem; display: block;">Delivery Address:</span>
                            <strong id="review-address">-</strong>
                        </div>
                    </div>
                </div>

                <!-- Order Items Table -->
                <h4 style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: var(--wizard-dark); letter-spacing: 0.06em; margin-bottom: 0.5rem;">Ordered Uniform Items</h4>
                <div style="overflow-x: auto;">
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Item Name</th>
                                <th>Size</th>
                                <th>Quantity</th>
                                <th style="text-align: right;">Unit Price</th>
                                <th style="text-align: right;">Total</th>
                                <th style="width: 44px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody id="review-items-tbody">
                            <!-- Populated dynamically -->
                        </tbody>
                        <tfoot>
                            <tr style="background: #F8FAFC; font-weight: 800;">
                                <td colspan="3" style="text-align: right; padding: 1rem;">Total Items:</td>
                                <td id="review-total-items-badge" style="padding: 1rem;">0</td>
                                <td style="text-align: right; padding: 1rem;">Estimated Total:</td>
                                <td id="review-total-amount" style="text-align: right; padding: 1rem; color: var(--wizard-accent); font-size: 1.25rem;">₹0</td>
                                <td style="width: 44px;"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Requisition Notice -->
                <div style="background: rgba(255, 103, 2, 0.06); border-radius: var(--radius-md); padding: 1rem; font-size: 0.85rem; color: var(--wizard-dark); line-height: 1.5; border: 1px dashed rgba(255, 103, 2, 0.3);">
                    💡 <strong>Fulfillment &amp; Distribution</strong>: Official uniforms are dispatched to the school campus counter or delivered direct to your home. Confirm your requisition on WhatsApp to receive immediate status alerts and pickup receipts.
                </div>
            </div>

            <!-- Verification Action CTAs -->
            <div class="wizard-action-bar" style="flex-wrap: wrap; gap: 1rem;">
                <button type="button" class="btn btn-outline" onclick="goToStep(2)">
                    &larr; Back to Cart
                </button>

                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <button type="button" class="btn-pay-now" id="verify-pay-btn" onclick="executeGatewayPayment()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                        <span>Verify &amp; Pay Now</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ==================== PORTAL NOTICE / POPUP MODAL ==================== -->
<div class="modal-backdrop" id="portal-alert-modal" style="display:none; z-index:99999;">
    <div class="modal-box" style="max-width: 440px; padding: 2.25rem 2rem; text-align: center; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #E2E8F0; position: relative;">
        <!-- Close button (x) -->
        <button type="button" class="modal-close-btn" onclick="closePortalAlertModal()" aria-label="Close" style="position:absolute; top:1.1rem; right:1.1rem; width:34px; height:34px; border-radius:50%; border:none; background:#F8FAFC; color:#64748B; font-size:1.3rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;">&times;</button>
        
        <!-- Icon container -->
        <div id="portal-alert-icon-box" style="width: 68px; height: 68px; border-radius: 50%; background: #FFF7ED; color: #FF6702; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; box-shadow: 0 0 0 8px rgba(255, 103, 2, 0.08);">
            <svg id="portal-alert-icon" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>

        <!-- Title -->
        <h3 id="portal-alert-title" style="margin: 0 0 0.6rem; font-size: 1.3rem; font-weight: 800; color: #1E2530; letter-spacing: -0.01em;">Selection Required</h3>

        <!-- Message -->
        <p id="portal-alert-message" style="margin: 0 0 1.75rem; font-size: 0.95rem; line-height: 1.55; color: #64748B; white-space: pre-line;">Please select at least one uniform item on Step 2.</p>

        <!-- Action Button -->
        <button type="button" id="portal-alert-btn" onclick="closePortalAlertModal()" style="width: 100%; padding: 0.85rem 1.5rem; background: #FF6702; color: #ffffff; border: none; border-radius: 9999px; font-size: 0.95rem; font-weight: 800; cursor: pointer; box-shadow: 0 4px 14px rgba(255, 103, 2, 0.35); transition: all 0.2s ease;">
            OK
        </button>
    </div>
</div>

<!-- ==================== DELETE CONFIRMATION MODAL ==================== -->
<div class="modal-backdrop" id="portal-confirm-delete-modal" style="display:none; z-index:99999;" onclick="if(event.target===this)closeConfirmDeleteModal();">
    <div class="modal-box" style="max-width: 440px; padding: 2.25rem 2rem; text-align: center; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #E2E8F0; position: relative;">
        <!-- Close button (x) -->
        <button type="button" class="modal-close-btn" onclick="closeConfirmDeleteModal()" aria-label="Close" style="position:absolute; top:1.1rem; right:1.1rem; width:34px; height:34px; border-radius:50%; border:none; background:#F8FAFC; color:#64748B; font-size:1.3rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;">&times;</button>
        
        <!-- Trash / Warning Icon -->
        <div style="width: 68px; height: 68px; border-radius: 50%; background: #FEF2F2; color: #EF4444; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; box-shadow: 0 0 0 8px rgba(239, 68, 68, 0.08);">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                <line x1="10" y1="11" x2="10" y2="17"></line>
                <line x1="14" y1="11" x2="14" y2="17"></line>
            </svg>
        </div>

        <!-- Title -->
        <h3 style="margin: 0 0 0.6rem; font-size: 1.25rem; font-weight: 800; color: #1E2530; letter-spacing: -0.01em;">Remove Item from Order?</h3>

        <!-- Item Confirmation Details -->
        <p id="confirm-delete-item-msg" style="margin: 0 0 1.75rem; font-size: 0.95rem; line-height: 1.55; color: #64748B;">
            Are you sure you want to remove this garment from your requisition?
        </p>

        <!-- Two Buttons: Cancel & Yes, Delete -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <button type="button" class="btn btn-outline" onclick="closeConfirmDeleteModal()" style="padding: 0.8rem 1rem; border-radius: 9999px; font-weight: 700; cursor: pointer; justify-content: center;">
                Cancel
            </button>
            <button type="button" id="btn-confirm-delete-yes" onclick="executeConfirmedDelete()" style="padding: 0.8rem 1rem; background: #EF4444; color: #ffffff; border: none; border-radius: 9999px; font-weight: 800; font-size: 0.95rem; cursor: pointer; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35); transition: all 0.2s ease;">
                Yes, Delete
            </button>
        </div>
    </div>
</div>

<!-- ==================== SIZING GUIDE MODAL ==================== -->
<div class="modal-backdrop" id="portal-size-chart-modal">
    <div class="modal-box" style="max-width: 820px; width: 95%; max-height: 90vh; overflow-y: auto; padding: 1.75rem;">
        <button type="button" class="modal-close-btn" onclick="closeSizeChartModal()" aria-label="Close Modal">&times;</button>
        <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1.25rem;">
            <span style="font-size:2rem;">📐</span>
            <div>
                <h3 class="heading-2" style="font-size:1.35rem; margin:0;">Standard School Uniform Size Chart</h3>
                <p style="font-size:0.85rem; color:var(--wizard-muted); margin:0;">Official academic measurements tailored with let-out seams for growing students.</p>
            </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <h4 style="font-size: 0.88rem; font-weight: 800; color: var(--wizard-accent); text-transform: uppercase; margin-bottom: 0.6rem; letter-spacing: 0.03em;">
                Garment &amp; Body Measurements (Chest, Waist, Height, Age)
            </h4>
            <div style="overflow-x:auto; border: 1.5px solid var(--wizard-border); border-radius: var(--radius-lg); background: #ffffff;">
                <table style="width:100%; border-collapse:collapse; font-size:0.84rem; text-align:left;">
                    <thead>
                        <tr style="background:#F1F5F9; border-bottom:2px solid var(--wizard-border); color:var(--wizard-dark); font-weight:800;">
                            <th style="padding:0.65rem 0.75rem;">Size</th>
                            <th style="padding:0.65rem 0.75rem;">Age / Grade</th>
                            <th style="padding:0.65rem 0.75rem;">Height (cm)</th>
                            <th style="padding:0.65rem 0.75rem;">Chest (in)</th>
                            <th style="padding:0.65rem 0.75rem;">Waist (in)</th>
                            <th style="padding:0.65rem 0.75rem;">Length (in)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom:1px solid var(--wizard-border);"><td style="padding:0.5rem 0.75rem; font-weight:800; color:var(--wizard-accent);">18–20</td><td style="padding:0.5rem 0.75rem;">Pre-KG to LKG (Ages 3–4)</td><td style="padding:0.5rem 0.75rem;">95–105 cm</td><td style="padding:0.5rem 0.75rem;">20"–22"</td><td style="padding:0.5rem 0.75rem;">18"–20"</td><td style="padding:0.5rem 0.75rem;">15"</td></tr>
                        <tr style="border-bottom:1px solid var(--wizard-border);"><td style="padding:0.5rem 0.75rem; font-weight:800; color:var(--wizard-accent);">22–24</td><td style="padding:0.5rem 0.75rem;">UKG to Class 1 (Ages 5–6)</td><td style="padding:0.5rem 0.75rem;">106–118 cm</td><td style="padding:0.5rem 0.75rem;">24"–26"</td><td style="padding:0.5rem 0.75rem;">21"–22"</td><td style="padding:0.5rem 0.75rem;">17"</td></tr>
                        <tr style="border-bottom:1px solid var(--wizard-border);"><td style="padding:0.5rem 0.75rem; font-weight:800; color:var(--wizard-accent);">26–28</td><td style="padding:0.5rem 0.75rem;">Class 2–3 (Ages 7–8)</td><td style="padding:0.5rem 0.75rem;">119–130 cm</td><td style="padding:0.5rem 0.75rem;">28"–30"</td><td style="padding:0.5rem 0.75rem;">23"–24"</td><td style="padding:0.5rem 0.75rem;">19"</td></tr>
                        <tr style="border-bottom:1px solid var(--wizard-border);"><td style="padding:0.5rem 0.75rem; font-weight:800; color:var(--wizard-accent);">30–32</td><td style="padding:0.5rem 0.75rem;">Class 4–5 (Ages 9–10)</td><td style="padding:0.5rem 0.75rem;">131–142 cm</td><td style="padding:0.5rem 0.75rem;">32"–34"</td><td style="padding:0.5rem 0.75rem;">25"–26"</td><td style="padding:0.5rem 0.75rem;">21"</td></tr>
                        <tr style="border-bottom:1px solid var(--wizard-border);"><td style="padding:0.5rem 0.75rem; font-weight:800; color:var(--wizard-accent);">34–36</td><td style="padding:0.5rem 0.75rem;">Class 6–7 (Ages 11–12)</td><td style="padding:0.5rem 0.75rem;">143–155 cm</td><td style="padding:0.5rem 0.75rem;">36"–38"</td><td style="padding:0.5rem 0.75rem;">27"–28"</td><td style="padding:0.5rem 0.75rem;">24"</td></tr>
                        <tr style="border-bottom:1px solid var(--wizard-border);"><td style="padding:0.5rem 0.75rem; font-weight:800; color:var(--wizard-accent);">38–40 / S–M</td><td style="padding:0.5rem 0.75rem;">Class 8–9 (Ages 13–14)</td><td style="padding:0.5rem 0.75rem;">156–168 cm</td><td style="padding:0.5rem 0.75rem;">38"–40"</td><td style="padding:0.5rem 0.75rem;">29"–31"</td><td style="padding:0.5rem 0.75rem;">26"</td></tr>
                        <tr><td style="padding:0.5rem 0.75rem; font-weight:800; color:var(--wizard-accent);">42–46 / L–XXL</td><td style="padding:0.5rem 0.75rem;">Class 10–12 &amp; Senior</td><td style="padding:0.5rem 0.75rem;">169–185 cm</td><td style="padding:0.5rem 0.75rem;">42"–46"</td><td style="padding:0.5rem 0.75rem;">32"–36"</td><td style="padding:0.5rem 0.75rem;">28"–30"</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 0.75rem; display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--wizard-muted);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span>Need sizing assistance? Call OnBolt School Helpdesk: <strong style="color:var(--wizard-dark);">+91 88486 70739</strong></span>
            </div>
        </div>

        <button type="button" class="btn btn-primary btn-sm" style="width: 100%; padding: 0.8rem; font-weight: 800;" onclick="closeSizeChartModal()">Got It, Close Guide</button>
    </div>
</div>

        <button type="button" class="btn btn-primary btn-sm" style="width: 100%; padding: 0.8rem; font-weight: 800;" onclick="closeSizeChartModal()">Got It, Close Guide</button>
    </div>
</div>

<!-- ==================== SECURE ONLINE PAYMENT GATEWAY MODAL ==================== -->
<div class="modal-backdrop" id="portal-payment-modal" style="display:none; z-index:99999;">
    <div class="modal-box" style="max-width: 580px; padding: 0; overflow: hidden; border-radius: var(--radius-xl); border: 1.5px solid var(--wizard-border); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        
        <!-- Modal Top Bar -->
        <div style="background: #1E2530; color: #ffffff; padding: 1.25rem 1.75rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="display:flex; align-items:center; gap:0.65rem;">
                <span style="font-size:1.35rem;">🔒</span>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #ffffff;">Online Payment Gateway</h3>
                    <span style="font-size: 0.75rem; color: #94A3B8;">Secure 256-Bit SSL Payment Gateway</span>
                </div>
            </div>
            <button type="button" onclick="closePaymentModal()" style="background: none; border: none; color: #94A3B8; font-size: 1.8rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <div style="padding: 1.5rem 1.75rem;">

            <!-- Total Amount Summary Bar -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: var(--radius-md); padding: 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <span style="font-size: 0.75rem; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em;">Total Payable Amount:</span>
                    <div style="font-size: 0.82rem; color: #1E2530; font-weight: 600;" id="modal-order-summary-meta">Order Ref: <?= sanitize($schoolOrderCode) ?>XXXXXX</div>
                </div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #FF6702;" id="modal-pay-amount">
                    ₹0.00
                </div>
            </div>

            <!-- Payment Method Navigation Tabs -->
            <div style="font-size: 0.8rem; font-weight: 800; color: #1E2530; text-transform: uppercase; margin-bottom: 0.6rem; letter-spacing: 0.05em;">
                Select Payment Method:
            </div>
            
            <div class="payment-tabs-bar" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.35rem; margin-bottom: 1.25rem;">
                <button type="button" class="pay-tab-btn active" id="tab-btn-upi" onclick="switchPayTab('upi')">
                    <span>📱 UPI</span>
                </button>
                <button type="button" class="pay-tab-btn" id="tab-btn-qr" onclick="switchPayTab('qr')">
                    <span>📷 QR</span>
                </button>
                <button type="button" class="pay-tab-btn" id="tab-btn-card" onclick="switchPayTab('card')">
                    <span>💳 Card</span>
                </button>
                <button type="button" class="pay-tab-btn" id="tab-btn-net" onclick="switchPayTab('net')">
                    <span>🏦 Net Bank</span>
                </button>
                <button type="button" class="pay-tab-btn" id="tab-btn-wallet" onclick="switchPayTab('wallet')">
                    <span>👛 Wallets</span>
                </button>
            </div>

            <!-- Tab 1: UPI -->
            <div class="pay-tab-content active" id="tab-content-upi">
                <div style="font-size: 0.82rem; color: #64748B; margin-bottom: 0.75rem;">Fast instant payment via UPI applications:</div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
                    <div class="upi-app-chip active" onclick="selectUpiApp(this, 'Google Pay')">
                        <strong>Google Pay</strong>
                    </div>
                    <div class="upi-app-chip" onclick="selectUpiApp(this, 'PhonePe')">
                        <strong>PhonePe</strong>
                    </div>
                    <div class="upi-app-chip" onclick="selectUpiApp(this, 'Paytm')">
                        <strong>Paytm</strong>
                    </div>
                    <div class="upi-app-chip" onclick="selectUpiApp(this, 'BHIM')">
                        <strong>BHIM UPI</strong>
                    </div>
                    <div class="upi-app-chip" onclick="selectUpiApp(this, 'CRED')">
                        <strong>CRED</strong>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700;">Or Enter UPI VPA ID</label>
                    <input type="text" id="custom-upi-id" class="form-control" placeholder="username@okhdfcbank / mobile@ybl" style="font-size: 0.9rem; font-weight: 600;">
                    <span style="font-size: 0.75rem; color: #64748B; margin-top: 0.25rem; display: block;">Payment request will be sent to your UPI app for authorization.</span>
                </div>
            </div>

            <!-- Tab 2: Dynamic QR Code -->
            <div class="pay-tab-content" id="tab-content-qr" style="display: none; text-align: center; padding: 0.5rem 0;">
                <div style="display: inline-block; padding: 14px; background: #ffffff; border: 2px dashed #FF6702; border-radius: 14px; margin-bottom: 0.75rem; box-shadow: 0 4px 12px rgba(255,103,2,0.1);">
                    <svg width="140" height="140" viewBox="0 0 100 100" fill="#1E2530">
                        <rect width="100" height="100" fill="#ffffff"/>
                        <path d="M5 5h30v30h-30z M10 10v20h20v-20z M15 15h10v10h-10z"/>
                        <path d="M65 5h30v30h-30z M70 10v20h20v-20z M75 15h10v10h-10z"/>
                        <path d="M5 65h30v30h-30z M10 70v20h20v-20z M15 75h10v10h-10z"/>
                        <rect x="42" y="10" width="8" height="8"/>
                        <rect x="42" y="24" width="8" height="8"/>
                        <rect x="52" y="17" width="8" height="8"/>
                        <rect x="10" y="42" width="8" height="8"/>
                        <rect x="24" y="42" width="8" height="8"/>
                        <rect x="17" y="52" width="8" height="8"/>
                        <rect x="42" y="42" width="16" height="16" fill="#FF6702"/>
                        <rect x="65" y="42" width="8" height="8"/>
                        <rect x="80" y="42" width="12" height="8"/>
                        <rect x="72" y="54" width="8" height="8"/>
                        <rect x="42" y="65" width="8" height="8"/>
                        <rect x="54" y="72" width="12" height="8"/>
                        <rect x="42" y="80" width="8" height="12"/>
                        <rect x="65" y="65" width="10" height="10"/>
                        <rect x="80" y="70" width="12" height="8"/>
                        <rect x="70" y="82" width="22" height="10"/>
                    </svg>
                </div>
                <div style="font-size: 0.88rem; font-weight: 800; color: #1E2530;">Scan with Any UPI App to Pay</div>
                <div style="font-size: 0.78rem; color: #64748B; margin-top: 0.2rem;">GPay &bull; PhonePe &bull; Paytm &bull; BHIM &bull; Any Banking App</div>
            </div>

            <!-- Tab 3: Card -->
            <div class="pay-tab-content" id="tab-content-card" style="display: none;">
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem;">
                    <span style="font-size: 0.8rem; color: #64748B;">Supported Cards:</span>
                    <span style="font-size: 0.75rem; font-weight: 800; background: #EFF6FF; color: #2563EB; padding: 0.2rem 0.5rem; border-radius: 4px;">Visa</span>
                    <span style="font-size: 0.75rem; font-weight: 800; background: #FFF7ED; color: #EA580C; padding: 0.2rem 0.5rem; border-radius: 4px;">Mastercard</span>
                    <span style="font-size: 0.75rem; font-weight: 800; background: #F0FDF4; color: #16A34A; padding: 0.2rem 0.5rem; border-radius: 4px;">RuPay</span>
                </div>
                <div class="form-group" style="margin-bottom: 0.75rem;">
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700;">Card Number</label>
                    <input type="text" id="gateway-card-num" class="form-control" placeholder="•••• •••• •••• ••••" maxlength="19" style="font-family: monospace; font-size: 0.95rem; font-weight: 700;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; font-weight: 700;">Valid Thru</label>
                        <input type="text" id="gateway-card-exp" class="form-control" placeholder="MM/YY" maxlength="5" style="font-weight: 700;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; font-weight: 700;">CVV / CVC</label>
                        <input type="password" id="gateway-card-cvv" class="form-control" placeholder="•••" maxlength="4" style="font-weight: 700;">
                    </div>
                </div>
            </div>

            <!-- Tab 4: Net Banking -->
            <div class="pay-tab-content" id="tab-content-net" style="display: none;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700;">Select Your Bank</label>
                    <select id="gateway-net-bank" class="form-control" style="font-weight: 700;" onchange="currentPayMethod = 'Net Banking (' + this.value + ')'">
                        <option value="HDFC Bank" selected>HDFC Bank</option>
                        <option value="State Bank of India">State Bank of India (SBI)</option>
                        <option value="ICICI Bank">ICICI Bank</option>
                        <option value="Axis Bank">Axis Bank</option>
                        <option value="Federal Bank">Federal Bank</option>
                        <option value="Kotak Mahindra Bank">Kotak Mahindra Bank</option>
                        <option value="Union Bank of India">Union Bank of India</option>
                        <option value="Canara Bank">Canara Bank</option>
                        <option value="Bank of Baroda">Bank of Baroda</option>
                        <option value="Punjab National Bank">Punjab National Bank</option>
                        <option value="IndusInd Bank">IndusInd Bank</option>
                        <option value="IDFC FIRST Bank">IDFC FIRST Bank</option>
                        <option value="Other Bank">Other Indian Commercial Bank</option>
                    </select>
                </div>
                <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.5rem;">
                    You will be redirected to your bank's secure net banking portal to authenticate payment.
                </div>
            </div>

            <!-- Tab 5: Wallets & PayLater -->
            <div class="pay-tab-content" id="tab-content-wallet" style="display: none;">
                <div style="font-size: 0.82rem; color: #64748B; margin-bottom: 0.75rem;">Select Wallet / Credit Line Provider:</div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <div class="upi-app-chip active wallet-app-chip" onclick="selectWalletApp(this, 'PhonePe Wallet')">
                        <strong>PhonePe</strong>
                    </div>
                    <div class="upi-app-chip wallet-app-chip" onclick="selectWalletApp(this, 'Amazon Pay')">
                        <strong>Amazon Pay</strong>
                    </div>
                    <div class="upi-app-chip wallet-app-chip" onclick="selectWalletApp(this, 'MobiKwik')">
                        <strong>MobiKwik</strong>
                    </div>
                    <div class="upi-app-chip wallet-app-chip" onclick="selectWalletApp(this, 'LazyPay')">
                        <strong>LazyPay (Pay Later)</strong>
                    </div>
                </div>
            </div>

            <!-- Submit Gateway Payment Button -->
            <button type="button" class="btn btn-primary" id="btn-submit-gateway-pay" onclick="executeGatewayPayment()" style="width: 100%; margin-top: 1.5rem; padding: 0.95rem; font-size: 1.05rem; font-weight: 800; background: #FF6702; border: none; border-radius: var(--radius-pill); box-shadow: 0 4px 14px rgba(255, 103, 2, 0.35); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <span>Pay <span id="btn-submit-pay-amount">₹0.00</span> Securely</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>

            <div style="text-align: center; margin-top: 0.85rem; font-size: 0.75rem; color: #64748B; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <span>🔒 256-Bit SSL Secured &bull; Instant Payment Verification</span>
            </div>

        </div>

    </div>
</div>



<!-- ==================== WIZARD STATE & CONTROLLER SCRIPT ==================== -->
<script>
    // State Store
    const state = {
        currentStep: <?= $initialStep ?>,
        selectedGender: 'boy',
        selectedCategory: {
            id: <?= $schoolId ?>,
            name: "<?= sanitize($school['name']) ?>",
            slug: "<?= sanitize($school['slug']) ?>",
            requiresClass: 0,
            uniformMode: "common",
            applicableClasses: ""
        },
        selectedClass: "<?= sanitize($student['grade_class'] ?? '') ?>",
        selectedProducts: {}, // { [productId]: { id, name, price, size, quantity, image } }
        parentDetails: {
            parentName: "",
            studentName: "",
            gradeClass: "",
            mobile: "",
            altMobile: "",
            email: "",
            address: "",
            pincode: "",
            districtState: ""
        },
        orderId: null,
        orderDate: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    };

    // ==================== PORTAL POPUP MODAL CONTROLLER ====================
    let portalAlertCallback = null;

    function showPortalAlert(message, title = 'Selection Required', type = 'warning', btnText = 'OK', callback = null) {
        const modal = document.getElementById('portal-alert-modal');
        if (!modal) {
            window._nativeAlert ? window._nativeAlert(message) : alert(message);
            return;
        }
        portalAlertCallback = callback;

        const titleEl = document.getElementById('portal-alert-title');
        const msgEl = document.getElementById('portal-alert-message');
        const btnEl = document.getElementById('portal-alert-btn');
        const iconBox = document.getElementById('portal-alert-icon-box');
        const iconSvg = document.getElementById('portal-alert-icon');

        if (titleEl) titleEl.textContent = title;
        if (msgEl) msgEl.textContent = message;
        if (btnEl) btnEl.textContent = btnText;

        if (iconBox && iconSvg) {
            if (type === 'error') {
                iconBox.style.background = '#FEF2F2';
                iconBox.style.color = '#DC2626';
                iconBox.style.boxShadow = '0 0 0 8px rgba(220, 38, 38, 0.08)';
                iconSvg.innerHTML = '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>';
            } else if (type === 'success') {
                iconBox.style.background = '#F0FDF4';
                iconBox.style.color = '#16A34A';
                iconBox.style.boxShadow = '0 0 0 8px rgba(22, 163, 74, 0.08)';
                iconSvg.innerHTML = '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>';
            } else if (type === 'info') {
                iconBox.style.background = '#EFF6FF';
                iconBox.style.color = '#2563EB';
                iconBox.style.boxShadow = '0 0 0 8px rgba(37, 99, 235, 0.08)';
                iconSvg.innerHTML = '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>';
            } else { // warning / notice
                iconBox.style.background = '#FFF7ED';
                iconBox.style.color = '#FF6702';
                iconBox.style.boxShadow = '0 0 0 8px rgba(255, 103, 2, 0.08)';
                iconSvg.innerHTML = '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>';
            }
        }

        modal.classList.add('open');
        modal.style.display = 'flex';
    }

    function closePortalAlertModal() {
        const modal = document.getElementById('portal-alert-modal');
        if (modal) {
            modal.classList.remove('open');
            modal.style.display = 'none';
        }
        if (typeof portalAlertCallback === 'function') {
            const cb = portalAlertCallback;
            portalAlertCallback = null;
            cb();
        }
    }

    // Intercept native browser alert calls globally on this portal page
    if (!window._nativeAlert) {
        window._nativeAlert = window.alert;
        window.alert = function(msg) {
            showPortalAlert(msg);
        };
    }

    // Navigation Handler
    function goToStep(step) {
        // Enforce Step 2 product selection
        if (step > 2) {
            if (Object.keys(state.selectedProducts).length === 0) {
                showPortalAlert('Please select at least one uniform item on Step 2.', 'Selection Required', 'warning');
                goToStep(2);
                return;
            }

            // Enforce manual size selection for every selected item that requires sizes
            const missingSizeNames = [];
            for (const pid in state.selectedProducts) {
                const item = state.selectedProducts[pid];
                const card = document.getElementById('card-prod-' + pid);
                const hasSize = card ? (card.dataset.hasSize !== '0') : (item.hasSize !== false && item.hasSize !== '0');
                if (hasSize && !item.size) {
                    missingSizeNames.push(item.name);
                    if (card) {
                        card.classList.add('missing-size-alert');
                    }
                    const statusEl = document.getElementById('size-status-' + pid);
                    if (statusEl) {
                        statusEl.style.display = 'inline-block';
                        statusEl.textContent = "Size didn't select";
                        statusEl.className = 'size-status-msg error';
                    }
                }
            }

            if (missingSizeNames.length > 0) {
                showPortalAlert("Size didn't select for: " + missingSizeNames.join(', ') + ".\nPlease select a size before proceeding.", 'Size Required', 'warning');
                return;
            }
        }

        state.currentStep = step;

        // Update Stepper Navigation
        for (let i = 1; i <= 4; i++) {
            const node = document.getElementById('node-step-' + i);
            const stepView = document.getElementById('wizard-step-' + i);
            if (node) {
                node.classList.remove('active', 'completed');
                if (i < step) {
                    node.classList.add('completed');
                    node.querySelector('.step-circle').innerHTML = '✓';
                } else if (i === step) {
                    node.classList.add('active');
                    node.querySelector('.step-circle').innerHTML = i;
                } else {
                    node.querySelector('.step-circle').innerHTML = i;
                }
            }
            if (stepView) {
                stepView.classList.remove('active');
                if (i === step) {
                    stepView.classList.add('active');
                }
            }
        }

        // Update Stepper Progress Bar
        const progressFill = document.getElementById('stepper-progress');
        if (progressFill) {
            const pct = ((step - 1) / 3) * 100;
            progressFill.style.width = pct + '%';
        }

        // Summary ribbon updates: Only display class when school has class-wise selection enabled
        const catNameEl = document.getElementById('summary-category-name');
        if (catNameEl) catNameEl.textContent = state.selectedCategory.name;

        if (step === 4) {
            populateStepFourVerification();
        }

        // Toggle sticky bar based on step
        const stickyBarEl = document.getElementById('sticky-order-bar');
        if (stickyBarEl) {
            stickyBarEl.style.display = (step === 2 && Object.keys(state.selectedProducts).length > 0) ? 'block' : 'none';
        }

        window.scrollTo({ top: 100, behavior: 'smooth' });
    }

    // Step 1: School Selection Handler
    function selectCategory(id, slug, requiresClass, el) {
        document.querySelectorAll('.category-choice-card').forEach(c => c.classList.remove('selected'));
        if (el) el.classList.add('selected');

        state.selectedCategory.id = id;
        state.selectedCategory.slug = slug;
        if (el && el.dataset.name) {
            state.selectedCategory.name = el.dataset.name;
        }
        state.selectedCategory.requiresClass = 0;
        state.selectedCategory.uniformMode = (el && el.dataset.uniformMode) ? el.dataset.uniformMode : 'common';
        state.selectedCategory.applicableClasses = (el && el.dataset.classes) ? el.dataset.classes : '';

        if (el && el.dataset.code) {
            const cleanCode = el.dataset.code.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            if (cleanCode) {
                state.schoolCode = cleanCode;
            }
        }
    }

    // Step 1 Next Button Action
    function handleStep1Next() {
        const currentSchoolSlug = '<?= $school['slug'] ?>';
        if (!state.selectedCategory || !state.selectedCategory.slug || state.selectedCategory.slug === currentSchoolSlug) {
            const selectedEl = document.querySelector('.category-choice-card.selected');
            if (selectedEl && selectedEl.dataset.slug && selectedEl.dataset.slug !== currentSchoolSlug) {
                window.location.href = '<?= BASE_URL ?>/schools/' + selectedEl.dataset.slug + '/order/';
                return;
            }
            showPortalAlert('Please select your <?= strtolower(sanitize($subSchoolUnitLabel)) ?> to proceed.', 'Select <?= sanitize($subSchoolUnitLabel) ?>', 'warning');
            return;
        }

        if (state.selectedCategory.slug !== currentSchoolSlug) {
            window.location.href = '<?= BASE_URL ?>/schools/' + state.selectedCategory.slug + '/order/';
            return;
        }

        goToStep(2);
    }

    // Step 2: Toggle Product Selection
    function toggleProductSelection(prodId) {
        const card = document.getElementById('card-prod-' + prodId);
        const btn = card.querySelector('.product-select-toggle-btn');
        const btnLabel = card.querySelector('.select-btn-label');
        const statusEl = document.getElementById('size-status-' + prodId);
        const sizeInput = document.getElementById('selected-size-' + prodId);
        const hasSize = card ? (card.dataset.hasSize !== '0') : true;

        if (state.selectedProducts[prodId]) {
            delete state.selectedProducts[prodId];
            card.classList.remove('selected', 'missing-size-alert');
            btnLabel.textContent = 'Select';
            if (statusEl) {
                statusEl.style.display = 'none';
            }
        } else {
            const name = card.dataset.productName;
            const price = parseFloat(card.dataset.productPrice);
            const image = card.dataset.productImage;
            const size = hasSize ? (sizeInput ? sizeInput.value.trim() : '') : '';
            const qty = parseInt(document.getElementById('qty-val-' + prodId).textContent, 10) || 1;

            state.selectedProducts[prodId] = {
                id: prodId,
                name: name,
                price: price,
                size: size,
                hasSize: hasSize,
                quantity: qty,
                image: image
            };
            card.classList.add('selected');
            btnLabel.textContent = '✓ Selected';

            // Show size status text only for products that require sizes
            if (hasSize && statusEl) {
                statusEl.style.display = 'inline-block';
                if (!size) {
                    statusEl.textContent = "Size didn't select";
                    statusEl.className = 'size-status-msg error';
                } else {
                    statusEl.textContent = '✓ Size ' + size;
                    statusEl.className = 'size-status-msg ok';
                }
            }
        }

        updateStepTwoActionBar();
    }

    // Pick Size
    function pickSize(prodId, size, el) {
        const card = document.getElementById('card-prod-' + prodId);
        card.querySelectorAll('.size-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('selected-size-' + prodId).value = size;

        card.classList.remove('missing-size-alert');

        const statusEl = document.getElementById('size-status-' + prodId);
        if (statusEl) {
            statusEl.style.display = 'inline-block';
            statusEl.textContent = '✓ Size ' + size;
            statusEl.className = 'size-status-msg ok';
        }

        if (state.selectedProducts[prodId]) {
            state.selectedProducts[prodId].size = size;
        } else {
            // Selecting size directly activates the product selection
            toggleProductSelection(prodId);
        }
    }

    // Adjust Quantity
    function adjustProductQty(prodId, delta) {
        const qtyVal = document.getElementById('qty-val-' + prodId);
        let current = parseInt(qtyVal.textContent, 10) || 1;
        current += delta;
        if (current < 1) current = 1;
        if (current > 15) current = 15;
        qtyVal.textContent = current;

        if (state.selectedProducts[prodId]) {
            state.selectedProducts[prodId].quantity = current;
            updateStepTwoActionBar();
        }
    }

    // Gender Filter Checkbox Button Handler (ALL / BOY / GIRL)
    function handleGenderFilter(targetGender) {
        const chkAll = document.getElementById('chk-gender-all');
        const chkBoy = document.getElementById('chk-gender-boy');
        const chkGirl = document.getElementById('chk-gender-girl');

        let activeGender = 'all';

        if (targetGender === 'all') {
            if (!chkAll || chkAll.checked) {
                if (chkAll) chkAll.checked = true;
            } else {
                chkAll.checked = true;
            }
            if (chkBoy) chkBoy.checked = false;
            if (chkGirl) chkGirl.checked = false;
            activeGender = 'all';
        } else if (targetGender === 'boy') {
            if (chkBoy && chkBoy.checked) {
                if (chkAll) chkAll.checked = false;
                if (chkGirl) chkGirl.checked = false;
                activeGender = 'boy';
            } else {
                // Unchecked BOY -> fallback to ALL
                if (chkAll) chkAll.checked = true;
                if (chkGirl) chkGirl.checked = false;
                activeGender = 'all';
            }
        } else if (targetGender === 'girl') {
            if (chkGirl && chkGirl.checked) {
                if (chkAll) chkAll.checked = false;
                if (chkBoy) chkBoy.checked = false;
                activeGender = 'girl';
            } else {
                // Unchecked GIRL -> fallback to ALL
                if (chkAll) chkAll.checked = true;
                if (chkBoy) chkBoy.checked = false;
                activeGender = 'all';
            }
        }

        // Update button visual styles
        ['all', 'boy', 'girl'].forEach(key => {
            const btn = document.getElementById('gender-btn-' + key);
            const chk = document.getElementById('chk-gender-' + key);
            if (!btn || !chk) return;

            if (chk.checked) {
                btn.classList.add('active');
                btn.style.background = '#ffffff';
                btn.style.color = '#FF6702';
                btn.style.borderColor = '#FF6702';
                btn.style.boxShadow = '0 2px 6px rgba(255, 103, 2, 0.16)';
            } else {
                btn.classList.remove('active');
                btn.style.background = 'transparent';
                btn.style.color = '#4B5563';
                btn.style.borderColor = 'transparent';
                btn.style.boxShadow = 'none';
            }
        });

        if (typeof state !== 'undefined') {
            state.selectedGender = activeGender;
        }

        filterProductsByGender(activeGender);
    }

    // Filter Products by Gender (Hides incompatible cards)
    function filterProductsByGender(gender) {
        const cards = document.querySelectorAll('.product-order-card');
        cards.forEach(card => {
            const cardGender = (card.dataset.gender || 'all').toLowerCase();
            const prodName = (card.dataset.productName || '').toLowerCase();

            if (gender === 'all') {
                card.style.display = 'flex';
            } else if (gender === 'boy') {
                // Boy filter: keep unisex and boy items; hide girl items
                const isGirlItem = cardGender === 'girl' || prodName.includes('girl') || prodName.includes('pinafore') || prodName.includes('skirt');
                if (isGirlItem) {
                    card.style.display = 'none';
                } else {
                    card.style.display = 'flex';
                }
            } else if (gender === 'girl') {
                // Girl filter: keep unisex and girl items; hide boy items
                const isBoyItem = cardGender === 'boy' || prodName.includes('trousers (boys') || prodName.includes('trousers (boy') || (prodName.includes('shorts') && !prodName.includes('sports') && cardGender !== 'all');
                if (isBoyItem) {
                    card.style.display = 'none';
                } else {
                    card.style.display = 'flex';
                }
            }
        });
    }

    // Backward-compatible setGenderFilter wrapper
    function setGenderFilter(gender) {
        handleGenderFilter(gender);
    }

    // Interactive gender selection in Step 3 Details
    function updateGenderChoice(val) {
        state.selectedGender = val;
        const boyCard = document.getElementById('gender-opt-boy');
        const girlCard = document.getElementById('gender-opt-girl');
        if (val === 'Boy') {
            if (boyCard) { boyCard.style.borderColor = '#FF6702'; boyCard.style.background = '#FFF9F5'; }
            if (girlCard) { girlCard.style.borderColor = '#E2E8F0'; girlCard.style.background = '#ffffff'; }
        } else {
            if (boyCard) { boyCard.style.borderColor = '#E2E8F0'; boyCard.style.background = '#ffffff'; }
            if (girlCard) { girlCard.style.borderColor = '#FF6702'; girlCard.style.background = '#FFF9F5'; }
        }
    }

    // Add Full Set (Diagram 1)
    function addFullSet(group) {
        const cards = document.querySelectorAll(`.product-order-card[data-group="${group}"]`);
        let addedCount = 0;
        let missingSizes = 0;

        cards.forEach(card => {
            // Only add visible cards matching active gender
            if (card.style.display !== 'none') {
                const prodId = parseInt(card.dataset.productId, 10);
                if (!state.selectedProducts[prodId]) {
                    toggleProductSelection(prodId);
                    addedCount++;
                }
                const sizeInput = document.getElementById('selected-size-' + prodId);
                if (!sizeInput || !sizeInput.value.trim()) {
                    card.classList.add('missing-size-alert');
                    missingSizes++;
                }
            }
        });

        if (missingSizes > 0) {
            showPortalAlert("Full academic uniform set added!\nPlease select the required size for each highlighted garment before proceeding.", "Academic Set Added", "info");
        }
    }

    // PIN Code Validation (All India PIN Codes Supported)
    function validatePincode(pin) {
        const msgEl = document.getElementById('pincode-validation-msg');
        if (!msgEl) return;
        const cleanPin = pin.trim();
        if (cleanPin.length === 0) {
            msgEl.style.display = 'none';
            return;
        }
        if (/^[1-9][0-9]{5}$/.test(cleanPin)) {
            msgEl.style.display = 'none';
            // Auto-populate City & State if empty
            const cityField = document.getElementById('input-district-state');
            if (cityField && !cityField.value.trim()) {
                fetch(`https://api.postalpincode.in/pincode/${cleanPin}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data && data[0] && data[0].Status === 'Success' && data[0].PostOffice && data[0].PostOffice.length > 0) {
                            const po = data[0].PostOffice[0];
                            if (cityField && !cityField.value.trim()) {
                                cityField.value = `${po.District || po.Name}, ${po.State}`;
                            }
                        }
                    })
                    .catch(() => {});
            }
        } else if (cleanPin.length >= 6) {
            msgEl.style.display = 'block';
            msgEl.className = 'pincode-validation-msg error';
            msgEl.innerHTML = '⚠️ Please enter a valid 6-digit PIN code.';
        } else {
            msgEl.style.display = 'none';
        }
    }

    // Inline Size Update on Step 4 Order Summary (Diagram 2)
    function updateItemSizeInline(prodId, newSize) {
        if (state.selectedProducts[prodId]) {
            state.selectedProducts[prodId].size = newSize;
            const sizeInput = document.getElementById('selected-size-' + prodId);
            if (sizeInput) sizeInput.value = newSize;
            const card = document.getElementById('card-prod-' + prodId);
            if (card) {
                card.querySelectorAll('.size-chip').forEach(c => {
                    c.classList.toggle('active', c.textContent.trim() === newSize);
                });
                const statusEl = document.getElementById('size-status-' + prodId);
                if (statusEl) {
                    statusEl.textContent = '✓ Size ' + newSize;
                    statusEl.className = 'size-status-msg ok';
                }
            }
        }
    }

    // Update Action Bar Summary & Sticky Cart Bar
    function updateStepTwoActionBar() {
        let count = 0;
        let total = 0;
        for (const pid in state.selectedProducts) {
            count += state.selectedProducts[pid].quantity;
            total += state.selectedProducts[pid].price * state.selectedProducts[pid].quantity;
        }
        const summaryText = count + (count === 1 ? ' item' : ' items');
        const summaryEl = document.getElementById('step-two-items-summary');
        if (summaryEl) summaryEl.textContent = summaryText;

        const stickyCountEl = document.getElementById('sticky-item-count');
        const stickyTotalEl = document.getElementById('sticky-total-price');
        const stickyBarEl = document.getElementById('sticky-order-bar');

        if (stickyCountEl) stickyCountEl.textContent = summaryText;
        if (stickyTotalEl) stickyTotalEl.textContent = '₹' + total.toLocaleString('en-IN');

        if (stickyBarEl) {
            if (state.currentStep === 2 && count > 0) {
                stickyBarEl.style.display = 'block';
            } else {
                stickyBarEl.style.display = 'none';
            }
        }
    }

    // Step 3: Save Details and Proceed
    function saveParentDetailsAndProceed() {
        const pName = document.getElementById('input-parent-name').value.trim();
        const sName = document.getElementById('input-student-name').value.trim();
        const sClass = document.getElementById('input-student-class').value.trim();
        const sGrade = document.getElementById('input-student-grade').value.trim();
        const genderRadio = document.querySelector('input[name="student_gender"]:checked');
        const gender = genderRadio ? genderRadio.value : (state.selectedGender || 'Boy');
        const mob = document.getElementById('input-mobile').value.trim();
        const email = document.getElementById('input-email').value.trim();
        const addr = document.getElementById('input-address').value.trim();
        const pin = document.getElementById('input-pincode').value.trim();
        const dist = document.getElementById('input-district-state').value.trim();

        if (!pName || !sName || !sClass || !sGrade || !mob || !email || !addr || !pin) {
            showPortalAlert('Please fill in all mandatory fields (*).', 'Incomplete Form', 'warning');
            return;
        }

        state.parentDetails.parentName = pName;
        state.parentDetails.studentName = sName;
        state.parentDetails.studentGender = gender;
        state.parentDetails.studentClass = sClass;
        state.parentDetails.studentGrade = sGrade;
        state.parentDetails.gradeClass = `${sClass} (${sGrade})`;
        state.parentDetails.mobile = mob;
        state.parentDetails.altMobile = document.getElementById('input-alt-mobile').value.trim();
        state.parentDetails.email = email;
        state.parentDetails.address = addr;
        state.parentDetails.pincode = pin;
        state.parentDetails.districtState = dist;

        goToStep(4);
    }

    // Step 4: Populate Verification
    function populateStepFourVerification() {
        const reviewOrderIdEl = document.getElementById('review-order-id');
        if (reviewOrderIdEl) {
            if (state.orderId) {
                reviewOrderIdEl.textContent = state.orderId;
                reviewOrderIdEl.style.background = '#DCFCE7';
                reviewOrderIdEl.style.color = '#15803D';
                reviewOrderIdEl.style.border = '1px solid #86EFAC';
            } else {
                reviewOrderIdEl.textContent = 'Generated on Payment Success';
                reviewOrderIdEl.style.background = '#FFF7ED';
                reviewOrderIdEl.style.color = '#EA580C';
                reviewOrderIdEl.style.border = '1px dashed #FDBA74';
                reviewOrderIdEl.style.fontSize = '0.78rem';
                reviewOrderIdEl.style.fontWeight = '700';
                reviewOrderIdEl.style.letterSpacing = 'normal';
            }
        }
        document.getElementById('review-order-date').textContent = state.orderDate;

        document.getElementById('review-school-name').textContent = state.selectedCategory.name;
        document.getElementById('review-student-name').textContent = state.parentDetails.studentName || 'Not Provided';
        if (document.getElementById('review-gender')) document.getElementById('review-gender').textContent = state.parentDetails.studentGender || 'Boy';
        if (document.getElementById('review-class')) document.getElementById('review-class').textContent = state.parentDetails.studentClass || '-';
        if (document.getElementById('review-grade')) document.getElementById('review-grade').textContent = state.parentDetails.studentGrade || '-';

        document.getElementById('review-parent-name').textContent = state.parentDetails.parentName || 'Not Provided';
        document.getElementById('review-mobile').textContent = state.parentDetails.mobile || 'Not Provided';
        document.getElementById('review-email').textContent = state.parentDetails.email || 'Not Provided';
        document.getElementById('review-address').textContent = `${state.parentDetails.address}, ${state.parentDetails.pincode} (${state.parentDetails.districtState})`;

        const tbody = document.getElementById('review-items-tbody');
        tbody.innerHTML = '';
        let totalItems = 0;
        let totalAmount = 0;
        let idx = 1;

        const pids = Object.keys(state.selectedProducts);
        if (pids.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center; padding:2.5rem 1rem; color:var(--wizard-muted);">
                        <div style="font-size:2rem; margin-bottom:0.5rem;">🛍️</div>
                        <div style="font-size:1.05rem; font-weight:800; color:var(--wizard-dark); margin-bottom:0.35rem;">No uniform items in your requisition</div>
                        <p style="font-size:0.85rem; margin-bottom:1rem;">All garments have been removed from this order.</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="goToStep(2)">← Go Back to Choose Products</button>
                    </td>
                </tr>
            `;
            document.getElementById('review-total-items-badge').textContent = '0';
            document.getElementById('review-total-amount').textContent = '₹0';
            return;
        }

        for (const pid of pids) {
            const it = state.selectedProducts[pid];
            const lineTotal = it.price * it.quantity;
            totalItems += it.quantity;
            totalAmount += lineTotal;

            const card = document.getElementById('card-prod-' + pid);
            const hasSize = card ? (card.dataset.hasSize !== '0') : (it.hasSize !== false && it.hasSize !== '0' && it.size && it.size !== 'Standard');
            const sizesRaw = card ? card.dataset.sizes : '';
            const sizeList = sizesRaw ? sizesRaw.split(',').map(s => s.trim()).filter(Boolean) : (hasSize ? ['24','26','28','30','32','34','36','38','40','42','44','46'] : []);

            let sizeHtml = '';
            if (hasSize && sizeList.length > 0) {
                let optionsHtml = '';
                sizeList.forEach(sz => {
                    optionsHtml += `<option value="${sz}" ${sz === it.size ? 'selected' : ''}>Size ${sz}</option>`;
                });
                sizeHtml = `
                    <select class="inline-size-select" onchange="updateItemSizeInline(${pid}, this.value)" title="Change size inline">
                        ${optionsHtml}
                    </select>
                `;
            } else {
                sizeHtml = `<span style="color:var(--wizard-muted); font-size:1.1rem; font-weight:700;">—</span>`;
            }

            const tr = document.createElement('tr');
            tr.id = `review-row-${pid}`;
            tr.innerHTML = `
                <td style="color:var(--wizard-muted);">${idx++}</td>
                <td>
                    <div style="font-weight:800; color:var(--wizard-dark);">${it.name}</div>
                    <span style="font-size:0.75rem; color:var(--wizard-muted);">Approved Academic Stitch</span>
                </td>
                <td>
                    ${sizeHtml}
                </td>
                <td>
                    <div style="display:inline-flex; align-items:center; gap:6px;">
                        <span id="qty-text-${pid}" style="font-weight:800; font-size:0.95rem;">${it.quantity}</span>
                        <button type="button" class="btn-pencil-qty" id="pencil-btn-${pid}" onclick="enableInlineQtyEdit(${pid})" title="Edit quantity">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                        </button>
                        <div id="qty-box-wrap-${pid}" style="display:none; align-items:center; gap:4px;">
                            <input type="number" id="inline-qty-input-${pid}" min="1" max="99" value="${it.quantity}" class="inline-edit-qty-input" onkeydown="if(event.key==='Enter')saveInlineQty(${pid}, this.value);" onblur="saveInlineQty(${pid}, this.value)">
                            <button type="button" class="btn-check-qty" onmousedown="event.preventDefault();" onclick="saveInlineQty(${pid}, document.getElementById('inline-qty-input-${pid}').value)" title="Save quantity">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </button>
                        </div>
                    </div>
                </td>
                <td style="text-align:right;">₹${it.price.toLocaleString('en-IN')}</td>
                <td style="text-align:right; font-weight:800; color:var(--wizard-accent);" id="review-row-total-${pid}">₹${lineTotal.toLocaleString('en-IN')}</td>
                <td style="text-align:center; width:44px; vertical-align:middle;">
                    <button type="button" class="btn-cancel-item" onclick="confirmRemoveReviewItem(${pid})" title="Remove ${it.name.replace(/"/g, '&quot;')}">
                        ✕
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        document.getElementById('review-total-items-badge').textContent = totalItems;
        document.getElementById('review-total-amount').textContent = '₹' + totalAmount.toLocaleString('en-IN');
    }

    // Toggle Quantity to Editable Box Mode (Click Pencil Button)
    function enableInlineQtyEdit(pid) {
        const textEl = document.getElementById('qty-text-' + pid);
        const pencilBtn = document.getElementById('pencil-btn-' + pid);
        const boxWrap = document.getElementById('qty-box-wrap-' + pid);
        const inputEl = document.getElementById('inline-qty-input-' + pid);

        if (textEl && pencilBtn && boxWrap && inputEl) {
            textEl.style.display = 'none';
            pencilBtn.style.display = 'none';
            boxWrap.style.display = 'inline-flex';
            inputEl.focus();
            inputEl.select();
        }
    }

    // Save Edited Quantity & Recalculate Totals
    function saveInlineQty(pid, val) {
        if (!state.selectedProducts[pid]) return;
        let qty = parseInt(val, 10);
        if (isNaN(qty) || qty < 1) qty = 1;
        if (qty > 99) qty = 99;

        state.selectedProducts[pid].quantity = qty;

        // Synchronize with Step 2 card input and display
        const cardQtyInput = document.getElementById('qty-' + pid);
        if (cardQtyInput) cardQtyInput.value = qty;
        const cardDisplayQty = document.getElementById('display-qty-' + pid);
        if (cardDisplayQty) cardDisplayQty.textContent = qty;

        // Update Step 4 line total
        const lineTotal = state.selectedProducts[pid].price * qty;
        const rowTotalEl = document.getElementById('review-row-total-' + pid);
        if (rowTotalEl) rowTotalEl.textContent = '₹' + lineTotal.toLocaleString('en-IN');

        const textEl = document.getElementById('qty-text-' + pid);
        const pencilBtn = document.getElementById('pencil-btn-' + pid);
        const boxWrap = document.getElementById('qty-box-wrap-' + pid);
        const inputEl = document.getElementById('inline-qty-input-' + pid);

        if (textEl) {
            textEl.textContent = qty;
            textEl.style.display = 'inline';
        }
        if (pencilBtn) pencilBtn.style.display = 'inline-flex';
        if (boxWrap) boxWrap.style.display = 'none';
        if (inputEl) inputEl.value = qty;

        // Recalculate totals
        let totalItems = 0;
        let grandTotal = 0;
        for (const p in state.selectedProducts) {
            totalItems += state.selectedProducts[p].quantity;
            grandTotal += state.selectedProducts[p].price * state.selectedProducts[p].quantity;
        }
        const badge = document.getElementById('review-total-items-badge');
        if (badge) badge.textContent = totalItems;
        const totalAmountEl = document.getElementById('review-total-amount');
        if (totalAmountEl) totalAmountEl.textContent = '₹' + grandTotal.toLocaleString('en-IN');

        // Sync Step 2 summary badge and sticky bar
        updateStepTwoActionBar();
    }

    // ==================== DELETE CONFIRMATION MODAL CONTROLLER ====================
    let pendingDeletePid = null;

    function confirmRemoveReviewItem(pid) {
        if (!state.selectedProducts[pid]) return;
        pendingDeletePid = pid;
        const it = state.selectedProducts[pid];
        const msgEl = document.getElementById('confirm-delete-item-msg');
        if (msgEl) {
            const hasSize = (it.hasSize !== false && it.hasSize !== '0' && it.size && it.size !== 'Standard');
            const sizeLabel = hasSize ? ` (Size: ${it.size})` : '';
            msgEl.innerHTML = `Are you sure you want to remove <strong>${it.name}</strong>${sizeLabel} from your order requisition?`;
        }
        const modal = document.getElementById('portal-confirm-delete-modal');
        if (modal) {
            modal.classList.add('open');
            modal.style.display = 'flex';
        }
    }

    function closeConfirmDeleteModal() {
        pendingDeletePid = null;
        const modal = document.getElementById('portal-confirm-delete-modal');
        if (modal) {
            modal.classList.remove('open');
            modal.style.display = 'none';
        }
    }

    function executeConfirmedDelete() {
        if (pendingDeletePid) {
            const pid = pendingDeletePid;
            closeConfirmDeleteModal();
            removeReviewItem(pid);
        }
    }

    // Cancel / Delete Product from Requisition (Cross X Button)
    function removeReviewItem(pid) {
        if (!state.selectedProducts[pid]) return;

        // Remove from state
        delete state.selectedProducts[pid];

        // Deselect in Step 2 card
        const card = document.getElementById('card-prod-' + pid);
        if (card) {
            card.classList.remove('selected');
            const selectBtn = card.querySelector('.product-select-toggle-btn');
            if (selectBtn) {
                selectBtn.classList.remove('selected');
                selectBtn.innerHTML = '<span class="select-btn-label">Select</span>';
            }
            card.querySelectorAll('.size-chip').forEach(c => c.classList.remove('active'));
            const hasSize = card.dataset.hasSize !== '0';
            const sizeInput = document.getElementById('selected-size-' + pid);
            if (sizeInput) sizeInput.value = '';
            const sizeStatus = card.querySelector('.size-status-text') || document.getElementById('size-status-' + pid);
            if (sizeStatus) {
                if (hasSize) {
                    sizeStatus.className = 'size-status-msg error';
                    sizeStatus.textContent = "Size didn't select";
                    sizeStatus.style.display = 'none';
                } else {
                    sizeStatus.style.display = 'none';
                }
            }
            const cardQtyInput = document.getElementById('qty-' + pid);
            if (cardQtyInput) cardQtyInput.value = 1;
        }

        // Re-render Step 4 verification table
        populateStepFourVerification();

        // Sync Step 2 summary bar
        updateStepTwoActionBar();
    }

    // Generate Official Vector PDF Invoice
    function generateOrderPdf() {
        if (typeof window.jspdf === 'undefined' || typeof window.jspdf.jsPDF === 'undefined') {
            showPortalAlert('PDF Generator is loading. Please try again in a moment.', 'Loading PDF Generator', 'info');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');

        // Brand Banner Header
        doc.setFillColor(30, 37, 48);
        doc.rect(0, 0, 210, 30, 'F');

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(18);
        doc.setTextColor(255, 103, 2);
        doc.text('ONBOLT', 14, 18);

        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(255, 255, 255);
        doc.text('Complete Branding Solutions & Institutional Uniform Portals', 14, 24);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11);
        doc.setTextColor(255, 255, 255);
        const isPaid = state.paymentStatus === 'Paid';
        if (isPaid) {
            doc.text('ONBOLT - UNIFORM PAYMENT RECEIPT', 196, 17, { align: 'right' });
            doc.setFontSize(9);
            doc.setTextColor(34, 197, 94);
            doc.text('STATUS: PAID (VERIFIED)', 196, 24, { align: 'right' });
        } else {
            doc.setFontSize(12);
            doc.text('OFFICIAL UNIFORM REQUISITION', 196, 18, { align: 'right' });
        }

        // Meta Info
        doc.setTextColor(30, 37, 48);
        doc.setFontSize(10);
        doc.setFont('helvetica', 'bold');
        doc.text('Requisition ID: ' + (state.orderId || 'Issued upon payment confirmation'), 14, 42);
        doc.setFont('helvetica', 'normal');
        doc.text('Date: ' + state.orderDate, 14, 48);
        doc.text('School: ' + state.selectedCategory.name, 14, 54);

        let curY = 60;
        if (isPaid) {
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(34, 197, 94);
            doc.text('Payment Status: PAID', 14, curY);
            curY += 6;
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(30, 37, 48);
            if (state.paymentMethod) {
                doc.text('Payment Method: ' + state.paymentMethod, 14, curY);
                curY += 6;
            }
            if (state.paymentTxnRef) {
                doc.text('Transaction Ref: ' + state.paymentTxnRef, 14, curY);
                curY += 6;
            }
        }

        const profileY = isPaid ? Math.max(curY + 4, 84) : 66;
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(30, 37, 48);
        doc.text('Student Profile:', 14, profileY);
        doc.setFont('helvetica', 'normal');
        doc.text('Name: ' + state.parentDetails.studentName, 14, profileY + 6);
        doc.text('Class: ' + (state.parentDetails.gradeClass || state.selectedClass || 'All Classes'), 14, profileY + 12);

        doc.setFont('helvetica', 'bold');
        doc.text('Parent Details:', 105, profileY);
        doc.setFont('helvetica', 'normal');
        doc.text('Parent Name: ' + state.parentDetails.parentName, 105, profileY + 6);
        doc.text('Mobile: ' + state.parentDetails.mobile, 105, profileY + 12);
        doc.text('Address: ' + state.parentDetails.address + ', ' + state.parentDetails.pincode, 105, profileY + 18);

        // Items Table
        const tableRows = [];
        let itemIndex = 1;
        let grandTotal = 0;
        let totalQty = 0;

        for (const pid in state.selectedProducts) {
            const it = state.selectedProducts[pid];
            const lineTotal = it.price * it.quantity;
            grandTotal += lineTotal;
            totalQty += it.quantity;
            const sizeVal = (it.hasSize !== false && it.size && it.size !== 'Standard') ? it.size : '—';
            tableRows.push([
                itemIndex++,
                it.name,
                sizeVal,
                it.quantity,
                'Rs. ' + it.price.toLocaleString('en-IN'),
                'Rs. ' + lineTotal.toLocaleString('en-IN')
            ]);
        }

        if (typeof doc.autoTable !== 'undefined') {
            doc.autoTable({
                startY: isPaid ? 112 : 96,
                head: [['#', 'Item Description', 'Size', 'Qty', 'Unit Price', 'Total']],
                body: tableRows,
                headStyles: { fillColor: [255, 103, 2], textColor: [255, 255, 255], fontStyle: 'bold' },
                foot: [['', 'Total Items:', '', totalQty, 'Grand Total:', 'Rs. ' + grandTotal.toLocaleString('en-IN')]],
                footStyles: { fillColor: [248, 250, 252], textColor: [30, 37, 48], fontStyle: 'bold' },
                margin: { left: 14, right: 14 },
                theme: 'striped'
            });
        }

        const finalY = doc.lastAutoTable ? doc.lastAutoTable.finalY + 16 : 180;
        doc.setFontSize(8);
        doc.setTextColor(100, 116, 139);
        doc.text('Official Requisition issued by OnBolt Consumer India Desk.', 14, finalY);
        doc.text('Support: +91 88486 70739 / +91 99469 25888 | Email: onboltconsumerindia@gmail.com', 14, finalY + 5);

        doc.save('OnBolt-Uniform-Requisition.pdf');
    }

    // Submit and Send via WhatsApp
    async function submitAndSendWhatsApp() {
        const orderPayload = {
            order_reference: null,
            school_id: state.selectedCategory.id,
            parent_name: state.parentDetails.parentName,
            student_name: state.parentDetails.studentName,
            gender: state.parentDetails.studentGender || 'Boy',
            grade_class: state.parentDetails.gradeClass || state.selectedClass || 'All Classes',
            mobile: state.parentDetails.mobile,
            alt_mobile: state.parentDetails.altMobile,
            email: state.parentDetails.email,
            delivery_address: state.parentDetails.address,
            pincode: state.parentDetails.pincode,
            state_district: state.parentDetails.districtState,
            items: Object.values(state.selectedProducts)
        };

        try {
            fetch('<?= BASE_URL ?>/api/orders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(orderPayload)
            }).catch(e => console.error('Order logging async error:', e));
        } catch (e) {}

        let itemsListText = '';
        let idx = 1;
        let totalItems = 0;
        for (const pid in state.selectedProducts) {
            const it = state.selectedProducts[pid];
            totalItems += it.quantity;
            const sizeStr = (it.hasSize !== false && it.size && it.size !== 'Standard') ? ` | Size: ${it.size}` : '';
            itemsListText += `${idx++}. ${it.name}${sizeStr} | Qty: ${it.quantity}\n`;
        }

        const msgText = 
`*NEW UNIFORM ORDER*
*Order ID:* ${state.orderId || 'Issued upon payment confirmation'}
*Date:* ${state.orderDate}
*School:* ${state.selectedCategory.name}

*STUDENT & PARENT DETAILS*
*Student Name:* ${state.parentDetails.studentName}
*Class / Grade:* ${state.parentDetails.gradeClass || state.selectedClass || 'All Classes'}
*Parent Name:* ${state.parentDetails.parentName}
*Mobile:* ${state.parentDetails.mobile}${state.parentDetails.altMobile ? '\n*Alt Mobile:* ' + state.parentDetails.altMobile : ''}
*Email:* ${state.parentDetails.email}
*Delivery Address:* ${state.parentDetails.address}
*Pincode:* ${state.parentDetails.pincode}
*District & State:* ${state.parentDetails.districtState}

*ORDER ITEMS*
${itemsListText}
*Total Items:* ${totalItems}

Please confirm this order. Thank you!`;

        const waTargetPhone = '918848670739';
        const waUrl = 'https://api.whatsapp.com/send/?phone=' + waTargetPhone + '&text=' + encodeURIComponent(msgText);
        window.open(waUrl, '_blank');
    }

    // ==================== EDVIRON PAYMENT GATEWAY MODAL CONTROLLER ====================
    let currentPayMethod = 'UPI (Google Pay)';

    function openPaymentModal() {
        // Enforce Step 2 product selection
        let totalAmt = 0.0;
        let totalQty = 0;
        for (const pid in state.selectedProducts) {
            const item = state.selectedProducts[pid];
            totalAmt += (parseFloat(item.price) * parseInt(item.quantity, 10));
            totalQty += parseInt(item.quantity, 10);
        }

        if (totalQty === 0) {
            showPortalAlert('Please select at least one uniform item on Step 2.', 'Selection Required', 'warning');
            goToStep(2);
            return;
        }

        const formattedAmt = '₹' + totalAmt.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const modalPayAmt = document.getElementById('modal-pay-amount');
        const modalBtnAmt = document.getElementById('btn-submit-pay-amount');
        const modalMeta = document.getElementById('modal-order-summary-meta');

        if (modalPayAmt) modalPayAmt.textContent = formattedAmt;
        if (modalBtnAmt) modalBtnAmt.textContent = formattedAmt;
        if (modalMeta) modalMeta.textContent = (state.orderId ? 'Order Ref: ' + state.orderId + ' • ' : '') + totalQty + ' Uniform Item' + (totalQty > 1 ? 's' : '') + ' • Secure Gateway Payment';

        const modal = document.getElementById('portal-payment-modal');
        if (modal) {
            modal.classList.add('open');
            modal.style.display = 'flex';
        }
    }

    function closePaymentModal() {
        const modal = document.getElementById('portal-payment-modal');
        if (modal) {
            modal.classList.remove('open');
            modal.style.display = 'none';
        }
    }

    function switchPayTab(tab) {
        document.querySelectorAll('.pay-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.pay-tab-content').forEach(c => c.style.display = 'none');

        const btn = document.getElementById('tab-btn-' + tab);
        const content = document.getElementById('tab-content-' + tab);
        if (btn) btn.classList.add('active');
        if (content) content.style.display = 'block';

        if (tab === 'upi') currentPayMethod = 'UPI (Google Pay)';
        if (tab === 'qr') currentPayMethod = 'QR Code (UPI Scan & Pay)';
        if (tab === 'card') currentPayMethod = 'Credit / Debit Card';
        if (tab === 'net') {
            const bank = document.getElementById('gateway-net-bank') ? document.getElementById('gateway-net-bank').value : 'HDFC Bank';
            currentPayMethod = 'Net Banking (' + bank + ')';
        }
        if (tab === 'wallet') {
            currentPayMethod = 'Wallet (PhonePe Wallet)';
        }
    }

    function selectUpiApp(el, appName) {
        document.querySelectorAll('#tab-content-upi .upi-app-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        currentPayMethod = 'UPI (' + appName + ')';
    }

    function selectWalletApp(el, walletName) {
        document.querySelectorAll('.wallet-app-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        currentPayMethod = 'Wallet (' + walletName + ')';
    }

    async function executeGatewayPayment() {
        // Enforce Step 2 product selection
        const itemsPayload = [];
        let totalAmt = 0.0;
        let totalQty = 0;
        for (const pid in state.selectedProducts) {
            const it = state.selectedProducts[pid];
            totalAmt += (parseFloat(it.price) * parseInt(it.quantity, 10));
            totalQty += parseInt(it.quantity, 10);
            itemsPayload.push({
                product_id: it.id,
                name: it.name,
                size: (it.hasSize !== false && it.size && it.size !== 'Standard') ? it.size : '',
                quantity: it.quantity,
                price: it.price
            });
        }

        if (totalQty === 0) {
            showPortalAlert('Please select at least one uniform item on Step 2.', 'Selection Required', 'warning');
            closePaymentModal();
            goToStep(2);
            return;
        }

        let finalMethod = (typeof currentPayMethod !== 'undefined' && currentPayMethod) ? currentPayMethod : 'UPI';

        const btn = document.getElementById('verify-pay-btn') || document.getElementById('btn-submit-gateway-pay');
        if (!btn) return;

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span>🔒 Connecting to Secure Payment Gateway...</span>';

        const payload = {
            order_reference: null,
            school_id: state.selectedCategory.id,
            parent_name: state.parentDetails.parentName || 'Parent Guardian',
            student_name: state.parentDetails.studentName || 'Student',
            gender: state.parentDetails.studentGender || 'Boy',
            grade_class: state.parentDetails.gradeClass || state.selectedClass || 'Class 5',
            mobile: state.parentDetails.mobile || '9876543210',
            alt_mobile: state.parentDetails.altMobile || '',
            email: state.parentDetails.email || 'parent@example.com',
            delivery_address: state.parentDetails.address || 'Campus Counter Delivery',
            pincode: state.parentDetails.pincode || '',
            state_district: state.parentDetails.districtState || '',
            items: itemsPayload,
            payment_method: finalMethod,
            payment_status: 'Pending'
        };

        try {
            const res = await fetch('<?= BASE_URL ?>/api/orders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();

            if (result && result.success && result.redirect_url) {
                btn.innerHTML = '<span>✓ Redirecting to Secure Payment Gateway...</span>';
                btn.style.background = '#15803D';
                setTimeout(() => {
                    window.location.href = result.redirect_url;
                }, 300);
            } else {
                showPortalAlert(result.error || 'Payment gateway connection error.', 'Payment Gateway', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
                btn.style.background = '';
            }
        } catch (e) {
            console.error('Payment Error:', e);
            showPortalAlert('Unable to reach payment gateway. Please check your network and try again.', 'Gateway Error', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
            btn.style.background = '';
        }
    }

    // Modal Controls
    function openSizeChartModal() {
        const m = document.getElementById('portal-size-chart-modal');
        if (m) {
            m.classList.add('open');
            m.style.display = 'flex';
        }
    }

    function closeSizeChartModal() {
        const m = document.getElementById('portal-size-chart-modal');
        if (m) {
            m.classList.remove('open');
            m.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Ensure all products are visible in product selection
        document.querySelectorAll('.product-order-card').forEach(card => card.style.display = 'flex');

        document.querySelectorAll('.open-size-chart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                openSizeChartModal();
            });
        });

        const alertModal = document.getElementById('portal-alert-modal');
        if (alertModal) {
            alertModal.addEventListener('click', (e) => {
                if (e.target === alertModal) closePortalAlertModal();
            });
        }

        const sizeModal = document.getElementById('portal-size-chart-modal');
        if (sizeModal) {
            sizeModal.addEventListener('click', (e) => {
                if (e.target === sizeModal) closeSizeChartModal();
            });
        }

        const payModal = document.getElementById('portal-payment-modal');
        if (payModal) {
            payModal.addEventListener('click', (e) => {
                if (e.target === payModal) closePaymentModal();
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePortalAlertModal();
                closeSizeChartModal();
                closePaymentModal();
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

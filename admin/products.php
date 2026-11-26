<?php
/**
 * OnBolt Digital Experience Platform - Uniforms & Apparel Catalog Management
 * School-wise Uniform Management: Only shows the selected school's uniforms
 * Strict 500 KB per-image upload limit
 * URL: /admin/products.php
 */

declare(strict_types=1);

$adminActive = 'products';
$adminTitle = 'Uniforms & Apparel Catalog Management';
require_once __DIR__ . '/header.php';

if (!adminCan('products_manage')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to access Uniforms & Apparel.');
}

$pdo = getDbConnection();
$msg = '';
$err = '';

if (!defined('MAX_IMAGE_BYTES')) {
    define('MAX_IMAGE_BYTES', 500 * 1024); // 500 KB strict limit
}

// 1. Fetch all active schools
$allActiveSchools = $pdo->query("SELECT id, name, slug, parent_id, has_sub_schools, sub_school_label, location, city, logo FROM schools WHERE status = 1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

$mainSchools = [];
$subSchoolsByParent = [];
$schoolsById = [];

foreach ($allActiveSchools as $s) {
    $schoolsById[(int)$s['id']] = $s;
    if (empty($s['parent_id'])) {
        $mainSchools[] = $s;
    } else {
        $subSchoolsByParent[(int)$s['parent_id']][] = $s;
    }
}

// 2. Resolve selected school & parent school
$schoolFilter = (int)($_GET['school_id'] ?? 0);
if ($schoolFilter > 0 && isset($schoolsById[$schoolFilter])) {
    $selectedSchoolId = $schoolFilter;
} else {
    $selectedSchoolId = !empty($mainSchools) ? (int)$mainSchools[0]['id'] : 1;
}

$currentSchool = $schoolsById[$selectedSchoolId] ?? reset($schoolsById);
$isSubSchool = !empty($currentSchool['parent_id']);
$parentSchoolId = $isSubSchool ? (int)$currentSchool['parent_id'] : (int)$currentSchool['id'];
$parentSchool = $schoolsById[$parentSchoolId] ?? $currentSchool;
$availableSubSchools = $subSchoolsByParent[$parentSchoolId] ?? [];
$availableSubIds = array_map(function($s) { return (int)$s['id']; }, $availableSubSchools);

// Resolve sub_school_ids (multiple selection)
$rawSubIds = $_GET['sub_school_ids'] ?? null;
if ($rawSubIds !== null) {
    if (is_array($rawSubIds)) {
        $activeSubSchoolIds = array_map('intval', $rawSubIds);
    } else {
        $activeSubSchoolIds = array_filter(array_map('intval', explode(',', (string)$rawSubIds)));
    }
    $activeSubSchoolIds = array_values(array_intersect($activeSubSchoolIds, $availableSubIds));
    if (empty($activeSubSchoolIds) && !empty($availableSubIds)) {
        $activeSubSchoolIds = $availableSubIds;
    }
} elseif ($isSubSchool) {
    $activeSubSchoolIds = [$selectedSchoolId];
} else {
    $activeSubSchoolIds = $availableSubIds;
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM product_categories ORDER BY sort_order ASC")->fetchAll();

// Handle POST actions: Create, Update, Delete Uniform, or School Unlink
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'] ?? $name)));
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        if (empty($sku)) {
            $sku = 'OB-' . strtoupper(bin2hex(random_bytes(3)));
        }
        $categoryId = (int)($_POST['category_id'] ?? 1);
        $schoolId = (int)($_POST['school_id'] ?? $selectedSchoolId);
        $actualPrice = (float)($_POST['actual_price'] ?? 0.00);
        $vendorCommission = (float)($_POST['vendor_commission'] ?? 0.00);
        $price = (float)($_POST['price'] ?? 0.00);
        if ($price <= 0 && $actualPrice > 0) {
            $price = round($actualPrice + $vendorCommission, 2);
        }
        $isMandatory = isset($_POST['is_mandatory']) ? 1 : 0;
        $shortDesc = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isKit = isset($_POST['is_kit']) ? 1 : 0;
        $sizes = trim($_POST['available_sizes'] ?? '24, 26, 28, 30, 32, 34, 36');
        $id = (int)($_POST['id'] ?? 0);

        if (empty($name) || ($price <= 0 && $actualPrice <= 0)) {
            $err = 'Uniform Name, Actual Price, and Vendor Commission are required.';
        } else {
            try {
                // Strict 500 KB Image Upload Validation
                $imagePath = null;
                if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $fileSize = $_FILES['image']['size'];
                    if ($fileSize > MAX_IMAGE_BYTES) {
                        $kb = round($fileSize / 1024, 1);
                        throw new Exception("Uniform image exceeds the strict 500 KB limit (Selected: {$kb} KB). Please choose an image under 500 KB.");
                    }

                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                        throw new Exception("Invalid image format. Allowed formats: PNG, JPG, WEBP.");
                    }

                    $filename = 'uniform_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $targetPath = ROOT_PATH . '/uploads/uniforms/' . $filename;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        $imagePath = 'uploads/uniforms/' . $filename;
                    }
                }

                if ($action === 'create') {
                    $defImage = $imagePath ? ltrim($imagePath, '/\\') : '';
                    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, sku, description, short_description, price, actual_price, vendor_commission, vendor_price, is_kit, featured_image, available_sizes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                    $stmt->execute([$categoryId, $name, $slug, $sku, $description, $shortDesc, $price, $actualPrice, $vendorCommission, $actualPrice, $isKit, $defImage, $sizes]);
                    $newProdId = (int)$pdo->lastInsertId();

                    // Insert size variants
                    $sizeList = array_values(array_filter(array_map('trim', explode(',', $sizes))));
                    $vStmt = $pdo->prepare("INSERT INTO product_variants (product_id, size, stock_quantity, status, created_at) VALUES (?, ?, 50, 1, NOW())");
                    foreach ($sizeList as $sz) {
                        $vStmt->execute([$newProdId, $sz]);
                    }

                    // Assign to this selected school
                    $pdo->prepare("INSERT INTO school_products (school_id, product_id, custom_price, actual_price, vendor_commission, vendor_price, is_mandatory, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), actual_price = VALUES(actual_price), vendor_commission = VALUES(vendor_commission), vendor_price = VALUES(vendor_price), is_mandatory = VALUES(is_mandatory)")
                        ->execute([$schoolId, $newProdId, $price, $actualPrice, $vendorCommission, $actualPrice, $isMandatory]);

                    // If this is a sub-school, also map to its parent school so parent catalog has it
                    $currS = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
                    $currS->execute([$schoolId]);
                    $sInfo = $currS->fetch();
                    if (!empty($sInfo['parent_id'])) {
                        $pdo->prepare("INSERT INTO school_products (school_id, product_id, custom_price, actual_price, vendor_commission, vendor_price, is_mandatory, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), actual_price = VALUES(actual_price), vendor_commission = VALUES(vendor_commission), vendor_price = VALUES(vendor_price), is_mandatory = VALUES(is_mandatory)")
                            ->execute([(int)$sInfo['parent_id'], $newProdId, $price, $actualPrice, $vendorCommission, $actualPrice, $isMandatory]);
                    }

                    clearFastCache();
                    $msg = "SAVED SUCCESSFULLY";
                    logActivity('create_uniform', 'products', (int)$admin['id'], 'admin', "Created uniform {$name} (#{$newProdId}) for school #{$schoolId}");
                } elseif ($action === 'update') {
                    $sql = "UPDATE products SET category_id = ?, name = ?, slug = ?, sku = ?, description = ?, short_description = ?, price = ?, actual_price = ?, vendor_commission = ?, vendor_price = ?, is_kit = ?, available_sizes = ?";
                    $params = [$categoryId, $name, $slug, $sku, $description, $shortDesc, $price, $actualPrice, $vendorCommission, $actualPrice, $isKit, $sizes];

                    if ($imagePath) {
                        $cleanImg = ltrim($imagePath, '/\\');
                        $sql .= ", featured_image = ?";
                        $params[] = $cleanImg;
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $id;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // Update school mapping & pricing on currently selected school
                    $pdo->prepare("INSERT INTO school_products (school_id, product_id, custom_price, actual_price, vendor_commission, vendor_price, is_mandatory, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), actual_price = VALUES(actual_price), vendor_commission = VALUES(vendor_commission), vendor_price = VALUES(vendor_price), is_mandatory = VALUES(is_mandatory)")
                        ->execute([$schoolId, $id, $price, $actualPrice, $vendorCommission, $actualPrice, $isMandatory]);

                    // Propagate to sub-schools or parent school to ensure complete sync
                    $currS = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
                    $currS->execute([$schoolId]);
                    $sInfo = $currS->fetch();

                    if (!empty($sInfo['has_sub_schools'])) {
                        // This is a parent school -> propagate price updates to all its sub-schools that have this product
                        $subIdsStmt = $pdo->prepare("SELECT id FROM schools WHERE parent_id = ?");
                        $subIdsStmt->execute([$schoolId]);
                        $children = $subIdsStmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($children)) {
                            $cPlaceholders = implode(',', array_fill(0, count($children), '?'));
                            $pdo->prepare("UPDATE school_products 
                                           SET custom_price = ?, actual_price = ?, vendor_commission = ?, vendor_price = ? 
                                           WHERE product_id = ? AND school_id IN ($cPlaceholders)")
                                ->execute(array_merge([$price, $actualPrice, $vendorCommission, $actualPrice, $id], $children));
                        }
                    } elseif (!empty($sInfo['parent_id'])) {
                        // This is a sub-school -> also update parent school's school_products entry
                        $pdo->prepare("UPDATE school_products 
                                       SET custom_price = ?, actual_price = ?, vendor_commission = ?, vendor_price = ? 
                                       WHERE product_id = ? AND school_id = ?")
                            ->execute([$price, $actualPrice, $vendorCommission, $actualPrice, $id, (int)$sInfo['parent_id']]);
                    }

                    // Sync size variants
                    if (!empty($sizes)) {
                        $sizeList = array_values(array_filter(array_map('trim', explode(',', $sizes))));
                        if (!empty($sizeList)) {
                            $delPlaceholders = implode(',', array_fill(0, count($sizeList), '?'));
                            $pdo->prepare("DELETE FROM product_variants WHERE product_id = ? AND size NOT IN ($delPlaceholders)")
                                ->execute(array_merge([$id], $sizeList));

                            $existingVariants = $pdo->prepare("SELECT size FROM product_variants WHERE product_id = ?");
                            $existingVariants->execute([$id]);
                            $currentSizes = $existingVariants->fetchAll(PDO::FETCH_COLUMN);

                            $vStmt = $pdo->prepare("INSERT INTO product_variants (product_id, size, stock_quantity, status, created_at) VALUES (?, ?, 50, 1, NOW())");
                            foreach ($sizeList as $sz) {
                                if (!in_array($sz, $currentSizes, true)) {
                                    $vStmt->execute([$id, $sz]);
                                }
                            }
                        }
                    }

                    clearFastCache();
                    $msg = "UPDATED SUCCESSFULLY";
                    logActivity('update_uniform', 'products', (int)$admin['id'], 'admin', "Updated uniform #{$id}");
                }
            } catch (Exception $e) {
                $err = $e->getMessage();
            }
        }
    } elseif ($action === 'quick_update_pricing_sizes') {
        $pId = (int)($_POST['product_id'] ?? 0);
        $actualPrice = (float)($_POST['actual_price'] ?? 0);
        $vendorCommission = (float)($_POST['vendor_commission'] ?? 0);
        $newPrice = (float)($_POST['price'] ?? 0);
        if ($newPrice <= 0 && $actualPrice > 0) {
            $newPrice = round($actualPrice + $vendorCommission, 2);
        }
        $newSizes = trim($_POST['available_sizes'] ?? '');

        if ($pId > 0 && ($newPrice > 0 || $actualPrice > 0)) {
            try {
                $pdo->prepare("UPDATE products SET price = ?, actual_price = ?, vendor_commission = ?, vendor_price = ?, available_sizes = ? WHERE id = ?")
                    ->execute([$newPrice, $actualPrice, $vendorCommission, $actualPrice, $newSizes, $pId]);

                $pdo->prepare("INSERT INTO school_products (school_id, product_id, custom_price, actual_price, vendor_commission, vendor_price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), actual_price = VALUES(actual_price), vendor_commission = VALUES(vendor_commission), vendor_price = VALUES(vendor_price)")
                    ->execute([$selectedSchoolId, $pId, $newPrice, $actualPrice, $vendorCommission, $actualPrice]);

                // Propagate to sub-schools or parent school
                $currS = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
                $currS->execute([$selectedSchoolId]);
                $sInfo = $currS->fetch();

                if (!empty($sInfo['has_sub_schools'])) {
                    $subIdsStmt = $pdo->prepare("SELECT id FROM schools WHERE parent_id = ?");
                    $subIdsStmt->execute([$selectedSchoolId]);
                    $children = $subIdsStmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($children)) {
                        $cPlaceholders = implode(',', array_fill(0, count($children), '?'));
                        $pdo->prepare("UPDATE school_products 
                                       SET custom_price = ?, actual_price = ?, vendor_commission = ?, vendor_price = ? 
                                       WHERE product_id = ? AND school_id IN ($cPlaceholders)")
                            ->execute(array_merge([$newPrice, $actualPrice, $vendorCommission, $actualPrice, $pId], $children));
                    }
                } elseif (!empty($sInfo['parent_id'])) {
                    $pdo->prepare("UPDATE school_products 
                                   SET custom_price = ?, actual_price = ?, vendor_commission = ?, vendor_price = ? 
                                   WHERE product_id = ? AND school_id = ?")
                        ->execute([$newPrice, $actualPrice, $vendorCommission, $actualPrice, $pId, (int)$sInfo['parent_id']]);
                }

                // Sync size variants
                if (!empty($newSizes)) {
                    $sizeList = array_values(array_filter(array_map('trim', explode(',', $newSizes))));
                    if (!empty($sizeList)) {
                        $delPlaceholders = implode(',', array_fill(0, count($sizeList), '?'));
                        $pdo->prepare("DELETE FROM product_variants WHERE product_id = ? AND size NOT IN ($delPlaceholders)")
                            ->execute(array_merge([$pId], $sizeList));

                        $existingVariants = $pdo->prepare("SELECT size FROM product_variants WHERE product_id = ?");
                        $existingVariants->execute([$pId]);
                        $currentSizes = $existingVariants->fetchAll(PDO::FETCH_COLUMN);

                        $vStmt = $pdo->prepare("INSERT INTO product_variants (product_id, size, stock_quantity, status, created_at) VALUES (?, ?, 50, 1, NOW())");
                        foreach ($sizeList as $sz) {
                            if (!in_array($sz, $currentSizes, true)) {
                                $vStmt->execute([$pId, $sz]);
                            }
                        }
                    }
                }

                clearFastCache();
                $msg = "UPDATED SUCCESSFULLY";
                logActivity('quick_update_pricing_sizes', 'products', (int)$admin['id'], 'admin', "Quick updated price/sizes for product #{$pId}");
            } catch (Exception $e) {
                $err = "Update failed: " . $e->getMessage();
            }
        } else {
            $err = "Invalid product ID or price.";
        }
    } elseif ($action === 'unlink_school_product') {
        $spId = (int)$_POST['school_product_id'];
        try {
            // Also clean up sub-school mapping if unlinking from parent school
            $spStmt = $pdo->prepare("SELECT school_id, product_id FROM school_products WHERE id = ?");
            $spStmt->execute([$spId]);
            $spRow = $spStmt->fetch();
            if ($spRow) {
                $curSchId = (int)$spRow['school_id'];
                $pId = (int)$spRow['product_id'];
                $sCheck = $pdo->prepare("SELECT has_sub_schools FROM schools WHERE id = ?");
                $sCheck->execute([$curSchId]);
                $sMeta = $sCheck->fetch();
                if (!empty($sMeta['has_sub_schools'])) {
                    $subIds = $pdo->query("SELECT id FROM schools WHERE parent_id = {$curSchId}")->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($subIds)) {
                        $subP = implode(',', array_fill(0, count($subIds), '?'));
                        $pdo->prepare("DELETE FROM school_products WHERE product_id = ? AND school_id IN ($subP)")
                            ->execute(array_merge([$pId], $subIds));
                    }
                }
            }
            $pdo->prepare("DELETE FROM school_products WHERE id = ?")->execute([$spId]);
            clearFastCache();
            $msg = "Uniform removed from this school's catalog.";
        } catch (Exception $e) {
            $err = "Failed to unlink uniform: " . $e->getMessage();
        }
    }
}

// Current school details are already loaded and verified above
$currSchoolStmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$currSchoolStmt->execute([$selectedSchoolId]);
$currentSchool = $currSchoolStmt->fetch();
if (!$currentSchool) {
    $currentSchool = reset($schoolsById);
    $selectedSchoolId = (int)$currentSchool['id'];
}

// Check edit uniform mode
$editId = (int)($_GET['edit'] ?? 0);
$editProduct = null;
if ($editId > 0) {
    $eStmt = $pdo->prepare("SELECT p.*, sp.school_id, sp.custom_price, sp.is_mandatory 
                           FROM products p 
                           LEFT JOIN school_products sp ON p.id = sp.product_id AND sp.school_id = ?
                           WHERE p.id = ? LIMIT 1");
    $eStmt->execute([$selectedSchoolId, $editId]);
    $editProduct = $eStmt->fetch();
}

// Fetch Uniforms FOR THIS SELECTED SCHOOL ONLY
$search = trim($_GET['search'] ?? '');
$catFilter = (int)($_GET['category'] ?? 0);

// Target schools scope for uniform catalog query
$filterTargetSchoolIds = [];
if (!empty($activeSubSchoolIds)) {
    $filterTargetSchoolIds = $activeSubSchoolIds;
    // If all sub-schools are selected, also include parent school ID
    if (!empty($availableSubIds) && count($activeSubSchoolIds) === count($availableSubIds)) {
        $filterTargetSchoolIds[] = $parentSchoolId;
    }
} else {
    $filterTargetSchoolIds = [$selectedSchoolId];
}
$filterTargetSchoolIds = array_values(array_unique($filterTargetSchoolIds));
$inSchoolPlaceholders = implode(',', array_fill(0, count($filterTargetSchoolIds), '?'));

$querySql = "SELECT sp.id as school_product_id, sp.school_id, sp.custom_price, sp.is_mandatory,
                    COALESCE(sp.actual_price, p.actual_price, p.vendor_price, ROUND(COALESCE(sp.custom_price, p.price) * 0.70, 2)) as actual_price,
                    COALESCE(sp.vendor_commission, p.vendor_commission, ROUND(COALESCE(sp.custom_price, p.price) - COALESCE(sp.actual_price, p.actual_price, p.vendor_price, p.price * 0.70), 2)) as vendor_commission,
                    p.id as product_id, p.name as product_name, p.sku, p.price as base_price,
                    p.featured_image, p.available_sizes, p.is_kit, p.description, c.name as category_name
             FROM school_products sp
             JOIN products p ON sp.product_id = p.id
             LEFT JOIN product_categories c ON p.category_id = c.id
             WHERE sp.school_id IN ($inSchoolPlaceholders)";

$params = $filterTargetSchoolIds;

if ($catFilter > 0) {
    $querySql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}
if (!empty($search)) {
    $querySql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$querySql .= " GROUP BY p.id ORDER BY sp.is_mandatory DESC, p.name ASC";

$stmtUniforms = $pdo->prepare($querySql);
$stmtUniforms->execute($params);
$schoolUniforms = $stmtUniforms->fetchAll();
?>

<style>
/* Clean, Spacious School Header */
.school-hero-card {
    background: #ffffff;
    border-radius: var(--radius-xl);
    border: 1.5px solid var(--brand-border);
    padding: 1.75rem 2.25rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

/* Grid Alignment: Consistent Heights & Spacing */
.uniform-catalog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 1.75rem;
    margin-bottom: 3rem;
}

.uniform-card-editorial {
    background: #ffffff;
    border: 1.5px solid var(--brand-border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    position: relative;
    box-shadow: var(--shadow-sm);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.uniform-card-editorial:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--brand-primary);
}

.uniform-card-img-wrap {
    width: 100%;
    height: 180px;
    background: #F8FAFC;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 1.25rem;
    border: 1px solid var(--brand-border);
    position: relative;
}

.uniform-card-img-wrap img {
    max-height: 155px;
    max-width: 90%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.uniform-card-editorial:hover .uniform-card-img-wrap img {
    transform: scale(1.05);
}

.uniform-pill-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 0.2rem 0.6rem;
    border-radius: var(--radius-pill);
    letter-spacing: 0.05em;
}

.badge-required {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.badge-optional {
    background: rgba(100, 116, 139, 0.1);
    color: #475569;
}

.uniform-card-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--brand-dark);
    margin-bottom: 0.4rem;
    line-height: 1.35;
    min-height: 2.7rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.uniform-card-price-row {
    display: flex;
    align-items: baseline;
    gap: 0.6rem;
    margin-bottom: 0.75rem;
}

.price-main {
    font-family: var(--font-display);
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--brand-primary);
}

.price-strike {
    font-size: 0.85rem;
    color: var(--brand-muted);
    text-decoration: line-through;
}

.uniform-sizes-row {
    font-size: 0.78rem;
    color: var(--brand-muted);
    margin-bottom: 1.25rem;
    line-height: 1.5;
    background: #F8FAFC;
    padding: 0.45rem 0.65rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--brand-border);
}

.uniform-card-actions {
    margin-top: auto;
    display: flex;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--brand-border);
}
</style>

<!-- Alert Notifications -->
<?php if (!empty($msg)): ?>
    <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem 1.25rem; border-radius: var(--radius-md); font-weight: 700; margin-bottom: 2rem;">
        ✓ <?= sanitize($msg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($err)): ?>
    <div style="background: rgba(255, 107, 107, 0.12); color: #dc2626; border: 1px solid rgba(255, 107, 107, 0.3); padding: 1rem 1.25rem; border-radius: var(--radius-md); font-weight: 700; margin-bottom: 2rem;">
        ⚠️ <?= sanitize($err) ?>
    </div>
<?php endif; ?>

<!-- 1. PRIMARY SCHOOL & SUB-SCHOOL / WING SELECTOR BAR -->
<div style="background:#ffffff; border-radius:var(--radius-xl); padding:1.25rem 2rem; border:1.5px solid var(--brand-border); margin-bottom:2rem; box-shadow:var(--shadow-sm); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem;">
    <div style="display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap;">
        <!-- 1. School Group / Main School -->
        <div style="display:flex; align-items:center; gap:0.5rem;">
            <label for="prod_main_school_picker" style="font-size:0.84rem; font-weight:800; color:var(--brand-dark); text-transform:uppercase; letter-spacing:0.04em; margin:0;">
                School:
            </label>
            <select id="prod_main_school_picker" class="form-control" style="font-weight:700; font-size:0.88rem; color:var(--brand-dark); padding:0.5rem 0.85rem; border-radius:var(--radius-md); min-width:230px; border:1.5px solid #CBD5E1; background:#ffffff;" onchange="handleMainSchoolChange(this.value)">
                <option value="" disabled>Select your School</option>
                <?php foreach ($mainSchools as $ms): ?>
                    <option value="<?= $ms['id'] ?>" <?= $parentSchoolId === (int)$ms['id'] ? 'selected' : '' ?>>
                        <?= sanitize($ms['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 2. Sub School Selector with Checkbox Multi-Select -->
        <form action="<?= BASE_URL ?>/admin/products.php" method="GET" id="sub-school-form" style="display:flex; align-items:center; gap:0.5rem; margin:0; position:relative;">
            <input type="hidden" name="school_id" value="<?= $parentSchoolId ?>">
            <?php if ($catFilter > 0): ?>
                <input type="hidden" name="category" value="<?= $catFilter ?>">
            <?php endif; ?>
            <?php if (!empty($search)): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>

            <label style="font-size:0.84rem; font-weight:800; color:var(--brand-dark); text-transform:uppercase; letter-spacing:0.04em; margin:0;">
                Sub School:
            </label>

            <?php if (!empty($availableSubSchools)): ?>
                <?php
                $totalSubCount = count($availableSubSchools);
                $checkedSubCount = count(array_intersect($activeSubSchoolIds, $availableSubIds));
                $isAllSubSelected = ($totalSubCount > 0 && $checkedSubCount === $totalSubCount);

                if ($checkedSubCount === 0) {
                    $subBtnInitialLabel = 'Select your Sub School';
                } elseif ($isAllSubSelected) {
                    $subBtnInitialLabel = 'All Sub Schools (Combined Catalog)';
                } elseif ($checkedSubCount === 1) {
                    $subBtnInitialLabel = '1 Sub School Selected';
                    foreach ($availableSubSchools as $s) {
                        if (in_array((int)$s['id'], $activeSubSchoolIds, true)) {
                            $subBtnInitialLabel = $s['name'];
                            break;
                        }
                    }
                } else {
                    $subBtnInitialLabel = $checkedSubCount . ' Sub Schools Selected';
                }
                ?>
                <div class="sub-school-multiselect" style="position:relative; min-width:280px;">
                    <button type="button" id="subSchoolDropdownBtn" onclick="toggleSubSchoolDropdown(event)" class="form-control" style="display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:0.88rem; color:var(--brand-dark); padding:0.5rem 0.85rem; border-radius:var(--radius-md); min-width:280px; border:1.5px solid #CBD5E1; background:#ffffff; cursor:pointer; text-align:left;">
                        <span id="subSchoolBtnLabel" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:240px;">
                            <?= htmlspecialchars($subBtnInitialLabel) ?>
                        </span>
                        <svg id="subSchoolArrowIcon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.5" style="margin-left:8px; transition:transform 0.2s; flex-shrink:0;">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <!-- Dropdown Checklist Box -->
                    <div id="subSchoolDropdownPanel" style="display:none; position:absolute; top:calc(100% + 5px); left:0; width:100%; min-width:300px; background:#ffffff; border:1.5px solid #CBD5E1; border-radius:var(--radius-md); box-shadow:0 12px 28px -4px rgba(0,0,0,0.18); z-index:99999; padding:0.5rem 0;">
                        <!-- Select All Checkbox Above -->
                        <label style="display:flex; align-items:center; gap:10px; padding:0.6rem 0.85rem; cursor:pointer; font-weight:800; font-size:0.85rem; color:#EA580C; background:#FFF7ED; margin:0 0.4rem; border-radius:6px; border:1px solid #FED7AA;">
                            <input type="checkbox" id="selectAllSubSchools" onchange="toggleSelectAllSubSchools(this)" <?= $isAllSubSelected ? 'checked' : '' ?> style="width:16px; height:16px; accent-color:#EA580C; cursor:pointer;">
                            <span>Select All</span>
                        </label>

                        <div style="height:1px; background:#E2E8F0; margin:0.4rem 0;"></div>

                        <!-- Each checkbox near the sub school -->
                        <div style="max-height:220px; overflow-y:auto; padding:0 0.4rem;">
                            <?php foreach ($availableSubSchools as $sub): ?>
                                <?php $isChecked = in_array((int)$sub['id'], $activeSubSchoolIds, true); ?>
                                <label class="sub-school-option-row" style="display:flex; align-items:center; gap:10px; padding:0.5rem 0.65rem; cursor:pointer; font-weight:700; font-size:0.84rem; color:var(--brand-dark); border-radius:6px; transition:background 0.15s; margin-bottom:2px;">
                                    <input type="checkbox" name="sub_school_ids[]" value="<?= $sub['id'] ?>" class="sub-school-checkbox" data-name="<?= htmlspecialchars($sub['name']) ?>" <?= $isChecked ? 'checked' : '' ?> onchange="handleSubSchoolCheckChange()" style="width:16px; height:16px; accent-color:var(--brand-primary); cursor:pointer;">
                                    <span style="user-select:none;"><?= sanitize($sub['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Footer with Apply button -->
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.5rem 0.75rem 0.2rem; border-top:1px solid #E2E8F0; margin-top:0.4rem;">
                            <span id="subSelectedCountText" style="font-size:0.75rem; font-weight:700; color:#64748B;">
                                <?= $checkedSubCount ?> of <?= $totalSubCount ?> selected
                            </span>
                            <button type="submit" class="btn btn-primary btn-sm" style="padding:0.35rem 0.85rem; font-size:0.78rem; font-weight:700;">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <select class="form-control" style="font-weight:700; font-size:0.88rem; color:#94A3B8; padding:0.5rem 0.85rem; border-radius:var(--radius-md); min-width:280px; border:1.5px solid #CBD5E1; background:#F8FAFC;" disabled>
                    <option selected>Main Campus (Single School / No Sub Schools)</option>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <div style="display:flex; gap:0.75rem; align-items:center;">
        <?php if ($editProduct): ?>
            <a href="<?= BASE_URL ?>/admin/products.php?school_id=<?= $selectedSchoolId ?>" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:6px; color:#ffffff;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Add New</span>
            </a>
        <?php else: ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="toggleUniformForm()" style="display:inline-flex; align-items:center; gap:6px; color:#ffffff;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Add New</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- 2. SELECTED SCHOOL DETAILS HEADER (Shows for this selected school or sub-school) -->
<div class="school-hero-card" style="padding:1.25rem 1.75rem; margin-bottom:2rem; background:#ffffff; border:1.5px solid var(--brand-border); border-radius:var(--radius-xl); box-shadow:var(--shadow-sm);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem; flex-wrap:wrap;">
                <?php if ($isSubSchool): ?>
                    <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--brand-muted);">
                        <?= sanitize($parentSchool['name']) ?> &rarr;
                    </span>
                    <span style="background:#EFF6FF; color:#1D4ED8; font-weight:800; font-size:0.75rem; padding:3px 10px; border-radius:9999px; border:1px solid #BFDBFE;">
                        Sub School: <?= sanitize($currentSchool['name']) ?>
                    </span>
                <?php elseif (!empty($availableSubSchools) && count($activeSubSchoolIds) < count($availableSubIds)): ?>
                    <span style="background:#EFF6FF; color:#1D4ED8; font-weight:800; font-size:0.75rem; padding:3px 10px; border-radius:9999px; border:1px solid #BFDBFE;">
                        Sub Schools: <?= count($activeSubSchoolIds) ?> Selected
                    </span>
                <?php else: ?>
                    <span style="background:#FFF7ED; color:#C2410C; font-weight:800; font-size:0.75rem; padding:3px 10px; border-radius:9999px; border:1px solid #FED7AA;">
                        Campus Catalog (All Sub Schools Combined)
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="heading-1" style="margin:0; font-size:1.5rem; color:var(--brand-dark);">
                <?= sanitize($currentSchool['name']) ?>
            </h2>
            <div style="font-size:0.85rem; color:var(--brand-muted); margin-top:4px;">
                📍 <?= sanitize($currentSchool['location'] ?: $currentSchool['city'] ?: 'Trivandrum') ?> &bull; 
                <strong style="color:var(--brand-primary);"><?= count($schoolUniforms) ?> Uniforms</strong> in this catalog
            </div>
        </div>

        <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/schools/<?= sanitize($currentSchool['slug']) ?>/order/" target="_blank" class="btn btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:6px; font-weight:700; border-radius:var(--radius-pill); border-color:#FF8A3D; color:#EA580C; background:#FFF7ED;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                <span>Live Public Portal ↗</span>
            </a>
        </div>
    </div>
</div>

<!-- 3. UNIFORM UPLOAD / EDIT DRAWER (Visible on Edit or when '+ Upload Uniform' is clicked) -->
<div id="uniform-form-box" style="display:<?= $editProduct ? 'block' : 'none' ?>; background:#ffffff; border-radius:var(--radius-xl); border:2px solid var(--brand-primary); padding:2rem 2.5rem; box-shadow:var(--shadow-lg); margin-bottom:2.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid var(--brand-border); padding-bottom:1rem;">
        <div>
            <h3 class="heading-2" style="font-size:1.35rem; margin:0; color:var(--brand-dark);">
                <?= $editProduct ? 'Edit Uniform Item: ' . sanitize($editProduct['name']) : 'Add New Uniform Item for ' . sanitize($currentSchool['name']) ?>
            </h3>
        </div>
        <?php if ($editProduct): ?>
            <a href="<?= BASE_URL ?>/admin/products.php?school_id=<?= $selectedSchoolId ?>" class="btn btn-outline btn-sm">&larr; Back to Collection</a>
        <?php else: ?>
            <button type="button" onclick="toggleUniformForm()" class="btn btn-outline btn-sm">&times; Close Form</button>
        <?php endif; ?>
    </div>

    <form action="<?= BASE_URL ?>/admin/products.php?school_id=<?= $selectedSchoolId ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editProduct ? 'update' : 'create' ?>">
        <input type="hidden" name="school_id" value="<?= $selectedSchoolId ?>">
        <?php if ($editProduct): ?>
            <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
        <?php endif; ?>

        <input type="hidden" name="sku" value="<?= sanitize($editProduct['sku'] ?? '') ?>">
        <input type="hidden" name="description" value="<?= sanitize($editProduct['description'] ?? '') ?>">
        <input type="hidden" name="is_mandatory" value="<?= !empty($editProduct['is_mandatory']) ? '1' : '0' ?>">
        <input type="hidden" name="is_kit" value="<?= !empty($editProduct['is_kit']) ? '1' : '0' ?>">

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
            <div class="form-group">
                <label class="form-label">Uniform Item Name *</label>
                <input type="text" name="name" required value="<?= sanitize($editProduct['name'] ?? '') ?>" placeholder="Enter your Uniform Item Name" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="" disabled <?= empty($editProduct['category_id']) ? 'selected' : '' ?>>Select your Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (!empty($editProduct['category_id']) && $editProduct['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= sanitize($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Pricing Breakdown Fields (Requirement 5: Actual Price + Vendor Commission) -->
        <div style="background:#FFF9F5; border:1.5px solid #FFEDD5; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; margin-bottom:1.5rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; font-weight:800; color:var(--brand-primary); margin-bottom:0.75rem;">
                💰 Pricing Breakdown (Actual Price + Vendor Commission = Combined Total)
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1.5fr; gap:1.25rem; align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:700; font-size:0.82rem;">1. Actual Price (₹) *</label>
                    <input type="number" step="0.01" name="actual_price" id="form-actual-price" required 
                           value="<?= isset($editProduct['actual_price']) ? sanitize((string)$editProduct['actual_price']) : (isset($editProduct['vendor_price']) ? sanitize((string)$editProduct['vendor_price']) : '') ?>" 
                           class="form-control" placeholder="Enter your Actual Price" oninput="calcProductTotal()">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:700; font-size:0.82rem;">2. Vendor Commission (₹) *</label>
                    <input type="number" step="0.01" name="vendor_commission" id="form-vendor-commission" required 
                           value="<?= isset($editProduct['vendor_commission']) ? sanitize((string)$editProduct['vendor_commission']) : '' ?>" 
                           class="form-control" placeholder="Enter your Vendor Commission" oninput="calcProductTotal()">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:800; font-size:0.82rem; color:var(--brand-primary);">Combined Total Price (₹)</label>
                    <input type="number" step="0.01" name="price" id="form-total-price" required readonly
                           value="<?= isset($editProduct['price']) ? sanitize((string)$editProduct['price']) : '' ?>" 
                           class="form-control" style="font-weight:800; background:#FFF7ED; color:var(--brand-primary); border-color:#FFC499;">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:700; font-size:0.82rem;">Available Sizes</label>
                    <input type="text" name="available_sizes" value="<?= sanitize($editProduct['available_sizes'] ?? '') ?>" placeholder="Enter your Available Sizes" class="form-control">
                </div>
            </div>
            <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:0.6rem;">
                ℹ️ <em>Public Website Note: Customers see strictly the Combined Total Price. Internal base cost and vendor commission breakdown are hidden.</em>
            </div>
        </div>

        <!-- Strict 500 KB Image Upload Input -->
        <div style="background:var(--brand-surface-alt); padding:1.5rem; border-radius:var(--radius-lg); border:1.5px dashed var(--brand-border); margin-bottom:1.5rem;">
            <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                <span>Uniform Photography / Catalog Image (Max 500 KB)</span>
                <span id="uniform-size-badge" style="font-size:0.75rem; color:var(--brand-muted); font-weight:700;">Strict Limit: 500 KB</span>
            </label>
            <input type="file" name="image" id="uniform-file-picker" accept="image/png,image/jpeg,image/webp" class="form-control" style="padding:0.5rem;" onchange="validateUniformFileSize(this)">
            <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:0.35rem;">Formats: PNG, JPG, WEBP &bull; Maximum allowable file size: 500 KB</div>

            <?php if (!empty($editProduct['featured_image'])): ?>
                <div style="display:flex; align-items:center; gap:0.75rem; margin-top:0.75rem;">
                    <img src="<?= BASE_URL ?>/<?= sanitize($editProduct['featured_image']) ?>" alt="Uniform" style="width:52px; height:52px; object-fit:cover; border-radius:6px; border:1px solid var(--brand-border);">
                    <div>
                        <div style="font-size:0.82rem; font-weight:700; color:var(--brand-dark);">Current Attached Image</div>
                        <div style="font-size:0.75rem; color:var(--brand-muted);"><?= sanitize(basename($editProduct['featured_image'])) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap:0.75rem;">
            <button type="submit" class="btn btn-primary">
                <span><?= $editProduct ? 'Update Uniform Item' : 'Save Uniform Item' ?></span>
            </button>
            <?php if ($editProduct): ?>
                <a href="<?= BASE_URL ?>/admin/products.php?school_id=<?= $selectedSchoolId ?>" class="btn btn-outline">Cancel &amp; Back to Collection</a>
            <?php else: ?>
                <button type="button" onclick="toggleUniformForm()" class="btn btn-outline">Cancel</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!$editProduct): ?>
<!-- 4. FILTER / SEARCH CONTROLS FOR THIS SCHOOL'S UNIFORMS -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h3 class="heading-2" style="font-size:1.25rem; margin:0; color:var(--brand-dark);">
            <?= sanitize($currentSchool['name']) ?> &mdash; Uniform Collection (<?= count($schoolUniforms) ?> items)
        </h3>
        <p style="font-size:0.82rem; color:var(--brand-muted); margin:0.2rem 0 0 0;">
            Approved uniforms, daily poloshirts, sports wear, and kits for this institution.
        </p>
    </div>

    <form action="" method="GET" style="display:flex; gap:0.5rem; flex-wrap:wrap;">
        <input type="hidden" name="school_id" value="<?= $parentSchoolId ?>">
        <?php foreach ($activeSubSchoolIds as $subId): ?>
            <input type="hidden" name="sub_school_ids[]" value="<?= $subId ?>">
        <?php endforeach; ?>

        <select name="category" class="form-control" style="padding:0.4rem 0.6rem; font-size:0.85rem; min-width:185px; width:auto;" onchange="this.form.submit()">
            <option value="0">Select your Category</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Enter your Uniform Name" class="form-control" style="padding:0.4rem 0.6rem; font-size:0.85rem; min-width:210px; width:auto;">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($catFilter > 0 || !empty($search)): ?>
            <a href="<?= BASE_URL ?>/admin/products.php?school_id=<?= $parentSchoolId ?><?= !empty($activeSubSchoolIds) ? '&' . http_build_query(['sub_school_ids' => $activeSubSchoolIds]) : '' ?>" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- 5. UNIFORM COLLECTION TABLE LIST (MATCHING REGISTERED PARTNER SCHOOLS) -->
<div class="table-scroll-frame" style="max-height:520px; overflow-y:auto; overflow-x:auto; position:relative; background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
    <table class="admin-table" style="margin:0; border:none; border-radius:0;">
        <thead>
            <tr>
                <th style="width:65px; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Image</th>
                <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Uniform Item</th>
                <th style="text-align:right; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Actual Price</th>
                <th style="text-align:right; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Vendor Commission</th>
                <th style="text-align:right; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Combined Total</th>
                <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Available Sizes</th>
                <th style="text-align:right; width:135px; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($schoolUniforms)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:3rem; color:var(--brand-muted);">
                        No uniforms found for <?= sanitize($currentSchool['name']) ?>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($schoolUniforms as $u): ?>
                    <tr>
                        <td style="width:65px; vertical-align:middle;">
                            <?php if (!empty($u['featured_image'])): ?>
                                <img src="<?= BASE_URL ?>/<?= sanitize($u['featured_image']) ?>" 
                                     alt="<?= sanitize($u['product_name']) ?>" 
                                     style="width:46px; height:46px; object-fit:contain; border-radius:8px; border:1px solid var(--brand-border); background:#F8FAFC; padding:2px; display:block;">
                            <?php else: ?>
                                <div style="width:46px; height:46px; border-radius:8px; border:1px solid var(--brand-border); background:#F8FAFC; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                    👔
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <div style="font-weight:800; color:var(--brand-dark); font-size:0.92rem;">
                                <?= sanitize($u['product_name']) ?>
                            </div>
                            <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:2px;">
                                <?= sanitize($u['category_name'] ?: 'School Uniform') ?>
                            </div>
                        </td>
                        <td style="vertical-align:middle; text-align:right; font-weight:700; color:#475569;">
                            <?= formatCurrency((float)$u['actual_price']) ?>
                        </td>
                        <td style="vertical-align:middle; text-align:right; font-weight:700; color:#059669;">
                            <?= formatCurrency((float)$u['vendor_commission']) ?>
                        </td>
                        <td style="vertical-align:middle; text-align:right;">
                            <span style="font-weight:800; color:var(--brand-primary); font-size:0.95rem;">
                                <?= formatCurrency((float)($u['custom_price'] ?: $u['base_price'])) ?>
                            </span>
                        </td>
                        <?php
                        $isAcc = ($u['uniform_group'] ?? '') === 'accessories' || preg_match('/\b(belt|socks?|cap|tie|bag|badge|bottle|mask)\b/i', $u['product_name']);
                        ?>
                        <td style="vertical-align:middle;">
                            <span style="font-size:0.85rem; color:var(--brand-muted); font-weight:600;">
                                <?= $isAcc || empty($u['available_sizes']) ? '—' : sanitize($u['available_sizes']) ?>
                            </span>
                        </td>
                        <td style="text-align:right; vertical-align:middle;">
                            <div style="display:inline-flex; gap:0.4rem; align-items:center;">
                                <button type="button" 
                                        class="btn btn-outline btn-sm product-view-btn" 
                                        style="padding:0.35rem 0.55rem; line-height:1; display:inline-flex; align-items:center; justify-content:center; color:#2563EB; border-color:#BFDBFE; background:#F8FAFC;" 
                                        title="View Item Details"
                                        onclick='openProductViewModal(<?= htmlspecialchars(json_encode([
                                            "id" => (int)$u["product_id"],
                                            "name" => (string)$u["product_name"],
                                            "sku" => (string)($u["sku"] ?? ""),
                                            "category" => (string)($u["category_name"] ?: "Uniform Item"),
                                            "school_name" => (string)$currentSchool["name"],
                                            "actual_price" => formatCurrency((float)$u["actual_price"]),
                                            "vendor_commission" => formatCurrency((float)$u["vendor_commission"]),
                                            "combined_total" => formatCurrency((float)($u["custom_price"] ?: $u["base_price"])),
                                            "sizes" => $isAcc ? "—" : (string)($u["available_sizes"] ?: "Standard Size"),
                                            "is_mandatory" => !empty($u["is_mandatory"]) ? "Mandatory Item" : "Optional Apparel",
                                            "is_kit" => !empty($u["is_kit"]) ? "Uniform Kit" : "Single Item",
                                            "description" => (string)(!empty($u["description"]) ? $u["description"] : "Approved institutional garment manufactured with high-durability fabric for students."),
                                            "image" => !empty($u["featured_image"]) ? BASE_URL . "/" . $u["featured_image"] : BASE_URL . "/assets/images/defaults/uniform-placeholder.png",
                                            "edit_url" => BASE_URL . "/admin/products.php?school_id=" . $selectedSchoolId . "&edit=" . $u["product_id"] . "#uniform-form-box"
                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)'>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>

                                <a href="<?= BASE_URL ?>/admin/products.php?school_id=<?= $selectedSchoolId ?>&edit=<?= $u['product_id'] ?>#uniform-form-box" 
                                   class="btn btn-primary btn-sm" 
                                   style="padding:0.35rem 0.55rem; line-height:1; display:inline-flex; align-items:center; justify-content:center;" 
                                   title="Edit Uniform">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                    </svg>
                                </a>

                                <form action="<?= BASE_URL ?>/admin/products.php?school_id=<?= $selectedSchoolId ?>" 
                                      method="POST" 
                                      data-confirm="delete" 
                                      data-item-name="<?= htmlspecialchars($u['product_name']) ?> from <?= htmlspecialchars($currentSchool['name']) ?>" 
                                      style="display:inline; margin:0;">
                                    <input type="hidden" name="action" value="unlink_school_product">
                                    <input type="hidden" name="school_product_id" value="<?= $u['school_product_id'] ?>">
                                    <button type="submit" 
                                            class="btn btn-outline btn-sm" 
                                            title="Delete Uniform" 
                                            style="padding:0.35rem 0.55rem; line-height:1; color:#dc2626; border-color:rgba(220,38,38,0.3); display:inline-flex; align-items:center; justify-content:center;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Client-side Strict 500 KB Limit File Validation & Drawer Toggle Script -->
<script>
function handleMainSchoolChange(mainSchoolId) {
    window.location.href = '<?= BASE_URL ?>/admin/products.php?school_id=' + encodeURIComponent(mainSchoolId);
}

function toggleSubSchoolDropdown(e) {
    if (e) e.stopPropagation();
    const panel = document.getElementById('subSchoolDropdownPanel');
    const arrow = document.getElementById('subSchoolArrowIcon');
    if (!panel) return;
    const isShown = panel.style.display === 'block';
    panel.style.display = isShown ? 'none' : 'block';
    if (arrow) {
        arrow.style.transform = isShown ? 'rotate(0deg)' : 'rotate(180deg)';
    }
}

function updateSubSchoolDisplay() {
    const allCheckbox = document.getElementById('selectAllSubSchools');
    const checkboxes = Array.from(document.querySelectorAll('.sub-school-checkbox'));
    const countText = document.getElementById('subSelectedCountText');
    const label = document.getElementById('subSchoolBtnLabel');
    if (!checkboxes.length) return;

    const checkedBoxes = checkboxes.filter(cb => cb.checked);
    const total = checkboxes.length;
    const checkedCount = checkedBoxes.length;

    // Update Select All Checkbox
    if (allCheckbox) {
        allCheckbox.checked = (checkedCount === total && total > 0);
        allCheckbox.indeterminate = (checkedCount > 0 && checkedCount < total);
    }

    if (countText) {
        countText.textContent = `${checkedCount} of ${total} selected`;
    }

    if (label) {
        if (checkedCount === 0) {
            label.textContent = 'Select your Sub School';
        } else if (checkedCount === total) {
            label.textContent = 'All Sub Schools (Combined Catalog)';
        } else if (checkedCount === 1) {
            label.textContent = checkedBoxes[0].getAttribute('data-name') || '1 Sub School';
        } else {
            label.textContent = `${checkedCount} Sub Schools Selected`;
        }
    }
}

function toggleSelectAllSubSchools(selectAllEl) {
    const isChecked = selectAllEl.checked;
    const checkboxes = document.querySelectorAll('.sub-school-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
    });
    updateSubSchoolDisplay();
}

function handleSubSchoolCheckChange() {
    updateSubSchoolDisplay();
}

document.addEventListener('click', function(e) {
    const panel = document.getElementById('subSchoolDropdownPanel');
    const btn = document.getElementById('subSchoolDropdownBtn');
    const arrow = document.getElementById('subSchoolArrowIcon');
    if (panel && panel.style.display === 'block') {
        if (!panel.contains(e.target) && (!btn || !btn.contains(e.target))) {
            panel.style.display = 'none';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateSubSchoolDisplay);
} else {
    updateSubSchoolDisplay();
}
window.addEventListener('pageshow', updateSubSchoolDisplay);

function toggleUniformForm() {
    const box = document.getElementById('uniform-form-box');
    if (box.style.display === 'none' || box.style.display === '') {
        box.style.display = 'block';
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        box.style.display = 'none';
    }
}

function validateUniformFileSize(input) {
    const badge = document.getElementById('uniform-size-badge');
    if (!input.files || !input.files[0]) {
        badge.textContent = 'Strict Limit: 500 KB';
        badge.style.color = 'var(--brand-muted)';
        return;
    }

    const file = input.files[0];
    const sizeKb = (file.size / 1024).toFixed(1);
    const maxKb = 500;

    if (file.size > maxKb * 1024) {
        alert(`⚠️ Selected uniform image exceeds the strict 500 KB limit!\n\nFile Name: ${file.name}\nFile Size: ${sizeKb} KB\n\nPlease select an image under 500 KB.`);
        input.value = ''; // Reset input immediately
        badge.textContent = `❌ Too Large: ${sizeKb} KB (Max 500 KB)`;
        badge.style.color = '#dc2626';
        badge.style.fontWeight = '800';
    } else {
        badge.textContent = `✓ Valid Image: ${sizeKb} KB (Under 500 KB)`;
        badge.style.color = '#16a34a';
        badge.style.fontWeight = '700';
    }
}

function calcProductTotal() {
    const act = parseFloat(document.getElementById('form-actual-price').value) || 0;
    const comm = parseFloat(document.getElementById('form-vendor-commission').value) || 0;
    document.getElementById('form-total-price').value = (act + comm).toFixed(2);
}

function calcQuickModalTotal() {
    const act = parseFloat(document.getElementById('quick-modal-actual-price').value) || 0;
    const comm = parseFloat(document.getElementById('quick-modal-vendor-commission').value) || 0;
    document.getElementById('quick-modal-total-price').value = (act + comm).toFixed(2);
}

function openQuickPriceModal(data) {
    document.getElementById('quick-modal-product-id').value = data.id || '';
    document.getElementById('quick-modal-title').textContent = data.name || 'Edit Uniform';
    document.getElementById('quick-modal-actual-price').value = parseFloat(data.actual_price || 0).toFixed(2);
    document.getElementById('quick-modal-vendor-commission').value = parseFloat(data.vendor_commission || 0).toFixed(2);
    document.getElementById('quick-modal-total-price').value = parseFloat(data.price || 0).toFixed(2);
    document.getElementById('quick-modal-sizes').value = data.sizes || '';
    document.getElementById('quick-price-modal').style.display = 'flex';
}

function closeQuickPriceModal() {
    document.getElementById('quick-price-modal').style.display = 'none';
}

// ==================== VIEW PARTICULAR ITEM DETAILS POPUP MODAL ====================
function openProductViewModal(item) {
    document.getElementById('pvm-title').textContent = item.name || 'Uniform Item';
    document.getElementById('pvm-category-badge').textContent = item.category || 'Uniform';
    document.getElementById('pvm-school-meta').textContent = '🏫 ' + (item.school_name || 'School') + ' Collection';
    document.getElementById('pvm-image').src = item.image || '';
    document.getElementById('pvm-sku').textContent = item.sku || 'N/A';
    document.getElementById('pvm-type').textContent = item.is_kit || 'Single Item';
    document.getElementById('pvm-actual-price').textContent = item.actual_price || '₹ 0.00';
    document.getElementById('pvm-vendor-commission').textContent = item.vendor_commission || '₹ 0.00';
    document.getElementById('pvm-combined-total').textContent = item.combined_total || '₹ 0.00';
    document.getElementById('pvm-description').textContent = item.description || 'Approved uniform garment for institutional wear.';
    document.getElementById('pvm-id-badge').textContent = '#' + (item.id || '');
    document.getElementById('pvm-edit-link').href = item.edit_url || '#';
    
    // Available Sizes as clean pills
    const sizesContainer = document.getElementById('pvm-sizes-container');
    sizesContainer.innerHTML = '';
    const rawSizes = (item.sizes || '').split(',');
    rawSizes.forEach(s => {
        const trimmed = s.trim();
        if (trimmed) {
            const pill = document.createElement('span');
            pill.style.cssText = 'background:#ffffff; border:1px solid #CBD5E1; color:#0F172A; font-weight:700; font-size:0.78rem; padding:0.25rem 0.65rem; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.04);';
            pill.textContent = trimmed;
            sizesContainer.appendChild(pill);
        }
    });

    const modal = document.getElementById('product-view-modal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeProductViewModal() {
    const modal = document.getElementById('product-view-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

document.getElementById('product-view-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'product-view-modal') {
        closeProductViewModal();
    }
});
</script>

<!-- Quick Price & Sizes Modal (Requirement 5: Actual Price + Vendor Commission) -->
<div id="quick-price-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(3px);">
    <div style="background:#ffffff; border-radius:var(--radius-xl); max-width:540px; width:100%; border:2px solid var(--brand-primary); box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); overflow:hidden; animation:modalPop 0.2s ease;">
        <div style="background:linear-gradient(135deg, var(--brand-dark) 0%, #1E293B 100%); color:#ffffff; padding:1.25rem 1.75rem; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h4 id="quick-modal-title" style="margin:0; font-size:1.15rem; font-weight:800; color:#ffffff;">Modify Price &amp; Sizes</h4>
                <div style="font-size:0.75rem; color:#94A3B8; margin-top:0.2rem;">For <?= sanitize($currentSchool['name']) ?></div>
            </div>
            <button type="button" onclick="closeQuickPriceModal()" style="background:transparent; border:none; color:#ffffff; font-size:1.5rem; cursor:pointer; line-height:1;">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/admin/products.php?school_id=<?= $selectedSchoolId ?>" method="POST" style="padding:1.75rem;">
            <input type="hidden" name="action" value="quick_update_pricing_sizes">
            <input type="hidden" name="product_id" id="quick-modal-product-id" value="">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.25rem;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-size:0.82rem; font-weight:700;">1. Actual Price (₹) *</label>
                    <input type="number" step="0.01" name="actual_price" id="quick-modal-actual-price" required class="form-control" oninput="calcQuickModalTotal()">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-size:0.82rem; font-weight:700;">2. Vendor Commission (₹) *</label>
                    <input type="number" step="0.01" name="vendor_commission" id="quick-modal-vendor-commission" required class="form-control" oninput="calcQuickModalTotal()">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label" style="font-size:0.82rem; font-weight:800; color:var(--brand-primary);">Combined Total Price (₹)</label>
                <input type="number" step="0.01" name="price" id="quick-modal-total-price" required readonly class="form-control" style="font-weight:800; background:#FFF7ED; color:var(--brand-primary); border-color:#FFC499;">
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
                <label class="form-label" style="font-size:0.82rem; display:flex; justify-content:space-between;">
                    <span>Available Sizes (Comma-separated) *</span>
                    <span style="font-size:0.72rem; color:var(--brand-muted);">e.g. 20, 22, 24, 26, 28, 30, 32</span>
                </label>
                <input type="text" name="available_sizes" id="quick-modal-sizes" required class="form-control" style="font-weight:600;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1px solid var(--brand-border); padding-top:1.25rem;">
                <button type="button" onclick="closeQuickPriceModal()" class="btn btn-outline" style="padding:0.6rem 1.25rem; font-weight:700;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.5rem; font-weight:800;">
                    ✓ Save Price &amp; Sizes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== ITEM VIEW DETAILS MODAL (View Particular Item Only Details Popup) ==================== -->
<div id="product-view-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:99999; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(4px);">
    <div style="background:#ffffff; border-radius:24px; max-width:680px; width:100%; border:1.5px solid var(--brand-border); box-shadow:0 25px 60px -12px rgba(0,0,0,0.3); overflow:hidden; animation:modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Modal Header -->
        <div style="background:linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); padding:1.25rem 1.75rem; border-bottom:1.5px solid #FED7AA; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                    <span style="font-size:1.3rem;">👕</span>
                    <h3 id="pvm-title" style="margin:0; font-size:1.25rem; font-weight:800; color:var(--brand-dark);">Uniform Details</h3>
                    <span id="pvm-category-badge" style="font-size:0.75rem; font-weight:800; color:#C2410C; background:#FFEDD5; border:1px solid #FDBA74; padding:0.2rem 0.65rem; border-radius:9999px;">Category</span>
                </div>
                <div style="font-size:0.78rem; color:var(--brand-muted); margin-top:0.25rem;" id="pvm-school-meta">
                    Affiliated Institution
                </div>
            </div>
            <button type="button" onclick="closeProductViewModal()" style="background:transparent; border:none; font-size:1.6rem; color:#64748B; cursor:pointer; line-height:1; width:34px; height:34px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.2s;" onmouseover="this.style.background='#FED7AA'; this.style.color='#C2410C';" onmouseout="this.style.background='transparent'; this.style.color='#64748B';">&times;</button>
        </div>

        <!-- Modal Body (Two-Column Layout) -->
        <div style="padding:1.75rem; display:grid; grid-template-columns:200px 1fr; gap:1.5rem; align-items:start; max-height:75vh; overflow-y:auto;">
            <!-- Left: Image & Meta -->
            <div>
                <div style="width:100%; height:200px; border-radius:16px; border:1.5px solid var(--brand-border); background:#F8FAFC; display:flex; align-items:center; justify-content:center; overflow:hidden; box-shadow:var(--shadow-sm); margin-bottom:1rem;">
                    <img id="pvm-image" src="" alt="Uniform Item" style="max-width:100%; max-height:100%; object-fit:contain;">
                </div>
                
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:0.75rem; font-size:0.78rem;">
                    <div style="margin-bottom:0.4rem;">
                        <span style="color:var(--brand-muted); font-weight:600;">SKU / Code:</span><br>
                        <strong id="pvm-sku" style="color:var(--brand-dark); font-family:monospace; font-size:0.85rem;">OB-XXX</strong>
                    </div>
                    <div>
                        <span style="color:var(--brand-muted); font-weight:600;">Item Type:</span><br>
                        <span id="pvm-type" style="font-weight:700; color:#0F172A;">Single Item</span>
                    </div>
                </div>
            </div>

            <!-- Right: Pricing Breakdown & Specifications -->
            <div>
                <!-- Pricing Splitting Card (Requirement 5) -->
                <div style="background:#FFF9F5; border:1.5px solid #FFEDD5; border-radius:16px; padding:1.1rem 1.25rem; margin-bottom:1.25rem;">
                    <div style="font-size:0.72rem; text-transform:uppercase; letter-spacing:0.06em; font-weight:800; color:var(--brand-primary); margin-bottom:0.6rem;">
                        💰 Pricing Breakdown
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0.75rem; padding-bottom:0.75rem; border-bottom:1px dashed #FDBA74;">
                        <div>
                            <div style="font-size:0.72rem; color:var(--brand-muted); font-weight:600;">1. Actual Price (Base)</div>
                            <div id="pvm-actual-price" style="font-size:1.05rem; font-weight:800; color:#334155;">₹ 0.00</div>
                        </div>
                        <div>
                            <div style="font-size:0.72rem; color:var(--brand-muted); font-weight:600;">2. Vendor Commission</div>
                            <div id="pvm-vendor-commission" style="font-size:1.05rem; font-weight:800; color:#059669;">₹ 0.00</div>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.82rem; font-weight:800; color:var(--brand-dark);">Combined Total Price:</span>
                        <span id="pvm-combined-total" style="font-size:1.35rem; font-weight:900; color:var(--brand-primary); font-family:var(--font-display);">₹ 0.00</span>
                    </div>
                </div>

                <!-- Available Sizes -->
                <div style="margin-bottom:1.25rem;">
                    <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:var(--brand-muted); margin-bottom:0.5rem;">
                        📏 Available Sizes
                    </div>
                    <div id="pvm-sizes-container" style="display:flex; flex-wrap:wrap; gap:0.4rem;">
                        <!-- Injected as pill badges -->
                    </div>
                </div>

                <!-- Item Description / Material Note -->
                <div>
                    <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:var(--brand-muted); margin-bottom:0.35rem;">
                        📝 Item Description &amp; Specifications
                    </div>
                    <p id="pvm-description" style="font-size:0.85rem; color:#475569; line-height:1.5; margin:0; background:#F8FAFC; border:1px solid #E2E8F0; padding:0.75rem 1rem; border-radius:12px;">
                        Description text
                    </p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="background:#F8FAFC; padding:1rem 1.75rem; border-top:1px solid var(--brand-border); display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:0.75rem; color:var(--brand-muted);">
                Item Reference: <strong id="pvm-id-badge">#0</strong>
            </div>
            <div style="display:flex; gap:0.6rem;">
                <button type="button" onclick="closeProductViewModal()" class="btn btn-outline btn-sm" style="padding:0.5rem 1.25rem; font-weight:700;">
                    Close
                </button>
                <a id="pvm-edit-link" href="#" class="btn btn-primary btn-sm" style="padding:0.5rem 1.25rem; font-weight:800; display:inline-flex; align-items:center; gap:5px; color:#ffffff;">
                    ✏️ Edit Item
                </a>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes modalPop {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>

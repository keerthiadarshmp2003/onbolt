<?php
/**
 * OnBolt Digital Experience Platform - School Directory & Image Uploads
 * Strict 500 KB per-image upload enforcement
 * URL: /admin/schools.php
 */

declare(strict_types=1);

$adminActive = 'schools';
$adminTitle = 'Schools Directory & Settings';
require_once __DIR__ . '/header.php';

if (!adminCan('schools_manage')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to access Schools Directory.');
}

$pdo = getDbConnection();
$msg = '';
$err = '';

if (!function_exists('isSchoolCodeTaken')) {
    function isSchoolCodeTaken(PDO $pdo, string $code, int $excludeId = 0): ?array {
        if (empty($code)) return null;
        $stmt = $pdo->prepare("SELECT id, name, code FROM schools WHERE code = ? AND id != ?");
        $stmt->execute([$code, $excludeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('generateUniqueSchoolCode')) {
    function generateUniqueSchoolCode(PDO $pdo, string $baseName, int $excludeId = 0, array $reservedCodes = []): string {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $baseName));
        $base = substr($clean, 0, 3);
        if (strlen($base) < 3) {
            $base = str_pad($base, 3, 'X');
        }
        $code = $base;
        $counter = 1;
        while (isSchoolCodeTaken($pdo, $code, $excludeId) || in_array($code, $reservedCodes, true)) {
            $code = substr($base, 0, 2) . $counter;
            $counter++;
            if ($counter > 9) {
                $code = substr($base, 0, 1) . $counter;
            }
            if ($counter > 99) {
                $code = 'S' . rand(10, 99);
            }
        }
        return $code;
    }
}

// Handle Form Submissions (Create, Update, Delete)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $mode = $_POST['school_structure_mode'] ?? 'single'; // 'single', 'multi', or 'sub_single'
        $id = (int)($_POST['id'] ?? 0);

        if ($mode === 'single') {
            $name = trim($_POST['single_name'] ?? '');
            $rawCode = trim($_POST['single_code'] ?? '');
            $location = trim($_POST['single_location'] ?? '');
            $contactPhone = trim($_POST['single_contact_phone'] ?? '');

            if (empty($name)) {
                $err = 'School Name is mandatory.';
            } else {
                $rawClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawCode));
                if (!empty($rawClean)) {
                    $code = substr($rawClean, 0, 5);
                    $taken = isSchoolCodeTaken($pdo, $code, ($action === 'update') ? $id : 0);
                    if ($taken) {
                        $err = "School code '{$code}' is already assigned to '{$taken['name']}'. School codes must be unique.";
                    }
                } else {
                    $code = generateUniqueSchoolCode($pdo, $name, ($action === 'update') ? $id : 0);
                }

                if (empty($err)) {
                    $slug = generateSchoolSlug($pdo, $name, ($action === 'update') ? $id : null);
                    $city = !empty($location) ? $location : 'Trivandrum';

                    try {
                        if ($action === 'create') {
                            $stmt = $pdo->prepare("INSERT INTO schools (name, slug, code, city, location, description, contact_phone, contact_email, logo, banner_image, parent_id, has_sub_schools, enable_class_selection, uniform_mode, applicable_classes, status, created_at) VALUES (?, ?, ?, ?, ?, '', ?, '', '', '', NULL, 0, 0, 'common', '', 1, NOW())");
                            $stmt->execute([$name, $slug, $code, $city, $location, $contactPhone]);
                            $newId = (int)$pdo->lastInsertId();

                            // Assign uniform catalog for this school
                            $prods = $pdo->query("SELECT id FROM products WHERE status = 1")->fetchAll(PDO::FETCH_COLUMN);
                            $insSp = $pdo->prepare("INSERT IGNORE INTO school_products (school_id, product_id, is_mandatory) VALUES (?, ?, 0)");
                            foreach ($prods as $pid) {
                                $insSp->execute([$newId, $pid]);
                            }

                            clearFastCache();
                            $msg = "SAVED SUCCESSFULLY";
                            logActivity('create_school', 'schools', (int)$admin['id'], 'admin', "Created single school {$name} (#{$newId}) with code {$code}");
                        } else {
                            $stmt = $pdo->prepare("UPDATE schools SET name = ?, slug = ?, code = ?, city = ?, location = ?, contact_phone = ?, parent_id = NULL, has_sub_schools = 0 WHERE id = ?");
                            $stmt->execute([$name, $slug, $code, $city, $location, $contactPhone, $id]);
                            clearFastCache();
                            clearFastCache();
                        $msg = "UPDATED SUCCESSFULLY";
                            logActivity('update_school', 'schools', (int)$admin['id'], 'admin', "Updated school #{$id} with code {$code}");
                        }
                    } catch (Exception $e) {
                        $err = $e->getMessage();
                    }
                }
            }
        } elseif ($mode === 'multi') {
            $commonName = trim($_POST['multi_name'] ?? '');
            $groupLocation = trim($_POST['multi_location'] ?? '');
            $groupPhone = trim($_POST['multi_contact_phone'] ?? '');

            // Sub-school selection unit label (e.g. School, Campus, Branch, etc.)
            $isSchoolUnit = isset($_POST['is_school_unit']) ? 1 : 0;
            $subSchoolLabel = $isSchoolUnit ? 'School' : trim($_POST['sub_school_label'] ?? '');
            if (empty($subSchoolLabel)) {
                $subSchoolLabel = 'School';
            }

            $subIds = $_POST['sub_school_id'] ?? [];
            $subNames = $_POST['sub_school_name'] ?? [];
            $subCodes = $_POST['sub_school_code'] ?? [];
            $subLocations = $_POST['sub_school_location'] ?? [];
            $subPhones = $_POST['sub_school_phone'] ?? [];

            $validSubSchools = [];
            for ($i = 0; $i < count($subNames); $i++) {
                $sName = trim($subNames[$i] ?? '');
                if (!empty($sName)) {
                    $validSubSchools[] = [
                        'id' => (int)($subIds[$i] ?? 0),
                        'name' => $sName,
                        'code' => trim($subCodes[$i] ?? ''),
                        'location' => trim($subLocations[$i] ?? ''),
                        'phone' => trim($subPhones[$i] ?? '') ?: $groupPhone
                    ];
                }
            }

            if (empty($commonName)) {
                $err = 'Common School Group Name is mandatory.';
            } elseif (empty($validSubSchools)) {
                $err = 'Please add at least one individual school under this Multi School group.';
            } else {
                // Validate that each sub-school has a unique code
                $reservedCodes = [];
                $cleanSubSchools = [];
                foreach ($validSubSchools as $sub) {
                    $subId = (int)($sub['id'] ?? 0);
                    $rawSubCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sub['code'] ?? ''));
                    if (!empty($rawSubCode)) {
                        $subCode = substr($rawSubCode, 0, 5);
                        if (in_array($subCode, $reservedCodes, true)) {
                            $err = "Duplicate school code '{$subCode}' in sub-schools. Each school code must be unique.";
                            break;
                        }
                        $taken = isSchoolCodeTaken($pdo, $subCode, $subId);
                        if ($taken) {
                            $err = "School code '{$subCode}' is already assigned to '{$taken['name']}'. School codes must be unique.";
                            break;
                        }
                        $reservedCodes[] = $subCode;
                        $sub['clean_code'] = $subCode;
                    } else {
                        $subCode = generateUniqueSchoolCode($pdo, $sub['name'], $subId, $reservedCodes);
                        $reservedCodes[] = $subCode;
                        $sub['clean_code'] = $subCode;
                    }
                    $cleanSubSchools[] = $sub;
                }

                if (empty($err)) {
                    // Common group code
                    $rawCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['multi_code'] ?? '')));
                    if (!empty($rawCode)) {
                        $code = substr($rawCode, 0, 5);
                        $taken = isSchoolCodeTaken($pdo, $code, ($action === 'update') ? $id : 0);
                        if ($taken || in_array($code, $reservedCodes, true)) {
                            $code = generateUniqueSchoolCode($pdo, $commonName, ($action === 'update') ? $id : 0, $reservedCodes);
                        }
                    } else {
                        $code = generateUniqueSchoolCode($pdo, $commonName, ($action === 'update') ? $id : 0, $reservedCodes);
                    }
                    $reservedCodes[] = $code;

                    $slug = generateSchoolSlug($pdo, $commonName, ($action === 'update') ? $id : null);
                    if (empty($groupLocation) && !empty($cleanSubSchools[0]['location'])) {
                        $groupLocation = $cleanSubSchools[0]['location'];
                    }
                    $city = !empty($groupLocation) ? $groupLocation : 'Trivandrum';
                    if (empty($groupPhone) && !empty($cleanSubSchools[0]['phone'])) {
                        $groupPhone = $cleanSubSchools[0]['phone'];
                    }

                    try {
                        if ($action === 'create') {
                            // 1. Create Common School Group
                            $stmt = $pdo->prepare("INSERT INTO schools (name, slug, code, city, location, description, contact_phone, contact_email, logo, banner_image, parent_id, has_sub_schools, sub_school_label, enable_class_selection, uniform_mode, applicable_classes, status, created_at) VALUES (?, ?, ?, ?, ?, '', ?, '', '', '', NULL, 1, ?, 0, 'common', '', 1, NOW())");
                            $stmt->execute([$commonName, $slug, $code, $city, $groupLocation, $groupPhone, $subSchoolLabel]);
                            $groupId = (int)$pdo->lastInsertId();

                            // 2. Insert each individual school under this group
                            $insSub = $pdo->prepare("INSERT INTO schools (name, slug, code, city, location, description, contact_phone, contact_email, logo, banner_image, parent_id, has_sub_schools, enable_class_selection, uniform_mode, applicable_classes, status, created_at) VALUES (?, ?, ?, ?, ?, '', ?, '', '', '', ?, 0, 0, 'common', '', 1, NOW())");
                            $prods = $pdo->query("SELECT id FROM products WHERE status = 1")->fetchAll(PDO::FETCH_COLUMN);
                            $insSp = $pdo->prepare("INSERT IGNORE INTO school_products (school_id, product_id, is_mandatory) VALUES (?, ?, 0)");

                            foreach ($cleanSubSchools as $sub) {
                                $subCode = $sub['clean_code'];
                                $subSlug = generateSchoolSlug($pdo, $sub['name']);
                                $subCity = !empty($sub['location']) ? $sub['location'] : $city;
                                $insSub->execute([$sub['name'], $subSlug, $subCode, $subCity, $sub['location'], $sub['phone'], $groupId]);
                                $subNewId = (int)$pdo->lastInsertId();

                                foreach ($prods as $pid) {
                                    $insSp->execute([$subNewId, $pid]);
                                }
                            }

                            clearFastCache();
                            $msg = "SAVED SUCCESSFULLY";
                            logActivity('create_school', 'schools', (int)$admin['id'], 'admin', "Created multi-school group {$commonName} (#{$groupId}) with " . count($cleanSubSchools) . " schools");
                        } else {
                            // Update Common School Group
                            $stmt = $pdo->prepare("UPDATE schools SET name = ?, slug = ?, code = ?, city = ?, location = ?, contact_phone = ?, sub_school_label = ?, parent_id = NULL, has_sub_schools = 1 WHERE id = ?");
                            $stmt->execute([$commonName, $slug, $code, $city, $groupLocation, $groupPhone, $subSchoolLabel, $id]);

                            // Handle individual schools under this group
                            $existingSubIds = $pdo->query("SELECT id FROM schools WHERE parent_id = {$id}")->fetchAll(PDO::FETCH_COLUMN);
                            $processedSubIds = [];

                            $updSub = $pdo->prepare("UPDATE schools SET name = ?, code = ?, city = ?, location = ?, contact_phone = ? WHERE id = ? AND parent_id = ?");
                            $insSub = $pdo->prepare("INSERT INTO schools (name, slug, code, city, location, description, contact_phone, contact_email, logo, banner_image, parent_id, has_sub_schools, enable_class_selection, uniform_mode, applicable_classes, status, created_at) VALUES (?, ?, ?, ?, ?, '', ?, '', '', '', ?, 0, 0, 'common', '', 1, NOW())");
                            $prods = $pdo->query("SELECT id FROM products WHERE status = 1")->fetchAll(PDO::FETCH_COLUMN);
                            $insSp = $pdo->prepare("INSERT IGNORE INTO school_products (school_id, product_id, is_mandatory) VALUES (?, ?, 0)");

                            foreach ($cleanSubSchools as $sub) {
                                $subCode = $sub['clean_code'];
                                $subCity = !empty($sub['location']) ? $sub['location'] : $city;

                                if (!empty($sub['id']) && in_array($sub['id'], $existingSubIds)) {
                                    $updSub->execute([$sub['name'], $subCode, $subCity, $sub['location'], $sub['phone'], $sub['id'], $id]);
                                    $processedSubIds[] = $sub['id'];
                                } else {
                                    $subSlug = generateSchoolSlug($pdo, $sub['name']);
                                    $insSub->execute([$sub['name'], $subSlug, $subCode, $subCity, $sub['location'], $sub['phone'], $id]);
                                    $newSubId = (int)$pdo->lastInsertId();
                                    $processedSubIds[] = $newSubId;
                                    foreach ($prods as $pid) {
                                        $insSp->execute([$newSubId, $pid]);
                                    }
                                }
                            }

                            // Remove individual schools removed by admin
                            foreach ($existingSubIds as $oldSubId) {
                                if (!in_array($oldSubId, $processedSubIds)) {
                                    $pdo->prepare("DELETE FROM school_products WHERE school_id = ?")->execute([$oldSubId]);
                                    $pdo->prepare("DELETE FROM schools WHERE id = ?")->execute([$oldSubId]);
                                }
                            }

                            clearFastCache();
                            clearFastCache();
                        $msg = "UPDATED SUCCESSFULLY";
                            logActivity('update_school', 'schools', (int)$admin['id'], 'admin', "Updated multi-school group #{$id}");
                        }
                    } catch (Exception $e) {
                        $err = $e->getMessage();
                    }
                }
            }
        } elseif ($mode === 'sub_single') {
            // Direct update of an individual sub-school
            $name = trim($_POST['sub_single_name'] ?? '');
            $rawCode = trim($_POST['sub_single_code'] ?? '');
            $location = trim($_POST['sub_single_location'] ?? '');
            $contactPhone = trim($_POST['sub_single_contact_phone'] ?? '');

            if (empty($name)) {
                $err = 'Sub-School Name is mandatory.';
            } else {
                $rawClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawCode));
                if (!empty($rawClean)) {
                    $code = substr($rawClean, 0, 5);
                    $taken = isSchoolCodeTaken($pdo, $code, $id);
                    if ($taken) {
                        $err = "School code '{$code}' is already assigned to '{$taken['name']}'. School codes must be unique.";
                    }
                } else {
                    $code = generateUniqueSchoolCode($pdo, $name, $id);
                }

                if (empty($err)) {
                    $slug = generateSchoolSlug($pdo, $name, $id);
                    $city = !empty($location) ? $location : 'Trivandrum';

                    try {
                        $stmt = $pdo->prepare("UPDATE schools SET name = ?, slug = ?, code = ?, city = ?, location = ?, contact_phone = ? WHERE id = ?");
                        $stmt->execute([$name, $slug, $code, $city, $location, $contactPhone, $id]);
                        clearFastCache();
                        $msg = "UPDATED SUCCESSFULLY";
                        logActivity('update_school', 'schools', (int)$admin['id'], 'admin', "Updated sub-school #{$id} with code {$code}");
                    } catch (Exception $e) {
                        $err = $e->getMessage();
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            // If deleting a multi-school group, also clean up sub-schools
            $subIds = $pdo->query("SELECT id FROM schools WHERE parent_id = {$id}")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($subIds as $sId) {
                $pdo->prepare("DELETE FROM school_products WHERE school_id = ?")->execute([$sId]);
                $pdo->prepare("DELETE FROM schools WHERE id = ?")->execute([$sId]);
            }
            $pdo->prepare("DELETE FROM school_products WHERE school_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM schools WHERE id = ?")->execute([$id]);
            clearFastCache();
            $msg = "School removed successfully.";
            logActivity('delete_school', 'schools', (int)$admin['id'], 'admin', "Deleted school #{$id}");
        } catch (Exception $e) {
            $err = "Cannot delete school: It may have active student orders associated with it.";
        }
    }
}

// Fetch edit school if requested
$editId = (int)($_GET['edit'] ?? 0);
$editSchool = null;
$editSchoolParent = null;
$subSchoolsList = [];
$currentSchoolStructureMode = 'single';

if ($editId > 0) {
    $eStmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
    $eStmt->execute([$editId]);
    $editSchool = $eStmt->fetch();

    if ($editSchool) {
        if (!empty($editSchool['has_sub_schools'])) {
            $currentSchoolStructureMode = 'multi';
            $subStmt = $pdo->prepare("SELECT * FROM schools WHERE parent_id = ? ORDER BY sort_order ASC, id ASC");
            $subStmt->execute([$editSchool['id']]);
            $subSchoolsList = $subStmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (!empty($editSchool['parent_id'])) {
            $currentSchoolStructureMode = 'sub_single';
            $pStmt = $pdo->prepare("SELECT id, name FROM schools WHERE id = ?");
            $pStmt->execute([$editSchool['parent_id']]);
            $editSchoolParent = $pStmt->fetch();
        } else {
            $currentSchoolStructureMode = 'single';
        }
    }
}

// Fetch all schools with hierarchy details
$search = trim($_GET['search'] ?? '');
$schoolFilter = (int)($_GET['school_id'] ?? 0);
$subSchoolFilter = (int)($_GET['sub_school_id'] ?? 0);

// Main schools list (only main schools: standalone single schools and multi-school groups)
$mainSchools = $pdo->query("SELECT id, name, has_sub_schools FROM schools WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name ASC")->fetchAll();

// Under schools list (only sub-schools / campuses with their parent reference)
$underSchools = $pdo->query("SELECT s.id, s.name, s.parent_id, p.name as parent_name FROM schools s LEFT JOIN schools p ON s.parent_id = p.id WHERE s.parent_id IS NOT NULL AND s.parent_id > 0 ORDER BY s.name ASC")->fetchAll();

// Check if selected main school has sub-schools
$selectedMainHasSubs = false;
if ($schoolFilter > 0) {
    foreach ($mainSchools as $ms) {
        if ((int)$ms['id'] === $schoolFilter && !empty($ms['has_sub_schools'])) {
            $selectedMainHasSubs = true;
            break;
        }
    }
}

$sql = "SELECT s.*, 
        p.name as parent_name,
        (SELECT COUNT(*) FROM schools sub WHERE sub.parent_id = s.id) as sub_school_count,
        (SELECT COUNT(*) FROM school_products sp WHERE sp.school_id = s.id) as assigned_products,
        (SELECT COUNT(*) FROM orders o WHERE o.school_id = s.id) as order_count
        FROM schools s 
        LEFT JOIN schools p ON s.parent_id = p.id
        WHERE 1=1";

if ($subSchoolFilter > 0) {
    $sql .= " AND s.id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $sql .= " AND (s.id = {$schoolFilter} OR s.parent_id = {$schoolFilter})";
}

if (!empty($search)) {
    $sql .= " AND (s.name LIKE " . $pdo->quote("%{$search}%") . " OR s.city LIKE " . $pdo->quote("%{$search}%") . " OR s.code LIKE " . $pdo->quote("%{$search}%") . ")";
}
$sql .= " ORDER BY COALESCE(s.parent_id, s.id) ASC, s.parent_id IS NOT NULL ASC, s.id ASC";
$schoolsList = $pdo->query($sql)->fetchAll();

// Pre-group sub-schools by parent for seamless modal editing
$allSubSchoolsByParent = [];
$subs = $pdo->query("SELECT id, name, code, location, city, contact_phone, parent_id FROM schools WHERE parent_id IS NOT NULL ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($subs as $sub) {
    $allSubSchoolsByParent[(int)$sub['parent_id']][] = $sub;
}
?>

<!-- Action Status Alerts -->
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

<!-- ==================== REGISTRATION SECTION (COLLAPSIBLE - DISPLAYS ONLY ON CLICK) ==================== -->
<div class="registration-toggle-box" style="background:#ffffff; border-radius:var(--radius-xl); border:1.5px solid var(--brand-border); box-shadow:var(--shadow-sm); margin-bottom:2.5rem; overflow:hidden;">
    <div id="register-header-toggle" onclick="toggleRegisterForm()" style="padding:1.4rem 2rem; display:flex; justify-content:space-between; align-items:center; cursor:pointer; user-select:none; transition:all 0.2s;" onmouseover="this.style.background='#FFF9F5';" onmouseout="if(!document.getElementById('register-school-form-body').classList.contains('is-open')) this.style.background='#ffffff';">
        <div>
            <h3 class="heading-2" style="font-size:1.35rem; margin:0; display:flex; align-items:center; gap:0.65rem; color:#0F172A;">
                <span>Register New Partner School</span>
            </h3>
            <p style="font-size:0.84rem; color:var(--brand-muted); margin:0.3rem 0 0 0;">
                Configure school structure based on the public website layout. Choose Single School or Multi School group.
            </p>
        </div>
        <button type="button" class="btn btn-primary" id="btn-toggle-register-pill" style="border-radius:9999px; font-weight:800; padding:0.6rem 1.4rem; pointer-events:none; box-shadow:0 4px 14px rgba(255,103,2,0.35); font-size:0.9rem;">
            <span id="btn-toggle-text">+ Register New Partner School</span>
        </button>
    </div>

    <!-- Hidden by default: displays ONLY when clicking Register New Partner School -->
    <div id="register-school-form-body" style="display:none; padding:1.5rem 2.5rem 2.5rem 2.5rem; border-top:1.5px solid #F1F5F9; background:#ffffff;">
        <form action="<?= BASE_URL ?>/admin/schools.php" method="POST" id="school-upload-form">
            <input type="hidden" name="action" value="create">

            <!-- 1. Choose Single School or Multi School -->
            <div style="margin-bottom: 2rem;">
                <label style="display:block; font-weight:800; font-size:0.95rem; color:#1E293B; margin-bottom:0.75rem;">
                    1. Choose School Type / Structure *
                </label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; align-items:start;">
                    
                    <!-- Single School Option -->
                    <label class="school-type-option-card selected" id="card-type-single" onclick="switchSchoolStructureMode('single')" style="border: 2px solid #FF6702; background: #FFF9F5; border-radius: 14px; padding: 1rem 1.4rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.85rem; position: relative; margin:0;">
                        <input type="radio" name="school_structure_mode" value="single" checked onchange="switchSchoolStructureMode('single')" style="accent-color:#FF6702; width:20px; height:20px; margin:0;">
                        <div style="font-weight: 800; font-size: 1.05rem; color: #1E293B;">
                            🏫 Single School
                        </div>
                    </label>

                    <!-- Multi School Option Column -->
                    <div style="display:flex; flex-direction:column; gap:0.6rem;">
                        <label class="school-type-option-card" id="card-type-multi" onclick="switchSchoolStructureMode('multi')" style="border: 2px solid #E2E8F0; background: #ffffff; border-radius: 14px; padding: 1rem 1.4rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.85rem; position: relative; margin:0;">
                            <input type="radio" name="school_structure_mode" value="multi" onchange="switchSchoolStructureMode('multi')" style="accent-color:#FF6702; width:20px; height:20px; margin:0;">
                            <div style="font-weight: 800; font-size: 1.05rem; color: #1E293B;">
                                🏛️ Multi School (Group)
                            </div>
                        </label>

                        <!-- Sub-unit label configuration: directly below Multi School (Group) -->
                        <div id="multi-unit-config-create" style="display:none; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:0.65rem 1rem;">
                            <label style="display:inline-flex; align-items:center; gap:0.55rem; font-weight:700; font-size:0.92rem; color:#1E293B; cursor:pointer; margin:0; user-select:none;">
                                <input type="checkbox" id="chk_is_school_create" name="is_school_unit" value="1" checked onchange="toggleUnitLabelField('create', this.checked)" style="width:18px; height:18px; accent-color:#FF6702; cursor:pointer;">
                                <span>School</span>
                            </label>
                            
                            <!-- Displayed if unchecked -->
                            <div id="custom-unit-box-create" style="display:none; margin-top:0.5rem;">
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <span style="font-size:0.85rem; font-weight:700; color:#475569; white-space:nowrap;">Select</span>
                                    <input type="text" name="sub_school_label" id="input_sub_school_label_create" placeholder="Enter label (e.g. Campus, Branch)" class="form-control" style="font-size:0.85rem; padding:0.35rem 0.65rem; height:auto;">
                                </div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:0.25rem;">Visible on public order page as: Select &quot;&lt;your text&gt;&quot;</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Single School Form Section -->
            <div id="section-single-school" style="display: block;">
                <div style="font-weight:800; font-size:0.95rem; color:#1E293B; margin-bottom:1rem; border-bottom:1px solid #E2E8F0; padding-bottom:0.5rem;">
                    2. Single School Details
                </div>

                <div style="display:grid; grid-template-columns: 1.5fr 0.8fr 1fr 1fr; gap:1.25rem; margin-bottom:1.5rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">School Name *</label>
                        <input type="text" name="single_name" id="single_name_input" placeholder="Enter your school name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">School Code (3 Letters) *</label>
                        <input type="text" name="single_code" id="single_code_input" placeholder="e.g. TIN" maxlength="3" class="form-control" style="text-transform:uppercase; font-weight:700; letter-spacing:1px;" oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3);">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">Campus Location</label>
                        <input type="text" name="single_location" placeholder="Enter your campus location" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">Contact Phone</label>
                        <input type="tel" name="single_contact_phone" placeholder="Enter your contact phone" class="form-control">
                    </div>
                </div>
            </div>

            <!-- Multi School Form Section -->
            <div id="section-multi-school" style="display: none;">
                <div style="font-weight:800; font-size:0.95rem; color:#1E293B; margin-bottom:1rem; border-bottom:1px solid #E2E8F0; padding-bottom:0.5rem;">
                    2. Common School Name
                </div>

                <div style="margin-bottom:1.5rem; max-width:650px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label" style="font-weight:700;">Common School Name *</label>
                        <input type="text" name="multi_name" id="multi_name_input" placeholder="Enter your school name" class="form-control">
                    </div>
                </div>

                <!-- Individual Schools Under This Group Container -->
                <div style="background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:16px; padding:1.5rem; margin-bottom:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
                        <div>
                            <h4 style="margin:0; font-size:1.05rem; font-weight:800; color:#1E293B;">
                                📚 Individual Schools Under This Group *
                            </h4>
                            <p style="margin:0.2rem 0 0; font-size:0.8rem; color:#64748B;">
                                When parents click the common school on the public website, these individual schools will be displayed for selection.
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addSubSchoolRow()" style="border-radius:9999px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
                            <span>+ Add Another School</span>
                        </button>
                    </div>

                    <div id="sub-schools-container" style="display:flex; flex-direction:column; gap:0.85rem;">
                        <div class="sub-school-row" style="background:#ffffff; border:1.5px solid #E2E8F0; border-radius:12px; padding:1rem 1.25rem; display:grid; grid-template-columns: 2fr 0.8fr 1.5fr 1fr auto; gap:1rem; align-items:center;">
                            <input type="hidden" name="sub_school_id[]" value="0">
                            <div>
                                <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Individual School Name *</label>
                                <input type="text" name="sub_school_name[]" placeholder="Enter your school name" class="form-control" style="font-size:0.9rem;">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Code (3 Letters)</label>
                                <input type="text" name="sub_school_code[]" placeholder="e.g. GPS" maxlength="3" class="form-control" style="font-size:0.9rem; text-transform:uppercase; font-weight:700;" oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3);">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Campus / Board / Location</label>
                                <input type="text" name="sub_school_location[]" placeholder="Enter your campus location" class="form-control" style="font-size:0.9rem;">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Contact Phone</label>
                                <input type="tel" name="sub_school_phone[]" placeholder="Enter your contact phone" class="form-control" style="font-size:0.9rem;">
                            </div>
                            <div style="padding-top:1.2rem;">
                                <button type="button" onclick="removeSubSchoolRow(this)" class="btn btn-outline btn-sm" style="color:#DC2626; border-color:#FCA5A5; padding:0.4rem 0.65rem;" title="Remove this sub-school">
                                    ✕
                                </button>
                            </div>
                        </div>

                        <div class="sub-school-row" style="background:#ffffff; border:1.5px solid #E2E8F0; border-radius:12px; padding:1rem 1.25rem; display:grid; grid-template-columns: 2fr 0.8fr 1.5fr 1fr auto; gap:1rem; align-items:center;">
                            <input type="hidden" name="sub_school_id[]" value="0">
                            <div>
                                <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Individual School Name *</label>
                                <input type="text" name="sub_school_name[]" placeholder="Enter your school name" class="form-control" style="font-size:0.9rem;">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Code (3 Letters)</label>
                                <input type="text" name="sub_school_code[]" placeholder="e.g. GPS" maxlength="3" class="form-control" style="font-size:0.9rem; text-transform:uppercase; font-weight:700;" oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3);">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Campus / Board / Location</label>
                                <input type="text" name="sub_school_location[]" placeholder="Enter your campus location" class="form-control" style="font-size:0.9rem;">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Contact Phone</label>
                                <input type="tel" name="sub_school_phone[]" placeholder="Enter your contact phone" class="form-control" style="font-size:0.9rem;">
                            </div>
                            <div style="padding-top:1.2rem;">
                                <button type="button" onclick="removeSubSchoolRow(this)" class="btn btn-outline btn-sm" style="color:#DC2626; border-color:#FCA5A5; padding:0.4rem 0.65rem;" title="Remove this sub-school">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="school-submit-btn" style="background:#FF6702; border:none; padding:0.75rem 1.75rem; font-weight:800; border-radius:var(--radius-pill); cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                <span id="school-submit-btn-text">Save Single School</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </button>
        </form>
    </div>
</div>

<script>
function toggleRegisterForm() {
    const formBody = document.getElementById('register-school-form-body');
    const btnText = document.getElementById('btn-toggle-text');
    const headerToggle = document.getElementById('register-header-toggle');
    if (!formBody) return;

    if (formBody.style.display === 'none' || !formBody.style.display) {
        formBody.style.display = 'block';
        formBody.classList.add('is-open');
        if (btnText) btnText.textContent = '✕ Close Form';
        if (headerToggle) headerToggle.style.background = '#FFF9F5';
    } else {
        formBody.style.display = 'none';
        formBody.classList.remove('is-open');
        if (btnText) btnText.textContent = '+ Register New Partner School';
        if (headerToggle) headerToggle.style.background = '#ffffff';
    }
}

function switchSchoolStructureMode(mode) {
    const radio = document.querySelector('input[name="school_structure_mode"][value="' + mode + '"]');
    if (radio) radio.checked = true;

    const cardSingle = document.getElementById('card-type-single');
    const cardMulti = document.getElementById('card-type-multi');
    const secSingle = document.getElementById('section-single-school');
    const secMulti = document.getElementById('section-multi-school');
    const singleInput = document.getElementById('single_name_input');
    const multiInput = document.getElementById('multi_name_input');
    const submitText = document.getElementById('school-submit-btn-text');

    const unitConfigCreate = document.getElementById('multi-unit-config-create');

    if (mode === 'multi') {
        if (cardSingle) { cardSingle.classList.remove('selected'); cardSingle.style.borderColor = '#E2E8F0'; cardSingle.style.background = '#ffffff'; }
        if (cardMulti) { cardMulti.classList.add('selected'); cardMulti.style.borderColor = '#FF6702'; cardMulti.style.background = '#FFF9F5'; }
        if (unitConfigCreate) { unitConfigCreate.style.display = 'block'; unitConfigCreate.style.borderColor = '#FED7AA'; unitConfigCreate.style.background = '#FFFBF7'; }
        if (secSingle) secSingle.style.display = 'none';
        if (secMulti) secMulti.style.display = 'block';
        if (singleInput) singleInput.removeAttribute('required');
        if (multiInput) multiInput.setAttribute('required', 'required');
        if (submitText) submitText.textContent = 'Save Multi School Group';
    } else {
        if (cardSingle) { cardSingle.classList.add('selected'); cardSingle.style.borderColor = '#FF6702'; cardSingle.style.background = '#FFF9F5'; }
        if (cardMulti) { cardMulti.classList.remove('selected'); cardMulti.style.borderColor = '#E2E8F0'; cardMulti.style.background = '#ffffff'; }
        if (unitConfigCreate) { unitConfigCreate.style.display = 'none'; unitConfigCreate.style.borderColor = '#E2E8F0'; unitConfigCreate.style.background = '#F8FAFC'; }
        if (secSingle) secSingle.style.display = 'block';
        if (secMulti) secMulti.style.display = 'none';
        if (singleInput) singleInput.setAttribute('required', 'required');
        if (multiInput) multiInput.removeAttribute('required');
        if (submitText) submitText.textContent = 'Save Single School';
    }
}

function toggleUnitLabelField(context, isChecked) {
    const box = document.getElementById('custom-unit-box-' + context);
    const input = document.getElementById('input_sub_school_label_' + context);
    if (!box) return;

    if (isChecked) {
        box.style.display = 'none';
        if (input) input.value = '';
    } else {
        box.style.display = 'block';
        if (input) {
            input.focus();
        }
    }

    if (context === 'create') {
        const multiRadio = document.querySelector('input[name="school_structure_mode"][value="multi"]');
        if (multiRadio && !multiRadio.checked) {
            multiRadio.checked = true;
            switchSchoolStructureMode('multi');
        }
    }
}

function addSubSchoolRow() {
    const container = document.getElementById('sub-schools-container');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'sub-school-row';
    row.style.cssText = 'background:#ffffff; border:1.5px solid #E2E8F0; border-radius:12px; padding:1rem 1.25rem; display:grid; grid-template-columns: 2fr 0.8fr 1.5fr 1fr auto; gap:1rem; align-items:center;';
    row.innerHTML = `
        <input type="hidden" name="sub_school_id[]" value="0">
        <div>
            <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Individual School Name *</label>
            <input type="text" name="sub_school_name[]" required placeholder="Enter your school name" class="form-control" style="font-size:0.9rem;">
        </div>
        <div>
            <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Code (3 Letters)</label>
            <input type="text" name="sub_school_code[]" placeholder="e.g. GPS" maxlength="3" class="form-control" style="font-size:0.9rem; text-transform:uppercase; font-weight:700;" oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3);">
        </div>
        <div>
            <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Campus / Board / Location</label>
            <input type="text" name="sub_school_location[]" placeholder="Enter your campus location" class="form-control" style="font-size:0.9rem;">
        </div>
        <div>
            <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Contact Phone</label>
            <input type="tel" name="sub_school_phone[]" placeholder="Enter your contact phone" class="form-control" style="font-size:0.9rem;">
        </div>
        <div style="padding-top:1.2rem;">
            <button type="button" onclick="removeSubSchoolRow(this)" class="btn btn-outline btn-sm" style="color:#DC2626; border-color:#FCA5A5; padding:0.4rem 0.65rem;" title="Remove this sub-school">
                ✕
            </button>
        </div>
    `;
    container.appendChild(row);
}

function removeSubSchoolRow(btn) {
    const rows = document.querySelectorAll('.sub-school-row');
    if (rows.length <= 1) {
        alert('A Multi School group must have at least one individual school.');
        return;
    }
    const row = btn.closest('.sub-school-row');
    if (row) row.remove();
}

function handleMainSchoolDirectoryChange(mainSelect) {
    const subSelect = document.getElementById('schools_sub_school_id_select');
    if (subSelect) {
        subSelect.value = '';
    }
    mainSelect.form.submit();
}
</script>

<!-- ==================== SCHOOLS DIRECTORY TABLE ==================== -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem;">
    <h3 class="heading-2" style="font-size:1.3rem; margin:0;">Registered Partner Schools (<?= count($schoolsList) ?>)</h3>

    <form action="" method="GET" style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap; margin:0;">
        <!-- Main Schools Filter -->
        <select name="school_id" id="schools_school_id_select" class="form-control" style="font-size:0.82rem; padding:0.35rem 0.65rem; border-radius:var(--radius-md); width:140px;" onchange="handleMainSchoolDirectoryChange(this)">
            <option value="">All Schools</option>
            <?php foreach ($mainSchools as $s): ?>
                <option value="<?= $s['id'] ?>" data-has-subs="<?= (int)$s['has_sub_schools'] ?>" <?= $schoolFilter === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= sanitize($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Under Schools Filter -->
        <select name="sub_school_id" id="schools_sub_school_id_select" class="form-control" style="font-size:0.82rem; padding:0.35rem 0.65rem; border-radius:var(--radius-md); width:145px;" onchange="this.form.submit()" <?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'disabled' : '' ?>>
            <option value=""><?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'No Under Schools' : 'All Under Schools' ?></option>
            <?php foreach ($underSchools as $sub): ?>
                <?php 
                    if ($schoolFilter > 0 && (int)$sub['parent_id'] !== $schoolFilter) {
                        continue;
                    }
                ?>
                <option value="<?= $sub['id'] ?>" data-parent="<?= (int)$sub['parent_id'] ?>" <?= $subSchoolFilter === (int)$sub['id'] ? 'selected' : '' ?>>
                    <?= sanitize($sub['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search school name…" class="form-control" style="padding:0.35rem 0.55rem; font-size:0.82rem; width:160px; border-radius:var(--radius-md);">
        <button type="submit" class="btn btn-primary btn-sm" style="padding:0.35rem 0.75rem; font-size:0.8rem;">Filter</button>
        <?php if (!empty($search) || $schoolFilter > 0 || $subSchoolFilter > 0): ?>
            <a href="<?= BASE_URL ?>/admin/schools.php" class="btn btn-outline btn-sm" style="padding:0.35rem 0.65rem; font-size:0.8rem;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-scroll-frame" style="max-height:520px; overflow-y:auto; overflow-x:auto; position:relative; background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); margin-bottom:3rem;">
    <table class="admin-table" style="margin:0; border:none; border-radius:0;">
        <thead>
            <tr>
                <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">School Name</th>
                <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Location</th>
                <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Assigned Uniforms</th>
                <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Status</th>
                <th style="text-align:right; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($schoolsList)): ?>
                <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--brand-muted);">No schools match the search query.</td></tr>
            <?php else: ?>
                <?php foreach ($schoolsList as $s): ?>
                    <tr>
                        <td>
                            <div style="font-weight:800; color:var(--brand-dark); font-size:0.95rem; display:flex; align-items:center; gap:6px;">
                                <span><?= sanitize($s['name']) ?></span>
                                <span style="font-size:0.75rem; font-weight:800; background:#FFF1E8; color:#FF6702; padding:2px 7px; border-radius:6px; border:1px solid #FED7AA; letter-spacing:0.5px;"><?= sanitize($s['code']) ?></span>
                            </div>
                            <?php if (!empty($s['has_sub_schools'])): ?>
                                <div style="margin-top:0.25rem;">
                                    <span style="font-size:0.72rem; font-weight:700; color:#9A3412; background:#FFF7ED; border:1px solid #FFEDD5; padding:0.12rem 0.5rem; border-radius:9999px; display:inline-flex; align-items:center; gap:4px;" title="Configured Step 1 Selection Unit for this school on public portal">
                                        Selection Unit: Select &ldquo;<?= sanitize($s['sub_school_label'] ?: 'School') ?>&rdquo;
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?= sanitize($s['location'] ?: $s['city']) ?></div>
                            <div style="font-size:0.78rem; color:var(--brand-muted);"><?= sanitize($s['contact_phone'] ?: 'No phone') ?></div>
                        </td>
                        <td>
                            <?php if (!empty($s['has_sub_schools'])): ?>
                                <?php
                                $subUniCount = (int)$pdo->query("SELECT COUNT(DISTINCT sp.product_id) FROM school_products sp JOIN schools sub ON sp.school_id = sub.id WHERE sub.parent_id = " . (int)$s['id'])->fetchColumn();
                                ?>
                                <span style="font-weight:800; color:#EA580C; font-size:0.85rem;"><?= $subUniCount ?> Uniforms across <?= (int)$s['sub_school_count'] ?> Sub-Schools</span>
                            <?php else: ?>
                                <span style="font-weight:700; color:var(--brand-dark);"><?= $s['assigned_products'] ?> Uniforms</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $s['status'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-neutral">Inactive</span>' ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:inline-flex; gap:0.4rem;">
                                <?php
                                $subUniText = '';
                                if (!empty($s['has_sub_schools'])) {
                                    $subUniText = $subUniCount . ' Uniforms across ' . (int)$s['sub_school_count'] . ' Sub-Schools';
                                } else {
                                    $subUniText = $s['assigned_products'] . ' Uniforms';
                                }

                                $structureType = 'Single School';
                                if (!empty($s['has_sub_schools'])) {
                                    $structureType = 'Multi School (' . (int)$s['sub_school_count'] . ' Individual Schools)';
                                } elseif (!empty($s['parent_id'])) {
                                    $structureType = 'Sub-School of ' . ($s['parent_name'] ?? 'Main Group');
                                }

                                $viewData = [
                                    'id' => (int)$s['id'],
                                    'name' => $s['name'],
                                    'code' => $s['code'] ?: 'ID: ' . $s['id'],
                                    'structure' => $structureType,
                                    'location' => $s['location'] ?: ($s['city'] ?: 'Trivandrum'),
                                    'contact_phone' => $s['contact_phone'] ?: 'No phone provided',
                                    'uniforms' => $subUniText,
                                    'status' => $s['status'] ? 'Active' : 'Inactive',
                                    'portal_url' => BASE_URL . '/schools/' . $s['slug'] . '/order/',
                                    'has_sub_schools' => (int)$s['has_sub_schools'],
                                    'parent_id' => !empty($s['parent_id']) ? (int)$s['parent_id'] : null,
                                    'parent_name' => $s['parent_name'] ?? '',
                                    'sub_schools' => !empty($s['has_sub_schools']) ? ($allSubSchoolsByParent[$s['id']] ?? []) : []
                                ];

                                $editModalData = [
                                    'id' => (int)$s['id'],
                                    'name' => $s['name'],
                                    'code' => $s['code'] ?: '',
                                    'location' => $s['location'] ?: $s['city'],
                                    'contact_phone' => $s['contact_phone'] ?: '',
                                    'has_sub_schools' => (int)$s['has_sub_schools'],
                                    'sub_school_label' => $s['sub_school_label'] ?: 'School',
                                    'parent_id' => !empty($s['parent_id']) ? (int)$s['parent_id'] : null,
                                    'parent_name' => $s['parent_name'] ?? '',
                                    'sub_schools' => !empty($s['has_sub_schools']) ? ($allSubSchoolsByParent[$s['id']] ?? []) : []
                                ];
                                ?>
                                <button type="button" 
                                        class="btn btn-outline btn-sm" 
                                        style="padding:0.35rem 0.55rem; line-height:1; display:inline-flex; align-items:center; justify-content:center; color:#2563EB; border-color:#BFDBFE; background:#F8FAFC; border-radius:var(--radius-md);" 
                                        title="View School Profile" 
                                        onclick='openSchoolModal(<?= json_encode($viewData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>

                                <button type="button" 
                                        class="btn btn-primary btn-sm" 
                                        style="padding:0.35rem 0.55rem; line-height:1; display:inline-flex; align-items:center; justify-content:center; background:var(--brand-primary); color:#ffffff; box-shadow:0 2px 6px rgba(255,103,2,0.25); border-radius:var(--radius-md);" 
                                        title="Edit School Settings" 
                                        onclick='openEditSchoolModal(<?= json_encode($editModalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                    </svg>
                                </button>

                                <form action="<?= BASE_URL ?>/admin/schools.php" method="POST" data-confirm="delete" data-item-name="<?= htmlspecialchars($s['name']) ?>" style="display:inline; margin:0;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" 
                                            class="btn btn-outline btn-sm" 
                                            title="Delete School" 
                                            style="padding:0.35rem 0.55rem; line-height:1; color:#dc2626; border-color:rgba(220,38,38,0.3); background:#ffffff; display:inline-flex; align-items:center; justify-content:center; border-radius:var(--radius-md);">
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

<!-- ==================== SCHOOL DETAILS VIEW POPUP MODAL ==================== -->
<div id="school-view-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; align-items:center; justify-content:center; padding:1.25rem; backdrop-filter:blur(4px);">
    <div style="background:#ffffff; border-radius:20px; max-width:580px; width:100%; border:2px solid #FF6702; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); overflow:hidden; animation:svmPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Modal Header -->
        <div style="background:linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); padding:1.25rem 1.75rem; border-bottom:1.5px solid #FED7AA; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <span style="font-size:1.6rem; line-height:1;">🏫</span>
                <div>
                    <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:#1E293B;">School Profile Details</h3>
                </div>
            </div>
            <button type="button" onclick="closeSchoolModal()" style="background:transparent; border:none; color:#64748B; font-size:1.75rem; cursor:pointer; line-height:1; padding:0; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.2s;" onmouseover="this.style.background='#FED7AA'; this.style.color='#1E293B';" onmouseout="this.style.background='transparent'; this.style.color='#64748B';">&times;</button>
        </div>

        <!-- Modal Body: Saved Details Grid -->
        <div style="padding:1.5rem 1.75rem; max-height:75vh; overflow-y:auto;">
            <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:1.25rem; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:1.25rem; margin-bottom:1.25rem;">
                <div style="grid-column: span 2;">
                    <span style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:#64748B; display:block; margin-bottom:0.25rem;">School Full Name & Code</span>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <strong id="svm-name" style="font-size:1.15rem; color:#0F172A; font-weight:800;"></strong>
                        <span id="svm-code" style="font-size:0.78rem; font-weight:800; background:#FFF1E8; color:#FF6702; padding:2px 8px; border-radius:6px; border:1px solid #FED7AA; letter-spacing:0.5px;"></span>
                    </div>
                </div>

                <div>
                    <span style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:#64748B; display:block; margin-bottom:0.25rem;">Account Status</span>
                    <span id="svm-status"></span>
                </div>

                <div>
                    <span style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:#64748B; display:block; margin-bottom:0.25rem;">Contact Phone</span>
                    <span id="svm-phone" style="font-size:0.92rem; font-weight:700; color:#334155; display:block;"></span>
                </div>

                <div style="grid-column: span 2;">
                    <span style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:#64748B; display:block; margin-bottom:0.25rem;">Campus Location</span>
                    <span id="svm-location" style="font-size:0.92rem; font-weight:700; color:#334155; display:block;"></span>
                </div>

                <div style="grid-column: span 2;">
                    <span style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:#64748B; display:block; margin-bottom:0.25rem;">Assigned Uniform Collection</span>
                    <span id="svm-uniforms" style="font-size:0.95rem; font-weight:800; color:#EA580C; display:block;"></span>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="background:#F8FAFC; padding:1rem 1.75rem; border-top:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
            <button type="button" id="svm-edit-btn" class="btn btn-primary btn-sm" style="background:#FF6702; border:none; font-weight:800; padding:0.55rem 1.25rem; border-radius:9999px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                <span>Edit Profile</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            </button>
            <button type="button" onclick="closeSchoolModal()" class="btn btn-outline btn-sm" style="font-weight:700; padding:0.55rem 1.25rem; border-radius:9999px;">
                Close
            </button>
        </div>

    </div>
</div>

<!-- ==================== SCHOOL EDIT POPUP WINDOW MODAL (SEPARATE EDIT WINDOW) ==================== -->
<div id="school-edit-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:99999; align-items:center; justify-content:center; padding:1.25rem; backdrop-filter:blur(5px); overflow-y:auto;">
    <div style="background:#ffffff; border-radius:22px; max-width:680px; width:100%; border:2px solid #FF6702; box-shadow:0 25px 60px -10px rgba(0,0,0,0.4); overflow:hidden; animation:svmPop 0.22s cubic-bezier(0.16, 1, 0.3, 1); margin:auto;">
        
        <!-- Modal Header -->
        <div style="background:linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); padding:1.25rem 1.75rem; border-bottom:1.5px solid #FED7AA; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <span style="font-size:1.6rem; line-height:1;">✏️</span>
                <div>
                    <h3 id="sem-modal-title" style="margin:0; font-size:1.2rem; font-weight:800; color:#1E293B;">Edit School Profile</h3>
                    <p style="margin:0.2rem 0 0; font-size:0.8rem; color:#64748B;">Update school details directly in this window</p>
                </div>
            </div>
            <button type="button" onclick="closeEditSchoolModal()" style="background:transparent; border:none; color:#64748B; font-size:1.75rem; cursor:pointer; line-height:1; padding:0; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.2s;" onmouseover="this.style.background='#FED7AA'; this.style.color='#1E293B';" onmouseout="this.style.background='transparent'; this.style.color='#64748B';">&times;</button>
        </div>

        <!-- Form inside popup window -->
        <form action="<?= BASE_URL ?>/admin/schools.php" method="POST" id="school-edit-modal-form" style="padding:1.5rem 1.75rem;">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="sem-id" value="">
            <input type="hidden" name="school_structure_mode" id="sem-mode" value="single">

            <!-- School Type / Structure Toggle in Edit Modal (Requirement 1) -->
            <div id="sem-type-selector-wrap" style="background:#FFF9F5; border:1.5px solid #FFEDD5; border-radius:12px; padding:0.85rem 1.25rem; margin-bottom:1.25rem;">
                <label style="font-weight:800; font-size:0.82rem; color:#475569; text-transform:uppercase; letter-spacing:0.04em; display:block; margin-bottom:0.6rem;">
                    1. Choose School Type / Structure * (Freely Switchable Anytime)
                </label>
                <div style="display:flex; gap:1rem; align-items:center;">
                    <label style="display:inline-flex; align-items:center; gap:0.5rem; font-weight:700; font-size:0.92rem; color:#1E293B; cursor:pointer; background:#ffffff; padding:0.5rem 1rem; border-radius:8px; border:2px solid #CBD5E1; transition:all 0.2s;" id="sem-lbl-type-single">
                        <input type="radio" name="edit_school_type_toggle" id="sem-radio-type-single" value="single" onchange="semToggleSchoolType('single')" style="accent-color:#FF6702; width:17px; height:17px; cursor:pointer;">
                        <span>🏫 Single School</span>
                    </label>
                    <label style="display:inline-flex; align-items:center; gap:0.5rem; font-weight:700; font-size:0.92rem; color:#1E293B; cursor:pointer; background:#ffffff; padding:0.5rem 1rem; border-radius:8px; border:2px solid #CBD5E1; transition:all 0.2s;" id="sem-lbl-type-multi">
                        <input type="radio" name="edit_school_type_toggle" id="sem-radio-type-multi" value="multi" onchange="semToggleSchoolType('multi')" style="accent-color:#FF6702; width:17px; height:17px; cursor:pointer;">
                        <span>🏛️ Multi School (Group)</span>
                    </label>
                </div>
            </div>

            <!-- 1. Single School Edit Section -->
            <div id="sem-section-single" style="display:none;">
                <div style="display:grid; grid-template-columns: 1.5fr 0.8fr 1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">School Name *</label>
                        <input type="text" name="single_name" id="sem-single-name" placeholder="Enter your school name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">School Code (3 Letters) *</label>
                        <input type="text" name="single_code" id="sem-single-code" placeholder="e.g. TIN" maxlength="3" class="form-control" style="text-transform:uppercase; font-weight:700; letter-spacing:1px;" oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3);">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">Campus Location</label>
                        <input type="text" name="single_location" id="sem-single-location" placeholder="Enter your campus location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">Contact Phone</label>
                        <input type="tel" name="single_contact_phone" id="sem-single-phone" placeholder="Enter your contact phone" class="form-control">
                    </div>
                </div>
            </div>

            <!-- 2. Sub-School Edit Section -->
            <div id="sem-section-subsingle" style="display:none;">
                <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:12px; padding:0.85rem 1.25rem; margin-bottom:1.25rem;">
                    <span style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#1D4ED8; display:block;">Individual Sub-School</span>
                    <strong id="sem-parent-banner-name" style="color:#1E3A8A; font-size:0.95rem;"></strong>
                </div>
                <div style="display:grid; grid-template-columns: 1.5fr 0.8fr 1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">Sub-School / Campus Name *</label>
                        <input type="text" name="sub_single_name" id="sem-subsingle-name" placeholder="Enter your school name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">Code (3 Letters) *</label>
                        <input type="text" name="sub_single_code" id="sem-subsingle-code" placeholder="e.g. GPS" maxlength="3" class="form-control" style="text-transform:uppercase; font-weight:700; letter-spacing:1px;" oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3);">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">Campus Location / Board</label>
                        <input type="text" name="sub_single_location" id="sem-subsingle-location" placeholder="Enter your campus location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:700;">Contact Phone</label>
                        <input type="tel" name="sub_single_contact_phone" id="sem-subsingle-phone" placeholder="Enter your contact phone" class="form-control">
                    </div>
                </div>
            </div>

            <!-- 3. Multi School Group Edit Section -->
            <div id="sem-section-multi" style="display:none;">
                <div style="margin-bottom:1.25rem; max-width:650px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label" style="font-weight:700;">Common School Name *</label>
                        <input type="text" name="multi_name" id="sem-multi-name" placeholder="Enter your school name" class="form-control">
                    </div>
                </div>

                <!-- Sub-unit label configuration in Edit Modal -->
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:0.75rem 1rem; margin-bottom:1.25rem;">
                    <div style="font-weight:700; font-size:0.82rem; color:#475569; margin-bottom:0.4rem;">Public Site Step 1 Selection Unit:</div>
                    <label style="display:inline-flex; align-items:center; gap:0.55rem; font-weight:700; font-size:0.92rem; color:#1E293B; cursor:pointer; margin:0; user-select:none;">
                        <input type="checkbox" id="chk_is_school_edit" name="is_school_unit" value="1" checked onchange="toggleUnitLabelField('edit', this.checked)" style="width:18px; height:18px; accent-color:#FF6702; cursor:pointer;">
                        <span>School</span>
                    </label>
                    
                    <div id="custom-unit-box-edit" style="display:none; margin-top:0.5rem;">
                        <div style="display:flex; align-items:center; gap:0.4rem;">
                            <span style="font-size:0.85rem; font-weight:700; color:#475569; white-space:nowrap;">Select</span>
                            <input type="text" name="sub_school_label" id="input_sub_school_label_edit" placeholder="Enter label (e.g. Campus, Branch)" class="form-control" style="font-size:0.85rem; padding:0.35rem 0.65rem; height:auto; max-width:320px;">
                        </div>
                        <div style="font-size:0.75rem; color:#94A3B8; margin-top:0.25rem;">Visible on public site order page: Select &quot;&lt;your text&gt;&quot;</div>
                    </div>
                </div>

                <div style="background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:14px; padding:1.25rem; margin-bottom:1.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem;">
                        <h4 style="margin:0; font-size:1rem; font-weight:800; color:#1E293B;">📚 Individual Schools Under This Group</h4>
                        <button type="button" class="btn btn-outline btn-sm" onclick="semAddSubSchoolRow()" style="border-radius:9999px; font-weight:700; font-size:0.75rem;">+ Add Another School</button>
                    </div>
                    <div id="sem-subschools-container" style="display:flex; flex-direction:column; gap:0.75rem;"></div>
                </div>
            </div>

            <!-- Modal Actions Footer -->
            <div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1px solid #F1F5F9; padding-top:1.25rem; margin-top:0.5rem;">
                <button type="button" onclick="closeEditSchoolModal()" class="btn btn-outline" style="border-radius:9999px; font-weight:700; padding:0.6rem 1.4rem;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="sem-submit-btn" style="background:#FF6702; border:none; padding:0.65rem 1.85rem; font-weight:800; border-radius:9999px; cursor:pointer;">
                    <span id="sem-submit-btn-text">Update School Profile</span>
                </button>
            </div>
        </form>

    </div>
</div>

<style>
@keyframes svmPop {
    from { opacity: 0; transform: scale(0.92) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.registration-toggle-box {
    transition: all 0.25s ease;
}
</style>

<script>
// ==================== VIEW MODAL LOGIC ====================
let currentSelectedViewData = null;

function openSchoolModal(data) {
    if (!data) return;
    currentSelectedViewData = data;

    const nameEl = document.getElementById('svm-name');
    if (nameEl) nameEl.textContent = data.name || '';

    const codeEl = document.getElementById('svm-code');
    if (codeEl) codeEl.textContent = data.code || '';

    const locEl = document.getElementById('svm-location');
    if (locEl) locEl.textContent = data.location || '';

    const phoneEl = document.getElementById('svm-phone');
    if (phoneEl) phoneEl.textContent = data.contact_phone || 'No phone provided';

    const uniformsEl = document.getElementById('svm-uniforms');
    if (uniformsEl) uniformsEl.textContent = data.uniforms || '';
    
    // Status Badge
    const statusEl = document.getElementById('svm-status');
    if (statusEl) {
        if (data.status === 'Active') {
            statusEl.innerHTML = '<span class="badge badge-success" style="font-size:0.8rem; padding:0.2rem 0.6rem;">Active</span>';
        } else {
            statusEl.innerHTML = '<span class="badge badge-neutral" style="font-size:0.8rem; padding:0.2rem 0.6rem;">Inactive</span>';
        }
    }

    // Edit button inside view modal opens Edit Modal directly
    const editBtn = document.getElementById('svm-edit-btn');
    if (editBtn) {
        editBtn.onclick = function(e) {
            e.preventDefault();
            closeSchoolModal();
            openEditSchoolModal(currentSelectedViewData);
        };
    }

    // Show modal
    const modal = document.getElementById('school-view-modal');
    if (modal) modal.style.display = 'flex';
}

function closeSchoolModal() {
    const modal = document.getElementById('school-view-modal');
    if (modal) modal.style.display = 'none';
}

// ==================== EDIT POPUP WINDOW MODAL LOGIC ====================
function openEditSchoolModal(data) {
    if (!data) return;

    const modal = document.getElementById('school-edit-modal');
    const titleEl = document.getElementById('sem-modal-title');
    const idInput = document.getElementById('sem-id');
    const modeInput = document.getElementById('sem-mode');
    const submitBtnText = document.getElementById('sem-submit-btn-text');

    const secSingle = document.getElementById('sem-section-single');
    const secMulti = document.getElementById('sem-section-multi');
    const secSubSingle = document.getElementById('sem-section-subsingle');

    idInput.value = data.id;

    const typeSelectorWrap = document.getElementById('sem-type-selector-wrap');

    if (data.parent_id) {
        // Sub-School of a main group (fixed sub-unit under group)
        if (typeSelectorWrap) typeSelectorWrap.style.display = 'none';
        titleEl.textContent = 'Edit Sub-School: ' + data.name;
        modeInput.value = 'sub_single';
        submitBtnText.textContent = 'Update Sub-School';

        secSingle.style.display = 'none';
        secMulti.style.display = 'none';
        secSubSingle.style.display = 'block';

        document.getElementById('sem-parent-banner-name').textContent = 'Campus under: ' + (data.parent_name || 'Main Group');
        document.getElementById('sem-subsingle-name').value = data.name || '';
        document.getElementById('sem-subsingle-code').value = data.code || '';
        document.getElementById('sem-subsingle-location').value = data.location || '';
        document.getElementById('sem-subsingle-phone').value = data.contact_phone || '';

    } else {
        // Main School (Freely switchable between Single and Multi School anytime)
        if (typeSelectorWrap) typeSelectorWrap.style.display = 'block';
        secSubSingle.style.display = 'none';

        // Prepopulate both single and multi form inputs so switching is seamless
        document.getElementById('sem-single-name').value = data.name || '';
        document.getElementById('sem-single-code').value = data.code || '';
        document.getElementById('sem-single-location').value = data.location || '';
        document.getElementById('sem-single-phone').value = data.contact_phone || '';

        const elMName = document.getElementById('sem-multi-name');
        if (elMName) elMName.value = data.name || '';
        const elMCode = document.getElementById('sem-multi-code');
        if (elMCode) elMCode.value = data.code || '';
        const elMLoc = document.getElementById('sem-multi-location');
        if (elMLoc) elMLoc.value = data.location || '';
        const elMPhone = document.getElementById('sem-multi-phone');
        if (elMPhone) elMPhone.value = data.contact_phone || '';

        // Sub-school unit label
        const chkEdit = document.getElementById('chk_is_school_edit');
        const boxEdit = document.getElementById('custom-unit-box-edit');
        const inputEdit = document.getElementById('input_sub_school_label_edit');
        const currentLabel = (data.sub_school_label && data.sub_school_label.trim()) ? data.sub_school_label.trim() : 'School';
        
        if (currentLabel.toLowerCase() === 'school') {
            if (chkEdit) chkEdit.checked = true;
            if (boxEdit) boxEdit.style.display = 'none';
            if (inputEdit) inputEdit.value = '';
        } else {
            if (chkEdit) chkEdit.checked = false;
            if (boxEdit) boxEdit.style.display = 'block';
            if (inputEdit) inputEdit.value = currentLabel;
        }

        // Populate sub-schools for multi mode
        const container = document.getElementById('sem-subschools-container');
        container.innerHTML = '';
        const subs = data.sub_schools || [];
        if (subs.length > 0) {
            subs.forEach(s => {
                semRenderSubSchoolRow(container, s.id, s.name, s.code || '', s.location, s.contact_phone);
            });
        } else {
            semRenderSubSchoolRow(container, 0, '', '', '', '');
        }

        if (data.has_sub_schools == 1) {
            titleEl.textContent = 'Edit Multi-School Group: ' + data.name;
            semToggleSchoolType('multi');
        } else {
            titleEl.textContent = 'Edit Single School: ' + data.name;
            semToggleSchoolType('single');
        }
    }

    modal.style.display = 'flex';
}

function semToggleSchoolType(targetMode) {
    const secSingle = document.getElementById('sem-section-single');
    const secMulti = document.getElementById('sem-section-multi');
    const modeInput = document.getElementById('sem-mode');
    const submitBtnText = document.getElementById('sem-submit-btn-text');
    const radioSingle = document.getElementById('sem-radio-type-single');
    const radioMulti = document.getElementById('sem-radio-type-multi');
    const lblSingle = document.getElementById('sem-lbl-type-single');
    const lblMulti = document.getElementById('sem-lbl-type-multi');

    if (targetMode === 'multi') {
        modeInput.value = 'multi';
        if (radioMulti) radioMulti.checked = true;
        if (radioSingle) radioSingle.checked = false;
        if (secSingle) secSingle.style.display = 'none';
        if (secMulti) secMulti.style.display = 'block';
        if (submitBtnText) submitBtnText.textContent = 'Update Multi School Group';

        if (lblMulti) {
            lblMulti.style.borderColor = '#FF6702';
            lblMulti.style.background = '#FFF7ED';
            lblMulti.style.color = '#EA580C';
        }
        if (lblSingle) {
            lblSingle.style.borderColor = '#CBD5E1';
            lblSingle.style.background = '#ffffff';
            lblSingle.style.color = '#1E293B';
        }

        // Sync values from single to multi if multi is empty
        const sName = document.getElementById('sem-single-name').value;
        const sCode = document.getElementById('sem-single-code').value;
        const sLoc = document.getElementById('sem-single-location').value;
        const sPhone = document.getElementById('sem-single-phone').value;

        const mName = document.getElementById('sem-multi-name');
        if (mName && !mName.value && sName) mName.value = sName;
        const mCode = document.getElementById('sem-multi-code');
        if (mCode && !mCode.value && sCode) mCode.value = sCode;
        const mLoc = document.getElementById('sem-multi-location');
        if (mLoc && !mLoc.value && sLoc) mLoc.value = sLoc;
        const mPhone = document.getElementById('sem-multi-phone');
        if (mPhone && !mPhone.value && sPhone) mPhone.value = sPhone;

        const container = document.getElementById('sem-subschools-container');
        if (container && container.children.length === 0) {
            semRenderSubSchoolRow(container, 0, '', '', '', '');
        }
    } else {
        modeInput.value = 'single';
        if (radioSingle) radioSingle.checked = true;
        if (radioMulti) radioMulti.checked = false;
        if (secMulti) secMulti.style.display = 'none';
        if (secSingle) secSingle.style.display = 'block';
        if (submitBtnText) submitBtnText.textContent = 'Update Single School Profile';

        if (lblSingle) {
            lblSingle.style.borderColor = '#FF6702';
            lblSingle.style.background = '#FFF7ED';
            lblSingle.style.color = '#EA580C';
        }
        if (lblMulti) {
            lblMulti.style.borderColor = '#CBD5E1';
            lblMulti.style.background = '#ffffff';
            lblMulti.style.color = '#1E293B';
        }

        // Sync values from multi to single if single is empty
        const mName = document.getElementById('sem-multi-name') ? document.getElementById('sem-multi-name').value : '';
        const mCode = document.getElementById('sem-multi-code') ? document.getElementById('sem-multi-code').value : '';
        const mLoc = document.getElementById('sem-multi-location') ? document.getElementById('sem-multi-location').value : '';
        const mPhone = document.getElementById('sem-multi-phone') ? document.getElementById('sem-multi-phone').value : '';

        const sName = document.getElementById('sem-single-name');
        if (sName && !sName.value && mName) sName.value = mName;
        const sCode = document.getElementById('sem-single-code');
        if (sCode && !sCode.value && mCode) sCode.value = mCode;
        const sLoc = document.getElementById('sem-single-location');
        if (sLoc && !sLoc.value && mLoc) sLoc.value = mLoc;
        const sPhone = document.getElementById('sem-single-phone');
        if (sPhone && !sPhone.value && mPhone) sPhone.value = mPhone;
    }
}

function closeEditSchoolModal() {
    const modal = document.getElementById('school-edit-modal');
    if (modal) modal.style.display = 'none';
}

function semRenderSubSchoolRow(container, id, name, code, location, phone) {
    const row = document.createElement('div');
    row.className = 'sem-sub-row';
    row.style.cssText = 'background:#ffffff; border:1px solid #CBD5E1; border-radius:10px; padding:0.75rem 1rem; display:grid; grid-template-columns: 2fr 0.8fr 1.5fr 1fr auto; gap:0.75rem; align-items:center;';
    row.innerHTML = `
        <input type="hidden" name="sub_school_id[]" value="${id}">
        <div>
            <label style="font-size:0.72rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem;">Individual School Name *</label>
            <input type="text" name="sub_school_name[]" value="${name ? name.replace(/"/g, '&quot;') : ''}" required placeholder="Enter school name" class="form-control" style="font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>
        <div>
            <label style="font-size:0.72rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem;">Code (3 Letters)</label>
            <input type="text" name="sub_school_code[]" value="${code ? code.replace(/"/g, '&quot;') : ''}" maxlength="3" placeholder="e.g. GPS" class="form-control" style="font-size:0.85rem; padding:0.4rem 0.6rem; text-transform:uppercase; font-weight:700;" oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3);">
        </div>
        <div>
            <label style="font-size:0.72rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem;">Location / Board</label>
            <input type="text" name="sub_school_location[]" value="${location ? location.replace(/"/g, '&quot;') : ''}" placeholder="Enter campus location" class="form-control" style="font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>
        <div>
            <label style="font-size:0.72rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem;">Contact Phone</label>
            <input type="tel" name="sub_school_phone[]" value="${phone ? phone.replace(/"/g, '&quot;') : ''}" placeholder="Enter contact phone" class="form-control" style="font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>
        <div style="padding-top:1rem;">
            <button type="button" onclick="semRemoveSubSchoolRow(this)" class="btn btn-outline btn-sm" style="color:#DC2626; border-color:#FCA5A5; padding:0.35rem 0.55rem; font-size:0.75rem;" title="Remove this sub-school">
                ✕
            </button>
        </div>
    `;
    container.appendChild(row);
}

function semAddSubSchoolRow() {
    const container = document.getElementById('sem-subschools-container');
    if (container) semRenderSubSchoolRow(container, 0, '', '', '');
}

function semRemoveSubSchoolRow(btn) {
    const rows = document.querySelectorAll('.sem-sub-row');
    if (rows.length <= 1) {
        alert('A Multi-School group must have at least one individual school.');
        return;
    }
    const row = btn.closest('.sem-sub-row');
    if (row) row.remove();
}

// Backdrop and Escape listeners
document.getElementById('school-view-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSchoolModal();
});
document.getElementById('school-edit-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEditSchoolModal();
});
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSchoolModal();
        closeEditSchoolModal();
    }
});
</script>

<?php if ($editId > 0 && $editSchool): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const schoolToEdit = <?= json_encode([
        'id' => (int)$editSchool['id'],
        'name' => $editSchool['name'],
        'code' => $editSchool['code'] ?: '',
        'location' => $editSchool['location'] ?: $editSchool['city'],
        'contact_phone' => $editSchool['contact_phone'] ?: '',
        'has_sub_schools' => (int)$editSchool['has_sub_schools'],
        'sub_school_label' => $editSchool['sub_school_label'] ?: 'School',
        'parent_id' => !empty($editSchool['parent_id']) ? (int)$editSchool['parent_id'] : null,
        'parent_name' => $editSchoolParent['name'] ?? '',
        'sub_schools' => $subSchoolsList ?? []
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    if (schoolToEdit) {
        openEditSchoolModal(schoolToEdit);
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

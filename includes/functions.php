<?php
/**
 * OnBolt Digital Platform - Core Functional Logic & Helpers
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

// ==================== HIGH-PERFORMANCE FAST CACHE ENGINE ====================
function getFastCache(string $key, int $ttl = 60): mixed {
    static $memoryCache = [];
    $now = time();
    if (isset($memoryCache[$key]) && $memoryCache[$key]['expires'] > $now) {
        return $memoryCache[$key]['data'];
    }

    if (function_exists('apcu_fetch')) {
        $success = false;
        $val = apcu_fetch('onbolt_' . $key, $success);
        if ($success) {
            $memoryCache[$key] = ['data' => $val, 'expires' => $now + $ttl];
            return $val;
        }
    }

    $cacheDir = sys_get_temp_dir() . '/onbolt_fast_cache';
    $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
    if (file_exists($cacheFile)) {
        $mtime = filemtime($cacheFile);
        if (($now - $mtime) < $ttl) {
            $content = @file_get_contents($cacheFile);
            if ($content !== false) {
                $val = @unserialize($content);
                if ($val !== false) {
                    $memoryCache[$key] = ['data' => $val, 'expires' => $now + $ttl];
                    return $val;
                }
            }
        }
    }
    return null;
}

function setFastCache(string $key, mixed $data, int $ttl = 60): void {
    static $memoryCache = [];
    $now = time();
    $memoryCache[$key] = ['data' => $data, 'expires' => $now + $ttl];

    if (function_exists('apcu_store')) {
        apcu_store('onbolt_' . $key, $data, $ttl);
    }

    $cacheDir = sys_get_temp_dir() . '/onbolt_fast_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
    @file_put_contents($cacheFile, serialize($data), LOCK_EX);
}

function clearFastCache(): void {
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }
    $cacheDir = sys_get_temp_dir() . '/onbolt_fast_cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.cache');
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}

// School functions
function getSchoolBySlug(string $slug): ?array {
    static $reqCache = [];
    $slugClean = trim(strtolower($slug));
    if (isset($reqCache[$slugClean])) {
        return $reqCache[$slugClean];
    }

    $cacheKey = 'school_slug_' . $slugClean;
    $cached = getFastCache($cacheKey, 120);
    if ($cached !== null) {
        $reqCache[$slugClean] = $cached ?: null;
        return $reqCache[$slugClean];
    }

    // Support all slug variants bidirectionally so either slug always resolves to the right school
    $candidates = [$slugClean];
    if (in_array($slugClean, ['gps-brookes', 'global-public-school-brookes', 'school-b'])) {
        $candidates = ['global-public-school-brookes', 'gps-brookes', 'school-b'];
    } elseif (in_array($slugClean, ['global-public-school-cbse', 'gps-cbse', 'school-c'])) {
        $candidates = ['global-public-school-cbse', 'gps-cbse', 'school-c'];
    } elseif (in_array($slugClean, ['global-public-school-international', 'gps-international', 'school-d'])) {
        $candidates = ['global-public-school-international', 'gps-international', 'school-d'];
    }

    $pdo = getDbConnection();
    $inClause = implode(',', array_fill(0, count($candidates), '?'));
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE slug IN ($inClause) AND status = 1 ORDER BY (slug = ?) DESC, id ASC LIMIT 1");
    $stmt->execute(array_merge($candidates, [$slugClean]));
    $school = $stmt->fetch();
    $res = $school ?: null;
    setFastCache($cacheKey, $res ?: false, 120);
    $reqCache[$slugClean] = $res;
    return $res;
}

function getSchoolById(int $id): ?array {
    static $reqCache = [];
    if (isset($reqCache[$id])) {
        return $reqCache[$id];
    }

    $cacheKey = 'school_id_' . $id;
    $cached = getFastCache($cacheKey, 120);
    if ($cached !== null) {
        $reqCache[$id] = $cached ?: null;
        return $reqCache[$id];
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $school = $stmt->fetch();
    $res = $school ?: null;
    setFastCache($cacheKey, $res ?: false, 120);
    $reqCache[$id] = $res;
    return $res;
}

function getAllSchools(bool $activeOnly = true): array {
    $cacheKey = 'all_schools_' . ($activeOnly ? '1' : '0');
    $cached = getFastCache($cacheKey, 120);
    if ($cached !== null) {
        return $cached;
    }

    $pdo = getDbConnection();
    $sql = "SELECT s.*, 
            (SELECT COUNT(*) FROM school_products sp WHERE sp.school_id = s.id AND sp.status = 1) as product_count,
            (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id) as student_count
            FROM schools s";
    if ($activeOnly) {
        $sql .= " WHERE s.status = 1";
    }
    $sql .= " ORDER BY s.sort_order ASC, s.name ASC";
    $schools = $pdo->query($sql)->fetchAll();
    setFastCache($cacheKey, $schools, 120);
    return $schools;
}

// Student lookup for parent
function getStudentsForParent(int $parentId): array {
    static $reqCache = [];
    if (isset($reqCache[$parentId])) {
        return $reqCache[$parentId];
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT s.*, ps.relationship 
                           FROM students s
                           JOIN parent_students ps ON s.id = ps.student_id
                           WHERE ps.parent_id = ? AND s.status = 1
                           ORDER BY s.student_name ASC");
    $stmt->execute([$parentId]);
    $res = $stmt->fetchAll();
    $reqCache[$parentId] = $res;
    return $res;
}

// Products for School (Optimized batch loading - Eliminates N+1 queries)
function getProductsForSchool(int $schoolId): array {
    static $reqCache = [];
    if (isset($reqCache[$schoolId])) {
        return $reqCache[$schoolId];
    }

    $cacheKey = 'school_prods_' . $schoolId;
    $cached = getFastCache($cacheKey, 60);
    if ($cached !== null) {
        $reqCache[$schoolId] = $cached;
        return $cached;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT p.*, 
            COALESCE(sp.custom_price, p.price) as effective_price,
            sp.is_mandatory,
            c.name as category_name
            FROM products p
            JOIN school_products sp ON p.id = sp.product_id
            LEFT JOIN product_categories c ON p.category_id = c.id
            WHERE sp.school_id = ? AND sp.status = 1 AND p.status = 1
            ORDER BY sp.is_mandatory DESC, p.sort_order ASC, p.name ASC");
    $stmt->execute([$schoolId]);
    $products = $stmt->fetchAll();

    if (!empty($products)) {
        $prodIds = array_column($products, 'id');
        $placeholders = implode(',', array_fill(0, count($prodIds), '?'));
        $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id IN ($placeholders) AND status = 1 ORDER BY id ASC");
        $vStmt->execute($prodIds);
        $allVariants = $vStmt->fetchAll();

        $variantsByProd = [];
        foreach ($allVariants as $var) {
            $variantsByProd[$var['product_id']][] = $var;
        }

        foreach ($products as &$prod) {
            $prod['variants'] = $variantsByProd[$prod['id']] ?? [];
        }
    }

    setFastCache($cacheKey, $products, 60);
    $reqCache[$schoolId] = $products;
    return $products;
}

// Product by ID
function getProductById(int $id): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                           FROM products p 
                           LEFT JOIN product_categories c ON p.category_id = c.id 
                           WHERE p.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if ($product) {
        $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND status = 1 ORDER BY id ASC");
        $vStmt->execute([$id]);
        $product['variants'] = $vStmt->fetchAll();
    }
    return $product ?: null;
}

// Cart Management (School-Specific)
function getCartKey(int $schoolId): string {
    return 'onbolt_cart_' . $schoolId;
}

function getCart(int $schoolId): array {
    $key = getCartKey($schoolId);
    return $_SESSION[$key] ?? [];
}

function addToCart(int $schoolId, int $productId, string $size, int $quantity = 1, ?string $color = null): bool {
    if ($quantity < 1) return false;
    $key = getCartKey($schoolId);
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    $cartItemId = $productId . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $size);
    
    // Fetch price
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT p.name, COALESCE(sp.custom_price, p.price) as price, p.featured_image 
                           FROM products p 
                           LEFT JOIN school_products sp ON p.id = sp.product_id AND sp.school_id = ?
                           WHERE p.id = ?");
    $stmt->execute([$schoolId, $productId]);
    $item = $stmt->fetch();
    if (!$item) return false;

    if (isset($_SESSION[$key][$cartItemId])) {
        $_SESSION[$key][$cartItemId]['quantity'] += $quantity;
    } else {
        $_SESSION[$key][$cartItemId] = [
            'item_id'        => $cartItemId,
            'product_id'     => $productId,
            'name'           => $item['name'],
            'price'          => (float)$item['price'],
            'size'           => $size,
            'color'          => $color,
            'quantity'       => $quantity,
            'featured_image' => $item['featured_image'] ?: 'assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png'
        ];
    }
    return true;
}

function updateCartQuantity(int $schoolId, string $cartItemId, int $quantity): void {
    $key = getCartKey($schoolId);
    if (isset($_SESSION[$key][$cartItemId])) {
        if ($quantity <= 0) {
            unset($_SESSION[$key][$cartItemId]);
        } else {
            $_SESSION[$key][$cartItemId]['quantity'] = $quantity;
        }
    }
}

function removeFromCart(int $schoolId, string $cartItemId): void {
    $key = getCartKey($schoolId);
    unset($_SESSION[$key][$cartItemId]);
}

function clearCart(int $schoolId): void {
    $key = getCartKey($schoolId);
    unset($_SESSION[$key]);
}

function getCartTotals(int $schoolId): array {
    $items = getCart($schoolId);
    $subtotal = 0.0;
    $itemCount = 0;
    foreach ($items as $item) {
        $subtotal += ($item['price'] * $item['quantity']);
        $itemCount += $item['quantity'];
    }
    $shipping = 0.0; // Free school distribution / delivery
    $total = $subtotal + $shipping;

    return [
        'items'     => $items,
        'count'     => $itemCount,
        'subtotal'  => $subtotal,
        'shipping'  => $shipping,
        'total'     => $total,
    ];
}

// Order Status Helper with Color Classes
function getOrderStatusBadge(string $status): string {
    $map = [
        'Pending'       => ['bg' => 'rgba(255, 200, 87, 0.2)', 'text' => '#B45309', 'border' => '#FFC857'],
        'Confirmed'     => ['bg' => 'rgba(58, 91, 160, 0.15)', 'text' => '#3A5BA0', 'border' => '#3A5BA0'],
        'Processing'    => ['bg' => 'rgba(59, 130, 246, 0.15)', 'text' => '#1D4ED8', 'border' => '#60A5FA'],
        'Production'    => ['bg' => 'rgba(168, 85, 247, 0.15)', 'text' => '#7E22CE', 'border' => '#C084FC'],
        'Quality Check' => ['bg' => 'rgba(14, 165, 233, 0.15)', 'text' => '#0369A1', 'border' => '#38BDF8'],
        'Packed'        => ['bg' => 'rgba(234, 88, 12, 0.15)', 'text' => '#C2410C', 'border' => '#FB923C'],
        'In progress'   => ['bg' => 'rgba(168, 85, 247, 0.15)', 'text' => '#7E22CE', 'border' => '#C084FC'],
        'Dispatched'    => ['bg' => 'rgba(14, 165, 233, 0.15)', 'text' => '#0369A1', 'border' => '#38BDF8'],
        'Dispatched by hand' => ['bg' => 'rgba(245, 158, 11, 0.18)', 'text' => '#B45309', 'border' => '#FBBF24'],
        'On the way'    => ['bg' => 'rgba(99, 102, 241, 0.15)', 'text' => '#4338CA', 'border' => '#818CF8'],
        'Shipped'       => ['bg' => 'rgba(20, 184, 166, 0.15)', 'text' => '#0F766E', 'border' => '#2DD4BF'],
        'Delivered'     => ['bg' => 'rgba(34, 197, 94, 0.15)', 'text' => '#15803D', 'border' => '#4ADE80'],
        'Cancelled'     => ['bg' => 'rgba(255, 107, 107, 0.15)', 'text' => '#DC2626', 'border' => '#FF6B6B'],
        'Cancel'        => ['bg' => 'rgba(255, 107, 107, 0.15)', 'text' => '#DC2626', 'border' => '#FF6B6B'],
        'Refunded'      => ['bg' => 'rgba(139, 92, 246, 0.15)', 'text' => '#6D28D9', 'border' => '#C4B5FD'],
        'Refund'        => ['bg' => 'rgba(139, 92, 246, 0.15)', 'text' => '#6D28D9', 'border' => '#C4B5FD'],
    ];

    $cfg = $map[$status] ?? ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#cbd5e1'];
    return "<span style=\"display:inline-flex;align-items:center;padding:4px 10px;border-radius:9999px;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;background-color:{$cfg['bg']};color:{$cfg['text']};border:1px solid {$cfg['border']};\">{$status}</span>";
}

function getOrderStatusSelectStyle(string $status): string {
    $map = [
        'Pending'            => 'background-color: #FEF3C7; color: #B45309; border-color: #FDE68A;',
        'Confirmed'          => 'background-color: #DBEAFE; color: #1D4ED8; border-color: #BFDBFE;',
        'In progress'        => 'background-color: #F3E8FF; color: #7E22CE; border-color: #D8B4FE;',
        'Production'         => 'background-color: #F3E8FF; color: #7E22CE; border-color: #D8B4FE;',
        'Packed'             => 'background-color: #FFEDD5; color: #C2410C; border-color: #FDBA74;',
        'Dispatched'         => 'background-color: #E0F2FE; color: #0369A1; border-color: #7DD3FC;',
        'Dispatched by hand' => 'background-color: #FEF3C7; color: #B45309; border-color: #FDE68A;',
        'On the way'         => 'background-color: #E0E7FF; color: #4338CA; border-color: #A5B4FC;',
        'Delivered'          => 'background-color: #DCFCE7; color: #15803D; border-color: #86EFAC;',
        'Cancelled'          => 'background-color: #FEE2E2; color: #DC2626; border-color: #FCA5A5;',
        'Cancel'             => 'background-color: #FEE2E2; color: #DC2626; border-color: #FCA5A5;',
        'Refunded'           => 'background-color: #EDE9FE; color: #6D28D9; border-color: #DDD6FE;',
        'Refund'             => 'background-color: #EDE9FE; color: #6D28D9; border-color: #DDD6FE;',
    ];
    return $map[$status] ?? 'background-color: #F1F5F9; color: #475569; border-color: #CBD5E1;';
}

function generateSchoolSlug(PDO $pdo, string $name, ?int $ignoreId = null): string {
    $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    if (empty($baseSlug)) {
        $baseSlug = 'school-' . time();
    }
    $slug = $baseSlug;
    $counter = 1;
    while (true) {
        $sql = "SELECT id FROM schools WHERE slug = ?";
        $params = [$slug];
        if ($ignoreId) {
            $sql .= " AND id != ?";
            $params[] = $ignoreId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            break;
        }
        $counter++;
        $slug = $baseSlug . '-' . $counter;
    }
    return $slug;
}

/**
 * Generate official unique Order ID / Reference for a school upon confirmed payment success
 * Format: (SchoolCode) + 6-digit random number (e.g. GIB822754, TIS140127)
 */
function generateOfficialOrderReference(int $schoolId): string {
    $pdo = getDbConnection();
    $schoolCode = 'OB';
    if ($schoolId > 0) {
        $stmt = $pdo->prepare("SELECT s.code, p.code as parent_code FROM schools s LEFT JOIN schools p ON s.parent_id = p.id WHERE s.id = ?");
        $stmt->execute([$schoolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $rawC = !empty($row['code']) ? $row['code'] : (!empty($row['parent_code']) ? $row['parent_code'] : 'TIS');
            $cleanC = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$rawC));
            if (!empty($cleanC)) {
                $schoolCode = $cleanC;
            }
        }
    }

    $chkStmt = $pdo->prepare("SELECT id FROM orders WHERE order_reference = ? LIMIT 1");
    do {
        $candidate = $schoolCode . rand(100000, 999999);
        $chkStmt->execute([$candidate]);
    } while ($chkStmt->fetch());

    return $candidate;
}

/**
 * Resolves a clean, specific payment method for UI display.
 * Shows only how the payment was done/attempted (e.g. 'UPI', 'UPI (Google Pay)', 'Card', 'Net Banking', 'Cash').
 * Eliminates generic multi-slash strings like 'Online / QR / UPI / Card'.
 */
function formatDisplayPaymentMethod(?string $method, ?string $gatewayResponse = null): string {
    $raw = trim((string)$method);
    $raw = str_ireplace('Transaction Mode: ', '', $raw);

    // If gatewayResponse JSON has specific mode, check that first
    if (!empty($gatewayResponse)) {
        $gw = json_decode($gatewayResponse, true);
        if (is_array($gw)) {
            $pm = strtolower((string)($gw['details']['payment_mode'] ?? ($gw['payment_mode'] ?? '')));
            if ($pm === 'upi') return 'UPI';
            if (str_contains($pm, 'card')) return 'Card';
            if (str_contains($pm, 'net')) return 'Net Banking';
            if (str_contains($pm, 'wallet')) return 'Wallet';
        }
    }

    if (empty($raw) || stripos($raw, 'Online / QR / UPI / Card') !== false || stripos($raw, 'edviron') !== false) {
        return 'UPI';
    }

    if (stripos($raw, 'cash') !== false || stripos($raw, 'counter') !== false) {
        return 'Cash';
    }
    if (stripos($raw, 'qr') !== false) {
        return 'UPI';
    }
    if (stripos($raw, 'card') !== false) {
        return 'Card';
    }
    if (stripos($raw, 'net') !== false || stripos($raw, 'bank') !== false) {
        return 'Net Banking';
    }
    if (stripos($raw, 'wallet') !== false) {
        return 'Wallet';
    }
    if (stripos($raw, 'upi') !== false) {
        return $raw; // e.g. 'UPI (Google Pay)' or 'UPI'
    }

    return $raw ?: 'UPI';
}


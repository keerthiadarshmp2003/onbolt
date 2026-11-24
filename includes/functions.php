<?php
/**
 * OnBolt Digital Platform - Core Functional Logic & Helpers
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

// School functions
function getSchoolBySlug(string $slug): ?array {
    $aliases = [
        'school-b' => 'gps-brookes',
        'global-public-school-brookes' => 'gps-brookes',
        'school-c' => 'global-public-school-cbse',
        'gps-cbse' => 'global-public-school-cbse',
        'school-d' => 'gps-international',
        'global-public-school-international' => 'gps-international'
    ];
    if (isset($aliases[$slug])) {
        $slug = $aliases[$slug];
    }
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE slug = ? AND status = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $school = $stmt->fetch();
    return $school ?: null;
}

function getSchoolById(int $id): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $school = $stmt->fetch();
    return $school ?: null;
}

function getAllSchools(bool $activeOnly = true): array {
    $pdo = getDbConnection();
    $sql = "SELECT s.*, 
            (SELECT COUNT(*) FROM school_products sp WHERE sp.school_id = s.id AND sp.status = 1) as product_count,
            (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id) as student_count
            FROM schools s";
    if ($activeOnly) {
        $sql .= " WHERE s.status = 1";
    }
    $sql .= " ORDER BY s.sort_order ASC, s.name ASC";
    return $pdo->query($sql)->fetchAll();
}

// Student lookup for parent
function getStudentsForParent(int $parentId): array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT s.*, ps.relationship 
                           FROM students s
                           JOIN parent_students ps ON s.id = ps.student_id
                           WHERE ps.parent_id = ? AND s.status = 1
                           ORDER BY s.student_name ASC");
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

// Products for School
function getProductsForSchool(int $schoolId): array {
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

    foreach ($products as &$prod) {
        $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND status = 1 ORDER BY id ASC");
        $vStmt->execute([$prod['id']]);
        $prod['variants'] = $vStmt->fetchAll();
    }
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
            'featured_image' => $item['featured_image'] ?: 'assets/images/home/630px-x-420px-Catogary-Uniforms-1.webp'
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

<?php
/**
 * OnBolt Digital Platform - Cart API Endpoint
 * Pure JSON API with CORS, output buffering & session validation
 */

declare(strict_types=1);

// Prevent any accidental PHP notices/warnings from breaking JSON payload
ob_start();

// Enable CORS for localhost / 127.0.0.1 interoperability
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'get';
    $schoolId = (int)($_POST['school_id'] ?? $_GET['school_id'] ?? ($_SESSION['parent_school_id'] ?? 1));

    if ($schoolId <= 0) {
        $schoolId = 1; // Default to primary school / general catalog
    }

    if ($action === 'add') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $size = trim((string)($_POST['size'] ?? ''));
        if (empty($size)) {
            $size = 'Standard';
        }
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $color = !empty($_POST['color']) ? trim((string)$_POST['color']) : null;

        if ($productId <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Please select a valid product.']);
            exit;
        }

        $added = addToCart($schoolId, $productId, $size, $quantity, $color);
        if ($added) {
            $totals = getCartTotals($schoolId);
            ob_clean();
            echo json_encode([
                'success'   => true,
                'message'   => 'Product added to your school kit!',
                'cartCount' => $totals['count'],
                'subtotal'  => $totals['subtotal'],
                'total'     => $totals['total']
            ]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Unable to add product to requisition.']);
        }
        exit;
    }

    if ($action === 'update') {
        $itemId = trim((string)($_POST['item_id'] ?? ''));
        $quantity = (int)($_POST['quantity'] ?? 1);

        if (!empty($itemId)) {
            updateCartQuantity($schoolId, $itemId, $quantity);
        }
        $totals = getCartTotals($schoolId);
        ob_clean();
        echo json_encode([
            'success'   => true,
            'cartCount' => $totals['count'],
            'subtotal'  => $totals['subtotal'],
            'total'     => $totals['total']
        ]);
        exit;
    }

    if ($action === 'remove') {
        $itemId = trim((string)($_POST['item_id'] ?? ''));
        if (!empty($itemId)) {
            removeFromCart($schoolId, $itemId);
        }
        $totals = getCartTotals($schoolId);
        ob_clean();
        echo json_encode([
            'success'   => true,
            'cartCount' => $totals['count'],
            'subtotal'  => $totals['subtotal'],
            'total'     => $totals['total']
        ]);
        exit;
    }

    // Default: get
    $totals = getCartTotals($schoolId);
    ob_clean();
    echo json_encode([
        'success' => true,
        'data'    => $totals
    ]);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error'   => 'Cart service error: ' . $e->getMessage()
    ]);
    exit;
}

<?php
/**
 * GET  /api/products/index.php          - List products (filter by business/branch)
 * POST /api/products/index.php          - Create product
 *
 * Query params (GET): business_id, branch_id, category_id, search, status, page, limit
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!function_exists('saveProductImage')) {
    function saveProductImage($imageData)
    {
        if (empty($imageData) || !is_string($imageData)) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $imageData)) {
            return $imageData;
        }

        if (!preg_match('/^data:image\/(png|jpg|jpeg|gif|webp);base64,/', $imageData, $matches)) {
            return null;
        }

        $uploadDir = __DIR__ . '/../../public/uploads/products';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $encoded = preg_replace('/^data:image\/[a-zA-Z0-9\-+]+;base64,/', '', $imageData);
        $binary  = base64_decode($encoded, true);
        if ($binary === false) {
            return null;
        }

        $extension = strtolower($matches[1]);
        $filename  = 'product_' . uniqid('', true) . '.' . $extension;
        $fullPath  = $uploadDir . '/' . $filename;

        if (file_put_contents($fullPath, $binary) === false) {
            return null;
        }

        return '/uploads/products/' . $filename;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Public browse or authenticated list
    $businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : null;
    $branchId   = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $search     = clean($_GET['search'] ?? '');
    $status     = clean($_GET['status'] ?? 'active');
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $limit      = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset     = ($page - 1) * $limit;

    $where  = ["p.status = ?"];
    $params = [$status];

    if ($businessId) {
        $where[]  = "p.business_id = ?";
        $params[] = $businessId;
    }
    if ($categoryId) {
        $where[]  = "p.category_id = ?";
        $params[] = $categoryId;
    }
    if ($search) {
        $where[]  = "(p.name LIKE ? OR p.name_sw LIKE ? OR p.barcode = ? OR p.sku = ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $search;
        $params[] = $search;
    }

    $whereSql = implode(' AND ', $where);

    // Count
    $countStmt = Database::query(
        "SELECT COUNT(*) AS total FROM products p WHERE $whereSql",
        $params
    );
    $total = (int)$countStmt->fetch()['total'];

    // Data with stock — specific branch, otherwise all branches combined
    $stockJoin = "LEFT JOIN (
            SELECT product_id,
                   SUM(quantity) AS quantity,
                   SUM(COALESCE(reserved_qty, 0)) AS reserved_qty
            FROM stock_levels
            GROUP BY product_id
        ) sl ON sl.product_id = p.id";
    $stockSelect = "COALESCE(sl.quantity, 0) AS quantity, COALESCE(sl.reserved_qty, 0) AS reserved_qty";
    if ($branchId) {
        $stockJoin = "LEFT JOIN stock_levels sl ON sl.product_id = p.id AND sl.branch_id = ? AND sl.variant_id IS NULL";
        array_unshift($params, $branchId); // for JOIN
    }

    $sql = "SELECT p.id, p.business_id, p.category_id, p.name, p.name_sw, p.description,
                   p.sku, p.barcode, p.unit, p.price, p.tax_rate, p.image_url,
                   p.low_stock_threshold, p.status, p.created_at,
                   c.name AS category_name,
                   $stockSelect
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            $stockJoin
            WHERE $whereSql
            ORDER BY p.name ASC
            LIMIT $limit OFFSET $offset";

    // Rebuild params correctly for the final query
    $finalParams = [];
    if ($branchId) {
        $finalParams[] = $branchId;
    }
    $finalParams = array_merge($finalParams, array_slice($params, $branchId ? 1 : 0));

    $stmt = Database::query($sql, $finalParams);
    $products = $stmt->fetchAll();

    Response::success([
        'products' => $products,
        'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

if ($method === 'POST') {
    $user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);

    $input = getJsonInput();

    $name        = clean($input['name'] ?? '');
    $nameSw      = clean($input['name_sw'] ?? '');
    $description = clean($input['description'] ?? '');
    $categoryId  = !empty($input['category_id']) ? (int)$input['category_id'] : null;
    $sku         = clean($input['sku'] ?? '');
    $barcode     = clean($input['barcode'] ?? '');
    $unit        = clean($input['unit'] ?? 'pcs');
    $price       = (float)($input['price'] ?? 0);
    $costPrice   = isset($input['cost_price']) ? (float)$input['cost_price'] : null;
    $taxRate     = (float)($input['tax_rate'] ?? 18.00);
    $threshold   = (int)($input['low_stock_threshold'] ?? 5);
    $initialQty  = (float)($input['initial_quantity'] ?? 0);
    $branchId    = (int)($input['branch_id'] ?? $user['branch_id']);
    $imageUrl    = isset($input['image_url']) && is_string($input['image_url'])
        ? saveProductImage($input['image_url'])
        : null;

    if (empty($name) || $price < 0) {
        Response::error('Product name and a valid price are required');
    }

    $businessId = $user['business_id'];
    if ($user['role'] === 'super_admin' && !empty($input['business_id'])) {
        $businessId = (int)$input['business_id'];
    }
    if (!$businessId) {
        Response::error('Business context required');
    }

    try {
        Database::beginTransaction();

        Database::query(
            "INSERT INTO products (business_id, category_id, name, name_sw, description, sku, barcode, unit, price, cost_price, tax_rate, image_url, low_stock_threshold, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')",
            [$businessId, $categoryId, $name, $nameSw ?: null, $description ?: null, $sku ?: null, $barcode ?: null, $unit, $price, $costPrice, $taxRate, $imageUrl, $threshold]
        );
        $productId = (int) Database::lastInsertId();

        // Initial stock for the branch
        if ($branchId && $initialQty > 0) {
            Database::query(
                "INSERT INTO stock_levels (branch_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)",
                [$branchId, $productId, $initialQty]
            );

            // Stock movement record
            Database::query(
                "INSERT INTO stock_movements (branch_id, product_id, movement_type, quantity, quantity_before, quantity_after, reference_type, performed_by, notes)
                 VALUES (?, ?, 'restock', ?, 0, ?, 'manual', ?, 'Initial stock on product creation')",
                [$branchId, $productId, $initialQty, $initialQty, $user['id']]
            );
        }

        Database::commit();

        Auth::audit('product.create', 'products', $productId, null, $input);

        Response::success(['id' => $productId], 'Product created successfully', 201);

    } catch (Exception $e) {
        Database::rollBack();
        error_log('Product create error: ' . $e->getMessage());
        Response::error('Failed to create product', 500);
    }
}

Response::error('Method not allowed', 405);
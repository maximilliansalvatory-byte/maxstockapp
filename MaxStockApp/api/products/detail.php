<?php
/**
 * GET    /api/products/detail.php?id=123
 * PUT    /api/products/detail.php?id=123
 * DELETE /api/products/detail.php?id=123
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    Response::error('Product ID required');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;

    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ? LIMIT 1";
    $product = Database::query($sql, [$id])->fetch();

    if (!$product) {
        Response::notFound('Product not found');
    }

    if ($branchId) {
        $stock = Database::query(
            "SELECT quantity, reserved_qty FROM stock_levels
             WHERE branch_id = ? AND product_id = ? AND variant_id IS NULL LIMIT 1",
            [$branchId, $id]
        )->fetch();
        $product['quantity']     = $stock['quantity'] ?? 0;
        $product['reserved_qty'] = $stock['reserved_qty'] ?? 0;
    }

    // Variants
    $variants = Database::query(
        "SELECT * FROM product_variants WHERE product_id = ? AND status = 'active'",
        [$id]
    )->fetchAll();
    $product['variants'] = $variants;

    Response::success($product);
}

if ($method === 'PUT' || $method === 'POST') { // allow POST for form compatibility
    $user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);
    $input = getJsonInput();

    $existing = Database::query("SELECT * FROM products WHERE id = ? LIMIT 1", [$id])->fetch();
    if (!$existing) {
        Response::notFound('Product not found');
    }

    // Permission: same business
    if ($user['role'] !== 'super_admin' && $user['business_id'] != $existing['business_id']) {
        Response::forbidden();
    }

    $fields = [];
    $params = [];

    $allowed = ['name', 'name_sw', 'description', 'description_sw', 'category_id', 'sku', 'barcode',
                'unit', 'price', 'cost_price', 'tax_rate', 'low_stock_threshold', 'status', 'image_url'];

    foreach ($allowed as $key) {
        if (array_key_exists($key, $input)) {
            $fields[] = "$key = ?";
            $params[] = $input[$key] === '' ? null : $input[$key];
        }
    }

    if (empty($fields)) {
        Response::error('No fields to update');
    }

    $params[] = $id;
    $sql = "UPDATE products SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
    Database::query($sql, $params);

    Auth::audit('product.update', 'products', $id, $existing, $input);

    Response::success(null, 'Product updated successfully');
}

if ($method === 'DELETE') {
    $user = Auth::requireAuth(['business_admin', 'super_admin']);

    $existing = Database::query("SELECT * FROM products WHERE id = ? LIMIT 1", [$id])->fetch();
    if (!$existing) {
        Response::notFound('Product not found');
    }
    if ($user['role'] !== 'super_admin' && $user['business_id'] != $existing['business_id']) {
        Response::forbidden();
    }

    // Soft delete by status
    Database::query("UPDATE products SET status = 'inactive', updated_at = NOW() WHERE id = ?", [$id]);

    Auth::audit('product.delete', 'products', $id, $existing, null);

    Response::success(null, 'Product deactivated successfully');
}

Response::error('Method not allowed', 405);

<?php
/**
 * POST /api/stock/adjust.php
 * Adjust stock quantity (restock, wastage, adjustment, etc.)
 *
 * Body: {
 *   "branch_id": 1,
 *   "product_id": 5,
 *   "variant_id": null,
 *   "movement_type": "restock",   // restock|wastage|adjustment|return
 *   "quantity": 20,               // positive number; sign depends on type
 *   "notes": "Weekly delivery"
 * }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);
$input = getJsonInput();

$branchId     = (int)($input['branch_id'] ?? $user['branch_id']);
$productId    = (int)($input['product_id'] ?? 0);
$variantId    = !empty($input['variant_id']) ? (int)$input['variant_id'] : null;
$movementType = clean($input['movement_type'] ?? '');
$qty          = (float)($input['quantity'] ?? 0);
$notes        = clean($input['notes'] ?? '');

$allowedTypes = ['restock', 'wastage', 'adjustment', 'return', 'transfer_in', 'transfer_out'];
if (!$productId || $qty == 0 || !in_array($movementType, $allowedTypes, true)) {
    Response::error('Valid product_id, quantity and movement_type are required');
}

if (!$branchId) {
    Response::error('Branch is required');
}

// Determine signed quantity
$signedQty = $qty;
if (in_array($movementType, ['wastage', 'transfer_out'], true)) {
    $signedQty = -abs($qty);
} elseif (in_array($movementType, ['restock', 'return', 'transfer_in'], true)) {
    $signedQty = abs($qty);
}
// 'adjustment' keeps the sign provided by the client

try {
    Database::beginTransaction();

    // Get current stock (create row if missing)
    $stock = Database::query(
        "SELECT id, quantity FROM stock_levels
         WHERE branch_id = ? AND product_id = ? AND " .
         ($variantId ? "variant_id = ?" : "variant_id IS NULL") . " LIMIT 1",
        $variantId ? [$branchId, $productId, $variantId] : [$branchId, $productId]
    )->fetch();

    $before = $stock ? (float)$stock['quantity'] : 0;
    $after  = $before + $signedQty;

    if ($after < 0) {
        Database::rollBack();
        Response::error('Insufficient stock. Current quantity: ' . $before);
    }

    if ($stock) {
        Database::query(
            "UPDATE stock_levels SET quantity = ?, updated_at = NOW() WHERE id = ?",
            [$after, $stock['id']]
        );
    } else {
        Database::query(
            "INSERT INTO stock_levels (branch_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)",
            [$branchId, $productId, $variantId, $after]
        );
    }

    // Movement log
    Database::query(
        "INSERT INTO stock_movements
         (branch_id, product_id, variant_id, movement_type, quantity, quantity_before, quantity_after, reference_type, performed_by, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'manual', ?, ?)",
        [$branchId, $productId, $variantId, $movementType, $signedQty, $before, $after, $user['id'], $notes ?: null]
    );

    // Update product status if out of stock
    if ($after <= 0) {
        Database::query("UPDATE products SET status = 'out_of_stock' WHERE id = ?", [$productId]);
    } else {
        Database::query("UPDATE products SET status = 'active' WHERE id = ? AND status = 'out_of_stock'", [$productId]);
    }

    Database::commit();

    Auth::audit('stock.adjust', 'stock_levels', $productId, ['before' => $before], ['after' => $after, 'type' => $movementType]);

    Response::success([
        'product_id' => $productId,
        'branch_id'  => $branchId,
        'before'     => $before,
        'after'      => $after,
        'change'     => $signedQty
    ], 'Stock updated successfully');

} catch (Exception $e) {
    Database::rollBack();
    error_log('Stock adjust error: ' . $e->getMessage());
    Response::error('Failed to adjust stock', 500);
}

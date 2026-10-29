<?php
/**
 * POST /api/orders/update.php
 * Update order status (fulfilled, completed, cancelled, etc.)
 *
 * Body JSON:
 * {
 *   "id": 12,
 *   "status": "fulfilled"   // pending|paid|fulfilled|completed|cancelled|refunded
 * }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);
$input = getJsonInput();

$orderId   = (int)($input['id'] ?? 0);
$newStatus = clean($input['status'] ?? '');

$allowed = ['pending', 'paid', 'fulfilled', 'completed', 'cancelled', 'refunded'];

if ($orderId <= 0 || !in_array($newStatus, $allowed, true)) {
    Response::error('Valid order id and status are required');
}

// Fetch order
$order = Database::query(
    "SELECT * FROM orders WHERE id = ? LIMIT 1",
    [$orderId]
)->fetch();

if (!$order) {
    Response::notFound('Order not found');
}

// Permission: same business (unless super_admin)
if ($user['role'] !== 'super_admin' && (int)$user['business_id'] !== (int)$order['business_id']) {
    Response::forbidden('You cannot update this order');
}

$oldStatus = $order['status'];

// Build extra fields based on new status
$extraSql = '';
$params   = [$newStatus];

if ($newStatus === 'fulfilled') {
    $extraSql = ', fulfilled_at = NOW()';
} elseif ($newStatus === 'completed') {
    $extraSql = ', completed_at = NOW()';
} elseif ($newStatus === 'cancelled') {
    $extraSql = ', cancelled_at = NOW()';
}

$params[] = $orderId;

try {
    Database::beginTransaction();

    Database::query(
        "UPDATE orders SET status = ?, updated_at = NOW() $extraSql WHERE id = ?",
        $params
    );

    // If cancelling a paid order, optionally restore stock (simple version)
    if ($newStatus === 'cancelled' && in_array($oldStatus, ['paid', 'fulfilled'], true)) {
        $items = Database::query(
            "SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?",
            [$orderId]
        )->fetchAll();

        foreach ($items as $item) {
            $variantClause = $item['variant_id'] ? 'AND variant_id = ?' : 'AND variant_id IS NULL';
            $stockParams = [$order['branch_id'], $item['product_id']];
            if ($item['variant_id']) {
                $stockParams[] = $item['variant_id'];
            }

            $stock = Database::query(
                "SELECT id, quantity FROM stock_levels
                 WHERE branch_id = ? AND product_id = ? $variantClause LIMIT 1",
                $stockParams
            )->fetch();

            if ($stock) {
                $before = (float)$stock['quantity'];
                $after  = $before + (float)$item['quantity'];

                Database::query(
                    "UPDATE stock_levels SET quantity = ?, updated_at = NOW() WHERE id = ?",
                    [$after, $stock['id']]
                );

                Database::query(
                    "INSERT INTO stock_movements
                     (branch_id, product_id, variant_id, movement_type, quantity, quantity_before, quantity_after, reference_type, reference_id, performed_by, notes)
                     VALUES (?, ?, ?, 'return', ?, ?, ?, 'order', ?, ?, 'Stock restored on order cancel')",
                    [
                        $order['branch_id'],
                        $item['product_id'],
                        $item['variant_id'] ?: null,
                        $item['quantity'],
                        $before,
                        $after,
                        $orderId,
                        $user['id']
                    ]
                );

                // Reactivate product if it was out of stock
                Database::query(
                    "UPDATE products SET status = 'active' WHERE id = ? AND status = 'out_of_stock'",
                    [$item['product_id']]
                );
            }
        }
    }

    Database::commit();

    Auth::audit('order.status_update', 'orders', $orderId, ['status' => $oldStatus], ['status' => $newStatus]);

    Response::success([
        'id'     => $orderId,
        'status' => $newStatus
    ], 'Order status updated to ' . $newStatus);

} catch (Exception $e) {
    Database::rollBack();
    error_log('Order update error: ' . $e->getMessage());
    Response::error('Failed to update order', 500);
}
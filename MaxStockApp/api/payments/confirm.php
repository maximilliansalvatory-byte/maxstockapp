<?php
/**
 * POST /api/payments/confirm.php
 * Confirm a pending payment (sandbox / webhook / staff cash confirm).
 *
 * Body JSON:
 * {
 *   "payment_ref": "MPESA-20260821-XXXX",
 *   "provider_txn_id": "optional-external-id",
 *   "raw_response": { ... }   // optional full callback payload
 * }
 *
 * On success:
 *  - payment.status = success
 *  - order.payment_status = paid, order.status = paid
 *  - stock: reserved_qty reduced, quantity deducted
 *  - stock_movement recorded as 'sale'
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = getJsonInput();
$paymentRef    = clean($input['payment_ref'] ?? '');
$providerTxnId = clean($input['provider_txn_id'] ?? '');
$rawResponse   = $input['raw_response'] ?? null;

if ($paymentRef === '') {
    Response::error('payment_ref is required');
}

// Load payment + order
$payment = Database::query(
    "SELECT p.*, o.business_id, o.branch_id, o.order_number, o.status AS order_status,
            o.payment_status AS order_payment_status
     FROM payments p
     INNER JOIN orders o ON o.id = p.order_id
     WHERE p.payment_ref = ?
     LIMIT 1",
    [$paymentRef]
)->fetch();

if (!$payment) {
    Response::notFound('Payment not found');
}

if ($payment['status'] === 'success') {
    // Idempotent: already confirmed
    Response::success([
        'payment_ref'  => $paymentRef,
        'status'       => 'success',
        'order_id'     => (int)$payment['order_id'],
        'order_number' => $payment['order_number'],
        'already_paid' => true
    ], 'Payment already confirmed');
}

if ($payment['status'] !== 'pending') {
    Response::error('Only pending payments can be confirmed (current: ' . $payment['status'] . ')');
}

try {
    Database::beginTransaction();

    // 1. Mark payment success
    Database::query(
        "UPDATE payments
         SET status = 'success',
             provider_txn_id = COALESCE(?, provider_txn_id),
             raw_response = ?,
             paid_at = NOW(),
             updated_at = NOW()
         WHERE id = ?",
        [
            $providerTxnId ?: null,
            $rawResponse ? json_encode($rawResponse) : null,
            $payment['id']
        ]
    );

    // 2. Update order → paid
    Database::query(
        "UPDATE orders
         SET payment_status = 'paid',
             status = CASE WHEN status = 'pending' THEN 'paid' ELSE status END,
             updated_at = NOW()
         WHERE id = ?",
        [$payment['order_id']]
    );

    // 3. Deduct stock (release reservation + reduce quantity)
    $items = Database::query(
        "SELECT product_id, variant_id, quantity, product_name
         FROM order_items
         WHERE order_id = ?",
        [$payment['order_id']]
    )->fetchAll();

    $branchId = (int)$payment['branch_id'];

    foreach ($items as $item) {
        $variantId = $item['variant_id'] ?: null;
        $qty       = (float)$item['quantity'];

        $stockSql = "SELECT id, quantity, reserved_qty FROM stock_levels
                     WHERE branch_id = ? AND product_id = ? AND " .
                     ($variantId ? "variant_id = ?" : "variant_id IS NULL") . " LIMIT 1 FOR UPDATE";
        $stockParams = $variantId
            ? [$branchId, $item['product_id'], $variantId]
            : [$branchId, $item['product_id']];

        $stock = Database::query($stockSql, $stockParams)->fetch();

        if ($stock) {
            $before = (float)$stock['quantity'];
            $after  = max(0, $before - $qty);
            $newReserved = max(0, (float)$stock['reserved_qty'] - $qty);

            Database::query(
                "UPDATE stock_levels
                 SET quantity = ?, reserved_qty = ?, updated_at = NOW()
                 WHERE id = ?",
                [$after, $newReserved, $stock['id']]
            );

            // Movement log
            Database::query(
                "INSERT INTO stock_movements
                 (branch_id, product_id, variant_id, movement_type, quantity,
                  quantity_before, quantity_after, reference_type, reference_id, notes)
                 VALUES (?, ?, ?, 'sale', ?, ?, ?, 'order', ?, ?)",
                [
                    $branchId,
                    $item['product_id'],
                    $variantId,
                    -$qty,
                    $before,
                    $after,
                    $payment['order_id'],
                    'Sale – payment confirmed: ' . $paymentRef
                ]
            );

            // Mark product out of stock if needed
            if ($after <= 0) {
                Database::query(
                    "UPDATE products SET status = 'out_of_stock' WHERE id = ?",
                    [$item['product_id']]
                );
            }
        }
    }

    Database::commit();

    // Optional audit (if a user is logged in)
    $user = Auth::user();
    if ($user) {
        Auth::audit('payment.confirm', 'payments', (int)$payment['id'], null, [
            'payment_ref' => $paymentRef,
            'order_id'    => $payment['order_id']
        ]);
    }

    Response::success([
        'payment_ref'  => $paymentRef,
        'payment_id'   => (int)$payment['id'],
        'order_id'     => (int)$payment['order_id'],
        'order_number' => $payment['order_number'],
        'status'       => 'success',
        'paid_at'      => date('Y-m-d H:i:s'),
        'amount'       => (float)$payment['amount']
    ], 'Payment confirmed. Order is now paid. Ownership transferred.');

} catch (Exception $e) {
    Database::rollBack();
    error_log('Payment confirm error: ' . $e->getMessage());
    Response::error('Failed to confirm payment', 500);
}
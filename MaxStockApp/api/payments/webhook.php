<?php
/**
 * POST /api/payments/webhook.php
 * Generic webhook endpoint for payment providers (M-Pesa, Tigo, Airtel, banks).
 *
 * Providers call this URL after customer completes payment.
 * We match by payment_ref (or provider_txn_id) and confirm the payment.
 *
 * Supports:
 *  - Sandbox manual testing
 *  - Future real M-Pesa / Tigo / Airtel / NMB / CRDB callbacks
 *
 * Query/body may contain provider-specific fields.
 * Always respond quickly with 200 so providers don't retry endlessly.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// Always accept POST (some providers also send GET)
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['POST', 'GET'], true)) {
    http_response_code(405);
    exit;
}

// Merge input sources
$input = array_merge($_GET, $_POST);
$json  = getJsonInput();
if (!empty($json)) {
    $input = array_merge($input, $json);
}

// Log raw callback for debugging / audit
$rawLog = [
    'method'    => $method,
    'headers'   => function_exists('getallheaders') ? getallheaders() : [],
    'input'     => $input,
    'raw_body'  => file_get_contents('php://input'),
    'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
    'time'      => date('Y-m-d H:i:s')
];
error_log('Payment webhook received: ' . json_encode($rawLog));

// Extract common fields (different providers use different names)
$paymentRef = clean(
    $input['payment_ref']
    ?? $input['BillRefNumber']
    ?? $input['account_reference']
    ?? $input['reference']
    ?? $input['externalId']
    ?? ''
);

$providerTxnId = clean(
    $input['provider_txn_id']
    ?? $input['TransID']
    ?? $input['MpesaReceiptNumber']
    ?? $input['transaction_id']
    ?? $input['txn_id']
    ?? ''
);

$resultCode = $input['ResultCode']
    ?? $input['result_code']
    ?? $input['status']
    ?? $input['Body']['stkCallback']['ResultCode']
    ?? null;

// Normalize success indicators
$isSuccess = false;
if ($resultCode !== null) {
    // M-Pesa style: 0 = success
    $isSuccess = ((string)$resultCode === '0' || strtolower((string)$resultCode) === 'success');
} elseif (!empty($input['success']) || !empty($input['paid'])) {
    $isSuccess = true;
} elseif (!empty($paymentRef) && empty($resultCode)) {
    // Sandbox / simple confirm: if only payment_ref is sent, treat as success
    $isSuccess = true;
}

if ($paymentRef === '') {
    // Still return 200 so provider stops retrying
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing payment reference']);
    exit;
}

// Load payment
$payment = Database::query(
    "SELECT p.*, o.order_number, o.status AS order_status
     FROM payments p
     INNER JOIN orders o ON o.id = p.order_id
     WHERE p.payment_ref = ?
     LIMIT 1",
    [$paymentRef]
)->fetch();

if (!$payment) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Payment not found']);
    exit;
}

// Already processed → idempotent success response
if ($payment['status'] === 'success') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Already confirmed',
        'payment_ref' => $paymentRef
    ]);
    exit;
}

if (!$isSuccess) {
    // Mark failed
    Database::query(
        "UPDATE payments
         SET status = 'failed',
             provider_txn_id = COALESCE(?, provider_txn_id),
             raw_response = ?,
             updated_at = NOW()
         WHERE id = ?",
        [
            $providerTxnId ?: null,
            json_encode($rawLog),
            $payment['id']
        ]
    );
    Database::query(
        "UPDATE orders SET payment_status = 'failed', updated_at = NOW() WHERE id = ?",
        [$payment['order_id']]
    );

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Failed status recorded']);
    exit;
}

// ----- SUCCESS PATH (same logic as confirm.php) -----
try {
    Database::beginTransaction();

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
            json_encode($rawLog),
            $payment['id']
        ]
    );

    Database::query(
        "UPDATE orders
         SET payment_status = 'paid',
             status = CASE WHEN status = 'pending' THEN 'paid' ELSE status END,
             updated_at = NOW()
         WHERE id = ?",
        [$payment['order_id']]
    );

    // Deduct stock
    $items = Database::query(
        "SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?",
        [$payment['order_id']]
    )->fetchAll();

    $branchId = (int) Database::query(
        "SELECT branch_id FROM orders WHERE id = ?",
        [$payment['order_id']]
    )->fetch()['branch_id'];

    foreach ($items as $item) {
        $variantId = $item['variant_id'] ?: null;
        $qty = (float)$item['quantity'];

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
                "UPDATE stock_levels SET quantity = ?, reserved_qty = ?, updated_at = NOW() WHERE id = ?",
                [$after, $newReserved, $stock['id']]
            );

            Database::query(
                "INSERT INTO stock_movements
                 (branch_id, product_id, variant_id, movement_type, quantity,
                  quantity_before, quantity_after, reference_type, reference_id, notes)
                 VALUES (?, ?, ?, 'sale', ?, ?, ?, 'order', ?, ?)",
                [
                    $branchId, $item['product_id'], $variantId,
                    -$qty, $before, $after, $payment['order_id'],
                    'Webhook confirmed: ' . $paymentRef
                ]
            );

            if ($after <= 0) {
                Database::query(
                    "UPDATE products SET status = 'out_of_stock' WHERE id = ?",
                    [$item['product_id']]
                );
            }
        }
    }

    Database::commit();

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'ResultCode'   => 0,
        'ResultDesc'   => 'Accepted',
        'payment_ref'  => $paymentRef,
        'order_number' => $payment['order_number']
    ]);

} catch (Exception $e) {
    Database::rollBack();
    error_log('Webhook confirm error: ' . $e->getMessage());
    http_response_code(200); // still 200 to stop provider retries
    header('Content-Type: application/json');
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Internal error']);
}
<?php
/**
 * GET /api/orders/detail.php?id=123
 * Returns one order with its line items and latest payment info.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin', 'customer']);
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId <= 0) {
    Response::error('Order ID required');
}

$order = Database::query(
    "SELECT
        o.*,
        u.full_name AS customer_name,
        u.phone AS customer_phone,
        b.name AS branch_name,
        biz.name AS business_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.customer_id
     LEFT JOIN branches b ON b.id = o.branch_id
     LEFT JOIN businesses biz ON biz.id = o.business_id
     WHERE o.id = ?
     LIMIT 1",
    [$orderId]
)->fetch();

if (!$order) {
    Response::notFound('Order not found');
}

// Access control
if ($user['role'] === 'customer') {
    if ((int)$order['customer_id'] !== (int)$user['id']) {
        Response::forbidden('You cannot view this order');
    }
} elseif ($user['role'] !== 'super_admin') {
    if ((int)$order['business_id'] !== (int)$user['business_id']) {
        Response::forbidden('You cannot view this order');
    }
}

// Line items
$items = Database::query(
    "SELECT
        id, product_id, variant_id, product_name, variant_name,
        unit_price, quantity, tax_rate, line_total
     FROM order_items
     WHERE order_id = ?
     ORDER BY id ASC",
    [$orderId]
)->fetchAll();

// Payments for this order
$payments = Database::query(
    "SELECT
        id, payment_ref, provider, amount, currency, status,
        provider_txn_id, phone, paid_at, created_at
     FROM payments
     WHERE order_id = ?
     ORDER BY created_at DESC",
    [$orderId]
)->fetchAll();

Response::success([
    'order'    => $order,
    'items'    => $items,
    'payments' => $payments
]);
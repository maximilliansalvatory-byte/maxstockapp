<?php
/**
 * GET /api/orders/my-orders.php
 * Returns orders for the logged-in customer only.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['customer', 'business_admin', 'staff', 'super_admin']);

// Only show this customer's orders (unless staff/admin testing)
$customerId = (int)$user['id'];

if ($user['role'] !== 'customer') {
    // Staff can pass ?customer_id= for support, but default is their own id
    if (!empty($_GET['customer_id'])) {
        $customerId = (int)$_GET['customer_id'];
    }
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$total = (int) Database::query(
    "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ?",
    [$customerId]
)->fetch()['total'];

$orders = Database::query(
    "SELECT
        o.id,
        o.order_number,
        o.status,
        o.payment_status,
        o.order_type,
        o.total_amount,
        o.currency,
        o.pickup_code,
        o.created_at,
        o.fulfilled_at,
        o.completed_at,
        (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS items_count
     FROM orders o
     WHERE o.customer_id = ?
     ORDER BY o.created_at DESC
     LIMIT $limit OFFSET $offset",
    [$customerId]
)->fetchAll();

Response::success([
    'orders' => $orders,
    'pagination' => [
        'page'  => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
    ]
]);
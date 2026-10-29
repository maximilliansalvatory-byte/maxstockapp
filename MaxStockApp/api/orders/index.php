<?php
/**
 * GET  /api/orders/index.php
 * List orders for a business (with filters)
 *
 * Query params:
 *   business_id, branch_id, status, payment_status, search, page, limit
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);

$businessId    = isset($_GET['business_id']) ? (int)$_GET['business_id'] : (int)($user['business_id'] ?? 0);
$branchId      = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$status        = clean($_GET['status'] ?? '');
$paymentStatus = clean($_GET['payment_status'] ?? '');
$search        = clean($_GET['search'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$limit         = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset        = ($page - 1) * $limit;

// Lock non-super-admin to their own business
if ($user['role'] !== 'super_admin') {
    $businessId = (int)$user['business_id'];
}
if (!$businessId) {
    Response::error('Business context required');
}

$where  = ['o.business_id = ?'];
$params = [$businessId];

if ($branchId) {
    $where[]  = 'o.branch_id = ?';
    $params[] = $branchId;
}
if ($status !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $status;
}
if ($paymentStatus !== '') {
    $where[]  = 'o.payment_status = ?';
    $params[] = $paymentStatus;
}
if ($search !== '') {
    $where[]  = '(o.order_number LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);

// Count
$total = (int) Database::query(
    "SELECT COUNT(*) AS total
     FROM orders o
     LEFT JOIN users u ON u.id = o.customer_id
     WHERE $whereSql",
    $params
)->fetch()['total'];

// Data
$sql = "SELECT
            o.id,
            o.order_number,
            o.business_id,
            o.branch_id,
            o.customer_id,
            o.order_type,
            o.status,
            o.payment_status,
            o.subtotal,
            o.tax_amount,
            o.discount_amount,
            o.total_amount,
            o.currency,
            o.pickup_code,
            o.notes,
            o.fulfilled_at,
            o.completed_at,
            o.cancelled_at,
            o.created_at,
            o.updated_at,
            u.full_name AS customer_name,
            u.phone AS customer_phone,
            (SELECT provider FROM payments WHERE order_id = o.id AND status = 'success' LIMIT 1) AS payment_channel,
            (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS items_count
        FROM orders o
        LEFT JOIN users u ON u.id = o.customer_id
        WHERE $whereSql
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset";

$orders = Database::query($sql, $params)->fetchAll();

Response::success([
    'orders' => $orders,
    'pagination' => [
        'page'  => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
    ]
]);
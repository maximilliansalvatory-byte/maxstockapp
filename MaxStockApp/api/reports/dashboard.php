<?php
/**
 * GET /api/reports/dashboard.php
 * Quick stats for the admin dashboard home page.
 *
 * Query: business_id (optional), branch_id (optional)
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);

$businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : (int)($user['business_id'] ?? 0);
$branchId   = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : (int)($user['branch_id'] ?? 0);

if ($user['role'] !== 'super_admin') {
    $businessId = (int)$user['business_id'];
}

$today = date('Y-m-d');

// ---------- Today's sales & orders ----------
$orderWhere = "business_id = ? AND DATE(created_at) = ? AND status IN ('paid','fulfilled','completed')";
$orderParams = [$businessId, $today];

if ($branchId && $user['role'] !== 'super_admin') {
    // optional: restrict staff to their branch
}

$todayStats = ['revenue' => 0, 'orders_count' => 0];
if ($businessId) {
    $todayStats = Database::query(
        "SELECT
            COALESCE(SUM(total_amount), 0) AS revenue,
            COUNT(*) AS orders_count
         FROM orders
         WHERE $orderWhere",
        $orderParams
    )->fetch() ?: $todayStats;
}

// ---------- Total products ----------
$productsCount = 0;
if ($businessId) {
    $productsCount = (int) Database::query(
        "SELECT COUNT(*) AS c FROM products WHERE business_id = ? AND status != 'inactive'",
        [$businessId]
    )->fetch()['c'];
}

// ---------- Low stock count ----------
$lowStockCount = 0;
if ($businessId) {
    $lowSql = "SELECT COUNT(*) AS c
               FROM products p
               LEFT JOIN stock_levels sl ON sl.product_id = p.id AND sl.variant_id IS NULL
                    " . ($branchId ? " AND sl.branch_id = ?" : "") . "
               WHERE p.business_id = ?
                 AND p.status IN ('active','out_of_stock')
                 AND COALESCE(sl.quantity, 0) <= p.low_stock_threshold";
    $lowParams = $branchId ? [$branchId, $businessId] : [$businessId];
    $lowStockCount = (int) Database::query($lowSql, $lowParams)->fetch()['c'];
}

// ---------- Recent orders (last 8) ----------
$recentOrders = [];
if ($businessId) {
    $recentOrders = Database::query(
        "SELECT
            o.id, o.order_number, o.total_amount, o.status, o.payment_status,
            o.created_at, u.full_name AS customer_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.customer_id
         WHERE o.business_id = ?
         ORDER BY o.created_at DESC
         LIMIT 8",
        [$businessId]
    )->fetchAll();
}

Response::success([
    'today_sales'    => (float)($todayStats['revenue'] ?? 0),
    'orders_today'   => (int)($todayStats['orders_count'] ?? 0),
    'low_stock'      => $lowStockCount,
    'total_products' => $productsCount,
    'recent_orders'  => $recentOrders
]);
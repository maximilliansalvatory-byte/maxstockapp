<?php
/**
 * GET /api/reports/summary.php
 * Query params: business_id, from (Y-m-d), to (Y-m-d), branch_id (optional)
 *
 * Returns real analytics from orders, payments, products & stock.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);

$businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : (int)($user['business_id'] ?? 0);
$branchId   = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : (int)($user['branch_id'] ?? 0);
$from       = clean($_GET['from'] ?? date('Y-m-d', strtotime('-6 days')));
$to         = clean($_GET['to'] ?? date('Y-m-d'));

// Basic date validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    Response::error('Invalid date format. Use YYYY-MM-DD');
}
if ($from > $to) {
    Response::error('From date cannot be after To date');
}

// Super admin can view any business; others are locked to their own
if ($user['role'] !== 'super_admin') {
    $businessId = (int)$user['business_id'];
}
if (!$businessId) {
    Response::error('Business context required');
}

try {
    // --------------------------------------------------
    // 1. Core metrics from orders (paid / fulfilled / completed)
    // --------------------------------------------------
    $orderWhere = "o.business_id = ? AND DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('paid','fulfilled','completed')";
    $orderParams = [$businessId, $from, $to];

    if ($branchId) {
        $orderWhere .= " AND o.branch_id = ?";
        $orderParams[] = $branchId;
    }

    $metricsRow = Database::query(
        "SELECT 
            COUNT(*) AS orders_count,
            COALESCE(SUM(o.total_amount), 0) AS revenue,
            COALESCE(SUM(oi_qty.total_qty), 0) AS items_sold
         FROM orders o
         LEFT JOIN (
             SELECT order_id, SUM(quantity) AS total_qty
             FROM order_items
             GROUP BY order_id
         ) oi_qty ON oi_qty.order_id = o.id
         WHERE $orderWhere",
        $orderParams
    )->fetch();

    $ordersCount = (int)($metricsRow['orders_count'] ?? 0);
    $revenue     = (float)($metricsRow['revenue'] ?? 0);
    $itemsSold   = (float)($metricsRow['items_sold'] ?? 0);
    $aov         = $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0;

    // --------------------------------------------------
    // 2. Daily breakdown (for charts)
    // --------------------------------------------------
    $dailyRows = Database::query(
        "SELECT 
            DATE(o.created_at) AS day,
            COUNT(*) AS orders,
            COALESCE(SUM(o.total_amount), 0) AS revenue,
            COALESCE(SUM(oi_qty.total_qty), 0) AS items
         FROM orders o
         LEFT JOIN (
             SELECT order_id, SUM(quantity) AS total_qty
             FROM order_items
             GROUP BY order_id
         ) oi_qty ON oi_qty.order_id = o.id
         WHERE $orderWhere
         GROUP BY DATE(o.created_at)
         ORDER BY day ASC",
        $orderParams
    )->fetchAll();

    // Fill missing days with zeros so the chart is continuous
    $dailyMap = [];
    foreach ($dailyRows as $r) {
        $dailyMap[$r['day']] = [
            'date'    => $r['day'],
            'orders'  => (int)$r['orders'],
            'revenue' => (float)$r['revenue'],
            'items'   => (float)$r['items']
        ];
    }

    $daily = [];
    $labels = [];
    $period = new DatePeriod(
        new DateTime($from),
        new DateInterval('P1D'),
        (new DateTime($to))->modify('+1 day')
    );
    foreach ($period as $dt) {
        $key = $dt->format('Y-m-d');
        $label = $dt->format('d M');
        $labels[] = $label;
        $daily[] = $dailyMap[$key] ?? [
            'date'    => $key,
            'orders'  => 0,
            'revenue' => 0,
            'items'   => 0
        ];
    }

    // --------------------------------------------------
    // 3. Best selling products
    // --------------------------------------------------
    $bestSellers = Database::query(
        "SELECT 
            oi.product_name AS name,
            SUM(oi.quantity) AS qty,
            SUM(oi.line_total) AS revenue
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE $orderWhere
         GROUP BY oi.product_id, oi.product_name
         ORDER BY qty DESC
         LIMIT 10",
        $orderParams
    )->fetchAll();

    $bestSellers = array_map(function ($r) {
        return [
            'name'    => $r['name'],
            'qty'     => (float)$r['qty'],
            'revenue' => (float)$r['revenue']
        ];
    }, $bestSellers);

    // --------------------------------------------------
    // 4. Revenue by payment channel
    // --------------------------------------------------
    $channelRows = Database::query(
        "SELECT 
            p.provider,
            COALESCE(SUM(p.amount), 0) AS amount,
            COUNT(*) AS txn_count
         FROM payments p
         INNER JOIN orders o ON o.id = p.order_id
         WHERE o.business_id = ?
           AND DATE(p.created_at) BETWEEN ? AND ?
           AND p.status = 'success'
         " . ($branchId ? " AND o.branch_id = ?" : "") . "
         GROUP BY p.provider
         ORDER BY amount DESC",
        $branchId ? [$businessId, $from, $to, $branchId] : [$businessId, $from, $to]
    )->fetchAll();

    $providerLabels = [
        'mpesa'  => 'M-Pesa',
        'tigo'   => 'Tigo/Yas',
        'airtel' => 'Airtel',
        'nmb'    => 'NMB',
        'crdb'   => 'CRDB',
        'cash'   => 'Cash',
        'other'  => 'Other'
    ];

    $channels = array_map(function ($r) use ($providerLabels) {
        return [
            'provider' => $providerLabels[$r['provider']] ?? ucfirst($r['provider']),
            'amount'   => (float)$r['amount'],
            'count'    => (int)$r['txn_count']
        ];
    }, $channelRows);

    // --------------------------------------------------
    // 5. Low stock items (current, not date-filtered)
    // --------------------------------------------------
    $lowStockParams = [$businessId];
    $lowStockSql = "
        SELECT p.id, p.name, p.unit, p.low_stock_threshold,
               COALESCE(sl.quantity, 0) AS quantity
        FROM products p
        LEFT JOIN stock_levels sl ON sl.product_id = p.id
            AND sl.variant_id IS NULL
            " . ($branchId ? " AND sl.branch_id = ?" : "") . "
        WHERE p.business_id = ?
          AND p.status IN ('active', 'out_of_stock')
          AND COALESCE(sl.quantity, 0) <= p.low_stock_threshold
        ORDER BY quantity ASC
        LIMIT 15";

    if ($branchId) {
        $lowStockParams = [$branchId, $businessId];
    }

    $lowStock = Database::query($lowStockSql, $lowStockParams)->fetchAll();
    $lowStock = array_map(function ($r) {
        return [
            'id'                  => (int)$r['id'],
            'name'                => $r['name'],
            'unit'                => $r['unit'],
            'quantity'            => (float)$r['quantity'],
            'low_stock_threshold' => (int)$r['low_stock_threshold']
        ];
    }, $lowStock);

    // --------------------------------------------------
    // 6. Recent orders list (for the detailed table)
    // --------------------------------------------------
    $ordersList = Database::query(
        "SELECT 
            o.id,
            o.order_number,
            o.total_amount,
            o.status,
            o.payment_status,
            o.order_type,
            o.created_at,
            o.pickup_code,
            u.full_name AS customer_name,
            (SELECT provider FROM payments WHERE order_id = o.id AND status = 'success' LIMIT 1) AS payment_channel,
            (SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id = o.id) AS items_count
         FROM orders o
         LEFT JOIN users u ON u.id = o.customer_id
         WHERE $orderWhere
         ORDER BY o.created_at DESC
         LIMIT 100",
        $orderParams
    )->fetchAll();

    // --------------------------------------------------
    // Response
    // --------------------------------------------------
    Response::success([
        'metrics' => [
            'revenue'      => $revenue,
            'orders_count' => $ordersCount,
            'aov'          => $aov,
            'items_sold'   => $itemsSold
        ],
        'daily'       => $daily,
        'labels'      => $labels,
        'bestSellers' => $bestSellers,
        'channels'    => $channels,
        'lowStock'    => $lowStock,
        'orders'      => $ordersList,
        'period'      => ['from' => $from, 'to' => $to]
    ]);

} catch (Exception $e) {
    error_log('Reports summary error: ' . $e->getMessage());
    Response::error('Failed to generate report', 500);
}
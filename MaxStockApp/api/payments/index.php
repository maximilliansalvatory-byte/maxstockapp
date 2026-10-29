<?php
/**
 * GET /api/payments/index.php
 * List payments for a business (with filters + summary stats)
 *
 * Query params:
 *   business_id, provider, status, search, from, to, page, limit
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);

$businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : (int)($user['business_id'] ?? 0);
$provider   = clean($_GET['provider'] ?? '');
$status     = clean($_GET['status'] ?? '');
$search     = clean($_GET['search'] ?? '');
$from       = clean($_GET['from'] ?? '');
$to         = clean($_GET['to'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset     = ($page - 1) * $limit;

if ($user['role'] !== 'super_admin') {
    $businessId = (int)$user['business_id'];
}
if (!$businessId) {
    Response::error('Business context required');
}

$where  = ['o.business_id = ?'];
$params = [$businessId];

if ($provider !== '') {
    $where[]  = 'p.provider = ?';
    $params[] = $provider;
}
if ($status !== '') {
    $where[]  = 'p.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[]  = '(p.payment_ref LIKE ? OR p.phone LIKE ? OR o.order_number LIKE ? OR p.provider_txn_id LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[]  = 'DATE(p.created_at) >= ?';
    $params[] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[]  = 'DATE(p.created_at) <= ?';
    $params[] = $to;
}

$whereSql = implode(' AND ', $where);

// Count
$total = (int) Database::query(
    "SELECT COUNT(*) AS total
     FROM payments p
     INNER JOIN orders o ON o.id = p.order_id
     WHERE $whereSql",
    $params
)->fetch()['total'];

// List
$payments = Database::query(
    "SELECT
        p.id,
        p.order_id,
        p.payment_ref,
        p.provider,
        p.amount,
        p.currency,
        p.status,
        p.provider_txn_id,
        p.phone,
        p.paid_at,
        p.created_at,
        p.updated_at,
        o.order_number,
        o.total_amount AS order_total
     FROM payments p
     INNER JOIN orders o ON o.id = p.order_id
     WHERE $whereSql
     ORDER BY p.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
)->fetchAll();

// Summary stats (today + overall for filters)
$today = date('Y-m-d');

$statsRow = Database::query(
    "SELECT
        COALESCE(SUM(CASE WHEN p.status = 'success' AND DATE(p.paid_at) = ? THEN p.amount ELSE 0 END), 0) AS today_amount,
        SUM(CASE WHEN p.status = 'success' THEN 1 ELSE 0 END) AS success_count,
        SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN p.status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
        SUM(CASE WHEN p.status = 'refunded' THEN 1 ELSE 0 END) AS refunded_count
     FROM payments p
     INNER JOIN orders o ON o.id = p.order_id
     WHERE o.business_id = ?",
    [$today, $businessId]
)->fetch();

Response::success([
    'payments' => $payments,
    'stats' => [
        'today_amount'   => (float)($statsRow['today_amount'] ?? 0),
        'success_count'  => (int)($statsRow['success_count'] ?? 0),
        'pending_count'  => (int)($statsRow['pending_count'] ?? 0),
        'failed_count'   => (int)($statsRow['failed_count'] ?? 0),
        'refunded_count' => (int)($statsRow['refunded_count'] ?? 0)
    ],
    'pagination' => [
        'page'  => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
    ]
]);
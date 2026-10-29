<?php
/**
 * GET /api/businesses/detail.php?id=1
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);
$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($user['business_id'] ?? 0);

if ($id <= 0) {
    Response::error('Business ID required');
}

if ($user['role'] !== 'super_admin' && (int)$user['business_id'] !== $id) {
    Response::forbidden();
}

$biz = Database::query(
    "SELECT id, name, slug, business_type, phone, email, address, city, tin_number, logo_url, status, created_at
     FROM businesses WHERE id = ? LIMIT 1",
    [$id]
)->fetch();

if (!$biz) {
    Response::notFound('Business not found');
}

Response::success($biz);
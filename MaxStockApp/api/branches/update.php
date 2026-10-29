<?php
/**
 * POST /api/branches/update.php
 * Update a branch (name, phone, city, address).
 *
 * Body JSON:
 * {
 *   "id": 1,
 *   "name": "Main Branch - Kariakoo",
 *   "phone": "255712345678",
 *   "city": "Dar es Salaam",
 *   "address": "..."
 * }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'super_admin']);
$input = getJsonInput();

$branchId = (int)($input['id'] ?? $user['branch_id'] ?? 0);
if ($branchId <= 0) {
    Response::error('Branch ID required');
}

$branch = Database::query(
    "SELECT * FROM branches WHERE id = ? LIMIT 1",
    [$branchId]
)->fetch();

if (!$branch) {
    Response::notFound('Branch not found');
}

// Permission: same business
if ($user['role'] !== 'super_admin' && (int)$user['business_id'] !== (int)$branch['business_id']) {
    Response::forbidden();
}

$allowed = ['name', 'phone', 'city', 'address'];
$fields  = [];
$params  = [];

foreach ($allowed as $key) {
    if (array_key_exists($key, $input)) {
        $val = is_string($input[$key]) ? clean($input[$key]) : $input[$key];
        $fields[] = "$key = ?";
        $params[] = $val === '' ? null : $val;
    }
}

if (empty($fields)) {
    Response::error('No fields to update');
}

$params[] = $branchId;

Database::query(
    "UPDATE branches SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?",
    $params
);

Auth::audit('branch.update', 'branches', $branchId, $branch, $input);

$updated = Database::query(
    "SELECT id, business_id, name, code, phone, address, city, is_main, status
     FROM branches WHERE id = ? LIMIT 1",
    [$branchId]
)->fetch();

Response::success(['branch' => $updated], 'Branch updated successfully');
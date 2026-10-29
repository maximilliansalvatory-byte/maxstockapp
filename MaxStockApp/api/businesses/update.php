<?php
/**
 * POST /api/businesses/update.php
 * Update the logged-in admin's business details.
 *
 * Body JSON:
 * {
 *   "name": "Demo Supermarket",
 *   "business_type": "supermarket",
 *   "phone": "255712345678",
 *   "email": "owner@demo.tz",
 *   "city": "Dar es Salaam",
 *   "address": "...",
 *   "tin_number": "..."
 * }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'super_admin']);
$input = getJsonInput();

$businessId = (int)($user['business_id'] ?? 0);
if ($user['role'] === 'super_admin' && !empty($input['business_id'])) {
    $businessId = (int)$input['business_id'];
}
if (!$businessId) {
    Response::error('No business linked to this account');
}

// Verify business exists
$biz = Database::query(
    "SELECT * FROM businesses WHERE id = ? LIMIT 1",
    [$businessId]
)->fetch();

if (!$biz) {
    Response::notFound('Business not found');
}

$allowed = ['name', 'business_type', 'phone', 'email', 'city', 'address', 'tin_number'];
$fields  = [];
$params  = [];

foreach ($allowed as $key) {
    if (array_key_exists($key, $input)) {
        $val = is_string($input[$key]) ? clean($input[$key]) : $input[$key];
        if ($key === 'business_type') {
            $validTypes = ['supermarket', 'restaurant', 'retail', 'other'];
            if (!in_array($val, $validTypes, true)) {
                Response::error('Invalid business type');
            }
        }
        if ($key === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address');
        }
        $fields[] = "$key = ?";
        $params[] = $val === '' ? null : $val;
    }
}

if (empty($fields)) {
    Response::error('No fields to update');
}

// Keep slug in sync if name changes
if (array_key_exists('name', $input) && clean($input['name']) !== '') {
    $newSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', clean($input['name'])));
    $newSlug = trim($newSlug, '-');
    $slugCheck = Database::query(
        "SELECT id FROM businesses WHERE slug = ? AND id != ? LIMIT 1",
        [$newSlug, $businessId]
    )->fetch();
    if ($slugCheck) {
        $newSlug .= '-' . substr(uniqid(), -4);
    }
    $fields[] = 'slug = ?';
    $params[] = $newSlug;
}

$params[] = $businessId;

Database::query(
    "UPDATE businesses SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?",
    $params
);

Auth::audit('business.update', 'businesses', $businessId, $biz, $input);

$updated = Database::query(
    "SELECT id, name, slug, business_type, phone, email, address, city, tin_number, status
     FROM businesses WHERE id = ? LIMIT 1",
    [$businessId]
)->fetch();

Response::success(['business' => $updated], 'Business updated successfully');
<?php
/**
 * POST /api/auth/change-password.php
 * Change password for the currently logged-in user.
 *
 * Body JSON:
 * {
 *   "current_password": "oldpass",
 *   "new_password": "newpass123"
 * }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(); // any logged-in role
$input = getJsonInput();

$current = $input['current_password'] ?? '';
$newPass = $input['new_password'] ?? '';

if ($current === '' || $newPass === '') {
    Response::error('Current password and new password are required');
}

if (strlen($newPass) < 6) {
    Response::error('New password must be at least 6 characters');
}

// Load full user with hash
$row = Database::query(
    "SELECT id, password_hash FROM users WHERE id = ? AND status = 'active' LIMIT 1",
    [$user['id']]
)->fetch();

if (!$row) {
    Response::unauthorized('User not found');
}

if (!Auth::verifyPassword($current, $row['password_hash'])) {
    Response::error('Current password is incorrect', 401);
}

// Prevent reusing the same password
if (Auth::verifyPassword($newPass, $row['password_hash'])) {
    Response::error('New password must be different from the current one');
}

$newHash = Auth::hashPassword($newPass);

Database::query(
    "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
    [$newHash, $user['id']]
);

Auth::audit('auth.password_change', 'users', $user['id']);

Response::success(null, 'Password updated successfully');
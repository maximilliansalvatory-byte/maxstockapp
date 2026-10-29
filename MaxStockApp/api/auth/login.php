<?php
/**
 * POST /api/auth/login.php
 * Body: { "phone": "2557...", "password": "..." }
 * Returns JWT-like token + user data
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = getJsonInput();
$phone    = clean($input['phone'] ?? '');
$password = $input['password'] ?? '';

if (empty($phone) || empty($password)) {
    Response::error('Phone and password are required');
}

// Normalize Tanzanian phone numbers
$phone = preg_replace('/[^0-9]/', '', $phone);
if (str_starts_with($phone, '0')) {
    $phone = '255' . substr($phone, 1);
} elseif (strlen($phone) === 9) {
    $phone = '255' . $phone;
}

$stmt = Database::query(
    "SELECT id, business_id, branch_id, role, full_name, phone, email, password_hash, language, status
     FROM users WHERE phone = ? LIMIT 1",
    [$phone]
);
$user = $stmt->fetch();

if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
    Response::error('Invalid phone or password', 401);
}

if ($user['status'] !== 'active') {
    Response::error('Account is suspended or pending approval', 403);
}

// Update last login
Database::query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);

// Create token
$token = Auth::generateToken([
    'user_id'     => $user['id'],
    'role'        => $user['role'],
    'business_id' => $user['business_id'],
    'branch_id'   => $user['branch_id']
]);

// Also set PHP session for admin dashboard convenience
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = $user['id'];
$_SESSION['role']    = $user['role'];

unset($user['password_hash']);

Auth::audit('auth.login', 'users', $user['id']);

Response::success([
    'token' => $token,
    'user'  => $user
], 'Login successful');

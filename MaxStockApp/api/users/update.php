<?php
/**
 * POST /api/users/update.php
 * Update the logged-in user's profile (name, email, language).
 *
 * Body JSON:
 * {
 *   "full_name": "John Doe",
 *   "email": "john@example.com",
 *   "language": "en"   // optional: en | sw
 * }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth();
$input = getJsonInput();

$fullName = clean($input['full_name'] ?? '');
$email    = clean($input['email'] ?? '');
$language = clean($input['language'] ?? '');

if ($fullName === '') {
    Response::error('Full name is required');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('Invalid email address');
}

if ($language !== '' && !in_array($language, ['en', 'sw'], true)) {
    Response::error('Language must be en or sw');
}

$fields = ['full_name = ?'];
$params = [$fullName];

if (array_key_exists('email', $input)) {
    $fields[] = 'email = ?';
    $params[] = $email !== '' ? $email : null;
}

if ($language !== '') {
    $fields[] = 'language = ?';
    $params[] = $language;
}

$params[] = $user['id'];

Database::query(
    "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?",
    $params
);

Auth::audit('user.update_profile', 'users', $user['id'], null, [
    'full_name' => $fullName,
    'email'     => $email,
    'language'  => $language
]);

// Return updated user
$updated = Database::query(
    "SELECT id, business_id, branch_id, role, full_name, phone, email, language, status
     FROM users WHERE id = ? LIMIT 1",
    [$user['id']]
)->fetch();

Response::success(['user' => $updated], 'Profile updated successfully');
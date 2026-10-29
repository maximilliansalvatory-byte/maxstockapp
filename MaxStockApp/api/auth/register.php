<?php
/**
 * POST /api/auth/register.php
 * 
 * Two modes:
 * 1. Customer registration (role=customer)
 * 2. Business registration (creates business + admin user)
 *
 * Body examples:
 * Customer: { "full_name": "...", "phone": "...", "password": "...", "role": "customer" }
 * Business: { "full_name": "...", "phone": "...", "password": "...", "role": "business_admin",
 *             "business_name": "...", "business_type": "supermarket", "city": "Dar es Salaam" }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = getJsonInput();

$fullName = clean($input['full_name'] ?? '');
$phone    = clean($input['phone'] ?? '');
$password = $input['password'] ?? '';
$email    = clean($input['email'] ?? '');
$role     = clean($input['role'] ?? 'customer');

if (empty($fullName) || empty($phone) || empty($password)) {
    Response::error('Full name, phone and password are required');
}

if (strlen($password) < 6) {
    Response::error('Password must be at least 6 characters');
}

// Normalize phone
$phone = preg_replace('/[^0-9]/', '', $phone);
if (str_starts_with($phone, '0')) {
    $phone = '255' . substr($phone, 1);
} elseif (strlen($phone) === 9) {
    $phone = '255' . $phone;
}

// Check phone uniqueness
$exists = Database::query("SELECT id FROM users WHERE phone = ? LIMIT 1", [$phone])->fetch();
if ($exists) {
    Response::error('Phone number already registered');
}

$allowedRoles = ['customer', 'business_admin'];
if (!in_array($role, $allowedRoles, true)) {
    Response::error('Invalid role');
}

$hash = Auth::hashPassword($password);

try {
    Database::beginTransaction();

    $businessId = null;
    $branchId   = null;

    // Business registration flow
    if ($role === 'business_admin') {
        $businessName = clean($input['business_name'] ?? '');
        $businessType = clean($input['business_type'] ?? 'retail');
        $city         = clean($input['city'] ?? 'Dar es Salaam');

        if (empty($businessName)) {
            Response::error('Business name is required for merchant registration');
        }

        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $businessName));
        $slug = trim($slug, '-');

        // Ensure unique slug
        $slugCheck = Database::query("SELECT id FROM businesses WHERE slug = ?", [$slug])->fetch();
        if ($slugCheck) {
            $slug .= '-' . substr(uniqid(), -4);
        }

        Database::query(
            "INSERT INTO businesses (name, slug, business_type, phone, email, city, status)
             VALUES (?, ?, ?, ?, ?, ?, 'active')",
            [$businessName, $slug, $businessType, $phone, $email ?: null, $city]
        );
        $businessId = (int) Database::lastInsertId();

        // Create main branch
        $branchCode = strtoupper(substr($city, 0, 3)) . '-01';
        Database::query(
            "INSERT INTO branches (business_id, name, code, phone, city, is_main, status)
             VALUES (?, ?, ?, ?, ?, 1, 'active')",
            [$businessId, 'Main Branch', $branchCode, $phone, $city]
        );
        $branchId = (int) Database::lastInsertId();
    }

    // Create user
    Database::query(
        "INSERT INTO users (business_id, branch_id, role, full_name, phone, email, password_hash, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
        [$businessId, $branchId, $role, $fullName, $phone, $email ?: null, $hash]
    );
    $userId = (int) Database::lastInsertId();

    Database::commit();

    // Auto-login
    $token = Auth::generateToken([
        'user_id'     => $userId,
        'role'        => $role,
        'business_id' => $businessId,
        'branch_id'   => $branchId
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['role']    = $role;

    Auth::audit('auth.register', 'users', $userId);

    Response::success([
        'token' => $token,
        'user'  => [
            'id'          => $userId,
            'full_name'   => $fullName,
            'phone'       => $phone,
            'email'       => $email,
            'role'        => $role,
            'business_id' => $businessId,
            'branch_id'   => $branchId
        ]
    ], 'Registration successful', 201);

} catch (Exception $e) {
    Database::rollBack();
    error_log('Registration error: ' . $e->getMessage());
    Response::error('Registration failed. Please try again.', 500);
}

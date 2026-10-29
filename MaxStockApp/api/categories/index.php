<?php
/**
 * GET  /api/categories/index.php  — list categories
 * POST /api/categories/index.php  — create category
 *
 * Query (GET): business_id
 * Body (POST): { "name": "...", "name_sw": "...", "parent_id": null }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user = Auth::user(); // optional auth for public browse
    $businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;

    if (!$businessId && $user && $user['business_id']) {
        $businessId = (int)$user['business_id'];
    }
    if (!$businessId) {
        Response::error('business_id is required');
    }

    $categories = Database::query(
        "SELECT id, business_id, name, name_sw, slug, parent_id, sort_order, status, created_at
         FROM categories
         WHERE business_id = ? AND status = 'active'
         ORDER BY sort_order ASC, name ASC",
        [$businessId]
    )->fetchAll();

    Response::success(['categories' => $categories]);
}

if ($method === 'POST') {
    $user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);
    $input = getJsonInput();

    $name     = clean($input['name'] ?? '');
    $nameSw   = clean($input['name_sw'] ?? '');
    $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

    if ($name === '') {
        Response::error('Category name is required');
    }

    $businessId = (int)($user['business_id'] ?? 0);
    if ($user['role'] === 'super_admin' && !empty($input['business_id'])) {
        $businessId = (int)$input['business_id'];
    }
    if (!$businessId) {
        Response::error('Business context required');
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-');

    // Ensure unique slug per business
    $exists = Database::query(
        "SELECT id FROM categories WHERE business_id = ? AND slug = ? LIMIT 1",
        [$businessId, $slug]
    )->fetch();
    if ($exists) {
        $slug .= '-' . substr(uniqid(), -4);
    }

    Database::query(
        "INSERT INTO categories (business_id, name, name_sw, slug, parent_id, status)
         VALUES (?, ?, ?, ?, ?, 'active')",
        [$businessId, $name, $nameSw ?: null, $slug, $parentId]
    );

    $id = (int) Database::lastInsertId();
    Auth::audit('category.create', 'categories', $id, null, $input);

    Response::success(['id' => $id, 'slug' => $slug], 'Category created', 201);
}

Response::error('Method not allowed', 405);
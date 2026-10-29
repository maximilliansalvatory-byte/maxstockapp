<?php
/**
 * POST /api/products/upload-image.php
 * Upload a product image (multipart/form-data)
 *
 * Form fields:
 *   image  (file)  — required, jpg/png/webp/gif, max 5MB
 *
 * Returns:
 *   { success: true, data: { url: "uploads/products/xxx.jpg" } }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = Auth::requireAuth(['business_admin', 'staff', 'super_admin']);

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    Response::error('No image uploaded or upload failed');
}

$file = $_FILES['image'];
$maxSize = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $maxSize) {
    Response::error('Image too large. Maximum 5MB');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
];

if (!isset($allowed[$mime])) {
    Response::error('Invalid image type. Use JPG, PNG, WEBP or GIF');
}

$ext = $allowed[$mime];
$dir = __DIR__ . '/../../uploads/products';

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$filename = 'prod_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest     = $dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    Response::error('Failed to save image', 500);
}

// Public URL relative to project root
$url = 'uploads/products/' . $filename;

Auth::audit('product.upload_image', 'products', null, null, ['url' => $url]);

Response::success([
    'url'      => $url,
    'filename' => $filename
], 'Image uploaded successfully');
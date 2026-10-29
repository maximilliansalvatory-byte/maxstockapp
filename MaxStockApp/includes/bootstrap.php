<?php
/**
 * MaxStockApp - Bootstrap
 * Include this at the top of every API endpoint and page that needs the core.
 */

// Error reporting (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Timezone - East Africa
date_default_timezone_set('Africa/Dar_es_Salaam');

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// Core includes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Auth.php';

/**
 * Get JSON body from request
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Sanitize string input
 */
function clean(string $value): string
{
    return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

/**
 * Generate unique order number
 */
function generateOrderNumber(): string
{
    return 'MAX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

/**
 * Generate unique payment reference
 */
function generatePaymentRef(string $provider = 'mpesa'): string
{
    return strtoupper($provider) . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

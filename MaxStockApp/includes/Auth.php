<?php
/**
 * MaxStockApp - Authentication & Authorization Helper
 * Uses JWT-style tokens stored in localStorage (customer) or PHP sessions (admin)
 * For simplicity in MVP we support both session and Bearer token.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

class Auth
{
    private const TOKEN_SECRET = 'maxstock_change_this_secret_in_production_2026'; // move to env
    private const TOKEN_EXPIRY = 86400 * 7; // 7 days

    /**
     * Hash a password using PHP's password_hash
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify password
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate a simple signed token (JWT-like, without external lib for MVP)
     */
    public static function generateToken(array $payload): string
    {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload['exp'] = time() + self::TOKEN_EXPIRY;
        $payload['iat'] = time();
        $body = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', "$header.$body", self::TOKEN_SECRET);
        return "$header.$body.$signature";
    }

    /**
     * Validate and decode token
     */
    public static function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $signature] = $parts;
        $expected = hash_hmac('sha256', "$header.$body", self::TOKEN_SECRET);
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $payload = json_decode(base64_decode($body), true);
        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        return $payload;
    }

    /**
     * Get current authenticated user from Authorization header or session
     */
    public static function user(): ?array
    {
        // Try Bearer token first
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $payload = self::validateToken($matches[1]);
            if ($payload && isset($payload['user_id'])) {
                return self::fetchUser((int)$payload['user_id']);
            }
        }

        // Fallback to PHP session (admin dashboard)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            return self::fetchUser((int)$_SESSION['user_id']);
        }

        return null;
    }

    /**
     * Require authentication
     */
    public static function requireAuth(array $allowedRoles = []): array
    {
        $user = self::user();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }
        if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles, true)) {
            Response::forbidden('Insufficient permissions');
        }
        return $user;
    }

    /**
     * Fetch user by ID
     */
    private static function fetchUser(int $id): ?array
    {
        $stmt = Database::query(
            "SELECT id, business_id, branch_id, role, full_name, phone, email, language, status
             FROM users WHERE id = ? AND status = 'active' LIMIT 1",
            [$id]
        );
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Log an audit event
     */
    public static function audit(string $action, ?string $entityType = null, ?int $entityId = null, $old = null, $new = null): void
    {
        $user = self::user();
        Database::query(
            "INSERT INTO audit_logs (user_id, business_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $user['id'] ?? null,
                $user['business_id'] ?? null,
                $action,
                $entityType,
                $entityId,
                $old ? json_encode($old) : null,
                $new ? json_encode($new) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]
        );
    }
}

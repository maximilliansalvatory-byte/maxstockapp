<?php
/**
 * POST /api/payments/initiate.php
 * Start a payment for an existing order.
 *
 * Body JSON:
 * {
 *   "order_id": 12,
 *   "provider": "mpesa",          // mpesa | tigo | airtel | nmb | crdb | cash
 *   "phone": "255712345678"       // required for mobile money
 * }
 *
 * Creates a payments row (status=pending).
 * For M-Pesa in sandbox mode it simulates success after a short flow.
 * Real provider APIs can be plugged in later without changing the frontend.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = getJsonInput();
$orderId  = (int)($input['order_id'] ?? 0);
$provider = strtolower(clean($input['provider'] ?? ''));
$phone    = clean($input['phone'] ?? '');

$allowedProviders = ['mpesa', 'tigo', 'airtel', 'nmb', 'crdb', 'cash'];
if ($orderId <= 0 || !in_array($provider, $allowedProviders, true)) {
    Response::error('Valid order_id and provider are required');
}

// Normalize phone (Tanzania)
if ($phone !== '') {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '255' . substr($phone, 1);
    } elseif (strlen($phone) === 9) {
        $phone = '255' . $phone;
    }
}

if (in_array($provider, ['mpesa', 'tigo', 'airtel'], true) && strlen($phone) < 12) {
    Response::error('Valid phone number is required for mobile money');
}

// Load order
$order = Database::query(
    "SELECT * FROM orders WHERE id = ? LIMIT 1",
    [$orderId]
)->fetch();

if (!$order) {
    Response::notFound('Order not found');
}

if ($order['payment_status'] === 'paid') {
    Response::error('Order is already paid');
}

if (in_array($order['status'], ['cancelled', 'refunded'], true)) {
    Response::error('Cannot pay a cancelled/refunded order');
}

// Prevent duplicate pending payment for same order + provider
$existing = Database::query(
    "SELECT id FROM payments
     WHERE order_id = ? AND provider = ? AND status = 'pending'
     LIMIT 1",
    [$orderId, $provider]
)->fetch();

if ($existing) {
    Response::error('A pending payment already exists for this order. Please wait or try another channel.');
}

$paymentRef = generatePaymentRef($provider);
$amount     = (float)$order['total_amount'];

try {
    Database::beginTransaction();

    // Create payment record
    Database::query(
        "INSERT INTO payments
         (order_id, payment_ref, provider, amount, currency, status, phone)
         VALUES (?, ?, ?, ?, 'TZS', 'pending', ?)",
        [$orderId, $paymentRef, $provider, $amount, $phone ?: null]
    );
    $paymentId = (int) Database::lastInsertId();

    // Mark order payment as pending
    Database::query(
        "UPDATE orders SET payment_status = 'pending', updated_at = NOW() WHERE id = ?",
        [$orderId]
    );

    Database::commit();

    // --------------------------------------------------
    // Provider-specific handling
    // --------------------------------------------------
    $sandbox = true; // set false when real credentials are configured
    $providerResponse = null;
    $message = 'Payment initiated';

    if ($provider === 'cash') {
        // Cash is confirmed immediately by staff (POS)
        // Frontend/admin should call confirm endpoint, or we auto-confirm here for POS convenience
        $providerResponse = ['mode' => 'cash', 'note' => 'Awaiting staff confirmation'];
        $message = 'Cash payment recorded as pending. Confirm at counter.';
    } elseif ($provider === 'mpesa') {
        if ($sandbox) {
            // Sandbox: return instructions; real STK Push comes later
            $providerResponse = [
                'mode'        => 'sandbox',
                'instruction' => 'SANDBOX: Simulate payment by calling /api/payments/confirm.php with this payment_ref',
                'payment_ref' => $paymentRef,
                'phone'       => $phone,
                'amount'      => $amount
            ];
            $message = 'M-Pesa sandbox payment created. Use confirm endpoint to complete.';
        } else {
            // TODO: Real Vodacom M-Pesa STK Push via official API
            // $providerResponse = MpesaService::stkPush($phone, $amount, $paymentRef);
            $providerResponse = ['mode' => 'live', 'status' => 'not_configured'];
            $message = 'M-Pesa live mode not yet configured';
        }
    } else {
        // tigo, airtel, nmb, crdb – same pattern
        $providerResponse = [
            'mode'        => $sandbox ? 'sandbox' : 'live',
            'instruction' => $sandbox
                ? 'SANDBOX: Call /api/payments/confirm.php with payment_ref to complete'
                : 'Live integration pending',
            'payment_ref' => $paymentRef
        ];
        $message = strtoupper($provider) . ' payment initiated (' . ($sandbox ? 'sandbox' : 'live') . ')';
    }

    Response::success([
        'payment_id'   => $paymentId,
        'payment_ref'  => $paymentRef,
        'order_id'     => $orderId,
        'order_number' => $order['order_number'],
        'provider'     => $provider,
        'amount'       => $amount,
        'currency'     => 'TZS',
        'status'       => 'pending',
        'phone'        => $phone,
        'provider_data'=> $providerResponse
    ], $message, 201);

} catch (Exception $e) {
    Database::rollBack();
    error_log('Payment initiate error: ' . $e->getMessage());
    Response::error('Failed to initiate payment', 500);
}

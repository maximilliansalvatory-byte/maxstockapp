<?php
/**
 * POST /api/orders/create.php
 * Create a new order (from customer app or POS)
 *
 * Body JSON example:
 * {
 *   "business_id": 1,
 *   "branch_id": 1,
 *   "customer_id": null,          // optional (null = walk-in)
 *   "order_type": "online",       // online | pos | delivery
 *   "items": [
 *     { "product_id": 1, "variant_id": null, "quantity": 2 }
 *   ],
 *   "discount_code": null,
 *   "notes": ""
 * }
 *
 * Stock is reserved. Order starts as status=pending, payment_status=unpaid.
 * Ownership / stock deduction happens only after payment is confirmed.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Customer can create their own order; staff/admin can create POS orders
$user = Auth::user(); // may be null for guest checkout later
$input = getJsonInput();

$businessId  = (int)($input['business_id'] ?? 0);
$branchId    = (int)($input['branch_id'] ?? 0);
$customerId  = !empty($input['customer_id']) ? (int)$input['customer_id'] : ($user['id'] ?? null);
$orderType   = clean($input['order_type'] ?? 'online');
$items       = $input['items'] ?? [];
$discountCode = clean($input['discount_code'] ?? '');
$notes       = clean($input['notes'] ?? '');

if (!in_array($orderType, ['online', 'pos', 'delivery'], true)) {
    $orderType = 'online';
}

if ($businessId <= 0 || $branchId <= 0 || empty($items) || !is_array($items)) {
    Response::error('business_id, branch_id and at least one item are required');
}

// If logged-in staff, force their business/branch when not super_admin
if ($user && in_array($user['role'], ['business_admin', 'staff'], true)) {
    $businessId = (int)$user['business_id'];
    if (empty($input['branch_id']) && $user['branch_id']) {
        $branchId = (int)$user['branch_id'];
    }
}

try {
    Database::beginTransaction();

    // Validate branch belongs to business
    $branch = Database::query(
        "SELECT id FROM branches WHERE id = ? AND business_id = ? AND status = 'active' LIMIT 1",
        [$branchId, $businessId]
    )->fetch();
    if (!$branch) {
        Database::rollBack();
        Response::error('Invalid branch for this business');
    }

    $subtotal = 0;
    $taxAmount = 0;
    $lineItems = []; // prepared rows for order_items

    foreach ($items as $row) {
        $productId = (int)($row['product_id'] ?? 0);
        $variantId = !empty($row['variant_id']) ? (int)$row['variant_id'] : null;
        $qty       = (float)($row['quantity'] ?? 0);

        if ($productId <= 0 || $qty <= 0) {
            Database::rollBack();
            Response::error('Each item needs a valid product_id and quantity > 0');
        }

        // Load product
        $product = Database::query(
            "SELECT id, name, price, tax_rate, status FROM products
             WHERE id = ? AND business_id = ? LIMIT 1",
            [$productId, $businessId]
        )->fetch();

        if (!$product || $product['status'] === 'inactive') {
            Database::rollBack();
            Response::error('Product not available: ID ' . $productId);
        }

        $unitPrice = (float)$product['price'];
        $variantName = null;

        // Variant price override
        if ($variantId) {
            $variant = Database::query(
                "SELECT id, name, price, status FROM product_variants
                 WHERE id = ? AND product_id = ? LIMIT 1",
                [$variantId, $productId]
            )->fetch();
            if (!$variant || $variant['status'] !== 'active') {
                Database::rollBack();
                Response::error('Variant not available');
            }
            $unitPrice = (float)$variant['price'];
            $variantName = $variant['name'];
        }

        // Check stock
        $stockSql = "SELECT id, quantity, reserved_qty FROM stock_levels
                     WHERE branch_id = ? AND product_id = ? AND " .
                     ($variantId ? "variant_id = ?" : "variant_id IS NULL") . " LIMIT 1";
        $stockParams = $variantId ? [$branchId, $productId, $variantId] : [$branchId, $productId];
        $stock = Database::query($stockSql, $stockParams)->fetch();

        $available = $stock ? ((float)$stock['quantity'] - (float)$stock['reserved_qty']) : 0;
        if ($available < $qty) {
            Database::rollBack();
            Response::error('Insufficient stock for "' . $product['name'] . '". Available: ' . $available);
        }

        $lineTotal = round($unitPrice * $qty, 2);
        $lineTax   = round($lineTotal * ((float)$product['tax_rate'] / 100), 2);

        $subtotal  += $lineTotal;
        $taxAmount += $lineTax;

        $lineItems[] = [
            'product_id'   => $productId,
            'variant_id'   => $variantId,
            'product_name' => $product['name'],
            'variant_name' => $variantName,
            'unit_price'   => $unitPrice,
            'quantity'     => $qty,
            'tax_rate'     => (float)$product['tax_rate'],
            'line_total'   => $lineTotal,
            'stock_id'     => $stock['id'] ?? null
        ];
    }

    // Simple discount (Phase 3 can expand)
    $discountAmount = 0;
    if ($discountCode !== '') {
        $disc = Database::query(
            "SELECT * FROM discount_codes
             WHERE business_id = ? AND code = ? AND status = 'active'
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
               AND (max_uses IS NULL OR used_count < max_uses)
             LIMIT 1",
            [$businessId, $discountCode]
        )->fetch();

        if ($disc && $subtotal >= (float)$disc['min_order_amount']) {
            if ($disc['type'] === 'percentage') {
                $discountAmount = round($subtotal * ((float)$disc['value'] / 100), 2);
            } else {
                $discountAmount = min((float)$disc['value'], $subtotal);
            }
        }
    }

    $totalAmount = round($subtotal + $taxAmount - $discountAmount, 2);
    if ($totalAmount < 0) $totalAmount = 0;

    // Generate order number + pickup code
    $orderNumber = generateOrderNumber();
    $pickupCode  = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    // Insert order
    Database::query(
        "INSERT INTO orders
         (order_number, business_id, branch_id, customer_id, order_type, status, payment_status,
          subtotal, tax_amount, discount_amount, total_amount, currency, discount_code, pickup_code, notes)
         VALUES (?, ?, ?, ?, ?, 'pending', 'unpaid', ?, ?, ?, ?, 'TZS', ?, ?, ?)",
        [
            $orderNumber, $businessId, $branchId, $customerId, $orderType,
            $subtotal, $taxAmount, $discountAmount, $totalAmount,
            $discountCode ?: null, $pickupCode, $notes ?: null
        ]
    );
    $orderId = (int) Database::lastInsertId();

    // Insert items + reserve stock
    foreach ($lineItems as $li) {
        Database::query(
            "INSERT INTO order_items
             (order_id, product_id, variant_id, product_name, variant_name, unit_price, quantity, tax_rate, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $orderId, $li['product_id'], $li['variant_id'],
                $li['product_name'], $li['variant_name'],
                $li['unit_price'], $li['quantity'], $li['tax_rate'], $li['line_total']
            ]
        );

        // Reserve quantity (do not deduct yet)
        if ($li['stock_id']) {
            Database::query(
                "UPDATE stock_levels SET reserved_qty = reserved_qty + ? WHERE id = ?",
                [$li['quantity'], $li['stock_id']]
            );
        }
    }

    // Increment discount usage
    if ($discountCode !== '' && $discountAmount > 0) {
        Database::query(
            "UPDATE discount_codes SET used_count = used_count + 1 WHERE business_id = ? AND code = ?",
            [$businessId, $discountCode]
        );
    }

    Database::commit();

    if ($user) {
        Auth::audit('order.create', 'orders', $orderId, null, [
            'order_number' => $orderNumber,
            'total' => $totalAmount
        ]);
    }

    Response::success([
        'id'            => $orderId,
        'order_number'  => $orderNumber,
        'pickup_code'   => $pickupCode,
        'status'        => 'pending',
        'payment_status'=> 'unpaid',
        'subtotal'      => $subtotal,
        'tax_amount'    => $taxAmount,
        'discount_amount' => $discountAmount,
        'total_amount'  => $totalAmount,
        'currency'      => 'TZS'
    ], 'Order created successfully', 201);

} catch (Exception $e) {
    Database::rollBack();
    error_log('Order create error: ' . $e->getMessage());
    Response::error('Failed to create order', 500);
}
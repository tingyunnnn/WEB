<?php
declare(strict_types=1);

// B: Order placement endpoint.
// Accepts a JSON POST, re-validates prices from the DB, writes orders + order_items.
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/order-options.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// ── Parse body ──────────────────────────────────────────────────────────────
$raw  = (string) file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body.']);
    exit;
}

// ── Validate fields ─────────────────────────────────────────────────────────
$customerName     = trim((string) ($data['customer_name']     ?? ''));
$phone            = trim((string) ($data['phone']             ?? ''));
$deliveryLocation = trim((string) ($data['delivery_location'] ?? ''));
$notes            = trim((string) ($data['notes']             ?? ''));
$pickupTime       = is_string($data['pickup_time'] ?? null) ? trim($data['pickup_time']) : '';
// Student discount only when the customer explicitly opts in (must show a student ID at pickup).
$studentDiscount  = ($data['student_discount'] ?? false) === true;
$items            = $data['items'] ?? [];

$errors = [];

if ($customerName === '')          $errors[] = 'Customer name is required.';
elseif (mb_strlen($customerName) > 100) $errors[] = 'Customer name is too long (max 100 characters).';

if ($phone === '')                 $errors[] = 'Phone number is required.';
elseif (strlen($phone) > 32)      $errors[] = 'Phone number is too long (max 32 characters).';
elseif (!tidebites_valid_phone($phone))
                                   $errors[] = 'Enter a valid phone number (8-15 digits; spaces, hyphens, parentheses and a leading + are allowed).';

if ($deliveryLocation === '')      $errors[] = 'Delivery location is required.';
elseif (!in_array($deliveryLocation, tidebites_pickup_locations(), true))
                                   $errors[] = 'Invalid delivery location.';

if ($pickupTime === '')            $errors[] = 'Pickup time is required.';

if (mb_strlen($notes) > 500)      $errors[] = 'Notes are too long (max 500 characters).';

if (!is_array($items) || count($items) === 0)
                                   $errors[] = 'Your cart is empty.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// Pickup time must be one of the slots bookable right now (see config/order-options.php).
$pickupTimeUtc = tidebites_pickup_time_utc($pickupTime);
if ($pickupTimeUtc === null) {
    $schedule = tidebites_pickup_schedule();
    http_response_code(422);
    echo json_encode([
        'error' => $schedule['accepting']
            ? 'The selected pickup time is no longer available. Please choose another pickup time.'
            : $schedule['message'],
        'code' => 'PICKUP_TIME_UNAVAILABLE',
        'pickup_schedule' => $schedule]);
    exit;
}

// ── Validate item structure ──────────────────────────────────────────────────
$validatedItems = [];
$seenIds = [];

foreach ($items as $item) {
    if (!is_array($item)) { http_response_code(400); echo json_encode(['error' => 'Invalid item data.']); exit; }
    $itemId = filter_var( $item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $itemQty = filter_var( $item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    $expectedPrice = $item['expected_price'] ?? null;
    if ( $itemId === false || $itemQty === false || !is_int($expectedPrice) || $expectedPrice < 0 || isset($seenIds[$itemId])) {
        http_response_code(400); echo json_encode(['error' => 'Invalid item data.']); exit;}
    $seenIds[$itemId] = true;
    $validatedItems[] = [ 'id' => (int) $itemId, 'quantity' => (int) $itemQty, 'expected_price' => $expectedPrice ];
}

// ── Database transaction ─────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = tidebites_pdo();
    $pdo->beginTransaction();

    // Lock and fetch the requested active food items.
    $ids          = array_unique(array_column($validatedItems, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, name, price
         FROM food_items
         WHERE id IN ($placeholders) AND is_active = 1
         FOR UPDATE"
    );
    $stmt->execute($ids);
    $rows    = $stmt->fetchAll();
    $dbItems = [];
    foreach ($rows as $row) {
        $dbItems[(int) $row['id']] = $row;
    }

    // Verify all requested items are still active.
    $unavailableIds = [];
    foreach ($validatedItems as $vi) {
        if (!isset($dbItems[$vi['id']])) $unavailableIds[] = $vi['id'];
    }
    if ($unavailableIds) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode([
            'error' => 'One or more items are no longer available. Please refresh the page and update your cart.',
            'code' => 'ITEM_UNAVAILABLE',
            'unavailable_ids' => $unavailableIds]);
        exit;
    }

    $priceChanges = [];
    foreach ($validatedItems as $vi) {
        $dbRow = $dbItems[$vi['id']];
        if ((float) $dbRow['price'] !== (float) $vi['expected_price']) {
            $priceChanges[] = [ 'id' => $vi['id'], 'name' => $dbRow['name'], 'old_price' => $vi['expected_price'], 'new_price' => (float) $dbRow['price']];
            }
        }

    if (count($priceChanges) > 0) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'error' => 'Menu prices have changed. Please review your order again.',
            'code' => 'PRICE_CHANGED',
            'price_changes' => $priceChanges ]);
        exit;
    }

    // Re-calculate amounts server-side (integer NT$, optional 10 % student discount).
    $orderItems = [];
    $subtotal   = 0;
    foreach ($validatedItems as $vi) {
        $dbRow     = $dbItems[$vi['id']];
        if (!preg_match('/^\d+\.00$/', (string) $dbRow['price'])) {
            $pdo->rollBack();
            http_response_code(503);
            echo json_encode([
                'error' => 'Invalid menu price configuration.']);
            exit;
        }
        $unitPrice = (int) $dbRow['price'];
        $qty       = $vi['quantity'];
        $subtotal += $unitPrice * $qty;
        $orderItems[] = [
            'id'         => $vi['id'],
            'name'       => $dbRow['name'],
            'unit_price' => $unitPrice,
            'quantity'   => $qty,
        ];
    }

    $discountAmount = $studentDiscount ? intdiv($subtotal + 5, 10) : 0;
    $totalAmount = $subtotal - $discountAmount;

    // Insert order.
    $stmtOrder = $pdo->prepare(
        'INSERT INTO orders
             (customer_name, phone, delivery_location, notes, pickup_time,
              subtotal, discount_amount, total_amount)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtOrder->execute([
        $customerName,
        $phone,
        $deliveryLocation,
        $notes !== '' ? $notes : null,
        $pickupTimeUtc,
        $subtotal,
        $discountAmount,
        $totalAmount,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    // Insert order_items (snapshot of name + unit price at time of order).
    $stmtItem = $pdo->prepare(
        'INSERT INTO order_items
             (order_id, food_item_id, food_name, unit_price, quantity)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($orderItems as $oi) {
        $stmtItem->execute([
            $orderId,
            $oi['id'],
            $oi['name'],
            $oi['unit_price'],
            $oi['quantity'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'order_id' => $orderId,
        'customer_name' => $customerName,
        'phone' => $phone,
        'delivery_location' => $deliveryLocation,
        'notes' => $notes,
        'pickup_time' => $pickupTime, // Asia/Taipei, "YYYY-MM-DD HH:MM"
        'items' => $orderItems,
        'subtotal' => $subtotal,
        'student_discount' => $discountAmount > 0,
        'discount_amount' => $discountAmount,
        'total_amount' => $totalAmount
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo !== null) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $re) {}
    }
    http_response_code(503);
    echo json_encode(['error' => 'Order could not be saved. Please try again.']);
}


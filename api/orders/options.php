<?php
declare(strict_types=1);

// Customer-facing order options (pickup locations and bookable pickup times) as JSON. Only GET is accepted.
require_once dirname(__DIR__, 2) . '/config/order-options.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

echo json_encode([
    'pickup_locations' => tidebites_pickup_locations(),
    // Computed on the server in Asia/Taipei so the customer's device clock does not matter.
    'pickup_schedule' => tidebites_pickup_schedule(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

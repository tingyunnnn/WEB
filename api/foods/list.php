<?php
declare(strict_types=1);

// B: Customer-facing menu API — returns active food_items as JSON.
// Only GET is accepted. Config is provided by A's config/database.php.
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    // Allow normal web requests; the guard above is a library pattern — skip for endpoints.
}
require_once dirname(__DIR__, 2) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = tidebites_pdo();
    $stmt = $pdo->prepare(
        'SELECT id, name, description, price, category, image_path, image_alt, sort_order
         FROM food_items
         WHERE is_active = 1
         ORDER BY sort_order, id'
    );
    $stmt->execute();
    $items = $stmt->fetchAll();

    // Cast types so JSON output is well-typed for JS.
    foreach ($items as &$item) {
        $item['id']         = (int)   $item['id'];
        $item['price']      = (float) $item['price'];
        $item['sort_order'] = (int)   $item['sort_order'];
    }
    unset($item);

    echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (RuntimeException | PDOException $e) {
    http_response_code(503);
    echo json_encode(['error' => 'Menu is temporarily unavailable. Please try again later.']);
}


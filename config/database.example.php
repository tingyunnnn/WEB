<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
// Copy OUTSIDE the repository/document root, never to a publicly served directory.
// Default destination: ../tidebites-local/database.php (relative to repository root).
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'tidebites',
    'username' => 'tidebites_app',
    'password' => '', // Set locally. No shared/default credential is provided.
];

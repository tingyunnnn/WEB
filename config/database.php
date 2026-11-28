<?php
declare(strict_types=1);

// This is a library, never a public diagnostic endpoint.
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function tidebites_config_path(): string
{
    return getenv('TIDEBITES_CONFIG') ?: dirname(__DIR__, 2) . '/tidebites-local/database.php';
}

function tidebites_external_config_path(string $path, bool $allowMissingParent = false): string
{
    if ($path === '' || str_contains($path, "\0") || str_ends_with($path, '/') || str_ends_with($path, '\\')) {
        throw new RuntimeException('Invalid local configuration path.');
    }
    $resolved = realpath($path);
    if ($resolved === false) {
        $normalizedPath = str_replace('\\', '/', $path);
        $isAbsolute = str_starts_with($normalizedPath, '/') || preg_match('/\A[A-Za-z]:\//', $normalizedPath) === 1;
        if (!$isAbsolute) {
            $cwd = getcwd();
            if ($cwd === false) throw new RuntimeException('Cannot resolve current working directory.');
            $normalizedPath = rtrim(str_replace('\\', '/', $cwd), '/') . '/' . $normalizedPath;
        }
        if (preg_match('/\A(\/\/[^\/]+\/[^\/]+\/|[A-Za-z]:\/|\/)/', $normalizedPath, $match) !== 1) {
            throw new RuntimeException('Invalid local configuration path.');
        }
        $prefix = $match[1];
        $stack = [];
        foreach (explode('/', substr($normalizedPath, strlen($prefix))) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') { array_pop($stack); continue; }
            $stack[] = $segment;
        }
        if (!$stack) throw new RuntimeException('Invalid local configuration path.');
        $tail = [];
        $ancestor = false;
        while ($stack) {
            $real = realpath($prefix . implode('/', $stack));
            if ($real !== false) { $ancestor = $real; break; }
            array_unshift($tail, array_pop($stack));
        }
        if ($ancestor === false) {
            $realPrefix = realpath($prefix);
            if ($realPrefix === false) throw new RuntimeException('Local configuration directory does not exist.');
            $ancestor = $realPrefix;
        }
        if (!$allowMissingParent && count($tail) > 1) {
            throw new RuntimeException('Local configuration directory does not exist.');
        }
        $resolved = rtrim($ancestor, '/\\') . ($tail ? DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $tail) : '');
    }
    $normalized = strtolower(str_replace('\\', '/', $resolved));
    $roots = [dirname(__DIR__)];
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT'])) $roots[] = $_SERVER['DOCUMENT_ROOT'];
    foreach ($roots as $root) {
        $root = realpath($root);
        if ($root === false) continue;
        $root = rtrim(strtolower(str_replace('\\', '/', $root)), '/');
        if ($normalized === $root || str_starts_with($normalized, $root . '/')) {
            throw new RuntimeException('Local configuration must be outside the repository and document root.');
        }
    }
    return $resolved;
}

function tidebites_db_config(): array
{
    try {
        $path = tidebites_external_config_path(tidebites_config_path());
        if (!is_file($path) || !is_readable($path)) throw new RuntimeException();
        $config = require $path;
        if (!is_array($config)) throw new RuntimeException();
        return $config;
    } catch (Throwable) {
        throw new RuntimeException('Database configuration unavailable. See docs/database-setup.md.');
    }
}

function tidebites_connect(#[SensitiveParameter] array $config): PDO
{
    try {
        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '' || str_contains($config[$key], "\0")) {
                throw new InvalidArgumentException();
            }
        }
        $trimmedUsername = trim($config['username']);
        if (!preg_match('/\A[a-zA-Z0-9.:-]+\z/', $config['host'])
            || !preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/', $config['database'])
            || $trimmedUsername === ''
            || strtolower($trimmedUsername) === 'root'
            || !is_int($config['port'] ?? null) || $config['port'] < 1 || $config['port'] > 65535) {
            throw new InvalidArgumentException();
        }
        $pdo = new PDO(
            'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'] . ';charset=utf8mb4',
            $config['username'], $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5,
             PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]
        );
        // Consistent DATETIME storage across A/B's machines; display in Asia/Taipei in the UI.
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    } catch (Throwable) {
        // Deliberately do not chain/log the original exception, DSN, credentials, or SQL.
        throw new RuntimeException('Database connection unavailable. See docs/database-setup.md.');
    }
}

function tidebites_pdo(): PDO
{
    static $pdo = null;
    return $pdo ??= tidebites_connect(tidebites_db_config());
}

<?php
declare(strict_types=1);
// PHP built-in server only. Never use `php -S ... -t .` without this router.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$deny = static function (): never {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Not found.';
    exit;
};
$raw = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
$path = rawurldecode($raw);
// Reject encoded separators, repeated encoding, Windows aliases/ADS, path-info and dot segments.
if (preg_match('/%(?:2f|5c|00)/i', $raw) || preg_match('~[\\\\\x00-\x20\x7F:%]~', $path)) $deny();
foreach (explode('/', $path) as $part) {
    if ($part === '.' || $part === '..' || str_starts_with($part, '.') || str_ends_with($part, '.')) $deny();
}
$lower = strtolower($path);
if (preg_match('~^/(?:database|config|docs|tests|scripts|admin/includes)(?:/|$)~', $lower)
    || in_array($lower, ['/router.php', '/start.bat', '/start.ps1', '/readme.md', '/agents.md'], true)) $deny();
$root = str_replace('\\', '/', realpath(__DIR__));
$file = realpath(__DIR__ . $path);
if ($file === false) $deny();
if (is_dir($file)) {
    if (is_file($file . '/index.php')) $file .= '/index.php';
    elseif (is_file($file . '/index.html')) $file .= '/index.html';
    else $deny();
}
$normalized = str_replace('\\', '/', $file);
if (!str_starts_with(strtolower($normalized), strtolower($root) . '/') || !is_file($file)) $deny();
$relative = substr($normalized, strlen($root) + 1);
// Repeat checks on resolved paths (also prevents Windows short-name and symlink bypasses).
if (preg_match('~^(?:database|config|docs|tests|scripts|admin/includes)(?:/|$)~i', $relative)) $deny();
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (str_starts_with(strtolower($relative), 'uploads/')) {
    if (!preg_match('~\Auploads/foods/tb-[a-f0-9]{32}\.(jpg|png|webp)\z~', $relative, $match)) $deny();
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'");
    header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$match[1]]);
    header('Content-Length: ' . filesize($file));
    if ($_SERVER['REQUEST_METHOD'] === 'GET') readfile($file);
    return true;
}
if ($extension === 'php') {
    if (strtolower($relative) === 'router.php') $deny();
    if (str_starts_with(strtolower($relative), 'admin/') && !preg_match('~\Aadmin/(?:index|login|logout|foods/(?:index|create|edit|delete|status)|orders/(?:index|detail))\.php\z~', $relative)) $deny();
    // Explicit exact-file execution avoids PHP's fallback to a parent index for unknown paths.
    $_SERVER['SCRIPT_NAME'] = '/' . $relative;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    require $file;
    return true;
}
if (!in_array($extension, ['html', 'css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'svg', 'ico', 'mp3', 'woff', 'woff2', 'ttf'], true)) $deny();
return false;

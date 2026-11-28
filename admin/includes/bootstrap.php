<?php
declare(strict_types=1);
if (!defined('TB_ADMIN_ENTRY')) { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/config/database.php';

ini_set('display_errors', '0');
ini_set('log_errors', '0'); // Never send SQL, credentials or request bodies to a web log.
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' blob:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function admin_base(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
    $position = strrpos($script, '/admin/');
    $base = $position === false ? '' : substr($script, 0, $position);
    return '/' . ltrim($base . '/', '/');
}
function admin_url(string $path = ''): string { return admin_base() . 'admin/' . $path; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_redirect(string $path): never { header('Location: ' . admin_url($path), true, 303); exit; }
function admin_fail(int $status, string $message): never
{
    http_response_code($status);
    $pageTitle = 'Unable to continue';
    $publicPage = true;
    require __DIR__ . '/header.php';
    echo '<section class="error-page panel"><p class="eyebrow">TideBites Admin</p><h1>Unable to continue</h1><p>' . e($message) . '</p><a class="button" href="' . e(admin_url()) . '">Return to Admin</a></section>';
    require __DIR__ . '/footer.php';
    exit;
}
set_exception_handler(static function (Throwable $error): never {
    admin_fail(503, 'This service is temporarily unavailable. Please try again later.');
});
function admin_method(array $allowed): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        admin_fail(405, 'This request method is not allowed.');
    }
}
function admin_runtime(): string
{
    static $directory;
    if (isset($directory)) return $directory;
    $config = tidebites_external_config_path(tidebites_config_path());
    $path = getenv('TIDEBITES_ADMIN_RUNTIME') ?: dirname($config) . '/admin-runtime-' . substr(hash('sha256', dirname(__DIR__, 2)), 0, 12);
    $path = tidebites_external_config_path($path, true);
    if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('Runtime unavailable.');
    if (!is_writable($path)) throw new RuntimeException('Runtime unavailable.');
    return $directory = $path;
}
function admin_post(string $key): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : ''; }
function admin_flash(string $message): void { $_SESSION['flash'] = $message; }
function admin_image_url(string $path): string
{
    // Only local food images; never render a database value as an arbitrary URL.
    if (!preg_match('~\A(?:assets/images/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)|uploads/foods/tb-[a-f0-9]{32}\.(?:jpg|png|webp))\z~', $path)) return admin_base() . 'assets/images/icon.png';
    return admin_base() . $path;
}
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
admin_start_session();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $limit = trim((string)ini_get('post_max_size'));
    $unit = strtolower(substr($limit, -1));
    $bytes = (int)$limit * (['g' => 1073741824, 'm' => 1048576, 'k' => 1024][$unit] ?? 1);
    if ($bytes > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $bytes) admin_fail(413, 'This upload is too large. Choose an image no larger than 2 MiB.');
}

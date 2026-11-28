<?php
declare(strict_types=1);
if (!defined('TB_ADMIN_ENTRY')) { http_response_code(404); exit; }
function admin_csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function admin_csrf_field(): void { echo '<input type="hidden" name="csrf" value="' . e(admin_csrf()) . '">'; }
function admin_check_csrf(): void
{
    if (!hash_equals(admin_csrf(), admin_post('csrf'))) admin_fail(403, 'Your form has expired. Reload the page and try again.');
}
function admin_form_token(): string
{
    $tokens = $_SESSION['create_tokens'] ?? [];
    $tokens = array_filter($tokens, static fn(int $created): bool => $created > time() - 3600);
    $token = bin2hex(random_bytes(24));
    $tokens[$token] = time();
    $_SESSION['create_tokens'] = array_slice($tokens, -20, null, true);
    return $token;
}
function admin_check_create_token(): void
{
    $created = $_SESSION['create_tokens'][admin_post('form_token')] ?? 0;
    if ($created < time() - 3600) admin_fail(409, 'This form has expired or was already saved. Open a new Add Food Item form.');
}

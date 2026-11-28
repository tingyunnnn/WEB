<?php
declare(strict_types=1);
if (!defined('TB_ADMIN_ENTRY')) { http_response_code(404); exit; }
function admin_order_id(mixed $id): int
{
    if (!is_string($id) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $id) || (int)$id > 4294967295) admin_fail(404, 'This order could not be found.');
    return (int)$id;
}
function admin_order_time(string $utc): string
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Taipei'))->format('M j, Y · H:i');
}
function admin_cents(string $amount): int
{
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');
    return (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
}
/** Whole NT dollars, rounded half up (matches the customer site, e.g. NT$1,234). */
function admin_money(int $cents): string
{
    return 'NT$' . number_format(intdiv($cents + 50, 100), 0, '.', ',');
}
function admin_pickup_time(?string $utc): string
{
    return $utc === null ? 'Not scheduled' : admin_order_time($utc);
}
function admin_optional(?string $value): string { return $value === null || trim($value) === '' ? 'Not provided' : $value; }

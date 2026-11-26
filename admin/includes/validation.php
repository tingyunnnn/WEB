<?php
declare(strict_types=1);
if (!defined('TB_ADMIN_ENTRY')) { http_response_code(404); exit; }
function admin_id(mixed $value): int
{
    if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $value) || (int)$value > 4294967295) admin_fail(404, 'This food item could not be found.');
    return (int)$value;
}
function admin_food_input(array $source): array
{
    $values = $errors = [];
    foreach (['name', 'description', 'price', 'category', 'image_alt', 'sort_order', 'is_active'] as $key) {
        $raw = $source[$key] ?? '';
        $values[$key] = is_string($raw) && mb_check_encoding($raw, 'UTF-8') ? trim($raw) : '';
        if (!is_string($raw) || !mb_check_encoding($raw, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $values[$key])) $errors[$key] = 'Enter valid text.';
    }
    foreach (['name' => 150, 'image_alt' => 500] as $key => $max) {
        if ($values[$key] === '' || preg_match('/\A\s*\z/u', $values[$key]) || mb_strlen($values[$key]) > $max) $errors[$key] = "Enter between 1 and $max characters.";
    }
    if ($values['description'] === '' || preg_match('/\A\s*\z/u', $values['description']) || strlen($values['description']) > 65535) $errors['description'] = 'Enter a description of up to 65,535 UTF-8 bytes.';
    // Keep the current whole-NT-dollar menu policy; do not change DECIMAL(10,2).
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.00)?\z/', $values['price'])) $errors['price'] = 'Enter a whole NT dollar amount from 0 to 99,999,999.';
    if (!in_array($values['category'], ['meals', 'desserts'], true)) $errors['category'] = 'Choose Meals or Desserts.';
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,4})\z/', $values['sort_order']) || (int)$values['sort_order'] > 65535) $errors['sort_order'] = 'Enter a whole number from 0 to 65,535.';
    if (!in_array($values['is_active'], ['0', '1'], true)) $errors['is_active'] = 'Choose Active or Inactive.';
    return [$values, $errors];
}
function admin_field_error(string $field, array $errors): void
{
    if (isset($errors[$field])) echo '<p class="field-error" id="error-' . e($field) . '">' . e($errors[$field]) . '</p>';
}
function admin_field_attributes(string $field, array $errors): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="error-' . e($field) . '"' : '';
}

<?php
declare(strict_types=1);
if (!defined('TB_ADMIN_ENTRY')) { http_response_code(404); exit; }
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/upload.php';
function admin_food(PDO $pdo, int $id, bool $lock = false): array
{
    $query = $pdo->prepare('SELECT * FROM food_items WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]);
    $food = $query->fetch();
    if (!$food) throw new OutOfBoundsException('Food not found.');
    return $food;
}
function admin_get_food(int $id): array
{
    try { return admin_food(tidebites_pdo(), $id); }
    catch (OutOfBoundsException) { admin_fail(404, 'This food item could not be found. It may have been deleted.'); }
}
/** A single transaction owns each data change. New files are cleaned only after rollback. */
function admin_save_food(PDO $pdo, ?int $id, array $values, ?string $newImage): bool
{
    $old = null;
    try {
        $pdo->beginTransaction();
        if ($id !== null) $old = admin_food($pdo, $id, true);
        $image = $newImage ?? ($old['image_path'] ?? null);
        if ($image === null) throw new InvalidArgumentException('Image required.');
        $params = [$values['name'], $values['description'], $values['price'], $values['category'], $image,
            $values['image_alt'], (int)$values['sort_order'], (int)$values['is_active']];
        if ($id === null) {
            $query = $pdo->prepare('INSERT INTO food_items (name,description,price,category,image_path,image_alt,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?)');
        } else {
            $query = $pdo->prepare('UPDATE food_items SET name=?,description=?,price=?,category=?,image_path=?,image_alt=?,sort_order=?,is_active=? WHERE id=?');
            $params[] = $id;
        }
        $query->execute($params);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (!admin_cleanup_image($pdo, $newImage)) error_log('TideBites: an unused upload needs local cleanup.');
        throw $error;
    }
    return $newImage !== null && $old !== null ? admin_cleanup_image($pdo, $old['image_path']) : true;
}

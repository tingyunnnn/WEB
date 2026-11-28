<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
admin_method(['POST']);
admin_require();
admin_check_csrf();
require dirname(__DIR__) . '/includes/foods.php';
$id = admin_id($_POST['id'] ?? null);
$status = admin_post('is_active');
if (!in_array($status, ['0', '1'], true)) admin_fail(422, 'Choose a valid availability status.');
$pdo = tidebites_pdo();
try {
    $pdo->beginTransaction();
    admin_food($pdo, $id, true);
    $query = $pdo->prepare('UPDATE food_items SET is_active=? WHERE id=?');
    $query->execute([(int)$status, $id]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($error instanceof OutOfBoundsException) admin_fail(404, 'This food item could not be found.');
    throw $error;
}
admin_flash($status === '1' ? 'Food item activated.' : 'Food item deactivated. You can activate it again at any time.');
admin_redirect('foods/index.php');

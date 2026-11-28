<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
admin_method(['GET', 'POST']);
admin_require();
require dirname(__DIR__) . '/includes/foods.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') admin_check_csrf();
$id = admin_id($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null));
$food = admin_get_food($id);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (admin_post('confirm') !== 'delete') admin_fail(422, 'Please confirm deletion from the food item page.');
    $pdo = tidebites_pdo();
    try {
        $pdo->beginTransaction();
        $food = admin_food($pdo, $id, true);
        $query = $pdo->prepare('DELETE FROM food_items WHERE id=?');
        $query->execute([$id]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof OutOfBoundsException) admin_fail(404, 'This food item has already been deleted.');
        throw $error;
    }
    $clean = admin_cleanup_image($pdo, $food['image_path']);
    admin_flash($clean ? 'Food item deleted. Existing order history has been preserved.' : 'Food item deleted. An unused image could not be removed; ask your local maintainer to check storage.');
    admin_redirect('foods/index.php');
}
$pageTitle = 'Delete Food Item';
require dirname(__DIR__) . '/includes/header.php';
?>
<a class="back-link" href="<?= e(admin_url('foods/index.php')) ?>">← Food Management</a>
<section class="panel delete-panel" aria-labelledby="delete-title">
  <p class="eyebrow danger-text">Permanent action</p><h1 id="delete-title">Delete this food item?</h1>
  <div class="delete-item"><img src="<?= e(admin_image_url($food['image_path'])) ?>" alt="<?= e($food['image_alt']) ?>"><div><h2><?= e($food['name']) ?></h2><p>NT$<?= e(number_format((float)$food['price'], 0, '.', ',')) ?> ·<?= $food['category'] === 'meals' ? 'Meals' : 'Desserts' ?></p></div></div>
  <p>This permanently removes the item from your menu. It cannot be undone. Existing order history will be preserved.</p>
  <div class="delete-alternative"><strong>Just taking it off the menu?</strong><p>Deactivate the item to keep its details and bring it back later.</p><form method="post" action="<?= e(admin_url('foods/status.php')) ?>"><?php admin_csrf_field(); ?><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="is_active" value="0"><button type="submit" class="button">Deactivate Instead</button></form></div>
  <form method="post" action="<?= e(admin_url('foods/delete.php')) ?>" class="delete-confirm"><?php admin_csrf_field(); ?><input type="hidden" name="id" value="<?= e($id) ?>"><label class="checkbox-label"><input type="checkbox" name="confirm" value="delete" required> I understand this item will be permanently deleted.</label><div class="delete-actions"><a class="button" href="<?= e(admin_url('foods/index.php')) ?>">Cancel</a><button type="submit" class="button danger">Delete Food Item</button></div></form>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

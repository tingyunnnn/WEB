<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
admin_method(['GET']);
$admin = admin_require();
$search = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 150)) : '';
$category = is_string($_GET['category'] ?? null) && in_array($_GET['category'], ['meals', 'desserts'], true) ? $_GET['category'] : '';
$status = is_string($_GET['status'] ?? null) && in_array($_GET['status'], ['0', '1'], true) ? $_GET['status'] : '';
$where = $params = [];
if ($search !== '') {
    $where[] = "(name LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!')";
    $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
    $params[] = $pattern; $params[] = $pattern;
}
if ($category !== '') { $where[] = 'category=?'; $params[] = $category; }
if ($status !== '') { $where[] = 'is_active=?'; $params[] = (int)$status; }
$pdo = tidebites_pdo();
$query = $pdo->prepare('SELECT * FROM food_items' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY sort_order,id');
$query->execute($params);
$foods = $query->fetchAll();
$stats = $pdo->query('SELECT COUNT(*) AS total,COALESCE(SUM(is_active=1),0) AS active,COALESCE(SUM(is_active=0),0) AS inactive FROM food_items')->fetch();
$pageTitle = 'Food Management';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">Your menu, at a glance</p><h1>Food Management</h1><p class="muted">A fresh menu starts here. Add, edit and organize your food items.</p></div><a class="button primary" href="<?= e(admin_url('foods/create.php')) ?>"><span aria-hidden="true">＋</span> Add Food Item</a></div>
<section class="stats" aria-label="Menu statistics">
  <div><span>Total Items</span><strong><?= e($stats['total']) ?></strong><small>Across your menu</small></div>
  <div><span><i class="dot active" aria-hidden="true"></i> Active Items</span><strong><?= e($stats['active']) ?></strong><small>Available on the menu</small></div>
  <div><span><i class="dot" aria-hidden="true"></i> Inactive Items</span><strong><?= e($stats['inactive']) ?></strong><small>Ready when you are</small></div>
</section>
<section class="menu-panel" aria-label="Food items">
  <form class="filters" method="get" action="<?= e(admin_url('foods/index.php')) ?>">
    <div class="field search-field"><label for="q">Search food</label><input type="search" id="q" name="q" placeholder="Search by name or description" value="<?= e($search) ?>" maxlength="150"></div>
    <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option><?php foreach (['meals' => 'Meals', 'desserts' => 'Desserts'] as $value => $label): ?><option value="<?= $value ?>"<?= $category === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><option value="1"<?= $status === '1' ? ' selected' : '' ?>>Active</option><option value="0"<?= $status === '0' ? ' selected' : '' ?>>Inactive</option></select></div>
    <button class="button" type="submit">Apply Filters</button><a class="clear-filters" href="<?= e(admin_url('foods/index.php')) ?>">Reset</a>
  </form>
  <div class="list-caption"><p><strong><?= count($foods) ?></strong> <?= count($foods) === 1 ? 'item' : 'items' ?><?= $where ? ' found' : ' in your menu' ?></p><span>Ordered by display order, then item ID</span></div>
  <div class="table-labels" aria-hidden="true"><span>Food item</span><span>Price</span><span>Category / Order</span><span>Status</span><span>Actions</span></div>
  <div class="food-list">
    <?php foreach ($foods as $food): ?>
    <article class="food-row" data-food-id="<?= e($food['id']) ?>">
      <div class="food-info"><img src="<?= e(admin_image_url($food['image_path'])) ?>" alt="<?= e($food['image_alt']) ?>" width="88" height="76" loading="lazy"><div><h2><?= e($food['name']) ?></h2><p><?= e(mb_strimwidth($food['description'], 0, 110, '…', 'UTF-8')) ?></p></div></div>
      <div class="food-price"><span class="mobile-label">Price</span><strong>NT$<?= e(number_format((float)$food['price'], 0, '.', ',')) ?></strong></div>
      <div class="food-category"><span><?= $food['category'] === 'meals' ? 'Meals' : 'Desserts' ?></span><small>Order <?= e($food['sort_order']) ?></small></div>
      <div class="food-status"><span class="badge <?= $food['is_active'] ? 'is-active' : '' ?>"><?= $food['is_active'] ? 'Active' : 'Inactive' ?></span><form method="post" action="<?= e(admin_url('foods/status.php')) ?>"><?php admin_csrf_field(); ?><input type="hidden" name="id" value="<?= e($food['id']) ?>"><input type="hidden" name="is_active" value="<?= $food['is_active'] ? '0' : '1' ?>"><button class="text-button" type="submit" aria-label="<?= $food['is_active'] ? 'Deactivate ' : 'Activate ' ?><?= e($food['name']) ?>"><?= $food['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></div>
      <div class="food-actions"><a class="button compact" href="<?= e(admin_url('foods/edit.php?id=' . $food['id'])) ?>" aria-label="Edit <?= e($food['name']) ?>">Edit</a><a class="button compact danger-quiet" href="<?= e(admin_url('foods/delete.php?id=' . $food['id'])) ?>" aria-label="Delete <?= e($food['name']) ?>">Delete</a></div>
    </article>
    <?php endforeach; ?>
    <?php if (!$foods): ?><div class="empty-state"><h2>No food items found</h2><p>Try another search or clear your filters.</p><a href="<?= e(admin_url('foods/create.php')) ?>">Add your next food item →</a></div><?php endif; ?>
  </div>
</section>
<p class="list-note">Deactivate keeps an item for later. Delete permanently removes it from your menu.</p>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

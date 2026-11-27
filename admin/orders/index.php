<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
admin_method(['GET']);
admin_require();
require dirname(__DIR__) . '/includes/orders.php';
$search = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 150)) : '';
$pageInput = $_GET['page'] ?? '1';
$page = is_string($pageInput) && preg_match('/\A[1-9][0-9]{0,6}\z/', $pageInput) ? (int)$pageInput : 1;
$params = [];
$where = '';
if ($search !== '') {
    $where = " WHERE (id=? OR customer_name LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!' OR delivery_location LIKE ? ESCAPE '!')";
    $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
    $params = [ctype_digit($search) && strlen($search) <= 10 && (int)$search <= 4294967295 ? (int)$search : 0, $pattern, $pattern, $pattern];
}
$pdo = tidebites_pdo();
$count = $pdo->prepare('SELECT COUNT(*) FROM orders' . $where);
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / 20));
$page = min($page, $pages);
$query = $pdo->prepare('SELECT id,customer_name,phone,delivery_location,total_amount,created_at FROM orders' . $where . ' ORDER BY created_at DESC,id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
$query->execute($params);
$orders = $query->fetchAll();
$pageTitle = 'Orders';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">Fresh from campus</p><h1>Orders</h1><p class="muted">Every order, every detail. A clear view of what came in.</p></div><span class="deck-chip">Order log · View only</span></div>
<section class="menu-panel orders-panel">
<form method="get" action="<?= e(admin_url('orders/index.php')) ?>" class="order-search"><div class="field"><label for="q">Search orders</label><input type="search" id="q" name="q" maxlength="150" value="<?= e($search) ?>" placeholder="Order ID, name, phone or delivery location"></div><button type="submit" class="button primary">Search</button><a href="<?= e(admin_url('orders/index.php')) ?>">Reset</a></form>
<div class="list-caption"><p><strong><?= $total ?></strong> <?= $total === 1 ? 'order' : 'orders' ?><?= $search !== '' ? ' found' : '' ?></p><span>Newest first · Asia/Taipei (UTC+08:00)</span></div>
<?php if (!$orders): ?>
<div class="empty-state ocean-empty"><img src="<?= e(admin_base()) ?>assets/images/submarine1.png" alt=""><p class="eyebrow">All quiet on the coast</p><h2><?= $search === '' ? 'No orders yet' : 'No matching orders' ?></h2><p><?= $search === '' ? 'When a campus order arrives, you will find it here.' : 'Try a different name, phone number or order ID.' ?></p></div>
<?php else: ?>
<div class="order-list">
<?php foreach ($orders as $order): ?>
<article class="order-card" data-order-id="<?= e($order['id']) ?>"><div class="order-card-heading"><h2>Order #<?= e($order['id']) ?></h2><span class="order-amount"><?= e(admin_money(admin_cents($order['total_amount']))) ?></span></div><dl class="order-facts"><div><dt>Customer</dt><dd><?= e(admin_optional($order['customer_name'])) ?></dd></div><div><dt>Phone</dt><dd><?= e(admin_optional($order['phone'])) ?></dd></div><div><dt>Delivery Location</dt><dd><?= e($order['delivery_location']) ?></dd></div><div><dt>Order Date · Taipei</dt><dd><?= e(admin_order_time($order['created_at'])) ?></dd></div></dl><a class="button" href="<?= e(admin_url('orders/detail.php?id=' . $order['id'])) ?>" aria-label="View details for order <?= e($order['id']) ?>">View Details <span aria-hidden="true">→</span></a></article>
<?php endforeach; ?>
</div>
<nav class="order-pagination" aria-label="Order pages"><?php if ($page > 1): ?><a class="button" href="?<?= e(http_build_query(['q' => $search, 'page' => $page - 1])) ?>">← Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a class="button" href="?<?= e(http_build_query(['q' => $search, 'page' => $page + 1])) ?>">Next →</a><?php endif; ?></nav>
<?php endif; ?></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

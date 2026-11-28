<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
admin_method(['GET']);
admin_require();
require dirname(__DIR__) . '/includes/orders.php';
$id = admin_order_id($_GET['id'] ?? null);
$pdo = tidebites_pdo();
$query = $pdo->prepare('SELECT * FROM orders WHERE id=?');
$query->execute([$id]);
$order = $query->fetch();
if (!$order) admin_fail(404, 'This order could not be found.');
$query = $pdo->prepare('SELECT food_name,unit_price,quantity FROM order_items WHERE order_id=? ORDER BY id');
$query->execute([$id]);
$items = $query->fetchAll();
// A discount is only given when the customer opted in to show a student ID at pickup.
$studentDiscount = admin_cents($order['discount_amount']) > 0;
$pageTitle = 'Order #' . $id;
require dirname(__DIR__) . '/includes/header.php';
?>
<a class="back-link" href="<?= e(admin_url('orders/index.php')) ?>">← Orders</a>
<div class="page-heading"><div><p class="eyebrow">A little joy, on record</p><h1>Order #<?= e($id) ?></h1><p class="muted"><?= e(admin_order_time($order['created_at'])) ?> · Asia/Taipei (UTC+08:00)</p></div><span class="deck-chip">Order details · View only</span></div>
<div class="order-detail-grid">
<section class="panel"><div class="panel-heading"><span class="section-number">01</span><h2>Delivery details</h2></div><dl class="delivery-facts"><div><dt>Customer Name</dt><dd><?= e(admin_optional($order['customer_name'])) ?></dd></div><div><dt>Phone</dt><dd><?= e(admin_optional($order['phone'])) ?></dd></div><div><dt>Delivery Location</dt><dd><?= e($order['delivery_location']) ?></dd></div><div><dt>Pickup Time · Taipei</dt><dd><?= e(admin_pickup_time($order['pickup_time'])) ?></dd></div><div><dt>Student ID</dt><dd><?= $studentDiscount ? '<strong>Check the orderer\'s student ID at pickup</strong> (10% student discount applied)' : 'Not required (no student discount)' ?></dd></div><div><dt>Order Notes</dt><dd class="order-notes"><?= e($order['notes'] === null || trim($order['notes']) === '' ? 'No notes provided.' : $order['notes']) ?></dd></div></dl></section>
<section class="panel"><div class="panel-heading"><span class="section-number">02</span><div><h2>On the order</h2><p>Names and prices saved at checkout.</p></div></div>
<div class="order-items"><?php foreach ($items as $item): ?><article class="order-line"><div><h3><?= e($item['food_name']) ?></h3><p><?= e(admin_money(admin_cents($item['unit_price']))) ?> × <?= e($item['quantity']) ?></p></div><strong><?= e(admin_money(admin_cents($item['unit_price']) * (int)$item['quantity'])) ?></strong></article><?php endforeach; ?><?php if (!$items): ?><p>No order items recorded.</p><?php endif; ?></div>
<dl class="order-totals"><div><dt>Subtotal</dt><dd><?= e(admin_money(admin_cents($order['subtotal']))) ?></dd></div><div><dt>Student Discount</dt><dd>−<?= e(admin_money(admin_cents($order['discount_amount']))) ?></dd></div><div class="grand-total"><dt>Total</dt><dd><?= e(admin_money(admin_cents($order['total_amount']))) ?></dd></div></dl></section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

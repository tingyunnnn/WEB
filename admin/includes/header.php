<?php
if (!defined('TB_ADMIN_ENTRY')) { http_response_code(404); exit; }
$publicPage = $publicPage ?? false;
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle ?? 'Admin') ?> | TideBites</title>
  <link rel="icon" href="<?= e(admin_base()) ?>assets/images/icon.png">
  <link rel="stylesheet" href="<?= e(admin_url('assets/css/admin.css')) ?>">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Alex+Brush&amp;family=Lato:wght@400;700&amp;family=Raleway:wght@400;600;700&amp;display=swap">
  <link rel="stylesheet" href="<?= e(admin_url('assets/css/deck.css')) ?>">
  <script src="<?= e(admin_url('assets/js/admin.js')) ?>" defer></script>
</head>
<body class="<?= $publicPage ? 'public-page' : 'admin-page' ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?php if (!$publicPage): ?>
<header class="site-header">
  <a class="brand" href="<?= e(admin_url('foods/index.php')) ?>"><img src="<?= e(admin_base()) ?>assets/images/icon.png" alt="TideBites"><span>CONTROL<br>DECK</span></a>
  <button type="button" class="nav-toggle button" aria-controls="admin-nav" aria-expanded="false" hidden>Menu</button>
  <nav id="admin-nav" aria-label="Admin navigation">
    <a href="<?= e(admin_url('foods/index.php')) ?>"<?= !str_contains($_SERVER['SCRIPT_NAME'], '/orders/') ? ' aria-current="page"' : '' ?>>Food Management</a>
    <a href="<?= e(admin_url('orders/index.php')) ?>"<?= str_contains($_SERVER['SCRIPT_NAME'], '/orders/') ? ' aria-current="page"' : '' ?>>Orders</a>
    <a href="<?= e(admin_base()) ?>index.html">Return to Website <span aria-hidden="true">↗</span></a>
    <form method="post" action="<?= e(admin_url('logout.php')) ?>"><?php admin_csrf_field(); ?><button type="submit" class="button quiet">Logout</button></form>
  </nav>
</header>
<?php endif; ?>
<?php if (!$publicPage): ?>
<div class="deck-banner"><div><p class="eyebrow">TideBites Control Deck</p><p class="deck-slogan">Catch a Wave of Flavor!</p><p>A little care behind every campus favorite.</p></div><img src="<?= e(admin_base()) ?>assets/images/dolphin.png" alt="" aria-hidden="true"><span class="deck-bubble bubble-one" aria-hidden="true"></span><span class="deck-bubble bubble-two" aria-hidden="true"></span></div>
<?php endif; ?>
<main id="main" class="<?= $publicPage ? 'public-main' : 'main-shell' ?>">
<?php if (!$publicPage && isset($_SESSION['flash'])): ?>
<div class="notice" role="status"><?= e($_SESSION['flash']) ?></div>
<?php unset($_SESSION['flash']); endif; ?>

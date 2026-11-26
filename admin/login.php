<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require __DIR__ . '/includes/bootstrap.php';
admin_method(['GET', 'POST']);
if (admin_current()) admin_redirect('foods/index.php');
if (session_status() !== PHP_SESSION_ACTIVE) admin_redirect('login.php');
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_check_csrf();
    $username = trim(admin_post('username'));
    $password = admin_post('password');
    if (!admin_login_attempt(substr($username, 0, 100))) {
        http_response_code(429);
        header('Retry-After: 900');
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } elseif (!preg_match('/\A[A-Za-z0-9_.-]{3,100}\z/', $username) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0") || !admin_login($username, $password)) {
        http_response_code(422);
        $error = 'Unable to sign in. Check your username and password.';
    } else { admin_redirect('foods/index.php'); }
    unset($password);
}
$pageTitle = 'Admin Login';
$publicPage = true;
require __DIR__ . '/includes/header.php';
?>
<div class="login-layout">
  <section class="login-brand" aria-label="TideBites">
    <img class="login-logo" src="<?= e(admin_base()) ?>assets/images/icon.png" alt="TideBites">
    <p class="eyebrow">TideBites Control Deck</p>
    <h1>Good food.<br>A little ocean joy.</h1>
    <p>Keep your menu fresh and your favorites<br class="desktop-break"> ready for the next study break.</p>
    <div class="ocean-lines" aria-hidden="true"><span></span><span></span><span></span></div>
    <img class="login-dolphin" src="<?= e(admin_base()) ?>assets/images/dolphin.png" alt="" aria-hidden="true">
    <img class="login-seaweed" src="<?= e(admin_base()) ?>assets/images/seaweed.png" alt="" aria-hidden="true">
    <p class="brand-caption">Catch a Wave of Flavor!</p>
  </section>
  <section class="login-form-wrap">
    <div class="login-form-inner">
      <p class="eyebrow">Your daily splash of good food</p>
      <h2>Welcome back.</h2>
      <p class="muted">Sign in to manage your food menu.</p>
      <?php if ($error): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <?php if (isset($_GET['logged_out'])): ?><div class="notice" role="status">You have been signed out.</div><?php endif; ?>
      <form method="post" action="<?= e(admin_url('login.php')) ?>" class="login-form">
        <?php admin_csrf_field(); ?>
        <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= e(mb_substr($username, 0, 100)) ?>" required maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false"></div>
        <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="current-password"></div>
        <button class="button primary full" type="submit">Sign In <span aria-hidden="true">→</span></button>
      </form>
      <p class="login-footnote">Authorized administrators only.</p>
      <a class="return-link" href="<?= e(admin_base()) ?>index.html">← Return to Website</a>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

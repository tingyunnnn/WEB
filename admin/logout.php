<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require __DIR__ . '/includes/bootstrap.php';
admin_method(['POST']);
admin_require();
admin_check_csrf();
admin_logout();
admin_redirect('login.php?logged_out=1');

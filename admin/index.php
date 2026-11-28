<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require __DIR__ . '/includes/bootstrap.php';
admin_method(['GET']);
admin_require();
admin_redirect('foods/index.php');

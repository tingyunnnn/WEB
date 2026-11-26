<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/tools.php';

/** Read the existing HTML independently of SQL, without rewriting any frontend files. */
function tb_original_menu(): array
{
    $html = file_get_contents(dirname(__DIR__) . '/index.html');
    if ($html === false) throw new RuntimeException('Cannot read original HTML.');
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    $rows = [];
    foreach ($xpath->query('//*[@id="menu-grid"]/article') as $article) {
        $text = static function (string $class) use ($xpath, $article): string {
            return trim($xpath->query('.//p[@class="' . $class . '"]', $article)->item(0)->textContent);
        };
        $image = $xpath->query('.//img', $article)->item(0);
        preg_match('/NT\$\s*(\d+)/', $text('card-meta'), $price);
        $rows[] = ['id' => (int)$article->getAttribute('data-id'), 'name' => $text('card-title'),
            'description' => $text('card-desc'), 'price' => $price[1] . '.00',
            'category' => $article->getAttribute('data-category'), 'image_path' => $image->getAttribute('src'),
            'image_alt' => $image->getAttribute('alt'), 'sort_order' => count($rows) + 1];
    }
    if (!$rows) throw new RuntimeException('No original menu data found.');
    return $rows;
}

function tb_run_tests(#[SensitiveParameter] array $input): array
{
    $report = ['result' => 'FAIL', 'checks' => []];
    $check = static function (string $name, bool $condition) use (&$report): void {
        $report['checks'][$name] = $condition ? 'PASS' : 'FAIL';
        if (!$condition) throw new RuntimeException('Failed check: ' . $name);
    };
    $reject = static function (string $name, callable $operation, array $codes) use ($check): void {
        try { $operation(); } catch (PDOException $e) {
            $check($name, in_array((int)($e->errorInfo[1] ?? 0), $codes, true));
            return;
        }
        $check($name, false);
    };
    $suffix = bin2hex(random_bytes(6));
    $database = 'tbtest' . $suffix;
    $other = 'tbother' . $suffix;
    $username = 'tbtest_' . $suffix;
    // Exercise SQL quoting, UTF-8 stdin/config writing and credential handling too.
    $password = "Tb9!中文'\\" . bin2hex(random_bytes(24));
    $configPath = sys_get_temp_dir() . '/tidebites-test-' . $suffix . '.php';
    $oldConfig = getenv('TIDEBITES_CONFIG');
    $owned = $otherOwned = $configOwned = false;
    $root = $pdo = null;
    $report['temporary_resources'] = ['database' => $database, 'account' => $username . '@localhost', 'other_database' => $other];
    try {
        $root = tb_admin_connection($input);
        $report['server_version'] = $root->query('SELECT VERSION()')->fetchColumn();
        tb_provision($root, $database, $username, $password);
        $owned = true;
        $root->exec("CREATE DATABASE `$other`");
        $otherOwned = true;
        $root->exec("CREATE TABLE `$other`.permission_probe (id INT)");
        $config = ['host' => $input['host'] ?? '127.0.0.1', 'port' => $input['port'] ?? 3306,
            'database' => $database, 'username' => $username, 'password' => $password];
        tb_write_config($configPath, $config);
        $configOwned = true;
        putenv('TIDEBITES_CONFIG=' . $configPath);
        $pdo = tidebites_pdo();
        $check('shared_pdo_and_external_config', $pdo->query('SELECT CURRENT_USER()')->fetchColumn() === $username . '@localhost');
        $check('native_prepared_statements', $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false);
        $check('connection_charset', $pdo->query('SELECT @@character_set_connection')->fetchColumn() === 'utf8mb4');
        $check('utc_session', $pdo->query('SELECT @@session.time_zone')->fetchColumn() === '+00:00');
        $tables = $root->query("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.tables WHERE table_schema='$database' ORDER BY TABLE_NAME")->fetchAll();
        $check('four_expected_tables', array_column($tables, 'TABLE_NAME') === ['admins', 'food_items', 'order_items', 'orders']);
        $check('innodb_and_utf8mb4', count(array_filter($tables, static fn($t) => $t['ENGINE'] === 'InnoDB' && $t['TABLE_COLLATION'] === 'utf8mb4_0900_ai_ci')) === 4);
        $check('primary_keys', (int)$root->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema='$database' AND constraint_type='PRIMARY KEY'")->fetchColumn() === 4);
        $check('foreign_keys', (int)$root->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema='$database'")->fetchColumn() === 2);
        $columns = 'id, name, description, price, category, image_path, image_alt, sort_order';
        $menu = $pdo->query("SELECT $columns FROM food_items ORDER BY sort_order, id")->fetchAll();
        $expected = tb_original_menu();
        $utf8Dom = new DOMDocument('1.0', 'UTF-8');
        $utf8Dom->loadHTML('<?xml encoding="UTF-8"><p id="t">海味餐點 · 🌊</p>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $check('html_utf8_parsing', $utf8Dom->getElementById('t')?->textContent === '海味餐點 · 🌊');
        $check('seed_matches_original_html_exactly', $menu === $expected);
        $report['seed_count'] = count($menu);
        $report['categories'] = array_count_values(array_column($menu, 'category'));
        $check('image_files_exist', count(array_filter($menu, static fn($row) => is_file(dirname(__DIR__) . '/' . $row['image_path']))) === count($menu));
        $category = $pdo->prepare('SELECT id FROM food_items WHERE is_active=1 AND category=? ORDER BY sort_order,id');
        foreach (['meals', 'desserts'] as $value) {
            $category->execute([$value]);
            $check('category_order_' . $value, $category->fetchAll(PDO::FETCH_COLUMN) === array_values(array_column(array_filter($expected, static fn($row) => $row['category'] === $value), 'id')));
        }
        $check('no_default_admin', (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0);
        tb_import($root, $database);
        $check('repeat_import_no_duplicates', $pdo->query("SELECT $columns FROM food_items ORDER BY sort_order,id")->fetchAll() === $expected);
        $pdo->exec("UPDATE food_items SET name='Edited locally', is_active=0 WHERE id=1");
        $pdo->exec('DELETE FROM food_items WHERE id=10');
        $before = $pdo->query('SELECT * FROM food_items ORDER BY id')->fetchAll();
        tb_import($root, $database);
        $check('repeat_import_preserves_edits_and_partial_deletions', $before === $pdo->query('SELECT * FROM food_items ORDER BY id')->fetchAll());
        $check('inactive_food_excluded', (int)$pdo->query('SELECT COUNT(*) FROM food_items WHERE is_active=1 AND id=1')->fetchColumn() === 0);
        try { tb_provision($root, $database, 'tbunused_' . $suffix, $password); $check('existing_database_refused', false); }
        catch (RuntimeException $e) { $check('existing_database_refused', str_starts_with($e->getMessage(), 'Database already exists.')); }
        try { tb_provision($root, 'tbunused' . $suffix, $username, $password); $check('existing_account_refused', false); }
        catch (RuntimeException $e) { $check('existing_account_refused', str_starts_with($e->getMessage(), 'Account already exists.')); }
        $refused = false;
        try { tb_write_config($configPath, $config); } catch (RuntimeException) { $refused = true; }
        $check('existing_config_refused', $refused && (require $configPath) === $config);
        try { tidebites_external_config_path(__DIR__ . '/forbidden.php'); $check('config_inside_web_root_refused', false); }
        catch (RuntimeException $e) { $check('config_inside_web_root_refused', str_contains($e->getMessage(), 'outside')); }
        $missingSubdirRefused = false;
        $traversalRefused = false;
        try { tidebites_external_config_path(__DIR__ . '/nonexistent_' . $suffix . '/forbidden.php', true); }
        catch (RuntimeException $e) { $missingSubdirRefused = str_contains($e->getMessage(), 'outside'); }
        try { tidebites_external_config_path(dirname(__DIR__, 2) . '/tbmissing_' . $suffix . '/../' . basename(dirname(__DIR__)) . '/newdir/forbidden.php', true); }
        catch (RuntimeException $e) { $traversalRefused = str_contains($e->getMessage(), 'outside'); }
        $check('config_inside_web_root_missing_subdir_refused', $missingSubdirRefused && $traversalRefused && !file_exists(__DIR__ . '/nonexistent_' . $suffix));
        try { tidebites_connect(array_replace($config, ['password' => $password . 'wrong'])); $check('connection_errors_sanitized', false); }
        catch (RuntimeException $e) { $check('connection_errors_sanitized', $e->getMessage() === 'Database connection unavailable. See docs/database-setup.md.' && $e->getPrevious() === null && !str_contains((string)$e, $password)); }
        $refused = false;
        try { tidebites_connect(array_replace($config, ['username' => 'root'])); } catch (RuntimeException) { $refused = true; }
        $check('web_root_account_refused', $refused);
        $wsRootRefused = $blankUserRefused = false;
        try { tidebites_connect(array_replace($config, ['username' => ' RoOt '])); } catch (RuntimeException) { $wsRootRefused = true; }
        try { tidebites_connect(array_replace($config, ['username' => '   '])); } catch (RuntimeException) { $blankUserRefused = true; }
        $check('web_root_whitespace_account_refused', $wsRootRefused && $blankUserRefused);
        $nulPwRefused = false;
        try { tb_provision($root, 'tbunused' . $suffix, 'tbunused_' . $suffix, str_repeat('a', 16) . "\0"); }
        catch (InvalidArgumentException) { $nulPwRefused = true; }
        $check('app_password_nul_rejected', $nulPwRefused);

        $insert = $pdo->prepare('INSERT INTO food_items (name,description,price,category,image_path,image_alt,sort_order) VALUES (?,?,?,?,?,?,?)');
        $name = "中文餐點 English 🌊 O'Reilly";
        $description = "中文 English 🍜 literal: x'); DROP TABLE food_items; --";
        $insert->execute([$name, $description, '19.50', 'meals', $expected[0]['image_path'], '測試圖片 🌊', 99]);
        $foodId = (int)$pdo->lastInsertId();
        $select = $pdo->prepare('SELECT * FROM food_items WHERE id=?');
        $select->execute([$foodId]); $food = $select->fetch(); $select->closeCursor();
        $check('food_create_read_utf8mb4_and_bound_literals', $food['name'] === $name && $food['description'] === $description && $food['image_alt'] === '測試圖片 🌊' && $food['price'] === '19.50');
        $reject('negative_price_rejected', fn() => $insert->execute(['Test','Test','-1','meals','test.png','Test',0]), [3819]);
        $reject('invalid_category_rejected', fn() => $insert->execute(['Test','Test','1','MEALS','test.png','Test',0]), [3819]);
        $reject('blank_name_rejected', fn() => $insert->execute([' ','Test','1','meals','test.png','Test',0]), [3819]);
        $reject('duplicate_primary_key_rejected', fn() => $root->exec("INSERT INTO `$database`.food_items SELECT * FROM `$database`.food_items WHERE id=2"), [1062]);

        $orderInsert = $pdo->prepare('INSERT INTO orders (delivery_location,notes,subtotal,discount_amount,total_amount) VALUES (?,?,?,?,?)');
        $itemInsert = $pdo->prepare('INSERT INTO order_items (order_id,food_item_id,food_name,unit_price,quantity) VALUES (?,?,?,?,?)');
        $pdo->beginTransaction();
        $orderInsert->execute(['Campus Main Gate', '中文 English 🌊', '39.00', '4.00', '35.00']);
        $orderId = (int)$pdo->lastInsertId();
        $itemInsert->execute([$orderId, $foodId, $name, '19.50', 2]);
        $pdo->commit();
        $check('guest_order_transaction_commit', (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE id=$orderId AND customer_name IS NULL AND phone IS NULL")->fetchColumn() === 1);
        $reject('invalid_order_total_rejected', fn() => $orderInsert->execute(['Gate',null,'10','1','99']), [3819]);
        $reject('invalid_discount_rejected', fn() => $orderInsert->execute(['Gate',null,'10','11','-1']), [3819]);
        $reject('blank_location_rejected', fn() => $orderInsert->execute([' ',null,'10','1','9']), [3819]);
        $reject('quantity_zero_rejected', fn() => $itemInsert->execute([$orderId,$foodId,$name,'19.50',0]), [3819]);
        $reject('quantity_over_99_rejected', fn() => $itemInsert->execute([$orderId,$foodId,$name,'19.50',100]), [3819]);
        $reject('missing_order_fk_rejected', fn() => $itemInsert->execute([4294967295,$foodId,$name,'19.50',1]), [1452]);
        $pdo->beginTransaction();
        $orderInsert->execute(['Library Entrance',null,'19.50','2.00','17.50']);
        $rolledBackId = (int)$pdo->lastInsertId();
        $reject('missing_food_fk_rejected', fn() => $itemInsert->execute([$rolledBackId,4294967295,'Missing','19.50',1]), [1452]);
        $pdo->rollBack();
        $check('failed_order_transaction_rollback', (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE id=$rolledBackId")->fetchColumn() === 0);
        $update = $pdo->prepare('UPDATE food_items SET name=?,price=? WHERE id=?');
        $update->execute(['Changed name','88.25',$foodId]);
        $select->execute([$foodId]); $updated = $select->fetch(); $select->closeCursor();
        $check('food_update', $updated['name'] === 'Changed name' && $updated['price'] === '88.25');
        $snapshot = $pdo->query("SELECT food_name,unit_price,food_item_id FROM order_items WHERE order_id=$orderId")->fetch();
        $check('price_and_name_snapshot_survives_edit', $snapshot['food_name'] === $name && $snapshot['unit_price'] === '19.50');
        $delete = $pdo->prepare('DELETE FROM food_items WHERE id=?'); $delete->execute([$foodId]);
        $select->execute([$foodId]); $check('food_delete', $select->fetch() === false); $select->closeCursor();
        $snapshot = $pdo->query("SELECT food_name,unit_price,food_item_id FROM order_items WHERE order_id=$orderId")->fetch();
        $check('food_delete_preserves_history_and_sets_null', $snapshot['food_item_id'] === null && $snapshot['food_name'] === $name && $snapshot['unit_price'] === '19.50');
        $check('order_amount_snapshot_survives_food_delete', $pdo->query("SELECT total_amount FROM orders WHERE id=$orderId")->fetchColumn() === '35.00');
        $root->exec("DELETE FROM `$database`.orders WHERE id=$orderId");
        $check('explicit_order_delete_cascades_items', (int)$pdo->query("SELECT COUNT(*) FROM order_items WHERE order_id=$orderId")->fetchColumn() === 0);

        $adminPassword = 'TestOnly!' . bin2hex(random_bytes(12));
        $adminId = tb_create_admin($pdo, 'test_admin', $adminPassword);
        $hash = $pdo->query("SELECT password_hash FROM admins WHERE id=$adminId")->fetchColumn();
        $check('admin_hash_roundtrip', $hash !== $adminPassword && password_verify($adminPassword, $hash));
        $reject('admin_username_unique', fn() => tb_create_admin($pdo, 'TEST_ADMIN', $adminPassword), [1062]);
        $reject('other_database_select_denied', fn() => $pdo->query("SELECT * FROM `$other`.permission_probe"), [1142,1044]);
        $reject('system_user_table_denied', fn() => $pdo->query('SELECT * FROM mysql.user'), [1142,1044]);
        $reject('schema_ddl_denied', fn() => $pdo->exec('CREATE TABLE forbidden (id INT)'), [1142]);
        $reject('admin_update_denied', fn() => $pdo->exec("UPDATE admins SET username='hijacked' WHERE id=$adminId"), [1142]);
        $reject('admin_delete_denied', fn() => $pdo->exec('DELETE FROM admins'), [1142]);
        $reject('orders_update_denied', fn() => $pdo->exec("UPDATE orders SET delivery_location='Hijacked' WHERE id=1"), [1142]);
        $reject('orders_delete_denied', fn() => $pdo->exec('DELETE FROM orders WHERE id=1'), [1142]);
        $reject('order_items_update_denied', fn() => $pdo->exec('UPDATE order_items SET quantity=1 WHERE id=1'), [1142]);
        $reject('order_items_delete_denied', fn() => $pdo->exec('DELETE FROM order_items WHERE id=1'), [1142]);
        $reject('global_account_management_denied', fn() => $pdo->exec("CREATE USER 'tbforbidden_$suffix'@'localhost'"), [1227]);
        $check('seed_unchanged_by_fixture_crud', $before === $pdo->query('SELECT * FROM food_items ORDER BY id')->fetchAll());
        $report['result'] = 'PASS';
    } catch (Throwable $e) {
        $report['error'] = $e instanceof PDOException ? 'SQLSTATE ' . $e->getCode() . '; driver code ' . ($e->errorInfo[1] ?? 'unknown')
            : (($e instanceof RuntimeException || $e instanceof InvalidArgumentException) ? $e->getMessage() : 'Unexpected test runtime error.');
    } finally {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        unset($category, $insert, $select, $update, $delete, $orderInsert, $itemInsert);
        $pdo = null;
        $report['cleanup'] = $root ? tb_cleanup($root, $database, $username, $owned, $owned) : ['database'=>'NOT_CREATED','account'=>'NOT_CREATED'];
        if ($otherOwned) $report['cleanup']['other_database'] = tb_cleanup($root, $other, $username, true, false)['database'];
        $report['cleanup']['config_file'] = !$configOwned || (unlink($configPath) && !file_exists($configPath)) ? 'REMOVED_OR_NOT_CREATED' : 'CLEANUP_FAILED';
        putenv($oldConfig === false ? 'TIDEBITES_CONFIG' : 'TIDEBITES_CONFIG=' . $oldConfig);
        if (in_array('CLEANUP_FAILED', $report['cleanup'], true)) $report['result'] = 'FAIL';
    }
    return $report;
}

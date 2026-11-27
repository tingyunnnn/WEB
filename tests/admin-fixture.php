<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
require dirname(__DIR__) . '/database/tools.php';
try {
    $input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
    $action = $argv[1] ?? '';
    if ($action === 'baseline') {
        $pdo = tidebites_pdo();
        $result = [];
        foreach (['food_items', 'admins', 'orders', 'order_items'] as $table) {
            $rows = $pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll();
            $result[$table] = ['count' => count($rows), 'digest' => hash('sha256', json_encode($rows))];
        }
    } elseif ($action === 'shutdown') {
        if (($input['port'] ?? 3306) === 3306) throw new RuntimeException();
        tb_admin_connection($input)->exec('SHUTDOWN');
        $result = ['result' => 'PASS'];
    } elseif ($action === 'setup') {
        $root = tb_admin_connection($input);
        if (!preg_match('/\Atbadmin[0-9a-f]+\z/', $input['database'])) throw new RuntimeException();
        tb_provision($root, $input['database'], $input['app_user'], $input['app_password']);
        $config = ['host' => '127.0.0.1', 'port' => $input['port'], 'database' => $input['database'], 'username' => $input['app_user'], 'password' => $input['app_password']];
        tb_write_config($input['config'], $config);
        $pdo = tidebites_connect($config);
        tb_create_admin($pdo, 'qa_admin', $input['admin_password']);
        $result = ['result' => 'PASS'];
    } else {
        $pdo = tidebites_pdo();
        $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!preg_match('/\Atbadmin[0-9a-f]+\z/', $database)) throw new RuntimeException();
        if ($action === 'snapshot') {
            $result = ['foods' => $pdo->query('SELECT * FROM food_items ORDER BY id')->fetchAll(),
                'orders' => $pdo->query('SELECT * FROM orders ORDER BY id')->fetchAll(),
                'items' => $pdo->query('SELECT * FROM order_items ORDER BY id')->fetchAll()];
        } elseif ($action === 'order') {
            $query = $pdo->prepare('SELECT name,price FROM food_items WHERE id=?');
            $query->execute([$input['id']]); $food = $query->fetch();
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO orders (delivery_location,subtotal,discount_amount,total_amount) VALUES (?,?,0,?)')->execute(['QA location', $food['price'], $food['price']]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO order_items (order_id,food_item_id,food_name,unit_price,quantity) VALUES (?,?,?,?,1)')->execute([$id, $input['id'], $food['name'], $food['price']]);
            $pdo->commit(); $result = ['order_id' => $id];
        } elseif ($action === 'shared-image') {
            $pdo->prepare('UPDATE food_items SET image_path=? WHERE id=?')->execute([$input['path'], $input['id']]);
            $result = ['result' => 'PASS'];
        } elseif ($action === 'fault') {
            $root = tb_admin_connection($input);
            $root->exec('USE `' . tb_database_name($database) . '`');
            if ($input['enabled']) {
                $event = ($input['kind'] ?? '') === 'insert' ? 'INSERT' : 'UPDATE';
                $root->exec("CREATE TRIGGER qa_reject_save BEFORE $event ON food_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA forced failure'");
            }
            else $root->exec('DROP TRIGGER qa_reject_save');
            $result = ['result' => 'PASS'];
        } elseif ($action === 'session') {
            if (!preg_match('/\A[a-zA-Z0-9,-]+\z/', $input['id'])) throw new RuntimeException();
            session_save_path($input['runtime'] . '/sessions');
            session_name('TIDEBITES_ADMIN'); session_id($input['id']); session_start();
            if ($input['mode'] === 'expired') $_SESSION['last_seen'] = time() - 1900;
            elseif ($input['mode'] === 'invalid') $_SESSION['admin_id'] = 4294967295;
            elseif ($input['mode'] === 'absolute') $_SESSION['signed_in'] = time() - 29000;
            session_write_close(); $result = ['result' => 'PASS'];
        } elseif ($action === 'cleanup-check') {
            define('TB_ADMIN_ENTRY', true);
            require dirname(__DIR__) . '/admin/includes/upload.php';
            $result = ['result' => admin_cleanup_image($pdo, $input['path'])];
        } elseif ($action === 'orders-fixture') {
            $root = tb_admin_connection($input);
            $root->exec('DELETE FROM `' . tb_database_name($database) . '`.orders');
            $ids = [];
            for ($i = 0; $i < (int)$input['count']; $i++) {
                $pdo->prepare('INSERT INTO food_items (name,description,price,category,image_path,image_alt) VALUES (?,?,?,?,?,?)')->execute(['Ocean Bowl (ordered)', 'Fixture only', '129.50', 'meals', 'assets/images/icon.png', 'Fixture']);
                $foodId = (int)$pdo->lastInsertId();
                $name = $i === 0 ? null : (($input['attack'] ?? false) && $i === 1 ? '<script>alert(1)</script>' : 'Jamie Chen ' . $i);
                $pdo->prepare('INSERT INTO orders (customer_name,phone,delivery_location,notes,subtotal,discount_amount,total_amount,created_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$name, $i === 0 ? null : '0912-345-678', 'Library Entrance', $i === 0 ? null : 'Please meet near the east doors.', '358.00', '36.00', '322.00', '2026-11-26 00:30:00']);
                $orderId = (int)$pdo->lastInsertId(); $ids[] = $orderId;
                $pdo->prepare('INSERT INTO order_items (order_id,food_item_id,food_name,unit_price,quantity) VALUES (?,?,?,?,?)')->execute([$orderId, $foodId, 'Ocean Bowl (ordered)', '129.50', 2]);
                $pdo->prepare('INSERT INTO order_items (order_id,food_item_id,food_name,unit_price,quantity) VALUES (?,NULL,?,?,?)')->execute([$orderId, 'Mango Snow Ice', '99.00', 1]);
                $pdo->prepare('UPDATE food_items SET name=?,price=? WHERE id=?')->execute(['Changed current menu name', '999.00', $foodId]);
                $pdo->prepare('DELETE FROM food_items WHERE id=?')->execute([$foodId]);
            }
            $result = ['ids' => $ids];
        } elseif ($action === 'reset-menu') {
            $pdo->exec('DELETE FROM food_items');
            $root = tb_admin_connection($input);
            tb_import($root, $database);
            $result = ['result' => 'PASS'];
        } elseif ($action === 'teardown') {
            $root = tb_admin_connection($input);
            $result = tb_cleanup($root, $database, $input['app_user'], true, true);
        } else throw new RuntimeException();
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (Throwable) { fwrite(STDERR, 'Fixture operation failed.'); exit(1); }

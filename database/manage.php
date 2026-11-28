<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require_once __DIR__ . '/tools.php';

$result = ['result' => 'FAIL'];
try {
    // PowerShell/macOS/Linux wrappers pipe UTF-8 JSON; no credentials in arguments.
    // Windows PowerShell may prepend a UTF-8 BOM to piped stdin; strip it before decoding.
    $stdin = (string) stream_get_contents(STDIN);
    if (str_starts_with($stdin, "\xEF\xBB\xBF")) $stdin = substr($stdin, 3);
    $input = json_decode(trim($stdin) ?: '{}', true, 32, JSON_THROW_ON_ERROR);
    unset($stdin);
    $action = strtolower($argv[1] ?? '');
    if ($action === 'check') {
        $pdo = tidebites_pdo();
        $result = ['result' => 'PASS', 'connection' => $pdo->query('SELECT VERSION() AS version, DATABASE() AS db, CURRENT_USER() AS account')->fetch(),
            'food_count' => (int)$pdo->query('SELECT COUNT(*) FROM food_items')->fetchColumn()];
    } elseif ($action === 'createadmin') {
        $id = tb_create_admin(tidebites_pdo(), (string)($input['admin_username'] ?? ''), (string)($input['admin_password'] ?? ''));
        $result = ['result' => 'PASS', 'admin_id' => $id, 'message' => 'Admin hash created. Login/access control belongs to PR 2.'];
    } elseif ($action === 'install') {
        $database = tb_database_name((string)($input['database'] ?? 'tidebites'));
        $username = tb_account_name((string)($input['app_user'] ?? 'tidebites_app'));
        $configPath = tidebites_external_config_path(tidebites_config_path(), true);
        if (file_exists($configPath)) throw new RuntimeException('Local configuration already exists. Inspect it before installing; nothing was changed.');
        $root = tb_admin_connection($input);
        tb_provision($root, $database, $username, (string)($input['app_password'] ?? ''));
        $config = ['host' => $input['host'] ?? '127.0.0.1', 'port' => $input['port'] ?? 3306,
            'database' => $database, 'username' => $username, 'password' => $input['app_password']];
        $createdDirs = [];
        try {
            $pdo = tidebites_connect($config);
            $count = (int)$pdo->query('SELECT COUNT(*) FROM food_items')->fetchColumn();
            $createdDirs = tb_ensure_config_dir(dirname($configPath));
            $configPath = tidebites_external_config_path($configPath);
            tb_write_config($configPath, $config);
        } catch (Throwable $e) {
            $pdo = null;
            tb_remove_created_dirs($createdDirs);
            $cleanup = tb_cleanup($root, $database, $username, true, true);
            $result['cleanup'] = $cleanup;
            throw $e;
        }
        $result = ['result' => 'PASS', 'database' => $database, 'account' => $username . '@localhost',
            'food_count' => $count, 'configuration' => $configPath, 'message' => 'Fresh installation complete.'];
    } elseif ($action === 'migrate') {
        $database = tb_database_name((string)($input['database'] ?? 'tidebites'));
        $result = ['result' => 'PASS', 'database' => $database, 'changes' => tb_migrate(tb_admin_connection($input), $database)];
    } elseif ($action === 'test') {
        require_once __DIR__ . '/tests.php';
        $result = tb_run_tests($input);
    } else {
        throw new InvalidArgumentException('Use install, check, createadmin, migrate, or test. See docs/database-setup.md.');
    }
} catch (PDOException $e) {
    $result['error'] = 'Database operation failed (SQLSTATE ' . $e->getCode() . '). No credentials or SQL are logged.';
} catch (Throwable $e) {
    // Only our curated errors are exposed; parser/type/runtime internals are not.
    $result['error'] = ($e instanceof RuntimeException || $e instanceof InvalidArgumentException) ? $e->getMessage() : 'Invalid input or local runtime error.';
}
unset($input, $config, $root, $pdo);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($result['result'] === 'PASS' ? 0 : 1);

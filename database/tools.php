<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/database.php';

function tb_database_name(string $name): string
{
    // No '_'/'%' SQL grant wildcards; keep database-level privileges exact.
    if (!preg_match('/\A[a-z][a-z0-9]{0,63}\z/', $name)) {
        throw new InvalidArgumentException('Database name must be 1-64 lowercase letters/digits, starting with a letter.');
    }
    return $name;
}

function tb_account_name(string $name): string
{
    if (!preg_match('/\A[a-z][a-z0-9_]{0,31}\z/', $name) || $name === 'root') {
        throw new InvalidArgumentException('Choose a non-root account name (1-32 lowercase letters, digits, underscores).');
    }
    return $name;
}

function tb_admin_connection(#[SensitiveParameter] array $input): PDO
{
    $host = $input['host'] ?? '127.0.0.1';
    $port = $input['port'] ?? 3306;
    if (!is_string($host) || !preg_match('/\A[a-zA-Z0-9.:-]+\z/', $host)
        || !is_int($port) || $port < 1 || $port > 65535
        || !is_string($input['root_password'] ?? null) || $input['root_password'] === ''
        || str_contains($input['root_password'], "\0")) {
        throw new InvalidArgumentException('Host, port and a locally entered root password are required.');
    }
    try {
        return new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $input['root_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5,
            tidebites_mysql_multi_statements_attr() => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('Administrative connection failed. SQLSTATE ' . $e->getCode() . '.');
    }
}

function tb_import(PDO $root, string $database): void
{
    tb_database_name($database);
    $sql = file_get_contents(__DIR__ . '/tidebites.sql');
    if ($sql === false) throw new RuntimeException('Cannot read initialization SQL.');
    $sql = str_replace('`tidebites`', '`' . $database . '`', $sql);
    foreach (preg_split('/^-- statement\s*$/m', $sql) as $statement) {
        if (trim($statement) !== '') $root->exec(trim($statement));
    }
}

function tb_cleanup(PDO $root, string $database, string $username, bool $databaseOwned, bool $accountOwned): array
{
    $result = [];
    foreach (['database' => $databaseOwned, 'account' => $accountOwned] as $kind => $owned) {
        if (!$owned) { $result[$kind] = 'NOT_CREATED'; continue; }
        try {
            if ($kind === 'database') {
                $root->exec('DROP DATABASE `' . tb_database_name($database) . '`');
                $q = $root->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
                $q->execute([$database]);
            } else {
                $root->exec('DROP USER ' . $root->quote(tb_account_name($username)) . "@'localhost'");
                $q = $root->prepare("SELECT COUNT(*) FROM mysql.user WHERE User = ? AND Host = 'localhost'");
                $q->execute([$username]);
            }
            if ((int)$q->fetchColumn() !== 0) throw new RuntimeException();
            $result[$kind] = 'REMOVED_AND_VERIFIED';
        } catch (Throwable) { $result[$kind] = 'CLEANUP_FAILED'; }
    }
    return $result;
}

function tb_provision(PDO $root, string $database, string $username, #[SensitiveParameter] string $password): void
{
    tb_database_name($database);
    tb_account_name($username);
    if (strlen($password) < 16 || str_contains($password, "\0")) {
        throw new InvalidArgumentException('Use an application password of at least 16 bytes with no NUL bytes.');
    }
    $q = $root->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
    $q->execute([$database]);
    if ((int)$q->fetchColumn() !== 0) throw new RuntimeException('Database already exists. Inspect its owner/purpose; nothing was changed.');
    $q = $root->prepare('SELECT COUNT(*) FROM mysql.user WHERE User = ?');
    $q->execute([$username]);
    if ((int)$q->fetchColumn() !== 0) throw new RuntimeException('Account already exists. Inspect its owner/purpose; no password was changed.');
    $databaseOwned = $accountOwned = false;
    try {
        // CREATE without IF NOT EXISTS establishes ownership even if another setup races us.
        $root->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $databaseOwned = true;
        tb_import($root, $database);
        $account = $root->quote($username) . "@'localhost'";
        $root->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $root->quote($password));
        $accountOwned = true;
        foreach (['food_items' => 'SELECT, INSERT, UPDATE, DELETE', 'admins' => 'SELECT, INSERT',
                  'orders' => 'SELECT, INSERT', 'order_items' => 'SELECT, INSERT'] as $table => $privileges) {
            $root->exec("GRANT $privileges ON `$database`.`$table` TO $account");
        }
    } catch (Throwable $e) {
        $cleanup = tb_cleanup($root, $database, $username, $databaseOwned, $accountOwned);
        if (in_array('CLEANUP_FAILED', $cleanup, true)) {
            throw new RuntimeException("Setup failed; inspect temporary resources $database / $username before retrying.");
        }
        throw $e;
    }
}

function tb_ensure_config_dir(string $directory): array
{
    if (is_dir($directory)) return [];
    if (file_exists($directory)) throw new RuntimeException('Cannot create external configuration directory.');
    $missing = [];
    $current = $directory;
    while (!is_dir($current)) {
        if (file_exists($current)) throw new RuntimeException('Cannot create external configuration directory.');
        $parent = dirname($current);
        if ($parent === $current) throw new RuntimeException('Cannot create external configuration directory.');
        array_unshift($missing, $current);
        $current = $parent;
    }
    $created = [];
    try {
        foreach ($missing as $dir) {
            if (@mkdir($dir, 0700, false)) {
                $created[] = $dir;
            } elseif (!is_dir($dir)) {
                throw new RuntimeException('Cannot create external configuration directory.');
            }
        }
    } catch (Throwable $e) {
        tb_remove_created_dirs($created);
        throw $e;
    }
    return $created;
}

function tb_remove_created_dirs(array $createdDirs): void
{
    foreach (array_reverse($createdDirs) as $dir) {
        if (is_dir($dir)) @rmdir($dir);
    }
}

function tb_write_config(string $path, #[SensitiveParameter] array $config): void
{
    $path = tidebites_external_config_path($path);
    $source = "<?php\ndeclare(strict_types=1);\nif (PHP_SAPI !== 'cli' && realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }\nreturn "
        . var_export($config, true) . ";\n";
    $previousMask = umask(0077);
    try {
        $handle = @fopen($path, 'x');
        if ($handle === false) throw new RuntimeException('Configuration file exists or cannot be created; it was not overwritten.');
        try {
            if (fwrite($handle, $source) !== strlen($source)) throw new RuntimeException('Configuration write failed.');
        } catch (Throwable $e) {
            fclose($handle);
            unlink($path);
            throw $e;
        }
        fclose($handle);
    } finally { umask($previousMask); }
}

/** Add columns introduced after the first release to an existing database. Safe to run repeatedly. */
function tb_migrate(PDO $root, string $database): array
{
    tb_database_name($database);
    $q = $root->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $q->execute([$database, 'orders']);
    if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('Database or orders table not found; nothing was changed.');
    $q = $root->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
    $q->execute([$database, 'orders', 'pickup_time']);
    if ((int)$q->fetchColumn() !== 0) return ['orders.pickup_time' => 'ALREADY_PRESENT'];
    $root->exec('ALTER TABLE `' . $database . '`.`orders` ADD COLUMN pickup_time DATETIME NULL AFTER notes, ADD KEY idx_orders_pickup (pickup_time)');
    return ['orders.pickup_time' => 'ADDED'];
}

function tb_create_admin(PDO $pdo, string $username, #[SensitiveParameter] string $password): int
{
    if (!preg_match('/\A[A-Za-z0-9_.-]{3,100}\z/', $username)) throw new InvalidArgumentException('Admin username must be 3-100 ASCII letters/digits/._- characters.');
    if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new InvalidArgumentException('Admin password must be 12-72 bytes with no NUL bytes.');
    }
    $q = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $q->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    return (int)$pdo->lastInsertId();
}

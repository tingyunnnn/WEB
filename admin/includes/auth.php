<?php
declare(strict_types=1);
if (!defined('TB_ADMIN_ENTRY')) { http_response_code(404); exit; }
function admin_start_session(): void
{
    $directory = admin_runtime() . '/sessions';
    if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) throw new RuntimeException();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '1800');
    session_save_path($directory);
    session_name('TIDEBITES_ADMIN');
    session_set_cookie_params(['lifetime' => 0, 'path' => admin_url(),
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        'httponly' => true, 'samesite' => 'Strict']);
    if (!session_start()) throw new RuntimeException();
}
function admin_logout(): void
{
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $cookie['path'],
        'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Strict']);
    session_destroy();
}
function admin_current(): ?array
{
    if (!isset($_SESSION['admin_id'])) return null;
    if (!is_int($_SESSION['admin_id']) || !is_int($_SESSION['signed_in'] ?? null)
        || !is_int($_SESSION['last_seen'] ?? null) || time() - $_SESSION['last_seen'] > 1800
        || time() - $_SESSION['signed_in'] > 28800) {
        admin_logout();
        return null;
    }
    $query = tidebites_pdo()->prepare('SELECT id,username,password_hash FROM admins WHERE id=?');
    $query->execute([$_SESSION['admin_id']]);
    $admin = $query->fetch();
    if (!$admin || !hash_equals(hash('sha256', $admin['password_hash']), (string)($_SESSION['credential'] ?? ''))) {
        admin_logout();
        return null;
    }
    $_SESSION['last_seen'] = time();
    return ['id' => $admin['id'], 'username' => $admin['username']];
}
function admin_require(): array
{
    $admin = admin_current();
    if ($admin) return $admin;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') admin_redirect('login.php');
    admin_fail(401, 'Please sign in before making changes.');
}

/** Reserve an attempt atomically, independent of cookies. Never trust forwarded IP headers. */
function admin_login_attempt(string $username): bool
{
    $handle = @fopen(admin_runtime() . '/login-attempts.json', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException();
    try {
        $contents = stream_get_contents($handle);
        $records = $contents === '' ? [] : json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($records)) throw new RuntimeException();
        $now = time();
        $records = array_filter($records, static fn(array $r): bool => $r['until'] > $now);
        $keys = [hash('sha256', 'user:' . strtolower($username)) => 5,
            hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) => 30];
        $allowed = count($records) < 10000;
        foreach ($keys as $key => $limit) if (($records[$key]['count'] ?? 0) >= $limit) $allowed = false;
        if ($allowed) foreach ($keys as $key => $limit) {
            $records[$key] ??= ['until' => $now + 900, 'count' => 0];
            $records[$key]['count']++;
        }
        $json = json_encode($records, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException();
        return $allowed;
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}
function admin_login(string $username, #[SensitiveParameter] string $password): bool
{
    $query = tidebites_pdo()->prepare('SELECT id,password_hash FROM admins WHERE username=?');
    $query->execute([$username]);
    $admin = $query->fetch();
    // A valid bcrypt hash keeps absent users on the same password verification path.
    $hash = $admin['password_hash'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $verified = password_verify($password, $hash);
    if (!$admin || !$verified) return false;
    $_SESSION = [];
    if (!session_regenerate_id(true)) throw new RuntimeException();
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['credential'] = hash('sha256', $admin['password_hash']);
    $_SESSION['signed_in'] = $_SESSION['last_seen'] = time();
    admin_csrf();
    return true;
}

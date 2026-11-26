<?php
declare(strict_types=1);
if (!defined('TB_ADMIN_ENTRY')) { http_response_code(404); exit; }
const ADMIN_IMAGE_MAX_BYTES = 2097152;
function admin_image_complete(string $file, string $mime): bool
{
    $data = file_get_contents($file);
    if ($data === false) return false;
    if ($mime === 'image/jpeg') return str_starts_with($data, "\xff\xd8") && str_ends_with($data, "\xff\xd9");
    if ($mime === 'image/webp') return strlen($data) >= 20 && substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP' && unpack('Vsize', substr($data, 4, 4))['size'] + 8 === strlen($data);
    if ($mime !== 'image/png' || substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;
    $offset = 8; $imageData = false;
    while ($offset + 12 <= strlen($data)) {
        $length = unpack('Nsize', substr($data, $offset, 4))['size'];
        if ($length > strlen($data) - $offset - 12) return false;
        $type = substr($data, $offset + 4, 4);
        if (hash('crc32b', substr($data, $offset + 4, $length + 4), true) !== substr($data, $offset + 8 + $length, 4)) return false;
        if ($type === 'IDAT') $imageData = true;
        $offset += $length + 12;
        if ($type === 'IEND') return $length === 0 && $offset === strlen($data) && $imageData;
    }
    return false;
}
function admin_upload(?array $file, bool $required): ?string
{
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
        if ($required) throw new InvalidArgumentException('Choose a food image.');
        return null;
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('The image could not be uploaded. Choose a file no larger than 2 MiB and try again.');
    $name = $file['full_path'] ?? $file['name'] ?? '';
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($name) || !is_string($tmp) || preg_match('~[\\\\/:\x00-\x1F]~', $name) || str_contains($name, '..') || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Choose a valid local image file.');
    $size = filesize($tmp);
    if ($size === false || $size < 1 || $size > ADMIN_IMAGE_MAX_BYTES) throw new InvalidArgumentException('Images must be no larger than 2 MiB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $info = @getimagesize($tmp);
    if (!isset($allowed[$mime]) || !in_array($extension, $allowed[$mime], true) || !$info || ($info['mime'] ?? '') !== $mime || !admin_image_complete($tmp, $mime)) throw new InvalidArgumentException('Choose a valid JPG, PNG or WebP image with a matching file extension.');
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 16000000) throw new InvalidArgumentException('Images must be at most 6,000 pixels per side and 16 megapixels.');
    $directory = dirname(__DIR__, 2) . '/uploads/foods';
    if (is_link($directory) || !is_dir($directory) || !is_writable($directory)) throw new RuntimeException('Image storage unavailable.');
    $filename = 'tb-' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime][0];
    $destination = $directory . '/' . $filename;
    // Exclusive creation prevents overwriting an existing resource, even on a collision.
    $reservation = @fopen($destination, 'x');
    if (!$reservation) throw new RuntimeException('Image storage unavailable.');
    fclose($reservation);
    if (!@move_uploaded_file($tmp, $destination)) {
        @unlink($destination);
        throw new RuntimeException('Image storage unavailable.');
    }
    @chmod($destination, 0644);
    return 'uploads/foods/' . $filename;
}
/** Fail closed: only generated, unreferenced files inside our exact upload directory. */
function admin_cleanup_image(PDO $pdo, ?string $path): bool
{
    if (!$path || !preg_match('~\Auploads/foods/tb-[a-f0-9]{32}\.(?:jpg|png|webp)\z~', $path)) return true;
    try {
        $query = $pdo->prepare('SELECT COUNT(*) FROM food_items WHERE image_path=?');
        $query->execute([$path]);
        if ((int)$query->fetchColumn() !== 0) return true;
        $directory = dirname(__DIR__, 2) . '/uploads/foods';
        $file = dirname(__DIR__, 2) . '/' . $path;
        if (is_link($directory) || is_link($file)) return false;
        if (!file_exists($file)) return true;
        if (realpath(dirname($file)) !== realpath($directory)) return false;
        return @unlink($file);
    } catch (Throwable) { return false; } // Keep the file if references cannot be verified.
}

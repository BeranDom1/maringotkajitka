<?php

require __DIR__ . '/config.php';

session_start();

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Neplatny bezpecnostni token.');
    }
}

function read_gallery(): array
{
    if (!is_file(GALLERY_DATA_FILE)) {
        return [];
    }

    $data = json_decode((string) file_get_contents(GALLERY_DATA_FILE), true);
    return is_array($data) ? $data : [];
}

function write_gallery(array $items): void
{
    $dir = dirname(GALLERY_DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents(
        GALLERY_DATA_FILE,
        json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function make_upload_name(string $extension): string
{
    return date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
}

function starts_with(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function is_local_gallery_image(string $image): bool
{
    return starts_with($image, GALLERY_UPLOAD_URL . '/');
}

function delete_local_image(string $image): void
{
    if (!is_local_gallery_image($image)) {
        return;
    }

    $file = realpath(__DIR__ . '/../' . $image);
    $uploadDir = realpath(GALLERY_UPLOAD_DIR);

    if ($file && $uploadDir && starts_with($file, $uploadDir) && is_file($file)) {
        unlink($file);
    }
}

function image_src_for_admin(string $image): string
{
    if (starts_with($image, 'http://') || starts_with($image, 'https://')) {
        return $image;
    }

    return '../' . $image;
}

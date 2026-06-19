<?php

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

if (!defined('ADMIN_PASSWORD')) {
    define('ADMIN_PASSWORD', getenv('MARINGOTKA_ADMIN_PASSWORD') ?: '');
}

const GALLERY_DATA_FILE = __DIR__ . '/../data/gallery.json';
const GALLERY_UPLOAD_DIR = __DIR__ . '/../uploads/gallery';
const GALLERY_UPLOAD_URL = 'uploads/gallery';
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
const ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

<?php
/**
 * Router untuk PHP built-in server (development lokal).
 *
 * Jalankan dari folder proyek:
 *   php -S localhost:8000 router.php
 *
 * Hanya aset statis di public/ dan node_modules/ yang dilayani langsung.
 * Semua route aplikasi diteruskan ke index.php, dan file sensitif
 * (storage, .git, upload, dsb.) diblokir.
 */

$root = realpath(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = str_replace('\\', '/', $path);

// Block sensitive paths and direct access to uploaded files.
$blockedPaths = '#^/(app|vendor|logs|storage|tmp|src|scripts|\.git)(/|$)#i';
$blockedFiles = '#^/(composer\.(json|lock)|package(-lock)?\.json|README\.md|LICENSE|CHANGELOG\.md|DEPLOYMENT\.md|setup-verify\.(ps1|sh)|tailwind\.config\.js|\.gitignore|\.htaccess)$#i';

if (
    preg_match($blockedPaths, $path)
    || preg_match($blockedFiles, $path)
    || preg_match('#^/public/uploads(/|$)#i', $path)
) {
    http_response_code(404);
    exit;
}

// Serve real static assets from public/ and node_modules/ only.
$staticRoots = [
    $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR,
    $root . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
];
$candidate = realpath($root . $path);

if ($candidate !== false && is_file($candidate)) {
    foreach ($staticRoots as $staticRoot) {
        if (str_starts_with($candidate, $staticRoot)) {
            return false;
        }
    }
}

chdir($root);
require $root . '/index.php';

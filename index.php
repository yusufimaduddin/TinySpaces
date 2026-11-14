<?php
// Debug is off by default. Enable it only for local development:
//   PowerShell: $env:APP_DEBUG=1; php -S localhost:8000 router.php
$appDebug = getenv('APP_DEBUG');
$appDebug = ($appDebug === false || $appDebug === '') ? 0 : (int) $appDebug;

error_reporting(E_ALL);
ini_set('display_errors', $appDebug > 0 ? '1' : '0');
ini_set('display_startup_errors', $appDebug > 0 ? '1' : '0');

// Load dependencies
require 'vendor/autoload.php';
require 'app/database.php';

$f3 = Base::instance();

// Konfigurasi
$f3->set('AUTOLOAD', 'app/controllers/;app/models/');
$f3->set('UI', 'app/views/');
$f3->set('DEBUG', $appDebug);
$f3->set('ONERROR', function ($f3) {
    // Clear any output that has already been generated
    while (ob_get_level())
        ob_end_clean();

    // API/AJAX requests must receive JSON, never an HTML error page
    if (class_exists('AuthController') && AuthController::isApiRequest()) {
        $status = (int) ($f3->get('ERROR.code') ?: 500);
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $f3->get('DEBUG') > 0
                ? ($f3->get('ERROR.text') ?: 'Something went wrong.')
                : 'Something went wrong. Please try again later.',
            'code' => $status
        ]);
        return;
    }

    // Render the error page
    echo Template::instance()->render('error.html');
});

// Secure session cookie settings (must be set before session_start)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Routes
$f3->route('GET /', function ($f3) {
    if ($f3->get('SESSION.logged_in')) {
        $role = $f3->get('SESSION.role');
        $f3->reroute($role === 'admin' ? '/admin/dashboard' : '/user/dashboard');
    } else {
        $f3->reroute('/login');
    }
});

// Auth routes
$f3->route('GET /login', 'AuthController->loginPage');
$f3->route('POST /login', 'AuthController->loginProcess');
$f3->route('GET /logout', 'AuthController->logout');

// Admin routes
$f3->route('GET /admin/dashboard', 'AdminController->dashboard');
$f3->route('POST /admin/users', 'AdminController->addUser');

// User routes
$f3->route('GET /user/dashboard', 'UserController->dashboard');
$f3->route('GET /user/profile', 'UserController->profile');
$f3->route('GET /user/space/@id', 'UserController->space');
$f3->route('GET /user/editor/@space_id/@file_id', 'UserController->editor');
$f3->route('GET /user/review/@id', 'ReviewController->review');

// User API routes
$f3->route('POST /api/profile/update', 'UserController->updateProfile');
$f3->route('POST /api/password/update', 'UserController->updatePassword');
$f3->route('POST /api/account/delete', 'UserController->deleteAccount');

// Space API routes
$f3->route('GET /api/spaces/recent', 'SpaceController->getRecentlyModified');
$f3->route('GET /api/spaces', 'SpaceController->listSpaces');
$f3->route('POST /api/spaces', 'SpaceController->createSpace');
$f3->route('GET /api/spaces/@id', 'SpaceController->getSpace');
$f3->route('PUT /api/spaces/@id', 'SpaceController->updateSpace');
$f3->route('DELETE /api/spaces/@id', 'SpaceController->deleteSpace');

// File API routes
$f3->route('POST /api/spaces/@id/upload', 'SpaceController->uploadFile');
$f3->route('GET /api/spaces/@space_id/files/@file_id/download', 'SpaceController->downloadFile');
$f3->route('GET /api/spaces/@space_id/files/@file_id/view', 'SpaceController->viewFile');
$f3->route('PUT /api/spaces/@space_id/files/@file_id', 'SpaceController->updateFile');
$f3->route('DELETE /api/spaces/@space_id/files/@file_id', 'SpaceController->deleteFile');
$f3->route('POST /api/spaces/@id/files/merge', 'SpaceController->mergeFileGroups');
$f3->route('POST /api/spaces/@id/groups/release', 'SpaceController->releaseGroup');
$f3->route('DELETE /api/spaces/@id/groups', 'SpaceController->deleteGroup');
$f3->route('GET /api/spaces/@id/groups/download', 'SpaceController->downloadGroup');

// Tag and sharing API routes
$f3->route('POST /api/spaces/@id/tags', 'SpaceController->manageTags');
$f3->route('POST /api/spaces/@id/share', 'SpaceController->shareSpace');
$f3->route('POST /api/spaces/@id/review-mode', 'SpaceController->toggleReviewMode');
$f3->route('PUT /api/spaces/@id/readme', 'SpaceController->updateReadme');

// Run app
$f3->run();
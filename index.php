<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load dependencies
require 'vendor/autoload.php';
require 'app/database.php';

$f3 = Base::instance();

// Konfigurasi
$f3->set('AUTOLOAD', 'app/controllers/;app/models/');
$f3->set('UI', 'app/views/');
$f3->set('DEBUG', 3);
$f3->set('ONERROR', function ($f3) {
    // Clear any output that has already been generated
    while (ob_get_level())
        ob_end_clean();
    // Render the error page
    echo Template::instance()->render('error.html');
});

// Start session
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

// User API routes
$f3->route('POST /api/profile/update', 'UserController->updateProfile');
$f3->route('POST /api/password/update', 'UserController->updatePassword');
$f3->route('POST /api/account/delete', 'UserController->deleteAccount');

// Space API routes
$f3->route('GET /api/spaces', 'SpaceController->listSpaces');
$f3->route('POST /api/spaces', 'SpaceController->createSpace');
$f3->route('GET /api/spaces/@id', 'SpaceController->getSpace');
$f3->route('PUT /api/spaces/@id', 'SpaceController->updateSpace');
$f3->route('DELETE /api/spaces/@id', 'SpaceController->deleteSpace');

// File API routes
$f3->route('POST /api/spaces/@id/upload', 'SpaceController->uploadFile');
$f3->route('GET /api/spaces/@space_id/files/@file_id/download', 'SpaceController->downloadFile');
$f3->route('GET /api/spaces/@space_id/files/@file_id/view', 'SpaceController->viewFile');
$f3->route('DELETE /api/spaces/@space_id/files/@file_id', 'SpaceController->deleteFile');

// Tag and sharing API routes
$f3->route('POST /api/spaces/@id/tags', 'SpaceController->manageTags');
$f3->route('POST /api/spaces/@id/share', 'SpaceController->shareSpace');
$f3->route('PUT /api/spaces/@id/readme', 'SpaceController->updateReadme');

// Run app
$f3->run();
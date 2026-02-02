<?php
// Main Entry Point
require_once __DIR__ . '/config/database.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $path_parts = explode('\\', $relative_class);

    // 1. Try exact match (Standard PSR-4)
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    // 2. Try with lowercase first directory (e.g. Middleware -> middleware)
    // This helps if the directory on Linux is "middleware" but namespace is "Middleware"
    if (!empty($path_parts)) {
        $path_parts[0] = strtolower($path_parts[0]);
        $file_lower = $base_dir . implode('/', $path_parts) . '.php';
        if (file_exists($file_lower)) {
            require $file_lower;
            return;
        }
    }
});

use App\Core\Router;

// Determine Base Path
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$basePath = $scriptDir === '/' || $scriptDir === '\\' ? '' : $scriptDir;
// Normalize backslashes to slashes if on Windows
$basePath = str_replace('\\', '/', $basePath);
define('BASE_PATH', $basePath);

$router = new Router();

// Define Routes
$router->add('GET', '/', 'HomeController', 'index');
$router->add('GET', '/login', 'AuthController', 'login');
$router->add('POST', '/login', 'AuthController', 'login');
$router->add('GET', '/logout', 'AuthController', 'logout');

// API Routes
$router->add('GET', '/api/latest', 'ApiController', 'latest');
$router->add('POST', '/api/fetch', 'ApiController', 'fetch');
$router->add('GET', '/api/kundlista', 'PhoneListController', 'api');

// View Routes
$router->add('GET', '/kundlista', 'PhoneListController', 'index');
$router->add('GET', '/users', 'UserController', 'index');
$router->add('POST', '/users', 'UserController', 'index');
$router->add('GET', '/phone_list', 'AdminPhoneListController', 'index');
$router->add('POST', '/phone_list', 'AdminPhoneListController', 'index');

// Dispatch
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Handle potential query strings in URI (Router dispatch handles it inside)
$router->dispatch($uri, $method);
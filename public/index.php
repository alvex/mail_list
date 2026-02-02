<?php
// Public entry point

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Router;

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

// Dispatch
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Handle potential query strings in URI (Router dispatch handles it inside, but let's pass it raw)
$router->dispatch($uri, $method);

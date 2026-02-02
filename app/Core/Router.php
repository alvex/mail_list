<?php
namespace App\Core;

/**
 * Enkel router för MVC-applikationen.
 *
 * Matchar inkommande HTTP-metod + path mot registrerade routes och
 * anropar motsvarande controller/metod.
 */
class Router
{
    /**
     * @var array Lista av registrerade routes.
     */
    protected $routes = [];

    /**
     * Registrera en ny route.
     *
     * @param string $method     HTTP-metod (t.ex. 'GET' eller 'POST').
     * @param string $path       Sökväg (t.ex. '/api/latest').
     * @param string $controller Controller-klassnamn utan namespace.
     * @param string $action     Metodnamn på controllern.
     *
     * @return void
     */
    public function add($method, $path, $controller, $action)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }

    /**
     * Försök matcha en inkommande request mot registrerade routes.
     *
     * @param string $uri    Rå URI från servern (t.ex. $_SERVER['REQUEST_URI']).
     * @param string $method HTTP-metod (t.ex. $_SERVER['REQUEST_METHOD']).
     *
     * @return void
     */
    public function dispatch($uri, $method)
    {
        $uri = parse_url($uri, PHP_URL_PATH);

        // Normalisera URI beroende på i vilket underkatalog-sammanhang
        // applikationen körs (Windows/Linux/virtuell host).
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        // Normalisera backslashes -> forward slashes för plattformsoberoende jämförelse.
        $scriptDir = str_replace('\\', '/', $scriptDir);

        // Ta bort trailing slash om det inte är root.
        if ($scriptDir !== '/' && substr($scriptDir, -1) === '/') {
            $scriptDir = rtrim($scriptDir, '/');
        }

        // Om URI:n börjar med script-katalogen, ta bort den delen.
        if ($scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
            $uri = substr($uri, strlen($scriptDir));
        }

        // Ta bort '/index.php' i början om det finns (vanligt i vissa serverkonfigurationer).
        if (strpos($uri, '/index.php') === 0) {
            $uri = substr($uri, 10); // längden på '/index.php'
        }

        if ($uri === '' || $uri === false) {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                $controllerClass = "App\\Controllers\\" . $route['controller'];
                $controller = new $controllerClass();
                $action = $route['action'];
                $controller->$action();
                return;
            }
        }

        // Fallback: om ingen route matchar, returnera enkel 404.
        http_response_code(404);
        echo "404 Not Found: " . htmlspecialchars($uri) . " (Method: $method)";
    }
}

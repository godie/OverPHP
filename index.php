<?php

declare(strict_types=1);

use OverPHP\Core\Benchmark;
use OverPHP\Core\Router;
use OverPHP\Core\Security;
use function OverPHP\Helpers\corsSendHeaders;

// Use Composer autoloader if available, fallback to manual for standalone
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/src/Helpers/cors.php';
    require_once __DIR__ . '/src/Helpers/database.php';

    spl_autoload_register(function (string $class): void {
        $prefix = 'OverPHP\\';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = __DIR__ . '/src/' . $relative . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

$config = file_exists(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

Benchmark::start((bool) ($config['benchmark']['enabled'] ?? false));
Security::sendSecurityHeaders();
Security::setCsrfEnabled((bool) ($config['security']['csrf_enabled'] ?? true));

if (corsSendHeaders($config['allowed_origins'] ?? [])) {
    return;
}

$routePrefix = (string) ($config['route_prefix'] ?? '/api');
$clientConfig = (array) ($config['client'] ?? []);

$router = new Router('OverPHP\\Controllers', $routePrefix, $clientConfig);

// Demo route. Users can replace/add routes here.
$router->add('GET', '/hello', 'HelloController@index');

$router->run();

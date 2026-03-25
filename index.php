<?php

declare(strict_types=1);

use OverPHP\Core\Benchmark;
use OverPHP\Core\Router;
use OverPHP\Core\Security;
use OverPHP\Core\Container;
use OverPHP\Libs\Database;
use function OverPHP\Helpers\corsSendHeaders;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/src/Helpers/cors.php';
    require_once __DIR__ . '/src/Helpers/database.php';

    spl_autoload_register(function (string $class): void {
        $prefix = 'OverPHP\\';
        if (!str_starts_with($class, $prefix)) {
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

$container = Container::getInstance();
$container->singleton(Database::class, function () use ($config) {
    return new Database($config);
});

Benchmark::start((bool) ($config['benchmark']['enabled'] ?? false));
Security::sendSecurityHeaders();
Security::setCsrfEnabled((bool) ($config['security']['csrf_enabled'] ?? true));

if (corsSendHeaders($config['allowed_origins'] ?? [])) {
    return;
}

$routePrefix = (string) ($config['route_prefix'] ?? '/api');
$clientConfig = (array) ($config['client'] ?? []);

$router = new Router('OverPHP\\Controllers', $routePrefix, $container, $clientConfig);

// Demo routes.
$router->add('GET', '/', 'HelloController@index');
$router->add('GET', '/hello', 'HelloController@index');
$router->add('GET', '/hello/{name}', 'HelloController@show');
$router->add('GET', '/raw', 'HelloController@raw');

$router->run();

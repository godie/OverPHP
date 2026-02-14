<?php

declare(strict_types=1);

use OverPHP\Core\Benchmark;
use OverPHP\Core\Router;
use function OverPHP\Helpers\corsSendHeaders;

$config = require __DIR__ . '/config.php';

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

Benchmark::start((bool) ($config['benchmark']['enabled'] ?? false));

if (corsSendHeaders($config['allowed_origins'] ?? [])) {
    exit;
}

$routePrefix = (string) ($config['route_prefix'] ?? '/api');
$router = new Router('OverPHP\\Controllers', $routePrefix);

// Demo route. Users can replace/add routes here.
$router->add('GET', '/hello', 'HelloController@index');

$router->run();

<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testRouterExecutesMatchingClosureRoute(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/ping';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router('OverPHP\\Controllers', '/api');
        $router->add('GET', '/ping', function (): array {
            return ['pong' => true];
        });

        ob_start();
        $router->run();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertIsArray($decoded);
        $this->assertTrue((bool) ($decoded['pong'] ?? false));
    }
}

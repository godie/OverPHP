<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Router;
use PHPUnit\Framework\TestCase;

final class PrefixEnforcementTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    public function testRouteCannotBeAccessedWithoutPrefix(): void
    {
        $router = new Router('OverPHP\\Controllers', '/api');
        $router->add('GET', '/test', fn() => 'ok');

        // Request WITHOUT /api prefix
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';

        ob_start();
        $router->run();
        $output = ob_get_clean();

        // This is expected to FAIL before my changes (it should return 404 but currently returns 'ok')
        $this->assertStringContainsString('Not Found', $output, 'Should return 404 when prefix is missing');
    }

    public function testRouteCanBeAccessedWithPrefix(): void
    {
        $router = new Router('OverPHP\\Controllers', '/api');
        $router->add('GET', '/test', fn() => 'ok');

        // Request WITH /api prefix
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';

        ob_start();
        $router->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('ok', $output, 'Should return ok when prefix is present');
    }
}

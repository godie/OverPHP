<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterFqnFixTest extends TestCase
{
    public function testResolveControllerFqnPreventsRedundantNamespace(): void
    {
        $namespace = 'App\\Controllers';
        $router = new Router($namespace);

        $reflector = new \ReflectionClass($router);
        $method = $reflector->getMethod('resolveControllerFqn');
        $method->setAccessible(true);

        // Case 1: Just the class name
        $this->assertEquals($namespace . '\\UserController', $method->invoke($router, 'UserController'));

        // Case 2: Class name already includes namespace
        $this->assertEquals($namespace . '\\UserController', $method->invoke($router, $namespace . '\\UserController'));

        // Case 3: OverPHP namespace
        $this->assertEquals('OverPHP\\Controllers\\HelloController', $method->invoke($router, 'OverPHP\\Controllers\\HelloController'));

        // Case 4: Absolute namespace
        $this->assertEquals('\\Custom\\Controller', $method->invoke($router, '\\Custom\\Controller'));
    }
}

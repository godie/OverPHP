<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterOptimizationTest extends TestCase
{
    public function testRouterCorrectlyHandlesMultipleDynamicRoutes(): void
    {
        \OverPHP\Core\Benchmark::start(false);
        $router = new Router('OverPHP\\Controllers', '/api');

        $router->add('GET', '/user/{id}', function (string $id): array {
            return ['type' => 'user', 'id' => $id];
        });

        $router->add('GET', '/post/{slug}', function (string $slug): array {
            return ['type' => 'post', 'slug' => $slug];
        });

        $router->add('GET', '/category/{cat}/post/{id}', function (string $cat, string $id): array {
            return ['type' => 'category_post', 'cat' => $cat, 'id' => $id];
        });

        // Test route 1
        $_SERVER['REQUEST_URI'] = '/api/user/42';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        $router->run();
        $output = json_decode((string) ob_get_clean(), true);
        $this->assertEquals(['type' => 'user', 'id' => '42'], $output);

        // Test route 2
        $_SERVER['REQUEST_URI'] = '/api/post/hello-world';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        $router->run();
        $output = json_decode((string) ob_get_clean(), true);
        $this->assertEquals(['type' => 'post', 'slug' => 'hello-world'], $output);

        // Test route 3
        $_SERVER['REQUEST_URI'] = '/api/category/tech/post/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        $router->run();
        $output = json_decode((string) ob_get_clean(), true);
        $this->assertEquals(['type' => 'category_post', 'cat' => 'tech', 'id' => '123'], $output);
    }
}

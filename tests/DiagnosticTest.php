<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Response;
use OverPHP\Core\Router;
use PHPUnit\Framework\TestCase;

final class DiagnosticTest extends TestCase
{
    public function testResponseIsJsonContentTypeChecksHeadersList(): void
    {
        $response = new Response('{"foo":"bar"}', 200, ['Content-Type' => 'application/json']);
        $reflection = new \ReflectionMethod($response, 'isJsonContentType');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($response), 'isJsonContentType should detect JSON from local headers');
    }

    public function testRouterDoesNotRedundantlyPrefixNamespace(): void
    {
        $namespace = 'App\\Controllers';
        $router = new Router($namespace);

        $reflection = new \ReflectionMethod($router, 'resolveControllerFqn');
        $reflection->setAccessible(true);

        $controller = $namespace . '\\MyController';
        $fqn = $reflection->invoke($router, $controller);

        $this->assertEquals($controller, $fqn, 'Should not redundant prefix if already present');
    }

    public function testIsJsonFallbackHandlesScalars(): void
    {
        $response = new Response('');
        $reflection = new \ReflectionMethod($response, 'isJson');
        $reflection->setAccessible(true);

        // We want to test the fallback logic even on PHP 8.3+
        // by manually invoking the logic if possible or just verifying current behavior.
        // Since we can't easily disable json_validate if it exists, we just test if it works.
        // If it works on PHP 8.3+ but fails on PHP < 8.3, we have an inconsistency.

        $this->assertTrue($reflection->invoke($response, 'true'), 'JSON true should be valid');
        $this->assertTrue($reflection->invoke($response, 'false'), 'JSON false should be valid');
        $this->assertTrue($reflection->invoke($response, 'null'), 'JSON null should be valid');
        $this->assertTrue($reflection->invoke($response, '123'), 'JSON number should be valid');
        $this->assertTrue($reflection->invoke($response, '"string"'), 'JSON string should be valid');
    }
}

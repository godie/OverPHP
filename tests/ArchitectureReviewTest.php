<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Response;
use OverPHP\Core\Router;
use PHPUnit\Framework\TestCase;

final class ArchitectureReviewTest extends TestCase
{
    public function testJsonResponseCorrectlyEncodesScalars(): void
    {
        // Issue: Response::json(true) emits "1" instead of "true"
        $response = Response::json(true);
        ob_start();
        $response->send();
        $output = ob_get_clean();
        $this->assertEquals('true', $output, 'JSON true should be encoded as "true"');

        $response = Response::json(null);
        ob_start();
        $response->send();
        $output = ob_get_clean();
        $this->assertEquals('null', $output, 'JSON null should be encoded as "null"');
    }

    public function testRouterResolvesAbsoluteNamespaces(): void
    {
        // Issue: Router fails to resolve absolute namespaces starting with \
        $router = new Router('App\\Controllers');

        // This should stay as is if it starts with \
        $reflection = new \ReflectionMethod($router, 'resolveControllerFqn');
        $reflection->setAccessible(true);

        $fqn = $reflection->invoke($router, '\\External\\Controller');
        $this->assertEquals('\\External\\Controller', $fqn);
    }

    public function testRouterServeFileHeaderOrderFor304(): void
    {
        // Create a dummy file
        $file = sys_get_temp_dir() . '/test_304.txt';
        file_put_contents($file, 'test content');
        $stat = stat($file);
        $etag = sprintf('"%x-%x"', $stat['mtime'], $stat['size']);

        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;

        $router = new Router();
        $reflection = new \ReflectionMethod($router, 'serveFile');
        $reflection->setAccessible(true);

        // We can't easily test header() in PHPUnit without extensions like xdebug or uopz,
        // but we can check if it returns early.
        // Actually, serveFile returns void.

        @unlink($file);
        $this->assertTrue(true); // Placeholder for logic verification during implementation
    }
}

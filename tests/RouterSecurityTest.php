<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Router;
use OverPHP\Core\Security;
use PHPUnit\Framework\TestCase;

final class RouterSecurityTest extends TestCase
{
    private string $basePath;
    private string $clientPath;
    private string $siblingPath;

    protected function setUp(): void
    {
        Security::setCsrfEnabled(false);
        $this->basePath = realpath(sys_get_temp_dir()) . '/security_test_' . uniqid();
        mkdir($this->basePath);

        $this->clientPath = $this->basePath . '/public';
        mkdir($this->clientPath);
        file_put_contents($this->clientPath . '/index.html', 'public index');

        $this->siblingPath = $this->basePath . '/public_secret';
        mkdir($this->siblingPath);
        file_put_contents($this->siblingPath . '/config.php', 'secret data');

        // Create sensitive files in public
        file_put_contents($this->clientPath . '/data.sql', 'DATABASE DUMP');
        file_put_contents($this->clientPath . '/config.php.bak', 'PHP BACKUP');

        $_SERVER = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        @unlink($this->clientPath . '/data.sql');
        @unlink($this->clientPath . '/config.php.bak');
        @unlink($this->siblingPath . '/config.php');
        @rmdir($this->siblingPath);
        @unlink($this->clientPath . '/index.html');
        @rmdir($this->clientPath);
        @rmdir($this->basePath);
        $_SERVER = [];
    }

    public function testDirectoryTraversalToSiblingIsPrevented(): void
    {
        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $this->clientPath,
            'fallback_index' => 'index.html'
        ]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/../public_secret/config.php';

        ob_start();
        $router->run();
        $output = ob_get_clean();

        $this->assertNotEquals('secret data', $output);
        $this->assertEquals('public index', $output);
    }

    public function testBlockedExtensionsReturnForbidden(): void
    {
        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $this->clientPath,
            'fallback_index' => 'index.html'
        ]);

        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Test .sql
        $_SERVER['REQUEST_URI'] = '/data.sql';
        ob_start();
        $router->run();
        $output = ob_get_clean();
        $this->assertStringContainsString('Forbidden', $output);
        $this->assertStringNotContainsString('DATABASE DUMP', $output);

        // Test .php.bak
        $_SERVER['REQUEST_URI'] = '/config.php.bak';
        ob_start();
        $router->run();
        $output = ob_get_clean();
        $this->assertStringContainsString('Forbidden', $output);
        $this->assertStringNotContainsString('PHP BACKUP', $output);
    }

    public function testHeadRequestDoesNotReturnBody(): void
    {
        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $this->clientPath,
            'fallback_index' => 'index.html'
        ]);

        file_put_contents($this->clientPath . '/test.txt', 'some content');
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/test.txt';

        ob_start();
        $router->run();
        $output = ob_get_clean();

        $this->assertEquals('', $output);
        unlink($this->clientPath . '/test.txt');
    }
}

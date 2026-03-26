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

        $_SERVER = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
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
}

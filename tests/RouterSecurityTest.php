<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterSecurityTest extends TestCase
{
    private string $basePath;
    private string $clientPath;
    private string $siblingPath;

    protected function setUp(): void
    {
        $this->basePath = __DIR__ . '/security_test_' . uniqid();
        mkdir($this->basePath);

        $this->clientPath = $this->basePath . '/public';
        mkdir($this->clientPath);
        file_put_contents($this->clientPath . '/index.html', 'public index');

        $this->siblingPath = $this->basePath . '/public_secret';
        mkdir($this->siblingPath);
        file_put_contents($this->siblingPath . '/config.php', 'secret data');
    }

    protected function tearDown(): void
    {
        unlink($this->siblingPath . '/config.php');
        rmdir($this->siblingPath);
        unlink($this->clientPath . '/index.html');
        rmdir($this->clientPath);
        rmdir($this->basePath);
    }

    public function testDirectoryTraversalToSiblingIsPrevented(): void
    {
        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $this->clientPath,
            'fallback_index' => 'index.html'
        ]);

        // We try to access /../public_secret/config.php
        // After the fix, $this->resolvedClientPath ends with a slash (e.g., '/.../public/')
        // realpath('/.../public//../public_secret/config.php') is '/.../public_secret/config.php'
        // str_starts_with('/.../public_secret/config.php', '/.../public/') will return FALSE

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/../public_secret/config.php';
        $_SERVER['HTTPS'] = 'off';

        ob_start();
        $router->run();
        $output = ob_get_clean();

        // It should fall back to index.html or not serve the file
        $this->assertNotEquals('secret data', $output);

        $this->assertEquals('public index', $output);
    }
}

<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterClientTest extends TestCase
{
    private string $tempClientDir;

    protected function setUp(): void
    {
        $this->tempClientDir = sys_get_temp_dir() . '/overphp_client_' . uniqid();
        mkdir($this->tempClientDir);
        file_put_contents($this->tempClientDir . '/index.html', '<html><body>Fallback</body></html>');
        file_put_contents($this->tempClientDir . '/test.txt', 'Hello World');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempClientDir);
    }

    private function removeDir(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testServeClientServesExistingFile(): void
    {
        $_SERVER['REQUEST_URI'] = '/test.txt';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $this->tempClientDir
        ]);

        ob_start();
        $router->run();
        $output = ob_get_clean();

        $this->assertEquals('Hello World', $output);
    }

    public function testServeClientServesFallbackIndexOnMissingFile(): void
    {
        $_SERVER['REQUEST_URI'] = '/non-existent.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $this->tempClientDir,
            'fallback_index' => 'index.html'
        ]);

        ob_start();
        $router->run();
        $output = ob_get_clean();

        $this->assertEquals('<html><body>Fallback</body></html>', $output);
    }
}

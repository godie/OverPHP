<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Router;
use OverPHP\Core\Response;
use OverPHP\Core\Container;
use OverPHP\Core\Security;
use PHPUnit\Framework\TestCase;

final class MartinReviewTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        Security::setCsrfEnabled(false);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    public function testScalarResponsesInRouter(): void
    {
        $router = new Router();

        // Test boolean true
        $router->add('GET', '/true', fn() => true);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/true';

        ob_start();
        $router->run();
        $output = ob_get_clean();
        $this->assertStringContainsString('true', $output, 'Boolean true should be "true" JSON string');

        // Test boolean false
        $router->add('GET', '/false', fn() => false);
        $_SERVER['REQUEST_URI'] = '/api/false';

        ob_start();
        $router->run();
        $output = ob_get_clean();
        $this->assertStringContainsString('false', $output, 'Boolean false should be "false" JSON string');

        // Test null
        $router->add('GET', '/null', fn() => null);
        $_SERVER['REQUEST_URI'] = '/api/null';

        ob_start();
        $router->run();
        $output = ob_get_clean();
        $this->assertStringContainsString('null', $output, 'Null should be "null" JSON string');

        // Test integer
        $router->add('GET', '/int', fn() => 123);
        $_SERVER['REQUEST_URI'] = '/api/int';

        ob_start();
        $router->run();
        $output = ob_get_clean();
        $this->assertStringContainsString('123', $output, 'Integer should be "123" JSON string');
    }

    public function testHeadRequestDoesNotServeBody(): void
    {
        // Setup a dummy client directory
        $clientDir = __DIR__ . '/test_client';
        if (!is_dir($clientDir)) {
            mkdir($clientDir);
        }
        file_put_contents($clientDir . '/test.txt', 'Hello World');

        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $clientDir,
        ]);

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/test.txt';

        ob_start();
        $router->run();
        $output = ob_get_clean();

        // Cleanup
        unlink($clientDir . '/test.txt');
        rmdir($clientDir);

        $this->assertEquals('', $output, 'HEAD request should NOT have a body');
    }

    public function testServeFileSensitiveFilesBlocked(): void
    {
        $clientDir = __DIR__ . '/test_client_sec';
        if (!is_dir($clientDir)) {
            mkdir($clientDir);
        }

        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $clientDir,
        ]);

        $sensitiveFiles = [
            'config.php.bak' => 'secret_data',
            'database.sql' => 'DROP TABLE users;',
            'app.log' => 'error at line 10',
            'test.old' => 'old version',
            'script.save' => 'saved version',
            '.env' => 'DB_PASS=123'
        ];

        foreach ($sensitiveFiles as $file => $content) {
            file_put_contents($clientDir . '/' . $file, $content);
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/' . $file;

            ob_start();
            $router->run();
            $output = ob_get_clean();

            $this->assertStringContainsString('Forbidden', $output, "File $file should be blocked");
            $this->assertStringNotContainsString($content, $output, "Content of $file should not be exposed");

            unlink($clientDir . '/' . $file);
        }

        // Cleanup
        rmdir($clientDir);
    }
}

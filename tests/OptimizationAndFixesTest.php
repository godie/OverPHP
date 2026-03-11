<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Response;
use OverPHP\Core\Router;
use OverPHP\Core\Security;
use PHPUnit\Framework\TestCase;

final class OptimizationAndFixesTest extends TestCase
{
    protected function setUp(): void
    {
        Security::setCsrfEnabled(true);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testRawResponseIsNotEscaped(): void
    {
        $content = '<h1>Hello World</h1>';
        $response = Response::raw($content);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertEquals($content, $output);
    }

    public function testRouterAcceptsXXSRFToken(): void
    {
        $token = Security::generateCsrfToken();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_X_XSRF_TOKEN'] = $token;

        $router = new Router('OverPHP\\Controllers', '/api');
        $router->add('POST', '/test', function () {
            return ['success' => true];
        });

        ob_start();
        $router->run();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertTrue($decoded['success'] ?? false);
    }

    public function testRouterServeFileHandlesETagAnd304(): void
    {
        // Setup a dummy client directory and file
        $clientPath = __DIR__ . '/../temp_client';
        if (!is_dir($clientPath)) {
            mkdir($clientPath);
        }
        $filePath = $clientPath . '/test.txt';
        file_put_contents($filePath, 'test content');
        $etag = md5_file($filePath);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test.txt';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;

        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $clientPath,
            'fallback_index' => 'index.html'
        ]);

        ob_start();
        $router->run();
        ob_get_clean();

        $this->assertEquals(304, http_response_code());

        // Cleanup
        unlink($filePath);
        rmdir($clientPath);
    }
}

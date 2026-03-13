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
        $lastModified = filemtime($filePath);
        $fileSize = filesize($filePath);
        $etag = sprintf('"%x-%x"', $lastModified, $fileSize);

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

    public function testRouterPreventsPathTraversalToSiblingDirectory(): void
    {
        $basePath = __DIR__ . '/../temp_traversal_' . uniqid();
        mkdir($basePath);
        $publicPath = $basePath . '/public';
        $secretPath = $basePath . '/secret';
        mkdir($publicPath);
        mkdir($secretPath);

        file_put_contents($publicPath . '/index.html', 'public content');
        file_put_contents($secretPath . '/secret.txt', 'secret content');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/../secret/secret.txt';

        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $publicPath,
            'fallback_index' => 'index.html'
        ]);

        ob_start();
        $router->run();
        $output = ob_get_clean();

        // Should return fallback index instead of secret content
        $this->assertEquals('public content', $output);

        // Cleanup
        unlink($publicPath . '/index.html');
        unlink($secretPath . '/secret.txt');
        rmdir($publicPath);
        rmdir($secretPath);
        rmdir($basePath);
    }
}

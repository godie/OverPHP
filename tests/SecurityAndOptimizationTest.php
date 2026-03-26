<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use PHPUnit\Framework\TestCase;
use OverPHP\Core\Response;
use OverPHP\Core\Router;

class SecurityAndOptimizationTest extends TestCase
{
    private string $tempClientPath;

    protected function setUp(): void
    {
        $this->tempClientPath = sys_get_temp_dir() . '/overphp_test_client_' . uniqid();
        mkdir($this->tempClientPath);
        file_put_contents($this->tempClientPath . '/index.html', '<html><body>Home</body></html>');
        file_put_contents($this->tempClientPath . '/test.php', '<?php echo "secret"; ?>');
        file_put_contents($this->tempClientPath . '/.env', 'SECRET=true');
        file_put_contents($this->tempClientPath . '/IMAGE.JPG', 'fake-image-data');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempClientPath);
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['HTTP_IF_NONE_MATCH']);
    }

    private function removeDirectory(string $path): void
    {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $this->removeDirectory($path . '/' . $file);
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }

    public function testResponseIsJsonHandlesWhitespaceCorrectly(): void
    {
        $response = new Response("   {\"status\":\"ok\"}   ");
        $reflection = new \ReflectionClass($response);
        $method = $reflection->getMethod('isJson');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($response, "   {\"status\":\"ok\"}   "));
        $this->assertTrue($method->invoke($response, "   [1, 2, 3]   "));
        $this->assertFalse($method->invoke($response, "   not json   "));
        $this->assertFalse($method->invoke($response, "   "));
        $this->assertFalse($method->invoke($response, ""));
    }

    public function testRouterBlocksPhpAndHiddenFiles(): void
    {
        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $this->tempClientPath,
            'fallback_index' => 'index.html'
        ]);

        // Capture output
        ob_start();

        // Test PHP file
        $_SERVER['REQUEST_URI'] = '/test.php';
        $router->run();
        $output = ob_get_clean();
        $this->assertStringContainsString('Forbidden', $output);
        $this->assertEquals(403, http_response_code());

        // Test Hidden file
        ob_start();
        $_SERVER['REQUEST_URI'] = '/.env';
        $router->run();
        $output = ob_get_clean();
        $this->assertStringContainsString('Forbidden', $output);
        $this->assertEquals(403, http_response_code());

        // Reset response code for next tests
        http_response_code(200);
    }

    public function testRouterHandlesCaseInsensitiveExtensions(): void
    {
        $router = new Router('OverPHP\\Controllers', '/api', null, [
            'enabled' => true,
            'path' => $this->tempClientPath,
            'fallback_index' => 'index.html'
        ]);

        $_SERVER['REQUEST_URI'] = '/IMAGE.JPG';

        ob_start();
        $router->run();
        $output = ob_get_clean();

        $this->assertEquals('fake-image-data', $output);
        // We can't easily test headers in CLI unless we mock them,
        // but if it didn't return 404/403, it worked.
    }
}

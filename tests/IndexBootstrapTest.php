<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use PHPUnit\Framework\TestCase;

final class IndexBootstrapTest extends TestCase
{
    private string $projectRoot;
    private string $configPath;
    private string $helloControllerPath;
    private string $configBackup;
    private string $helloControllerBackup;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__);
        $this->configPath = $this->projectRoot . '/config.php';
        $this->helloControllerPath = $this->projectRoot . '/src/Controllers/HelloController.php';
        $this->configBackup = file_get_contents($this->configPath) ?: '';
        $this->helloControllerBackup = file_get_contents($this->helloControllerPath) ?: '';
    }

    protected function tearDown(): void
    {
        file_put_contents($this->configPath, $this->configBackup);
        file_put_contents($this->helloControllerPath, $this->helloControllerBackup);
    }

    public function testIndexBootstrapsDefaultControllerNamespace(): void
    {
        file_put_contents($this->configPath, <<<'PHP'
<?php

declare(strict_types=1);

return [
    'allowed_origins' => ['http://localhost:5173'],
    'route_prefix' => '/api',
    'benchmark' => ['enabled' => false],
    'security' => ['csrf_enabled' => false],
    'client' => ['enabled' => false, 'path' => '', 'fallback_index' => 'index.html'],
];
PHP);

        $output = $this->runIndexRequest('/api/hello');
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Hello from OverPHP!', $decoded['message'] ?? null);
    }

    public function testIndexBootstrapsCustomControllerNamespaceWithComposerAutoloadPresent(): void
    {
        file_put_contents($this->configPath, <<<'PHP'
<?php

declare(strict_types=1);

return [
    'allowed_origins' => ['http://localhost:5173'],
    'route_prefix' => '/api',
    'controller_namespace' => 'App\\Controllers',
    'benchmark' => ['enabled' => false],
    'security' => ['csrf_enabled' => false],
    'client' => ['enabled' => false, 'path' => '', 'fallback_index' => 'index.html'],
];
PHP);

        file_put_contents($this->helloControllerPath, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers;

final class HelloController
{
    public function index(): array
    {
        return ['message' => 'Hello from custom namespace!'];
    }

    public function show(string $name): array
    {
        return ['message' => 'Hello, ' . $name . '!'];
    }

    public function raw(): string
    {
        return '<h1>Hello from custom namespace!</h1>';
    }
}
PHP);

        $output = $this->runIndexRequest('/api/hello');
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Hello from custom namespace!', $decoded['message'] ?? null);
    }

    private function runIndexRequest(string $uri): string
    {
        $script = <<<'PHP'
chdir(%s);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = %s;
ob_start();
require 'index.php';
echo ob_get_clean();
PHP;

        $command = sprintf(
            '%s -r %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(sprintf($script, var_export($this->projectRoot, true), var_export($uri, true)))
        );

        $output = shell_exec($command);

        $this->assertNotFalse($output);

        return (string) $output;
    }
}

<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use PHPUnit\Framework\TestCase;

final class ScaffoldCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/overphp_scaffold_' . bin2hex(random_bytes(6));
        mkdir($this->workspace, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testNewCommandGeneratesProjectThatSupportsCustomControllerNamespace(): void
    {
        $projectName = 'SampleApp';
        $projectRoot = $this->workspace . '/' . $projectName;
        $cliPath = dirname(__DIR__) . '/bin/overphp';

        $command = sprintf(
            'cd %s && %s %s new %s',
            escapeshellarg($this->workspace),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($cliPath),
            escapeshellarg($projectName)
        );

        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertDirectoryExists($projectRoot);
        $this->assertFileExists($projectRoot . '/index.php');
        $this->assertFileExists($projectRoot . '/config.php');
        $this->assertFileExists($projectRoot . '/composer.json');
        $this->assertFileExists($projectRoot . '/src/Controllers/HelloController.php');

        $composer = json_decode((string) file_get_contents($projectRoot . '/composer.json'), true);
        $this->assertIsArray($composer);
        $this->assertSame('src/', $composer['autoload']['psr-4']['OverPHP\\'] ?? null);

        file_put_contents($projectRoot . '/config.php', <<<'PHP'
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

        file_put_contents($projectRoot . '/src/Controllers/HelloController.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers;

final class HelloController
{
    public function index(): array
    {
        return ['message' => 'Scaffold custom namespace works'];
    }

    public function show(string $name): array
    {
        return ['message' => 'Hello, ' . $name];
    }

    public function raw(): string
    {
        return '{"raw":true}';
    }
}
PHP);

        mkdir($projectRoot . '/vendor', 0777, true);
        file_put_contents($projectRoot . '/vendor/autoload.php', <<<'PHP'
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Helpers/cors.php';
require_once dirname(__DIR__) . '/src/Helpers/database.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'OverPHP\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
PHP);

        $script = <<<'PHP'
chdir(%s);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/hello';
ob_start();
require 'index.php';
echo ob_get_clean();
PHP;

        $requestCommand = sprintf(
            '%s -r %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(sprintf($script, var_export($projectRoot, true)))
        );

        $response = shell_exec($requestCommand);

        $this->assertNotFalse($response);
        $decoded = json_decode((string) $response, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Scaffold custom namespace works', $decoded['message'] ?? null);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $current = $path . '/' . $item;
            if (is_dir($current)) {
                $this->removeDirectory($current);
                continue;
            }

            unlink($current);
        }

        rmdir($path);
    }
}

<?php

declare(strict_types=1);

namespace OverPHP\Core;

final class Router
{
    /** @var array<int,array{method:string,path:string,handler:mixed}> */
    private array $routes = [];

    private string $controllerNamespace;
    private string $prefix;
    /** @var array{enabled:bool,path:string,fallback_index:string} */
    private array $clientConfig;

    /**
     * @param array{enabled?:bool,path?:string,fallback_index?:string} $clientConfig
     */
    public function __construct(
        string $controllerNamespace = 'OverPHP\\Controllers',
        string $prefix = '/api',
        array $clientConfig = []
    ) {
        $this->controllerNamespace = $controllerNamespace;
        $this->prefix = $prefix;
        $this->clientConfig = array_merge([
            'enabled' => false,
            'path' => '',
            'fallback_index' => 'index.html',
        ], $clientConfig);
    }

    /** @param callable|string $handler */
    public function add(string $method, string $path, $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function run(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = explode('?', $uri)[0];
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // 1. Try to serve client static files if enabled and prefix doesn't match
        if ($this->clientConfig['enabled'] && !str_starts_with($uri, $this->prefix)) {
            if ($this->serveClient($uri)) {
                return;
            }
        }

        // 2. Handle API routes
        // Strip prefix from the beginning of URI only
        if ($this->prefix !== '' && str_starts_with($uri, $this->prefix)) {
            $uri = substr($uri, strlen($this->prefix));
        }

        // CSRF Protection for state-changing methods
        if (Security::isCsrfEnabled() && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf_token'] ?? null;
            if (!Security::validateCsrfToken($token)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                try {
                    echo Security::jsonEncode(['success' => false, 'error' => 'Invalid CSRF token']);
                } catch (\JsonException $e) {
                    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                }
                return;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['path'] !== $uri || $route['method'] !== $method) {
                continue;
            }

            $handler = $route['handler'];

            if (is_callable($handler)) {
                $response = $handler();
                $this->emitResponse($response);
                return;
            }

            if (is_string($handler) && strpos($handler, '@') !== false) {
                [$controller, $methodName] = explode('@', $handler, 2);
                $controllerClass = $this->resolveControllerFqn($controller);

                if (!class_exists($controllerClass)) {
                    break;
                }

                $instance = new $controllerClass(); /** @phpstan-ignore-line */
                if (!method_exists($instance, $methodName)) {
                    break;
                }

                $response = $instance->$methodName();
                $this->emitResponse($response);
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Not Found']);
    }

    private function serveClient(string $uri): bool
    {
        $clientPath = rtrim($this->clientConfig['path'], '/');
        if ($clientPath === '' || !is_dir($clientPath)) {
            return false;
        }

        $filePath = $clientPath . $uri;

        // If it's a directory, try to find index.html
        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, '/') . '/' . $this->clientConfig['fallback_index'];
        }

        // Security check: Prevent directory traversal
        $realClientPath = realpath($clientPath);
        $realFilePath = realpath($filePath);

        if ($realFilePath && $realClientPath && str_starts_with($realFilePath, $realClientPath) && is_file($realFilePath)) {
            $this->serveFile($realFilePath);
            return true;
        }

        // SPA Fallback: if file doesn't exist, serve fallback_index
        $fallbackPath = $clientPath . '/' . $this->clientConfig['fallback_index'];
        $realFallbackPath = realpath($fallbackPath);
        if ($realFallbackPath && is_file($realFallbackPath)) {
            $this->serveFile($realFallbackPath);
            return true;
        }

        return false;
    }

    private function serveFile(string $filePath): void
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
        ];

        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        readfile($filePath);
    }

    private function resolveControllerFqn(string $controller): string
    {
        if (strpos($controller, 'OverPHP\\') === 0) {
            return $controller;
        }

        return rtrim($this->controllerNamespace, '\\') . '\\' . $controller;
    }

    private function emitResponse(mixed $response): void
    {
        if (is_array($response) || is_object($response)) {
            header('Content-Type: application/json; charset=utf-8');
            $output = (array) $response;

            $stats = Benchmark::stats();
            if ($stats !== null) {
                $output['_performance'] = $stats;
            }

            try {
                echo Security::jsonEncode($output);
            } catch (\JsonException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
            }
            return;
        }

        if (is_string($response)) {
            echo Security::escape($response);
        }
    }
}

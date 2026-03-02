<?php

declare(strict_types=1);

namespace OverPHP\Core;

final class Router
{
    /** @var array<int,array{method:string,path:string,handler:mixed,options:array,pattern:string}> */
    private array $routes = [];

    private string $controllerNamespace;
    private string $prefix;
    private ?Container $container;
    /** @var array{enabled:bool,path:string,fallback_index:string} */
    private array $clientConfig;

    /**
     * @param array{enabled?:bool,path?:string,fallback_index?:string} $clientConfig
     */
    public function __construct(
        string $controllerNamespace = 'OverPHP\\Controllers',
        string $prefix = '/api',
        ?Container $container = null,
        array $clientConfig = []
    ) {
        $this->controllerNamespace = $controllerNamespace;
        $this->prefix = $prefix;
        $this->container = $container ?? Container::getInstance();
        $this->clientConfig = array_merge([
            'enabled' => false,
            'path' => '',
            'fallback_index' => 'index.html',
        ], $clientConfig);
    }

    /**
     * @param callable|string $handler
     * @param array $options Route options like 'skip_csrf' => true
     */
    public function add(string $method, string $path, $handler, array $options = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'options' => $options,
            'pattern' => $this->convertPathToRegex($path),
        ];
    }

    public function run(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = explode('?', $uri)[0];
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($this->clientConfig['enabled'] && !str_starts_with($uri, $this->prefix)) {
            if ($this->serveClient($uri)) {
                return;
            }
        }

        if ($this->prefix !== '' && str_starts_with($uri, $this->prefix)) {
            $uri = substr($uri, strlen($this->prefix));
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                if (Security::isCsrfEnabled() &&
                    empty($route['options']['skip_csrf']) &&
                    in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {

                    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf_token'] ?? null;
                    if (!Security::validateCsrfToken($token)) {
                        $this->sendError(403, 'Invalid CSRF token');
                        return;
                    }
                }

                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];

                if (is_callable($handler)) {
                    $response = $handler(...array_values($params));
                    $this->emitResponse($response);
                    return;
                }

                if (is_string($handler) && strpos($handler, '@') !== false) {
                    [$controller, $methodName] = explode('@', $handler, 2);
                    $controllerClass = $this->resolveControllerFqn($controller);

                    if (!class_exists($controllerClass)) {
                        break;
                    }

                    $instance = $this->container->make($controllerClass);
                    if (!method_exists($instance, $methodName)) {
                        break;
                    }

                    $response = $instance->$methodName(...array_values($params));
                    $this->emitResponse($response);
                    return;
                }
            }
        }

        $this->sendError(404, 'Not Found');
    }

    private function convertPathToRegex(string $path): string
    {
        return '#^' . preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path) . '$#';
    }

    private function serveClient(string $uri): bool
    {
        $clientPath = rtrim($this->clientConfig['path'], '/');
        if ($clientPath === '' || !is_dir($clientPath)) {
            return false;
        }

        $filePath = $clientPath . $uri;

        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, '/') . '/' . $this->clientConfig['fallback_index'];
        }

        $realClientPath = realpath($clientPath);
        $realFilePath = realpath($filePath);

        if ($realFilePath && $realClientPath && str_starts_with($realFilePath, $realClientPath) && is_file($realFilePath)) {
            $this->serveFile($realFilePath);
            return true;
        }

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
        if ($response instanceof Response) {
            $response->send();
            return;
        }

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
                $this->sendError(500, 'Internal Server Error');
            }
            return;
        }

        if (is_string($response)) {
            if ($this->isJson($response)) {
                header('Content-Type: application/json; charset=utf-8');
                echo $response;
            } else {
                echo Security::escape($response);
            }
        }
    }

    private function isJson(string $string): bool
    {
        if ($string === '') {
            return false;
        }
        $first = $string[0];
        $last = substr($string, -1);
        if (($first === '{' && $last === '}') || ($first === '[' && $last === ']')) {
            json_decode($string);
            return json_last_error() === JSON_ERROR_NONE;
        }
        return false;
    }

    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

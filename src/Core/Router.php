<?php

declare(strict_types=1);

namespace OverPHP\Core;

final class Router
{
    private const MIME_TYPES = [
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

    /** @var array<string, array{static: array<string, array{handler:mixed, options:array}>, dynamic: array<int, array{path:string, handler:mixed, options:array, params:array<string>}>}> */
    private array $routes = [];

    /** @var array<string, string> */
    private array $compiledRegex = [];

    private string $controllerNamespace;
    private string $prefix;
    private ?Container $container;
    /** @var array{enabled:bool,path:string,fallback_index:string} */
    private array $clientConfig;
    private ?string $resolvedClientPath = null;

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

        if ($this->clientConfig['enabled'] && $this->clientConfig['path'] !== '') {
            $this->resolvedClientPath = realpath($this->clientConfig['path']) ?: null;
        }
    }

    /**
     * @param callable|string $handler
     * @param array $options Route options like 'skip_csrf' => true
     */
    public function add(string $method, string $path, $handler, array $options = []): void
    {
        $method = strtoupper($method);
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = ['static' => [], 'dynamic' => []];
        }

        if (strpos($path, '{') === false) {
            $this->routes[$method]['static'][$path] = [
                'handler' => $handler,
                'options' => $options,
            ];
        } else {
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches);
            $this->routes[$method]['dynamic'][] = [
                'path' => $path,
                'handler' => $handler,
                'options' => $options,
                'params' => $matches[1],
            ];
        }
    }

    public function run(): void
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($this->clientConfig['enabled'] && !str_starts_with($uri, $this->prefix)) {
            if ($this->serveClient($uri)) {
                return;
            }
        }

        if ($this->prefix !== '' && str_starts_with($uri, $this->prefix)) {
            $uri = substr($uri, strlen($this->prefix));
            if ($uri === '') {
                $uri = '/';
            }
        }

        if (!isset($this->routes[$method])) {
            $this->sendError(404, 'Not Found');
            return;
        }

        // 1. Check static routes (O(1))
        if (isset($this->routes[$method]['static'][$uri])) {
            $route = $this->routes[$method]['static'][$uri];
            if ($this->validateCsrf($method, $route['options'])) {
                $this->handleRoute($route['handler']);
            }
            return;
        }

        // 2. Check dynamic routes (Combined Regex for high performance)
        $compiled = $this->getCompiledRegex($method);
        if ($compiled && preg_match($compiled, $uri, $matches)) {
            $index = (int) $matches['MARK'];
            $route = $this->routes[$method]['dynamic'][$index];
            if ($this->validateCsrf($method, $route['options'])) {
                $params = [];
                foreach ($route['params'] as $i => $name) {
                    $params[$name] = $matches[$i + 1] ?? '';
                }
                $this->handleRoute($route['handler'], $params);
            }
            return;
        }

        $this->sendError(404, 'Not Found');
    }

    private function validateCsrf(string $method, array $options): bool
    {
        if (Security::isCsrfEnabled() &&
            empty($options['skip_csrf']) &&
            in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {

            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? $_POST['_csrf_token'] ?? null;
            if (!Security::validateCsrfToken($token)) {
                $this->sendError(403, 'Invalid CSRF token');
                return false;
            }
        }
        return true;
    }

    private function handleRoute(mixed $handler, array $params = []): void
    {
        if (is_callable($handler)) {
            $response = $handler(...array_values($params));
            $this->emitResponse($response);
            return;
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controller, $methodName] = explode('@', $handler, 2);
            $controllerClass = $this->resolveControllerFqn($controller);

            if (!class_exists($controllerClass)) {
                $this->sendError(404, 'Controller Not Found');
                return;
            }

            $instance = $this->container->make($controllerClass);
            if (!method_exists($instance, $methodName)) {
                $this->sendError(404, 'Action Not Found');
                return;
            }

            $response = $instance->$methodName(...array_values($params));
            $this->emitResponse($response);
            return;
        }
    }

    private function getCompiledRegex(string $method): ?string
    {
        if (isset($this->compiledRegex[$method])) {
            return $this->compiledRegex[$method];
        }

        if (empty($this->routes[$method]['dynamic'])) {
            return null;
        }

        $patterns = [];
        foreach ($this->routes[$method]['dynamic'] as $index => $route) {
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $route['path']);
            $patterns[] = $pattern . '(*MARK:' . $index . ')';
        }

        return $this->compiledRegex[$method] = '#^(?|' . implode('|', $patterns) . ')$#';
    }

    private function serveClient(string $uri): bool
    {
        if ($this->resolvedClientPath === null) {
            return false;
        }

        $filePath = $this->resolvedClientPath . $uri;

        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, '/') . '/' . $this->clientConfig['fallback_index'];
        }

        $realFilePath = realpath($filePath);

        if ($realFilePath && str_starts_with($realFilePath, $this->resolvedClientPath) && is_file($realFilePath)) {
            $this->serveFile($realFilePath);
            return true;
        }

        $fallbackPath = $this->resolvedClientPath . '/' . $this->clientConfig['fallback_index'];
        $realFallbackPath = realpath($fallbackPath);
        if ($realFallbackPath && is_file($realFallbackPath)) {
            $this->serveFile($realFallbackPath);
            return true;
        }

        return false;
    }

    private function serveFile(string $filePath): void
    {
        $lastModified = filemtime($filePath);
        $etag = md5_file($filePath);

        if (!headers_sent()) {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $contentType = self::MIME_TYPES[$extension] ?? 'application/octet-stream';

            header('Content-Type: ' . $contentType);
            header('Cache-Control: public, max-age=3600');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
            header('ETag: "' . $etag . '"');
            header('Content-Length: ' . filesize($filePath));

            $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            if ($ifNoneMatch !== '' && trim($ifNoneMatch, '" ') === $etag) {
                http_response_code(304);
                return;
            }
        }

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

        (new Response($response))->send();
    }

    private function sendError(int $code, string $message): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo Security::jsonEncode(['success' => false, 'error' => $message]);
    }
}

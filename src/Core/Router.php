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
        'webp' => 'image/webp',
        'txt'  => 'text/plain',
        'pdf'  => 'application/pdf',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];

    /** @var array<string, array{static: array<string, array{handler:mixed, options:array}>, dynamic: array<int, array{path:string, handler:mixed, options:array, params:array<string>, types:array<string,string>}>}> */
    private array $routes = [];

    /** @var array<string, string> */
    private array $compiledRegex = [];

    private static ?\finfo $finfo = null;

    private readonly string $controllerNamespace;
    private readonly string $prefix;
    private readonly ?Container $container;
    /** @var array{enabled:bool,path:string,fallback_index:string} */
    private readonly array $clientConfig;
    private readonly ?string $resolvedClientPath;
    private readonly ?string $resolvedFallbackPath;

    /**
     * @param array{enabled?:bool,path?:string,fallback_index?:string} $clientConfig
     */
    public function __construct(
        string $controllerNamespace = 'OverPHP\\Controllers',
        string $prefix = '/api',
        ?Container $container = null,
        array $clientConfig = []
    ) {
        $this->controllerNamespace = rtrim($controllerNamespace, '\\');
        $this->prefix = rtrim($prefix, '/');
        $this->container = $container ?? Container::getInstance();
        $this->clientConfig = array_merge([
            'enabled' => false,
            'path' => '',
            'fallback_index' => 'index.html',
        ], $clientConfig);

        $resolvedClientPath = null;
        $resolvedFallbackPath = null;

        if ($this->clientConfig['enabled'] && $this->clientConfig['path'] !== '') {
            $resolvedClientPath = realpath($this->clientConfig['path']) ?: null;
            if ($resolvedClientPath !== null) {
                // Append DIRECTORY_SEPARATOR to ensure str_starts_with is secure against sibling traversal
                $resolvedClientPath .= DIRECTORY_SEPARATOR;
                $fallbackPath = $resolvedClientPath . $this->clientConfig['fallback_index'];
                $resolvedFallbackPath = realpath($fallbackPath) ?: null;
            }
        }

        $this->resolvedClientPath = $resolvedClientPath;
        $this->resolvedFallbackPath = $resolvedFallbackPath;
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

        if (!str_contains($path, '{')) {
            $this->routes[$method]['static'][$path] = [
                'handler' => $handler,
                'options' => $options,
            ];
        } else {
            preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/', $path, $matches);
            $this->routes[$method]['dynamic'][] = [
                'path' => $path,
                'handler' => $handler,
                'options' => $options,
                'params' => $matches[1],
                'types' => $this->routeTypes($options),
            ];
        }

        unset($this->compiledRegex[$method]);
    }

    public function run(): void
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $hasPrefix = $this->prefix !== '' && (
            $uri === $this->prefix || str_starts_with($uri, $this->prefix . '/')
        );

        if ($this->clientConfig['enabled'] && !$hasPrefix) {
            if ($this->serveClient($uri)) {
                return;
            }
        }

        if ($this->prefix !== '' && !$hasPrefix) {
            $this->sendError(404, 'Not Found');
            return;
        }

        if ($hasPrefix) {
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
                try {
                    $this->handleRoute($route['handler'], $this->castRouteParams($params, $route['types']));
                } catch (\InvalidArgumentException) {
                    $this->sendError(404, 'Not Found');
                }
            }
            return;
        }

        $this->sendError(404, 'Not Found');
    }

    private function validateCsrf(string $method, array $options): bool
    {
        if (Security::isCsrfEnabled() &&
            empty($options['skip_csrf']) &&
            Security::shouldValidateCsrf($method)) {

            $token = Security::csrfTokenFromRequest();
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
            $response = $handler(...$params);
            $this->emitResponse($response);
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
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

            $response = $instance->$methodName(...$params);
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
            $pattern = $this->compileDynamicRoutePattern($route['path'], (array) ($route['options']['where'] ?? []));
            $patterns[] = $pattern . '(*MARK:' . $index . ')';
        }

        return $this->compiledRegex[$method] = '#^(?|' . implode('|', $patterns) . ')$#';
    }

    /**
     * @param array<string, string> $constraints
     */
    private function compileDynamicRoutePattern(string $path, array $constraints): string
    {
        $offset = 0;
        $pattern = '';

        if (!preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/', $path, $matches, PREG_OFFSET_CAPTURE)) {
            return preg_quote($path, '#');
        }

        foreach ($matches[0] as $index => $match) {
            [$placeholder, $position] = $match;
            $name = $matches[1][$index][0];
            $inlineConstraint = $matches[2][$index][0] ?? null;
            $constraint = $inlineConstraint !== '' && $inlineConstraint !== null
                ? $inlineConstraint
                : ($constraints[$name] ?? '[^/]+');

            $this->assertSafeRouteConstraint($constraint);
            $pattern .= preg_quote(substr($path, $offset, $position - $offset), '#');
            $pattern .= '(' . $constraint . ')';
            $offset = $position + strlen($placeholder);
        }

        $pattern .= preg_quote(substr($path, $offset), '#');

        return $pattern;
    }

    private function assertSafeRouteConstraint(string $constraint): void
    {
        if ($constraint === '' || strlen($constraint) > 128) {
            throw new \InvalidArgumentException('Invalid route constraint.');
        }

        if (str_contains($constraint, '#') || str_contains($constraint, "\n") || str_contains($constraint, "\0")) {
            throw new \InvalidArgumentException('Invalid route constraint.');
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    private function routeTypes(array $options): array
    {
        $types = $options['types'] ?? [];

        return is_array($types) ? array_filter($types, 'is_string') : [];
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $types
     * @return array<string, mixed>
     */
    private function castRouteParams(array $params, array $types): array
    {
        $casted = [];

        foreach ($params as $name => $value) {
            $type = $types[$name] ?? 'string';
            $casted[$name] = match ($type) {
                'int' => $this->castIntParam($name, $value),
                'uuid' => $this->castUuidParam($name, $value),
                'slug' => $this->castSlugParam($name, $value),
                'string' => $value,
                default => throw new \InvalidArgumentException("Unsupported route parameter type [{$type}]."),
            };
        }

        return $casted;
    }

    private function castIntParam(string $name, string $value): int
    {
        if (!preg_match('/^(?:0|[1-9][0-9]*)$/', $value)) {
            throw new \InvalidArgumentException("Invalid integer route parameter [{$name}].");
        }

        return (int) $value;
    }

    private function castUuidParam(string $name, string $value): string
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException("Invalid UUID route parameter [{$name}].");
        }

        return strtolower($value);
    }

    private function castSlugParam(string $name, string $value): string
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new \InvalidArgumentException("Invalid slug route parameter [{$name}].");
        }

        return $value;
    }

    private function serveClient(string $uri): bool
    {
        if ($this->resolvedClientPath === null) {
            return false;
        }

        $normalizedUri = $this->normalizeClientUri($uri);
        if ($normalizedUri === null) {
            $this->sendError(403, 'Forbidden');
            return true;
        }

        $filePath = $this->resolvedClientPath . $normalizedUri;

        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, '/') . '/' . $this->clientConfig['fallback_index'];
        }

        $realFilePath = realpath($filePath);

        if ($realFilePath && str_starts_with($realFilePath, $this->resolvedClientPath) && is_file($realFilePath)) {
            $this->serveFile($realFilePath);
            return true;
        }

        if ($this->resolvedFallbackPath !== null && is_file($this->resolvedFallbackPath)) {
            $this->serveFile($this->resolvedFallbackPath);
            return true;
        }

        return false;
    }

    private function normalizeClientUri(string $uri): ?string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path)) {
            return null;
        }

        $decoded = rawurldecode($path);

        if (str_contains($decoded, "\0")) {
            return null;
        }

        $decoded = str_replace('\\', '/', $decoded);
        $decoded = ltrim($decoded, '/');

        if ($decoded === '') {
            return $this->clientConfig['fallback_index'];
        }

        if ($decoded === '..' || str_contains($decoded, '../') || str_contains($decoded, '/..')) {
            return null;
        }

        foreach (explode('/', $decoded) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
                return null;
            }
        }

        return $decoded;
    }

    private function serveFile(string $filePath): void
    {
        $fileName = basename($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // 🔐 Security: only serve known static asset types; block code, backups and dotfiles.
        $blockedExtensions = ['php', 'bak', 'sql', 'log', 'old', 'save'];
        if (!array_key_exists($extension, self::MIME_TYPES) ||
            in_array($extension, $blockedExtensions, true) ||
            str_contains($fileName, '.php.') ||
            str_starts_with($fileName, '.')) {
            $this->sendError(403, 'Forbidden');
            return;
        }

        $stat = stat($filePath);
        if ($stat === false) {
            $this->sendError(404, 'Not Found');
            return;
        }

        $lastModified = $stat['mtime'];
        $fileSize = $stat['size'];
        $etag = sprintf('"%x-%x"', $lastModified, $fileSize);

        if (!headers_sent()) {
            header('Cache-Control: public, max-age=3600');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

            $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            if ($ifNoneMatch !== '' && trim($ifNoneMatch, '" ') === trim($etag, '" ')) {
                http_response_code(304);
                return;
            }

            $contentType = self::MIME_TYPES[$extension] ?? null;

            if ($contentType === null && class_exists('finfo')) {
                self::$finfo ??= new \finfo(FILEINFO_MIME_TYPE);
                $contentType = self::$finfo->file($filePath) ?: null;
            }

            if ($contentType === null) {
                $contentType = 'application/octet-stream';
            }

            header('Content-Type: ' . $contentType);
            header('Content-Length: ' . $fileSize);

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
                return;
            }
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }

        readfile($filePath);
    }

    private function resolveControllerFqn(string $controller): string
    {
        if (str_starts_with($controller, '\\') ||
            str_starts_with($controller, 'OverPHP\\') ||
            str_starts_with($controller, $this->controllerNamespace . '\\')) {
            return $controller;
        }

        return $this->controllerNamespace . '\\' . $controller;
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

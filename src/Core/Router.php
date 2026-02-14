<?php

declare(strict_types=1);

namespace OverPHP\Core;

final class Router
{
    /** @var array<int,array{method:string,path:string,handler:mixed}> */
    private array $routes = [];

    private string $controllerNamespace;
    private string $prefix;

    public function __construct(string $controllerNamespace = 'OverPHP\\Controllers', string $prefix = '/api')
    {
        $this->controllerNamespace = $controllerNamespace;
        $this->prefix = $prefix;
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
        $uri = str_replace($this->prefix, '', $uri);
        $uri = explode('?', $uri)[0];
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

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

            echo json_encode($output);
            return;
        }

        if (is_string($response)) {
            echo $response;
        }
    }
}

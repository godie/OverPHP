<?php

declare(strict_types=1);

namespace OverPHP\Helpers;

/**
 * @param array<int,string> $allowedOrigins
 * @param array<int,string> $allowedMethods
 * @param array<int,string> $allowedHeaders
 */
function corsSendHeaders(
    array $allowedOrigins,
    bool $allowCredentials = true,
    array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    array $allowedHeaders = ['Content-Type', 'Authorization', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN', 'X-Requested-With']
): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $origin = is_string($origin) ? $origin : '';

    if ($allowCredentials && in_array('*', $allowedOrigins, true)) {
        throw new \RuntimeException('Wildcard CORS origin cannot be used with credentials.');
    }

    $normalizedAllowed = array_map(
        static fn (string $value): string => rtrim(strtolower($value), '/'),
        $allowedOrigins
    );
    $normalizedOrigin = rtrim(strtolower($origin), '/');
    $allowed = $origin !== '' && in_array($normalizedOrigin, $normalizedAllowed, true);

    if ($allowed) {
        header("Access-Control-Allow-Origin: {$origin}");

        if ($allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
    header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
    header('Access-Control-Max-Age: 600');
    header('Vary: Origin', false);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code($allowed ? 200 : 403);
        return true;
    }

    return false;
}

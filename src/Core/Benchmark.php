<?php

declare(strict_types=1);

namespace OverPHP\Core;

final class Benchmark
{
    private static float $startTime = 0.0;
    private static int $startMemory = 0;
    private static bool $enabled = false;
    private static string $environment = 'production';

    public static function start(bool $enabled = false, ?string $environment = null): void
    {
        self::$environment = strtolower((string) ($environment ?? getenv('APP_ENV') ?: 'testing'));
        self::$enabled = $enabled && self::canExpose();

        if (!self::$enabled) {
            return;
        }

        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage();
    }

    /** @return array{time:string,memory:string,peak:string}|null */
    public static function stats(): ?array
    {
        if (!self::$enabled) {
            return null;
        }

        return [
            'time' => round((microtime(true) - self::$startTime) * 1000, 3) . 'ms',
            'memory' => round((memory_get_usage() - self::$startMemory) / 1024, 2) . 'KB',
            'peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB',
        ];
    }

    public static function canExpose(): bool
    {
        return in_array(self::$environment, ['local', 'development', 'dev', 'testing', 'test'], true);
    }
}

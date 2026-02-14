<?php

declare(strict_types=1);

namespace OverPHP\Libs;

use OverPHP\Libs\Drivers\DriverInterface;
use OverPHP\Libs\Drivers\MysqlDriver;
use OverPHP\Libs\Drivers\SqliteDriver;

final class Database
{
    /** @var array<string, DriverInterface> */
    private static array $drivers = [];

    private static ?\PDO $connection = null;
    private static ?string $lastError = null;
    private static bool $builtInsLoaded = false;

    // ── Driver registry ──────────────────────────────────────────

    /**
     * Register a custom driver (or override a built-in one).
     */
    public static function registerDriver(DriverInterface $driver): void
    {
        self::$drivers[$driver->getName()] = $driver;
    }

    /**
     * Return the names of all registered drivers.
     *
     * @return string[]
     */
    public static function availableDrivers(): array
    {
        self::loadBuiltInDrivers();

        return array_keys(self::$drivers);
    }

    // ── Connection ───────────────────────────────────────────────

    public static function isEnabled(array $config): bool
    {
        $dbConfig = $config['database'] ?? [];
        return !empty($dbConfig['enabled']);
    }

    public static function getConnection(array $config): ?\PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        if (!self::isEnabled($config)) {
            self::$lastError = 'Database layer is disabled in the configuration.';
            return null;
        }

        self::loadBuiltInDrivers();

        $dbConfig = $config['database'] ?? [];
        $driverName = strtolower((string) ($dbConfig['driver'] ?? 'mysql'));

        $driver = self::$drivers[$driverName] ?? null;

        if ($driver === null) {
            self::$lastError = sprintf(
                "Driver '%s' is not registered. Available: %s.",
                $driverName,
                implode(', ', array_keys(self::$drivers))
            );
            return null;
        }

        $driverConfig = $dbConfig[$driverName] ?? [];

        try {
            self::$connection = $driver->connect($driverConfig);
            self::$lastError = null;
            return self::$connection;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            self::$connection = null;
            return null;
        }
    }

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    // ── Internal ─────────────────────────────────────────────────

    /**
     * Load the drivers that ship with the framework (once).
     */
    private static function loadBuiltInDrivers(): void
    {
        if (self::$builtInsLoaded) {
            return;
        }

        self::$builtInsLoaded = true;

        $builtIn = [
            new MysqlDriver(),
            new SqliteDriver(),
        ];

        foreach ($builtIn as $driver) {
            // Don't overwrite a user-registered driver with the same name
            if (!isset(self::$drivers[$driver->getName()])) {
                self::$drivers[$driver->getName()] = $driver;
            }
        }
    }

    /**
     * Reset internal state. Useful for testing.
     */
    public static function reset(): void
    {
        self::$connection = null;
        self::$lastError = null;
        self::$drivers = [];
        self::$builtInsLoaded = false;
    }
}

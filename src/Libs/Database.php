<?php

declare(strict_types=1);

namespace OverPHP\Libs;

use OverPHP\Libs\Drivers\DriverInterface;
use OverPHP\Libs\Drivers\MysqlDriver;
use OverPHP\Libs\Drivers\SqliteDriver;

final class Database
{
    /** @var array<string, DriverInterface> */
    private array $drivers = [];

    private ?\PDO $connection = null;
    private ?string $lastError = null;
    private bool $builtInsLoaded = false;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    // ── Driver registry ──────────────────────────────────────────

    /**
     * Register a custom driver (or override a built-in one).
     */
    public function registerDriver(DriverInterface $driver): void
    {
        $this->drivers[$driver->getName()] = $driver;
    }

    /**
     * Return the names of all registered drivers.
     *
     * @return string[]
     */
    public function availableDrivers(): array
    {
        $this->loadBuiltInDrivers();

        return array_keys($this->drivers);
    }

    // ── Connection ───────────────────────────────────────────────

    public function isEnabled(): bool
    {
        $dbConfig = $this->config['database'] ?? [];
        return !empty($dbConfig['enabled']);
    }

    public function getConnection(): ?\PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        if (!$this->isEnabled()) {
            $this->lastError = 'Database layer is disabled in the configuration.';
            return null;
        }

        $this->loadBuiltInDrivers();

        $dbConfig = $this->config['database'] ?? [];
        $driverName = strtolower((string) ($dbConfig['driver'] ?? 'mysql'));

        $driver = $this->drivers[$driverName] ?? null;

        if ($driver === null) {
            $this->lastError = sprintf(
                "Driver '%s' is not registered. Available: %s.",
                $driverName,
                implode(', ', array_keys($this->drivers))
            );
            return null;
        }

        $driverConfig = $dbConfig[$driverName] ?? [];

        try {
            $this->connection = $driver->connect($driverConfig);
            $this->lastError = null;
            return $this->connection;
        } catch (\PDOException $e) {
            $this->lastError = 'Database connection failed. Please check your configuration and logs.';
            error_log('Database Connection Error: ' . $e->getMessage());
            $this->connection = null;
            return null;
        } catch (\Throwable $e) {
            $this->lastError = 'An unexpected error occurred in the database layer.';
            error_log('Unexpected Database Error: ' . $e->getMessage());
            $this->connection = null;
            return null;
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    // ── Internal ─────────────────────────────────────────────────

    /**
     * Load the drivers that ship with the framework (once).
     */
    private function loadBuiltInDrivers(): void
    {
        if ($this->builtInsLoaded) {
            return;
        }

        $this->builtInsLoaded = true;

        $builtIn = [
            new MysqlDriver(),
            new SqliteDriver(),
        ];

        foreach ($builtIn as $driver) {
            if (!isset($this->drivers[$driver->getName()])) {
                $this->drivers[$driver->getName()] = $driver;
            }
        }
    }
}

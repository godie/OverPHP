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

    /** @var array<string, class-string<DriverInterface>> */
    private array $driverMap = [
        'mysql'  => MysqlDriver::class,
        'sqlite' => SqliteDriver::class,
    ];

    private ?\PDO $connection = null;
    private ?string $lastError = null;

    public function __construct(
        private readonly array $config = []
    ) {}

    // ── Driver registry ──────────────────────────────────────────

    /**
     * Register a custom driver (or override a built-in one).
     */
    public function registerDriver(DriverInterface $driver): void
    {
        $this->drivers[strtolower($driver->getName())] = $driver;
    }

    /**
     * Return the names of all registered drivers.
     *
     * @return string[]
     */
    public function availableDrivers(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->driverMap),
            array_keys($this->drivers)
        )));
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

        $dbConfig = $this->config['database'] ?? [];
        $driverName = strtolower((string) ($dbConfig['driver'] ?? 'mysql'));

        $driver = $this->resolveDriver($driverName);

        if ($driver === null) {
            $this->lastError = sprintf(
                "Driver '%s' is not registered. Available: %s.",
                $driverName,
                implode(', ', $this->availableDrivers())
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

    /**
     * Execute a SELECT query using prepared statements.
     *
     * @param array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->prepareAndExecute($sql, $params)->fetchAll();
    }

    /**
     * Execute a SELECT query and return a single row.
     *
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->prepareAndExecute($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Execute a mutating SQL statement using prepared statements.
     *
     * @param array<string|int, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->prepareAndExecute($sql, $params)->rowCount();
    }

    // ── Internal ─────────────────────────────────────────────────

    /**
     * Resolve a driver by name, instantiating it if necessary.
     */
    private function resolveDriver(string $name): ?DriverInterface
    {
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        if (isset($this->driverMap[$name])) {
            $class = $this->driverMap[$name];
            $this->drivers[$name] = new $class();
            return $this->drivers[$name];
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    private function prepareAndExecute(string $sql, array $params): \PDOStatement
    {
        $connection = $this->getConnection();

        if ($connection === null) {
            throw new \RuntimeException($this->lastError ?? 'Database connection is not available.');
        }

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            throw new \RuntimeException('Unable to prepare SQL statement.');
        }

        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $statement->bindValue($parameter, $value, $this->pdoType($value));
        }

        $statement->execute();

        return $statement;
    }

    private function pdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => \PDO::PARAM_INT,
            is_bool($value) => \PDO::PARAM_BOOL,
            $value === null => \PDO::PARAM_NULL,
            default => \PDO::PARAM_STR,
        };
    }
}

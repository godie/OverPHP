<?php

declare(strict_types=1);

namespace OverPHP\Libs\Drivers;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Base class with shared logic for database drivers.
 *
 * Extend this instead of implementing DriverInterface directly
 * to get sensible PDO defaults for free.
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * Merge user-provided PDO options with framework defaults.
     *
     * @param array $overrides  User-provided PDO attribute overrides.
     * @return array
     */
    protected function mergeOptions(array $overrides): array
    {
        $defaults = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        return $overrides + $defaults;
    }

    /**
     * Execute SQL safely using PDO prepared statements.
     *
     * @param array<string|int, mixed> $params
     */
    public function prepareAndExecute(PDO $pdo, string $sql, array $params = []): PDOStatement
    {
        $statement = $pdo->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare SQL statement.');
        }

        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $statement->bindValue($parameter, $value, $this->pdoType($value));
        }

        try {
            $statement->execute();
        } catch (PDOException $exception) {
            throw new RuntimeException('Database query failed.', 0, $exception);
        }

        return $statement;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function select(PDO $pdo, string $sql, array $params = []): array
    {
        return $this->prepareAndExecute($pdo, $sql, $params)->fetchAll();
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function selectOne(PDO $pdo, string $sql, array $params = []): ?array
    {
        $row = $this->prepareAndExecute($pdo, $sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function execute(PDO $pdo, string $sql, array $params = []): int
    {
        return $this->prepareAndExecute($pdo, $sql, $params)->rowCount();
    }

    private function pdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * @param list<string> $allowed
     */
    protected function assertAllowedIdentifier(string $identifier, array $allowed): string
    {
        if (!in_array($identifier, $allowed, true)) {
            throw new RuntimeException('Invalid SQL identifier.');
        }

        return $identifier;
    }
}

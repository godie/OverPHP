<?php

declare(strict_types=1);

namespace OverPHP\Helpers;

use OverPHP\Libs\Database;

function databaseIsEnabled(array $config): bool
{
    return Database::isEnabled($config);
}

function databaseConnection(array $config): ?\PDO
{
    return Database::getConnection($config);
}

function databaseLastError(): ?string
{
    return Database::getLastError();
}

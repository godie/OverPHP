<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Libs\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testConnectionReturnsNullWhenDisabled(): void
    {
        $config = [
            'database' => [
                'enabled' => false,
            ],
        ];

        $this->assertNull(Database::getConnection($config));
        $this->assertSame('Database layer is disabled in the configuration.', Database::getLastError());
    }
}

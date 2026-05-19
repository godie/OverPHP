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

        $db = new Database($config);
        $this->assertNull($db->getConnection());
        $this->assertSame('Database layer is disabled in the configuration.', $db->getLastError());
    }

    public function testPreparedStatementsBindParametersSafely(): void
    {
        $db = new Database([
            'database' => [
                'enabled' => true,
                'driver' => 'sqlite',
                'sqlite' => [
                    'path' => ':memory:',
                ],
            ],
        ]);

        $db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
        $db->execute('INSERT INTO users (email) VALUES (:email)', [
            'email' => "attacker@example.com' OR 1=1 --",
        ]);

        $row = $db->selectOne('SELECT id, email FROM users WHERE email = :email', [
            'email' => "attacker@example.com' OR 1=1 --",
        ]);

        $this->assertIsArray($row);
        $this->assertSame("attacker@example.com' OR 1=1 --", $row['email']);
        $this->assertSame([], $db->select('SELECT id FROM users WHERE email = :email', [
            'email' => 'attacker@example.com',
        ]));
    }
}

<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Benchmark;
use PHPUnit\Framework\TestCase;

final class BenchmarkTest extends TestCase
{
    public function testStatsReturnNullWhenDisabled(): void
    {
        Benchmark::start(false);
        $this->assertNull(Benchmark::stats());
    }

    public function testStatsReturnValuesWhenEnabled(): void
    {
        Benchmark::start(true);
        $stats = Benchmark::stats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('time', $stats);
        $this->assertArrayHasKey('memory', $stats);
        $this->assertArrayHasKey('peak', $stats);
    }
}

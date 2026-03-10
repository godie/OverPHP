<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Container;
use PHPUnit\Framework\TestCase;

class CachedService {}

final class ContainerCachingTest extends TestCase
{
    public function testContainerCachesAutoWiredInstancesByDefault(): void
    {
        $container = new Container();

        $instance1 = $container->make(CachedService::class);
        $instance2 = $container->make(CachedService::class);

        $this->assertSame($instance1, $instance2, 'Auto-wired instances should be cached by default for performance.');
    }

    public function testContainerDoesNotCacheExplicitlyNonSharedBindings(): void
    {
        $container = new Container();
        $container->bind(CachedService::class, CachedService::class, false);

        $instance1 = $container->make(CachedService::class);
        $instance2 = $container->make(CachedService::class);

        $this->assertNotSame($instance1, $instance2, 'Explicitly non-shared bindings should not be cached.');
    }
}

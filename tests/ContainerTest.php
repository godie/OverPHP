<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Container;
use PHPUnit\Framework\TestCase;

class ServiceA {}
class ServiceB {
    public function __construct(public ServiceA $a) {}
}

final class ContainerTest extends TestCase
{
    public function testContainerResolvesClasses(): void
    {
        $container = new Container();
        $instance = $container->make(ServiceA::class);
        $this->assertInstanceOf(ServiceA::class, $instance);
    }

    public function testContainerResolvesDependencies(): void
    {
        $container = new Container();
        $instance = $container->make(ServiceB::class);
        $this->assertInstanceOf(ServiceB::class, $instance);
        $this->assertInstanceOf(ServiceA::class, $instance->a);
    }

    public function testContainerSingletons(): void
    {
        $container = new Container();
        $container->singleton(ServiceA::class, fn() => new ServiceA());

        $instance1 = $container->make(ServiceA::class);
        $instance2 = $container->make(ServiceA::class);

        $this->assertSame($instance1, $instance2);
    }
}

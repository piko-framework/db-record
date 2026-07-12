<?php
declare(strict_types=1);

namespace Piko\Tests\Infrastructure;

use PDO;
use League\Container\Container;
use League\Container\ReflectionContainer;
use Psr\Container\ContainerInterface;

class TestContext
{
    private static ?ContainerInterface $container = null;

    public static function getContainer(): ?ContainerInterface
    {
        return static::$container;
    }

    public static function setUpBeforeAllTests(PDO $pdo): void
    {
        $container = new Container();
        $container->addShared(PDO::class, $pdo);
        $container->delegate(new ReflectionContainer()); // Activate autowiring

        static::$container = $container;
    }
}

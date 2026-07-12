<?php
declare(strict_types=1);

namespace Piko\Tests\Infrastructure;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use function file_exists;

final class DatabaseExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters
    ): void {
        $params = $_ENV;

        // Try to load env from env.php
        $envFile = realpath(__DIR__ . '/../../env.php');

        if ($envFile !== false) {
            $customEnv = file_exists($envFile) ? require $envFile : [];
            $params = array_replace($_ENV, $customEnv);
        }

        $facade->registerSubscriber(new StartedSubscriber($params));
        $facade->registerSubscriber(new FinishedSubscriber());
    }
}

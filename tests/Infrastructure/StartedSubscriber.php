<?php

declare(strict_types=1);

namespace Piko\Tests\Infrastructure;

use PDO;
use InvalidArgumentException;
use PHPUnit\Event\TestRunner\Started;
use PHPUnit\Event\TestRunner\StartedSubscriber as PHPUnitStartedSubscriber;

final class StartedSubscriber implements PHPUnitStartedSubscriber
{
    public function __construct(private array $params)
    {

    }

    public function notify(Started $event): void
    {
        $driver = $this->params['DB_DRIVER'] ?? 'mysql';

        $pdo = match($driver) {
            'sqlite' => $this->getSqlitePdo(),
            'mysql' => $this->getMysqlPdo(),
            'mssql' => $this->getMsSqlPdo(),
            'pgsql' => $this->getPostgresPdo(),
            default => throw new InvalidArgumentException('Unknown database driver')
        };

        // setup global
        TestContext::setUpBeforeAllTests($pdo);
    }

    private function getSqlitePdo(): PDO
    {
        return new PDO('sqlite:' . sys_get_temp_dir() . '/test_post.sqlite');
    }

    private function getMysqlPdo(): PDO
    {
        $host = $this->params['MYSQL_HOST'] ?? '127.0.0.1';
        $database = $this->params['DATABASE_NAME'] ?? 'test';
        $user = $this->params['MYSQL_USER'] ?? 'root';
        $password = $this->params['MYSQL_PASSWORD'] ?? 'root';
        $db = new PDO('mysql:host=' . $host, $user, $password);
        $db->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '`');
        $dsn = 'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4';

        return new PDO($dsn, $user, $password);
    }

    private function getMsSqlPdo(): PDO
    {
        $host = $this->params['MSSQL_HOST'] ?? '127.0.0.1';
        $database = $this->params['DATABASE_NAME'] ?? 'test';
        $user = $this->params['MSSQL_USER'] ?? 'sa';
        $password = $this->params['MSSQL_PASSWORD'] ?? 'pass';
        $db = new PDO('dblib:host=' . $host, $user, $password);
        $db->exec("IF DB_ID('{$database}') IS NULL CREATE DATABASE {$database}");


        return new PDO("dblib:host={$host};dbname={$database}", $user, $password);
    }

    private function getPostgresPdo(): PDO
    {
        $host = $this->params['POSTGRESQL_HOST'] ?? '127.0.0.1';
        $database = $this->params['DATABASE_NAME'] ?? 'test';
        $user = $this->params['POSTGRESQL_USER'] ?? 'root';
        $password = $this->params['POSTGRESQL_PASSWORD'] ?? 'root';
        $db = new PDO('pgsql:host=' . $host, $user, $password);

        // Check if the database already exists
        $result = $db->query("SELECT 1 FROM pg_database WHERE datname='{$database}'");

        if ($result->fetchColumn() === false) {
            // Database does not exist, create it
            $db->exec("CREATE DATABASE {$database}");
        }

        return new PDO("pgsql:host={$host};dbname={$database}", $user, $password);
    }
}

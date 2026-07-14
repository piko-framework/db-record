<?php

declare(strict_types=1);

namespace Piko\Tests;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Piko\DbRecord;
use Piko\DbRecord\Exception\PersistenceException;

final class DbRecordErrorHandlingTest extends TestCase
{
    public function testExistsWrapsPrepareThrowableInPersistenceException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('sqlite');
        $pdo->method('prepare')->willThrowException(new \RuntimeException('prepare exploded'));

        $record = $this->createRecord($pdo);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Failed to prepare SQL statement during exists: prepare exploded');

        $record->exists(1);
    }

    public function testExistsThrowsPersistenceExceptionWhenPrepareReturnsFalse(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('sqlite');
        $pdo->method('prepare')->willReturn(false);
        $pdo->method('errorInfo')->willReturn(['HY000', 1, 'prepare failed']);

        $record = $this->createRecord($pdo);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Failed to prepare SQL statement during exists: prepare failed');

        $record->exists(1);
    }

    public function testExistsThrowsPersistenceExceptionWhenExecuteReturnsFalse(): void
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('bindValue')->willReturn(true);
        $statement->method('execute')->willReturn(false);
        $statement->method('errorInfo')->willReturn(['HY000', 1, 'execution failed']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('sqlite');
        $pdo->method('prepare')->willReturn($statement);

        $record = $this->createRecord($pdo);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Failed to execute SQL statement during exists: execution failed');

        $record->exists(1);
    }

    private function createRecord(PDO $pdo): DbRecord
    {
        return new class($pdo) extends DbRecord {
            protected string $tableName = 'contact';
            protected array $schema = ['id' => DbRecord::TYPE_INT];
            protected string $primaryKey = 'id';
        };
    }
}

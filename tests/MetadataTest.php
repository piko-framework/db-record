<?php

declare(strict_types=1);

namespace Piko\Tests;

use PHPUnit\Framework\TestCase;
use Piko\DbRecord;
use Piko\DbRecord\Exception\SchemaException;
use Piko\DbRecord\Metadata;

final class MetadataTest extends TestCase
{
    public function testGetCastTypeForColumnThrowsWhenColumnNotInSchema(): void
    {
        $metadata = new Metadata(
            tableName: 'contact',
            schema: ['id' => DbRecord::TYPE_INT],
            castTypes: [],
            decimalScales: [],
            primaryKey: 'id',
            columnToProperty: [],
            propertyToColumn: []
        );

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('unknown_column is not in the table schema.');

        $metadata->getCastTypeForColumn('unknown_column');
    }

    public function testGetDecimalScaleForColumnThrowsWhenColumnNotInSchema(): void
    {
        $metadata = new Metadata(
            tableName: 'contact',
            schema: ['id' => DbRecord::TYPE_INT],
            castTypes: [],
            decimalScales: [],
            primaryKey: 'id',
            columnToProperty: [],
            propertyToColumn: []
        );

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('unknown_column is not in the table schema.');

        $metadata->getDecimalScaleForColumn('unknown_column');
    }
}

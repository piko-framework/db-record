<?php

/**
 * This file is part of Piko DbRecord - Web micro framework
 *
 * @copyright 2019-2026 Sylvain PHILIP
 * @license LGPL-3.0; see LICENSE.txt
 * @link https://github.com/piko-framework/db-record
 */

declare(strict_types=1);

namespace Piko\DbRecord\Metadata;

use ReflectionClass;
use ReflectionNamedType;
use InvalidArgumentException;
use Piko\DbRecord as ActiveRecord;
use Piko\DbRecord\Metadata;
use Piko\DbRecord\Attribute\Column;
use Piko\DbRecord\Attribute\Table;
use Piko\DbRecord\Exception\SchemaException;

/**
 * Resolves and caches metadata for DbRecord models.
 *
 * @author Sylvain PHILIP <contact@sphilip.com>
 */
final class MetadataResolver
{
    /**
     * @var array<class-string, Metadata>
     */
    private static array $cache = [];

    /**
     * @param class-string $className
     * @param array<string, int> $schema
     */
    public function resolve(
        string $className,
        string $tableName,
        array $schema,
        string $primaryKey
    ): Metadata {
        $castTypes = [];
        $decimalScales = [];
        $columnToProperty = [];
        $propertyToColumn = [];
        if (isset(self::$cache[$className])) {
            return self::$cache[$className];
        }

        $reflectionClass = new ReflectionClass($className);
        $tableAttribute = $reflectionClass->getAttributes(Table::class)[0] ?? null;

        if ($tableAttribute) {
            $tableName = $tableAttribute->newInstance()->name;
        }

        foreach ($reflectionClass->getProperties() as $property) {
            $columnAttribute = $property->getAttributes(Column::class)[0] ?? null;

            if (!$columnAttribute) {
                continue;
            }

            $columnInstance = $columnAttribute->newInstance();
            $propertyName = $property->getName();
            $fieldName = $columnInstance->name ?? $propertyName;

            $propertyType = $property->getType();
            $type = $propertyType instanceof ReflectionNamedType ? $propertyType->getName() : 'string';
            $castType = $this->normalizeCastType($columnInstance->type ?? $type);

            $schema[$fieldName] = $this->getSchemaType($castType);
            $castTypes[$fieldName] = $castType;

            if ($castType === 'decimal' && $columnInstance->scale !== null) {
                if ($columnInstance->scale < 0) {
                    throw new InvalidArgumentException('Decimal scale must be greater than or equal to 0.');
                }

                $decimalScales[$fieldName] = $columnInstance->scale;
            }

            $columnToProperty[$fieldName] = $propertyName;
            $propertyToColumn[$propertyName] = $fieldName;

            if ($columnInstance->primaryKey) {
                $primaryKey = $fieldName;
            }
        }

        foreach (array_keys($schema) as $columnName) {
            $propertyName = $columnToProperty[$columnName] ?? $columnName;
            $columnToProperty[$columnName] = $propertyName;
            $propertyToColumn[$propertyName] = $columnName;
        }

        $metadata = new Metadata(
            $tableName,
            $schema,
            $castTypes,
            $decimalScales,
            $primaryKey,
            $columnToProperty,
            $propertyToColumn
        );

        self::$cache[$className] = $metadata;

        return $metadata;
    }

    private function normalizeCastType(string $type): string
    {
        $normalized = ltrim(strtolower($type), '\\');

        return match ($normalized) {
            'int', 'integer' => 'int',
            'string' => 'string',
            'bool', 'boolean' => 'bool',
            'float', 'double' => 'float',
            'decimal' => 'decimal',
            'datetime', 'datetime_immutable', 'datetimeimmutable', 'datetimeinterface' => 'datetime_immutable',
            'datetime_mutable', 'datetimemutable' => 'datetime_mutable',
            'json', 'array' => 'json',
            default => throw new SchemaException("Unsupported type: $type"),
        };
    }

    private function getSchemaType(string $type): int
    {
        return match ($type) {
            'int' => ActiveRecord::TYPE_INT,
            'bool' => ActiveRecord::TYPE_BOOL,
            default => ActiveRecord::TYPE_STRING,
        };
    }
}

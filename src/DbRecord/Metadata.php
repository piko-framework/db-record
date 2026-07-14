<?php

/**
 * This file is part of Piko DbRecord - Web micro framework
 *
 * @copyright 2019-2024 Sylvain PHILIP
 * @license LGPL-3.0; see LICENSE.txt
 * @link https://github.com/piko-framework/db-record
 */

declare(strict_types=1);

namespace Piko\DbRecord;

use Piko\DbRecord as ActiveRecord;
use Piko\DbRecord\Exception\SchemaException;

/**
 * Immutable model metadata resolved from attributes and legacy schema.
 *
 * @author Sylvain PHILIP <contact@sphilip.com>
 */
final class Metadata
{
    /**
     * @param array<string, int> $schema
     * @param array<string, string> $castTypes
     * @param array<string, int> $decimalScales
     * @param array<string, string> $columnToProperty
     * @param array<string, string> $propertyToColumn
     */
    public function __construct(
        public string $tableName,
        public array $schema,
        public array $castTypes,
        public array $decimalScales,
        public string $primaryKey,
        public array $columnToProperty,
        public array $propertyToColumn
    ) {
    }

    /**
     * Resolve column name from a column or mapped property name.
     */
    public function resolveColumnName(string $name): string
    {
        if (isset($this->schema[$name])) {
            return $name;
        }

        if (isset($this->propertyToColumn[$name])) {
            return $this->propertyToColumn[$name];
        }

        throw new SchemaException("$name is not in the table schema.");
    }

    /**
     * Resolve mapped property name from a schema column.
     */
    public function getPropertyName(string $column): string
    {
        return $this->columnToProperty[$column] ?? $column;
    }

    /**
     * Resolve cast type from column metadata.
     */
    public function getCastTypeForColumn(string $column): string
    {
        if (!isset($this->schema[$column])) {
            throw new SchemaException("$column is not in the table schema.");
        }

        if (isset($this->castTypes[$column])) {
            return $this->castTypes[$column];
        }

        return match ($this->schema[$column]) {
            ActiveRecord::TYPE_INT => 'int',
            ActiveRecord::TYPE_BOOL => 'bool',
            default => 'string',
        };
    }

    /**
     * Resolve decimal scale from column metadata.
     */
    public function getDecimalScaleForColumn(string $column): ?int
    {
        if (!isset($this->schema[$column])) {
            throw new SchemaException("$column is not in the table schema.");
        }

        return $this->decimalScales[$column] ?? null;
    }

    /**
     * Check whether a name exists as schema column or mapped property.
     */
    public function hasAttribute(string $name): bool
    {
        return isset($this->schema[$name]) || isset($this->propertyToColumn[$name]);
    }
}

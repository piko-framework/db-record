<?php

/**
 * This file is part of Piko DbRecord - Web micro framework
 *
 * @copyright 2019-2024 Sylvain PHILIP
 * @license LGPL-3.0; see LICENSE.txt
 * @link https://github.com/piko-framework/db-record
 */

declare(strict_types=1);

namespace Piko;

use PDO;
use ReflectionClass;
use RuntimeException;
use ReflectionNamedType;
use InvalidArgumentException;
use Piko\DbRecord\Attribute\Table;
use Piko\DbRecord\Attribute\Column;
use Piko\DbRecord\Event\AfterSaveEvent;
use Piko\DbRecord\Event\BeforeSaveEvent;
use Piko\DbRecord\Event\AfterDeleteEvent;
use Piko\DbRecord\Event\BeforeDeleteEvent;

/**
 * DbRecord represents a database table's row and implements
 * the Active Record pattern.
 *
 * @author Sylvain PHILIP <contact@sphilip.com>
 */
abstract class DbRecord
{
    use EventHandlerTrait;
    use ModelTrait;

    public const TYPE_INT = PDO::PARAM_INT;
    public const TYPE_STRING = PDO::PARAM_STR;
    public const TYPE_BOOL = PDO::PARAM_BOOL;

    /**
     * Database connection.
     */
    protected PDO $db;

    /**
     * Database table name.
     */
    protected string $tableName = '';

    /**
     * Table schema as `column => PDO::PARAM_*`.
     *
     * @var array<string, int>
     */
    protected array $schema = [];

    /**
     * Primary key column name.
     */
    protected string $primaryKey = 'id';

    /**
     * Row data storage.
     *
     * @var array<string, string|int|bool|null>
     */
    protected array $data = [];

    /**
     * Map DB column names to model property names.
     *
     * @var array<string, string>
     */
    protected array $columnToProperty = [];

    /**
     * Map model property names to DB column names.
     *
     * @var array<string, string>
     */
    protected array $propertyToColumn = [];

    /**
     * Metadata cache indexed by model FQCN.
     *
     * @var array<class-string, array{tableName:string, schema:array<string, int>, primaryKey:string, columnToProperty:array<string, string>, propertyToColumn:array<string, string>}>
     */
    private static array $metadataCache = [];

    /**
     * Create a new record instance.
     *
     * @param PDO $db Database connection.
     *
     * @throws RuntimeException When table name, schema, or primary key is invalid.
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->initializeSchema();

        if (empty($this->tableName)) {
            throw new RuntimeException(
                "The table name is not defined." .
                " Ensure the class has a '@Table' attribute with a 'name' property."
            );
        }

        if (empty($this->schema)) {
            throw new RuntimeException(
                "No table schema defined." .
                " Ensure the class has properties annotated with '@Column' attributes."
            );
        }

        if (!isset($this->schema[$this->primaryKey])) {
            throw new RuntimeException("The primary key {$this->primaryKey} is not defined in the table schema");
        }
    }

    /**
     * Quote a table or column identifier according to the current PDO driver.
     *
     * @param string $identifier Raw table or column name.
     *
     * @return string Quoted identifier.
     *
     * @codeCoverageIgnore
     */
    public function quoteIdentifier($identifier): string
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'mysql', 'sqlite' => '`' . $identifier . '`',
            'pgsql' => '"' . $identifier . '"',
            'sqlsrv', 'dblib' => '[' . $identifier . ']',
            default => $identifier,
        };
    }

    /**
     * Return record attributes for all schema fields.
     *
     * @return array<string, mixed> Associative array of `column => value`.
     */
    protected function getAttributes(): array
    {
        $fields = array_keys($this->schema);

        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field] = $this->getColumnValue($field);
        }

        return $attributes;
    }

    /**
     * Resolve an attribute name to a schema column.
     *
     * @param string $name Column or property name.
     *
     * @return string Schema column name.
     *
     * @throws RuntimeException When the name cannot be resolved to a schema column.
     */
    protected function resolveColumnName(string $name): string
    {
        if (isset($this->schema[$name])) {
            return $name;
        }

        if (isset($this->propertyToColumn[$name])) {
            return $this->propertyToColumn[$name];
        }

        throw new RuntimeException("$name is not in the table schema.");
    }

    /**
     * Resolve a schema column to its mapped property name.
     */
    protected function getPropertyName(string $column): string
    {
        return $this->columnToProperty[$column] ?? $column;
    }

    /**
     * Read a value by schema column name.
     *
     * @param string $column Schema column name.
     *
     * @return mixed
     */
    protected function getColumnValue(string $column)
    {
        $propertyName = $this->getPropertyName($column);

        if ($propertyName !== $column && property_exists($this, $propertyName)) {
            return $this->{$propertyName};
        }

        return $this->{$column};
    }

    /**
     * Initialize table metadata from `#[Table]` and `#[Column]` attributes.
     *
     * If attributes are present, this method resolves table name, schema types,
     * and primary key definition through reflection.
     */
    protected function initializeSchema(): void
    {
        $className = static::class;

        if (isset(self::$metadataCache[$className])) {
            $metadata = self::$metadataCache[$className];
            $this->tableName = $metadata['tableName'];
            $this->schema = $metadata['schema'];
            $this->primaryKey = $metadata['primaryKey'];
            $this->columnToProperty = $metadata['columnToProperty'];
            $this->propertyToColumn = $metadata['propertyToColumn'];

            return;
        }

        $reflectionClass = new ReflectionClass($this);

        $tableAttribute = $reflectionClass->getAttributes(Table::class)[0] ?? null;

        if ($tableAttribute) {
            $this->tableName = $tableAttribute->newInstance()->name;
        }

        foreach ($reflectionClass->getProperties() as $property) {

            $fieldAttribute = $property->getAttributes(Column::class)[0] ?? null;

            if ($fieldAttribute) {
                $fieldInstance = $fieldAttribute->newInstance();
                $propertyName = $property->getName();
                $fieldName = $fieldInstance->name ?? $propertyName;
                $propertyType = $property->getType();
                // Default type to string if no type is declared
                $type = $propertyType instanceof ReflectionNamedType ? $propertyType->getName() : 'string';

                $this->schema[$fieldName] = $this->getSchemaType($type);
                $this->columnToProperty[$fieldName] = $propertyName;
                $this->propertyToColumn[$propertyName] = $fieldName;

                if ($fieldInstance->primaryKey) {
                    $this->primaryKey = $fieldName;
                }
            }
        }

        foreach (array_keys($this->schema) as $columnName) {
            $propertyName = $this->columnToProperty[$columnName] ?? $columnName;
            $this->columnToProperty[$columnName] = $propertyName;
            $this->propertyToColumn[$propertyName] = $columnName;
        }

        self::$metadataCache[$className] = [
            'tableName' => $this->tableName,
            'schema' => $this->schema,
            'primaryKey' => $this->primaryKey,
            'columnToProperty' => $this->columnToProperty,
            'propertyToColumn' => $this->propertyToColumn,
        ];
    }

    /**
     * Map a PHP property type to a PDO parameter type.
     *
     * @param string $type PHP type name.
     *
     * @return int One of the `PDO::PARAM_*` constants.
     *
     * @throws InvalidArgumentException When the type is not supported.
     */
    private function getSchemaType(string $type): int
    {
        return match ($type) {
            'int' => self::TYPE_INT,
            'string' => self::TYPE_STRING,
            'bool' => self::TYPE_BOOL,
            'float' => self::TYPE_STRING,
            default => throw new InvalidArgumentException("Unsupported type: $type"),
        };
    }

    /**
     * Resolve the PDO parameter type for a value.
     *
     * @param int $schemaType One of the `PDO::PARAM_*` constants.
     * @param mixed $value Value to bind.
     *
     * @return int One of the `PDO::PARAM_*` constants.
     */
    private function getParameterType(int $schemaType, mixed $value): int
    {
        if ($value === null) {
            return PDO::PARAM_NULL;
        }

        return $schemaType;
    }

    /**
     * Magic getter for schema-defined attributes.
     *
     * @param string $attribute Attribute name.
     *
     * @return mixed Attribute value.
     */
    public function __get(string $attribute)
    {
        $column = $this->resolveColumnName($attribute);

        if (array_key_exists($column, $this->data)) {
            return $this->data[$column];
        }

        $propertyName = $this->getPropertyName($column);

        if ($propertyName !== $column && property_exists($this, $propertyName)) {
            return $this->{$propertyName};
        }

        return null;
    }

    /**
     * Magic setter for schema-defined attributes.
     *
     * Values are cast according to schema PDO types.
     *
     * @param string $attribute Attribute name.
     * @param string|int|bool|null $value Attribute value.
     *
     * @throws RuntimeException When the attribute is not in the schema.
     * @throws InvalidArgumentException When schema type is unsupported.
     */
    public function __set(string $attribute, $value): void
    {
        $column = $this->resolveColumnName($attribute);

        if ($value === null) {
            $this->data[$column] = null;

            $propertyName = $this->getPropertyName($column);

            if ($propertyName !== $column && property_exists($this, $propertyName)) {
                $this->{$propertyName} = null;
            }

            return;
        }

        $schemaType = $this->schema[$column];

        $castBooleanValue = function ($value): bool {
            if (is_bool($value)) {
                return $value;
            }

            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($filtered !== null) {
                return $filtered;
            }

            return (bool) $value;
        };

        $castValue = match ($schemaType) {
            self::TYPE_INT => (int) $value,
            self::TYPE_BOOL => $castBooleanValue($value),
            self::TYPE_STRING => (string) $value,
            default => throw new InvalidArgumentException("Unsupported type: $schemaType") // @codeCoverageIgnore
        };

        $this->data[$column] = $castValue;

        $propertyName = $this->getPropertyName($column);

        if ($propertyName !== $column && property_exists($this, $propertyName)) {
            $this->{$propertyName} = $castValue;
        }
    }

    /**
     * Magic isset for schema-defined attributes.
     *
     * @param string $attribute Attribute name.
     */
    public function __isset(string $attribute): bool
    {
        return isset($this->schema[$attribute]) || isset($this->propertyToColumn[$attribute]);
    }

    /**
     * Load a row by primary key into the current instance.
     *
     * @param int|string $id Primary key value.
     *
     * @return static
     *
     * @throws RuntimeException When no row matches the given primary key.
     */
    public function load(int|string $id = 0): static
    {
        $cols = [];

        foreach (array_keys($this->schema) as $columnName) {
            $quotedColumnName = $this->quoteIdentifier($columnName);
            $propertyName = $this->getPropertyName($columnName);

            if ($propertyName !== $columnName) {
                $cols[] = $quotedColumnName . ' AS ' . $this->quoteIdentifier($propertyName);
            } else {
                $cols[] = $quotedColumnName;
            }
        }

        $query = 'SELECT ' . implode(', ', $cols) . ' FROM '
               . $this->quoteIdentifier($this->tableName)
               . ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?';

        $st = $this->db->prepare($query);
        $st->setFetchMode(PDO::FETCH_INTO, $this);
        $st->bindParam(1, $id, $this->schema[$this->primaryKey]);
        $st->execute();

        if (!$st->fetch()) {
            throw new RuntimeException("Error while trying to load item {$id}");
        }

        return $this;
    }

    /**
     * Hook called before save.
     *
     * @param bool $insert Whether the operation is an INSERT (`true`) or UPDATE (`false`).
     *
     * @return bool Whether save may continue.
     */
    protected function beforeSave(bool $insert): bool
    {
        $event = new BeforeSaveEvent($insert, $this);
        $this->trigger($event);

        return $event->isValid;
    }

    /**
     * Hook called before delete.
     *
     * @return bool Whether delete may continue.
     */
    protected function beforeDelete(): bool
    {
        $event = new BeforeDeleteEvent($this);
        $this->trigger($event);

        return $event->isValid;
    }

    /**
     * Hook called after save.
     */
    protected function afterSave(): void
    {
        $this->trigger(new AfterSaveEvent($this));
    }

    /**
     * Hook called after delete.
     */
    protected function afterDelete(): void
    {
        $this->trigger(new AfterDeleteEvent($this));
    }

    /**
     * Persist the current record.
     *
     * Inserts when the primary key is `null`, otherwise updates the row.
     *
     * @return bool `true` on success, `false` when blocked by `beforeSave()`.
     */
    public function save(): bool
    {
        $insert = $this->getColumnValue($this->primaryKey) === null;

        if (!$this->beforeSave($insert)) {
            return false;
        }

        $fields = array_keys($this->schema);
        $valueKeys = [];

        $primaryKeyIndex = array_search($this->primaryKey, $fields);

        // Remove the primary key from the fields array
        if ($primaryKeyIndex !== false) {
            unset($fields[$primaryKeyIndex]);
        }

        if ($insert) {
            $cols = [];

            foreach ($fields as $field) {
                $valueKeys[] = ':' . $field;
                $cols[] = $this->quoteIdentifier($field);
            }

            $query = 'INSERT INTO ' . $this->quoteIdentifier($this->tableName) . ' (' . implode(', ', $cols) . ')';
            $query .= ' VALUES (' . implode(', ', $valueKeys) . ')';

        } else {
            foreach ($fields as $field) {
                $valueKeys[] = $this->quoteIdentifier($field) . '= :' . $field;
            }

            $query = 'UPDATE ' . $this->quoteIdentifier($this->tableName) . ' SET ' . implode(', ', $valueKeys);
            $query .= ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = :__pk';
        }

        $st = $this->db->prepare($query);

        foreach ($fields as $field) {
            $value = $this->getColumnValue($field);
            $st->bindValue(':' . $field, $value, $this->getParameterType($this->schema[$field], $value));
        }

        if (!$insert) {
            $primaryKeyValue = $this->getColumnValue($this->primaryKey);
            $st->bindValue(
                ':__pk',
                $primaryKeyValue,
                $this->getParameterType($this->schema[$this->primaryKey], $primaryKeyValue)
            );
        }

        $st->execute();

        if ($insert && $this->schema[$this->primaryKey] === self::TYPE_INT && $this->getColumnValue($this->primaryKey) === null) {
            $lastInsertId = $this->db->lastInsertId();

            if ($lastInsertId !== false && $lastInsertId !== '') {
                $primaryKeyProperty = $this->getPropertyName($this->primaryKey);
                $this->{$primaryKeyProperty} = (int) $lastInsertId;
            }
        }

        $this->afterSave();

        return true;
    }

    /**
     * Delete the current record.
     *
     * @return bool `true` on success, `false` when blocked by `beforeDelete()`.
     *
     * @throws RuntimeException When the record is not loaded.
     */
    public function delete(): bool
    {
        $id = $this->getColumnValue($this->primaryKey);

        if ($id === null) {
            throw new RuntimeException("Item cannot be deleted because it is not loaded.");
        }

        if (!$this->beforeDelete()) {
            return false;
        }

        $st = $this->db->prepare(
            'DELETE FROM ' . $this->quoteIdentifier($this->tableName) .
            ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?'
        );
        $st->bindValue(1, $id, $this->schema[$this->primaryKey]);
        $st->execute();
        $this->afterDelete();

        return true;
    }
}

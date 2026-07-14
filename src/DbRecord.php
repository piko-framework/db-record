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
use Throwable;
use PDOStatement;
use Piko\DbRecord\ValueCaster;
use Piko\DbRecord\Event\AfterSaveEvent;
use Piko\DbRecord\Metadata;
use Piko\DbRecord\Metadata\MetadataResolver;
use Piko\DbRecord\Exception\SchemaException;
use Piko\DbRecord\Exception\PersistenceException;
use Piko\DbRecord\Exception\RecordNotFoundException;
use Piko\DbRecord\Event\BeforeSaveEvent;
use Piko\DbRecord\Event\AfterDeleteEvent;
use Piko\DbRecord\Event\BeforeDeleteEvent;

/**
 * DbRecord represents a database table's row and implements
 * the Active Record pattern.
 *
 * @author Sylvain PHILIP <contact@sphilip.com>
 *
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
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Resolved metadata for the current model class.
     */
    protected Metadata $metadata;

    /**
     * Metadata resolver helper.
     */
    private MetadataResolver $metadataResolver;

    /**
     * Create a new record instance.
     *
     * @param PDO $db Database connection.
     *
     * @throws SchemaException When table name, schema, or primary key is invalid.
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->metadataResolver = new MetadataResolver();
        $this->initializeSchema();

        if (empty($this->tableName)) {
            throw new SchemaException(
                "The table name is not defined." .
                " Ensure the class has a '#[Table]' attribute with a 'name' property."
            );
        }

        if (empty($this->schema)) {
            throw new SchemaException(
                "No table schema defined." .
                " Ensure the class has properties annotated with '#[Column]' attributes."
            );
        }

        if (!isset($this->schema[$this->primaryKey])) {
            throw new SchemaException("The primary key {$this->primaryKey} is not defined in the table schema");
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
    public function quoteIdentifier(string $identifier): string
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'mysql', 'sqlite' => '`' . str_replace('`', '``', $identifier) . '`',
            'pgsql' => '"' . str_replace('"', '""', $identifier) . '"',
            'sqlsrv', 'dblib' => '[' . str_replace(']', ']]', $identifier) . ']',
            default => $identifier,
        };
    }

    /**
     * Prepare a SQL statement or throw with context.
     *
     * @param string $query SQL query.
     * @param string $context Action context for error messages.
     *
     * @return PDOStatement
     */
    private function prepareOrFail(string $query, string $context): PDOStatement
    {
        try {
            $statement = $this->db->prepare($query);
        } catch (Throwable $exception) {
            throw new PersistenceException(
                'Failed to prepare SQL statement during ' . $context . ': ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (!$statement instanceof PDOStatement) {
            $error = $this->db->errorInfo();
            $reason = $error[2] ?? 'unknown error';
            throw new PersistenceException('Failed to prepare SQL statement during ' . $context . ': ' . $reason);
        }

        return $statement;
    }

    /**
     * Execute a SQL statement or throw with context.
     *
     * @param PDOStatement $statement Prepared statement.
     * @param string $context Action context for error messages.
     */
    private function executeOrFail(PDOStatement $statement, string $context): void
    {
        try {
            if ($statement->execute()) {
                return;
            }
        } catch (Throwable $exception) {
            throw new PersistenceException(
                'Failed to execute SQL statement during ' . $context . ': ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $error = $statement->errorInfo();
        $reason = $error[2] ?? 'unknown error';

        throw new PersistenceException('Failed to execute SQL statement during ' . $context . ': ' . $reason);
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
     * Resolve a schema column to a distinct, existing mapped property.
     *
     * @param string $column Schema column name.
     *
     * @return string|null Property name when it differs from the column and exists, `null` otherwise.
     */
    private function getMappedProperty(string $column): ?string
    {
        $propertyName = $this->metadata->getPropertyName($column);

        if ($propertyName !== $column && property_exists($this, $propertyName)) {
            return $propertyName;
        }

        return null;
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
        $propertyName = $this->getMappedProperty($column);

        return $propertyName !== null ? $this->{$propertyName} : $this->{$column};
    }

    /**
     * Initialize table metadata from `#[Table]` and `#[Column]` attributes.
     */
    protected function initializeSchema(): void
    {
        $this->metadata = $this->metadataResolver->resolve(
            static::class,
            $this->tableName,
            $this->schema,
            $this->primaryKey
        );

        $this->tableName = $this->metadata->tableName;
        $this->schema = $this->metadata->schema;
        $this->primaryKey = $this->metadata->primaryKey;
    }

    /**
     * Assign a casted value to both internal data and mapped property when available.
     *
     * @param string $column Schema column name.
     * @param mixed $value Casted value.
     */
    private function assignColumnValue(string $column, mixed $value): void
    {
        $this->data[$column] = $value;
        $propertyName = $this->metadata->getPropertyName($column);

        if (property_exists($this, $propertyName)) {
            $this->{$propertyName} = $value;
        }
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
     * Normalize and bind a column value to a prepared statement.
     *
     * @param PDOStatement $statement Prepared statement.
     * @param string $placeholder Named placeholder (e.g. `:name`).
     * @param string $column Schema column name.
     */
    private function bindColumnValue(PDOStatement $statement, string $placeholder, string $column): void
    {
        $value = ValueCaster::normalizeForDatabase(
            $this->getColumnValue($column),
            $this->metadata->getCastTypeForColumn($column),
            $this->metadata->getDecimalScaleForColumn($column)
        );
        $statement->bindValue($placeholder, $value, $this->getParameterType($this->schema[$column], $value));
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
        $column = $this->metadata->resolveColumnName($attribute);

        if (array_key_exists($column, $this->data)) {
            return $this->data[$column];
        }

        $propertyName = $this->getMappedProperty($column);

        return $propertyName !== null ? $this->{$propertyName} : null;
    }

    /**
     * Magic setter for schema-defined attributes.
     *
     * Values are cast according to schema PDO types.
     *
     * @param string $attribute Attribute name.
     * @param string|int|bool|null $value Attribute value.
     *
     * @throws SchemaException When the attribute is not in the schema.
     * @throws \InvalidArgumentException When schema type is unsupported.
     */
    public function __set(string $attribute, $value): void
    {
        $column = $this->metadata->resolveColumnName($attribute);

        $castValue = ValueCaster::cast(
            $value,
            $this->metadata->getCastTypeForColumn($column),
            $this->metadata->getDecimalScaleForColumn($column)
        );
        $this->assignColumnValue($column, $castValue);
    }

    /**
     * Magic isset for schema-defined attributes.
     *
     * @param string $attribute Attribute name.
     */
    public function __isset(string $attribute): bool
    {
        return $this->metadata->hasAttribute($attribute);
    }

    /**
     * Check whether a row exists for a primary key.
     *
     * @param int|string $id Primary key value.
     *
     * @return bool
     */
    public function exists(int|string $id): bool
    {
        $query = 'SELECT 1 FROM ' . $this->quoteIdentifier($this->tableName)
               . ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?';

        $statement = $this->prepareOrFail($query, 'exists');
        $statement->bindValue(1, $id, $this->schema[$this->primaryKey]);
        $this->executeOrFail($statement, 'exists');

        return $statement->fetchColumn() !== false;
    }

    /**
     * Load a row by primary key into the current instance.
     *
     * @param int|string $id Primary key value.
     *
     * @return static
     *
     * @throws RecordNotFoundException When no row matches the given primary key.
     */
    public function load(int|string $id = 0): static
    {
        $cols = array_map(
            fn(string $columnName): string => $this->quoteIdentifier($columnName),
            array_keys($this->schema)
        );

        $query = 'SELECT ' . implode(', ', $cols) . ' FROM '
               . $this->quoteIdentifier($this->tableName)
               . ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?';

        $st = $this->prepareOrFail($query, 'load');
        $st->bindParam(1, $id, $this->schema[$this->primaryKey]);
        $this->executeOrFail($st, 'load');

        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RecordNotFoundException("Error while trying to load item {$id}");
        }

        foreach ($row as $column => $value) {
            $castValue = ValueCaster::cast(
                $value,
                $this->metadata->getCastTypeForColumn($column),
                $this->metadata->getDecimalScaleForColumn($column)
            );
            $this->assignColumnValue($column, $castValue);
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
     * Inserts when the primary key is `null` or when the record doesn't exists,
     * otherwise updates the row.
     *
     * @return bool `true` on success, `false` when blocked by `beforeSave()`.
     */
    public function save(): bool
    {
        $primaryKeyValue = $this->getColumnValue($this->primaryKey);
        $insert = $primaryKeyValue === null || !$this->exists(ValueCaster::toString($primaryKeyValue));

        if (!$this->beforeSave($insert)) {
            return false;
        }

        $valueKeys = [];

        // For INSERT, include primary key only when explicitly provided (e.g. string/UUID keys).
        // For UPDATE, primary key is always excluded from SET and bound separately in WHERE.
        $fields = array_keys($this->schema);

        if (!$insert || $primaryKeyValue === null) {
            $fields = array_values(array_filter(
                $fields,
                fn(string $field): bool => $field !== $this->primaryKey
            ));
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

        $st = $this->prepareOrFail($query, $insert ? 'insert' : 'update');

        foreach ($fields as $field) {
            $this->bindColumnValue($st, ':' . $field, $field);
        }

        if (!$insert) {
            $this->bindColumnValue($st, ':__pk', $this->primaryKey);
        }

        $this->executeOrFail($st, $insert ? 'insert' : 'update');

        if (
            $insert &&
            $this->schema[$this->primaryKey] === self::TYPE_INT &&
            $this->getColumnValue($this->primaryKey) === null
        ) {
            $lastInsertId = $this->db->lastInsertId();

            if ($lastInsertId !== false && $lastInsertId !== '') {
                $primaryKeyProperty = $this->metadata->getPropertyName($this->primaryKey);
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
     * @throws RecordNotFoundException When the record is not loaded.
     */
    public function delete(): bool
    {
        $id = $this->getColumnValue($this->primaryKey);

        if ($id === null) {
            throw new RecordNotFoundException("Item cannot be deleted because it is not loaded.");
        }

        if (!$this->beforeDelete()) {
            return false;
        }

        $st = $this->prepareOrFail(
            'DELETE FROM ' . $this->quoteIdentifier($this->tableName) .
            ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?',
            'delete'
        );
        $dbId = ValueCaster::normalizeForDatabase(
            $id,
            $this->metadata->getCastTypeForColumn($this->primaryKey),
            $this->metadata->getDecimalScaleForColumn($this->primaryKey)
        );
        $st->bindValue(1, $dbId, $this->schema[$this->primaryKey]);
        $this->executeOrFail($st, 'delete');
        $this->afterDelete();

        return true;
    }
}

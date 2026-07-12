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
     * The database instance.
     *
     * @var PDO
     */
    protected $db;

    /**
     * The name of the table.
     *
     * @var string
     */
    protected $tableName = '';

    /**
     * A name-value pair that describes the structure of the table.
     * eg.`['id' => self::TYPE_INT, 'name' => 'id' => self::TYPE_STRING]`
     *
     * @var int[]
     */
    protected $schema = [];

    /**
     * The name of the primary key. Default to 'id'.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var array<string, string|int|bool> Represents the rows's data.
     */
    protected $data = [];

    /**
     * Constructor
     *
     * @param PDO $db A PDO instance
     *
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->initializeSchema();
    }

    /**
     * Quote table or column name
     *
     * @param string $identifier
     *
     * @return string
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
     * Retrieve the attributes representing the record in the database.
     *
     * This method returns an associative array where each key corresponds to a column name
     * as defined in the schema, and each value is the respective column's value from the
     * current instance's data. This can be particularly useful for debugging or when you need
     * to serialize the record for storage or transmission.
     *
     * @return array<string, mixed> An associative array where keys are column names and values are column values.
     */
    protected function getAttributes(): array
    {
        $fields = array_keys($this->schema);

        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field] = $this->$field;
        }

        return $attributes;
    }

    /**
     * Check if column name is defined in the table schema.
     *
     * @param string $name
     * @return void
     * @throws RuntimeException
     * @see DbRecord::$schema
     */
    protected function checkColumn(string $name): void
    {
        if (!isset($this->schema[$name])) {
            throw new RuntimeException("$name is not in the table schema.");
        }
    }

    /**
     * Initialize the schema for the database table.
     *
     * This method uses reflection to inspect the current class for properties that have the `FieldAttribute` attribute.
     * It then builds the schema array, which describes the structure of the table, using these properties.
     * Additionally, it sets the table name if a `TableAttribute` is present on the class and identifies
     * the primary key based on field attributes.
     *
     * @return void
     */
    protected function initializeSchema(): void
    {
        $reflectionClass = new ReflectionClass($this);

        $tableAttribute = $reflectionClass->getAttributes(Table::class)[0] ?? null;

        if ($tableAttribute) {
            $this->tableName = $tableAttribute->newInstance()->name;
        }

        foreach ($reflectionClass->getProperties() as $property) {

            $fieldAttribute = $property->getAttributes(Column::class)[0] ?? null;

            if ($fieldAttribute) {
                $fieldInstance = $fieldAttribute->newInstance();
                $fieldName = $fieldInstance->name ?? $property->getName();
                $propertyType = $property->getType();
                // Default type to string if no type is declared
                $type = $propertyType instanceof ReflectionNamedType ? $propertyType->getName() : 'string';

                $this->schema[$fieldName] = $this->getSchemaType($type);

                if ($fieldInstance->primaryKey) {
                    $this->primaryKey = $fieldName;
                }
            }
        }
    }

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
     * Magick method to access rows's data as class attribute.
     *
     * @param string $attribute The attribute's name.
     * @return mixed The attribute's value.
     */
    public function __get(string $attribute)
    {
        $this->checkColumn($attribute);

        return $this->data[$attribute] ?? null;
    }


    /**
     * Magick method to set row's data as class attribute.
     *
     * @param string $attribute The attribute's name.
     * @param string|int|bool $value The attribute's value.
     *
     * @return void
     */
    public function __set(string $attribute, $value): void
    {
        $this->checkColumn($attribute);

        $shemaType = $this->schema[$attribute];

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

        $this->data[$attribute] = match ($shemaType) {
            self::TYPE_INT => (int) $value,
            self::TYPE_BOOL => $castBooleanValue($value),
            self::TYPE_STRING => (string) $value,
            default => throw new InvalidArgumentException("Unsupported type: $shemaType") // @codeCoverageIgnore
        };
    }

    /**
     * Magick method to check if attribute is defined in the table schema.
     *
     * @param string $attribute The attribute's name.
     */
    public function __isset(string $attribute): bool
    {
        return isset($this->schema[$attribute]);
    }

    /**
     * Load row data.
     *
     * @param number $id The value of the row primary key.
     * @return static
     * @throws RuntimeException
     */
    public function load($id = 0): DbRecord
    {
        if (!isset($this->schema[$this->primaryKey])) {
            throw new RuntimeException("The primary key {$this->primaryKey} is not defined in the table schema");
        }

        $cols = array_keys($this->schema);

        foreach ($cols as &$col) {
            $col = $this->quoteIdentifier($col);
        }

        $query = 'SELECT ' . implode(',', $cols) . ' FROM '
               . $this->quoteIdentifier($this->tableName)
               . ' WHERE ' . $this->primaryKey . ' = ?';

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
     * Method called before a save action.
     *
     * @param boolean $insert If the row is a new record, the value will be true, otherwise, false.
     * @return boolean
     */
    protected function beforeSave(bool $insert): bool
    {
        $event = new BeforeSaveEvent($insert, $this);
        $this->trigger($event);

        return $event->isValid;
    }

    /**
     * Method called before a delete action.
     *
     * @return boolean
     */
    protected function beforeDelete(): bool
    {
        $event = new BeforeDeleteEvent($this);
        $this->trigger($event);

        return $event->isValid;
    }

    /**
     * Method called after a save action.
     *
     * @return void
     */
    protected function afterSave(): void
    {
        $this->trigger(new AfterSaveEvent($this));
    }

    /**
     * Method called after a delete action.
     *
     * @return void
     */
    protected function afterDelete(): void
    {
        $this->trigger(new AfterDeleteEvent($this));
    }

    /**
     * Save this record into the table.
     *
     * @throws RuntimeException
     * @return boolean
     */
    public function save(): bool
    {
        $insert = empty($this->{$this->primaryKey}) ? true : false;

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
            $query .= ' WHERE ' . $this->primaryKey . ' = ' . (int) $this->{$this->primaryKey};
        }

        $st = $this->db->prepare($query);

        foreach ($fields as $field) {
            $st->bindValue(':' . $field, $this->$field, $this->schema[$field]);
        }

        $st->execute();

        if ($insert) {
            $this->{$this->primaryKey} = (int) $this->db->lastInsertId();
        }

        $this->afterSave();

        return true;
    }

    /**
     * Delete this record.
     *
     * @throws RuntimeException
     * @return boolean
     */
    public function delete(): bool
    {
        if (empty($this->{$this->primaryKey})) {
            throw new RuntimeException("Item cannot be delete because it is not loaded.");
        }

        if (!$this->beforeDelete()) {
            return false;
        }

        $st = $this->db->prepare(
            'DELETE FROM ' . $this->quoteIdentifier($this->tableName) . ' WHERE ' . $this->primaryKey . ' = ?'
        );
        $id = $this->{$this->primaryKey};
        $st->bindParam(1, $id, PDO::PARAM_INT);
        $st->execute();
        $this->afterDelete();

        return true;
    }
}

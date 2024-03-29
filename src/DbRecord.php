<?php

/**
 * This file is part of Piko - Web micro framework
 *
 * @copyright 2019-2022 Sylvain PHILIP
 * @license LGPL-3.0; see LICENSE.txt
 * @link https://github.com/piko-framework/db-record
 */

declare(strict_types=1);

namespace Piko;

use Piko\DbRecord\Event\AfterDeleteEvent;
use Piko\DbRecord\Event\AfterSaveEvent;
use Piko\DbRecord\Event\BeforeDeleteEvent;
use Piko\DbRecord\Event\BeforeSaveEvent;
use PDO;
use PDOStatement;
use RuntimeException;

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
    }

    /**
     * Quote table or column name
     *
     * @param string $identifier
     *
     * @return string
     */
    public function quoteIdentifier($identifier): string
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'mysql':
            case 'sqlite':
                return '`' . $identifier . '`';
            case 'pgsql':
                return '"' . $identifier . '"';
            case 'sqlsrv':
                return '[' . $identifier . ']';
            default:
                return $identifier;
        }
    }

    /**
     * Override ModelTrait::bind()
     *
     * @param array<string, string|int|bool> $data
     */
    public function bind(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Override ModelTrait::toArray()
     *
     * @return mixed[]
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Check if column name is defined in the table schema.
     *
     * @param string $name
     * @return void
     * @throws RuntimeException
     * @see DbRecord::$schema
     */
    protected function checkColumn(string $name)
    {
        if (!isset($this->schema[$name])) {
            throw new RuntimeException("$name is not in the table schema.");
        }
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
    public function __set(string $attribute, $value)
    {
        $this->checkColumn($attribute);

        $this->data[$attribute] = $value;
    }

    /**
     * Magick method to check if attribute is defined in row's data.
     *
     * @param string $attribute The attribute's name.
     */
    public function __isset(string $attribute)
    {
        return isset($this->data[$attribute]);
    }

    /** Magick method to unset attribute in row's data.
     *
     * @param string $attribute The attribute's name.
     */
    public function __unset(string $attribute)
    {
        if (isset($this->data[$attribute])) {
            unset($this->data[$attribute]);
        }
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
        $query = 'SELECT * FROM ' . $this->quoteIdentifier($this->tableName) . ' WHERE ' . $this->primaryKey . ' = ?';
        $st = $this->db->prepare($query);

        if (!$st instanceof PDOStatement) {
            $error = $this->db->errorInfo();
            throw new RuntimeException("Query '$query' failed with error {$error[0]} : {$error[2]}");
        }

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
        foreach ($this->data as $key => $value) {
            $this->checkColumn($key);
        }

        $insert = empty($this->data[$this->primaryKey]) ? true : false;

        if (!$this->beforeSave($insert)) {
            return false;
        }

        $cols = array_keys($this->data);
        $valueKeys = [];

        if ($insert) {
            foreach ($cols as &$key) {
                $valueKeys[] = ':' . $key;
                $key = $this->quoteIdentifier($key);
            }

            $query = 'INSERT INTO ' . $this->quoteIdentifier($this->tableName) . ' (' . implode(', ', $cols) . ')';
            $query .= ' VALUES (' . implode(', ', $valueKeys) . ')';
        } else {
            foreach ($cols as $key) {
                $valueKeys[] = $this->quoteIdentifier($key) . '= :' . $key;
            }

            $query = 'UPDATE ' . $this->quoteIdentifier($this->tableName) . ' SET ' . implode(', ', $valueKeys);
            $query .= ' WHERE ' . $this->primaryKey . ' = ' . (int) $this->data[$this->primaryKey];
        }

        $st = $this->db->prepare($query);

        if (!$st instanceof PDOStatement) {
            // @codeCoverageIgnoreStart
            $error = $this->db->errorInfo();
            throw new RuntimeException("Query '$query' failed with error {$error[0]} : {$error[2]}");
            // @codeCoverageIgnoreEnd
        }

        foreach ($this->data as $key => $value) {
            $st->bindValue(':' . $key, $value, $this->schema[$key]);
        }

        if ($st->execute() === false) {
            // @codeCoverageIgnoreStart
            $error = $st->errorInfo();
            throw new RuntimeException("Query '$query' failed with error {$error[0]} : {$error[2]}");
            // @codeCoverageIgnoreEnd
        }

        if ($insert) {
            $this->data[$this->primaryKey] = $this->db->lastInsertId();
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
        if (!isset($this->data[$this->primaryKey])) {
            throw new RuntimeException("Item cannot be delete because it is not loaded.");
        }

        if (!$this->beforeDelete()) {
            return false;
        }

        $st = $this->db->prepare(
            'DELETE FROM ' . $this->quoteIdentifier($this->tableName) . ' WHERE ' . $this->primaryKey . ' = ?'
        );

        $st->bindParam(1, $this->data[$this->primaryKey], PDO::PARAM_INT);

        if (!$st->execute()) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException("Error while trying to delete item {$this->data[$this->primaryKey]}");
            // @codeCoverageIgnoreEnd
        }

        $this->afterDelete();

        return true;
    }
}

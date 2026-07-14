<?php

/**
 * This file is part of Piko DbRecord - Web micro framework
 *
 * @copyright 2019-2024 Sylvain PHILIP
 * @license LGPL-3.0; see LICENSE.txt
 * @link https://github.com/piko-framework/db-record
 */

declare(strict_types=1);

namespace Piko\DbRecord\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]

/**
 * Attribute class to define metadata for a database field.
 *
 * This attribute can be used to specify properties of database fields such as
 * whether the field is a primary key and what its name should be.
 *
 * Usage example:
 *
 * ```php
 * #[Table('users')]
 * class User {
 *     #[Column(primaryKey: true)]
 *     public int $id;
 *
 *     #[Column(name: 'user_name')]
 *     public string $userName;
 *
 *     #[Column]
 *     public string $email;
 *
 *     // Class implementation
 * }
 * ```
 *
 * @package Piko\DbRecord
 * @author Sylvain PHILIP <contact@sphilip.com>
 */
class Column
{
    /**
     * Constructor for the Column class.
     *
     * @param bool $primaryKey Indicates if the field is a primary key. Default is false.
     * @param string|null $name The name of the field. Default is null.
     * @param string|null $type Optional cast type
     *                          (int|string|bool|float|decimal|datetime_immutable|datetime_mutable|json).
     * @param int|null $scale Optional decimal scale (used with `decimal` cast type).
     */
    public function __construct(
        public bool $primaryKey = false,
        public ?string $name = null,
        public ?string $type = null,
        public ?int $scale = null
    ) {
    }
}

<?php

/**
 * This file is part of Piko Framework
 *
 * @copyright 2019-2022 Sylvain Philip
 * @license LGPL-3.0-or-later; see LICENSE.txt
 * @link https://github.com/piko-framework/db-record
 */

declare(strict_types=1);

namespace Piko\DbRecord\Event;

use Piko\DbRecord;

/**
 * Event emitted before db save operation (INSERT / UPDATE)
 *
 * @author Sylvain Philip <contact@sphilip.com>
 */
class BeforeSaveEvent extends AlterEvent
{
    /**
     * Indicates if the event is valid for further validation.
     */
    public bool $isValid = true;

    /**
     * Flag to see if db save operation is INSERT.
     * If false, indicates that is an UPDATE operation.
     */
    public bool $insert = false;

    /**
     * @param bool $insert Indicates if the operation is INSERT
     * @param DbRecord $record
     */
    public function __construct(bool $insert, DbRecord $record)
    {
        $this->insert = $insert;

        parent::__construct($record);
    }
}

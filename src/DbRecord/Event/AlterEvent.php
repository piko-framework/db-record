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

use Piko\Event;
use Piko\DbRecord;

/**
 * Event emitted during db record alteration ( INSERT / UPDATE / DELETE)
 *
 * @author Sylvain Philip <contact@sphilip.com>
 */
abstract class AlterEvent extends Event
{
    /**
     * @var DbRecord
     */
    public DbRecord $record;

    public function __construct(DbRecord $record)
    {
        $this->record = $record;
    }
}

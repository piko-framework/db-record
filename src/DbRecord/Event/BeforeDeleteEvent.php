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

/**
 * Event emitted before db DELETE operation
 *
 * @author Sylvain Philip <contact@sphilip.com>
 */
class BeforeDeleteEvent extends AlterEvent
{
    /**
     * Indicates if the event is valid for further validation.
     */
    public bool $isValid = true;
}

<?php

declare(strict_types=1);

namespace Piko\Tests\Models;

use DateTime;
use Piko\DbRecord\Attribute\Column;
use Piko\DbRecord\Attribute\Table;

#[Table(name: 'contact')]
class ContactDecimalAndMutableDate extends \Piko\DbRecord
{
    #[Column(primaryKey: true)]
    public ?int $id = null;

    #[Column(name: 'name', type: 'decimal', scale: 4)]
    public ?string $balance = null;

    #[Column(name: 'lastname', type: 'datetime_mutable')]
    public ?DateTime $lastSeenAt = null;
}

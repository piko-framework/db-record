<?php

declare(strict_types=1);

namespace Piko\Tests\Models;

use DateTimeImmutable;
use Piko\DbRecord\Attribute\Column;
use Piko\DbRecord\Attribute\Table;

#[Table(name: 'contact')]
class ContactAdvancedTypes extends \Piko\DbRecord
{
    #[Column(primaryKey: true)]
    public ?int $id = null;

    #[Column(type: 'float')]
    public ?float $income = null;

    #[Column(name: 'name', type: 'json')]
    public ?array $nameData = null;

    #[Column(name: 'lastname', type: 'datetime')]
    public ?DateTimeImmutable $lastSeenAt = null;
}

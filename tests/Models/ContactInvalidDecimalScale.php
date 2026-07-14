<?php

declare(strict_types=1);

namespace Piko\Tests\Models;

use Piko\DbRecord\Attribute\Column;
use Piko\DbRecord\Attribute\Table;

#[Table(name: 'contact')]
class ContactInvalidDecimalScale extends \Piko\DbRecord
{
    #[Column(primaryKey: true)]
    public ?int $id = null;

    #[Column(name: 'name', type: 'decimal', scale: -1)]
    public ?string $balance = null;
}

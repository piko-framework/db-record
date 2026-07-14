<?php

declare(strict_types=1);

namespace Piko\Tests\Models;

use Piko\DbRecord\Attribute\Column;
use Piko\DbRecord\Attribute\Table;

#[Table(name: 'contact')]
class ContactDecimalNoScale extends \Piko\DbRecord
{
    #[Column(primaryKey: true)]
    public ?int $id = null;

    #[Column(name: 'name', type: 'decimal')]
    public ?string $amount = null;
}

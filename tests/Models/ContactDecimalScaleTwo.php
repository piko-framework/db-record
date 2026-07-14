<?php

declare(strict_types=1);

namespace Piko\Tests\Models;

use Piko\DbRecord\Attribute\Column;
use Piko\DbRecord\Attribute\Table;

#[Table(name: 'contact')]
class ContactDecimalScaleTwo extends \Piko\DbRecord
{
    #[Column(primaryKey: true)]
    public ?int $id = null;

    #[Column(name: 'name', type: 'decimal', scale: 2)]
    public ?string $balance = null;
}

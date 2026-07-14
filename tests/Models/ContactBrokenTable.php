<?php

declare(strict_types=1);

namespace Piko\Tests\Models;

use Piko\DbRecord\Attribute\Column;
use Piko\DbRecord\Attribute\Table;

#[Table(name: 'contact_missing_table')]
class ContactBrokenTable extends \Piko\DbRecord
{
    #[Column(primaryKey: true)]
    public ?int $id = null;

    #[Column]
    public ?string $firstname = null;
}

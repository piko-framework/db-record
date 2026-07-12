<?php
namespace Piko\Tests\Models;

use Piko\DbRecord\Attribute\Table;
use Piko\DbRecord\Attribute\Column;

#[Table(name: 'contact')]
class Contact extends \Piko\DbRecord
{
    #[Column(primaryKey: true)]
    public ?int $id = null;

    #[Column]
    public $name = null;

    #[Column]
    public ?string $firstname = null;

    #[Column]
    public ?string $lastname = null;

    #[Column]
    public ?int $age = null;

    #[Column]
    public ?int $order = null;

    #[Column]
    public ?bool $active = false;

    #[Column]
    public bool $active2 = true;

    #[Column]
    public float $income = 0;

    protected function validate(): void
    {
        if (empty($this->firstname)) {
            $this->setError('firstname', 'First name is required');
        }

        if (empty($this->lastname)) {
            $this->setError('lastname', 'Last name is required');
        }
    }
}

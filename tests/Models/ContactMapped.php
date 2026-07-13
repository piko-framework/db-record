<?php
namespace Piko\Tests\Models;

use Piko\DbRecord\Attribute\Table;
use Piko\DbRecord\Attribute\Column;

#[Table(name: 'contact')]
class ContactMapped extends \Piko\DbRecord
{
    #[Column(name: 'id', primaryKey: true)]
    public ?int $contactId = null;

    #[Column(name: 'firstname')]
    public ?string $firstName = null;

    #[Column(name: 'lastname')]
    public ?string $lastName = null;

    #[Column(name: 'active')]
    public ?bool $isActive = false;

    protected function validate(): void
    {
        if (empty($this->firstName)) {
            $this->setError('firstName', 'First name is required');
        }

        if (empty($this->lastName)) {
            $this->setError('lastName', 'Last name is required');
        }
    }
}

<?php
namespace Piko\Tests\Models;

use Piko\DbRecord\Attribute\Table;
use Piko\DbRecord\Attribute\Column;

#[Table(name: 'contact')]
class ContactMappedProtected extends \Piko\DbRecord
{
    #[Column(name: 'id', primaryKey: true)]
    protected ?int $contactId = null;

    #[Column(name: 'firstname')]
    protected ?string $firstName = null;

    public function setFirstNameDirect(?string $value): void
    {
        $this->firstName = $value;
    }

    public function clearFirstNameFromData(): void
    {
        unset($this->data['firstname']);
    }
}

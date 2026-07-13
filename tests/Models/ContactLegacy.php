<?php
namespace Piko\Tests\Models;

class ContactLegacy extends \Piko\DbRecord
{
    protected string $tableName = 'contact';

    protected array $schema = [
        'id'        => self::TYPE_INT,
        'name'      => self::TYPE_STRING,
        'firstname' => self::TYPE_STRING,
        'lastname'  => self::TYPE_STRING,
        'age'       => self::TYPE_INT,
        'order'     => self::TYPE_INT,
        'active'    => self::TYPE_BOOL,
        'active2'   => self::TYPE_BOOL,
        'income'    => self::TYPE_STRING,
    ];

    protected function beforeSave(bool $insert): bool
    {
        if ($this->active2 === null) {
            $this->active2 = true;
        }

        return parent::beforeSave($insert);
    }

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

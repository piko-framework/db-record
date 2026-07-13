<?php
namespace Piko\Tests\Models;

class ContactStringPk extends \Piko\DbRecord
{
    protected string $tableName = 'contact';
    protected string $primaryKey = 'firstname';

    protected array $schema = [
        'firstname' => self::TYPE_STRING,
        'lastname'  => self::TYPE_STRING,
    ];
}

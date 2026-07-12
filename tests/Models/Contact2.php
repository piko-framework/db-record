<?php
namespace Piko\Tests\Models;

class Contact2 extends \Piko\DbRecord
{
    protected $tableName = 'contact';
    protected $primaryKey = 'contact_id';

    protected $schema = [
        'id'        => self::TYPE_INT,
        'name'      => self::TYPE_STRING,
        'firstname' => self::TYPE_STRING,
        'lastname'  => self::TYPE_STRING,
        'order'     =>  self::TYPE_INT
    ];
}

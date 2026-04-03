<?php
namespace Piko\Tests;

use PDO;
use RuntimeException;
use TypeError;
use Piko\Tests\Contact;
use Piko\Tests\Contact2;
use Piko\Tests\ContactLegacy;
use PHPUnit\Framework\TestCase;
use Piko\DbRecord\Event\BeforeSaveEvent;
use Piko\DbRecord\Event\BeforeDeleteEvent;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class AbstractTestDbRecord extends TestCase
{
    protected static ?PDO $db = null;

    protected function createContact($className)
    {
        $contact = new $className(self::$db);
        // $contact->name = 'Toto';
        $contact->firstname = 'Sylvain';
        $contact->lastname = 'Philip';
        $contact->order = 1; // order is a reserved word
        $contact->income = 0;
        $contact->save();

        return $contact;
    }

    public static function contactProvider()
    {
        return [
            [Contact::class],
            [ContactLegacy::class]
        ];
    }

    public function testWithNullDb()
    {
        $this->expectException(TypeError::class);
        new Contact(null);
    }

    public function testWithWrongDb()
    {
        $this->expectException(TypeError::class);
        new Contact(new \DateTime());
    }

    #[DataProvider('contactProvider')]
    public function testCreate($className)
    {
        $contact = $this->createContact($className);
        $this->assertEquals(1, $contact->id);
        $this->assertEquals('Sylvain', $contact->firstname);
        $this->assertEquals('Philip', $contact->lastname);
    }

    public function testLoadWithWrongPrimaryKey()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/primary key contact_id is not defined/');
        (new Contact2(self::$db))->load(1);
    }

    #[DataProvider('contactProvider')]
    public function testWrongColumnAccess($className)
    {
        $contact = $this->createContact($className);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('email is not in the table schema.');
        $contact->email;
    }

    #[DataProvider('contactProvider')]
    public function testIsset($className)
    {
        $contact = $this->createContact($className);
        $this->assertTrue(isset($contact->order));
        $this->assertFalse(isset($contact->email));
    }

    #[DataProvider('contactProvider')]
    public function testUpdate($className)
    {
        $this->createContact($className);
        $contact = (new Contact(self::$db))->load(1);
        $this->assertEquals('Sylvain', $contact->firstname);

        $contact->firstname .= ' updated';
        $contact->save();

        $contact = (new Contact(self::$db))->load(1);
        $this->assertEquals('Sylvain updated', $contact->firstname);
    }

    #[DataProvider('contactProvider')]
    public function testBeforeSave($className)
    {
        $contact = $this->createContact($className);
        $contact->on(BeforeSaveEvent::class, function(BeforeSaveEvent $event) {
            $event->record->name = $event->record->firstname . ' ' . $event->record->lastname;
        });
        $this->assertTrue($contact->save());
        $this->assertEquals('Sylvain Philip', $contact->name);
    }

    #[DataProvider('contactProvider')]
    public function testBeforeSaveFalse($className)
    {
        $contact = $this->createContact($className);
        $contact->on(BeforeSaveEvent::class, function(BeforeSaveEvent $event) {
            $event->isValid = false;
        });
        $this->assertFalse($contact->save());
    }

    #[DataProvider('contactProvider')]
    public function testDelete($className)
    {
        $contact = $this->createContact($className);
        $this->assertEquals(1, $contact->id);
        $contact->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error while trying to load item 1');
        $contact = (new Contact(self::$db))->load(1);
    }

    #[DataProvider('contactProvider')]
    public function testDeleteNotLoaded($className)
    {
        $contact = new $className(self::$db);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Item cannot be delete because it is not loaded.');
        $contact->delete();
    }

    #[DataProvider('contactProvider')]
    public function testBeforeDelete($className)
    {
        $contact = $this->createContact($className);
        $contact->on(BeforeDeleteEvent::class, function(BeforeDeleteEvent $event) {
            if ($event->record->firstname == 'Sylvain') {
                $event->isValid = false;
            }
        });

        $this->assertFalse($contact->delete());
    }

    #[DataProvider('contactProvider')]
    public function testModelValidation($className)
    {
        $model = new $className(self::$db);

        $this->assertFalse($model->isValid());

        $errors = $model->getErrors();

        $this->assertArrayHasKey('firstname', $errors);
        $this->assertArrayHasKey('lastname', $errors);

        $model = new $className(self::$db);

        $model->firstname = 'John';
        $model->lastname = 'Lennon';

        $this->assertTrue($model->isValid());
    }

    #[DataProvider('contactProvider')]
    public function testModelBind($className)
    {
        $model = new $className(self::$db);

        $data = [
            'id' => 1,
            'name' => 'John Lennon',
            'firstname' => 'John',
            'lastname' => 'Lennon',
            'age' => 45,
            'order' => 1,
            'active' => false,
            'active2' => true,
            'income' => 20230.95
        ];

        $model->bind($data);
        $this->assertEquals($data, $model->toArray());
    }

    #[DataProvider('contactProvider')]
    public function testModelBindWithStringValues($className)
    {
        $model = new $className(self::$db);

        $model->bind([
            'id' => '1',
            'name' => 'John Lennon',
            'firstname' => 'John',
            'lastname' => 'Lennon',
            'age' => '45',
            'order' => '1',
            'active' => 'false',
            'active2' => 'true',
            'income' => '20230.95',
        ]);

        $this->assertSame(1, $model->id);
        $this->assertSame(45, $model->age);
        $this->assertSame(1, $model->order);
        $this->assertFalse($model->active);
        $this->assertTrue($model->active2);
        $this->assertEquals(20230.95, $model->income);
    }

    #[DataProvider('contactProvider')]
    public function testModelBindWithBooleanValues($className)
    {
        $model = new $className(self::$db);

        $model->bind([
            'active' => 0,
            'active2' => 1,
        ]);

        $this->assertFalse($model->active);
        $this->assertTrue($model->active2);

        $model->bind([
            'active' => [],
            'active2' => [1],
        ]);

        $this->assertFalse($model->active);
        $this->assertTrue($model->active2);
    }
}

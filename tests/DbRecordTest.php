<?php
namespace Piko\Tests;

use PDO;
use RuntimeException;
use TypeError;
use Piko\Tests\Models\Contact;
use Piko\Tests\Models\Contact2;
use Piko\Tests\Models\ContactLegacy;
use Piko\Tests\Models\ContactStringPk;
use Piko\Tests\Infrastructure\TestContext;
use PHPUnit\Framework\TestCase;
use Piko\DbRecord\Event\BeforeSaveEvent;
use Piko\DbRecord\Event\BeforeDeleteEvent;
use PHPUnit\Framework\Attributes\DataProvider;

class DbRecordTest extends TestCase
{
    const SETUP_SQLITE = '
        CREATE TABLE contact (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            firstname TEXT,
            lastname TEXT,
            age INTEGER,
            `order` INTEGER,
            active INTEGER DEFAULT 0,
            active2 INTEGER DEFAULT 1,
            income REAL DEFAULT 0
        );
    ';

    const SETUP_MYSQL = '
        CREATE TABLE IF NOT EXISTS contact (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NULL,
            firstname VARCHAR(255),
            lastname VARCHAR(255),
            age INT,
            `order` INT,
            active INT DEFAULT 0,
            active2 TINYINT DEFAULT 1,
            income FLOAT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ';

    const SETUP_MSSQL = '
        CREATE TABLE contact (
            id INT IDENTITY(1,1) PRIMARY KEY,
            name NVARCHAR(255) NULL,
            firstname NVARCHAR(255),
            lastname NVARCHAR(255),
            age INT NULL,
            [order] INT,
            active BIT DEFAULT 0 NULL,
            active2 BIT DEFAULT 1,
            income FLOAT DEFAULT 0
        );
    ';

    const SETUP_POSTGRESQL = '
        CREATE TABLE IF NOT EXISTS contact (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255),
            firstname VARCHAR(255),
            lastname VARCHAR(255),
            age INT,
            "order" INT,
            active BOOLEAN DEFAULT FALSE,
            active2 BOOLEAN DEFAULT TRUE,
            income FLOAT DEFAULT 0
        );
    ';

    private static function getPDO(): PDO
    {
        $pdo = TestContext::getContainer()?->get(PDO::class);

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('PDO driver not configured');
        }

        return $pdo;
    }

    public static function setUpBeforeClass(): void
    {
        $db = static::getPDO();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        match($driver) {
            'sqlite' => $db->exec(DbRecordTest::SETUP_SQLITE),
            'mysql' => $db->exec(DbRecordTest::SETUP_MYSQL),
            'dblib' => $db->exec(DbRecordTest::SETUP_MSSQL),
            'pgsql' => $db->exec(DbRecordTest::SETUP_POSTGRESQL),
            default => throw new RuntimeException('Unknown database driver')
        };
    }

    public static function tearDownAfterClass(): void
    {
        $db = static::getPDO();
        $db->exec('DROP TABLE contact');
    }

    protected function setUp(): void
    {
        $db = static::getPDO();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        $sqliteSetup = function(PDO $db) {
            $db->exec('DELETE FROM contact');
            $db->exec("DELETE FROM SQLITE_SEQUENCE WHERE name='contact'"); // Reset primaryu key
        };

        match($driver) {
            'sqlite' => $sqliteSetup($db),
            'mysql' => $db->exec('TRUNCATE contact'),
            'dblib' => $db->exec('TRUNCATE TABLE contact'),
            'pgsql' => $db->exec('TRUNCATE TABLE contact RESTART IDENTITY'),
            default => throw new RuntimeException('Unknown database driver')
        };
    }

    protected function createContact($className): object
    {
        $contact = TestContext::getContainer()?->get($className);
        $contact->firstname = 'Sylvain';
        $contact->lastname = 'Philip';
        $contact->order = 1; // order is a reserved word
        $contact->income = 0;
        $contact->save();

        return $contact;
    }

    public static function contactProvider(): array
    {
        return [
            [Contact::class],
            [ContactLegacy::class]
        ];
    }

    public function testWithNullDb(): void
    {
        $this->expectException(TypeError::class);
        new Contact(null);
    }

    public function testWithWrongDb(): void
    {
        $this->expectException(TypeError::class);
        new Contact(new \DateTime());
    }

    public function testWithMissingTableName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The table name is not defined.");

        new class(self::getPDO()) extends \Piko\DbRecord {};
    }

    public function testWithMissingSchema(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No table schema defined.");

        new class(self::getPDO()) extends \Piko\DbRecord {
            protected string $tableName = 'contact';
        };
    }

    #[DataProvider('contactProvider')]
    public function testCreate($className): void
    {
        $contact = $this->createContact($className);
        $this->assertEquals(1, $contact->id);
        $this->assertEquals('Sylvain', $contact->firstname);
        $this->assertEquals('Philip', $contact->lastname);
    }

    public function testLoadWithWrongPrimaryKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/primary key contact_id is not defined/');
        $contact = TestContext::getContainer()?->get(Contact2::class);
    }

    #[DataProvider('contactProvider')]
    public function testWrongColumnAccess($className): void
    {
        $contact = $this->createContact($className);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('email is not in the table schema.');
        $contact->email;
    }

    #[DataProvider('contactProvider')]
    public function testIsset($className): void
    {
        $contact = $this->createContact($className);
        $this->assertTrue(isset($contact->order));
        $this->assertFalse(isset($contact->email));
    }

    #[DataProvider('contactProvider')]
    public function testUpdate($className): void
    {
        $this->createContact($className);
        $contact = TestContext::getContainer()?->get($className);
        $contact->load(1);
        $this->assertEquals('Sylvain', $contact->firstname);

        $contact->firstname .= ' updated';
        $contact->save();

        $contact = TestContext::getContainer()?->get($className);
        $contact->load(1);
        $this->assertEquals('Sylvain updated', $contact->firstname);
    }

    public function testUpdateWithStringPrimaryKey(): void
    {
        $db = self::getPDO();
        $st = $db->prepare('INSERT INTO contact (firstname, lastname) VALUES (:firstname, :lastname)');
        $st->bindValue(':firstname', 'pk_string', PDO::PARAM_STR);
        $st->bindValue(':lastname', 'Before', PDO::PARAM_STR);
        $st->execute();

        $contact = TestContext::getContainer()?->get(ContactStringPk::class);
        $contact->load('pk_string');
        $this->assertSame('Before', $contact->lastname);

        $contact->lastname = 'After';
        $this->assertTrue($contact->save());

        $reloaded = TestContext::getContainer()?->get(ContactStringPk::class);
        $reloaded->load('pk_string');

        $this->assertSame('After', $reloaded->lastname);
    }

    #[DataProvider('contactProvider')]
    public function testBeforeSave($className): void
    {
        $contact = $this->createContact($className);
        $contact->on(BeforeSaveEvent::class, function(BeforeSaveEvent $event) {
            $event->record->name = $event->record->firstname . ' ' . $event->record->lastname;
        });
        $this->assertTrue($contact->save());
        $this->assertEquals('Sylvain Philip', $contact->name);
    }

    #[DataProvider('contactProvider')]
    public function testBeforeSaveFalse($className): void
    {
        $contact = $this->createContact($className);
        $contact->on(BeforeSaveEvent::class, function(BeforeSaveEvent $event) {
            $event->isValid = false;
        });
        $this->assertFalse($contact->save());
    }

    #[DataProvider('contactProvider')]
    public function testDelete($className): void
    {
        $contact = $this->createContact($className);
        $this->assertEquals(1, $contact->id);
        $contact->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error while trying to load item 1');
        $contact = TestContext::getContainer()?->get($className);
        $contact->load(1);
    }

    #[DataProvider('contactProvider')]
    public function testDeleteNotLoaded($className): void
    {
        $contact = TestContext::getContainer()?->get($className);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Item cannot be deleted because it is not loaded.');
        $contact->delete();
    }

    #[DataProvider('contactProvider')]
    public function testBeforeDelete($className): void
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
    public function testModelValidation($className): void
    {
        $model = TestContext::getContainer()?->get($className);

        $this->assertFalse($model->isValid());

        $errors = $model->getErrors();

        $this->assertArrayHasKey('firstname', $errors);
        $this->assertArrayHasKey('lastname', $errors);

        $model = TestContext::getContainer()?->get($className);

        $model->firstname = 'John';
        $model->lastname = 'Lennon';

        $this->assertTrue($model->isValid());
    }

    #[DataProvider('contactProvider')]
    public function testModelBind($className): void
    {
        $model = TestContext::getContainer()?->get($className);
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
    public function testModelBindWithStringValues($className): void
    {
        $model = TestContext::getContainer()?->get($className);

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
    public function testModelBindWithBooleanValues($className): void
    {
        $model = TestContext::getContainer()?->get($className);

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

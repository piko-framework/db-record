# Piko Db Record

[![build](https://github.com/piko-framework/db-record/actions/workflows/php.yml/badge.svg)](https://github.com/piko-framework/db-record/actions/workflows/php.yml)
[![Coverage Status](https://coveralls.io/repos/github/piko-framework/db-record/badge.svg?branch=main)](https://coveralls.io/github/piko-framework/db-record?branch=main)

Piko Db Record is a lightweight Active Record implementation built on top of PDO.

It has been tested with:

- SQLite
- MySQL
- PostgreSQL
- MSSQL

## Installation

It is recommended to install Piko Db Record with Composer:

```bash
composer require piko/db-record
```

## Documentation

https://piko-framework.github.io/docs/db-record.html

## Usage

First, ensure autoloading is available:

```php
require 'vendor/autoload.php';
```

### Define your model (attributes)

```php
use Piko\DbRecord;
use Piko\DbRecord\Attribute\Table;
use Piko\DbRecord\Attribute\Column;

#[Table(name: 'contact')]
class Contact extends DbRecord
{
    #[Column(primaryKey: true)]
    public ?int $id = null;

    #[Column]
    public ?string $firstname = null;

    #[Column]
    public ?string $lastname = null;

    #[Column]
    public ?bool $active = false;
}
```

### Optional: map DB columns to different PHP property names

```php
use Piko\DbRecord;
use Piko\DbRecord\Attribute\Table;
use Piko\DbRecord\Attribute\Column;

#[Table(name: 'contact')]
class ContactMapped extends DbRecord
{
    #[Column(name: 'id', primaryKey: true)]
    public ?int $contactId = null;

    #[Column(name: 'firstname')]
    public ?string $firstName = null;

    #[Column(name: 'lastname')]
    public ?string $lastName = null;

    #[Column(name: 'active')]
    public ?bool $isActive = false;
}
```

You can then use either mapped property names (`$contact->firstName`) or underlying column names (`$contact->firstname`).

### Optional: legacy/manual schema (including string primary keys)

```php
use Piko\DbRecord;

class ContactStringPk extends DbRecord
{
    protected string $tableName = 'contact';
    protected string $primaryKey = 'firstname';

    protected array $schema = [
        'firstname' => self::TYPE_STRING,
        'lastname'  => self::TYPE_STRING,
    ];
}
```

### Setup database connection

Create a PDO instance and initialize schema:

```php
$db = new PDO('sqlite::memory:');

$query = <<<SQL
CREATE TABLE contact (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  firstname TEXT,
  lastname TEXT,
  active INTEGER DEFAULT 0
)
SQL;

$db->exec($query);
```

### Perform CRUD operations

#### Create

Create a new record and save it to the database:

```php
$contact = new Contact($db);
$contact->firstname = 'John';
$contact->lastname = 'Doe';
$contact->active = true;
$contact->save();

echo "Contact id: {$contact->id}"; // Contact id : 1
```

#### Read

```php
$contact = (new Contact($db))->load(1);

var_dump($contact->firstname); // John
```

#### Update

```php
$contact->lastname = 'Doe Jr.';
$contact->save();
```

#### Delete

```php
$contact->delete();
```

## Running tests

Run full checks (all database targets + coding standards + static analysis):

```bash
composer tests
```

Run tests for one database only:

```bash
composer phpunit:sqlite
composer phpunit:mysql
composer phpunit:pgsql
composer phpunit:mssql
```

## Support

If you encounter issues or have questions, feel free to open an issue on GitHub.

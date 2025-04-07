# ORM (PHP Attribute-Based Object-Relational Mapper)

This is a lightweight, attribute-driven ORM built for PHP 8.4+ that maps PHP classes to relational database tables using native attributes. It provides a minimal but flexible layer for working with your entities.

---

## Features

✅ Attribute-based entity mapping  
✅ Support for insert, update, delete, find, findAll, findBy, findOneBy, streamAll, and streamBy  
✅ Unit of Work pattern for efficient batching  
✅ Identity map to avoid duplicate hydration  
✅ Logging via PSR-3 (Monolog)  
✅ Custom `StreamWrapper` for reading/updating/deleting via PHP's stream API  
✅ Support for `PrimaryGeneratedColumn` (incl. UUID strategy)  
✅ Works with any PDO-compatible database

---

## Installation

```bash
composer install
```

Make sure your `.env` file includes:

```env
DB_DSN=mysql:host=localhost;dbname=orm
DB_USER=root
DB_PASSWORD=secret
APP_ENV=dev
```

---

## Usage Example

### Setup

```php
use ORM\EntityManager;
use ORM\Drivers\PDODriver;
use ORM\Logger\LoggerFactory;

$driver = PDODriver::fromEnv();
$entityManager = new EntityManager($driver, LoggerFactory::create());
```

### Example Entity

```php
#[Table(name: "users")]
class User implements JsonSerializable {
    #[Column(name: "id", type: "int", primary: true, autoIncrement: true)]
    public int $id;

    #[Column(name: "username", type: "string")]
    public string $username;
    
    #[Column(name: "email", type: "string")]
    public string $email;

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }
}
```

### Insert

```php
$user = new User();
$user->username = 'alice';
$user->email = 'alice@example.com';

$entityManager->persist($user);
$entityManager->flush();
```

### Update

```php
$user->email = 'new@example.com';
$entityManager->update($user);
$entityManager->flush();
```

### Delete

```php
$entityManager->delete($user);
$entityManager->flush();
```

### Find

```php
$user = $entityManager->find(User::class, 1);
```

---

## Stream Wrapper Usage

```php
stream_wrapper_register("orm", ORM\Stream\StreamWrapper::class);

// Read
$handle = fopen("orm://Entity\\User", "r");
while (!feof($handle)) {
    echo fgets($handle);
}
fclose($handle);

// Write (update or create)
$handle = fopen("orm://Entity\\User", "w");
fwrite($handle, json_encode(['id' => 1, 'email' => 'updated@example.com']));
fclose($handle);

// Delete
unlink("orm://Entity\\User?id=1");
```

---

## Requirements

- PHP 8.4+
- PDO extension
- Composer

---

## TODO / Ideas

- [ ] Add support for relations (OneToOne, OneToMany, etc.)
- [ ] Lazy loading with proxy/lazy-object support (PHP 8.4)
- [ ] Batch inserts / bulk updates
- [ ] Schema validation & syncing
- [ ] Add migrations support
- [ ] Caching for metadata
- [ ] Type safety & hydration strategies (e.g., enums, DateTime)
- [ ] Extend StreamWrapper to support `fseek`/`ftell`
- [ ] Add annotation support (for legacy systems)
- [ ] Improve error handling with custom exceptions
- [ ] Add unit tests for stream wrapper & UnitOfWork
- [ ] CLI Tooling (e.g., generate entities, migration runner)

---

## License

MIT


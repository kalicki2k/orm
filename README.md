# ORM (PHP Attribute-Based Object-Relational Mapper)

A fast, minimal, attribute-based ORM for PHP 8.4+, built for performance and readability.  
Powered by native attributes, a modular architecture, and zero magic.

---

## ✨ Features

✅ PHP 8.4 attributes for entity mapping  
✅ Clean architecture with responsibility-separated components  
✅ Modular QueryBuilder with pluggable builders & SQL renderers  
✅ Support for insert, update, delete, find, streamAll, streamBy  
✅ Lazy & Eager loading with FetchType enum  
✅ OneToOne support incl. JoinColumn handling  
✅ UnitOfWork with cascade persistence/removal  
✅ StreamWrapper for CRUD via PHP streams (`fopen('orm://...')`)  
✅ PSR-3 Logging (Monolog or custom)  
✅ Reflection caching for blazing speed

---

## 🧱 Installation

```bash
composer install
```

`.env`:

```dotenv
DB_DSN=mysql:host=localhost;dbname=orm
DB_USER=root
DB_PASSWORD=secret
```

---

## 🔧 Usage

```php
use ORM\Drivers\PDODriver;
use ORM\Entity\EntityManager;
use ORM\Logger\LoggerFactory;

$entityManager = new EntityManager(
    PDODriver::fromEnv(),
    new \ORM\Metadata\MetadataParser(),
    LoggerFactory::create()
);
```

---

## 👤 Example Entity

```php
#[Entity]
#[Table("users")]
class User extends EntityBase {
    #[Id]
    #[GeneratedValue]
    #[Column(type: "int")]
    private int $id;

    #[Column(type: "string")]
    private string $username;

    #[Column(type: "string", default: "test@example.com")]
    private string $email;

    #[OneToOne(entity: Profile::class)]
    #[JoinColumn(name: "profile_id", referencedColumn: "id")]
    private Profile|Closure $profile;

    public function getProfile(): Profile {
        if ($this->profile instanceof Closure) {
            $this->profile = ($this->profile)();
        }
        return $this->profile;
    }

    public function jsonSerialize(): mixed {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }
}
```

---

## 🔄 CRUD Operations

```php
// Insert
$user = new User();
$user->setUsername("neo");
$user->setEmail("neo@matrix.io");
$entityManager->persist($user);
$entityManager->flush();

// Update
$user->setEmail("trinity@zion.com");
$entityManager->update($user);
$entityManager->flush();

// Delete
$entityManager->delete($user);
$entityManager->flush();

// Find
$found = $entityManager->findBy(User::class, 1);
```

---

## 🔁 StreamWrapper

```php
stream_wrapper_register("orm", ORM\Stream\StreamWrapper::class);

// Read
$h = fopen("orm://Entity\\User?format=json", "r");
while (!feof($h)) echo fgets($h);
fclose($h);

// Write
$h = fopen("orm://Entity\\User", "w");
fwrite($h, json_encode(['id' => 1, 'email' => 'updated@example.com']));
fclose($h);

// Delete
unlink("orm://Entity\\User?id=1");
```

---

## 🧱 Architecture

| Component | Responsibility |
|----------|----------------|
| `EntityManager` | orchestrates all ORM operations |
| `UnitOfWork` | tracks inserts/updates/deletes with cascade handling |
| `MetadataParser` | reads PHP attributes into metadata |
| `QueryBuilder` | fluent API for query construction |
| `InsertBuilder` etc. | builds metadata-based query contexts |
| `SelectSqlRenderer` etc. | renders SQL based on QueryBuilder state |
| `StreamWrapper` | CRUD via PHP stream API |
| `ReflectionCacheInstance` | optimizes performance by caching reflection |

---

## 🧪 Requirements

- PHP 8.4+
- PDO
- Composer

---

## 🧠 What's next?

- [x] Lazy & Eager loading
- [x] JoinColumn + mappedBy logic
- [x] Modular QueryBuilder
- [x] SQL Renderer Strategy
- [ ] OneToMany / ManyToOne / ManyToMany
- [ ] QueryContext abstraction
- [ ] CLI tooling (generate entities, run migrations)
- [ ] Schema sync / migration diffing
- [ ] Type coercion (enum, datetime, uuid, etc.)
- [ ] Soft deletes
- [ ] ExpressionBuilder for where clauses
- [ ] Test coverage for UoW + Hydrators + Builders

---

## 📄 License

MIT
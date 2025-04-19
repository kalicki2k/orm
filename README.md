
# ORM (PHP Attribute-Based Object-Relational Mapper)

A fast, minimal, attribute-based ORM for PHP 8.4+, built for performance and readability.  
Powered by native attributes, a modular architecture, and zero magic.

---

## ✨ Features

✅ PHP 8.4 attributes for entity mapping  
✅ Clean architecture with responsibility-separated components  
✅ Modular QueryBuilder with pluggable builders & SQL renderers  
✅ `ExpressionBuilder` for powerful WHERE conditions  
✅ Support for insert, update, delete, find, streamAll, streamBy, countBy  
✅ Lazy & Eager loading with FetchType enum  
✅ OneToOne support incl. JoinColumn handling  
✅ UnitOfWork with cascade persistence/removal  
✅ StreamWrapper for CRUD via PHP streams (`fopen('orm://...')`)  
✅ PSR-3 Logging (Monolog or custom)  
✅ Reflection caching via swappable `ReflectionCache`  
✅ Metadata caching via pluggable interface (PSR-16 compatible)  
✅ Entity identity caching via pluggable `EntityCache`

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
use ORM\Cache\InMemoryMetadataCache;
use ORM\Metadata\MetadataParser;

$entityManager = new EntityManager(
    PDODriver::default(),
    new MetadataParser(new InMemoryMetadataCache()),
    LoggerFactory::create()
);
```

To enable Redis cache:

```php
use ORM\Cache\RedisMetadataCache;

$entityManager = new EntityManager(
    PDODriver::fromEnv(),
    new MetadataParser(new RedisMetadataCache()),
    LoggerFactory::create()
);
```

Or switch at runtime:

```php
$parser = (new MetadataParser())->withCache(new RedisMetadataCache());
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

## 🔍 Advanced Queries with ExpressionBuilder

```php
use ORM\Query\Expression;

$expr = Expression::and()
    ->andLike("email", "%@example.com")
    ->orEq("username", "admin")
    ->andBetweenExclusive("age", 18, 65)
    ->andNotIn("status", ["banned", "disabled"]);

$count = $entityManager->countBy(User::class, $expr);
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

| Component             | Responsibility                                   |
|-----------------------|--------------------------------------------------|
| `EntityManager`       | orchestrates all ORM operations                  |
| `UnitOfWork`          | tracks inserts/updates/deletes with cascades     |
| `MetadataParser`      | reads PHP attributes into metadata               |
| `QueryBuilder`        | fluent API for query construction                |
| `*Builder`            | builds query context based on metadata           |
| `*SqlRenderer`        | renders SQL based on QueryBuilder                |
| `StreamWrapper`       | enables PHP stream API for ORM                   |
| `ReflectionCache`     | pluggable strategy for caching reflection        |
| `MetadataCache`       | pluggable cache layer for parsed metadata        |
| `EntityCache`         | identity map for caching hydrated entities       |
| `Expression`          | powerful WHERE clause construction               |

---

## 🧪 Requirements

- PHP 8.4+
- PDO
- Composer
- Optional: Redis / PSR-16 cache pool

---

## 🧠 What's next?

- [x] Lazy & Eager loading  
- [x] JoinColumn + mappedBy logic  
- [x] Modular QueryBuilder  
- [x] SQL Renderer Strategy  
- [x] Redis + PSR-16 metadata cache support  
- [x] ReflectionCache abstraction  
- [x] Entity identity cache via `EntityCache`  
- [x] ExpressionBuilder (v1)  
- [ ] OneToMany / ManyToOne / ManyToMany  
- [ ] CLI tooling (generate entities, run migrations)  
- [ ] Schema sync / migration diffing  
- [ ] Type coercion (enum, datetime, uuid, etc.)  
- [ ] Soft deletes  
- [ ] Advanced SQL Expressions (JSON, MATCH, HAVING, etc.)  
- [ ] Test coverage for UnitOfWork, Hydrators, Builders ...

---

## 📄 License

MIT

# ORM (PHP Attribute-Based Object-Relational Mapper)

A fast, minimal, attribute-based ORM for PHP 8.4+, built for performance and readability.  
Powered by native attributes, a modular architecture, and zero magic.

---

## ✨ Features

✅ PHP 8.4 attributes for entity & relation mapping  
✅ Clean architecture with responsibility-separated components  
✅ Modular QueryBuilder with pluggable builders & SQL renderers  
✅ `ExpressionBuilder` for powerful WHERE conditions  
✅ Support for `insert`, `update`, `delete`, `find`, `streamAll`, `streamBy`, `countBy`  
✅ Default Lazy loading with `FetchType::Lazy`  
✅ Optional Eager loading via `FetchType::Eager` and `joins => [...]`  
✅ OneToOne support with `JoinColumn` mapping and Closure-based lazy hydration  
✅ OneToMany & ManyToOne hydration with alias-based eager & deferred strategies  
✅ Full ManyToMany support: JoinTable mapping, lazy closures & eager join hydration  
✅ Cascade persistence & removal via `CascadeType` in UnitOfWork  
✅ Alias-based hydration with safe reflection and type conversion  
✅ StreamWrapper for CRUD via native PHP streams (`fopen('orm://...')`)  
✅ PSR-3 logging integration (e.g. Monolog or custom logger)  
✅ Reflection caching via swappable `ReflectionCache` interface  
✅ Metadata caching via pluggable PSR-16 compatible cache (e.g. Redis, Filesystem)  
✅ Entity identity caching with injectable `EntityCache` implementation

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

// Use Redis for metadata caching
$entityManager = new EntityManager(
    PDODriver::fromEnv(),
    new MetadataParser(new RedisMetadataCache()),
    LoggerFactory::create()
);
```

Or switch at runtime:
```php
use ORM\Metadata\MetadataParser;
use ORM\Cache\RedisMetadataCache;

// Add Redis dynamically to an existing parser
$parser = (new MetadataParser())->with(new RedisMetadataCache());
```

---

## 👤 Example Entity

```php
#[Entity]
#[Table("users")]
class User extends EntityBase
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: "int")]
    private int $id;

    #[Column(type: "string", length: 255)]
    private string $username;

    #[Column(type: "string", default: "test@example.com")]
    private string $email;

    #[OneToOne(
        entity: Profile::class,
        fetch: FetchType::Lazy,
        cascade: [CascadeType::Persist, CascadeType::Remove]
    )]
    #[JoinColumn(name: "profile_id", referencedColumn: "id")]
    private Profile|Closure $profile;

    public function getProfile(): Profile
    {
        if ($this->profile instanceof Closure) {
            $this->profile = ($this->profile)();
        }
        return $this->profile;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'profile' => $this->getProfile()
        ];
    }
}
```

## 🔄 Join Example

To eagerly fetch a relation (e.g., `profile`), pass `joins` into `findBy`:

```php
$user = $entityManager->findBy(User::class, 1, [
    'joins' => ['profile']
]);
```

## ✅ Fetch Behavior

- Lazy is default for all relations
- Eager loading requires:
    - `fetch: FetchType::Eager` in entity
    - AND explicit `joins => [...]` in query
- Lazy hydration via Closure
- Eager hydration via JOIN + aliased columns

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

| Component             | Responsibility                                                                  |
|-----------------------|---------------------------------------------------------------------------------|
| `EntityManager`       | orchestrates all ORM operations                                                 |
| `UnitOfWork`          | tracks inserts/updates/deletes with cascades                                    |
| `MetadataParser`      | reads PHP attributes into metadata                                              |
| `QueryBuilder`        | fluent API for query construction                                               |
| `*Builder`            | builds query context based on metadata                                          |
| `*SqlRenderer`        | renders SQL based on QueryBuilder                                               |
| `StreamWrapper`       | enables PHP stream API for ORM                                                  |
| `ReflectionCache`     | pluggable strategy for caching reflection                                       |
| `MetadataCache`       | pluggable cache layer for parsed metadata                                       |
| `EntityCache`         | identity map for caching hydrated entities                                      |
| `RelationHydrator`    | plugs in lazy & eager strategies for OneToOne, OneToMany, ManyToOne, ManyToMany |
| `FetchType`           | controls default vs. explicit JOIN behavior                                     |
| `Expression`          | powerful WHERE clause construction                                              |

---

## 🧪 Requirements

- PHP 8.4+
- PDO
- Composer
- Optional: Redis / PSR-16 cache pool

---

## 🧠 What's next?

- [x] Lazy & Eager loading (FetchType + Closure hydration)  
- [x] JoinColumn + mappedBy logic (owning & inverse side handled)  
- [x] Modular QueryBuilder (select, join, where, options)  
- [x] SQL Renderer Strategy  
- [x] Redis + PSR-16 metadata cache support  
- [x] ReflectionCache abstraction  
- [x] Entity identity cache via `EntityCache`  
- [x] ExpressionBuilder (v1)  
- [x] Alias-based column hydration  
- [x] JOINs only on demand via `joins => [...]`  
- [x] Safe fallback for uninitialized virtual props  
- [x] OneToMany / ManyToOne / ManyToMany (full support incl. JoinTable, Lazy, Eager)  
- [ ] CLI tooling (generate entities, run migrations)  
- [ ] Schema sync / migration diffing  
- [ ] Type coercion (enum, datetime, uuid, etc.)  
- [ ] Soft deletes  
- [ ] Advanced SQL Expressions (JSON, MATCH, HAVING, etc.)  
- [ ] Test coverage for UnitOfWork, Hydrators, Builders ...

---

## 📄 License

MIT

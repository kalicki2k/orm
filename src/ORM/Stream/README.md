# ORM StreamWrapper

The `ORM\Stream\StreamWrapper` allows you to interact with your ORM entity classes using native PHP stream functions like `fopen()`, `fgets()`, `fwrite()`, and `unlink()`.

This means you can read from, write to, update, and delete entities using `orm://` URLs as if they were files. It adds a powerful abstraction layer over your database access.

---

## Features

- Read entities lazily (`fopen` + `fgets`)
- Create new entities using `fwrite` (no primary key in input)
- Update existing entities using `fwrite` (primary key required)
- Delete entities using `unlink`
- Native support for JSON serialization (`JsonSerializable`)
- Entity filtering using query string (`?status=active`)

---

## Registering the wrapper

```php
stream_wrapper_register("orm", \ORM\Stream\StreamWrapper::class);
```

---

## Usage

### Read (streamAll or streamBy)

```php
// Read all users
$handle = fopen("orm://Entity\\User", "r");

while (!feof($handle)) {
    echo fgets($handle);
}

fclose($handle);
```

```php
// Read active users only
$handle = fopen("orm://Entity\\User?status=active", "r");
```

### Create or Update (via `fwrite`)

```php
// Update existing user (requires primary key in input)
$handle = fopen("orm://Entity\\User", "w");
fwrite($handle, json_encode([
    'id' => 1,
    'email' => 'new@example.com'
]));
fclose($handle);
```

```php
// Create new user (omit primary key, it will be generated)
$handle = fopen("orm://Entity\\User", "w");
fwrite($handle, json_encode([
    'username' => 'alice',
    'email' => 'alice@example.com'
]));
fclose($handle);
```

> The system detects whether to insert or update based on presence of the primary key.

### Delete (via `unlink()`)

```php
unlink("orm://Entity\\User?id=1");
```

---

## Requirements

- The entity class **must exist and be autoloaded**.
- The entity must be annotated with `#[Table]` and `#[Column]`.
- You should implement `JsonSerializable` if you want custom JSON output.

---

## Examples

### Example Entity

```php
#[Table(name: "users")]
class User implements JsonSerializable {
    #[Column(name: "id", type: "int", primary: true, autoIncrement: true)]
    public int $id;

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

---

## Tips

- Use `stream_get_contents("orm://...", "r")` for quick reads.
- For testing, register the stream wrapper globally in `bootstrap.php` or `index.php`.
- Extend functionality for formats (e.g. YAML, CSV) via additional `Accept`-style query params.

---

## See also

- [`EntityManager::streamBy()`](https://github.com/kalicki2k/orm/blob/main/src/ORM/EntityManager.php#L536)
- [`EntityManager::persist()`](https://github.com/kalicki2k/orm/blob/main/src/ORM/EntityManager.php#L163)
- [`EntityManager::update()`](https://github.com/kalicki2k/orm/blob/main/src/ORM/EntityManager.php#L203)
- [`EntityManager::delete()`](https://github.com/kalicki2k/orm/blob/main/src/ORM/EntityManager.php#L243)
- [PHP Manual: stream_wrapper_register](https://www.php.net/manual/en/function.stream-wrapper-register.php)


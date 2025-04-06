# ORM StreamWrapper

The `ORM\Stream\StreamWrapper` allows you to interact with your ORM entity classes using native PHP stream functions like `fopen()`, `fgets()`, `fwrite()` and `unlink()`.

This means you can read from and write to your entities using `orm://` URLs as if they were files. It adds a powerful abstraction layer over your database access.

---

## Features

- Read entities lazily (`fopen` + `fgets`)
- Update entities using `fwrite`
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

### Update (via `fwrite`)

```php
$handle = fopen("orm://Entity\\User", "w");
fwrite($handle, json_encode([
    'id' => 1,
    'email' => 'new@example.com'
]));
fclose($handle);
```

> Make sure your entity supports updates by setting the primary key in the input JSON.

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

- Use `stream_all_contents("orm://...", "r")` for quick reads.
- For testing, register the stream wrapper globally in `bootstrap.php` or `index.php`.
- Extend functionality for formats (e.g. YAML, CSV) via additional `Accept`-style query params.

---

## See also

- [`EntityManager::streamBy()`](#)
- [`EntityManager::update()`](#)
- [`EntityManager::delete()`](#)
- [PHP Manual: stream_wrapper_register](https://www.php.net/manual/en/function.stream-wrapper-register.php)


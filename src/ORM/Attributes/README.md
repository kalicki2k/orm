## 🔄 CRUD Operations

```php
// Create
$user = new User();
$user->setUsername('alice')->setEmail('alice@example.com');

$em->persist($user);
$em->flush();

// Update
$user->setEmail('alice@wonderland.com');
$em->update($user);
$em->flush();

// Delete
$em->delete($user);
$em->flush();

// Find by Primary Key
$fetched = $em->findBy(User::class, 1);

// Find all with options
$allUsers = $em->findAll(User::class, null, ['orderBy' => ['username' => 'ASC']]);

// Count
$count = $em->countBy(Post::class);

// Stream
foreach ($em->streamAll(Post::class) as $post) {
    echo $post->getTitle(), PHP_EOL;
}
```

---

## 🔍 ExpressionBuilder

```php
use ORM\Query\Expression;

$expr = Expression::and()
    ->andEq('status', 'published')
    ->andLike('title', '%ORM%')
    ->orGt('created_at', '2025-01-01');

$posts = $em->findBy(Post::class, $expr);
```

---

## 👤 Example Entities

## User

```php
#[Entity]
#[Table('users')]
class User extends EntityBase
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'int')]
    private int $id;

    #[Column(type: 'string', length: 255)]
    private string $username;

    #[Column(type: 'string', length: 255, default: 'default_email@example.com')]
    private string $email;

    #[OneToOne(entity: Profile::class, cascade: [CascadeType::Persist, CascadeType::Remove], fetch: FetchType::Eager)]
    #[JoinColumn(name: 'profile_id', referencedColumn: 'id', nullable: false)]
    private Profile|Closure|null $profile = null;

    #[OneToMany(entity: Post::class, mappedBy: 'user', cascade: [CascadeType::Persist, CascadeType::Remove], fetch: FetchType::Eager)]
    private Collection|Closure $posts;

    public function __construct() {
        $this->posts = new Collection();
    }

    // getters/setters ...
}
```  

## Profile

```php
#[Entity]
#[Table('profiles')]
class Profile extends EntityBase
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'int', name: 'id', nullable: false)]
    private int $id;

    #[Column(type: 'text', name: 'bio', nullable: true)]
    private ?string $bio = null;

    #[OneToOne(entity: User::class, mappedBy: 'profile', fetch: FetchType::Eager)]
    private Closure|User|null $user = null;

    // getters/setters ...
}
```  

## Post

```php
#[Entity]
#[Table('posts')]
class Post extends EntityBase
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'int')]
    private int $id;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[Column(type: 'text')]
    private string $content;

    #[ManyToOne(entity: User::class, fetch: FetchType::Eager)]
    #[JoinColumn(name: 'user_id', referencedColumn: 'id', nullable: false)]
    private User|Closure $user;

    // getters/setters ...
}
```  

---

## 📄 License

MIT License.
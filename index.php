<?php

use Dotenv\Dotenv;
use Entity\Post;
use Entity\Profile;
use Entity\Role;
use Entity\User;
use ORM\Cache\RedisMetadataCache;
use ORM\Drivers\PDODriver;
use ORM\Entity\EntityManager;
use ORM\Logger\LoggerFactory;
use ORM\Metadata\MetadataParser;
use ORM\Query\Expression;

require_once 'vendor/autoload.php';

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup services
$redis = new Redis();
$redis->connect('redis', 6379);
$cache = new RedisMetadataCache($redis);

$driver = PDODriver::default();
$logger = LoggerFactory::create();
$parser = new MetadataParser();//->with($cache);

$entityManager = new EntityManager($driver, $parser, $logger);

##### CREATE #####

$role = new Role();
$role->setName("Administrator");

$post1 = new Post();
$post1->setTitle("ORMs sind magisch!");
$post1->setContent("Besonders mit Cascade und Dependency Ordering.");

$post2 = new Post();
$post2->setTitle("Phase 2 incoming");
$post2->setContent("OneToMany rules the game.");

$profile = new Profile();
$profile->setBio('Ich liebe saubere ORMs!');

$user = new User();
$user->setUsername('john_doe');
$user->setEmail('john@example.com');
$user->setProfile($profile); // <- OneToOne Verknüpfung

$user->addPost($post1);
$user->addPost($post2);

$user->addRole($role);

$entityManager->persist($user);
$entityManager->flush();

echo "✅ User und Profile gespeichert!\n";
echo "User-ID: " . $user->getId() . "\n";
echo "Profile-ID: " . $user->getProfile()->getId() . "\n";

########## UPDATE ##########

$user->setUsername('johnny_updated');
$user->getProfile()->setBio('Update: ORMs rocken noch mehr nach dem Refactor!');

$entityManager->update($user);
$entityManager->flush();

echo "📝 User und Profile wurden aktualisiert!\n";
echo "Neuer Username: " . $user->getUsername() . "\n";
echo "Neues Bio: " . $user->getProfile()->getBio() . "\n";

########## DELETE ##########
//
//$entityManager->delete($user);
//$entityManager->flush();

echo "❌ User und Profile gelöscht!\n";

########## STREAM ##########

echo "Alle User (streamAll):\n";

foreach ($entityManager->findAll(
    User::class,
    null,
    ["joins" => ["profile", "posts", "roles"]]
) as $user) {
    echo "User #{$user->getId()}: {$user->getUsername()} <{$user->getEmail()}>\n";

    $profile = $user->getProfile();
    echo "  Profile: ID={$profile->getId()}, Bio=\"{$profile->getBio()}\"\n";

    $count = count($user->getPosts());
    echo "  Posts ({$count}):\n";
    foreach ($user->getPosts() as $post) {
        echo "    • [{$post->getId()}] {$post->getTitle()} – {$post->getContent()}\n";
    }

    $roles = $user->getRoles();
    $countRoles = count($roles);
    echo "  Roles ($countRoles):\n";
    foreach ($roles as $role) {
        echo "    • [{$role->getId()}] {$role->getName()}\n";
    }

    echo str_repeat('-', 40) . "\n";
}

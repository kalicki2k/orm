<?php

use Dotenv\Dotenv;
use Entity\Post;
use Entity\Profile;
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
$parser = new MetadataParser(); // ->with($cache);

$entityManager = new EntityManager($driver, $parser, $logger);

##### CREATE #####

//$postFound = new Post();
//$postFound->setTitle("ORMs sind magisch!");
//$postFound->setContent("Besonders mit Cascade und Dependency Ordering.");
//
//$post2 = new Post();
//$post2->setTitle("Phase 2 incoming");
//$post2->setContent("OneToMany rules the game.");
//
//$profile = new Profile();
//$profile->setBio('Ich liebe saubere ORMs!');
//
//$user = new User();
//$user->setUsername('john_doe');
//$user->setEmail('john@example.com');
//$user->setProfile($profile); // <- OneToOne Verknüpfung
//
//$user->addPost($postFound);
//$user->addPost($post2);
//
//$entityManager->persist($user); // sollte via Cascade auch das Profile persistieren
//$entityManager->flush();

//echo "✅ User und Profile gespeichert!\n";
//echo "User-ID: " . $user->getId() . "\n";
//echo "Profile-ID: " . $user->getProfile()->getId() . "\n";

##########

##### FindBy Profile and User ######

//$profile = $entityManager->findBy(Profile::class, 1, ["joins" => ["user"]]);
//
//echo "Profile-ID: " . $profile->getId() . "\n";
//echo "Profile-Bio: " . $profile->getBio() . "\n";
//echo "User-ID: " . $profile->getUser()->getId() . "\n";
//echo "Username: " . $profile->getUser()->getUsername() . "\n";

##########

##### FindBy User and Profile


//$user = $entityManager->findBy(User::class, 1, ["joins" => ["profile"]]);
//
//echo "User-ID: " . $user->getId() . "\n";
//echo "Profile-Bio: " . $user->getProfile()->getBio() . "\n";

##########

##### FindBy User #####

$userFound = $entityManager->findBy(User::class, 1, ["joins" => ["profile", "posts"]]);
echo "User: " . $userFound->getUsername() . "\n";

$profileFound = $userFound->getProfile();
echo "Profile ID: " . $profileFound->getId() . "\n";
echo "Bio: " . $profileFound->getBio() . "\n";

echo "Posts:\n";

foreach ($userFound->getPosts() as $postFound) {
    echo "- " . $postFound->getTitle() . " (" . $postFound->getContent() . ")\n";
}

$result = $entityManager->findAll(User::class, null, ["joins" => ["profile", "posts"]]);

foreach ($result as $user) {
    // User‑Grundeigenschaften
    echo "User #{$user->getId()}: {$user->getUsername()} <{$user->getEmail()}>\n";

    // Profil (OneToOne)
    $profile = $user->getProfile();
    echo "  Profile: ID={$profile->getId()}, Bio=\"{$profile->getBio()}\"\n";

    // Posts (OneToMany)
    $count = count($user->getPosts());
    echo "  Posts ({$count}):\n";
    foreach ($user->getPosts() as $post) {
        echo "    • [{$post->getId()}] {$post->getTitle()} – {$post->getContent()}\n";
    }

    echo str_repeat('-', 40) . "\n";
}

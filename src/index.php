<?php

use Dotenv\Dotenv;
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

$profile = new Profile();
$profile->setBio('Ich liebe saubere ORMs!');

$user = new User();
$user->setUsername('john_doe');
$user->setEmail('john@example.com');
$user->setProfile($profile); // <- OneToOne Verknüpfung

$entityManager->persist($user); // sollte via Cascade auch das Profile persistieren
$entityManager->flush();

echo "✅ User und Profile gespeichert!\n";
echo "User-ID: " . $user->getId() . "\n";
echo "Profile-ID: " . $user->getProfile()->getId() . "\n";

$user = $entityManager->findBy(User::class, 1, ["joins" => ["profile"]]);
echo "User: " . $user->getUsername() . "\n";

$profile = $user->getProfile();
echo "Profile ID: " . $profile->getId() . "\n";
echo "Bio: " . $profile->getBio() . "\n";
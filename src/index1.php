<?php

use Dotenv\Dotenv;
use Entity\Profile;
use Entity\User;
use ORM\Cache\RedisMetadataCache;
use ORM\Drivers\PDODriver;
use ORM\Entity\EntityManager;
use ORM\Logger\LoggerFactory;
use ORM\Metadata\MetadataParser;
use ORM\Stream\StreamWrapper;

require_once 'vendor/autoload.php';

// Load environment variables (optional)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup Redis
$redis = new Redis();
$redis->connect('redis', 6379);
$cache = new RedisMetadataCache($redis);

// Setup ORM
$driver = PDODriver::default();
$logger = LoggerFactory::create();
$parser = new MetadataParser(); //->with($cache);
$em = new EntityManager($driver, $parser, $logger);

// CREATE
$profile = new Profile()->setBio("???");
$user = new User()
    ->setUsername("alice")
    ->setEmail("alice@redis.dev")
    ->setProfile($profile);
$em->persist($user)->flush();
echo "✅  Created user ID: " . $user->getId() . PHP_EOL;

// READ
$found = $em->findBy(User::class, $user->getId());
echo "👀 Read: " . $found->getUsername() . PHP_EOL;

// UPDATE
$found->setEmail("updated@redis.dev");
$em->update($found)->flush();
echo "✏️ Updated user ID: " . $found->getId() . PHP_EOL;

// DELETE
$em->delete($found)->flush();
echo "❌  Deleted user ID: " . $found->getId() . PHP_EOL;

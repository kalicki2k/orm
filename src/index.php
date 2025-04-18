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
$parser = new MetadataParser()->with($cache);
$em = new EntityManager($driver, $parser, $logger);

// --- CREATE multiple ---
echo PHP_EOL . "🛠 CREATING USERS..." . PHP_EOL;

$users = [];
for ($i = 1; $i <= 5; $i++) {
    $profile = (new Profile())->setBio("Bio for user $i");
    $user = (new User())
        ->setUsername("user$i")
        ->setEmail("user$i@example.com")
        ->setProfile($profile);
    $em->persist($user);
    $users[] = $user;
}
$em->flush();
echo "✅  Created " . count($users) . " users" . PHP_EOL;

// --- FIND SINGLE ---
echo PHP_EOL . "🔍 FIND ONE (by ID)" . PHP_EOL;
$one = $em->findBy(User::class, $users[2]->getId());
echo "🎯 Found user {$one->getUsername()} ({$one->getEmail()})" . PHP_EOL;

// --- COUNT ALL ---
echo PHP_EOL . "🔢 COUNT ALL USERS" . PHP_EOL;
$countAll = $em->countBy(User::class);
echo "📊 Total users: $countAll" . PHP_EOL;

// --- COMPLEX EXPRESSION ---
echo PHP_EOL . "🔍 COMPLEX EXPRESSION" . PHP_EOL;
$complex = Expression::or()
    ->andEq('username', 'user1')
    ->orLike('email', '%@example.com');
foreach ($em->streamBy(User::class, $complex) as $u) {
    echo "- 🎯 {$u->getUsername()} => {$u->getEmail()}" . PHP_EOL;
}

// --- PERFORMANCE TEST ---
echo PHP_EOL . "🚀 PERFORMANCE TEST (create + stream 1000)" . PHP_EOL;
$start = microtime(true);
for ($i = 1000; $i < 2000; $i++) {
    $em->persist(
        (new User())
            ->setUsername("batch_user_$i")
            ->setEmail("batch_user_$i@load.test")
            ->setProfile((new Profile())->setBio("Batch bio $i"))
    );
}
$em->flush();
echo "✅  Inserted 1000 users in " . round(microtime(true) - $start, 3) . "s" . PHP_EOL;

// --- STREAM BATCH TEST ---
echo PHP_EOL . "🔁 STREAM (batch users only)" . PHP_EOL;
$batchExpr = Expression::and()->andLike('email', '%@load.test');
$streamed = 0;
foreach ($em->streamBy(User::class, $batchExpr, ['limit' => 10]) as $batchUser) {
    echo "- {$batchUser->getUsername()}" . PHP_EOL;
    if (++$streamed >= 10) break;
}

// --- CLEANUP ---
echo PHP_EOL . "🧹 DELETE TEST USERS" . PHP_EOL;
foreach ($users as $u) {
    $em->delete($u);
}
$em->flush();
echo "🗑️  Deleted initial users" . PHP_EOL;

// CREATE Test-User
$profile = (new Profile())->setBio("Testing profile");
$user = (new User())
    ->setUsername("alice")
    ->setEmail("alice+expr@demo.dev")
    ->setProfile($profile);

$em->persist($user)->flush();

echo "✅ Created user ID: {$user->getId()}" . PHP_EOL;

// Test: andEq
echo "\n🔎 Test: andEq\n";
$expr = Expression::and()->andEq("email", $user->getEmail());
echo "Found: " . $em->findBy(User::class, $expr)?->getEmail() . PHP_EOL;

// Test: orEq
echo "\n🔎 Test: orEq\n";
$expr = Expression::or()->orEq("email", "does-not-exist@x.dev")->orEq("email", $user->getEmail());
echo "Found: " . $em->findBy(User::class, $expr)?->getEmail() . PHP_EOL;

// Test: andGt
echo "\n🔎 Test: andGt\n";
$expr = Expression::and()->andGt("id", 0);
foreach ($em->streamBy(User::class, $expr, ["limit" => 2]) as $u) {
    echo "- ID: {$u->getId()}, Email: {$u->getEmail()}" . PHP_EOL;
}

// Test: andLike
echo "\n🔎 Test: andLike\n";
$expr = Expression::and()->andLike("email", "%expr@demo.dev");
echo "Matching count: " . $em->countBy(User::class, $expr) . PHP_EOL;

// Test: orLike
echo "\n🔎 Test: orLike\n";
$expr = Expression::or()->orLike("email", "%notfound%")->orLike("email", "%expr@demo.dev");
echo "Matching count: " . $em->countBy(User::class, $expr) . PHP_EOL;

// Test: andIn
echo "\n🔎 Test: andIn\n";
$expr = Expression::and()->andIn("id", [$user->getId()]);
echo "Matching: " . $em->countBy(User::class, $expr) . PHP_EOL;

// Test: andBetween
echo "\n🔎 Test: andBetween\n";
$expr = Expression::and()->andBetween("id", $user->getId() - 1, $user->getId() + 10);
echo "Matched in range: " . $em->countBy(User::class, $expr) . PHP_EOL;

// Test: where() custom op
echo "\n🔎 Test: custom where()\n";
$expr = Expression::and()->where("!=", "email", "x@example.com");
echo "Not x@example.com: " . $em->countBy(User::class, $expr) . PHP_EOL;

// CLEANUP
echo "\n🗑️  DELETE\n";
$em->delete($user)->flush();
echo "✅ Deleted user ID: {$user->getId()}" . PHP_EOL;
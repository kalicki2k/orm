<?php

use Dotenv\Dotenv;
use Entity\Profile;
use Entity\User;
use ORM\Drivers\PDODriver;
use ORM\Entity\EntityManager;
use ORM\Logger\LoggerFactory;
use ORM\Metadata\MetadataParser;
use ORM\Stream\StreamWrapper;

require_once 'vendor/autoload.php';

// Load environment variables (optional)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

//stream_wrapper_register("orm", StreamWrapper::class);
//
//$handle = fopen("orm://Entity\\User?format=csv", "r");
//$output = '';
//
//while (!feof($handle)) {
//    $line = trim(fgets($handle));
//    if (!empty($line)) {
//        $output .= $line . "\n";
//    }
//}
//fclose($handle);
//
//
//echo "CSV Output:\n";
//echo $output;

$entityManager = new EntityManager(PDODriver::default(), new MetadataParser(), LoggerFactory::create());

//$results = $entityManager->findAll(User::class, ["profile"]);
//var_dump($results);

$user = $entityManager->findBy(User::class, 2, ["profile"]);
//var_dump($user);
var_dump($user->getProfile()->getBio());

//foreach ($entityManager->streamAll(User::class) as $u) {
//    echo "- {$u->getId()}: {$u->getUsername()}" . PHP_EOL;
//}

//
$profile = new Profile();
$profile->setBio("...");

$user = new User();
$user->setUsername("foo")->setProfile($profile);
$user->setUsername("foo")->setEmail("bar@example.com")->setProfile($profile);

$entityManager->persist($user);
$entityManager->flush();

$entityManager->delete($user);
$entityManager->flush();

//$user1 = new User();
//$user1->setUsername("alice")->setEmail("alice@example.com");
//
//$user2 = new User();
//$user2->setUsername("bob")->setEmail("bob@example.com");

//$entityManager->persist($user1);
//$entityManager->persist($user2);
//$entityManager->persist([$user1, $user2]);
//$entityManager->flush();
//var_dump($user1);
//var_dump($user2);

//$entityManager->delete($user2);
//$entityManager->flush();

//$foundAlice = $entityManager->find(User::class, ["email" => "alice@example.com"]);
//$foundBob = $entityManager->find(User::class, ["email" => "bob@example.com"]);
//
//var_dump($foundAlice); // sollte User enthalten
//var_dump($foundBob);   // sollte NULL sein

//$user = $entityManager->find(User::class, ["id" => 1]);

//var_dump($user);


//$newUser = new User();
//$newUser->setUsername("kalle")->setEmail("kalle@kalle.com");
//
//$entityManager->persist($newUser);

//$user->setUsername("kalleUpdated")->setEmail("kalleUpdated@kalle.com");
//
//$entityManager->update($user);
//$entityManager->delete($user);
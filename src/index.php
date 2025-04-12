<?php

use Dotenv\Dotenv;
use Entity\User;
use ORM\Drivers\PDODriver;
use ORM\Entity\EntityManager;
use ORM\Logger\LoggerFactory;
use ORM\Metadata\MetadataParser;

require_once 'vendor/autoload.php';

// Load environment variables (optional)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$entityManager = new EntityManager(PDODriver::default(), new MetadataParser(), LoggerFactory::create());

//$user = $entityManager->find(User::class, ["id" => 1], ["profile"]);

//var_dump($user);


$newUser = new User();
$newUser->setUsername("kalle")->setEmail("kalle@kalle.com");

$entityManager->persist($newUser);

//$user->setUsername("kalleUpdated")->setEmail("kalleUpdated@kalle.com");
//
//$entityManager->update($user);
//$entityManager->delete($user);
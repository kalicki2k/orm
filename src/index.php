<?php

use Dotenv\Dotenv;
use Entity\User;
use ORM\Drivers\PDODriver;
use ORM\EntityManager;
use ORM\Logger\LoggerFactory;
use ORM\Metadata\MetadataParser;
use ORM\Stream\StreamWrapper;

require_once 'vendor/autoload.php';

// Load environment variables (optional)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$entityManager = new EntityManager(new MetadataParser());

$entityManager->find(User::class, 1);

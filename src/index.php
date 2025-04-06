<?php

use Dotenv\Dotenv;
use Entity\User;
use ORM\Drivers\PDODriver;
use ORM\EntityManager;
use ORM\Logger\LoggerFactory;
use ORM\Stream\StreamWrapper;

require_once 'vendor/autoload.php';

// Load environment variables (optional)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Register ORM stream wrapper
stream_wrapper_register("orm", StreamWrapper::class);

echo "Creating users...\n";

// --- CREATE USERS ---

function generateUsers(int $count = 100): array {
    $users = [];
    for ($i = 1; $i <= $count; $i++) {
        $username = "user{$i}";
        $email = "{$username}@example.com";
        $users[] = [
            'username' => $username,
            'email'    => $email,
        ];
    }
    return $users;
}

$usersToCreate = generateUsers(100);

$handle = fopen("orm://Entity\\User", "x");
foreach ($usersToCreate as $user) {
    fwrite($handle, json_encode($user) . PHP_EOL);
}
fclose($handle);


// --- READ ALL USERS ---
//echo "\nReading all users...\n";
//$handle = fopen("orm://Entity\\User", "r");
//$users = [];
//
//while (!feof($handle)) {
//    $line = trim(fgets($handle));
//    if (!empty($line)) {
//        $decoded = json_decode($line, true);
//        if (is_array($decoded)) {
//            $users[] = $decoded;
//            echo "User: " . json_encode($decoded) . PHP_EOL;
//        }
//    }
//}
//fclose($handle);

$handle = fopen("orm://Entity\\User?format=xml", "r");
$xmlOutput = '';

// Lesen: Zeilenweise wird der Stream gelesen, wobei jede Zeile ein XML-Dokument einer Entität enthält.
while (!feof($handle)) {
    $line = trim(fgets($handle));
    if (!empty($line)) {
        $xmlOutput .= $line . "\n";
    }
}
fclose($handle);

echo "XML Output:\n";
echo $xmlOutput;


// --- UPDATE FIRST USER ---
if (!empty($users)) {
    $firstUser = $users[0];
    $firstUser['email'] = 'updated@example.com';

    echo "\nUpdating user with ID {$firstUser['id']}...\n";

    $handle = fopen("orm://Entity\\User", "w");
    fwrite($handle, json_encode($firstUser));
    fclose($handle);

    echo "Updated user: " . json_encode($firstUser) . PHP_EOL;
}


// --- DELETE SECOND USER ---
if (isset($users[1])) {
    $userIdToDelete = $users[1]['id'];
    echo "\nDeleting user with ID {$userIdToDelete}...\n";
    unlink("orm://Entity\\User?id={$userIdToDelete}");
    echo "Deleted user with ID {$userIdToDelete}" . PHP_EOL;
}

### Default
/*
// Create config and driver
$driver = PDODriver::default();

// Logger and EntityManager
$logger = LoggerFactory::create();
$entityManager = new EntityManager($driver, $logger);

// --- CREATE / INSERT ---
$user = new User();
$user->username = 'TestUser';
$user->email = 'test@example.com';

$entityManager->persist($user);
$entityManager->flush();

echo "Inserted User with ID: {$user->id}" . PHP_EOL;

// --- FIND / SELECT (by primary key) ---
$foundUser = $entityManager->find(User::class, $user->id);

if ($foundUser !== null) {
    echo "Found User ID {$foundUser->id} with email: {$foundUser->email}" . PHP_EOL;

    // --- UPDATE ---
    $foundUser->email = 'updated@example.com';
    $entityManager->update($foundUser);
    $entityManager->flush();

    echo "Updated User ID {$foundUser->id} with new email." . PHP_EOL;

    // --- DELETE ---
    $entityManager->delete($foundUser);
    $entityManager->flush();

    echo "Deleted User with ID: {$foundUser->id}" . PHP_EOL;
} else {
    echo "User not found." . PHP_EOL;
}

// --- FIND ALL ---
$users = $entityManager->findAll(User::class);
echo PHP_EOL . "All Users:" . PHP_EOL;
foreach ($users as $u) {
    echo "- {$u->id}: {$u->username} ({$u->email})" . PHP_EOL;
}

// --- FIND BY ---
$activeUsers = $entityManager->findBy(User::class, ['email' => 'test@example.com'], ['id' => 'DESC']);
echo PHP_EOL . "Users with email=test@example.com:" . PHP_EOL;
foreach ($activeUsers as $u) {
    echo "- {$u->id}: {$u->username}" . PHP_EOL;
}

// --- FIND ONE BY ---
$oneUser = $entityManager->findOneBy(User::class, ['username' => 'TestUser']);
if ($oneUser) {
    echo PHP_EOL . "Found one by username 'TestUser': {$oneUser->email}" . PHP_EOL;
}

// --- STREAM ALL (lazy loading) ---
echo PHP_EOL . "Streaming all users (memory-efficient):" . PHP_EOL;
foreach ($entityManager->streamAll(User::class) as $u) {
    echo "- {$u->id}: {$u->username}" . PHP_EOL;
}

// --- STREAM BY criteria ---
echo PHP_EOL . "Streaming users with email='test@example.com':" . PHP_EOL;
foreach ($entityManager->streamBy(User::class, ['email' => 'test@example.com']) as $u) {
    echo "- {$u->id}: {$u->username}" . PHP_EOL;
}
*/

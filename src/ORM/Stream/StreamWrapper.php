<?php

namespace ORM\Stream;

use InvalidArgumentException;
use JsonSerializable;
use ORM\Drivers\PDODriver;
use ORM\EntityManager;
use ORM\Logger\LoggerFactory;
use ReflectionException;

/**
 * StreamWrapper enables accessing ORM entity data through PHP stream functions such as `fopen()`, `fgets()`, `fwrite()`, and `unlink()`.
 *
 * This allows interaction with ORM-based entities using a URL-like syntax via a custom protocol (e.g. `orm://Entity\\User`).
 *
 * Supports read, write (for updates), and delete operations.
 *
 * @example Reading users
 *   stream_wrapper_register("orm", \ORM\Stream\StreamWrapper::class);
 *   $handle = fopen("orm://Entity\\User", "r");
 *   while (!feof($handle)) {
 *       echo fgets($handle);
 *   }
 *   fclose($handle);
 *
 * @example Updating a user
 *   $handle = fopen("orm://Entity\\User", "w");
 *   fwrite($handle, json_encode(['id' => 1, 'email' => 'new@example.com']));
 *   fclose($handle);
 *
 * @example Deleting a user
 *   unlink("orm://Entity\\User?id=1");
 *
 * @see https://www.php.net/manual/en/class.streamwrapper.php
 * @see EntityManager::streamBy()
 * @see EntityManager::update()
 * @see EntityManager::delete()
 */
class StreamWrapper
{
    /**
     * PHP stream context (required for stream wrappers).
     *
     * @var resource|null
     */
    public $context;

    /**
     * Generator used to lazily fetch entity records from the database.
     *
     * @var \Generator|null
     */
    private ?\Generator $generator = null;

    /**
     * Internal buffer to store serialized entities between read calls.
     *
     * @var string
     */
    private string $buffer = '';

    /**
     * The EntityManager instance used for ORM operations.
     *
     * @var EntityManager
     */
    private EntityManager $entityManager;

    /**
     * Fully-qualified class name of the entity being streamed.
     *
     * @var string
     */
    private string $entity;

    /**
     * Parsed query parameters from the stream URL used as criteria for filtering results.
     *
     * @var array
     */
    private array $criteria = [];

    /**
     * Opens the stream and prepares a generator for reading entity data.
     *
     * @param string $path The full stream URL (e.g. "orm://Entity\\User?status=active")
     * @param string $mode Stream mode (e.g., "r" for read, "w" for write)
     * @param int $options Stream context options
     * @param string|null $opened_path Not used, but required by interface
     *
     * @return bool True on success
     *
     * @throws ReflectionException
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->entityManager = new EntityManager(PDODriver::default(), LoggerFactory::create());

        $parsed = parse_url($path);
        $this->entity = ltrim($parsed["host"] ?? "", "/");

        if (empty($this->entity)) {
            trigger_error("Missing entity name in stream URL: '{$path}'", E_USER_WARNING);
            return false;
        }

        if (isset($parsed["query"])) {
            parse_str($parsed["query"], $this->criteria);
        }

        $this->generator = !empty($this->criteria)
            ? $this->entityManager->streamBy($this->entity, $this->criteria)
            : $this->entityManager->streamAll($this->entity);

        return true;
    }

    /**
     * Reads and serializes entity records from the generator.
     *
     * @param int $count Number of bytes to read from the buffer.
     * @return string The serialized entity data.
     */
    public function stream_read(int $count): string
    {
        while (strlen($this->buffer) < $count && $this->generator?->valid()) {
            $entity = $this->generator->current();
            $this->generator->next();

            $this->buffer .= $entity instanceof JsonSerializable
                ? json_encode($entity, JSON_UNESCAPED_UNICODE) . PHP_EOL
                : serialize($entity) . PHP_EOL;
        }

        $output = substr($this->buffer, 0, $count);
        $this->buffer = substr($this->buffer, $count);
        return $output;
    }

    /**
     * Writes JSON-encoded entity data to the stream.
     *
     * Depending on whether the primary key is present in the input, this method will either
     * update an existing entity or insert a new one. The input must be a valid JSON object
     * that maps property names to values.
     *
     * If the primary key field (as defined by metadata) is missing or empty, the entity will be persisted (INSERT).
     * If the primary key is present and not null, the entity will be marked for update (UPDATE).
     *
     * @param string $data JSON string representing an entity (e.g., {"email": "new@example.com"})
     * @return int Number of bytes written (equals strlen($data))
     *
     * @throws InvalidArgumentException If the input JSON is invalid or the entity class is not found.
     * @throws ReflectionException If metadata parsing fails during entity hydration.
     *
     * @example
     * // Insert new user (primary key omitted)
     * fwrite($handle, json_encode(['username' => 'jane', 'email' => 'jane@example.com']));
     *
     * @example
     * // Update existing user (primary key included)
     * fwrite($handle, json_encode(['id' => 5, 'email' => 'updated@example.com']));
     *
     * @see EntityManager::persist()
     * @see EntityManager::update()
     * @see EntityManager::flush()
     */
    public function stream_write(string $data): int
    {
        $decoded = json_decode($data, true);

        if (!is_array($decoded)) {
            trigger_error("Invalid JSON input for update", E_USER_WARNING);
            return 0;
        }

        $entityClass = $this->entity;
        if (!class_exists($entityClass)) {
            trigger_error("Unknown entity class: {$entityClass}", E_USER_WARNING);
            return 0;
        }

        $entity = new $entityClass();
        [$_, $columns] = $this->entityManager->getMetadata($entityClass);
        $primaryKeyField = $this->entityManager->getPrimaryKeyColumn($columns);

        foreach ($decoded as $key => $value) {
            $entity->$key = $value;
        }

        if (empty($primaryKeyField) || empty($decoded[$primaryKeyField])) {
            $this->entityManager->persist($entity);
        } else {
            $this->entityManager->update($entity);
        }

        $this->entityManager->flush();

        return strlen($data);
    }

    /**
     * Checks if the end of stream has been reached.
     *
     * @return bool True if all data has been read and buffer is empty.
     */
    public function stream_eof(): bool
    {
        return $this->generator?->valid() !== true && $this->buffer === '';
    }

    /**
     * Returns metadata about the stream (not implemented).
     *
     * @return array Empty array
     */
    public function stream_stat(): array
    {
        return [];
    }

    /**
     * Closes the stream and clears internal state.
     *
     * @return void
     */
    public function stream_close(): void
    {
        $this->generator = null;
        $this->buffer = '';
    }

    /**
     * Deletes an entity via the `unlink()` stream function.
     *
     * @param string $path The stream path, including `id` or composite keys as query parameters.
     * @return bool True on successful deletion, false on failure.
     *
     * @throws ReflectionException
     *
     * @example
     * unlink("orm://Entity\\User?id=1");
     */
    public static function unlink(string $path): bool
    {
        $parsed = parse_url($path);
        $entityClass = ltrim($parsed["host"] ?? "", "/");

        if (!class_exists($entityClass)) {
            trigger_error("Unknown entity: {$entityClass}", E_USER_WARNING);
            return false;
        }

        parse_str($parsed["query"] ?? "", $params);

        $entityManager = new EntityManager(PDODriver::default(), LoggerFactory::create());
        $entity = $entityManager->find($entityClass, $params);

        if (!$entity) {
            trigger_error("Entity not found for deletion", E_USER_WARNING);
            return false;
        }

        $entityManager->delete($entity);
        $entityManager->flush();

        return true;
    }
}

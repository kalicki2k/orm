<?php

namespace ORM\Stream;

use DateMalformedStringException;
use InvalidArgumentException;
use ORM\Drivers\PDODriver;
use ORM\Entity\EntityManager;
use ORM\Logger\LoggerFactory;
use ORM\Metadata\MetadataParser;
use ORM\Stream\Format\CSVFormatWriter;
use ORM\Stream\Format\FormatWriter;
use ORM\Stream\Format\JsonFormatWriter;
use ORM\Stream\Format\XmlFormatWriter;
use ReflectionException;

/**
 * StreamWrapper enables accessing ORM entity data through PHP stream functions such as `fopen()`, `fgets()`, `fwrite()`, and `unlink()`.
 *
 * This allows interaction with ORM-based entities using a URL-like syntax via a custom protocol (e.g. `orm://Entity\\User`).
 *
 * Supports read, write (for updates and creation), and delete operations.
 *
 * @example Reading users
 *   stream_wrapper_register("orm", \ORM\Stream\StreamWrapper::class);
 *   $handle = fopen("orm://Entity\\User", "r");
 *   while (!feof($handle)) {
 *       echo fgets($handle);
 *   }
 *   fclose($handle);
 *
 * @example Updating a user (write mode "w")
 *   $handle = fopen("orm://Entity\\User", "w");
 *   fwrite($handle, json_encode(['id' => 1, 'email' => 'new@example.com']));
 *   fclose($handle);
 *
 * @example Creating a new user with append mode ("a")
 *   $handle = fopen("orm://Entity\\User", "a");
 *   fwrite($handle, json_encode(['username' => 'jane', 'email' => 'jane@example.com']));
 *   fclose($handle);
 *
 * @example Creating a new user with exclusive mode ("x")
 *   $handle = fopen("orm://Entity\\User", "x");
 *   fwrite($handle, json_encode(['username' => 'jane', 'email' => 'jane@example.com']));
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
     * The stream mode, e.g. "r", "w", "a", or "x".
     *
     * @var string
     */
    private string $mode;

    /**
     * The configured FormatWriter instance.
     *
     * @var FormatWriter
     */
    private FormatWriter $formatWriter;

    /**
     * Opens the stream and prepares a generator for reading entity data.
     *
     * In addition, this method configures the FormatWriter based on the "format" query parameter.
     *
     * @param string $path The full stream URL (e.g. "orm://Entity\\User?status=active&format=json")
     * @param string $mode Stream mode (e.g., "r", "w", "a", "x")
     * @param int $options Stream context options
     * @param string|null $opened_path Not used, but required by interface
     *
     * @return bool True on success
     *
     * @throws ReflectionException|DateMalformedStringException
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->mode = $mode;
        $this->entityManager = new EntityManager(PDODriver::default(), new MetadataParser(), LoggerFactory::create());

        $parsed = parse_url($path);
        $this->entity = ltrim($parsed["host"] ?? "", "/");

        if (empty($this->entity)) {
            trigger_error("Missing entity name in stream URL: '{$path}'", E_USER_WARNING);
            return false;
        }

        if (isset($parsed["query"])) {
            parse_str($parsed["query"], $this->criteria);
        }

        $format = $this->criteria["format"] ?? "json";
        $this->formatWriter = $this->getFormatWriter($format);
        unset($this->criteria["format"]);

        $this->generator = !empty($this->criteria)
            ? $this->entityManager->streamBy($this->entity, $this->criteria)
            : $this->entityManager->streamAll($this->entity);

        return true;
    }

    /**
     * Reads and serializes entity records from the generator.
     *
     * This method retrieves entities from an internal generator and serializes each one using the
     * configured FormatWriter. The serialized entities are appended to an internal buffer along with
     * a newline (PHP_EOL) after each entity. The process continues until the buffer contains at least
     * the specified number of bytes ($count) or until there are no more entities available.
     *
     * The method then extracts exactly $count bytes from the buffer and returns them, leaving any
     * remaining data in the buffer for subsequent reads. This approach maintains streaming behavior
     * while allowing formatted output (e.g., newline-delimited JSON).
     *
     * @param int $count Number of bytes to read from the internal buffer.
     * @return string The serialized entity data as a string of up to $count bytes.
     *
     * @example
     * // Example usage within a stream context:
     * // Assuming $streamWrapper is an instance of StreamWrapper configured with a FormatWriter,
     * // the following call reads a chunk of 1024 bytes from the stream.
     * $dataChunk = $streamWrapper->stream_read(1024);
     * echo $dataChunk;
     *
     * @see \ORM\Stream\Format\FormatWriter::write() Method used to serialize each entity.
     * @see \ORM\Stream\Format\JsonFormatWriter A specific implementation of FormatWriter for JSON.
     */
    public function stream_read(int $count): string
    {
        while (strlen($this->buffer) < $count && $this->generator?->valid()) {
            $entity = $this->generator->current();
            $this->generator->next();
            $this->buffer .= $this->formatWriter->write($entity) . PHP_EOL;
        }

        $output = substr($this->buffer, 0, $count);
        $this->buffer = substr($this->buffer, $count);
        return $output;
    }

    /**
     * Writes JSON-encoded entity data to the stream.
     *
     * Depending on the stream mode and whether the primary key is present in the input,
     * this method will either update an existing entity or insert a new one.
     *
     * In **"w"** mode: If the primary key field is present and not empty, the entity will be updated; otherwise, a new entity is inserted.
     *
     * In **"a"** mode: A new entity is always inserted (appended) regardless of primary key presence.
     *
     * In **"x"** mode: A new entity is inserted only if an entity with the same primary key does not already exist.
     * Otherwise, an error is triggered.
     *
     * The input must be a valid JSON object that maps property names to values.
     *
     * @param string $data JSON string representing an entity (e.g., {"email": "new@example.com"})
     * @return int Number of bytes written (equals strlen($data))
     *
     * @throws InvalidArgumentException If the input JSON is invalid or the entity class is not found.
     * @throws ReflectionException|DateMalformedStringException If metadata parsing fails during entity hydration.
     *
     * @example
     * // Insert new user (append mode or no primary key provided)
     * fwrite($handle, json_encode(['username' => 'jane', 'email' => 'jane@example.com']));
     *
     * @example
     * // Update existing user (write mode with primary key provided)
     * fwrite($handle, json_encode(['id' => 5, 'email' => 'updated@example.com']));
     *
     * @example
     * // Exclusive creation: Error if entity already exists
     * fwrite($handle, json_encode(['id' => 5, 'email' => 'jane@example.com']));
     *
     * @see EntityManager::persist()
     * @see EntityManager::update()
     * @see EntityManager::flush()
     */
public function stream_write(string $data): int
{
    $decoded = json_decode($data, true);

    if (!is_array($decoded)) {
        trigger_error("Invalid JSON input for write", E_USER_WARNING);
        return 0;
    }

    $entityClass = $this->entity;
    if (!class_exists($entityClass)) {
        trigger_error("Unknown entity class: {$entityClass}", E_USER_WARNING);
        return 0;
    }

    $entity = new $entityClass();

    foreach ($decoded as $key => $value) {
        $entity->$key = $value;
    }

    $metadata = $this->entityManager->getMetadata($entityClass);
    $primaryKeyField = $metadata->getPrimaryKey();

    if ($this->mode === 'a' || $this->mode === 'x') {
        if ($this->mode === 'x' && !empty($primaryKeyField) && !empty($decoded[$primaryKeyField])) {
            $existing = $this->entityManager->findBy($entityClass, [$primaryKeyField => $decoded[$primaryKeyField]]);
            if ($existing) {
                trigger_error("Entity already exists", E_USER_WARNING);
                return 0;
            }
        }
        $this->entityManager->persist($entity);
    } else {
        if (empty($primaryKeyField) || empty($decoded[$primaryKeyField])) {
            $this->entityManager->persist($entity);
        } else {
            $this->entityManager->update($entity);
        }
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
        $entity = $entityManager->findBy($entityClass, $params);

        if (!$entity) {
            trigger_error("Entity not found for deletion", E_USER_WARNING);
            return false;
        }

        $entityManager->delete($entity);
        $entityManager->flush();

        return true;
    }

    /**
     * Factory method to obtain the appropriate FormatWriter instance.
     *
     * @param string $format The requested format (e.g., "json", "csv", "xml").
     * @return FormatWriter An instance of a FormatWriter for the specified format.
     */
    protected function getFormatWriter(string $format): FormatWriter
    {
        return match (strtolower($format)) {
            'csv'  => new CsvFormatWriter(),
            'xml'  => new XmlFormatWriter(),
            // 'yaml' => new \ORM\Stream\Format\YamlFormatWriter(),
            default => new JsonFormatWriter(),
        };
    }
}

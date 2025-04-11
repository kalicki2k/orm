<?php

namespace ORM\Logger;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Factory class to create and configure a Monolog Logger instance
 * based on the environment (development or production).
 */
class LoggerFactory
{
    /**
     * Creates a configured Logger instance.
     *
     * In a development environment (`ENV=dev`), logs are written to `php://stdout`
     * with DEBUG level to assist in real-time debugging.
     * In other environments (e.g., production), logs are written to a file with WARNING level or higher.
     *
     * @param string $name The name of the logger (default: "orm").
     * @param StreamHandler $streamHandler
     *
     * @return Logger Configured Monolog logger.
     */
    public static function create(
        string $name = "orm",
        StreamHandler $streamHandler = new StreamHandler('php://stdout', Level::Debug),
    ): Logger
    {
        $logger = new Logger($name);
        $logger->pushHandler($streamHandler);
        return $logger;
    }
}

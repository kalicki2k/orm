<?php

namespace ORM\Config;

/**
 * Holds database configuration values.
 */
class DatabaseConfig
{
    public function __construct(
        public string $dsn,
        public string $username,
        public string $password,
        public string $environment = 'production'
    ) {}

    /**
     * Creates config from environment variables (.env or real env).
     */
    public static function fromEnv(): self
    {
        return new self(
            dsn: $_ENV['DB_DSN'] ?? getenv('DB_DSN') ?? '',
            username: $_ENV['DB_USER'] ?? getenv('DB_USER') ?? '',
            password: $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '',
            environment: $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production'
        );
    }
}
<?php

declare(strict_types=1);

namespace EzPhp\Env;

use RuntimeException;

/**
 * Class Dotenv
 *
 * Loads variables from a .env file into the PHP environment.
 * Existing variables (already set via the real environment) are never overwritten.
 *
 * Usage:
 *   $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
 *   $dotenv->safeLoad();
 *
 * @package EzPhp\Env
 */
final class Dotenv
{
    /**
     * Dotenv Constructor
     *
     * @param string $directory Absolute path to the directory containing the .env file.
     * @param string $filename  Name of the env file (default: '.env').
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $filename = '.env',
    ) {
    }

    /**
     * Create an immutable Dotenv instance that will not overwrite existing variables.
     *
     * @param string $directory
     * @param string $filename
     *
     * @return self
     */
    public static function createImmutable(string $directory, string $filename = '.env'): self
    {
        return new self($directory, $filename);
    }

    /**
     * Load the .env file.
     *
     * @throws RuntimeException If the file cannot be read.
     *
     * @return void
     */
    public function load(): void
    {
        $path = $this->path();

        if (!is_file($path)) {
            throw new RuntimeException("Cannot read .env file: $path");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Cannot read .env file: $path");
        }

        $this->populate((new Parser())->parse($content));
    }

    /**
     * Load the .env file only if it exists; silently skip if it is missing.
     *
     * @return void
     */
    public function safeLoad(): void
    {
        if (!file_exists($this->path())) {
            return;
        }

        $this->load();
    }

    /**
     * @return string
     */
    private function path(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->filename;
    }

    /**
     * Write parsed variables into the PHP environment.
     * Immutable: skips keys that are already set.
     *
     * @param array<string, string> $vars
     *
     * @return void
     */
    private function populate(array $vars): void
    {
        foreach ($vars as $key => $value) {
            // Do not overwrite variables that already exist in the environment.
            if (getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) {
                continue;
            }

            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

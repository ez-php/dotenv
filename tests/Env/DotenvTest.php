<?php

declare(strict_types=1);

namespace Tests\Env;

use EzPhp\Env\Dotenv;
use EzPhp\Env\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Class DotenvTest
 *
 * @package Tests\Env
 */
#[CoversClass(Dotenv::class)]
#[UsesClass(Parser::class)]
final class DotenvTest extends TestCase
{
    private string $dir;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ez-php-dotenv-' . uniqid();
        mkdir($this->dir, 0o755, true);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        rmdir($this->dir);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param string $content
     * @param string $name
     *
     * @return void
     */
    private function write(string $content, string $name = '.env'): void
    {
        file_put_contents($this->dir . '/' . $name, $content);
    }

    /**
     * Remove a key from the PHP environment so tests are isolated.
     */
    private function unsetEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_create_immutable_returns_instance(): void
    {
        $dotenv = Dotenv::createImmutable($this->dir);

        $this->assertInstanceOf(Dotenv::class, $dotenv);
    }

    /**
     * @return void
     */
    public function test_load_sets_env_variable(): void
    {
        $this->write('EZ_TEST_LOAD=hello');
        $this->unsetEnv('EZ_TEST_LOAD');

        Dotenv::createImmutable($this->dir)->load();

        $this->assertSame('hello', getenv('EZ_TEST_LOAD'));
        $this->assertSame('hello', $_ENV['EZ_TEST_LOAD']);
        $this->assertSame('hello', $_SERVER['EZ_TEST_LOAD']);

        $this->unsetEnv('EZ_TEST_LOAD');
    }

    /**
     * @return void
     */
    public function test_safe_load_does_not_throw_when_file_missing(): void
    {
        $dotenv = Dotenv::createImmutable($this->dir, '.env.missing');

        $dotenv->safeLoad();

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return void
     */
    public function test_load_throws_when_file_missing(): void
    {
        $dotenv = Dotenv::createImmutable($this->dir, '.env.missing');

        $this->expectException(RuntimeException::class);

        $dotenv->load();
    }

    /**
     * @return void
     */
    public function test_immutable_does_not_overwrite_existing_env(): void
    {
        putenv('EZ_TEST_IMMUTABLE=original');
        $_ENV['EZ_TEST_IMMUTABLE'] = 'original';

        $this->write('EZ_TEST_IMMUTABLE=overwritten');

        Dotenv::createImmutable($this->dir)->load();

        $this->assertSame('original', getenv('EZ_TEST_IMMUTABLE'));

        $this->unsetEnv('EZ_TEST_IMMUTABLE');
    }

    /**
     * @return void
     */
    public function test_custom_filename_is_loaded(): void
    {
        $this->write('EZ_CUSTOM_FILE=yes', '.env.test');
        $this->unsetEnv('EZ_CUSTOM_FILE');

        Dotenv::createImmutable($this->dir, '.env.test')->load();

        $this->assertSame('yes', getenv('EZ_CUSTOM_FILE'));

        $this->unsetEnv('EZ_CUSTOM_FILE');
    }

    /**
     * @return void
     */
    public function test_load_multiple_variables(): void
    {
        $this->write("EZ_A=foo\nEZ_B=bar");
        $this->unsetEnv('EZ_A');
        $this->unsetEnv('EZ_B');

        Dotenv::createImmutable($this->dir)->load();

        $this->assertSame('foo', getenv('EZ_A'));
        $this->assertSame('bar', getenv('EZ_B'));

        $this->unsetEnv('EZ_A');
        $this->unsetEnv('EZ_B');
    }

    /**
     * @return void
     */
    public function test_safe_load_loads_when_file_exists(): void
    {
        $this->write('EZ_SAFE=loaded');
        $this->unsetEnv('EZ_SAFE');

        Dotenv::createImmutable($this->dir)->safeLoad();

        $this->assertSame('loaded', getenv('EZ_SAFE'));

        $this->unsetEnv('EZ_SAFE');
    }
}

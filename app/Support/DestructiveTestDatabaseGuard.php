<?php

namespace App\Support;

use LogicException;

final class DestructiveTestDatabaseGuard
{
    /** @var list<string> */
    private const ALLOWED_HOSTS = ['postgres', '127.0.0.1', 'localhost'];

    /** @return array<string, string> */
    public static function processEnvironment(): array
    {
        return [
            'APP_ENV' => (string) getenv('APP_ENV'),
            'DB_CONNECTION' => (string) getenv('DB_CONNECTION'),
            'DB_URL' => (string) getenv('DB_URL'),
            'DB_DATABASE' => (string) getenv('DB_DATABASE'),
            'DB_HOST' => (string) getenv('DB_HOST'),
        ];
    }

    /** @param array<string, mixed> $environment */
    public static function assertProcessEnvironmentIsSafe(array $environment): void
    {
        self::assertSame('APP_ENV', 'testing', $environment['APP_ENV'] ?? null);
        self::assertSame('DB_CONNECTION', 'pgsql', $environment['DB_CONNECTION'] ?? null);
        self::assertEmpty('DB_URL', $environment['DB_URL'] ?? null);
        self::assertSame('DB_DATABASE', 'centro_acolhimento_test', $environment['DB_DATABASE'] ?? null);
        self::assertAllowedHost('DB_HOST', $environment['DB_HOST'] ?? null);
    }

    /** @param array<string, string> $environment */
    public static function synchronizeValidatedEnvironment(array $environment): void
    {
        self::assertProcessEnvironmentIsSafe($environment);

        foreach ($environment as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /** @param array<string, mixed> $configuration */
    public static function assertEffectiveConfigurationIsSafe(array $configuration): void
    {
        self::assertSame('app.env', 'testing', $configuration['app_env'] ?? null);
        self::assertSame('database.default', 'pgsql', $configuration['default_connection'] ?? null);
        self::assertSame('database.driver', 'pgsql', $configuration['driver'] ?? null);
        self::assertEmpty('database.url', $configuration['url'] ?? null);
        self::assertSame('database.name', 'centro_acolhimento_test', $configuration['database'] ?? null);
        self::assertAllowedHost('database.host', $configuration['host'] ?? null);
    }

    private static function assertSame(string $key, string $expected, mixed $actual): void
    {
        if ($actual !== $expected) {
            throw new LogicException("Unsafe destructive test database configuration: {$key}.");
        }
    }

    private static function assertEmpty(string $key, mixed $actual): void
    {
        if (trim((string) $actual) !== '') {
            throw new LogicException("Unsafe destructive test database configuration: {$key}.");
        }
    }

    private static function assertAllowedHost(string $key, mixed $host): void
    {
        if (! is_string($host) || ! in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new LogicException("Unsafe destructive test database configuration: {$key}.");
        }
    }
}

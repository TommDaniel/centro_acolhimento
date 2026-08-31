<?php

namespace Tests\Unit;

use App\Support\DestructiveTestDatabaseGuard;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PostgreSqlTestEnvironmentGuardTest extends TestCase
{
    public function test_it_accepts_the_isolated_postgresql_test_environment(): void
    {
        DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe($this->safeEnvironment());

        $this->addToAssertionCount(1);
    }

    /** @param array<string, string> $unsafeValues */
    #[DataProvider('unsafeEnvironmentProvider')]
    public function test_it_rejects_an_unsafe_environment_before_any_database_query(array $unsafeValues): void
    {
        $this->expectException(LogicException::class);

        DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe([
            ...$this->safeEnvironment(),
            ...$unsafeValues,
        ]);
    }

    public function test_it_rejects_unsafe_cached_configuration_without_executing_a_query(): void
    {
        $queryWasExecuted = false;

        try {
            DestructiveTestDatabaseGuard::assertEffectiveConfigurationIsSafe([
                ...$this->safeEffectiveConfiguration(),
                'database' => 'centro_acolhimento',
            ]);
            $queryWasExecuted = true;
        } catch (LogicException) {
            $this->assertFalse($queryWasExecuted);

            return;
        }

        $this->fail('A configuração efetiva insegura deveria ser rejeitada antes da consulta.');
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function unsafeEnvironmentProvider(): iterable
    {
        yield 'application environment' => [['APP_ENV' => 'production']];
        yield 'database driver' => [['DB_CONNECTION' => 'sqlite']];
        yield 'database URL' => [['DB_URL' => 'pgsql://user:secret@example.invalid/production']];
        yield 'database name' => [['DB_DATABASE' => 'centro_acolhimento']];
        yield 'database host' => [['DB_HOST' => 'database.example.invalid']];
    }

    /** @return array<string, string> */
    private function safeEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_DATABASE' => 'centro_acolhimento_test',
            'DB_HOST' => 'postgres',
        ];
    }

    /** @return array<string, string> */
    private function safeEffectiveConfiguration(): array
    {
        return [
            'app_env' => 'testing',
            'default_connection' => 'pgsql',
            'driver' => 'pgsql',
            'url' => '',
            'database' => 'centro_acolhimento_test',
            'host' => 'postgres',
        ];
    }
}

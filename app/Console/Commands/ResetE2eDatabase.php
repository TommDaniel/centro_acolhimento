<?php

namespace App\Console\Commands;

use App\Support\DestructiveTestDatabaseGuard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;

#[Signature('e2e:reset-database')]
#[Description('Recria exclusivamente o banco PostgreSQL isolado do E2E e aplica seeds sintéticos')]
class ResetE2eDatabase extends Command
{
    public function handle(): int
    {
        try {
            DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe(
                DestructiveTestDatabaseGuard::processEnvironment(),
            );
            DestructiveTestDatabaseGuard::assertEffectiveConfigurationIsSafe([
                'app_env' => config('app.env'),
                'default_connection' => config('database.default'),
                'driver' => config('database.connections.pgsql.driver'),
                'url' => config('database.connections.pgsql.url'),
                'database' => config('database.connections.pgsql.database'),
                'host' => config('database.connections.pgsql.host'),
            ]);
        } catch (LogicException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);
    }
}

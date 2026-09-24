<?php

namespace Tests;

use App\Actions\ProvisionInstitutionContext;
use App\Support\DestructiveTestDatabaseGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            getenv('SKIP_TEST_CONTEXT_PROVISION') !== 'true'
            && Schema::hasTable('organizacoes')
            && Schema::hasTable('unidades')
        ) {
            app(ProvisionInstitutionContext::class)->handle();
        }
    }

    public function createApplication(): Application
    {
        DestructiveTestDatabaseGuard::synchronizeValidatedEnvironment(
            DestructiveTestDatabaseGuard::processEnvironment(),
        );

        $app = parent::createApplication();
        $configuration = $app->make('config');

        DestructiveTestDatabaseGuard::assertEffectiveConfigurationIsSafe([
            'app_env' => $configuration->get('app.env'),
            'default_connection' => $configuration->get('database.default'),
            'driver' => $configuration->get('database.connections.pgsql.driver'),
            'url' => $configuration->get('database.connections.pgsql.url'),
            'database' => $configuration->get('database.connections.pgsql.database'),
            'host' => $configuration->get('database.connections.pgsql.host'),
        ]);

        return $app;
    }
}

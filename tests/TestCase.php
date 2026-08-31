<?php

namespace Tests;

use App\Support\DestructiveTestDatabaseGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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

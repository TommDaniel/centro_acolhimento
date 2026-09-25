<?php

namespace Tests;

use App\Actions\ProvisionInstitutionContext;
use App\Enums\UserStatus;
use App\Models\MfaEnrollment;
use App\Models\User;
use App\Support\DestructiveTestDatabaseGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsWithVerifiedMfa(Authenticatable $user, $guard = null): static
    {
        if ($user instanceof User
            && $user->status === UserStatus::Ativa
            && Schema::hasTable('mfa_enrollments')) {
            MfaEnrollment::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'state' => 'active'],
                [
                    'version' => 1,
                    'secret' => 'JBSWY3DPEHPK3PXP',
                    'confirmed_at' => now('UTC'),
                ],
            );

            $now = now('UTC')->getTimestamp();
            $this->withSession([
                'auth.level' => 'mfa_verified',
                'auth.access_generation' => $user->access_generation,
                'auth.mfa_issued_at' => $now,
                'auth.mfa_last_activity_at' => $now,
            ]);
        }

        return parent::actingAs($user, $guard);
    }

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

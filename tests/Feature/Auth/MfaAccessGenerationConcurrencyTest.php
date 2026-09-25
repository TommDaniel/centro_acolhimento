<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\MfaEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MfaAccessGenerationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_password_reset_committing_during_login_cannot_promote_the_old_password_proof(): void
    {
        $user = $this->activeMfaUser('concorrencia-login-reset@exemplo-ficticio.local');
        $expectedGeneration = $user->access_generation;
        $expectedHash = $user->getAuthPassword();

        [$writer, $verifier] = $this->runWriterAgainstVerifier(
            $this->writerProcess('password_reset', $user),
            $this->loginVerifierProcess($user, $expectedGeneration, $expectedHash),
        );

        $this->assertSame(0, $writer->getExitCode(), $writer->getErrorOutput());
        $this->assertSame(2, $verifier->getExitCode(), $verifier->getErrorOutput());
        $this->assertTrue(Hash::check('senha-reset-concorrente-ficticia', $user->fresh()->getAuthPassword()));
    }

    public function test_inactivation_committing_during_challenge_cannot_promote_a_revoked_session(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = $this->activeMfaUser('concorrencia-challenge-inativacao@exemplo-ficticio.local', $secret);
        $factor = MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'active')->sole();

        [$writer, $verifier] = $this->runWriterAgainstVerifier(
            $this->writerProcess('inactivate', $user),
            $this->mfaVerifierProcess('challenge', $user, $factor, $secret),
        );

        $this->assertSame(0, $writer->getExitCode(), $writer->getErrorOutput());
        $this->assertSame(3, $verifier->getExitCode(), $verifier->getErrorOutput());
        $this->assertSame(UserStatus::Inativa, $user->fresh()->status);
        $this->assertNull($factor->fresh()->last_accepted_time_step);
    }

    public function test_password_reset_committing_during_enrollment_cannot_activate_the_revoked_session(): void
    {
        $secret = 'KRSXG5DSNFXGOIDB';
        $user = User::factory()->create([
            'email' => 'concorrencia-enrollment-reset@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);
        $factor = MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'pending',
            'secret' => $secret,
        ]);

        [$writer, $verifier] = $this->runWriterAgainstVerifier(
            $this->writerProcess('password_reset', $user),
            $this->mfaVerifierProcess('enrollment', $user, $factor, $secret),
        );

        $this->assertSame(0, $writer->getExitCode(), $writer->getErrorOutput());
        $this->assertSame(3, $verifier->getExitCode(), $verifier->getErrorOutput());
        $this->assertSame(UserStatus::PendenteMfa, $user->fresh()->status);
        $this->assertSame('pending', $factor->fresh()->state);
    }

    /** @return array{Process, Process} */
    private function runWriterAgainstVerifier(Process $writer, Process $verifier): array
    {
        $writer->start();
        $deadline = microtime(true) + 15;

        while (! str_contains($writer->getOutput(), 'LOCKED') && microtime(true) < $deadline) {
            usleep(10_000);
        }

        $this->assertStringContainsString('LOCKED', $writer->getOutput(), $writer->getErrorOutput());
        $verifier->start();
        $writer->wait();
        $verifier->wait();

        return [$writer, $verifier];
    }

    private function writerProcess(string $operation, User $user): Process
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Support\DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe(
    App\Support\DestructiveTestDatabaseGuard::processEnvironment(),
);
Illuminate\Support\Facades\DB::transaction(function () use ($argv): void {
    $user = App\Models\User::query()->lockForUpdate()->findOrFail((int) $argv[2]);
    if ($argv[1] === 'inactivate') {
        $user->status = App\Enums\UserStatus::Inativa;
    } else {
        $user->password = Illuminate\Support\Facades\Hash::make('senha-reset-concorrente-ficticia');
    }
    $user->access_generation++;
    $user->save();
    fwrite(STDOUT, "LOCKED\n");
    fflush(STDOUT);
    usleep(400000);
});
PHP;

        return $this->childProcess($script, [$operation, (string) $user->getKey()]);
    }

    private function loginVerifierProcess(User $user, int $expectedGeneration, string $expectedHash): Process
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::query()->findOrFail((int) $argv[1]);
try {
    app(App\Actions\EstablishPasswordOnlyLogin::class)->handle(
        $user,
        (int) $argv[2],
        $argv[3],
        'password',
    );
    exit(0);
} catch (Illuminate\Validation\ValidationException) {
    exit(2);
}
PHP;

        return $this->childProcess($script, [
            (string) $user->getKey(),
            (string) $expectedGeneration,
            $expectedHash,
        ]);
    }

    private function mfaVerifierProcess(string $operation, User $user, MfaEnrollment $factor, string $secret): Process
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::query()->findOrFail((int) $argv[2]);
$code = app(PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($argv[5]);
try {
    if ($argv[1] === 'challenge') {
        app(App\Actions\VerifyMfaChallenge::class)->handle($user, $code, (int) $argv[4], 'password_only');
    } else {
        app(App\Actions\ConfirmMfaEnrollment::class)->handle(
            $user,
            (int) $argv[3],
            $code,
            (int) $argv[4],
            'password_only',
        );
    }
    exit(0);
} catch (App\Exceptions\MfaSessionRevokedException) {
    exit(3);
}
PHP;

        return $this->childProcess($script, [
            $operation,
            (string) $user->getKey(),
            (string) $factor->getKey(),
            (string) $user->access_generation,
            $secret,
        ]);
    }

    /** @param list<string> $arguments */
    private function childProcess(string $script, array $arguments): Process
    {
        return (new Process(
            [PHP_BINARY, '-r', $script, '--', ...$arguments],
            base_path(),
            $this->childEnvironment(),
        ))->setTimeout(20);
    }

    /** @return array<string, string> */
    private function childEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => (string) config('app.key'),
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => 'centro_acolhimento_test',
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
            'DB_SSLMODE' => (string) config('database.connections.pgsql.sslmode'),
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];
    }

    private function activeMfaUser(string $email, string $secret = 'MFRGGZDFMZTWQ2LK'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'status' => UserStatus::Ativa,
        ]);
        MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'active',
            'secret' => $secret,
            'confirmed_at' => now('UTC'),
        ]);

        return $user;
    }
}

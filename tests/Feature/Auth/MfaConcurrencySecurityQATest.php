<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\AuditEvent;
use App\Models\MfaEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MfaConcurrencySecurityQATest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_concurrent_enrollment_starts_leave_one_current_generation(): void
    {
        $user = User::factory()->create([
            'email' => 'qa-concorrencia-geracao@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);

        $processes = $this->runConcurrently('start', $user);

        foreach ($processes as $process) {
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        }

        $enrollments = MfaEnrollment::query()
            ->whereBelongsTo($user)
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $enrollments);
        $this->assertSame([1, 2], $enrollments->pluck('version')->all());
        $this->assertSame(1, $enrollments->where('state', 'pending')->count());
        $this->assertSame(1, $enrollments->where('state', 'revoked')->count());
        $this->assertNotSame($enrollments[0]->secret, $enrollments[1]->secret);
    }

    public function test_two_concurrent_confirmations_produce_one_cutover_and_one_denial(): void
    {
        $user = User::factory()->create([
            'email' => 'qa-concorrencia-confirmacao@exemplo-ficticio.local',
            'status' => UserStatus::PendenteMfa,
        ]);
        MfaEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'version' => 1,
            'state' => 'pending',
            'secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $processes = $this->runConcurrently('confirm', $user);
        $exitCodes = collect($processes)->map(fn (Process $process): ?int => $process->getExitCode())->sort()->values()->all();

        $this->assertSame([0, 2], $exitCodes, collect($processes)->map->getErrorOutput()->implode("\n"));
        $this->assertSame(UserStatus::Ativa, $user->fresh()->status);
        $this->assertSame(1, MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'active')->count());
        $this->assertSame(0, MfaEnrollment::query()->whereBelongsTo($user)->where('state', 'pending')->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'auth.mfa_enrollment_confirmed')->where('result', 'success')->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'auth.mfa_enrollment_confirmed')->where('result', 'denied')->count());
    }

    /** @return list<Process> */
    private function runConcurrently(string $operation, User $user): array
    {
        $startAt = microtime(true) + 0.75;
        $processes = [
            $this->newChildProcess($operation, $user, $startAt),
            $this->newChildProcess($operation, $user, $startAt),
        ];

        foreach ($processes as $process) {
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
        }

        return $processes;
    }

    private function newChildProcess(string $operation, User $user, float $startAt): Process
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Support\DestructiveTestDatabaseGuard::assertProcessEnvironmentIsSafe(
    App\Support\DestructiveTestDatabaseGuard::processEnvironment(),
);
while (microtime(true) < (float) $argv[3]) {
    usleep(1000);
}
$user = App\Models\User::query()->findOrFail((int) $argv[2]);
try {
    if ($argv[1] === 'start') {
        app(App\Actions\StartMfaEnrollment::class)->handle($user);
    } else {
        $factor = App\Models\MfaEnrollment::query()
            ->whereBelongsTo($user)
            ->where('state', 'pending')
            ->sole();
        $code = app(PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($factor->secret);
        app(App\Actions\ConfirmMfaEnrollment::class)->handle($user, $factor->getKey(), $code);
    }
    exit(0);
} catch (Illuminate\Validation\ValidationException) {
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
PHP;

        return (new Process(
            [PHP_BINARY, '-r', $script, '--', $operation, (string) $user->getKey(), (string) $startAt],
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
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class AdministratorConcurrencyTest extends TestCase
{
    public function test_concurrent_cross_demotion_keeps_one_active_administrator(): void
    {
        if (getenv('RUN_AUTHORIZATION_CONCURRENCY_TEST') !== 'true') {
            $this->markTestSkipped('Executado separadamente contra PostgreSQL efêmero e vazio.');
        }

        User::query()->update(['status' => UserStatus::Inativa->value]);
        $first = User::factory()->administrator()->create();
        $second = User::factory()->administrator()->create();
        $worker = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$actor = App\Models\User::query()->findOrFail((int) $argv[2]);
$subject = App\Models\User::query()->findOrFail((int) $argv[3]);
$attributes = [
    'name' => $subject->name,
    'email' => $subject->email,
    'setor_id' => $subject->setor_id,
    'role' => App\Enums\UserRole::EquipeTecnica->value,
    'status' => App\Enums\UserStatus::Ativa->value,
    'cargo' => $subject->cargo,
    'telefone' => $subject->telefone,
];
try {
    $app->make(App\Actions\UpdateUserAccount::class)->handle($subject, $attributes, $actor);
    echo 'completed';
} catch (Throwable) {
    echo 'denied';
}
PHP;

        $results = Process::concurrently(function (Pool $pool) use ($first, $second, $worker): void {
            $pool->as('first')->path(base_path())->timeout(30)->command([
                PHP_BINARY, '-r', $worker, base_path(), (string) $first->id, (string) $second->id,
            ]);
            $pool->as('second')->path(base_path())->timeout(30)->command([
                PHP_BINARY, '-r', $worker, base_path(), (string) $second->id, (string) $first->id,
            ]);
        });

        $this->assertTrue($results->successful());
        $this->assertEqualsCanonicalizing(
            ['completed', 'denied'],
            $results->collect()->map(fn ($result): string => trim($result->output()))->values()->all(),
        );
        $this->assertSame(1, User::query()
            ->whereKey([$first->id, $second->id])
            ->where('role', UserRole::Administradora->value)
            ->where('status', UserStatus::Ativa->value)
            ->count());
    }
}

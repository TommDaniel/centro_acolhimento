<?php

namespace Tests\Feature;

use App\Actions\OpenAcolhimento;
use App\Models\Acolhimento;
use App\Models\AcolhimentoMovimentacao;
use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcolhimentoConcurrencyTest extends TestCase
{
    public function test_two_processes_cannot_open_two_episodes_for_same_person_and_unit(): void
    {
        if (getenv('RUN_ACOLHIMENTO_CONCURRENCY_TEST') !== 'true') {
            $this->markTestSkipped('Executado separadamente contra PostgreSQL efêmero.');
        }

        $firstActor = User::factory()->create();
        $secondActor = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa Concorrente Fictícia']);
        $worker = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$child = App\Models\Crianca::query()->findOrFail((int) $argv[2]);
$actor = App\Models\User::query()->findOrFail((int) $argv[3]);
$attributes = [
    'ingresso_em' => Carbon\CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'),
    'motivo' => 'Ingresso concorrente inteiramente fictício.',
    'fundamento' => null,
    'origem_codigo' => 'conselho_tutelar',
    'origem_complemento' => null,
    'orgao_condutor_codigo' => 'conselho_tutelar',
    'orgao_condutor_complemento' => null,
    'pessoa_condutora' => 'Pessoa Condutora Fictícia',
    'idempotency_key' => $argv[4],
];
try {
    $app->make(App\Actions\OpenAcolhimento::class)->handle($child, $attributes, $actor);
    echo 'completed';
} catch (Throwable) {
    echo 'denied';
}
PHP;

        $results = Process::concurrently(function (Pool $pool) use ($firstActor, $secondActor, $child, $worker): void {
            $pool->as('first')->path(base_path())->timeout(30)->command([
                PHP_BINARY, '-r', $worker, base_path(), (string) $child->id,
                (string) $firstActor->id, '11111111-1111-4111-8111-111111111111',
            ]);
            $pool->as('second')->path(base_path())->timeout(30)->command([
                PHP_BINARY, '-r', $worker, base_path(), (string) $child->id,
                (string) $secondActor->id, '22222222-2222-4222-8222-222222222222',
            ]);
        });

        $this->assertTrue($results->successful());
        $this->assertEqualsCanonicalizing(
            ['completed', 'denied'],
            $results->collect()->map(fn ($result): string => trim($result->output()))->values()->all(),
        );
        $this->assertSame(1, Acolhimento::query()
            ->where('crianca_id', $child->id)
            ->whereNull('encerrado_em')
            ->count());
        $this->assertSame(1, Acolhimento::query()
            ->where('crianca_id', $child->id)
            ->sole()
            ->movimentacoes()
            ->count());
    }

    public function test_two_competing_movements_from_same_state_commit_only_one_transition(): void
    {
        $this->requireConcurrencyEnvironment();

        $firstActor = User::factory()->create();
        $secondActor = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa Movimento Concorrente Fictícia']);
        $episode = $this->openEpisode($child, $firstActor);
        $worker = $this->movementWorker();

        $results = Process::concurrently(function (Pool $pool) use ($firstActor, $secondActor, $child, $episode, $worker): void {
            $pool->as('evasion')->path(base_path())->timeout(30)->command([
                PHP_BINARY, '-r', $worker, base_path(), (string) $child->id, (string) $episode->id,
                (string) $firstActor->id, 'evasao', '33333333-3333-4333-8333-333333333333',
            ]);
            $pool->as('hospitalization')->path(base_path())->timeout(30)->command([
                PHP_BINARY, '-r', $worker, base_path(), (string) $child->id, (string) $episode->id,
                (string) $secondActor->id, 'internacao', '44444444-4444-4444-8444-444444444444',
            ]);
        });

        $this->assertTrue($results->successful());
        $this->assertEqualsCanonicalizing(
            ['completed', 'denied'],
            $results->collect()->map(fn ($result): string => trim($result->output()))->values()->all(),
        );
        $this->assertSame(2, AcolhimentoMovimentacao::query()->where('acolhimento_id', $episode->id)->count());
        $this->assertSame(1, AuditEvent::query()
            ->where('action', 'acolhimento.movement.recorded')
            ->where('subject_id', (string) $episode->id)
            ->count());
    }

    public function test_concurrent_retry_with_same_uuid_creates_one_fact_and_one_audit_event(): void
    {
        $this->requireConcurrencyEnvironment();

        $actor = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa Retry Concorrente Fictícia']);
        $episode = $this->openEpisode($child, $actor);
        $worker = $this->movementWorker();
        $key = '55555555-5555-4555-8555-555555555555';

        $results = Process::concurrently(function (Pool $pool) use ($actor, $child, $episode, $worker, $key): void {
            foreach (['first', 'second'] as $name) {
                $pool->as($name)->path(base_path())->timeout(30)->command([
                    PHP_BINARY, '-r', $worker, base_path(), (string) $child->id, (string) $episode->id,
                    (string) $actor->id, 'evasao', $key,
                ]);
            }
        });

        $this->assertTrue($results->successful());
        $this->assertSame(
            ['completed', 'completed'],
            $results->collect()->map(fn ($result): string => trim($result->output()))->sort()->values()->all(),
        );
        $this->assertSame(1, AcolhimentoMovimentacao::query()
            ->where('acolhimento_id', $episode->id)
            ->where('idempotency_key', $key)
            ->count());
        $this->assertSame(1, AuditEvent::query()
            ->where('action', 'acolhimento.movement.recorded')
            ->where('subject_id', (string) $episode->id)
            ->count());
    }

    private function requireConcurrencyEnvironment(): void
    {
        if (getenv('RUN_ACOLHIMENTO_CONCURRENCY_TEST') !== 'true') {
            $this->markTestSkipped('Executado separadamente contra PostgreSQL efêmero.');
        }
    }

    private function openEpisode(Crianca $child, User $actor): Acolhimento
    {
        return app(OpenAcolhimento::class)->handle($child, [
            'ingresso_em' => CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'),
            'motivo' => 'Ingresso concorrente inteiramente fictício.',
            'fundamento' => null,
            'origem_codigo' => 'conselho_tutelar',
            'origem_complemento' => null,
            'orgao_condutor_codigo' => 'conselho_tutelar',
            'orgao_condutor_complemento' => null,
            'pessoa_condutora' => 'Pessoa Condutora Fictícia',
            'idempotency_key' => (string) Str::uuid(),
        ], $actor);
    }

    private function movementWorker(): string
    {
        return <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$child = App\Models\Crianca::query()->findOrFail((int) $argv[2]);
$episode = App\Models\Acolhimento::query()->findOrFail((int) $argv[3]);
$actor = App\Models\User::query()->findOrFail((int) $argv[4]);
$type = $argv[5];
$attributes = [
    'tipo' => $type,
    'efetiva_em' => Carbon\CarbonImmutable::parse('2026-10-01 13:00:00', 'UTC'),
    'motivo' => 'Movimento concorrente inteiramente fictício.',
    'fundamento' => null,
    'local_destino' => $type === 'internacao' ? 'Hospital Concorrente Fictício' : null,
    'observacao' => null,
    'idempotency_key' => $argv[6],
];
try {
    $app->make(App\Actions\RecordAcolhimentoMovimentacao::class)->handle($child, $episode, $attributes, $actor);
    echo 'completed';
} catch (Throwable) {
    echo 'denied';
}
PHP;
    }
}

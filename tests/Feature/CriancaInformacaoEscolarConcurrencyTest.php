<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\CriancaInformacaoEscolar;
use App\Models\User;
use App\Services\CriancaInformacaoEscolarHistory;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CriancaInformacaoEscolarConcurrencyTest extends TestCase
{
    public function test_concurrent_retry_with_same_uuid_creates_one_version_and_one_audit(): void
    {
        $this->requireConcurrencyEnvironment();

        $actor = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa Retry Escolar Concorrente Fictícia']);
        $key = '66666666-6666-4666-8666-666666666666';
        $worker = $this->worker();
        $startAt = sprintf('%.6F', microtime(true) + 1);

        $results = Process::concurrently(function (Pool $pool) use ($actor, $child, $key, $worker, $startAt): void {
            foreach (['first', 'second'] as $name) {
                $pool->as($name)->path(base_path())->timeout(30)->command([
                    PHP_BINARY, '-r', $worker, base_path(), (string) $child->id,
                    (string) $actor->id, $key, 'CONC-RETRY-FICT', $startAt,
                ]);
            }
        });

        $this->assertTrue($results->successful());
        $versionIds = $results->collect()
            ->map(fn ($result): string => trim($result->output()))
            ->values();
        $this->assertCount(1, $versionIds->unique());
        $this->assertSame(1, CriancaInformacaoEscolar::query()->whereBelongsTo($child)->count());
        $this->assertSame(1, AuditEvent::query()
            ->where('action', 'crianca.informacao_escolar.created')
            ->where('subject_id', (string) $child->id)
            ->count());
    }

    public function test_concurrent_distinct_uuids_form_a_linear_chain_with_a_deterministic_head(): void
    {
        $this->requireConcurrencyEnvironment();

        $firstActor = User::factory()->create();
        $secondActor = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa Versões Escolares Concorrentes Fictícia']);
        $worker = $this->worker();
        $startAt = sprintf('%.6F', microtime(true) + 1);

        $results = Process::concurrently(function (Pool $pool) use (
            $child,
            $firstActor,
            $secondActor,
            $worker,
            $startAt,
        ): void {
            $pool->as('first')->path(base_path())->timeout(30)->command([
                PHP_BINARY, '-r', $worker, base_path(), (string) $child->id,
                (string) $firstActor->id, '77777777-7777-4777-8777-777777777777',
                'CONC-DISTINCT-A', $startAt,
            ]);
            $pool->as('second')->path(base_path())->timeout(30)->command([
                PHP_BINARY, '-r', $worker, base_path(), (string) $child->id,
                (string) $secondActor->id, '88888888-8888-4888-8888-888888888888',
                'CONC-DISTINCT-B', $startAt,
            ]);
        });

        $this->assertTrue($results->successful());
        $versions = CriancaInformacaoEscolar::query()
            ->whereBelongsTo($child)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $versions);
        $this->assertNull($versions[0]->versao_anterior_id);
        $this->assertSame($versions[0]->id, $versions[1]->versao_anterior_id);
        $this->assertSame(
            $versions[1]->id,
            app(CriancaInformacaoEscolarHistory::class)->current($child)?->id,
        );
        $this->assertSame(2, AuditEvent::query()
            ->where('action', 'crianca.informacao_escolar.created')
            ->where('subject_id', (string) $child->id)
            ->count());
    }

    private function requireConcurrencyEnvironment(): void
    {
        if (getenv('RUN_SCHOOL_INFORMATION_CONCURRENCY_TEST') !== 'true') {
            $this->markTestSkipped('Executado separadamente contra PostgreSQL efêmero.');
        }
    }

    private function worker(): string
    {
        return <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$child = App\Models\Crianca::query()->findOrFail((int) $argv[2]);
$actor = App\Models\User::query()->findOrFail((int) $argv[3]);
$delay = ((float) $argv[6]) - microtime(true);
if ($delay > 0) {
    usleep((int) ($delay * 1_000_000));
}
$version = $app->make(App\Actions\RecordCriancaInformacaoEscolar::class)->handle($child, [
    'situacao_codigo' => 'matriculada',
    'situacao_complemento' => null,
    'escola_nome' => 'Escola Concorrente Fictícia',
    'rede_codigo' => 'municipal',
    'rede_complemento' => null,
    'matricula' => $argv[5],
    'ano_serie' => 'Ano concorrente fictício',
    'turma' => 'Turma concorrente fictícia',
    'turno_codigo' => 'matutino',
    'turno_complemento' => null,
    'vigente_em' => '2026-09-29',
    'fonte_codigo' => 'documento',
    'fonte_complemento' => null,
    'idempotency_key' => $argv[4],
], $actor);
echo $version->id;
PHP;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Acolhimento;
use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\Pia;
use App\Models\User;
use App\Services\AuditRecorder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PiaAcolhimentoHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_legacy_history_remains_visible_after_a_confirmed_entry_on_child_and_pia_screens(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa Legada com Ingresso Fictícia']);
        DB::table('criancas')->where('id', $child->id)->update([
            'data_acolhimento' => '2025-04-03',
            'motivo_acolhimento' => 'Histórico anterior inteiramente fictício.',
            'status' => 'desligada',
        ]);
        $episode = $this->openEpisode($technical, $child, null);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('acolhimento.id', $episode->id)
                ->where('legadoAConferir.data', '2025-04-03')
                ->where('legadoAConferir.motivo', 'Histórico anterior inteiramente fictício.'));

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.create', ['crianca_id' => $child->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('criancas.0.episodio_aberto_id', $episode->id)
                ->where('criancas.0.legado_a_conferir.data', '2025-04-03'));

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => $episode->id,
            ])
            ->assertSessionHasNoErrors();

        $pia = Pia::query()->sole();
        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.show', $pia))
            ->assertInertia(fn (Assert $page) => $page
                ->where('vinculoAcolhimento.id', $episode->id)
                ->where('legadoAConferir.data', '2025-04-03')
                ->where('legadoAConferir.motivo', 'Histórico anterior inteiramente fictício.'));
    }

    public function test_pia_binding_and_historical_identification_stay_stable_after_reentry_on_screen_and_pdf(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create([
            'nome_completo' => 'Pessoa PIA Histórico Fictícia',
            'processo_numero' => 'PROCESSO-HISTORICO-01',
        ]);
        $firstEpisode = $this->openEpisode($technical, $child, 'PROCESSO-HISTORICO-01');

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => $firstEpisode->id,
            ])
            ->assertSessionHasNoErrors();
        $pia = Pia::query()->sole();

        $this->closeEpisode($technical, $child, $firstEpisode, '2026-10-01T10:00');
        $child->update(['processo_numero' => 'PROCESSO-ATUAL-02']);
        $secondEpisode = $this->openEpisode($technical, $child, 'PROCESSO-ATUAL-02', '2026-10-01T11:00');

        $this->assertSame($firstEpisode->id, $pia->fresh()->acolhimento_id);
        $this->assertNotSame($secondEpisode->id, $pia->acolhimento_id);

        DB::statement('SAVEPOINT pia_episode_link_attempt');
        try {
            Pia::query()->whereKey($pia)->update(['acolhimento_id' => $secondEpisode->id]);
            $this->fail('O PostgreSQL deveria rejeitar a troca do episódio vinculado ao PIA.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT pia_episode_link_attempt');
        }

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.show', $pia))
            ->assertInertia(fn (Assert $page) => $page
                ->where('vinculoAcolhimento.id', $firstEpisode->id)
                ->where('identificacao.Nº do processo', 'PROCESSO-HISTORICO-01')
                ->where('identificacao.Data de ingresso', '01/10/2026 09:00'));

        $renderer = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $renderer->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $renderer->shouldReceive('stream')->once()->andReturn(response('%PDF-1.4 SYNTHETIC'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data) use ($firstEpisode): bool {
            $this->assertSame('pdf.pia', $view);
            $this->assertSame($firstEpisode->id, $data['pia']->acolhimento->id);
            $this->assertSame('PROCESSO-HISTORICO-01', $data['identificacao']['Nº do processo']);
            $this->assertSame('01/10/2026 09:00', $data['identificacao']['Data de ingresso']);

            return true;
        })->andReturn($renderer);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.pdf', $pia))
            ->assertOk();
    }

    public function test_pia_created_after_child_judicial_update_uses_only_episode_snapshot_on_screen_and_pdf(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create([
            'nome_completo' => 'Pessoa Contexto Judicial Fictícia',
            'processo_numero' => 'PROCESSO-INGRESSO-01',
            'vara' => 'Vara do Ingresso Fictícia',
            'comarca' => 'Comarca do Ingresso Fictícia',
        ]);
        $episode = $this->openEpisode($technical, $child, 'PROCESSO-INGRESSO-01');

        $child->update([
            'processo_numero' => 'PROCESSO-CADASTRO-02',
            'vara' => 'Vara Atual Fictícia',
            'comarca' => 'Comarca Atual Fictícia',
        ]);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.create', ['crianca_id' => $child->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('criancas.0.episodio_aberto_id', $episode->id)
                ->where('criancas.0.processo_numero_snapshot', 'PROCESSO-INGRESSO-01')
                ->where('criancas.0.vara_snapshot', 'Vara do Ingresso Fictícia')
                ->where('criancas.0.comarca_snapshot', 'Comarca do Ingresso Fictícia')
                ->missingAll([
                    'criancas.0.processo_numero',
                    'criancas.0.vara',
                    'criancas.0.comarca',
                ]));

        $historicalContext = 'Processo nº PROCESSO-INGRESSO-01 — Vara do Ingresso Fictícia / Comarca do Ingresso Fictícia.';
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => $episode->id,
                'dados_acolhimento' => $historicalContext,
            ])
            ->assertSessionHasNoErrors();

        $pia = Pia::query()->sole();
        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.show', $pia))
            ->assertInertia(fn (Assert $page) => $page
                ->where('identificacao.Nº do processo', 'PROCESSO-INGRESSO-01')
                ->where('identificacao.Vara', 'Vara do Ingresso Fictícia')
                ->where('identificacao.Comarca', 'Comarca do Ingresso Fictícia')
                ->where('pia.dados_acolhimento', $historicalContext));

        $renderer = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $renderer->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $renderer->shouldReceive('stream')->once()->andReturn(response('%PDF-1.4 SYNTHETIC'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data) use ($historicalContext): bool {
            $this->assertSame('pdf.pia', $view);
            $this->assertSame('PROCESSO-INGRESSO-01', $data['identificacao']['Nº do processo']);
            $this->assertSame('Vara do Ingresso Fictícia', $data['identificacao']['Vara']);
            $this->assertSame('Comarca do Ingresso Fictícia', $data['identificacao']['Comarca']);
            $this->assertSame($historicalContext, $data['pia']->dados_acolhimento);
            $this->assertStringNotContainsString('PROCESSO-CADASTRO-02', json_encode($data['identificacao']));

            return true;
        })->andReturn($renderer);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.pdf', $pia))
            ->assertOk();
    }

    public function test_new_pia_only_binds_open_episode_and_legacy_null_link_is_never_inferred(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa PIA sem Inferência Fictícia']);
        $legacyPia = Pia::query()->create([
            'crianca_id' => $child->id,
            'created_by' => $technical->id,
        ]);
        $episode = $this->openEpisode($technical, $child, null);
        $this->closeEpisode($technical, $child, $episode, '2026-10-01T10:00');

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => null,
                'acolhimento_id' => $episode->id,
            ])
            ->assertSessionHasErrors('acolhimento_id');

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => null,
            ])
            ->assertSessionHasNoErrors();

        $newPia = Pia::query()->whereKeyNot($legacyPia->id)->sole();
        $this->assertNull($legacyPia->fresh()->acolhimento_id);
        $this->assertNull($newPia->acolhimento_id);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.show', $legacyPia))
            ->assertInertia(fn (Assert $page) => $page
                ->where('vinculoAcolhimento', null)
                ->where('identificacao.Data anterior de acolhimento (a conferir)', null));
    }

    public function test_linked_pia_pdf_renders_episode_and_separate_legacy_context(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa PDF Histórico Fictícia']);
        DB::table('criancas')->where('id', $child->id)->update([
            'data_acolhimento' => '2025-04-03',
            'motivo_acolhimento' => 'Histórico PDF inteiramente fictício.',
            'status' => 'desligada',
        ]);
        $episode = $this->openEpisode($technical, $child, null);
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => $episode->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.pdf', Pia::query()->sole()))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_episode_judicial_context_snapshot_is_server_derived_and_immutable(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create([
            'nome_completo' => 'Pessoa Processo Snapshot Fictícia',
            'processo_numero' => 'PROCESSO-SNAPSHOT-01',
            'vara' => 'Vara Snapshot Fictícia',
            'comarca' => 'Comarca Snapshot Fictícia',
        ]);
        $episode = $this->openEpisode($technical, $child, 'PROCESSO-SNAPSHOT-01');

        $child->update([
            'processo_numero' => 'PROCESSO-ATUALIZADO-02',
            'vara' => 'Vara Atualizada Fictícia',
            'comarca' => 'Comarca Atualizada Fictícia',
        ]);

        $episode->refresh();
        $this->assertSame('PROCESSO-SNAPSHOT-01', $episode->processo_numero_snapshot);
        $this->assertSame('Vara Snapshot Fictícia', $episode->vara_snapshot);
        $this->assertSame('Comarca Snapshot Fictícia', $episode->comarca_snapshot);
        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('identificacao.Nº do processo', 'PROCESSO-SNAPSHOT-01')
                ->where('identificacao.Vara', 'Vara Snapshot Fictícia')
                ->where('identificacao.Comarca', 'Comarca Snapshot Fictícia'));

        DB::statement('SAVEPOINT process_snapshot_attempt');
        try {
            Acolhimento::query()->whereKey($episode)->update([
                'vara_snapshot' => 'Vara Reescrita Inválida',
            ]);
            $this->fail('O PostgreSQL deveria rejeitar a reescrita do contexto judicial histórico.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT process_snapshot_attempt');
        }
    }

    public function test_stale_open_episode_precondition_rejects_old_form_without_pia_or_success_audit(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa PIA Concorrente Fictícia']);
        $firstEpisode = $this->openEpisode($technical, $child, null);

        $this->closeEpisode($technical, $child, $firstEpisode, '2026-10-01T10:00');
        $secondEpisode = $this->openEpisode($technical, $child, null, '2026-10-01T11:00');

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => $firstEpisode->id,
                'dados_acolhimento' => 'Texto do primeiro episódio inteiramente fictício.',
            ])
            ->assertRedirect(route('pias.create', ['crianca_id' => $child->id]))
            ->assertSessionHasErrors([
                'expected_acolhimento_id' => 'O episódio de acolhimento mudou. Atualize o formulário antes de registrar o PIA.',
            ]);

        $this->assertDatabaseCount('pias', 0);
        $this->assertSame(0, AuditEvent::query()->where('action', 'pia.created')->count());

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => $secondEpisode->id,
                'dados_acolhimento' => 'Texto atualizado do segundo episódio inteiramente fictício.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($secondEpisode->id, Pia::query()->sole()->acolhimento_id);
        $this->assertSame(1, AuditEvent::query()->where('action', 'pia.created')->count());
    }

    public function test_stale_null_precondition_and_foreign_episode_id_are_rejected_without_effect(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa PIA sem Episódio Fictícia']);
        $otherChild = Crianca::query()->create(['nome_completo' => 'Outra Pessoa PIA Fictícia']);
        $otherEpisode = $this->openEpisode($technical, $otherChild, null);

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => $otherEpisode->id,
            ])
            ->assertSessionHasErrors('expected_acolhimento_id');

        $episode = $this->openEpisode($technical, $child, null);
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'expected_acolhimento_id' => null,
            ])
            ->assertRedirect(route('pias.create', ['crianca_id' => $child->id]))
            ->assertSessionHasErrors('expected_acolhimento_id');

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('pias.store'), ['crianca_id' => $child->id])
            ->assertSessionHasErrors('expected_acolhimento_id');

        $this->assertDatabaseCount('pias', 0);
        $this->assertSame(0, AuditEvent::query()->where('action', 'pia.created')->count());
        $this->assertSame($episode->id, $child->acolhimentos()->whereNull('encerrado_em')->sole()->id);
    }

    public function test_pia_creation_and_episode_link_roll_back_when_audit_fails(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa PIA Rollback Fictícia']);
        $episode = $this->openEpisode($technical, $child, null);
        $audit = Mockery::mock(AuditRecorder::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Falha sintética da auditoria do PIA.'));
        $this->app->instance(AuditRecorder::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->actingAsWithVerifiedMfa($technical)
                ->post(route('pias.store'), [
                    'crianca_id' => $child->id,
                    'expected_acolhimento_id' => $episode->id,
                ]);
            $this->fail('A falha sintética deveria interromper a requisição.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha sintética da auditoria do PIA.', $exception->getMessage());
        }

        $this->assertDatabaseCount('pias', 0);
    }

    private function openEpisode(
        User $technical,
        Crianca $child,
        ?string $expectedProcess,
        string $effectiveAt = '2026-10-01T09:00',
    ): Acolhimento {
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.store', $child), [
                'ingresso_em' => $effectiveAt,
                'motivo' => 'Ingresso inteiramente fictício.',
                'fundamento' => null,
                'origem_codigo' => 'conselho_tutelar',
                'origem_complemento' => null,
                'orgao_condutor_codigo' => 'conselho_tutelar',
                'orgao_condutor_complemento' => null,
                'pessoa_condutora' => 'Pessoa Condutora Fictícia',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors();

        $episode = Acolhimento::query()->where('crianca_id', $child->id)->latest('id')->firstOrFail();
        $this->assertSame($expectedProcess, $episode->processo_numero_snapshot);

        return $episode;
    }

    private function closeEpisode(User $technical, Crianca $child, Acolhimento $episode, string $effectiveAt): void
    {
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]), [
                'tipo' => 'desacolhimento',
                'efetiva_em' => $effectiveAt,
                'motivo' => 'Saída inteiramente fictícia.',
                'fundamento' => null,
                'local_destino' => 'Destino Fictício',
                'observacao' => null,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors();
    }
}

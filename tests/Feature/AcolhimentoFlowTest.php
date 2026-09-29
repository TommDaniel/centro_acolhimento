<?php

namespace Tests\Feature;

use App\Models\Acolhimento;
use App\Models\AcolhimentoMovimentacao;
use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\User;
use App\Services\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AcolhimentoFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_authorized_technical_opens_episode_with_server_context_utc_and_minimized_audit(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa de Ingresso Fictícia']);
        $payload = $this->openingPayload([
            'motivo' => 'Motivo sintético que não deve ir para auditoria.',
            'pessoa_condutora' => 'Pessoa Condutora Sintética',
        ]);

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.store', $child), $payload)
            ->assertRedirect(route('criancas.show', $child));

        $episode = Acolhimento::query()->with('movimentacoes')->sole();
        $this->assertSame($child->id, $episode->crianca_id);
        $this->assertSame($technical->unidade_id, $episode->unidade_id);
        $this->assertSame('2026-10-01 12:00:00', $episode->ingresso_em->format('Y-m-d H:i:s'));
        $this->assertSame('ingresso', $episode->movimentacoes->sole()->tipo->value);
        $this->assertSame('na_unidade', $episode->movimentacoes->sole()->situacao_resultante->value);

        $audit = AuditEvent::query()->where('action', 'acolhimento.opened')->sole();
        $this->assertSame($technical->id, $audit->actor_id);
        $this->assertContains('pessoa_condutora', $audit->changed_fields);
        $this->assertStringNotContainsString($payload['motivo'], $audit->toJson());
        $this->assertStringNotContainsString($payload['pessoa_condutora'], $audit->toJson());
    }

    public function test_full_sequence_preserves_each_fact_and_only_desacolhimento_closes_episode(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa da Sequência Fictícia']);
        $episode = $this->openEpisode($technical, $child);

        $this->recordMovement($technical, $child, $episode, 'evasao', '2026-10-01T10:00', [
            'motivo' => 'Evasão inteiramente fictícia.',
        ]);
        $this->assertNull($episode->fresh()->encerrado_em);

        $this->recordMovement($technical, $child, $episode, 'retorno', '2026-10-01T11:00');
        $this->recordMovement($technical, $child, $episode, 'internacao', '2026-10-01T12:00', [
            'motivo' => 'Internação inteiramente fictícia.',
            'local_destino' => 'Hospital Fictício',
        ]);
        $this->assertNull($episode->fresh()->encerrado_em);

        $this->recordMovement($technical, $child, $episode, 'retorno', '2026-10-01T13:00');
        $this->recordMovement($technical, $child, $episode, 'desacolhimento', '2026-10-01T14:00', [
            'motivo' => 'Desacolhimento inteiramente fictício.',
            'local_destino' => 'Destino Fictício',
        ]);

        $episode->refresh();
        $movements = $episode->movimentacoes()->orderBy('efetiva_em')->get();

        $this->assertSame(
            ['ingresso', 'evasao', 'retorno', 'internacao', 'retorno', 'desacolhimento'],
            $movements->pluck('tipo')->map->value->all(),
        );
        $this->assertSame(
            ['na_unidade', 'evadido', 'na_unidade', 'internado', 'na_unidade', 'desacolhido'],
            $movements->pluck('situacao_resultante')->map->value->all(),
        );
        $this->assertSame('2026-10-01 17:00:00', $episode->encerrado_em->format('Y-m-d H:i:s'));
        $this->assertSame($movements->last()->id, $episode->encerrado_por_movimentacao_id);
        $this->assertSame('acolhida', $child->fresh()->status);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('acolhimento.situacao', 'desacolhido')
                ->where('acolhimento.aberto', false)
                ->has('linhaDoTempoAcolhimento', 6));
    }

    public function test_invalid_transition_and_backdated_fact_fail_without_partial_write(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa de Transição Fictícia']);
        $episode = $this->openEpisode($technical, $child);

        $this->recordMovement($technical, $child, $episode, 'evasao', '2026-10-01T10:00', [
            'motivo' => 'Evasão inteiramente fictícia.',
        ]);

        $this->actingAsWithVerifiedMfa($technical)
            ->post(
                route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]),
                $this->movementPayload('internacao', '2026-10-01T11:00', [
                    'motivo' => 'Internação inválida fictícia.',
                    'local_destino' => 'Hospital Fictício',
                ]),
            )
            ->assertSessionHasErrors('tipo');

        $this->actingAsWithVerifiedMfa($technical)
            ->post(
                route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]),
                $this->movementPayload('retorno', '2026-10-01T09:30'),
            )
            ->assertSessionHasErrors('efetiva_em');

        $this->assertSame(2, $episode->movimentacoes()->count());
        $this->assertSame(2, AuditEvent::query()
            ->whereIn('action', ['acolhimento.opened', 'acolhimento.movement.recorded'])
            ->count());
    }

    public function test_idempotent_replay_returns_same_fact_and_divergent_replay_is_rejected(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa de Retry Fictícia']);
        $payload = $this->openingPayload();

        foreach ([1, 2] as $attempt) {
            $this->actingAsWithVerifiedMfa($technical)
                ->post(route('criancas.acolhimentos.store', $child), $payload)
                ->assertRedirect(route('criancas.show', $child));
        }

        $this->assertSame(1, Acolhimento::query()->count());
        $this->assertSame(1, AcolhimentoMovimentacao::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'acolhimento.opened')->count());

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.store', $child), [
                ...$payload,
                'motivo' => 'Payload divergente fictício.',
            ])
            ->assertSessionHasErrors('idempotency_key');

        $episode = Acolhimento::query()->sole();
        $movement = $this->movementPayload('evasao', '2026-10-01T10:00', [
            'motivo' => 'Evasão de retry fictícia.',
        ]);

        foreach ([1, 2] as $attempt) {
            $this->actingAsWithVerifiedMfa($technical)
                ->post(route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]), $movement)
                ->assertRedirect(route('criancas.show', $child));
        }

        $this->assertSame(2, AcolhimentoMovimentacao::query()->count());

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]), [
                ...$movement,
                'motivo' => 'Movimento divergente fictício.',
            ])
            ->assertSessionHasErrors('idempotency_key');
    }

    public function test_postgresql_partial_unique_index_rejects_second_open_episode(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa de Unicidade Fictícia']);
        $this->openEpisode($technical, $child);

        $this->expectException(QueryException::class);

        Acolhimento::query()->forceCreate([
            'crianca_id' => $child->id,
            'ingresso_em' => CarbonImmutable::parse('2026-10-02 12:00:00', 'UTC'),
            'motivo' => 'Segundo episódio aberto inválido e fictício.',
            'origem_codigo' => 'conselho_tutelar',
            'orgao_condutor_codigo' => 'conselho_tutelar',
            'pessoa_condutora' => 'Pessoa Condutora Fictícia',
            'created_by' => $technical->id,
            'recorded_at' => now('UTC'),
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    public function test_administrator_can_open_with_other_codes_only_when_complements_are_present(): void
    {
        $administrator = User::factory()->administrator()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa com Origem Fictícia']);

        $this->actingAsWithVerifiedMfa($administrator)
            ->post(route('criancas.acolhimentos.store', $child), $this->openingPayload([
                'origem_codigo' => 'outro',
                'origem_complemento' => null,
                'orgao_condutor_codigo' => 'outro',
                'orgao_condutor_complemento' => null,
            ]))
            ->assertSessionHasErrors(['origem_complemento', 'orgao_condutor_complemento']);

        $this->actingAsWithVerifiedMfa($administrator)
            ->post(route('criancas.acolhimentos.store', $child), $this->openingPayload([
                'origem_codigo' => 'outro',
                'origem_complemento' => 'Origem sintética selecionável',
                'orgao_condutor_codigo' => 'outro',
                'orgao_condutor_complemento' => 'Órgão sintético selecionável',
            ]))
            ->assertRedirect(route('criancas.show', $child));

        $episode = Acolhimento::query()->sole();
        $this->assertSame($administrator->id, $episode->created_by);
        $this->assertSame('Origem sintética selecionável', $episode->origem_complemento);
        $this->assertSame('Órgão sintético selecionável', $episode->orgao_condutor_complemento);
    }

    public function test_new_episode_cannot_overlap_previous_timeline(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa com Reingresso Fictício']);
        $episode = $this->openEpisode($technical, $child);
        $this->recordMovement($technical, $child, $episode, 'desacolhimento', '2026-10-01T10:00', [
            'motivo' => 'Saída fictícia antes do reingresso.',
            'fundamento' => 'Fundamento de saída fictício.',
            'local_destino' => 'Destino Fictício',
        ]);

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.store', $child), $this->openingPayload([
                'ingresso_em' => '2026-10-01T09:30',
            ]))
            ->assertSessionHasErrors('ingresso_em');

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.store', $child), $this->openingPayload([
                'ingresso_em' => '2026-10-01T11:00',
                'motivo' => 'Motivo do segundo episódio fictício.',
                'fundamento' => 'Fundamento do segundo episódio fictício.',
                'origem_codigo' => 'outro',
                'origem_complemento' => 'Origem do segundo episódio fictícia',
                'orgao_condutor_codigo' => 'outro',
                'orgao_condutor_complemento' => 'Órgão do segundo episódio fictício',
                'pessoa_condutora' => 'Pessoa Condutora do Segundo Episódio Fictícia',
            ]))
            ->assertRedirect(route('criancas.show', $child));

        $this->assertSame(2, Acolhimento::query()->where('crianca_id', $child->id)->count());
        $this->assertSame(1, Acolhimento::query()
            ->where('crianca_id', $child->id)
            ->whereNull('encerrado_em')
            ->count());

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('acolhimento.motivo', 'Motivo do segundo episódio fictício.')
                ->where('acolhimento.fundamento', 'Fundamento do segundo episódio fictício.')
                ->where('acolhimento.origem', 'Outro')
                ->where('acolhimento.origem_complemento', 'Origem do segundo episódio fictícia')
                ->where('acolhimento.orgao_condutor', 'Outro')
                ->where('acolhimento.orgao_condutor_complemento', 'Órgão do segundo episódio fictício')
                ->where('acolhimento.pessoa_condutora', 'Pessoa Condutora do Segundo Episódio Fictícia')
                ->has('linhaDoTempoAcolhimento', 3)
                ->where('linhaDoTempoAcolhimento.0.episodio_ordem', 1)
                ->where('linhaDoTempoAcolhimento.0.episodio_contexto.fundamento', 'Fundamento inteiramente fictício.')
                ->where('linhaDoTempoAcolhimento.0.episodio_contexto.origem', 'Conselho Tutelar')
                ->where('linhaDoTempoAcolhimento.0.episodio_contexto.orgao_condutor', 'Conselho Tutelar')
                ->where('linhaDoTempoAcolhimento.0.episodio_contexto.pessoa_condutora', 'Pessoa Condutora Fictícia')
                ->where('linhaDoTempoAcolhimento.1.fundamento', 'Fundamento de saída fictício.')
                ->where('linhaDoTempoAcolhimento.2.episodio_ordem', 2)
                ->where('linhaDoTempoAcolhimento.2.episodio_contexto.motivo', 'Motivo do segundo episódio fictício.')
                ->where('linhaDoTempoAcolhimento.2.episodio_contexto.origem_complemento', 'Origem do segundo episódio fictícia')
                ->where('linhaDoTempoAcolhimento.2.episodio_contexto.orgao_condutor_complemento', 'Órgão do segundo episódio fictício'));
    }

    public function test_legacy_record_is_visible_as_unconfirmed_without_invented_time_or_exit(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa Legada Fictícia']);
        DB::table('criancas')->where('id', $child->id)->update([
            'data_acolhimento' => '2025-04-03',
            'motivo_acolhimento' => 'Motivo legado inteiramente fictício.',
            'status' => 'desligada',
        ]);

        $this->assertDatabaseCount('acolhimentos', 0);
        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('acolhimento', null)
                ->where('legadoAConferir.data', '2025-04-03')
                ->where('legadoAConferir.motivo', 'Motivo legado inteiramente fictício.')
                ->where('linhaDoTempoAcolhimento', []));

        $newChild = Crianca::query()->create(['nome_completo' => 'Pessoa sem Ingresso Fictícia']);
        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $newChild))
            ->assertInertia(fn (Assert $page) => $page
                ->where('acolhimento', null)
                ->where('legadoAConferir', null));
    }

    public function test_list_and_pia_form_use_episode_projection_instead_of_legacy_status(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa com Projeção Fictícia']);
        $this->openEpisode($technical, $child);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('criancas.data.0.id', $child->id)
                ->where('criancas.data.0.acolhimento_situacao', 'na_unidade')
                ->where('criancas.data.0.acolhimento_fonte', 'episodio')
                ->missingAll([
                    'criancas.data.0.status',
                    'criancas.data.0.motivo_ingresso',
                    'criancas.data.0.legado_a_conferir',
                    'criancas.data.0.ingresso_em',
                ]));

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('criancas.0.id', $child->id)
                ->where('criancas.0.acolhimento_situacao', 'na_unidade')
                ->where('criancas.0.acolhimento_fonte', 'episodio')
                ->where('criancas.0.motivo_ingresso', 'Ingresso inteiramente fictício.')
                ->missing('criancas.0.status'));
    }

    public function test_direct_legacy_status_fields_and_client_context_are_prohibited(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa Protegida Fictícia']);

        $this->actingAsWithVerifiedMfa($technical)
            ->put(route('criancas.update', $child), [
                'nome_completo' => 'Pessoa Protegida Fictícia',
                'data_acolhimento' => '2026-10-01',
                'motivo_acolhimento' => 'Alteração paralela fictícia.',
                'status' => 'desligada',
            ])
            ->assertSessionHasErrors(['data_acolhimento', 'motivo_acolhimento', 'status']);

        $this->actingAsWithVerifiedMfa($technical)
            ->postJson(route('criancas.acolhimentos.store', $child), [
                ...$this->openingPayload(),
                'unidade_id' => PHP_INT_MAX,
                'created_by' => PHP_INT_MAX,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('acolhimentos', 0);
        $this->assertSame('acolhida', $child->fresh()->status);
    }

    public function test_visitor_inactive_pending_mfa_and_nested_id_swap_are_denied(): void
    {
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa de Autorização Fictícia']);
        $payload = $this->openingPayload();

        $this->post(route('criancas.acolhimentos.store', $child), $payload)
            ->assertRedirect(route('login'));

        foreach ([User::factory()->inactive()->create(), User::factory()->create(['status' => 'pendente_mfa'])] as $denied) {
            $this->actingAsWithVerifiedMfa($denied)
                ->post(route('criancas.acolhimentos.store', $child), $payload)
                ->assertForbidden();
        }

        $technical = User::factory()->create();
        $episode = $this->openEpisode($technical, $child);
        $otherChild = Crianca::query()->create(['nome_completo' => 'Outra Pessoa Fictícia']);

        $this->actingAsWithVerifiedMfa($technical)
            ->post(
                route('criancas.acolhimentos.movimentacoes.store', [$otherChild, $episode]),
                $this->movementPayload('evasao', '2026-10-01T10:00', ['motivo' => 'IDOR fictício.']),
            )
            ->assertNotFound();

        $this->assertSame(1, $episode->movimentacoes()->count());
    }

    public function test_audit_failure_rolls_back_episode_movement_and_closure_projection(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa de Rollback Fictícia']);
        $episode = $this->openEpisode($technical, $child);
        $audit = Mockery::mock(AuditRecorder::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Falha sintética da auditoria.'));
        $this->app->instance(AuditRecorder::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->actingAsWithVerifiedMfa($technical)
                ->post(
                    route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]),
                    $this->movementPayload('desacolhimento', '2026-10-01T10:00', [
                        'motivo' => 'Saída fictícia que deve reverter.',
                        'local_destino' => 'Destino Fictício',
                    ]),
                );
            $this->fail('A falha sintética deveria interromper a requisição.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha sintética da auditoria.', $exception->getMessage());
        }

        $this->assertSame(1, $episode->movimentacoes()->count());
        $this->assertNull($episode->fresh()->encerrado_em);
        $this->assertNull($episode->encerrado_por_movimentacao_id);
    }

    public function test_postgresql_rejects_rewriting_or_deleting_assistential_history(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa de Histórico Fictícia']);
        $episode = $this->openEpisode($technical, $child);
        $movement = $episode->movimentacoes()->sole();

        DB::statement('SAVEPOINT movement_history_attempt');
        try {
            AcolhimentoMovimentacao::query()->whereKey($movement)->update(['motivo' => 'Reescrita inválida fictícia.']);
            $this->fail('O PostgreSQL deveria rejeitar a reescrita de movimentação.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT movement_history_attempt');
        }

        DB::statement('SAVEPOINT episode_history_attempt');
        try {
            Acolhimento::query()->whereKey($episode)->update(['motivo' => 'Reescrita inválida fictícia.']);
            $this->fail('O PostgreSQL deveria rejeitar a reescrita do episódio.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT episode_history_attempt');
        }

        DB::statement('SAVEPOINT movement_delete_attempt');
        try {
            AcolhimentoMovimentacao::query()->whereKey($movement)->delete();
            $this->fail('O PostgreSQL deveria rejeitar a exclusão de movimentação.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT movement_delete_attempt');
        }

        $this->assertModelExists($episode);
        $this->assertModelExists($movement);
    }

    /** @param array<string, mixed> $overrides */
    private function openingPayload(array $overrides = []): array
    {
        return array_replace([
            'ingresso_em' => '2026-10-01T09:00',
            'motivo' => 'Ingresso inteiramente fictício.',
            'fundamento' => 'Fundamento inteiramente fictício.',
            'origem_codigo' => 'conselho_tutelar',
            'origem_complemento' => null,
            'orgao_condutor_codigo' => 'conselho_tutelar',
            'orgao_condutor_complemento' => null,
            'pessoa_condutora' => 'Pessoa Condutora Fictícia',
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    private function openEpisode(User $actor, Crianca $child): Acolhimento
    {
        $this->actingAsWithVerifiedMfa($actor)
            ->post(route('criancas.acolhimentos.store', $child), $this->openingPayload())
            ->assertRedirect(route('criancas.show', $child));

        return Acolhimento::query()->where('crianca_id', $child->id)->sole();
    }

    /** @param array<string, mixed> $overrides */
    private function movementPayload(string $type, string $effectiveAt, array $overrides = []): array
    {
        return array_replace([
            'tipo' => $type,
            'efetiva_em' => $effectiveAt,
            'motivo' => null,
            'fundamento' => null,
            'local_destino' => null,
            'observacao' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function recordMovement(
        User $actor,
        Crianca $child,
        Acolhimento $episode,
        string $type,
        string $effectiveAt,
        array $overrides = [],
    ): void {
        $this->actingAsWithVerifiedMfa($actor)
            ->post(
                route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]),
                $this->movementPayload($type, $effectiveAt, $overrides),
            )
            ->assertRedirect(route('criancas.show', $child));
    }
}

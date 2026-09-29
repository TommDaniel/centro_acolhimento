<?php

namespace Tests\Feature;

use App\Actions\RecordCriancaInformacaoEscolar;
use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\CriancaInformacaoEscolar;
use App\Models\User;
use App\Policies\CriancaPolicy;
use App\Services\AuditRecorder;
use App\Services\CriancaInformacaoEscolarHistory;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CriancaInformacaoEscolarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_child_creation_with_known_school_information_is_atomic_audited_and_visible(): void
    {
        $technical = User::factory()->create();
        $payload = [
            'nome_completo' => 'Pessoa Escolar Inicial Fictícia',
            'informacao_escolar' => $this->schoolPayload([
                'escola_nome' => 'Escola Inicial Fictícia',
                'matricula' => 'MATR-FICT-001',
            ]),
        ];

        $response = $this->actingAsWithVerifiedMfa($technical)->post(route('criancas.store'), $payload);

        $child = Crianca::query()->sole();
        $version = CriancaInformacaoEscolar::query()->sole();
        $response->assertRedirect(route('criancas.show', $child));
        $this->assertSame($child->id, $version->crianca_id);
        $this->assertSame($technical->id, $version->created_by);
        $this->assertSame('Escola Inicial Fictícia', $version->escola_nome);
        $this->assertNull($version->versao_anterior_id);

        $audit = AuditEvent::query()->where('action', 'crianca.informacao_escolar.created')->sole();
        $this->assertContains('escola_nome', $audit->changed_fields);
        $this->assertStringNotContainsString('Escola Inicial Fictícia', $audit->toJson());
        $this->assertStringNotContainsString('MATR-FICT-001', $audit->toJson());

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('informacaoEscolarAtual.situacao', 'Matriculada')
                ->where('informacaoEscolarAtual.escola_nome', 'Escola Inicial Fictícia')
                ->where('informacaoEscolarAtual.registrado_por', $technical->name)
                ->where('historicoInformacaoEscolar.data', [])
                ->where('historicoInformacaoEscolar.tem_mais', false)
                ->where('historicoInformacaoEscolar.proximo_antes_de', null));
    }

    public function test_child_creation_without_school_information_does_not_presume_a_school(): void
    {
        $technical = User::factory()->create();

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.store'), ['nome_completo' => 'Pessoa Sem Informação Escolar Fictícia'])
            ->assertRedirect();

        $this->assertDatabaseCount('criancas', 1);
        $this->assertDatabaseCount('crianca_informacoes_escolares', 0);
    }

    public function test_empty_optional_school_information_array_does_not_presume_school_information(): void
    {
        $technical = User::factory()->create();

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.store'), [
                'nome_completo' => 'Pessoa Payload Escolar Vazio Fictícia',
                'informacao_escolar' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('criancas', ['nome_completo' => 'Pessoa Payload Escolar Vazio Fictícia']);
        $this->assertDatabaseCount('crianca_informacoes_escolares', 0);
    }

    public function test_explicit_unknown_status_has_no_invented_school_details(): void
    {
        $technical = User::factory()->create();
        $child = $this->child();
        $invalid = $this->schoolPayload([
            'situacao_codigo' => 'nao_informada',
            'escola_nome' => 'Escola que não pode ser presumida',
            'vigente_em' => '2026-09-29',
        ]);

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.informacoes-escolares.store', $child), $invalid)
            ->assertSessionHasErrors(['escola_nome', 'vigente_em']);

        $valid = $this->schoolPayload([
            'situacao_codigo' => 'nao_informada',
            'escola_nome' => null,
            'rede_codigo' => null,
            'matricula' => null,
            'ano_serie' => null,
            'turma' => null,
            'turno_codigo' => null,
            'vigente_em' => null,
        ]);
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.informacoes-escolares.store', $child), $valid)
            ->assertRedirect(route('criancas.show', $child));

        $version = CriancaInformacaoEscolar::query()->sole();
        $this->assertSame('nao_informada', $version->situacao_codigo);
        $this->assertNull($version->escola_nome);
        $this->assertNull($version->matricula);
        $this->assertNull($version->vigente_em);
    }

    public function test_other_options_require_complements_and_invalid_codes_are_rejected(): void
    {
        $technical = User::factory()->create();
        $child = $this->child();
        $payload = $this->schoolPayload([
            'situacao_codigo' => 'outra',
            'situacao_complemento' => null,
            'rede_codigo' => 'outra',
            'rede_complemento' => null,
            'turno_codigo' => 'outro',
            'turno_complemento' => null,
            'fonte_codigo' => 'outra',
            'fonte_complemento' => null,
        ]);

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.informacoes-escolares.store', $child), $payload)
            ->assertSessionHasErrors([
                'situacao_complemento', 'rede_complemento',
                'turno_complemento', 'fonte_complemento',
            ]);

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.informacoes-escolares.store', $child), $this->schoolPayload([
                'situacao_codigo' => 'codigo_invalido',
                'rede_codigo' => 'codigo_invalido',
                'turno_codigo' => 'codigo_invalido',
                'fonte_codigo' => 'codigo_invalido',
            ]))
            ->assertSessionHasErrors(['situacao_codigo', 'rede_codigo', 'turno_codigo', 'fonte_codigo']);

        $this->assertDatabaseCount('crianca_informacoes_escolares', 0);
    }

    public function test_update_creates_an_idempotent_append_only_version_and_preserves_history(): void
    {
        $technical = User::factory()->create();
        $child = $this->child();
        $first = $this->schoolPayload([
            'escola_nome' => 'Escola Mantida Fictícia',
            'matricula' => 'MATR-FICT-ANTERIOR',
            'vigente_em' => '2026-09-28',
        ]);
        $second = $this->schoolPayload([
            'escola_nome' => 'Escola Mantida Fictícia',
            'rede_codigo' => 'outra',
            'rede_complemento' => 'Rede conveniada fictícia',
            'matricula' => 'MATR-FICT-ATUAL',
            'vigente_em' => '2026-09-29',
        ]);

        $this->record($technical, $child, $first);
        $this->record($technical, $child, $second);
        $this->record($technical, $child, $second);

        $versions = CriancaInformacaoEscolar::query()->orderBy('id')->get();
        $this->assertCount(2, $versions);
        $this->assertSame($versions[0]->id, $versions[1]->versao_anterior_id);
        $this->assertSame('MATR-FICT-ANTERIOR', $versions[0]->matricula);
        $this->assertSame('MATR-FICT-ATUAL', $versions[1]->matricula);
        $this->assertSame(2, AuditEvent::query()->where('action', 'crianca.informacao_escolar.created')->count());

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('informacaoEscolarAtual.escola_nome', 'Escola Mantida Fictícia')
                ->where('informacaoEscolarAtual.rede', 'Outra')
                ->where('informacaoEscolarAtual.rede_complemento', 'Rede conveniada fictícia')
                ->where('informacaoEscolarAtual.matricula', 'MATR-FICT-ATUAL')
                ->where('informacaoEscolarAtual.vigente_em', '2026-09-29')
                ->where('historicoInformacaoEscolar.data.0.escola_nome', 'Escola Mantida Fictícia')
                ->where('historicoInformacaoEscolar.data.0.rede', 'Municipal')
                ->where('historicoInformacaoEscolar.data.0.matricula', 'MATR-FICT-ANTERIOR')
                ->where('historicoInformacaoEscolar.data.0.vigente_em', '2026-09-28')
                ->has('historicoInformacaoEscolar.data', 1));
    }

    public function test_chain_head_is_current_when_recorded_clock_moves_backwards(): void
    {
        $technical = User::factory()->create();
        $child = $this->child('Pessoa Relógio Regressivo Fictícia');
        $action = app(RecordCriancaInformacaoEscolar::class);

        try {
            CarbonImmutable::setTestNow('2026-09-29 12:00:00 UTC');
            $first = $action->handle($child, $this->schoolPayload(['matricula' => 'REL-V1']), $technical);
            CarbonImmutable::setTestNow('2026-09-29 11:00:00 UTC');
            $second = $action->handle($child, $this->schoolPayload(['matricula' => 'REL-V2']), $technical);
            CarbonImmutable::setTestNow('2026-09-29 10:00:00 UTC');
            $third = $action->handle($child, $this->schoolPayload(['matricula' => 'REL-V3']), $technical);
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame($first->id, $second->versao_anterior_id);
        $this->assertSame($second->id, $third->versao_anterior_id);
        $this->assertSame($third->id, app(CriancaInformacaoEscolarHistory::class)->current($child)?->id);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('informacaoEscolarAtual.id', $third->id)
                ->where('informacaoEscolarAtual.matricula', 'REL-V3')
                ->where('historicoInformacaoEscolar.data.0.id', $second->id)
                ->where('historicoInformacaoEscolar.data.1.id', $first->id));
    }

    public function test_history_is_loaded_in_bounded_cursor_pages_beyond_twenty_versions(): void
    {
        $technical = User::factory()->create();
        $child = $this->child('Pessoa Histórico Escolar Longo Fictícia');
        $action = app(RecordCriancaInformacaoEscolar::class);
        $versions = collect();

        foreach (range(1, 23) as $number) {
            $versions->push($action->handle($child, $this->schoolPayload([
                'matricula' => sprintf('PAG-FICT-%02d', $number),
            ]), $technical));
        }

        $firstPage = $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child));
        $firstPage->assertInertia(fn (Assert $page) => $page
            ->where('informacaoEscolarAtual.id', $versions[22]->id)
            ->has('historicoInformacaoEscolar.data', 20)
            ->where('historicoInformacaoEscolar.data.0.id', $versions[21]->id)
            ->where('historicoInformacaoEscolar.data.19.id', $versions[2]->id)
            ->where('historicoInformacaoEscolar.tem_mais', true)
            ->where('historicoInformacaoEscolar.proximo_antes_de', $versions[2]->id));

        $this->actingAsWithVerifiedMfa($technical)
            ->getJson(route('criancas.informacoes-escolares.index', [
                'crianca' => $child,
                'before_id' => $versions[2]->id,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $versions[1]->id)
            ->assertJsonPath('data.0.matricula', 'PAG-FICT-02')
            ->assertJsonPath('data.1.id', $versions[0]->id)
            ->assertJsonPath('tem_mais', false)
            ->assertJsonPath('proximo_antes_de', null);

        $this->assertSame(1, AuditEvent::query()
            ->where('action', 'crianca.informacao_escolar.history.viewed')
            ->where('subject_id', (string) $child->id)
            ->count());
    }

    public function test_visitor_inactive_and_pending_mfa_are_denied(): void
    {
        $child = $this->child();
        $payload = $this->schoolPayload();

        $this->post(route('criancas.informacoes-escolares.store', $child), $payload)
            ->assertRedirect(route('login'));
        $this->get(route('criancas.informacoes-escolares.index', [
            'crianca' => $child,
            'before_id' => 100,
        ]))->assertRedirect(route('login'));

        $inactive = User::factory()->inactive()->create();
        $this->actingAsWithVerifiedMfa($inactive)
            ->post(route('criancas.informacoes-escolares.store', $child), $payload)
            ->assertForbidden();
        $this->actingAsWithVerifiedMfa($inactive)
            ->get(route('criancas.informacoes-escolares.index', [
                'crianca' => $child,
                'before_id' => 100,
            ]))->assertForbidden();

        $pendingMfa = User::factory()->create(['status' => 'pendente_mfa']);
        $this->actingAsWithVerifiedMfa($pendingMfa)
            ->post(route('criancas.informacoes-escolares.store', $child), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('crianca_informacoes_escolares', 0);
    }

    public function test_policy_denies_child_id_from_another_organization(): void
    {
        $technical = User::factory()->create();
        $foreignChild = new Crianca(['nome_completo' => 'Pessoa de Outra Organização Fictícia']);
        $foreignChild->setAttribute('organizacao_id', PHP_INT_MAX);
        $policy = app(CriancaPolicy::class);

        $this->assertFalse($policy->view($technical, $foreignChild));
        $this->assertFalse($policy->update($technical, $foreignChild));
    }

    public function test_audit_failure_rolls_back_child_and_initial_school_version(): void
    {
        $technical = User::factory()->create();
        $audit = Mockery::mock(AuditRecorder::class);
        $audit->shouldReceive('record')->once()->andReturn(new AuditEvent);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Falha sintética da auditoria escolar.'));
        $this->app->instance(AuditRecorder::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->actingAsWithVerifiedMfa($technical)
                ->post(route('criancas.store'), [
                    'nome_completo' => 'Pessoa de Rollback Escolar Fictícia',
                    'informacao_escolar' => $this->schoolPayload(),
                ]);
            $this->fail('A falha sintética deveria interromper a criação.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha sintética da auditoria escolar.', $exception->getMessage());
        }

        $this->assertDatabaseCount('criancas', 0);
        $this->assertDatabaseCount('crianca_informacoes_escolares', 0);
    }

    public function test_postgresql_rejects_rewrite_delete_and_cross_child_version_chain(): void
    {
        $technical = User::factory()->create();
        $firstChild = $this->child('Primeira Pessoa Escolar Fictícia');
        $secondChild = $this->child('Segunda Pessoa Escolar Fictícia');
        $this->record($technical, $firstChild, $this->schoolPayload());
        $version = CriancaInformacaoEscolar::query()->sole();

        DB::statement('SAVEPOINT school_history_update_attempt');
        try {
            CriancaInformacaoEscolar::query()->whereKey($version)->update(['escola_nome' => 'Reescrita inválida']);
            $this->fail('O PostgreSQL deveria rejeitar a reescrita do histórico escolar.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT school_history_update_attempt');
        }

        DB::statement('SAVEPOINT school_history_delete_attempt');
        try {
            CriancaInformacaoEscolar::query()->whereKey($version)->delete();
            $this->fail('O PostgreSQL deveria rejeitar a exclusão do histórico escolar.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT school_history_delete_attempt');
        }

        DB::statement('SAVEPOINT school_history_cross_child_attempt');
        try {
            CriancaInformacaoEscolar::query()->forceCreate([
                ...$this->schoolPayload(),
                'crianca_id' => $secondChild->id,
                'versao_anterior_id' => $version->id,
                'created_by' => $technical->id,
                'recorded_at' => now('UTC'),
            ]);
            $this->fail('O PostgreSQL deveria rejeitar cadeia entre pessoas distintas.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT school_history_cross_child_attempt');
        }

        $this->assertModelExists($version);
        $this->assertDatabaseCount('crianca_informacoes_escolares', 1);
    }

    /** @param array<string, mixed> $overrides */
    private function schoolPayload(array $overrides = []): array
    {
        return array_replace([
            'situacao_codigo' => 'matriculada',
            'situacao_complemento' => null,
            'escola_nome' => 'Escola de Teste Fictícia',
            'rede_codigo' => 'municipal',
            'rede_complemento' => null,
            'matricula' => 'MATR-FICT-000',
            'ano_serie' => 'Ano fictício',
            'turma' => 'Turma fictícia',
            'turno_codigo' => 'matutino',
            'turno_complemento' => null,
            'vigente_em' => '2026-09-29',
            'fonte_codigo' => 'documento',
            'fonte_complemento' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    private function child(string $name = 'Pessoa Escolar Fictícia'): Crianca
    {
        return Crianca::query()->create(['nome_completo' => $name]);
    }

    /** @param array<string, mixed> $payload */
    private function record(User $user, Crianca $child, array $payload): void
    {
        $this->actingAsWithVerifiedMfa($user)
            ->post(route('criancas.informacoes-escolares.store', $child), $payload)
            ->assertRedirect(route('criancas.show', $child));
    }
}

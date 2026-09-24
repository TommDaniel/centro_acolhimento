<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\CriancaDocumento;
use App\Models\Evento;
use App\Models\Familiar;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\PiaAnexo;
use App\Models\Report;
use App\Models\Setor;
use App\Models\User;
use App\Models\VisitaTecnica;
use App\Services\AuditRecorder;
use App\Support\InstitutionContext;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AuthorizationHttpWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_active_technical_and_administrator_reach_assistential_controller_reads_and_downloads(): void
    {
        $technical = User::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $records = $this->assistentialRecords($technical);
        $this->withoutVite();

        $pageRoutes = [
            route('criancas.index'), route('criancas.create'), route('criancas.show', $records['child']), route('criancas.edit', $records['child']),
            route('agenda.index'),
            route('pias.index'), route('pias.create'), route('pias.show', $records['pia']), route('pias.edit', $records['pia']),
            route('visitas-tecnicas.index'), route('visitas-tecnicas.create'), route('visitas-tecnicas.show', $records['visit']), route('visitas-tecnicas.edit', $records['visit']),
            route('reports.index'), route('reports.create'), route('reports.show', $records['report']), route('reports.edit', $records['report']),
            route('pertences.index'), route('pertences.create'), route('pertences.show', $records['belonging']), route('pertences.edit', $records['belonging']),
            route('setores.index'), route('setores.show', $records['sector']),
        ];

        foreach ([$technical, $administrator] as $activeUser) {
            foreach ($pageRoutes as $url) {
                $this->actingAs($activeUser)->get($url)->assertOk();
            }

            foreach ([
                route('pias.pdf', $records['pia']),
                route('visitas-tecnicas.pdf', $records['visit']),
                route('reports.pdf', $records['report']),
                route('pertences.pdf', $records['belonging']),
            ] as $url) {
                $this->actingAs($activeUser)
                    ->get($url)
                    ->assertOk()
                    ->assertHeader('content-type', 'application/pdf');
            }
        }
    }

    public function test_current_assistential_controller_mutations_are_wired_to_backend_authorization(): void
    {
        $technical = User::factory()->create();
        $records = $this->assistentialRecords($technical);

        $this->actingAs($technical)->put(route('pias.update', $records['pia']), [
            'crianca_id' => $records['child']->id,
            'consideracoes_tecnicas' => 'Atualização de PIA inteiramente fictícia.',
        ])->assertRedirect(route('pias.show', $records['pia']));

        $this->actingAs($technical)->put(route('visitas-tecnicas.update', $records['visit']), [
            'crianca_id' => $records['child']->id,
            'data_visita' => '2026-09-24',
            'relato' => 'Atualização de visita inteiramente fictícia.',
        ])->assertRedirect(route('visitas-tecnicas.show', $records['visit']));

        $this->actingAs($technical)->put(route('reports.update', $records['report']), [
            'crianca_id' => $records['child']->id,
            'introducao' => 'Introdução atualizada fictícia.',
            'desenvolvimento' => 'Desenvolvimento atualizado fictício.',
        ])->assertRedirect(route('reports.show', $records['report']));

        $this->actingAs($technical)->put(route('pertences.update', $records['belonging']), [
            'data_entrega' => '2026-09-24',
        ])->assertRedirect(route('pertences.show', $records['belonging']));

        $this->actingAs($technical)->put(route('agenda.update', $records['event']), [
            'titulo' => 'Agenda atualizada fictícia',
            'tipo' => 'tarefa',
            'inicio' => '2026-09-24T09:00:00',
            'dia_inteiro' => false,
        ])->assertRedirect(route('agenda.index'));
    }

    public function test_active_technical_and_administrator_can_reach_current_assistential_stores_and_custom_actions(): void
    {
        $gate = Mockery::spy(app(GateContract::class));
        $this->app->instance(GateContract::class, $gate);
        Storage::fake('public');
        $sector = Setor::query()->create(['nome' => 'Setor de Escritas HTTP Fictício']);
        $technical = User::factory()->create(['setor_id' => $sector->id]);
        $administrator = User::factory()->administrator()->create(['setor_id' => $sector->id]);

        foreach ([$technical, $administrator] as $index => $activeUser) {
            $suffix = (string) ($index + 1);
            $childName = 'Acolhido Store HTTP Fictício '.$suffix;

            $this->actingAs($activeUser)
                ->post(route('criancas.store'), ['nome_completo' => $childName])
                ->assertSessionHasNoErrors();

            $child = Crianca::query()->where('nome_completo', $childName)->sole();

            $this->actingAs($activeUser)
                ->post(route('criancas.documentos.store', $child), [
                    'anexos' => [UploadedFile::fake()->create('documento-ficticio-'.$suffix.'.pdf', 10, 'application/pdf')],
                ])
                ->assertSessionHasNoErrors();

            $this->actingAs($activeUser)
                ->post(route('criancas.familiares.store', $child), [
                    'tipo' => 'familiar',
                    'nome' => 'Familiar Store HTTP Fictício '.$suffix,
                ])
                ->assertSessionHasNoErrors();

            $this->actingAs($activeUser)
                ->post(route('pias.store'), ['crianca_id' => $child->id])
                ->assertSessionHasNoErrors();

            $this->actingAs($activeUser)
                ->post(route('visitas-tecnicas.store'), [
                    'crianca_id' => $child->id,
                    'data_visita' => '2026-09-24',
                    'relato' => 'Visita store inteiramente fictícia.',
                ])
                ->assertSessionHasNoErrors();

            $this->actingAs($activeUser)
                ->post(route('reports.store'), [
                    'crianca_id' => $child->id,
                    'introducao' => 'Introdução store inteiramente fictícia.',
                    'desenvolvimento' => 'Desenvolvimento store inteiramente fictício.',
                ])
                ->assertSessionHasNoErrors();

            $this->actingAs($activeUser)
                ->post(route('pertences.store'), [
                    'crianca_id' => $child->id,
                    'itens' => [['descricao' => 'Item store fictício', 'quantidade' => '1']],
                    'data_entrega' => '2026-09-24',
                ])
                ->assertSessionHasNoErrors();

            $eventTitle = 'Agenda store HTTP fictícia '.$suffix;
            $this->actingAs($activeUser)
                ->post(route('agenda.store'), [
                    'titulo' => $eventTitle,
                    'tipo' => 'tarefa',
                    'inicio' => '2026-09-24T09:00:00',
                    'dia_inteiro' => false,
                ])
                ->assertRedirect(route('agenda.index'));

            $event = Evento::query()->where('titulo', $eventTitle)->sole();
            $this->actingAs($activeUser)
                ->patch(route('agenda.concluido', $event))
                ->assertRedirect(route('agenda.index'));
            $this->assertTrue($event->fresh()->concluido);

            $this->actingAs($activeUser)
                ->get(route('busca', ['q' => $childName]))
                ->assertOk();
        }

        $this->assertSame(2, CriancaDocumento::query()->count());
        $this->assertSame(2, Familiar::query()->count());
        $this->assertSame(2, Pia::query()->count());
        $this->assertSame(2, VisitaTecnica::query()->count());
        $this->assertSame(2, Report::query()->count());
        $this->assertSame(2, Pertence::query()->count());

        foreach ([
            ['create', Crianca::class],
            ['create', CriancaDocumento::class],
            ['create', Familiar::class],
            ['create', Pia::class],
            ['create', VisitaTecnica::class],
            ['create', Report::class],
            ['create', Pertence::class],
            ['create', Evento::class],
            ['viewAny', Crianca::class],
        ] as [$ability, $resource]) {
            $gate->shouldHaveReceived('authorize')->with($ability, $resource)->atLeast()->once();
        }

        $gate->shouldHaveReceived('authorize')
            ->with('update', Mockery::type(Evento::class))
            ->atLeast()->once();
    }

    public function test_technical_user_cannot_reach_current_account_or_sector_writes(): void
    {
        $technical = User::factory()->create();
        $sector = Setor::query()->create(['nome' => 'Setor Protegido HTTP Fictício']);

        $this->actingAs($technical)
            ->post(route('equipe.store'), [
                'name' => 'Conta Indevida Fictícia',
                'email' => 'conta-indevida-ficticia@poc.local',
                'password' => 'senha-ficticia-segura',
                'password_confirmation' => 'senha-ficticia-segura',
                'role' => 'equipe_tecnica',
                'status' => 'ativa',
            ])
            ->assertForbidden();

        $this->actingAs($technical)
            ->post(route('setores.store'), ['nome' => 'Setor Indevido Fictício'])
            ->assertForbidden();

        $this->actingAs($technical)
            ->put(route('setores.update', $sector), ['nome' => 'Setor Alterado Indevidamente'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'conta-indevida-ficticia@poc.local']);
        $this->assertDatabaseMissing('setores', ['nome' => 'Setor Indevido Fictício']);
        $this->assertSame('Setor Protegido HTTP Fictício', $sector->fresh()->nome);
    }

    public function test_every_current_physical_delete_route_is_denied_for_active_roles(): void
    {
        $technical = User::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $records = $this->assistentialRecords($technical);

        $deleteRoutes = [
            route('criancas.destroy', $records['child']),
            route('agenda.destroy', $records['event']),
            route('documentos.destroy', $records['childDocument']),
            route('familiares.destroy', $records['familyMember']),
            route('pias.destroy', $records['pia']),
            route('pias.anexos.destroy', $records['piaAttachment']),
            route('visitas-tecnicas.destroy', $records['visit']),
            route('reports.destroy', $records['report']),
            route('pertences.destroy', $records['belonging']),
            route('setores.destroy', $records['sector']),
            route('equipe.destroy', $technical),
        ];

        foreach ([$technical, $administrator] as $activeUser) {
            foreach ($deleteRoutes as $url) {
                $this->actingAs($activeUser)->delete($url)->assertForbidden();
            }
        }
    }

    public function test_management_and_audit_controllers_allow_administrator_and_deny_technical_visitor_and_inactive(): void
    {
        $administrator = User::factory()->administrator()->create();
        $technical = User::factory()->create();
        $inactive = User::factory()->inactive()->create();
        $this->withoutVite();

        foreach ([route('equipe.index'), route('equipe.create'), route('equipe.edit', $technical), route('auditoria.index')] as $url) {
            $this->actingAs($administrator)->get($url)->assertOk();
            $this->actingAs($technical)->get($url)->assertForbidden();
            $this->actingAs($inactive)->get($url)->assertForbidden();
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_forbidden_page_is_safe_localized_and_has_server_correlation_identifier(): void
    {
        $technical = User::factory()->create();
        $this->withoutVite();

        $response = $this->actingAs($technical)->get(route('auditoria.index'));

        $response->assertForbidden()
            ->assertHeader('X-Correlation-ID')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/Forbidden')
                ->missing('target')
                ->missing('url')
                ->missing('query'));

        $event = AuditEvent::query()->where('result', 'denied')->sole();
        $this->assertSame('access.denied.audit.list', $event->action);
        $this->assertStringNotContainsString('/auditoria', $event->toJson());
    }

    public function test_audit_index_is_scoped_to_trusted_unit_and_uses_scoped_filters(): void
    {
        $administrator = User::factory()->administrator()->create();
        app(InstitutionContext::class)->unit();
        app(AuditRecorder::class)->record('scoped.synthetic', 'success', $administrator, $administrator);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'audit_events')) {
                $queries[] = $query->sql;
            }
        });

        $this->withoutVite();
        $this->actingAs($administrator)
            ->get(route('auditoria.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auditoria/Index')
                ->has('events.data', 1)
                ->where('events.data.0.action', 'scoped.synthetic')
                ->where('actions', ['scoped.synthetic']));

        $this->assertTrue(collect($queries)->contains(
            fn (string $sql): bool => str_contains(strtolower($sql), '"unidade_id" ='),
        ));
    }

    public function test_sector_page_exposes_only_the_deliberate_minimal_user_projection(): void
    {
        $administrator = User::factory()->administrator()->create();
        $sector = Setor::query()->create(['nome' => 'Setor de Projeção Fictício']);
        $technical = User::factory()->create([
            'setor_id' => $sector->id,
            'cargo' => 'Técnica Fictícia',
            'telefone' => '000000000',
        ]);
        $child = Crianca::query()->create([
            'nome_completo' => 'Acolhido de Projeção Fictício',
            'cpf' => '00000000000',
            'cartao_sus' => '000000000000000',
            'observacoes' => 'Observação sintética que não pode aparecer no subtópico.',
        ]);
        $pia = new Pia([
            'crianca_id' => $child->id,
            'setor_id' => $sector->id,
        ]);
        $pia->created_by = $technical->id;
        $pia->save();
        $report = new Report([
            'crianca_id' => $child->id,
            'setor_id' => $sector->id,
            'introducao' => 'Narrativa sintética que não pode aparecer no subtópico.',
            'desenvolvimento' => 'Desenvolvimento sintético que não pode aparecer no subtópico.',
        ]);
        $report->created_by = $technical->id;
        $report->save();
        $visit = new VisitaTecnica([
            'crianca_id' => $child->id,
            'setor_id' => $sector->id,
            'data_visita' => '2026-09-24',
            'relato' => 'Relato sintético que não pode aparecer no subtópico.',
        ]);
        $visit->created_by = $technical->id;
        $visit->save();
        $belonging = new Pertence([
            'crianca_id' => $child->id,
            'setor_id' => $sector->id,
            'itens' => [['descricao' => 'Item sintético', 'quantidade' => '1']],
            'data_entrega' => '2026-09-24',
            'assinatura_entrega' => 'Assinatura sintética que não pode aparecer no subtópico.',
        ]);
        $belonging->created_by = $technical->id;
        $belonging->save();
        $this->withoutVite();

        $this->actingAs($administrator)
            ->get(route('setores.show', $sector))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setores/Show')
                ->has('setor.users', 1)
                ->has('setor.users.0', fn (Assert $user) => $user
                    ->hasAll(['id', 'name', 'cargo', 'setor_id'])
                    ->missingAll(['email', 'telefone', 'status', 'role', 'remember_token', 'password']))
                ->has('subtopicos.pias', 1)
                ->has('subtopicos.pias.0', fn (Assert $document) => $document
                    ->hasAll(['id', 'crianca_id', 'created_by', 'setor_id', 'created_at', 'crianca', 'criador'])
                    ->missingAll(['saude', 'consideracoes_tecnicas', 'plano_acao', 'providencias_judiciario']))
                ->has('subtopicos.pias.0.crianca', fn (Assert $relatedChild) => $relatedChild
                    ->hasAll(['id', 'nome_completo'])
                    ->missingAll(['cpf', 'cartao_sus', 'observacoes', 'foto', 'processo_numero']))
                ->has('subtopicos.pias.0.criador', fn (Assert $creator) => $creator
                    ->hasAll(['id', 'name'])
                    ->missingAll(['email', 'telefone', 'status', 'role', 'remember_token', 'password']))
                ->has('subtopicos.reports.0', fn (Assert $document) => $document
                    ->hasAll(['id', 'crianca_id', 'created_by', 'setor_id', 'created_at', 'crianca', 'criador'])
                    ->missingAll(['introducao', 'desenvolvimento', 'consideracoes']))
                ->has('subtopicos.visitas.0', fn (Assert $document) => $document
                    ->hasAll(['id', 'crianca_id', 'created_by', 'setor_id', 'data_visita', 'crianca', 'criador'])
                    ->missingAll(['motivo', 'relato', 'encaminhamentos', 'visitante']))
                ->has('subtopicos.pertences.0', fn (Assert $document) => $document
                    ->hasAll(['id', 'crianca_id', 'created_by', 'setor_id', 'created_at', 'crianca', 'criador'])
                    ->missingAll(['itens', 'assinatura_entrega', 'assinatura_devolucao', 'observacao_devolucao'])));
    }

    /**
     * @return array{child: Crianca, sector: Setor, pia: Pia, visit: VisitaTecnica, report: Report, belonging: Pertence, event: Evento, familyMember: Familiar, childDocument: CriancaDocumento, piaAttachment: PiaAnexo}
     */
    private function assistentialRecords(User $creator): array
    {
        $sector = Setor::query()->create(['nome' => 'Setor HTTP Fictício']);
        $creator->update(['setor_id' => $sector->id]);
        $child = Crianca::query()->create([
            'nome_completo' => 'Acolhido HTTP Fictício',
            'status' => 'acolhida',
            'data_nascimento' => '2012-01-01',
        ]);

        $pia = new Pia(['crianca_id' => $child->id, 'setor_id' => $sector->id]);
        $pia->created_by = $creator->id;
        $pia->save();

        $visit = new VisitaTecnica([
            'crianca_id' => $child->id,
            'setor_id' => $sector->id,
            'data_visita' => '2026-09-24',
            'relato' => 'Visita inteiramente fictícia.',
        ]);
        $visit->created_by = $creator->id;
        $visit->save();

        $report = new Report([
            'crianca_id' => $child->id,
            'setor_id' => $sector->id,
            'introducao' => 'Introdução fictícia.',
            'desenvolvimento' => 'Desenvolvimento fictício.',
        ]);
        $report->created_by = $creator->id;
        $report->save();

        $belonging = new Pertence([
            'crianca_id' => $child->id,
            'setor_id' => $sector->id,
            'itens' => [['descricao' => 'Item fictício', 'quantidade' => '1']],
            'data_entrega' => '2026-09-24',
        ]);
        $belonging->created_by = $creator->id;
        $belonging->save();

        $event = new Evento([
            'titulo' => 'Evento HTTP Fictício',
            'tipo' => 'tarefa',
            'inicio' => now(),
            'dia_inteiro' => false,
            'setor_id' => $sector->id,
        ]);
        $event->created_by = $creator->id;
        $event->save();

        $familyMember = $child->familiares()->create([
            'tipo' => 'familiar',
            'nome' => 'Familiar HTTP Fictício',
        ]);
        $childDocument = $child->documentos()->create([
            'nome_original' => 'documento-ficticio.pdf',
            'path' => 'synthetic/documento-ficticio.pdf',
            'mime' => 'application/pdf',
            'tamanho' => 100,
            'uploaded_by' => $creator->id,
        ]);
        $piaAttachment = $pia->anexos()->create([
            'nome_original' => 'anexo-ficticio.pdf',
            'path' => 'synthetic/anexo-ficticio.pdf',
            'mime' => 'application/pdf',
            'tamanho' => 100,
            'uploaded_by' => $creator->id,
        ]);

        return compact(
            'child', 'sector', 'pia', 'visit', 'report', 'belonging', 'event',
            'familyMember', 'childDocument', 'piaAttachment',
        );
    }
}

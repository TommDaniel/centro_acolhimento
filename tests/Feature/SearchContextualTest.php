<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\CriancaInformacaoEscolar;
use App\Models\Organizacao;
use App\Models\Pertence;
use App\Models\Report;
use App\Models\User;
use App\Models\VisitaTecnica;
use App\Support\InstitutionContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class SearchContextualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_search_finds_only_current_school_information_and_safe_document_metadata(): void
    {
        $technical = User::factory()->create(['name' => 'Técnica de Busca Contextual Fictícia']);
        $child = Crianca::query()->create([
            'nome_completo' => 'Pessoa de Busca Contextual Fictícia',
            'processo_numero' => 'PROC-CONTEXTUAL-FICTICIO-2026',
        ]);
        $child->updated_by = $technical->id;
        $child->save();
        $identificationUpdatedAt = $child->updated_at?->toIso8601String();
        $historicalSchool = CriancaInformacaoEscolar::factory()->create([
            'crianca_id' => $child->id,
            'escola_nome' => 'Escola Histórica Exclusiva Fictícia',
            'created_by' => $technical->id,
            'recorded_at' => '2026-08-01 12:00:00+00',
        ]);
        CriancaInformacaoEscolar::factory()->create([
            'crianca_id' => $child->id,
            'versao_anterior_id' => $historicalSchool->id,
            'escola_nome' => 'Colégio Horizonte Atual Fictício',
            'situacao_codigo' => 'matriculada',
            'created_by' => $technical->id,
            'recorded_at' => '2026-09-29 15:00:00+00',
        ]);

        $pia = $child->pias()->make([
            'numero_oficio' => '701/2026',
            'saude' => 'NARRATIVA-PIA-FICTICIA-NAO-RETORNAR',
        ]);
        $pia->created_by = $technical->id;
        $pia->save();
        $visit = $child->visitasTecnicas()->make([
            'numero_oficio' => '702/2026',
            'data_visita' => '2026-09-20',
            'relato' => 'NARRATIVA-VISITA-FICTICIA-NAO-RETORNAR',
        ]);
        $visit->created_by = $technical->id;
        $visit->save();
        $report = $child->reports()->make([
            'numero_oficio' => '703/2026',
            'titulo' => 'Parecer de acompanhamento fictício',
            'introducao' => 'NARRATIVA-PARECER-INTRODUCAO-NAO-RETORNAR',
            'desenvolvimento' => 'NARRATIVA-PARECER-DESENVOLVIMENTO-NAO-RETORNAR',
        ]);
        $report->created_by = $technical->id;
        $report->save();
        $belonging = $child->pertences()->make([
            'numero_oficio' => '704/2026',
            'itens' => [['descricao' => 'NARRATIVA-PERTENCE-NAO-RETORNAR', 'quantidade' => '1']],
            'data_entrega' => '2026-09-20',
        ]);
        $belonging->created_by = $technical->id;
        $belonging->save();
        $child->documentos()->create([
            'nome_original' => 'arquivo-contextual-ficticio.pdf',
            'path' => 'private/synthetic/CHAVE-STORAGE-CONTEXTUAL-FICTICIA-NAO-BUSCAR.pdf',
            'mime' => 'application/pdf',
            'tamanho' => 128,
        ]);

        $schoolResult = $this->search($technical, 'COLÉGIO HORIZONTE ATUAL');
        $this->assertSame(1, $schoolResult['props']['criancas']['total']);
        $this->assertSame($child->id, $schoolResult['props']['criancas']['data'][0]['id']);
        $this->assertSame([
            'tipo' => 'escola_atual',
            'rotulo' => 'Informação escolar atual',
            'detalhe' => 'Colégio Horizonte Atual Fictício · Matriculada',
            'atualizado_em' => '2026-09-29T15:00:00+00:00',
            'atualizado_por' => $technical->name,
        ], $schoolResult['props']['criancas']['data'][0]['fontes_busca'][0]);

        $statusResult = $this->search($technical, 'MATRICULADA');
        $this->assertSame($child->id, $statusResult['props']['criancas']['data'][0]['id']);
        $this->assertSame('escola_atual', $statusResult['props']['criancas']['data'][0]['fontes_busca'][0]['tipo']);

        foreach ([
            'PIA' => ['pia', 'PIA', 'Ofício 701/2026', $pia->created_at?->toIso8601String()],
            'Visita' => ['visita_tecnica', 'Visita técnica', 'Ofício 702/2026', $visit->created_at?->toIso8601String()],
            'Parecer de acompanhamento' => ['parecer', 'Parecer', 'Parecer de acompanhamento fictício · Ofício 703/2026', $report->created_at?->toIso8601String()],
            '704/2026' => ['pertences', 'Pertences', 'Ofício 704/2026', $belonging->created_at?->toIso8601String()],
        ] as $term => [$expectedType, $expectedLabel, $expectedDetail, $expectedCreatedAt]) {
            $result = $this->search($technical, $term);
            $source = $result['props']['criancas']['data'][0]['fontes_busca'][0];

            $this->assertSame(1, $result['props']['criancas']['total'], $term);
            $this->assertSame($child->id, $result['props']['criancas']['data'][0]['id'], $term);
            $this->assertSame($expectedType, $source['tipo'], $term);
            $this->assertSame($expectedLabel, $source['rotulo'], $term);
            $this->assertSame($expectedDetail, $source['detalhe'], $term);
            $this->assertSame($expectedCreatedAt, $source['criado_em'], $term);
            $this->assertSame($technical->name, $source['criado_por'], $term);

            $serialized = json_encode($result['props'], JSON_THROW_ON_ERROR);
            foreach ([
                'NARRATIVA-PIA-FICTICIA-NAO-RETORNAR',
                'NARRATIVA-VISITA-FICTICIA-NAO-RETORNAR',
                'NARRATIVA-PARECER-INTRODUCAO-NAO-RETORNAR',
                'NARRATIVA-PARECER-DESENVOLVIMENTO-NAO-RETORNAR',
                'NARRATIVA-PERTENCE-NAO-RETORNAR',
            ] as $forbiddenValue) {
                $this->assertStringNotContainsString($forbiddenValue, $serialized, $term);
            }

            $this->assertSame([
                'tipo', 'rotulo', 'detalhe', 'criado_em', 'criado_por',
            ], array_keys($source), $term);
        }

        foreach ([
            'NARRATIVA-PIA-FICTICIA-NAO-RETORNAR',
            'NARRATIVA-VISITA-FICTICIA-NAO-RETORNAR',
            'NARRATIVA-PARECER-INTRODUCAO-NAO-RETORNAR',
            'NARRATIVA-PARECER-DESENVOLVIMENTO-NAO-RETORNAR',
            'NARRATIVA-PERTENCE-NAO-RETORNAR',
            'CHAVE-STORAGE-CONTEXTUAL-FICTICIA-NAO-BUSCAR',
        ] as $forbiddenSearchMarker) {
            $forbiddenResult = $this->search($technical, $forbiddenSearchMarker);
            $this->assertSame(0, $forbiddenResult['props']['criancas']['total'], $forbiddenSearchMarker);
            $this->assertSame([], $forbiddenResult['props']['criancas']['data'], $forbiddenSearchMarker);
        }

        $identificationResult = $this->search($technical, 'PROC-CONTEXTUAL-FICTICIO-2026');
        $this->assertSame([
            'tipo' => 'identificacao',
            'rotulo' => 'Identificação',
            'detalhe' => null,
            'atualizado_em' => $identificationUpdatedAt,
            'atualizado_por' => $technical->name,
        ], $identificationResult['props']['criancas']['data'][0]['fontes_busca'][0]);

        $historicalResult = $this->search($technical, 'Escola Histórica Exclusiva');
        $this->assertSame(0, $historicalResult['props']['criancas']['total']);
        $this->assertSame([], $historicalResult['props']['criancas']['data']);
    }

    public function test_document_source_keeps_creation_provenance_after_another_user_edits_it(): void
    {
        $creator = User::factory()->create(['name' => 'Técnica Criadora Fictícia']);
        $editor = User::factory()->create(['name' => 'Técnica Editora Fictícia']);
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa com Parecer Editado Fictício']);

        $this->travelTo(CarbonImmutable::parse('2026-09-28 12:00:00+00:00'));
        $this->actingAsWithVerifiedMfa($creator)
            ->post(route('reports.store'), [
                'crianca_id' => $child->id,
                'numero_oficio' => '811/2026',
                'titulo' => 'Parecer antes da edição fictício',
                'introducao' => 'Texto inicial estritamente fictício.',
                'desenvolvimento' => 'Desenvolvimento inicial estritamente fictício.',
            ])
            ->assertSessionHasNoErrors();
        $report = Report::query()->where('numero_oficio', '811/2026')->sole();
        $this->assertSame($creator->id, $report->created_by);
        $createdAt = $report->created_at?->toIso8601String();

        $this->travelTo(CarbonImmutable::parse('2026-09-29 18:00:00+00:00'));
        $this->actingAsWithVerifiedMfa($editor)
            ->put(route('reports.update', $report), [
                'crianca_id' => $child->id,
                'titulo' => 'Parecer depois da edição fictício',
                'introducao' => 'Texto editado estritamente fictício.',
                'desenvolvimento' => 'Desenvolvimento editado estritamente fictício.',
            ])
            ->assertRedirect(route('reports.show', $report));

        $result = $this->search($editor, 'Parecer depois da edição');
        $source = $result['props']['criancas']['data'][0]['fontes_busca'][0];

        $this->assertSame($createdAt, $source['criado_em']);
        $this->assertSame($creator->name, $source['criado_por']);
        $this->assertNotSame($editor->name, $source['criado_por']);
        $this->assertArrayNotHasKey('atualizado_em', $source);
        $this->assertArrayNotHasKey('atualizado_por', $source);

        $this->travelBack();
    }

    public function test_visit_and_belonging_stores_assign_creation_actor_exposed_by_search(): void
    {
        $creator = User::factory()->create(['name' => 'Técnica de Documentos Fictícia']);
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa com Documentos Criados Fictícia']);

        $this->actingAsWithVerifiedMfa($creator)
            ->post(route('visitas-tecnicas.store'), [
                'crianca_id' => $child->id,
                'numero_oficio' => '812/2026',
                'data_visita' => '2026-09-29',
                'relato' => 'Relato estritamente fictício e fora da busca.',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAsWithVerifiedMfa($creator)
            ->post(route('pertences.store'), [
                'crianca_id' => $child->id,
                'numero_oficio' => '813/2026',
                'itens' => [['descricao' => 'Item inteiramente fictício', 'quantidade' => '1']],
                'data_entrega' => '2026-09-29',
            ])
            ->assertSessionHasNoErrors();

        $visit = VisitaTecnica::query()->where('numero_oficio', '812/2026')->sole();
        $belonging = Pertence::query()->where('numero_oficio', '813/2026')->sole();
        $this->assertSame($creator->id, $visit->created_by);
        $this->assertSame($creator->id, $belonging->created_by);

        foreach ([
            '812/2026' => ['visita_tecnica', $visit->created_at?->toIso8601String()],
            '813/2026' => ['pertences', $belonging->created_at?->toIso8601String()],
        ] as $term => [$expectedType, $expectedCreatedAt]) {
            $source = $this->search($creator, $term)['props']['criancas']['data'][0]['fontes_busca'][0];

            $this->assertSame($expectedType, $source['tipo']);
            $this->assertSame($expectedCreatedAt, $source['criado_em']);
            $this->assertSame($creator->name, $source['criado_por']);
        }
    }

    public function test_search_is_scoped_by_organization_and_child_destination_is_not_cacheable(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Pessoa de Outra Organização Fictícia']);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.show', $child))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $realContext = app(InstitutionContext::class);
        $foreignOrganization = new Organizacao;
        $foreignOrganization->setAttribute('id', $realContext->organization()->id + 1000);
        $foreignOrganization->exists = true;
        $context = Mockery::mock(InstitutionContext::class);
        $context->shouldReceive('organization')->zeroOrMoreTimes()->andReturn($foreignOrganization);
        $context->shouldReceive('unit')->zeroOrMoreTimes()->andReturn($realContext->unit());
        $this->app->instance(InstitutionContext::class, $context);

        $result = $this->search($technical, 'Pessoa de Outra Organização Fictícia');

        $this->assertSame(0, $result['props']['criancas']['total']);
        $this->assertSame([], $result['props']['criancas']['data']);
    }

    public function test_literal_like_wildcards_do_not_expand_the_search(): void
    {
        $technical = User::factory()->create();
        $percent = Crianca::query()->create(['nome_completo' => 'Pessoa 100% Literal Fictícia']);
        Crianca::query()->create(['nome_completo' => 'Pessoa 1000 Literal Fictícia']);
        $underscore = Crianca::query()->create(['nome_completo' => 'Pessoa Código_A Literal Fictícia']);
        Crianca::query()->create(['nome_completo' => 'Pessoa CódigoXA Literal Fictícia']);

        $percentResult = $this->search($technical, '%');
        $underscoreResult = $this->search($technical, '_');

        $this->assertSame([$percent->id], collect($percentResult['props']['criancas']['data'])->pluck('id')->all());
        $this->assertSame([$underscore->id], collect($underscoreResult['props']['criancas']['data'])->pluck('id')->all());
    }

    public function test_contextual_school_search_stays_paginated_filterable_and_does_not_grow_queries_per_child(): void
    {
        $technical = User::factory()->create();
        $uniqueTerm = 'Escola Única da Paginação Fictícia';

        for ($index = 1; $index <= 16; $index++) {
            $child = Crianca::query()->create([
                'nome_completo' => sprintf('Pessoa Escolar Paginada Fictícia %02d', $index),
            ]);
            CriancaInformacaoEscolar::factory()->create([
                'crianca_id' => $child->id,
                'escola_nome' => $index === 1
                    ? $uniqueTerm
                    : sprintf('Escola Compartilhada da Paginação Fictícia %02d', $index),
                'created_by' => $technical->id,
            ]);
        }

        $queryCount = 0;
        DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
            $queryCount++;
        });

        $uniqueUrl = $this->submit($technical, $uniqueTerm);
        $queryCount = 0;
        $this->actingAsWithVerifiedMfa($technical)->get($uniqueUrl)->assertOk();
        $singleResultQueries = $queryCount;

        $sharedUrl = $this->submit($technical, 'paginação fictícia');
        $queryCount = 0;
        $response = $this->actingAsWithVerifiedMfa($technical)->get($sharedUrl)->assertOk();
        $paginatedResultQueries = $queryCount;
        $page = $this->page($response);

        $this->assertSame(16, $page['props']['criancas']['total']);
        $this->assertSame(15, $page['props']['criancas']['per_page']);
        $this->assertCount(15, $page['props']['criancas']['data']);
        foreach ($page['props']['criancas']['data'] as $result) {
            $this->assertSame('escola_atual', $result['fontes_busca'][0]['tipo']);
        }
        $this->assertLessThanOrEqual($singleResultQueries + 1, $paginatedResultQueries);

        $filtered = $this->actingAsWithVerifiedMfa($technical)
            ->get($sharedUrl.'?situacao=sem_ingresso')
            ->assertOk();
        $this->assertSame(16, $this->page($filtered)['props']['criancas']['total']);

        $secondPage = $this->actingAsWithVerifiedMfa($technical)
            ->get($sharedUrl.'?situacao=sem_ingresso&page=2')
            ->assertOk();
        $this->assertCount(1, $this->page($secondPage)['props']['criancas']['data']);
    }

    public function test_denied_search_does_not_audit_or_echo_the_term(): void
    {
        $inactive = User::factory()->inactive()->create();
        $term = 'TERMO-NEGADO-CONTEXTUAL-FICTICIO';

        $response = $this->actingAsWithVerifiedMfa($inactive)
            ->post(route('busca.search'), ['q' => $term]);

        $response->assertForbidden();
        $this->assertStringNotContainsString($term, $response->getContent());
        $this->assertStringNotContainsString($term, AuditEvent::query()->get()->toJson());
        $this->assertSame(0, AuditEvent::query()->where('action', 'search.executed')->count());
    }

    /** @return array<string, mixed> */
    private function search(User $user, string $term, array $payload = []): array
    {
        $url = $this->submit($user, $term, $payload);
        $response = $this->actingAsWithVerifiedMfa($user)->get($url);

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        return $this->page($response);
    }

    /** @param array<string, mixed> $payload */
    private function submit(User $user, string $term, array $payload = []): string
    {
        $response = $this->actingAsWithVerifiedMfa($user)
            ->post(route('busca.search'), ['q' => $term, ...$payload]);

        $response->assertStatus(303);
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString($term, $location);
        $this->assertMatchesRegularExpression('#/busca/[a-zA-Z0-9]{64}(?:\?.*)?$#', $location);

        return $location;
    }

    /** @return array<string, mixed> */
    private function page(TestResponse $response): array
    {
        return json_decode(
            json_encode($response->viewData('page'), JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}

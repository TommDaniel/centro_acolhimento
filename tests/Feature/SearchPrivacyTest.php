<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\Report;
use App\Models\User;
use App\Models\VisitaTecnica;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Regression coverage for the PROT-01A / BE-01 search payload privacy cut. */
class SearchPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_authorized_search_returns_only_card_fields_and_stays_paginated(): void
    {
        $user = User::factory()->create();
        $child = $this->createSyntheticChildWithSensitiveRelatedRecords();
        $searchTerm = 'Busca de privacidade ficticia';

        for ($index = 1; $index <= 15; $index++) {
            Crianca::query()->create([
                'nome_completo' => sprintf('%s %02d', $searchTerm, $index),
            ]);
        }

        ['response' => $submission, 'searchId' => $searchId, 'url' => $searchUrl] = $this->submitSearch($user, $searchTerm);

        $submission->assertStatus(303)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringNotContainsString($searchTerm, $searchUrl);

        $response = $this->get($searchUrl);

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Busca')
                ->missing('q')
                ->where('criancas.current_page', 1)
                ->where('criancas.per_page', 15)
                ->where('criancas.total', 16)
                ->has('criancas.data', 15)
                ->where('criancas.data.0.id', $child->id)
                ->where('criancas.data.0.nome_completo', $child->nome_completo)
                ->where('criancas.data.0.data_nascimento', '2013-06-07')
                ->where('criancas.data.0.processo_numero', 'PROCESSO-PRIVACIDADE-FICTICIO-2026')
                ->where('criancas.data.0.acolhimento_situacao', null)
                ->where('criancas.data.0.acolhimento_fonte', 'nenhum')
                ->where('criancas.data.0.pias_count', 1)
                ->where('criancas.data.0.reports_count', 1)
                ->where('criancas.data.0.visitas_tecnicas_count', 1)
                ->where('criancas.data.0.pertences_count', 1)
                ->where('criancas.data.0.fontes_busca', [[
                    'tipo' => 'identificacao',
                    'rotulo' => 'Identificação',
                    'detalhe' => null,
                    'atualizado_em' => $child->updated_at?->toIso8601String(),
                    'atualizado_por' => null,
                ]])
                ->missingAll([
                    'criancas.data.0.nome_social',
                    'criancas.data.0.sexo',
                    'criancas.data.0.identidade_genero',
                    'criancas.data.0.cor_raca',
                    'criancas.data.0.naturalidade',
                    'criancas.data.0.nacionalidade',
                    'criancas.data.0.rg',
                    'criancas.data.0.cpf',
                    'criancas.data.0.certidao_nascimento',
                    'criancas.data.0.rn',
                    'criancas.data.0.cartao_sus',
                    'criancas.data.0.nis',
                    'criancas.data.0.titulo_eleitor',
                    'criancas.data.0.nome_mae',
                    'criancas.data.0.nome_pai',
                    'criancas.data.0.responsavel_legal',
                    'criancas.data.0.contato_responsavel',
                    'criancas.data.0.endereco_familia',
                    'criancas.data.0.vara',
                    'criancas.data.0.comarca',
                    'criancas.data.0.data_acolhimento',
                    'criancas.data.0.motivo_acolhimento',
                    'criancas.data.0.status',
                    'criancas.data.0.foto',
                    'criancas.data.0.foto_url',
                    'criancas.data.0.idade',
                    'criancas.data.0.observacoes',
                    'criancas.data.0.familiares',
                    'criancas.data.0.documentos',
                    'criancas.data.0.saude',
                    'criancas.data.0.introducao',
                    'criancas.data.0.relato',
                    'criancas.data.0.created_by',
                    'criancas.data.0.updated_by',
                    'criancas.data.0.created_at',
                    'criancas.data.0.updated_at',
                    'criancas.data.0.organizacao_id',
                    'criancas.data.0.unidade_id',
                ]));

        $page = json_decode(
            json_encode($response->viewData('page'), JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $childProps = $page['props']['criancas']['data'][0];

        $this->assertSame([
            'id',
            'nome_completo',
            'data_nascimento',
            'processo_numero',
            'acolhimento_situacao',
            'acolhimento_fonte',
            'pias_count',
            'reports_count',
            'visitas_tecnicas_count',
            'pertences_count',
            'fontes_busca',
        ], array_keys($childProps));

        $this->assertStringContainsString($searchId, $page['url']);
        $this->assertStringNotContainsString($searchTerm, $page['url']);
        foreach ($page['props']['criancas']['links'] as $link) {
            $this->assertStringNotContainsString($searchTerm, (string) $link['url']);
        }

        $serializedProps = json_encode($page['props'], JSON_THROW_ON_ERROR);
        foreach ([
            'CPF-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'RG-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'CARTAO-SUS-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'FAMILIAR-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'ENDERECO-FAMILIA-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'OBSERVACAO-PRIVACIDADE-FICTICIA-NAO-RETORNAR',
            'RELATO-SAÚDE-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'NARRATIVA-DOCUMENTO-PRIVACIDADE-FICTICIA-NAO-RETORNAR',
            'CAMINHO-DOCUMENTO-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'CAMINHO-FOTO-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
        ] as $sensitiveMarker) {
            $this->assertStringNotContainsString($sensitiveMarker, $serializedProps);
        }

        $secondPage = $this->get(route('busca', ['searchId' => $searchId, 'page' => 2]));

        $secondPage->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Busca')
                ->missing('q')
                ->where('criancas.current_page', 2)
                ->where('criancas.total', 16)
                ->has('criancas.data', 1)
                ->where('criancas.data.0.nome_completo', sprintf('%s 15', $searchTerm)));

        $events = AuditEvent::query()->where('action', 'search.executed')->orderBy('id')->get();
        $this->assertCount(2, $events);

        foreach ($events as $event) {
            $this->assertSame($user->id, $event->actor_id);
            $this->assertSame('success', $event->result);
            $this->assertNull($event->subject_type);
            $this->assertNull($event->subject_id);
            $this->assertSame([], $event->changed_fields);
            $this->assertNotNull($event->occurred_at);
        }

        $this->assertStringNotContainsString($searchTerm, $events->toJson());
    }

    public function test_direct_get_search_term_is_canonicalized_without_running_a_search(): void
    {
        $user = User::factory()->create();
        $searchTerm = 'CPF-FICTICIO-LEGACY-GET-NAO-EXECUTAR';
        Crianca::query()->create(['nome_completo' => 'Pessoa da busca fictícia legado', 'cpf' => $searchTerm]);
        $childQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$childQueries): void {
            if (str_contains(strtolower($query->sql), 'from "criancas"')) {
                $childQueries[] = $query->sql;
            }
        });

        $response = $this->actingAsWithVerifiedMfa($user)
            ->get(route('busca', ['q' => $searchTerm]));

        $response->assertStatus(303)
            ->assertRedirect(route('busca'))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertStringNotContainsString($searchTerm, (string) $response->headers->get('Location'));
        $this->assertSame([], $childQueries);
        $this->assertSame(0, AuditEvent::query()->where('action', 'search.executed')->count());
    }

    public function test_successful_no_result_search_is_audited_without_term_or_subject(): void
    {
        $user = User::factory()->create();
        $searchTerm = 'Nenhum resultado fictício para auditoria';
        ['url' => $searchUrl] = $this->submitSearch($user, $searchTerm);

        $this->get($searchUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Busca')
                ->missing('q')
                ->where('criancas.total', 0)
                ->has('criancas.data', 0));

        $event = AuditEvent::query()->where('action', 'search.executed')->sole();
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame('success', $event->result);
        $this->assertNull($event->subject_type);
        $this->assertNull($event->subject_id);
        $this->assertSame([], $event->changed_fields);
        $this->assertNotNull($event->occurred_at);
        $this->assertStringNotContainsString($searchTerm, $event->toJson());
    }

    public function test_search_handles_are_bounded_and_expire(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now());
        $searchIds = [];

        for ($index = 0; $index < 6; $index++) {
            ['searchId' => $searchId] = $this->submitSearch($user, 'Termo fictício '.$index);
            $searchIds[] = $searchId;
        }

        $this->assertCount(5, Session::get('search.query_handles'));
        $this->get(route('busca', ['searchId' => $searchIds[0]]))
            ->assertRedirect(route('busca'));
        $this->assertSame(0, AuditEvent::query()->where('action', 'search.executed')->count());

        $this->get(route('busca', ['searchId' => $searchIds[5]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('criancas.total', 0));
        $this->assertSame(1, AuditEvent::query()->where('action', 'search.executed')->count());

        $this->travel(9)->minutes();
        $this->travel(59)->seconds();
        $this->actingAsWithVerifiedMfa($user)
            ->get(route('busca', ['searchId' => $searchIds[5]]))
            ->assertOk();
        $this->assertSame(2, AuditEvent::query()->where('action', 'search.executed')->count());

        $this->travel(1)->seconds();
        $this->actingAsWithVerifiedMfa($user)
            ->get(route('busca', ['searchId' => $searchIds[5]]))
            ->assertRedirect(route('busca'));
        $this->assertSame(2, AuditEvent::query()->where('action', 'search.executed')->count());
    }

    public function test_search_submission_rejects_invalid_or_extra_payload_without_side_effects(): void
    {
        $user = User::factory()->create();
        $existingChild = Crianca::query()->create([
            'nome_completo' => 'Acolhido de validação inteiramente fictício',
            'status' => 'acolhida',
        ]);
        $oversizedMarker = str_repeat('MARCADOR-FICTICIO-', 16);

        foreach ([
            [],
            ['q' => ['formato-invalido-ficticio']],
            ['q' => $oversizedMarker],
        ] as $payload) {
            $response = $this->actingAsWithVerifiedMfa($user)
                ->from(route('busca'))
                ->post(route('busca.search'), [
                    ...$payload,
                    'status' => 'desacolhida',
                ]);

            $response->assertRedirect(route('busca'))
                ->assertSessionHasErrors('q');
            $this->assertStringNotContainsString($oversizedMarker, $response->getContent());
            $this->assertStringNotContainsString($oversizedMarker, (string) $response->headers->get('Location'));
        }

        $this->assertFalse(Session::has('search.query_handles'));

        $contextTampering = $this->actingAsWithVerifiedMfa($user)
            ->post(route('busca.search'), [
                'q' => $oversizedMarker,
                'organizacao_id' => PHP_INT_MAX,
                'unidade_id' => PHP_INT_MAX,
            ]);
        $contextTampering->assertUnprocessable()
            ->assertJsonValidationErrors('contexto');
        $this->assertStringNotContainsString($oversizedMarker, $contextTampering->getContent());
        $this->assertFalse(Session::has('search.query_handles'));

        $this->actingAsWithVerifiedMfa($user)
            ->post(route('busca.search'), [
                'q' => 'Acolhido de validação inteiramente fictício',
                'status' => 'desacolhida',
            ])
            ->assertStatus(303);

        $this->assertSame('acolhida', $existingChild->fresh()->status);
        $this->assertSame(0, AuditEvent::query()->where('action', 'search.executed')->count());
        $this->assertStringNotContainsString($oversizedMarker, AuditEvent::query()->get()->toJson());
    }

    public function test_search_handle_is_session_bound_and_invalid_handles_fail_closed(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $searchTerm = 'Termo fictício isolado por sessão';

        ['searchId' => $searchId] = $this->submitSearch($firstUser, $searchTerm);

        foreach (['identificador-inválido', str_repeat('A', 64)] as $invalidSearchId) {
            $this->get(route('busca', ['searchId' => $invalidSearchId]))
                ->assertStatus(303)
                ->assertRedirect(route('busca'))
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('Referrer-Policy', 'no-referrer');
        }

        $this->flushSession();

        $this->actingAsWithVerifiedMfa($secondUser)
            ->get(route('busca', ['searchId' => $searchId]))
            ->assertStatus(303)
            ->assertRedirect(route('busca'))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertSame(0, AuditEvent::query()->where('action', 'search.executed')->count());
        $this->assertStringNotContainsString($searchTerm, AuditEvent::query()->get()->toJson());
    }

    public function test_visitor_is_redirected_from_search(): void
    {
        $searchTerm = 'Termo de busca inteiramente fictício';
        $response = $this->post(route('busca.search'), ['q' => $searchTerm]);

        $response->assertRedirect(route('login'));
        $this->assertStringNotContainsString($searchTerm, (string) $response->headers->get('Location'));
    }

    public function test_inactive_account_is_denied_and_search_term_is_not_added_to_denial_audit(): void
    {
        $inactiveUser = User::factory()->inactive()->create();
        $searchTerm = 'Termo de busca restrito fictício';

        $this->actingAsWithVerifiedMfa($inactiveUser)
            ->post(route('busca.search'), ['q' => $searchTerm])
            ->assertForbidden();

        $denial = AuditEvent::query()->where('result', 'denied')->sole();

        $this->assertStringNotContainsString($searchTerm, $denial->toJson());
    }

    /**
     * @return array{response: TestResponse, searchId: string, url: string}
     */
    private function submitSearch(User $user, string $term): array
    {
        $response = $this->actingAsWithVerifiedMfa($user)
            ->post(route('busca.search'), ['q' => $term]);
        $url = (string) $response->headers->get('Location');
        $path = (string) parse_url($url, PHP_URL_PATH);
        $searchId = basename($path);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame(route('busca', ['searchId' => $searchId]), $url);
        $this->assertMatchesRegularExpression('/\A[a-zA-Z0-9]{64}\z/', $searchId);

        return compact('response', 'searchId', 'url');
    }

    private function createSyntheticChildWithSensitiveRelatedRecords(): Crianca
    {
        $child = Crianca::query()->create([
            'nome_completo' => 'Busca de privacidade ficticia 00',
            'nome_social' => 'NOME-SOCIAL-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'data_nascimento' => '2013-06-07',
            'sexo' => 'SEXO-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'identidade_genero' => 'GENERO-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'cor_raca' => 'RACA-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'naturalidade' => 'NATURALIDADE-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'nacionalidade' => 'NACIONALIDADE-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'rg' => 'RG-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'cpf' => 'CPF-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'certidao_nascimento' => 'CERTIDAO-PRIVACIDADE-FICTICIA-NAO-RETORNAR',
            'rn' => 'RN-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'cartao_sus' => 'CARTAO-SUS-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'nis' => 'NIS-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'titulo_eleitor' => 'TITULO-ELEITOR-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'nome_mae' => 'MAE-PRIVACIDADE-FICTICIA-NAO-RETORNAR',
            'nome_pai' => 'PAI-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'responsavel_legal' => 'RESPONSAVEL-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'contato_responsavel' => 'CONTATO-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'endereco_familia' => 'ENDERECO-FAMILIA-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'processo_numero' => 'PROCESSO-PRIVACIDADE-FICTICIO-2026',
            'vara' => 'VARA-PRIVACIDADE-FICTICIA-NAO-RETORNAR',
            'comarca' => 'COMARCA-PRIVACIDADE-FICTICIA-NAO-RETORNAR',
            'data_acolhimento' => '2025-01-03',
            'motivo_acolhimento' => 'MOTIVO-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'foto' => 'private/synthetic/CAMINHO-FOTO-PRIVACIDADE-FICTICIO-NAO-RETORNAR.jpg',
            'observacoes' => 'OBSERVACAO-PRIVACIDADE-FICTICIA-NAO-RETORNAR',
            'status' => 'acolhida',
        ]);

        $child->familiares()->create([
            'tipo' => 'familiar',
            'nome' => 'FAMILIAR-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
            'endereco' => 'ENDERECO-FAMILIA-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
        ]);

        $child->documentos()->create([
            'nome_original' => 'DOCUMENTO-PRIVACIDADE-FICTICIO-NAO-RETORNAR.pdf',
            'path' => 'private/synthetic/CAMINHO-DOCUMENTO-PRIVACIDADE-FICTICIO-NAO-RETORNAR.pdf',
            'mime' => 'application/pdf',
            'tamanho' => 128,
        ]);

        $pia = new Pia([
            'crianca_id' => $child->id,
            'saude' => 'RELATO-SAÚDE-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
        ]);
        $pia->save();

        $report = new Report([
            'crianca_id' => $child->id,
            'introducao' => 'NARRATIVA-DOCUMENTO-PRIVACIDADE-FICTICIA-NAO-RETORNAR',
            'desenvolvimento' => 'DESENVOLVIMENTO-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
        ]);
        $report->save();

        $visit = new VisitaTecnica([
            'crianca_id' => $child->id,
            'data_visita' => '2026-09-20',
            'relato' => 'RELATO-VISITA-PRIVACIDADE-FICTICIO-NAO-RETORNAR',
        ]);
        $visit->save();

        $belonging = new Pertence([
            'crianca_id' => $child->id,
            'itens' => [['descricao' => 'PERTENCE-PRIVACIDADE-FICTICIO', 'quantidade' => '1']],
            'data_entrega' => '2026-09-20',
        ]);
        $belonging->save();

        return $child;
    }
}

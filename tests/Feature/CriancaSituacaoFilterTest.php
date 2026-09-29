<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Acolhimento;
use App\Models\Crianca;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CriancaSituacaoFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_classifies_each_person_once_and_counts_from_canonical_history(): void
    {
        $technical = $this->technicalUser();
        $inUnit = $this->child('A Na Unidade Fictícia');
        $legacyConflict = $this->child('B Episódio Vence Legado Fictícia');
        DB::table('criancas')->where('id', $legacyConflict->id)->update([
            'data_acolhimento' => '2025-01-02',
            'motivo_acolhimento' => 'Dado anterior inteiramente fictício.',
            'status' => 'desligada',
        ]);
        $evaded = $this->child('C Evadida Fictícia');
        $hospitalized = $this->child('D Internada Fictícia');
        $discharged = $this->child('E Desacolhida Fictícia');
        $legacy = $this->child('F A Conferir Fictícia');
        DB::table('criancas')->where('id', $legacy->id)->update([
            'data_acolhimento' => '2025-02-03',
            'motivo_acolhimento' => 'Informação anterior inteiramente fictícia.',
            'status' => 'acolhida',
        ]);
        $withoutAdmission = $this->child('G Sem Ingresso Fictícia');

        $this->openEpisode($technical, $inUnit, '2026-09-01T09:00');
        $this->openEpisode($technical, $legacyConflict, '2026-09-01T09:00');
        $evadedEpisode = $this->openEpisode($technical, $evaded, '2026-09-01T09:00');
        $this->move($technical, $evaded, $evadedEpisode, 'evasao', '2026-09-01T10:00');
        $hospitalEpisode = $this->openEpisode($technical, $hospitalized, '2026-09-01T09:00');
        $this->move($technical, $hospitalized, $hospitalEpisode, 'internacao', '2026-09-01T10:00');
        $dischargedEpisode = $this->openEpisode($technical, $discharged, '2026-09-01T09:00');
        $this->move($technical, $discharged, $dischargedEpisode, 'desacolhimento', '2026-09-01T10:00');

        $expected = [
            'todos' => [$inUnit->id, $legacyConflict->id, $evaded->id, $hospitalized->id, $discharged->id, $legacy->id, $withoutAdmission->id],
            'acolhidos' => [$inUnit->id, $legacyConflict->id],
            'desacolhidos' => [$discharged->id],
            'evadidos' => [$evaded->id],
            'internados' => [$hospitalized->id],
            'a_conferir' => [$legacy->id],
            'sem_ingresso' => [$withoutAdmission->id],
        ];

        foreach ($expected as $filter => $ids) {
            $response = $this->actingAsWithVerifiedMfa($technical)
                ->get(route('criancas.index', ['situacao' => $filter]));
            $response->assertOk();
            $props = $this->props($response);

            $this->assertSame($filter, $props['situacao']);
            $this->assertEqualsCanonicalizing($ids, array_column($props['criancas']['data'], 'id'));
            $this->assertSame(count($ids), $props['criancas']['total']);

            $counts = collect($props['filtrosSituacao'])->pluck('count', 'value')->all();
            $this->assertSame([
                'todos' => 7,
                'acolhidos' => 2,
                'desacolhidos' => 1,
                'evadidos' => 1,
                'internados' => 1,
                'a_conferir' => 1,
                'sem_ingresso' => 1,
            ], $counts);
            $this->assertSame(7, array_sum(collect($counts)->except('todos')->all()));
        }
    }

    public function test_transitions_and_ties_use_latest_ids_within_the_configured_unit(): void
    {
        $technical = $this->technicalUser();
        $child = $this->child('Pessoa de Transições Fictícia');
        $firstEpisode = $this->openEpisode($technical, $child, '2026-09-01T09:00');

        $this->assertChildInFilter($technical, $child, 'acolhidos');
        $this->move($technical, $child, $firstEpisode, 'evasao', '2026-09-01T10:00');
        $this->assertChildInFilter($technical, $child, 'evadidos');
        $this->move($technical, $child, $firstEpisode, 'retorno', '2026-09-01T10:00');
        $this->assertChildInFilter($technical, $child, 'acolhidos');
        $this->move($technical, $child, $firstEpisode, 'internacao', '2026-09-01T11:00');
        $this->assertChildInFilter($technical, $child, 'internados');
        $this->move($technical, $child, $firstEpisode, 'desacolhimento', '2026-09-01T12:00');
        $this->assertChildInFilter($technical, $child, 'desacolhidos');

        $secondEpisode = $this->openEpisode($technical, $child, '2026-09-01T12:00');
        $this->move($technical, $child, $secondEpisode, 'internacao', '2026-09-01T12:00');
        $this->assertChildInFilter($technical, $child, 'internados');

        $tiedChild = $this->child('Pessoa com Episódios Empatados Fictícia');
        $tiedFirstEpisode = $this->openEpisode($technical, $tiedChild, '2026-09-02T09:00');
        $this->move($technical, $tiedChild, $tiedFirstEpisode, 'desacolhimento', '2026-09-02T09:00');
        $tiedSecondEpisode = $this->openEpisode($technical, $tiedChild, '2026-09-02T09:00');
        $this->move($technical, $tiedChild, $tiedSecondEpisode, 'internacao', '2026-09-02T09:00');

        $this->assertGreaterThan($tiedFirstEpisode->id, $tiedSecondEpisode->id);
        $this->assertSame(
            $tiedFirstEpisode->ingresso_em->toISOString(),
            $tiedSecondEpisode->ingresso_em->toISOString(),
        );
        $internados = $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['situacao' => 'internados']));
        $this->assertContains($tiedChild->id, array_column($this->props($internados)['criancas']['data'], 'id'));

        $desacolhidos = $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['situacao' => 'desacolhidos']));
        $this->assertNotContains($tiedChild->id, array_column($this->props($desacolhidos)['criancas']['data'], 'id'));
    }

    public function test_homonymous_people_keep_stable_id_order_across_index_and_search_pages(): void
    {
        $technical = $this->technicalUser();
        $sharedName = 'Pessoa Homônima Inteiramente Fictícia';
        $ids = [];

        for ($index = 0; $index < 16; $index++) {
            $ids[] = $this->child($sharedName)->id;
        }

        $indexFirstPage = $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['situacao' => 'sem_ingresso']));
        $indexFirstProps = $this->props($indexFirstPage);
        $this->assertSame(array_slice($ids, 0, 12), array_column($indexFirstProps['criancas']['data'], 'id'));

        $indexSecondPage = $this->get(
            collect($indexFirstProps['criancas']['links'])->firstWhere('label', '2')['url'],
        );
        $this->assertSame(
            array_slice($ids, 12),
            array_column($this->props($indexSecondPage)['criancas']['data'], 'id'),
        );

        $submission = $this->post(route('busca.search'), [
            'q' => $sharedName,
            'situacao' => 'sem_ingresso',
        ]);
        $submission->assertStatus(303);

        $searchFirstPage = $this->get((string) $submission->headers->get('Location'));
        $searchFirstProps = $this->props($searchFirstPage);
        $this->assertSame(array_slice($ids, 0, 15), array_column($searchFirstProps['criancas']['data'], 'id'));

        $searchSecondPage = $this->get(
            collect($searchFirstProps['criancas']['links'])->firstWhere('label', '2')['url'],
        );
        $this->assertSame(
            array_slice($ids, 15),
            array_column($this->props($searchSecondPage)['criancas']['data'], 'id'),
        );
    }

    public function test_protected_search_combines_filter_counts_and_pagination_without_term_in_urls(): void
    {
        $technical = $this->technicalUser();
        $term = 'Marcador Busca Situação Fictícia';

        for ($index = 1; $index <= 16; $index++) {
            $this->child(sprintf('%s %02d', $term, $index));
        }

        $legacy = $this->child("{$term} Legado");
        DB::table('criancas')->where('id', $legacy->id)->update([
            'data_acolhimento' => '2025-03-04',
            'motivo_acolhimento' => 'Informação legada de busca inteiramente fictícia.',
        ]);

        $submission = $this->actingAsWithVerifiedMfa($technical)
            ->post(route('busca.search'), [
                'q' => $term,
                'situacao' => 'sem_ingresso',
            ]);
        $submission->assertStatus(303);
        $location = (string) $submission->headers->get('Location');

        $this->assertMatchesRegularExpression('#/busca/[a-zA-Z0-9]{64}\?situacao=sem_ingresso$#', $location);
        $this->assertStringNotContainsString($term, $location);
        $this->assertStringNotContainsString(rawurlencode($term), $location);

        $firstPage = $this->get($location);
        $firstPage->assertOk();
        $props = $this->props($firstPage);
        $this->assertSame('sem_ingresso', $props['situacao']);
        $this->assertSame(16, $props['criancas']['total']);
        $this->assertCount(15, $props['criancas']['data']);
        $counts = collect($props['filtrosSituacao'])->pluck('count', 'value')->all();
        $this->assertSame(17, $counts['todos']);
        $this->assertSame(16, $counts['sem_ingresso']);
        $this->assertSame(1, $counts['a_conferir']);

        $nextUrl = collect($props['criancas']['links'])->firstWhere('label', '2')['url'];
        $this->assertStringContainsString('situacao=sem_ingresso', $nextUrl);
        $this->assertStringContainsString('page=2', $nextUrl);
        $this->assertStringNotContainsString($term, $nextUrl);
        $this->assertStringNotContainsString('q=', $nextUrl);

        $secondPage = $this->get($nextUrl);
        $secondProps = $this->props($secondPage);
        $this->assertSame(2, $secondProps['criancas']['current_page']);
        $this->assertCount(1, $secondProps['criancas']['data']);

        $legacyHref = collect($props['filtrosSituacao'])->firstWhere('value', 'a_conferir')['href'];
        $this->assertStringNotContainsString($term, $legacyHref);
        $legacyResponse = $this->get($legacyHref);
        $this->assertSame([$legacy->id], array_column($this->props($legacyResponse)['criancas']['data'], 'id'));
    }

    public function test_invalid_filters_and_unapproved_accounts_fail_closed_without_creating_searches(): void
    {
        $technical = $this->technicalUser();
        $invalid = 'estado-inventado-com-PII-ficticia';

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['situacao' => $invalid]))
            ->assertStatus(303)
            ->assertRedirect(route('criancas.index'));
        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index').'?situacao[]=acolhidos')
            ->assertStatus(303)
            ->assertRedirect(route('criancas.index'));

        $response = $this->actingAsWithVerifiedMfa($technical)
            ->from(route('busca'))
            ->post(route('busca.search'), [
                'q' => 'Termo de filtro inválido inteiramente fictício',
                'situacao' => $invalid,
            ]);
        $response->assertRedirect(route('busca'))->assertSessionHasErrors('situacao');
        $this->assertFalse(Session::has('search.query_handles'));
        $this->assertStringNotContainsString($invalid, (string) $response->headers->get('Location'));
        $this->assertDatabaseCount('audit_events', 0);

        $validSearch = $this->actingAsWithVerifiedMfa($technical)
            ->post(route('busca.search'), [
                'q' => 'Termo seguro inteiramente fictício',
                'situacao' => 'todos',
            ]);
        $validSearch->assertStatus(303);
        $searchLocation = (string) $validSearch->headers->get('Location');
        $invalidSearchLocation = strtok($searchLocation, '?').'?situacao[]='.rawurlencode($invalid);
        $this->get($invalidSearchLocation)
            ->assertStatus(303)
            ->assertRedirect(strtok($searchLocation, '?'));

        foreach ([
            User::factory()->inactive()->create([
                'name' => 'Conta Inativa Fictícia',
                'email' => 'conta-inativa-filtro@exemplo-ficticio.local',
            ]),
            User::factory()->create([
                'name' => 'Conta MFA Pendente Fictícia',
                'email' => 'conta-mfa-pendente-filtro@exemplo-ficticio.local',
                'status' => UserStatus::PendenteMfa,
            ]),
        ] as $denied) {
            $deniedResponse = $this->actingAsWithVerifiedMfa($denied)
                ->get(route('criancas.index', ['situacao' => 'acolhidos']));
            $this->assertContains($deniedResponse->getStatusCode(), [302, 403]);
        }

        $this->get(route('criancas.index', ['situacao' => 'acolhidos']))
            ->assertRedirect(route('login'));
    }

    public function test_default_legacy_status_alone_does_not_invent_historical_origin(): void
    {
        $technical = $this->technicalUser();
        $child = $this->child('Pessoa sem Marcador Histórico Fictícia');
        DB::table('criancas')->where('id', $child->id)->update([
            'data_acolhimento' => null,
            'motivo_acolhimento' => null,
            'status' => 'acolhida',
        ]);

        $withoutAdmission = $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['situacao' => 'sem_ingresso']));
        $this->assertSame([$child->id], array_column($this->props($withoutAdmission)['criancas']['data'], 'id'));

        $toReview = $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['situacao' => 'a_conferir']));
        $this->assertSame([], $this->props($toReview)['criancas']['data']);
    }

    public function test_list_queries_are_bounded_and_postgresql_supporting_indexes_exist(): void
    {
        $technical = $this->technicalUser();
        $child = $this->child('Pessoa de Plano Fictícia');
        $this->openEpisode($technical, $child, '2026-09-01T09:00');
        $outsideScope = $this->child('Pessoa Fora do Escopo Fictícia');
        DB::table('criancas')->where('id', $outsideScope->id)->update(['organizacao_id' => null]);
        $relevantQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$relevantQueries): void {
            $sql = mb_strtolower($query->sql);

            if (str_contains($sql, 'criancas') || str_contains($sql, 'acolhimentos')) {
                $relevantQueries[] = $sql;
            }
        });

        $response = $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['situacao' => 'todos']));
        $props = $this->props($response);

        $this->assertSame([$child->id], array_column($props['criancas']['data'], 'id'));
        $this->assertLessThanOrEqual(4, count($relevantQueries));
        $this->assertTrue(collect($relevantQueries)->contains(
            fn (string $sql): bool => str_contains($sql, '"organizacao_id"')
                && str_contains($sql, '"acolhimentos"."unidade_id"'),
        ));

        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('indexname', [
                'acolhimentos_child_entry_index',
                'acolhimento_movements_timeline_index',
            ])
            ->pluck('indexname')
            ->all();

        $this->assertEqualsCanonicalizing([
            'acolhimentos_child_entry_index',
            'acolhimento_movements_timeline_index',
        ], $indexes);
    }

    private function technicalUser(): User
    {
        return User::factory()->create([
            'name' => 'Técnica de Filtros Fictícia',
            'email' => 'tecnica-filtros@exemplo-ficticio.local',
        ]);
    }

    private function child(string $name): Crianca
    {
        return Crianca::query()->create(['nome_completo' => $name]);
    }

    private function openEpisode(User $technical, Crianca $child, string $at): Acolhimento
    {
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.store', $child), [
                'ingresso_em' => $at,
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

        return Acolhimento::query()
            ->where('crianca_id', $child->id)
            ->orderByDesc('ingresso_em')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function move(
        User $technical,
        Crianca $child,
        Acolhimento $episode,
        string $type,
        string $at,
    ): void {
        $payload = [
            'tipo' => $type,
            'efetiva_em' => $at,
            'motivo' => 'Movimentação inteiramente fictícia.',
            'fundamento' => null,
            'local_destino' => in_array($type, ['internacao', 'desacolhimento'], true)
                ? 'Destino Fictício'
                : null,
            'observacao' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]), $payload)
            ->assertSessionHasNoErrors();
    }

    private function assertChildInFilter(User $technical, Crianca $child, string $filter): void
    {
        $response = $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['situacao' => $filter]));

        $this->assertSame([$child->id], array_column($this->props($response)['criancas']['data'], 'id'));
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        $response->assertOk();

        return json_decode(
            json_encode($response->viewData('page'), JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['props'];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Acolhimento;
use App\Models\Crianca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcolhimentoProjectionConsumersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_counts_only_open_episodes_and_not_legacy_status(): void
    {
        $technical = User::factory()->create();
        $openChild = Crianca::query()->create(['nome_completo' => 'Aberta Fictícia']);
        $closedChild = Crianca::query()->create(['nome_completo' => 'Encerrada Fictícia']);
        $legacyChild = Crianca::query()->create(['nome_completo' => 'Legada Fictícia']);
        DB::table('criancas')->where('id', $legacyChild->id)->update([
            'data_acolhimento' => '2025-01-02',
            'motivo_acolhimento' => 'Motivo legado fictício.',
            'status' => 'acolhida',
        ]);
        $this->openEpisode($technical, $openChild);
        $closedEpisode = $this->openEpisode($technical, $closedChild);
        $this->closeEpisode($technical, $closedChild, $closedEpisode);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('totais.acolhimentos_em_curso', 1)
                ->missing('totais.criancas'));
    }

    public function test_document_and_agenda_selectors_expose_effective_and_explicit_legacy_states(): void
    {
        $technical = User::factory()->create();
        $openChild = Crianca::query()->create(['nome_completo' => 'A Pessoa Aberta Fictícia']);
        $closedChild = Crianca::query()->create(['nome_completo' => 'B Pessoa Encerrada Fictícia']);
        $legacyChild = Crianca::query()->create(['nome_completo' => 'C Pessoa Legada Fictícia']);
        $noEntryChild = Crianca::query()->create(['nome_completo' => 'D Pessoa sem Ingresso Fictícia']);
        DB::table('criancas')->where('id', $legacyChild->id)->update([
            'data_acolhimento' => '2025-01-02',
            'motivo_acolhimento' => 'Motivo legado fictício.',
            'status' => 'acolhida',
        ]);
        $this->openEpisode($technical, $openChild);
        $closedEpisode = $this->openEpisode($technical, $closedChild);
        $this->closeEpisode($technical, $closedChild, $closedEpisode);

        foreach ([
            'reports.create',
            'visitas-tecnicas.create',
            'pertences.create',
            'agenda.index',
        ] as $routeName) {
            $response = $this->actingAsWithVerifiedMfa($technical)->get(route($routeName));
            $response->assertInertia(fn (Assert $page) => $page
                ->has('criancas', 4)
                ->where('criancas.0.id', $openChild->id)
                ->where('criancas.0.acolhimento_fonte', 'episodio')
                ->where('criancas.0.acolhimento_situacao', 'na_unidade')
                ->where('criancas.1.id', $closedChild->id)
                ->where('criancas.1.acolhimento_situacao', 'desacolhido')
                ->where('criancas.2.id', $legacyChild->id)
                ->where('criancas.2.acolhimento_fonte', 'legado')
                ->where('criancas.2.acolhimento_situacao', null)
                ->where('criancas.3.id', $noEntryChild->id)
                ->where('criancas.3.acolhimento_fonte', 'nenhum')
                ->where('criancas.3.acolhimento_situacao', null)
                ->missingAll([
                    'criancas.0.status',
                    'criancas.0.processo_numero',
                    'criancas.0.vara',
                    'criancas.0.comarca',
                    'criancas.0.ingresso_em',
                    'criancas.0.motivo_ingresso',
                    'criancas.0.episodio_aberto_id',
                    'criancas.0.legado_a_conferir',
                    'criancas.2.motivo_acolhimento',
                    'criancas.2.legado_a_conferir',
                ]));

            $page = json_decode(json_encode($response->viewData('page'), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(
                ['id', 'nome_completo', 'acolhimento_situacao', 'acolhimento_fonte'],
                array_keys($page['props']['criancas'][0]),
            );
        }

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('pias.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('criancas', 4)
                ->where('criancas.0.episodio_aberto_id', fn (mixed $id): bool => is_int($id))
                ->where('criancas.0.motivo_ingresso', 'Ingresso inteiramente fictício.')
                ->where('criancas.2.legado_a_conferir.motivo', 'Motivo legado fictício.'));
    }

    public function test_children_index_returns_only_fields_used_by_cards_without_narratives(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create([
            'nome_completo' => 'Pessoa Cartão Mínimo Fictícia',
            'data_nascimento' => '2013-06-07',
            'processo_numero' => 'PROCESSO-CARTAO-FICTICIO',
        ]);
        DB::table('criancas')->where('id', $child->id)->update([
            'data_acolhimento' => '2025-01-02',
            'motivo_acolhimento' => 'Narrativa legada fictícia que não deve sair.',
            'status' => 'acolhida',
        ]);

        $response = $this->actingAsWithVerifiedMfa($technical)->get(route('criancas.index'));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('criancas.data.0.id', $child->id)
            ->where('criancas.data.0.acolhimento_fonte', 'legado')
            ->missingAll([
                'criancas.data.0.data_acolhimento',
                'criancas.data.0.motivo_acolhimento',
                'criancas.data.0.legado_a_conferir',
                'criancas.data.0.motivo_ingresso',
                'criancas.data.0.ingresso_em',
                'criancas.data.0.status',
                'criancas.data.0.vara',
                'criancas.data.0.comarca',
            ]));

        $page = json_decode(json_encode($response->viewData('page'), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([
            'id',
            'nome_completo',
            'data_nascimento',
            'processo_numero',
            'foto_url',
            'acolhimento_situacao',
            'acolhimento_fonte',
        ], array_keys($page['props']['criancas']['data'][0]));
        $this->assertStringNotContainsString(
            'Narrativa legada fictícia que não deve sair.',
            json_encode($page['props'], JSON_THROW_ON_ERROR),
        );
    }

    public function test_children_index_drops_query_search_terms_and_uses_protected_search_post(): void
    {
        $technical = User::factory()->create();
        $term = 'Pessoa Busca Protegida Fictícia';
        Crianca::query()->create(['nome_completo' => $term]);

        $this->actingAsWithVerifiedMfa($technical)
            ->get(route('criancas.index', ['q' => $term]))
            ->assertRedirect(route('criancas.index'));

        $response = $this->actingAsWithVerifiedMfa($technical)
            ->post(route('busca.search'), ['q' => $term]);

        $response->assertStatus(303);
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(route('busca'), $location);
        $this->assertStringNotContainsString(rawurlencode($term), $location);
        $this->assertStringNotContainsString($term, $location);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function openEpisode(User $technical, Crianca $child): Acolhimento
    {
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.store', $child), [
                'ingresso_em' => '2026-10-01T09:00',
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

        return Acolhimento::query()->where('crianca_id', $child->id)->sole();
    }

    private function closeEpisode(User $technical, Crianca $child, Acolhimento $episode): void
    {
        $this->actingAsWithVerifiedMfa($technical)
            ->post(route('criancas.acolhimentos.movimentacoes.store', [$child, $episode]), [
                'tipo' => 'desacolhimento',
                'efetiva_em' => '2026-10-01T10:00',
                'motivo' => 'Saída inteiramente fictícia.',
                'fundamento' => null,
                'local_destino' => 'Destino Fictício',
                'observacao' => null,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors();
    }
}

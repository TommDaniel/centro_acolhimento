<?php

namespace Tests\Feature;

use App\Models\Crianca;
use App\Models\Evento;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\Report;
use App\Models\Setor;
use App\Models\User;
use App\Models\VisitaTecnica;
use App\Support\InstitutionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstitutionContextWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_derives_context_for_every_direct_aggregate_write(): void
    {
        $context = app(InstitutionContext::class);
        $sector = Setor::create(['nome' => 'Setor Fictício']);
        $user = User::factory()->create(['setor_id' => $sector->id]);
        $child = Crianca::create(['nome_completo' => 'Pessoa Acolhida Fictícia']);
        $aggregates = [
            $user,
            $sector,
            Pia::create(['crianca_id' => $child->id, 'setor_id' => $sector->id]),
            VisitaTecnica::create([
                'crianca_id' => $child->id,
                'data_visita' => '2026-09-10',
                'relato' => 'Relato inteiramente fictício.',
                'setor_id' => $sector->id,
            ]),
            Report::create([
                'crianca_id' => $child->id,
                'introducao' => 'Introdução fictícia.',
                'desenvolvimento' => 'Desenvolvimento fictício.',
                'setor_id' => $sector->id,
            ]),
            Pertence::create([
                'crianca_id' => $child->id,
                'itens' => [['descricao' => 'Item fictício', 'quantidade' => '1']],
                'data_entrega' => '2026-09-10',
                'setor_id' => $sector->id,
            ]),
            Evento::create([
                'titulo' => 'Evento Fictício',
                'inicio' => now(),
                'setor_id' => $sector->id,
            ]),
        ];

        $this->assertSame($context->organization()->id, $child->organizacao_id);

        foreach ($aggregates as $aggregate) {
            $this->assertSame($context->unit()->id, $aggregate->unidade_id, $aggregate::class);
        }
    }

    public function test_context_ids_are_not_mass_assignable_and_cannot_be_changed_directly(): void
    {
        $context = app(InstitutionContext::class);
        $child = Crianca::create([
            'nome_completo' => 'Pessoa Protegida Fictícia',
            'organizacao_id' => PHP_INT_MAX,
        ]);
        $sector = Setor::create([
            'nome' => 'Setor Protegido Fictício',
            'unidade_id' => PHP_INT_MAX,
        ]);

        $this->assertSame($context->organization()->id, $child->organizacao_id);
        $this->assertSame($context->unit()->id, $sector->unidade_id);

        $child->organizacao_id = PHP_INT_MAX;

        $this->expectException(ValidationException::class);
        $child->save();
    }

    public function test_http_body_query_and_nested_aliases_cannot_select_context(): void
    {
        $user = User::factory()->create();
        $valid = ['nome_completo' => 'Pessoa HTTP Fictícia'];

        foreach ([
            ['organizacao_id' => 1],
            ['organization' => 1],
            ['organization' => ['id' => 1]],
            ['organization' => [['id' => 1]]],
            ['metadata' => ['unit_id' => 1]],
            ['organizationId' => 1],
            ['organizacaoId' => 1],
            ['unidadeId' => 1],
            ['orgId' => 1],
            ['organization.id' => 1],
            ['filters[organization][id]' => 1],
        ] as $spoofed) {
            $this->actingAs($user)
                ->postJson(route('criancas.store'), array_replace_recursive($valid, $spoofed))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('contexto');
        }

        $this->actingAs($user)
            ->postJson(route('criancas.store', ['filters' => ['organizacao' => ['id' => 1]]]), $valid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contexto');

        $queryCollisionUrl = route('criancas.store').'?'.http_build_query([
            'filters' => ['organization_id' => 1],
        ]);

        $this->actingAs($user)
            ->postJson($queryCollisionUrl, array_merge($valid, ['filters' => 'safe']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contexto');

        $this->assertDatabaseMissing('criancas', ['nome_completo' => 'Pessoa HTTP Fictícia']);
    }

    public function test_route_context_aliases_are_rejected_and_generic_unit_field_is_allowed(): void
    {
        Route::middleware('web')->post('/_test/context/{organizationId}', fn () => response()->json(['ok' => true]));

        $this->postJson('/_test/context/123')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contexto');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('criancas.store'), [
                'nome_completo' => 'Pessoa com unidade de medida Fictícia',
                'unit' => 'mg',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('criancas', [
            'nome_completo' => 'Pessoa com unidade de medida Fictícia',
        ]);
    }

    public function test_sector_context_consistency_is_enforced_by_application_and_database(): void
    {
        $sector = Setor::create(['nome' => 'Setor Íntegro Fictício']);
        $constraint = DB::selectOne(
            "SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conname = 'users_setor_unidade_foreign'",
        );

        $this->assertNotNull($constraint);
        $this->assertStringContainsString('FOREIGN KEY (setor_id, unidade_id)', $constraint->definition);

        $user = User::factory()->create(['setor_id' => $sector->id]);
        $user->unidade_id = PHP_INT_MAX;

        $this->expectException(ValidationException::class);
        $user->save();
    }

    public function test_children_derive_context_from_parent_without_redundant_columns(): void
    {
        $child = Crianca::create(['nome_completo' => 'Pessoa com Familiar Fictício']);
        $family = $child->familiares()->create([
            'tipo' => 'familiar',
            'nome' => 'Familiar Fictício',
        ]);

        $this->assertSame($child->id, $family->crianca_id);
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('familiares', 'organizacao_id'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('familiares', 'unidade_id'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('pia_anexos', 'organizacao_id'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('crianca_documentos', 'unidade_id'));
    }
}

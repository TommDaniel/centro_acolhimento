<?php

namespace Tests\Feature;

use App\Actions\ProvisionInstitutionContext;
use App\Models\Organizacao;
use App\Models\Unidade;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InstitutionContextProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_idempotent_and_reconciliation_succeeds(): void
    {
        $organizationId = Organizacao::query()->sole()->id;
        $unitId = Unidade::query()->sole()->id;

        $this->artisan('institution:provision-context')
            ->expectsOutputToContain('organizacoes_criadas=0')
            ->expectsOutputToContain('unidades_criadas=0')
            ->assertSuccessful();

        $this->artisan('institution:provision-context', ['--reconcile-only' => true])
            ->assertSuccessful();

        $this->assertSame($organizationId, Organizacao::query()->sole()->id);
        $this->assertSame($unitId, Unidade::query()->sole()->id);
    }

    public function test_dry_run_reports_work_without_creating_or_backfilling(): void
    {
        Unidade::query()->delete();
        Organizacao::query()->delete();
        DB::table('criancas')->insert([
            'nome_completo' => 'Pessoa em simulação (Fictícia)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('institution:provision-context', ['--dry-run' => true])
            ->expectsOutputToContain('Simulação concluída sem gravações.')
            ->expectsOutputToContain('organizacoes_criadas=1')
            ->expectsOutputToContain('backfill_criancas.organizacao_id=1')
            ->assertSuccessful();

        $this->assertDatabaseCount('organizacoes', 0);
        $this->assertDatabaseCount('unidades', 0);
        $this->assertDatabaseHas('criancas', ['organizacao_id' => null]);
    }

    public function test_backfill_preserves_ids_and_counts_and_leaves_zero_context_orphans(): void
    {
        $organization = Organizacao::query()->sole();
        $unit = Unidade::query()->sole();
        $sectorId = DB::table('setores')->insertGetId([
            'nome' => 'Setor de Backfill Fictício',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Pessoa Usuária de Backfill (Fictícia)',
            'email' => 'backfill-ficticio@example.test',
            'password' => 'hash-ficticio-sem-uso',
            'setor_id' => $sectorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $childId = DB::table('criancas')->insertGetId([
            'nome_completo' => 'Pessoa de Backfill (Fictícia)',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = [
            'users' => DB::table('users')->count(),
            'setores' => DB::table('setores')->count(),
            'criancas' => DB::table('criancas')->count(),
        ];

        app(ProvisionInstitutionContext::class)->handle(chunkSize: 1);

        $this->assertSame($before['users'], DB::table('users')->count());
        $this->assertSame($before['setores'], DB::table('setores')->count());
        $this->assertSame($before['criancas'], DB::table('criancas')->count());
        $this->assertDatabaseHas('setores', ['id' => $sectorId, 'unidade_id' => $unit->id]);
        $this->assertDatabaseHas('users', ['id' => $userId, 'unidade_id' => $unit->id]);
        $this->assertDatabaseHas('criancas', ['id' => $childId, 'organizacao_id' => $organization->id]);
        $this->assertSame(0, DB::table('criancas')->whereNull('organizacao_id')->count());

        foreach (['users', 'setores', 'pias', 'visitas_tecnicas', 'reports', 'pertences', 'eventos'] as $table) {
            $this->assertSame(0, DB::table($table)->whereNull('unidade_id')->count(), $table);
        }
    }

    public function test_configuration_divergence_fails_before_any_backfill(): void
    {
        $childId = DB::table('criancas')->insertGetId([
            'nome_completo' => 'Pessoa sem contexto (Fictícia)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        config()->set('institution.organization.code', 'org-divergente-ficticia');

        try {
            app(ProvisionInstitutionContext::class)->handle();
            $this->fail('Era esperada falha fechada por configuração divergente.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('diverge', $exception->getMessage());
        }

        $this->assertDatabaseHas('criancas', ['id' => $childId, 'organizacao_id' => null]);
    }

    public function test_missing_configuration_and_unconfirmed_production_fail_closed(): void
    {
        config()->set('institution.organization.code');
        $this->artisan('institution:provision-context')->assertFailed();

        config()->set('institution.organization.code', 'org-producao-segura');
        config()->set('institution.organization.name', 'Organização de Produção');
        config()->set('institution.unit.code', 'unidade-producao-segura');
        config()->set('institution.unit.name', 'Unidade de Produção');
        config()->set('institution.production_confirmed', false);
        app()->instance('env', 'production');

        $this->artisan('institution:provision-context')->assertFailed();
    }

    public function test_command_does_not_expose_unexpected_error_details(): void
    {
        $sensitiveDetail = 'select * from secrets where password = local-secret-ficticio';
        $this->mock(ProvisionInstitutionContext::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException($sensitiveDetail));

        $this->artisan('institution:provision-context')
            ->expectsOutputToContain('Não foi possível concluir a operação de contexto institucional com segurança.')
            ->doesntExpectOutputToContain($sensitiveDetail)
            ->assertFailed();
    }

    public function test_reconciliation_rejects_context_orphan_without_mutating_it(): void
    {
        $childId = DB::table('criancas')->insertGetId([
            'nome_completo' => 'Pessoa Órfã de Contexto (Fictícia)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('institution:provision-context', ['--reconcile-only' => true])
            ->assertFailed();

        $this->assertDatabaseHas('criancas', ['id' => $childId, 'organizacao_id' => null]);
    }

    public function test_postgresql_rejects_second_context(): void
    {
        $this->expectException(QueryException::class);

        DB::table('organizacoes')->insert([
            'context_slot' => 2,
            'codigo' => 'segunda-org-ficticia',
            'nome' => 'Segunda Organização Fictícia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_second_unit(): void
    {
        $this->expectException(QueryException::class);

        DB::table('unidades')->insert([
            'context_slot' => 2,
            'organizacao_id' => Organizacao::query()->sole()->id,
            'codigo' => 'segunda-unidade-ficticia',
            'nome' => 'Segunda Unidade Fictícia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_invalid_context_foreign_key(): void
    {
        $this->expectException(QueryException::class);

        DB::table('criancas')->insert([
            'nome_completo' => 'Pessoa com contexto inválido (Fictícia)',
            'organizacao_id' => PHP_INT_MAX,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_sector_constraints_are_composite_and_all_new_foreign_keys_have_indexes(): void
    {
        $sectorConstraints = DB::table('pg_constraint')
            ->where('contype', 'f')
            ->where('conname', 'like', '%_setor_unidade_foreign')
            ->count();

        $this->assertSame(6, $sectorConstraints);

        $this->assertSame([], DB::select(<<<'SQL'
            select c.conrelid::regclass::text as table_name, c.conname
            from pg_constraint c
            join pg_namespace n on n.oid = c.connamespace
            where c.contype = 'f'
              and n.nspname = current_schema()
              and (c.conname like '%organizacao_id_foreign' or c.conname like '%unidade_id_foreign')
              and not exists (
                  select 1 from pg_index i
                  where i.indrelid = c.conrelid
                    and i.indisvalid and i.indisready
                    and i.indnkeyatts >= cardinality(c.conkey)
                    and not exists (
                        select 1 from unnest(c.conkey) with ordinality fk(attnum, position)
                        where (i.indkey::smallint[])[position - 1] <> fk.attnum
                    )
              )
            SQL));
    }
}

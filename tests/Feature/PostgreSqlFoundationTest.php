<?php

namespace Tests\Feature;

use App\Models\Crianca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PostgreSqlFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_suite_uses_postgresql_17(): void
    {
        $this->assertSame(
            'pgsql',
            DB::connection()->getDriverName(),
            'A suíte Feature deve falhar fora do PostgreSQL.',
        );

        $serverVersion = (int) DB::selectOne(
            "select current_setting('server_version_num') as version",
        )->version;

        $this->assertGreaterThanOrEqual(170000, $serverVersion);
        $this->assertLessThan(180000, $serverVersion);
    }

    public function test_synthetic_seed_preserves_essential_relationships(): void
    {
        $this->seed();

        $syntheticUser = User::query()
            ->where('email', 'admin@poc.local')
            ->firstOrFail();
        $syntheticChild = Crianca::query()
            ->with(['pias', 'familiares', 'criador'])
            ->where('nome_completo', 'João Pedro da Silva Fictício')
            ->firstOrFail();

        $this->assertStringContainsString('Fictício', $syntheticUser->name);
        $this->assertNotNull($syntheticUser->setor);
        $this->assertNotEmpty($syntheticChild->pias);
        $this->assertNotEmpty($syntheticChild->familiares);
        $this->assertNotNull($syntheticChild->criador);
    }

    public function test_every_application_foreign_key_has_a_supporting_index(): void
    {
        $missingIndexes = DB::select(<<<'SQL'
            select
                c.conrelid::regclass::text as table_name,
                c.conname as constraint_name
            from pg_constraint c
            join pg_namespace n on n.oid = c.connamespace
            where c.contype = 'f'
              and n.nspname = current_schema()
              and not exists (
                  select 1
                  from pg_index i
                  where i.indrelid = c.conrelid
                    and i.indisvalid
                    and i.indisready
                    and i.indnkeyatts >= cardinality(c.conkey)
                    and not exists (
                        select 1
                        from unnest(c.conkey) with ordinality fk(attnum, position)
                        where (i.indkey::smallint[])[position - 1] <> fk.attnum
                    )
              )
            order by c.conrelid::regclass::text, c.conname
            SQL);

        $this->assertSame(
            [],
            $missingIndexes,
            'Foreign keys sem índice: '.json_encode($missingIndexes, JSON_THROW_ON_ERROR),
        );
    }

    public function test_instant_columns_use_timestamptz_and_round_trip_to_sao_paulo(): void
    {
        $expectedColumns = [
            'users' => ['email_verified_at', 'created_at', 'updated_at'],
            'password_reset_tokens' => ['created_at'],
            'failed_jobs' => ['failed_at'],
            'setores' => ['created_at', 'updated_at'],
            'criancas' => ['created_at', 'updated_at'],
            'crianca_documentos' => ['created_at', 'updated_at'],
            'pias' => ['created_at', 'updated_at'],
            'visitas_tecnicas' => ['created_at', 'updated_at'],
            'reports' => ['created_at', 'updated_at'],
            'pertences' => ['created_at', 'updated_at'],
            'familiares' => ['created_at', 'updated_at'],
            'eventos' => ['inicio', 'fim', 'created_at', 'updated_at'],
            'pia_anexos' => ['created_at', 'updated_at'],
        ];

        $columns = collect(DB::select(<<<'SQL'
            select table_name, column_name, data_type
            from information_schema.columns
            where table_schema = current_schema()
            SQL))->keyBy(fn (object $column): string => "{$column->table_name}.{$column->column_name}");

        foreach ($expectedColumns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                $this->assertSame(
                    'timestamp with time zone',
                    $columns->get("{$table}.{$column}")?->data_type,
                    "{$table}.{$column} deve armazenar um instante inequívoco.",
                );
            }
        }

        $eventId = DB::table('eventos')->insertGetId([
            'titulo' => 'Evento sintético de fuso horário',
            'inicio' => '2026-08-31 15:00:00+00',
            'created_at' => '2026-08-31 15:00:00+00',
            'updated_at' => '2026-08-31 15:00:00+00',
        ]);
        $storedInstant = DB::table('eventos')->where('id', $eventId)->value('inicio');

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame(
            '2026-08-31 12:00:00 -03:00',
            CarbonImmutable::parse($storedInstant, 'UTC')
                ->setTimezone('America/Sao_Paulo')
                ->format('Y-m-d H:i:s P'),
        );
    }

    public function test_effective_office_number_chain_preserves_existing_number_and_is_deterministic(): void
    {
        Date::setTestNow('2026-08-31 12:00:00');

        try {
            $this->seed();
            $childId = Crianca::query()->firstOrFail()->getKey();
            $timestamp = '2026-08-31 12:00:00';

            foreach ($this->officeTables() as $table) {
                DB::table($table)->delete();
            }

            (require database_path('migrations/2026_07_17_000013_add_numero_oficio_to_documentos.php'))->down();
            (require database_path('migrations/2026_07_17_000013_add_numero_oficio_to_documentos.php'))->up();

            $documentIds = [
                'existing' => DB::table('pias')->insertGetId([
                    'crianca_id' => $childId,
                    'numero_oficio' => '7/2026',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]),
                'pias' => DB::table('pias')->insertGetId([
                    'crianca_id' => $childId,
                    'numero_oficio' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]),
                'visitas_tecnicas' => DB::table('visitas_tecnicas')->insertGetId([
                    'crianca_id' => $childId,
                    'data_visita' => '2026-08-31',
                    'numero_oficio' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]),
                'reports' => DB::table('reports')->insertGetId([
                    'crianca_id' => $childId,
                    'introducao' => 'Introdução sintética para teste.',
                    'desenvolvimento' => 'Desenvolvimento sintético para teste.',
                    'numero_oficio' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]),
                'pertences' => DB::table('pertences')->insertGetId([
                    'crianca_id' => $childId,
                    'itens' => json_encode([['descricao' => 'Item fictício', 'quantidade' => 1]], JSON_THROW_ON_ERROR),
                    'data_entrega' => '2026-08-31',
                    'numero_oficio' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]),
            ];

            (require database_path('migrations/2026_07_17_000014_preencher_numero_oficio_existentes.php'))->up();
            $beforeValidation = $this->officeNumberSnapshot();
            (require database_path('migrations/2026_07_17_000015_unicificar_numero_oficio.php'))->up();

            $this->assertSame($beforeValidation, $this->officeNumberSnapshot());
            $this->assertSame('7/2026', DB::table('pias')->where('id', $documentIds['existing'])->value('numero_oficio'));
            $this->assertSame('8/2026', DB::table('pertences')->where('id', $documentIds['pertences'])->value('numero_oficio'));
            $this->assertSame('9/2026', DB::table('pias')->where('id', $documentIds['pias'])->value('numero_oficio'));
            $this->assertSame('10/2026', DB::table('reports')->where('id', $documentIds['reports'])->value('numero_oficio'));
            $this->assertSame('11/2026', DB::table('visitas_tecnicas')->where('id', $documentIds['visitas_tecnicas'])->value('numero_oficio'));
        } finally {
            Date::setTestNow();
        }
    }

    public function test_office_number_validation_rejects_invalid_value_without_partial_change(): void
    {
        $this->seed();
        $documentId = DB::table('pias')->value('id');
        DB::table('pias')->where('id', $documentId)->update(['numero_oficio' => 'inválido']);
        $snapshot = $this->officeNumberSnapshot();

        try {
            (require database_path('migrations/2026_07_17_000015_unicificar_numero_oficio.php'))->up();
            $this->fail('A migration deveria rejeitar número inválido.');
        } catch (RuntimeException) {
            $this->assertSame($snapshot, $this->officeNumberSnapshot());
        }
    }

    public function test_office_number_validation_rejects_global_duplicate_without_partial_change(): void
    {
        $this->seed();
        $existingNumber = DB::table('pias')->value('numero_oficio');
        $documentId = DB::table('reports')->value('id');
        DB::table('reports')->where('id', $documentId)->update(['numero_oficio' => $existingNumber]);
        $snapshot = $this->officeNumberSnapshot();

        try {
            (require database_path('migrations/2026_07_17_000015_unicificar_numero_oficio.php'))->up();
            $this->fail('A migration deveria rejeitar número duplicado.');
        } catch (RuntimeException) {
            $this->assertSame($snapshot, $this->officeNumberSnapshot());
        }
    }

    /** @return list<string> */
    private function officeTables(): array
    {
        return ['pias', 'visitas_tecnicas', 'reports', 'pertences'];
    }

    /** @return array<string, array<int, string|null>> */
    private function officeNumberSnapshot(): array
    {
        return collect($this->officeTables())
            ->mapWithKeys(fn (string $table): array => [
                $table => DB::table($table)->orderBy('id')->pluck('numero_oficio', 'id')->all(),
            ])
            ->all();
    }
}

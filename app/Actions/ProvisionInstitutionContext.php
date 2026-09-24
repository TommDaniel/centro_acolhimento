<?php

namespace App\Actions;

use App\Exceptions\InstitutionContextOperationException;
use App\Models\Organizacao;
use App\Models\Unidade;
use App\Support\InstitutionContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProvisionInstitutionContext
{
    private const POSTGRES_ADVISORY_LOCK_KEY = 4200106;

    /** @var list<string> */
    private const UNIT_TABLES = [
        'setores',
        'users',
        'pias',
        'visitas_tecnicas',
        'reports',
        'pertences',
        'eventos',
    ];

    public function __construct(private InstitutionContext $context) {}

    /**
     * @return array{created_organizations: int, created_units: int, updated_names: int, backfilled: array<string, int>, organization_id: int|null, unit_id: int|null}
     */
    public function handle(bool $dryRun = false, bool $reconcileOnly = false, int $chunkSize = 200): array
    {
        if ($chunkSize < 1 || $chunkSize > 5000) {
            throw new InstitutionContextOperationException('O tamanho do lote deve estar entre 1 e 5000.');
        }

        $configured = $this->context->configuredValues();

        try {
            return DB::transaction(function (ConnectionInterface $connection) use ($configured, $dryRun, $reconcileOnly, $chunkSize): array {
                $this->acquireProvisioningLock($connection);
                $organizations = Organizacao::query()->lockForUpdate()->get();
                $units = Unidade::query()->lockForUpdate()->get();

                if ($organizations->count() > 1 || $units->count() > 1) {
                    throw new InstitutionContextOperationException('Existe mais de um contexto institucional persistido.');
                }

                $organization = $organizations->first();
                $unit = $units->first();

                if ($organization !== null && $organization->codigo !== $configured['organization_code']) {
                    throw new InstitutionContextOperationException('A organização persistida diverge da configuração.');
                }

                if ($unit !== null && $unit->codigo !== $configured['unit_code']) {
                    throw new InstitutionContextOperationException('A unidade persistida diverge da configuração.');
                }

                if ($unit !== null && ($organization === null || $unit->organizacao_id !== $organization->id)) {
                    throw new InstitutionContextOperationException('A unidade persistida não pertence à organização configurada.');
                }

                if ($reconcileOnly && ($organization === null || $unit === null)) {
                    throw new InstitutionContextOperationException('Não há contexto institucional completo para reconciliar.');
                }

                $result = [
                    'created_organizations' => $organization === null ? 1 : 0,
                    'created_units' => $unit === null ? 1 : 0,
                    'updated_names' => (int) ($organization !== null && $organization->nome !== $configured['organization_name'])
                        + (int) ($unit !== null && $unit->nome !== $configured['unit_name']),
                    'backfilled' => $this->pendingBackfillCounts(),
                    'organization_id' => $organization?->id,
                    'unit_id' => $unit?->id,
                ];

                if ($dryRun) {
                    if ($organization !== null && $unit !== null) {
                        $this->assertNoConflictingContext($organization->id, $unit->id);
                    }

                    return $result;
                }

                if ($reconcileOnly) {
                    if ($result['updated_names'] !== 0) {
                        throw new InstitutionContextOperationException('Os nomes persistidos divergem da configuração institucional.');
                    }

                    $this->assertNoConflictingContext($organization->id, $unit->id);
                    $this->assertReconciled($organization->id, $unit->id);

                    return $result;
                }

                $result['updated_names'] = 0;

                if ($organization === null) {
                    $organization = Organizacao::query()->create([
                        'codigo' => $configured['organization_code'],
                        'nome' => $configured['organization_name'],
                    ]);
                } elseif ($organization->nome !== $configured['organization_name']) {
                    $organization->update(['nome' => $configured['organization_name']]);
                    $result['updated_names']++;
                }

                if ($unit === null) {
                    $unit = new Unidade([
                        'codigo' => $configured['unit_code'],
                        'nome' => $configured['unit_name'],
                    ]);
                    $unit->organizacao()->associate($organization);
                    $unit->save();
                } elseif ($unit->nome !== $configured['unit_name']) {
                    $unit->update(['nome' => $configured['unit_name']]);
                    $result['updated_names']++;
                }

                $result['organization_id'] = $organization->id;
                $result['unit_id'] = $unit->id;

                $this->assertNoConflictingContext($organization->id, $unit->id);
                $this->backfillTable($connection, 'criancas', 'organizacao_id', $organization->id, $chunkSize);

                foreach (self::UNIT_TABLES as $table) {
                    $this->backfillTable($connection, $table, 'unidade_id', $unit->id, $chunkSize);
                }

                $this->assertReconciled($organization->id, $unit->id);
                $this->context->clearResolved();

                return $result;
            }, 3);
        } catch (InstitutionContextOperationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw InstitutionContextOperationException::unexpected();
        }
    }

    private function acquireProvisioningLock(ConnectionInterface $connection): void
    {
        if ($connection->getDriverName() === 'pgsql') {
            $connection->select('select pg_advisory_xact_lock(?)', [self::POSTGRES_ADVISORY_LOCK_KEY]);

            return;
        }

        if ($connection->getDriverName() === 'sqlite') {
            $connection->statement('pragma busy_timeout = 5000');

            return;
        }

        throw new InstitutionContextOperationException('O banco configurado não suporta o provisionamento institucional seguro.');
    }

    /** @return array<string, int> */
    private function pendingBackfillCounts(): array
    {
        $counts = ['criancas.organizacao_id' => DB::table('criancas')->whereNull('organizacao_id')->count()];

        foreach (self::UNIT_TABLES as $table) {
            $counts[$table.'.unidade_id'] = DB::table($table)->whereNull('unidade_id')->count();
        }

        return $counts;
    }

    private function assertNoConflictingContext(int $organizationId, int $unitId): void
    {
        if (DB::table('criancas')->whereNotNull('organizacao_id')->where('organizacao_id', '!=', $organizationId)->exists()) {
            throw new InstitutionContextOperationException('Há registros vinculados a uma organização conflitante.');
        }

        foreach (self::UNIT_TABLES as $table) {
            if (DB::table($table)->whereNotNull('unidade_id')->where('unidade_id', '!=', $unitId)->exists()) {
                throw new InstitutionContextOperationException('Há registros com unidade institucional conflitante.');
            }
        }

        foreach (array_diff(self::UNIT_TABLES, ['setores']) as $table) {
            if (DB::table($table)
                ->whereNotNull($table.'.setor_id')
                ->whereNotExists(function ($query) use ($table, $unitId) {
                    $query->selectRaw('1')
                        ->from('setores')
                        ->whereColumn('setores.id', $table.'.setor_id')
                        ->where(function ($unit) use ($unitId) {
                            $unit->whereNull('setores.unidade_id')->orWhere('setores.unidade_id', $unitId);
                        });
                })
                ->exists()) {
                throw new InstitutionContextOperationException('Há setor órfão ou de unidade institucional conflitante.');
            }
        }
    }

    private function backfillTable(ConnectionInterface $connection, string $table, string $column, int $contextId, int $chunkSize): void
    {
        $connection->table($table)
            ->select('id')
            ->whereNull($column)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($connection, $table, $column, $contextId): void {
                $connection->table($table)
                    ->whereIn('id', $rows->pluck('id'))
                    ->whereNull($column)
                    ->update([$column => $contextId]);
            });
    }

    private function assertReconciled(int $organizationId, int $unitId): void
    {
        if (DB::table('criancas')->whereNull('organizacao_id')->orWhere('organizacao_id', '!=', $organizationId)->exists()) {
            throw new InstitutionContextOperationException('A reconciliação deixou pessoas sem contexto válido.');
        }

        foreach (self::UNIT_TABLES as $table) {
            if (DB::table($table)->whereNull('unidade_id')->orWhere('unidade_id', '!=', $unitId)->exists()) {
                throw new InstitutionContextOperationException('A reconciliação deixou registros sem contexto institucional válido.');
            }
        }

        foreach (array_diff(self::UNIT_TABLES, ['setores']) as $table) {
            if (DB::table($table)
                ->whereNotNull($table.'.setor_id')
                ->whereNotExists(function ($query) use ($table) {
                    $query->selectRaw('1')
                        ->from('setores')
                        ->whereColumn('setores.id', $table.'.setor_id')
                        ->whereColumn('setores.unidade_id', $table.'.unidade_id');
                })
                ->exists()) {
                throw new InstitutionContextOperationException('A reconciliação encontrou setor institucional inválido.');
            }
        }
    }
}

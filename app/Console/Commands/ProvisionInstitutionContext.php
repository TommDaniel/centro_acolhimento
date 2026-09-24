<?php

namespace App\Console\Commands;

use App\Actions\ProvisionInstitutionContext as ProvisionInstitutionContextAction;
use App\Exceptions\InstitutionContextOperationException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('institution:provision-context {--dry-run : Valida e informa contagens sem gravar} {--reconcile-only : Apenas valida um contexto já provisionado} {--chunk=200 : Tamanho dos lotes de backfill}')]
#[Description('Provisiona e reconcilia a organização e a unidade únicas da implantação')]
class ProvisionInstitutionContext extends Command
{
    public function handle(ProvisionInstitutionContextAction $action): int
    {
        try {
            $result = $action->handle(
                dryRun: (bool) $this->option('dry-run'),
                reconcileOnly: (bool) $this->option('reconcile-only'),
                chunkSize: (int) $this->option('chunk'),
            );
        } catch (InstitutionContextOperationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error(InstitutionContextOperationException::unexpected()->getMessage());

            return self::FAILURE;
        }

        $this->components->info($this->option('dry-run') ? 'Simulação concluída sem gravações.' : 'Contexto institucional reconciliado.');
        $this->line('organizacoes_criadas='.$result['created_organizations']);
        $this->line('unidades_criadas='.$result['created_units']);
        $this->line('nomes_atualizados='.$result['updated_names']);
        $this->line('organizacao_id='.($result['organization_id'] ?? 'planejado'));
        $this->line('unidade_id='.($result['unit_id'] ?? 'planejado'));

        foreach ($result['backfilled'] as $field => $count) {
            $this->line("backfill_{$field}={$count}");
        }

        return self::SUCCESS;
    }
}

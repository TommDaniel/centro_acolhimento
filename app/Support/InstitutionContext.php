<?php

namespace App\Support;

use App\Exceptions\InstitutionContextOperationException;
use App\Models\Organizacao;
use App\Models\Setor;
use App\Models\Unidade;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class InstitutionContext
{
    private ?Organizacao $organization = null;

    private ?Unidade $unit = null;

    /** @return array{organization_code: string, organization_name: string, unit_code: string, unit_name: string} */
    public function configuredValues(): array
    {
        $values = [
            'organization_code' => Arr::get(config('institution'), 'organization.code'),
            'organization_name' => Arr::get(config('institution'), 'organization.name'),
            'unit_code' => Arr::get(config('institution'), 'unit.code'),
            'unit_name' => Arr::get(config('institution'), 'unit.name'),
        ];

        foreach ($values as $key => $value) {
            if (! is_string($value) || blank($value)) {
                throw new InstitutionContextOperationException("Configuração institucional obrigatória ausente: {$key}.");
            }
        }

        foreach (['organization_code', 'unit_code'] as $key) {
            if (preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/', $values[$key]) !== 1) {
                throw new InstitutionContextOperationException("Código institucional inválido: {$key}.");
            }
        }

        if (app()->isProduction() && config('institution.synthetic_legacy_demo') !== true) {
            if (config('institution.production_confirmed') !== true) {
                throw new InstitutionContextOperationException('O contexto institucional de produção não foi confirmado.');
            }

            foreach ($values as $value) {
                if (str_contains(mb_strtolower($value), 'fict')) {
                    throw new InstitutionContextOperationException('Valores sintéticos não podem provisionar produção.');
                }
            }
        }

        return $values;
    }

    public function organization(): Organizacao
    {
        $this->resolve();

        return $this->organization;
    }

    public function unit(): Unidade
    {
        $this->resolve();

        return $this->unit;
    }

    public function assertSectorBelongsToUnit(?int $sectorId, int $unitId): void
    {
        if ($sectorId === null) {
            return;
        }

        $belongs = Setor::query()
            ->whereKey($sectorId)
            ->where('unidade_id', $unitId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'setor_id' => 'O setor informado não está disponível.',
            ]);
        }
    }

    public function clearResolved(): void
    {
        $this->organization = null;
        $this->unit = null;
    }

    private function resolve(): void
    {
        if ($this->organization !== null && $this->unit !== null) {
            return;
        }

        $configured = $this->configuredValues();
        $organizations = Organizacao::query()->limit(2)->get();
        $units = Unidade::query()->limit(2)->get();

        if ($organizations->count() !== 1 || $units->count() !== 1) {
            throw new InstitutionContextOperationException('O contexto institucional persistido não está provisionado de forma única.');
        }

        $organization = $organizations->sole();
        $unit = $units->sole();

        if (
            $organization->codigo !== $configured['organization_code']
            || $unit->codigo !== $configured['unit_code']
            || $unit->organizacao_id !== $organization->id
        ) {
            throw new InstitutionContextOperationException('O contexto institucional persistido diverge da configuração.');
        }

        $this->organization = $organization;
        $this->unit = $unit;
    }
}

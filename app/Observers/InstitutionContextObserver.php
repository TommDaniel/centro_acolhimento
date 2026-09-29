<?php

namespace App\Observers;

use App\Models\Acolhimento;
use App\Models\Crianca;
use App\Models\Evento;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\Report;
use App\Models\Setor;
use App\Models\User;
use App\Models\VisitaTecnica;
use App\Support\InstitutionContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class InstitutionContextObserver
{
    /** @var list<class-string<Model>> */
    private const UNIT_MODELS = [
        Acolhimento::class,
        User::class,
        Setor::class,
        Pia::class,
        VisitaTecnica::class,
        Report::class,
        Pertence::class,
        Evento::class,
    ];

    public function __construct(private InstitutionContext $context) {}

    public function creating(Model $model): void
    {
        if ($model instanceof Crianca) {
            $model->setAttribute('organizacao_id', $this->context->organization()->id);

            return;
        }

        if (in_array($model::class, self::UNIT_MODELS, true)) {
            $unitId = $this->context->unit()->id;
            $model->setAttribute('unidade_id', $unitId);
            $this->context->assertSectorBelongsToUnit($this->sectorId($model), $unitId);
        }
    }

    public function updating(Model $model): void
    {
        $contextColumn = $model instanceof Crianca ? 'organizacao_id' : 'unidade_id';
        $expectedId = $model instanceof Crianca
            ? $this->context->organization()->id
            : $this->context->unit()->id;

        if ($model->getAttribute($contextColumn) === null) {
            $model->setAttribute($contextColumn, $expectedId);
        }

        if ((int) $model->getAttribute($contextColumn) !== $expectedId) {
            throw ValidationException::withMessages([
                'contexto' => 'O contexto institucional do registro não pode ser alterado.',
            ]);
        }

        if (! $model instanceof Crianca) {
            $this->context->assertSectorBelongsToUnit($this->sectorId($model), $expectedId);
        }
    }

    private function sectorId(Model $model): ?int
    {
        $sectorId = $model->getAttribute('setor_id');

        return $sectorId === null ? null : (int) $sectorId;
    }
}

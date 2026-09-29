<?php

namespace App\Services;

use App\Enums\CriancaSituacaoFiltro;
use App\Models\Acolhimento;
use App\Models\AcolhimentoMovimentacao;
use App\Models\Crianca;
use App\Support\InstitutionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class CriancaSituacaoQuery
{
    public function __construct(private InstitutionContext $context) {}

    public function scopedChildren(): Builder
    {
        return Crianca::query()
            ->where('organizacao_id', $this->context->organization()->id);
    }

    public function filter(Builder $scopedChildren, CriancaSituacaoFiltro $filter): Builder
    {
        $classified = DB::query()
            ->fromSub($this->classifiedChildren($scopedChildren), 'filtered_classes')
            ->select('filtered_classes.id');

        if ($filter !== CriancaSituacaoFiltro::Todos) {
            $classified->where('filtered_classes.situacao_filtro', $filter->value);
        }

        return Crianca::query()->whereIn(
            (new Crianca)->qualifyColumn('id'),
            $classified,
        );
    }

    /** @return array<string, int> */
    public function counts(Builder $scopedChildren): array
    {
        $grouped = DB::query()
            ->fromSub($this->classifiedChildren($scopedChildren), 'classified_children')
            ->select('situacao_filtro')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('situacao_filtro')
            ->pluck('aggregate', 'situacao_filtro');

        $counts = [];

        foreach (CriancaSituacaoFiltro::cases() as $filter) {
            if ($filter === CriancaSituacaoFiltro::Todos) {
                continue;
            }

            $counts[$filter->value] = (int) ($grouped[$filter->value] ?? 0);
        }

        $counts[CriancaSituacaoFiltro::Todos->value] = array_sum($counts);

        return $counts;
    }

    public function addProjection(Builder $query): Builder
    {
        $childId = (new Crianca)->qualifyColumn('id');
        $latestEpisodeId = $this->latestEpisodeId($childId);

        return $query
            ->selectSub(clone $latestEpisodeId, 'latest_acolhimento_id')
            ->selectSub($this->latestMovementSituation($latestEpisodeId), 'canonical_situation');
    }

    /** @return array{acolhimento_situacao: string|null, acolhimento_fonte: string} */
    public function summary(Crianca $child): array
    {
        if ($child->getAttribute('latest_acolhimento_id') !== null) {
            return [
                'acolhimento_situacao' => $child->getAttribute('canonical_situation'),
                'acolhimento_fonte' => 'episodio',
            ];
        }

        $hasLegacyData = $child->data_acolhimento !== null
            || filled($child->motivo_acolhimento)
            || $child->status === 'desligada';

        return [
            'acolhimento_situacao' => null,
            'acolhimento_fonte' => $hasLegacyData ? 'legado' : 'nenhum',
        ];
    }

    /** @return array<int, array{value: string, label: string, count: int}> */
    public function options(array $counts): array
    {
        return array_map(
            static fn (CriancaSituacaoFiltro $filter): array => [
                'value' => $filter->value,
                'label' => $filter->label(),
                'count' => $counts[$filter->value] ?? 0,
            ],
            CriancaSituacaoFiltro::cases(),
        );
    }

    private function classifiedChildren(Builder $scopedChildren): QueryBuilder
    {
        $childTable = (new Crianca)->getTable();
        $latestEpisodeId = $this->latestEpisodeId("{$childTable}.id");

        $childrenWithEpisode = (clone $scopedChildren)
            ->reorder()
            ->select([
                "{$childTable}.id",
                "{$childTable}.data_acolhimento",
                "{$childTable}.motivo_acolhimento",
                "{$childTable}.status",
            ])
            ->selectSub($latestEpisodeId, 'latest_acolhimento_id');

        $childrenWithSituation = DB::query()
            ->fromSub($childrenWithEpisode->toBase(), 'scoped_children')
            ->select([
                'scoped_children.id',
                'scoped_children.data_acolhimento',
                'scoped_children.motivo_acolhimento',
                'scoped_children.status',
                'scoped_children.latest_acolhimento_id',
            ])
            ->selectSub(
                $this->latestMovementSituationForProjectedEpisode(),
                'canonical_situation',
            );

        return DB::query()
            ->fromSub($childrenWithSituation, 'classification_inputs')
            ->select('classification_inputs.id')
            ->selectRaw(<<<'SQL'
CASE
    WHEN latest_acolhimento_id IS NOT NULL AND canonical_situation = 'na_unidade' THEN 'acolhidos'
    WHEN latest_acolhimento_id IS NOT NULL AND canonical_situation = 'evadido' THEN 'evadidos'
    WHEN latest_acolhimento_id IS NOT NULL AND canonical_situation = 'internado' THEN 'internados'
    WHEN latest_acolhimento_id IS NOT NULL AND canonical_situation = 'desacolhido' THEN 'desacolhidos'
    WHEN latest_acolhimento_id IS NULL AND (
        data_acolhimento IS NOT NULL
        OR NULLIF(TRIM(motivo_acolhimento), '') IS NOT NULL
        OR status = 'desligada'
    ) THEN 'a_conferir'
    WHEN latest_acolhimento_id IS NULL THEN 'sem_ingresso'
END AS situacao_filtro
SQL);
    }

    private function latestEpisodeId(string $qualifiedChildId): Builder
    {
        $episodeTable = (new Acolhimento)->getTable();

        return Acolhimento::query()
            ->select("{$episodeTable}.id")
            ->whereColumn("{$episodeTable}.crianca_id", $qualifiedChildId)
            ->where("{$episodeTable}.unidade_id", $this->context->unit()->id)
            ->orderByDesc("{$episodeTable}.ingresso_em")
            ->orderByDesc("{$episodeTable}.id")
            ->limit(1);
    }

    private function latestMovementSituation(Builder $latestEpisodeId): Builder
    {
        $movementTable = (new AcolhimentoMovimentacao)->getTable();

        return AcolhimentoMovimentacao::query()
            ->select("{$movementTable}.situacao_resultante")
            ->where("{$movementTable}.acolhimento_id", clone $latestEpisodeId)
            ->orderByDesc("{$movementTable}.efetiva_em")
            ->orderByDesc("{$movementTable}.id")
            ->limit(1);
    }

    private function latestMovementSituationForProjectedEpisode(): Builder
    {
        $movementTable = (new AcolhimentoMovimentacao)->getTable();

        return AcolhimentoMovimentacao::query()
            ->select("{$movementTable}.situacao_resultante")
            ->whereColumn("{$movementTable}.acolhimento_id", 'scoped_children.latest_acolhimento_id')
            ->orderByDesc("{$movementTable}.efetiva_em")
            ->orderByDesc("{$movementTable}.id")
            ->limit(1);
    }
}

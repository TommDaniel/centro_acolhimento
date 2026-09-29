<?php

namespace App\Services;

use App\Enums\CriancaSituacaoFiltro;
use App\Models\Crianca;
use App\Models\CriancaInformacaoEscolar;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\Report;
use App\Models\VisitaTecnica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProtectedSearchQuery
{
    private const PAGE_SIZE = 15;

    /** @var list<string> */
    private const IDENTIFICATION_COLUMNS = [
        'nome_completo', 'nome_social', 'processo_numero', 'rg', 'cpf',
        'nome_mae', 'nome_pai', 'responsavel_legal',
    ];

    /** @var list<string> */
    private const SCHOOL_TEXT_COLUMNS = [
        'escola_nome', 'situacao_complemento', 'rede_complemento', 'matricula',
        'ano_serie', 'turma', 'turno_complemento',
    ];

    public function __construct(
        private CriancaSituacaoQuery $situacaoQuery,
    ) {}

    /**
     * @return array{
     *     criancas: LengthAwarePaginator<int, array<string, mixed>>,
     *     filtros: array<int, array{value: string, label: string, count: int}>
     * }
     */
    public function search(string $term, CriancaSituacaoFiltro $situation): array
    {
        $like = $this->likePattern($term);
        $schoolCodes = $this->schoolCodeMatches($term);
        $documentTypes = $this->documentTypeMatches($term);

        $scopedChildren = $this->situacaoQuery->scopedChildren()
            ->where(function (Builder $query) use ($like, $schoolCodes, $documentTypes): void {
                $this->whereAnyLike($query, self::IDENTIFICATION_COLUMNS, $like);

                $query
                    ->orWhereIn('id', $this->schoolMatches($like, $schoolCodes)->select('crianca_id'))
                    ->orWhereIn('id', $this->piaMatches($like, $documentTypes['pia'])->select('crianca_id'))
                    ->orWhereIn('id', $this->visitMatches($like, $documentTypes['visita_tecnica'])->select('crianca_id'))
                    ->orWhereIn('id', $this->reportMatches($like, $documentTypes['parecer'])->select('crianca_id'))
                    ->orWhereIn('id', $this->belongingMatches($like, $documentTypes['pertences'])->select('crianca_id'));
            });

        $counts = $this->situacaoQuery->counts($scopedChildren);
        $query = $this->situacaoQuery->filter($scopedChildren, $situation)
            ->select([
                'id', 'nome_completo', 'data_nascimento', 'processo_numero',
                'data_acolhimento', 'motivo_acolhimento', 'status', 'updated_by', 'updated_at',
            ])
            ->withCount(['pias', 'reports', 'visitasTecnicas', 'pertences'])
            ->with([
                'atualizador:id,name',
                'informacoesEscolares' => function (HasMany $relation) use ($like, $schoolCodes): void {
                    $this->applySchoolRelationMatch($relation, $like, $schoolCodes)
                        ->select([
                            'id', 'crianca_id', 'situacao_codigo', 'escola_nome',
                            'created_by', 'recorded_at',
                        ])
                        ->with('criador:id,name')
                        ->orderByDesc('id')
                        ->limit(1);
                },
                'pias' => function (HasMany $relation) use ($like, $documentTypes): void {
                    $this->applyOfficeNumberRelationMatch($relation, $like, $documentTypes['pia'])
                        ->select(['id', 'crianca_id', 'numero_oficio', 'created_by', 'created_at'])
                        ->with('criador:id,name')
                        ->latest('created_at')
                        ->latest('id')
                        ->limit(1);
                },
                'visitasTecnicas' => function (HasMany $relation) use ($like, $documentTypes): void {
                    $this->applyOfficeNumberRelationMatch($relation, $like, $documentTypes['visita_tecnica'])
                        ->select(['id', 'crianca_id', 'numero_oficio', 'created_by', 'created_at'])
                        ->with('criador:id,name')
                        ->latest('created_at')
                        ->latest('id')
                        ->limit(1);
                },
                'reports' => function (HasMany $relation) use ($like, $documentTypes): void {
                    $this->applyReportRelationMatch($relation, $like, $documentTypes['parecer'])
                        ->select(['id', 'crianca_id', 'titulo', 'numero_oficio', 'created_by', 'created_at'])
                        ->with('criador:id,name')
                        ->latest('created_at')
                        ->latest('id')
                        ->limit(1);
                },
                'pertences' => function (HasMany $relation) use ($like, $documentTypes): void {
                    $this->applyOfficeNumberRelationMatch($relation, $like, $documentTypes['pertences'])
                        ->select(['id', 'crianca_id', 'numero_oficio', 'created_by', 'created_at'])
                        ->with('criador:id,name')
                        ->latest('created_at')
                        ->latest('id')
                        ->limit(1);
                },
            ])
            ->orderBy('nome_completo')
            ->orderBy('id');
        $this->situacaoQuery->addProjection($query);

        $children = $query
            ->paginate(self::PAGE_SIZE)
            ->appends(['situacao' => $situation->value])
            ->through(fn (Crianca $child): array => $this->serialize($child));

        return [
            'criancas' => $children,
            'filtros' => $this->situacaoQuery->options($counts),
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(Crianca $child): array
    {
        $sources = collect();
        $school = $child->informacoesEscolares->first();

        if ($school !== null) {
            $schoolStatus = CriancaInformacaoEscolar::SITUACOES[$school->situacao_codigo]
                ?? $school->situacao_codigo;
            $sources->push($this->source(
                'escola_atual',
                'Informação escolar atual',
                collect([$school->escola_nome, $schoolStatus])->filter()->join(' · '),
                $school->recorded_at?->toIso8601String(),
                $school->criador?->name,
            ));
        }

        $pia = $child->pias->first();
        if ($pia !== null) {
            $sources->push($this->createdSource(
                'pia',
                'PIA',
                $this->officeNumberDetail($pia->numero_oficio),
                $pia->created_at?->toIso8601String(),
                $pia->criador?->name,
            ));
        }

        $visit = $child->visitasTecnicas->first();
        if ($visit !== null) {
            $sources->push($this->createdSource(
                'visita_tecnica',
                'Visita técnica',
                $this->officeNumberDetail($visit->numero_oficio),
                $visit->created_at?->toIso8601String(),
                $visit->criador?->name,
            ));
        }

        $report = $child->reports->first();
        if ($report !== null) {
            $sources->push($this->createdSource(
                'parecer',
                'Parecer',
                collect([$report->titulo, $this->officeNumberDetail($report->numero_oficio)])->filter()->join(' · '),
                $report->created_at?->toIso8601String(),
                $report->criador?->name,
            ));
        }

        $belonging = $child->pertences->first();
        if ($belonging !== null) {
            $sources->push($this->createdSource(
                'pertences',
                'Pertences',
                $this->officeNumberDetail($belonging->numero_oficio),
                $belonging->created_at?->toIso8601String(),
                $belonging->criador?->name,
            ));
        }

        if ($sources->isEmpty()) {
            $sources->push($this->source(
                'identificacao',
                'Identificação',
                updatedAt: $child->updated_at?->toIso8601String(),
                updatedBy: $child->atualizador?->name,
            ));
        }

        $projection = $this->situacaoQuery->summary($child);

        return [
            'id' => $child->id,
            'nome_completo' => $child->nome_completo,
            'data_nascimento' => $child->data_nascimento?->toDateString(),
            'processo_numero' => $child->processo_numero,
            'acolhimento_situacao' => $projection['acolhimento_situacao'],
            'acolhimento_fonte' => $projection['acolhimento_fonte'],
            'pias_count' => (int) $child->pias_count,
            'reports_count' => (int) $child->reports_count,
            'visitas_tecnicas_count' => (int) $child->visitas_tecnicas_count,
            'pertences_count' => (int) $child->pertences_count,
            'fontes_busca' => $sources->values()->all(),
        ];
    }

    /**
     * @return array{
     *     tipo: string,
     *     rotulo: string,
     *     detalhe: ?string,
     *     atualizado_em: ?string,
     *     atualizado_por: ?string
     * }
     */
    private function source(
        string $type,
        string $label,
        ?string $detail = null,
        ?string $updatedAt = null,
        ?string $updatedBy = null,
    ): array {
        return [
            'tipo' => $type,
            'rotulo' => $label,
            'detalhe' => filled($detail) ? $detail : null,
            'atualizado_em' => $updatedAt,
            'atualizado_por' => $updatedBy,
        ];
    }

    /**
     * @return array{
     *     tipo: string,
     *     rotulo: string,
     *     detalhe: ?string,
     *     criado_em: ?string,
     *     criado_por: ?string
     * }
     */
    private function createdSource(
        string $type,
        string $label,
        ?string $detail,
        ?string $createdAt,
        ?string $createdBy,
    ): array {
        return [
            'tipo' => $type,
            'rotulo' => $label,
            'detalhe' => filled($detail) ? $detail : null,
            'criado_em' => $createdAt,
            'criado_por' => $createdBy,
        ];
    }

    private function officeNumberDetail(?string $officeNumber): ?string
    {
        return filled($officeNumber) ? "Ofício {$officeNumber}" : null;
    }

    /** @param array<string, list<string>> $schoolCodes */
    private function schoolMatches(string $like, array $schoolCodes): Builder
    {
        return $this->applySchoolMatch(CriancaInformacaoEscolar::query(), $like, $schoolCodes);
    }

    /** @param array<string, list<string>> $schoolCodes */
    private function applySchoolMatch(Builder $query, string $like, array $schoolCodes): Builder
    {
        return $query
            ->whereDoesntHave('proximaVersao')
            ->where(function (Builder $matches) use ($like, $schoolCodes): void {
                $this->whereAnyLike($matches, self::SCHOOL_TEXT_COLUMNS, $like);

                foreach ($schoolCodes as $column => $codes) {
                    if ($codes !== []) {
                        $matches->orWhereIn($column, $codes);
                    }
                }
            });
    }

    /** @param array<string, list<string>> $schoolCodes */
    private function applySchoolRelationMatch(HasMany $relation, string $like, array $schoolCodes): HasMany
    {
        $relation
            ->whereDoesntHave('proximaVersao')
            ->where(function (Builder $matches) use ($like, $schoolCodes): void {
                $this->whereAnyLike($matches, self::SCHOOL_TEXT_COLUMNS, $like);

                foreach ($schoolCodes as $column => $codes) {
                    if ($codes !== []) {
                        $matches->orWhereIn($column, $codes);
                    }
                }
            });

        return $relation;
    }

    private function piaMatches(string $like, bool $typeMatches): Builder
    {
        return $this->applyOfficeNumberMatch(Pia::query(), $like, $typeMatches);
    }

    private function visitMatches(string $like, bool $typeMatches): Builder
    {
        return $this->applyOfficeNumberMatch(VisitaTecnica::query(), $like, $typeMatches);
    }

    private function reportMatches(string $like, bool $typeMatches): Builder
    {
        return $this->applyReportMatch(Report::query(), $like, $typeMatches);
    }

    private function belongingMatches(string $like, bool $typeMatches): Builder
    {
        return $this->applyOfficeNumberMatch(Pertence::query(), $like, $typeMatches);
    }

    private function applyOfficeNumberMatch(Builder $query, string $like, bool $typeMatches): Builder
    {
        if ($typeMatches) {
            return $query;
        }

        return $query->whereLike('numero_oficio', $like);
    }

    private function applyOfficeNumberRelationMatch(HasMany $relation, string $like, bool $typeMatches): HasMany
    {
        if (! $typeMatches) {
            $relation->whereLike('numero_oficio', $like);
        }

        return $relation;
    }

    private function applyReportMatch(Builder $query, string $like, bool $typeMatches): Builder
    {
        if ($typeMatches) {
            return $query;
        }

        return $query->where(function (Builder $matches) use ($like): void {
            $matches->whereLike('titulo', $like)
                ->orWhereLike('numero_oficio', $like);
        });
    }

    private function applyReportRelationMatch(HasMany $relation, string $like, bool $typeMatches): HasMany
    {
        if (! $typeMatches) {
            $relation->where(function (Builder $matches) use ($like): void {
                $matches->whereLike('titulo', $like)
                    ->orWhereLike('numero_oficio', $like);
            });
        }

        return $relation;
    }

    /** @return array{pia: bool, visita_tecnica: bool, parecer: bool, pertences: bool} */
    private function documentTypeMatches(string $term): array
    {
        $normalized = $this->normalize($term);

        return [
            'pia' => in_array($normalized, ['pia', 'plano individual de atendimento'], true),
            'visita_tecnica' => in_array($normalized, ['visita', 'visita tecnica', 'relatorio de visita tecnica'], true),
            'parecer' => in_array($normalized, ['parecer', 'parecer do acolhido'], true),
            'pertences' => in_array($normalized, ['pertence', 'pertences', 'termo de recebimento', 'termo de entrega'], true),
        ];
    }

    /** @return array{situacao_codigo: list<string>, rede_codigo: list<string>, turno_codigo: list<string>} */
    private function schoolCodeMatches(string $term): array
    {
        return [
            'situacao_codigo' => $this->matchingCodes(CriancaInformacaoEscolar::SITUACOES, $term),
            'rede_codigo' => $this->matchingCodes(CriancaInformacaoEscolar::REDES, $term),
            'turno_codigo' => $this->matchingCodes(CriancaInformacaoEscolar::TURNOS, $term),
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return list<string>
     */
    private function matchingCodes(array $options, string $term): array
    {
        $normalizedTerm = $this->normalize($term);

        return collect($options)
            ->filter(function (string $label, string $code) use ($normalizedTerm): bool {
                return Str::contains($this->normalize($label), $normalizedTerm)
                    || Str::contains($this->normalize($code), $normalizedTerm);
            })
            ->keys()
            ->values()
            ->all();
    }

    /** @param list<string> $columns */
    private function whereAnyLike(Builder $query, array $columns, string $like): Builder
    {
        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $query->whereLike($column, $like);

                continue;
            }

            $query->orWhereLike($column, $like);
        }

        return $query;
    }

    private function likePattern(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->replace('_', ' ')
            ->ascii()
            ->lower()
            ->squish();
    }
}

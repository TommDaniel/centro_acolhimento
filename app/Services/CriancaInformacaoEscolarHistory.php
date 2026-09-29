<?php

namespace App\Services;

use App\Models\Crianca;
use App\Models\CriancaInformacaoEscolar;
use Illuminate\Database\Eloquent\Builder;

class CriancaInformacaoEscolarHistory
{
    public const PAGE_SIZE = 20;

    /** @var list<string> */
    private const COLUMNS = [
        'id', 'crianca_id', 'versao_anterior_id', 'situacao_codigo',
        'situacao_complemento', 'escola_nome', 'rede_codigo', 'rede_complemento',
        'matricula', 'ano_serie', 'turma', 'turno_codigo', 'turno_complemento',
        'vigente_em', 'fonte_codigo', 'fonte_complemento', 'created_by', 'recorded_at',
    ];

    public function current(Crianca $crianca): ?CriancaInformacaoEscolar
    {
        return CriancaInformacaoEscolar::query()
            ->whereBelongsTo($crianca)
            ->whereDoesntHave('proximaVersao')
            ->orderByDesc('id')
            ->first();
    }

    /** @return array{data: list<array<string, mixed>>, tem_mais: bool, proximo_antes_de: ?int} */
    public function initialPage(
        Crianca $crianca,
        ?CriancaInformacaoEscolar $current,
    ): array {
        $query = $this->queryFor($crianca);

        if ($current !== null) {
            $query->whereKeyNot($current->getKey());
        }

        return $this->page($query);
    }

    /** @return array{data: list<array<string, mixed>>, tem_mais: bool, proximo_antes_de: ?int} */
    public function olderPage(Crianca $crianca, int $beforeId): array
    {
        return $this->page(
            $this->queryFor($crianca)->where('id', '<', $beforeId),
        );
    }

    /** @return array<string, mixed> */
    public function serialize(CriancaInformacaoEscolar $version): array
    {
        return [
            'id' => $version->id,
            'situacao_codigo' => $version->situacao_codigo,
            'situacao' => CriancaInformacaoEscolar::SITUACOES[$version->situacao_codigo]
                ?? $version->situacao_codigo,
            'situacao_complemento' => $version->situacao_complemento,
            'escola_nome' => $version->escola_nome,
            'rede_codigo' => $version->rede_codigo,
            'rede' => $version->rede_codigo === null
                ? null
                : (CriancaInformacaoEscolar::REDES[$version->rede_codigo] ?? $version->rede_codigo),
            'rede_complemento' => $version->rede_complemento,
            'matricula' => $version->matricula,
            'ano_serie' => $version->ano_serie,
            'turma' => $version->turma,
            'turno_codigo' => $version->turno_codigo,
            'turno' => $version->turno_codigo === null
                ? null
                : (CriancaInformacaoEscolar::TURNOS[$version->turno_codigo] ?? $version->turno_codigo),
            'turno_complemento' => $version->turno_complemento,
            'vigente_em' => $version->vigente_em?->toDateString(),
            'fonte_codigo' => $version->fonte_codigo,
            'fonte' => CriancaInformacaoEscolar::FONTES[$version->fonte_codigo]
                ?? $version->fonte_codigo,
            'fonte_complemento' => $version->fonte_complemento,
            'registrado_por' => $version->criador?->name,
            'registrado_em' => $version->recorded_at,
        ];
    }

    /** @return Builder<CriancaInformacaoEscolar> */
    private function queryFor(Crianca $crianca): Builder
    {
        return CriancaInformacaoEscolar::query()
            ->whereBelongsTo($crianca)
            ->select(self::COLUMNS)
            ->with('criador:id,name');
    }

    /**
     * @param  Builder<CriancaInformacaoEscolar>  $query
     * @return array{data: list<array<string, mixed>>, tem_mais: bool, proximo_antes_de: ?int}
     */
    private function page(Builder $query): array
    {
        $versions = $query
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();
        $hasMore = $versions->count() > self::PAGE_SIZE;
        $data = $versions
            ->take(self::PAGE_SIZE)
            ->map($this->serialize(...))
            ->values();

        return [
            'data' => $data->all(),
            'tem_mais' => $hasMore,
            'proximo_antes_de' => $hasMore ? $data->last()['id'] : null,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Acolhimento;
use App\Models\Crianca;
use App\Support\InstitutionContext;
use Illuminate\Support\Collection;

class AcolhimentoProjection
{
    public function __construct(private InstitutionContext $context) {}

    /** @return Collection<int, array<string, mixed>> */
    public function childrenForSelection(): Collection
    {
        return Crianca::query()
            ->where('organizacao_id', $this->context->organization()->id)
            ->with('ultimoAcolhimento.ultimaMovimentacao')
            ->orderBy('nome_completo')
            ->get(['id', 'nome_completo', 'data_acolhimento', 'motivo_acolhimento', 'status'])
            ->map(fn (Crianca $crianca): array => [
                'id' => $crianca->id,
                'nome_completo' => $crianca->nome_completo,
                ...$this->summaryForChild($crianca),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function childrenForPia(): Collection
    {
        return Crianca::query()
            ->where('organizacao_id', $this->context->organization()->id)
            ->with('ultimoAcolhimento.ultimaMovimentacao')
            ->orderBy('nome_completo')
            ->get([
                'id', 'nome_completo', 'data_acolhimento', 'motivo_acolhimento',
                'status',
            ])
            ->map(fn (Crianca $crianca): array => [
                'id' => $crianca->id,
                'nome_completo' => $crianca->nome_completo,
                ...$this->contextForPia($crianca),
            ]);
    }

    /** @return array<string, mixed> */
    public function summaryForChild(Crianca $crianca): array
    {
        $episode = $crianca->ultimoAcolhimento;
        $movement = $episode?->ultimaMovimentacao;
        $legacy = $this->legacyData($crianca);

        return [
            'acolhimento_situacao' => $movement?->situacao_resultante?->value,
            'acolhimento_fonte' => $episode !== null ? 'episodio' : ($legacy !== null ? 'legado' : 'nenhum'),
        ];
    }

    /** @return array<string, mixed> */
    private function contextForPia(Crianca $crianca): array
    {
        $episode = $crianca->ultimoAcolhimento;
        $openEpisode = $episode !== null && $episode->encerrado_em === null ? $episode : null;

        return [
            ...$this->summaryForChild($crianca),
            'episodio_aberto_id' => $openEpisode?->id,
            'ingresso_em' => $openEpisode?->ingresso_em,
            'motivo_ingresso' => $openEpisode?->motivo,
            'processo_numero_snapshot' => $openEpisode?->processo_numero_snapshot,
            'vara_snapshot' => $openEpisode?->vara_snapshot,
            'comarca_snapshot' => $openEpisode?->comarca_snapshot,
            'legado_a_conferir' => $this->legacyData($crianca),
        ];
    }

    /** @return array{data: string|null, motivo: string|null, estado_anterior: string|null}|null */
    public function legacyData(Crianca $crianca): ?array
    {
        if ($crianca->data_acolhimento === null
            && blank($crianca->motivo_acolhimento)
            && $crianca->status !== 'desligada') {
            return null;
        }

        return [
            'data' => $crianca->data_acolhimento?->toDateString(),
            'motivo' => $crianca->motivo_acolhimento,
            'estado_anterior' => $crianca->status,
        ];
    }

    public function openPeopleCount(): int
    {
        return Acolhimento::query()
            ->where('unidade_id', $this->context->unit()->id)
            ->whereNull('encerrado_em')
            ->distinct()
            ->count('crianca_id');
    }
}

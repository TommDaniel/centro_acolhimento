<?php

namespace App\Actions;

use App\Enums\AcolhimentoMovimentacaoTipo;
use App\Enums\AcolhimentoSituacao;
use App\Models\Acolhimento;
use App\Models\AcolhimentoMovimentacao;
use App\Models\Crianca;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\InstitutionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordAcolhimentoMovimentacao
{
    public function __construct(
        private AuditRecorder $audit,
        private InstitutionContext $context,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(
        Crianca $crianca,
        Acolhimento $acolhimento,
        array $attributes,
        User $actor,
    ): AcolhimentoMovimentacao {
        return DB::transaction(function () use ($crianca, $acolhimento, $attributes, $actor): AcolhimentoMovimentacao {
            $lockedActor = User::query()->whereKey($actor)->lockForUpdate()->first();

            if ($lockedActor === null
                || ! $lockedActor->hasApprovedAccess()
                || (int) $lockedActor->unidade_id !== $this->context->unit()->id) {
                throw new AuthorizationException;
            }

            $lockedEpisode = Acolhimento::query()
                ->whereKey($acolhimento)
                ->where('crianca_id', $crianca->id)
                ->where('unidade_id', $this->context->unit()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $crianca->organizacao_id !== $this->context->organization()->id) {
                throw new AuthorizationException;
            }

            Gate::forUser($lockedActor)->authorize('recordMovement', $lockedEpisode);

            $replayed = AcolhimentoMovimentacao::query()
                ->where('acolhimento_id', $lockedEpisode->id)
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->first();

            if ($replayed !== null) {
                $this->assertSameReplay($replayed, $attributes, $lockedActor);

                return $replayed;
            }

            if ($lockedEpisode->encerrado_em !== null) {
                throw ValidationException::withMessages([
                    'tipo' => 'Este episódio já foi encerrado.',
                ]);
            }

            $latest = AcolhimentoMovimentacao::query()
                ->where('acolhimento_id', $lockedEpisode->id)
                ->orderByDesc('efetiva_em')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->firstOrFail();
            $type = AcolhimentoMovimentacaoTipo::from($attributes['tipo']);

            $this->assertTransitionIsAllowed($latest->situacao_resultante, $type);

            if ($attributes['efetiva_em']->lessThan($latest->efetiva_em)) {
                throw ValidationException::withMessages([
                    'efetiva_em' => 'A data e hora não pode ser anterior à última movimentação.',
                ]);
            }

            $movement = new AcolhimentoMovimentacao($attributes);
            $movement->acolhimento_id = $lockedEpisode->id;
            $movement->situacao_resultante = $type->situacaoResultante();
            $movement->created_by = $lockedActor->id;
            $movement->recorded_at = now('UTC');
            $movement->save();

            if ($type === AcolhimentoMovimentacaoTipo::Desacolhimento) {
                $lockedEpisode->encerrado_em = $movement->efetiva_em;
                $lockedEpisode->encerrado_por_movimentacao_id = $movement->id;
                $lockedEpisode->save();
            }

            $changedFields = collect([
                'tipo', 'efetiva_em', 'motivo', 'fundamento', 'local_destino', 'observacao',
            ])->filter(fn (string $field): bool => $field === 'tipo'
                || $field === 'efetiva_em'
                || filled($attributes[$field] ?? null))
                ->values()
                ->all();

            $this->audit->record(
                'acolhimento.movement.recorded',
                'success',
                $lockedActor,
                $lockedEpisode,
                $changedFields,
            );

            return $movement->load('criador');
        }, 3);
    }

    private function assertTransitionIsAllowed(
        AcolhimentoSituacao $current,
        AcolhimentoMovimentacaoTipo $requested,
    ): void {
        $allowed = match ($current) {
            AcolhimentoSituacao::NaUnidade => [
                AcolhimentoMovimentacaoTipo::Evasao,
                AcolhimentoMovimentacaoTipo::Internacao,
                AcolhimentoMovimentacaoTipo::Desacolhimento,
            ],
            AcolhimentoSituacao::Evadido, AcolhimentoSituacao::Internado => [
                AcolhimentoMovimentacaoTipo::Retorno,
                AcolhimentoMovimentacaoTipo::Desacolhimento,
            ],
            AcolhimentoSituacao::Desacolhido => [],
        };

        if (! in_array($requested, $allowed, true)) {
            throw ValidationException::withMessages([
                'tipo' => 'A movimentação não é válida para a situação atual.',
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertSameReplay(
        AcolhimentoMovimentacao $existing,
        array $attributes,
        User $actor,
    ): void {
        $same = $existing->created_by === $actor->id
            && $existing->tipo->value === $attributes['tipo']
            && $existing->efetiva_em->equalTo($attributes['efetiva_em'])
            && $existing->motivo === ($attributes['motivo'] ?? null)
            && $existing->fundamento === ($attributes['fundamento'] ?? null)
            && $existing->local_destino === ($attributes['local_destino'] ?? null)
            && $existing->observacao === ($attributes['observacao'] ?? null);

        if (! $same) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Esta chave de repetição já foi usada com dados diferentes.',
            ]);
        }
    }
}

<?php

namespace App\Actions;

use App\Enums\AcolhimentoMovimentacaoTipo;
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

class OpenAcolhimento
{
    public function __construct(
        private AuditRecorder $audit,
        private InstitutionContext $context,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Crianca $crianca, array $attributes, User $actor): Acolhimento
    {
        return DB::transaction(function () use ($crianca, $attributes, $actor): Acolhimento {
            $lockedActor = User::query()->whereKey($actor)->lockForUpdate()->first();

            if ($lockedActor === null
                || ! $lockedActor->hasApprovedAccess()
                || (int) $lockedActor->unidade_id !== $this->context->unit()->id) {
                throw new AuthorizationException;
            }

            Gate::forUser($lockedActor)->authorize('create', Acolhimento::class);

            $lockedChild = Crianca::query()
                ->whereKey($crianca)
                ->where('organizacao_id', $this->context->organization()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $replayed = Acolhimento::query()
                ->where('crianca_id', $lockedChild->id)
                ->where('unidade_id', $this->context->unit()->id)
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->first();

            if ($replayed !== null) {
                $this->assertSameReplay($replayed, $attributes, $lockedActor);

                return $replayed;
            }

            if (Acolhimento::query()
                ->where('crianca_id', $lockedChild->id)
                ->where('unidade_id', $this->context->unit()->id)
                ->whereNull('encerrado_em')
                ->exists()) {
                throw ValidationException::withMessages([
                    'ingresso_em' => 'Já existe um episódio de acolhimento aberto para esta pessoa.',
                ]);
            }

            $latestMovement = AcolhimentoMovimentacao::query()
                ->whereHas('acolhimento', fn ($query) => $query
                    ->where('crianca_id', $lockedChild->id)
                    ->where('unidade_id', $this->context->unit()->id))
                ->orderByDesc('efetiva_em')
                ->orderByDesc('id')
                ->first();

            if ($latestMovement !== null && $attributes['ingresso_em']->lessThan($latestMovement->efetiva_em)) {
                throw ValidationException::withMessages([
                    'ingresso_em' => 'A data e hora não pode ser anterior à última movimentação registrada.',
                ]);
            }

            $recordedAt = now('UTC');
            $acolhimento = new Acolhimento($attributes);
            $acolhimento->crianca_id = $lockedChild->id;
            $acolhimento->processo_numero_snapshot = $lockedChild->processo_numero;
            $acolhimento->vara_snapshot = $lockedChild->vara;
            $acolhimento->comarca_snapshot = $lockedChild->comarca;
            $acolhimento->created_by = $lockedActor->id;
            $acolhimento->recorded_at = $recordedAt;
            $acolhimento->save();

            $movimentacao = $acolhimento->movimentacoes()->make([
                'tipo' => AcolhimentoMovimentacaoTipo::Ingresso,
                'situacao_resultante' => AcolhimentoMovimentacaoTipo::Ingresso->situacaoResultante(),
                'efetiva_em' => $attributes['ingresso_em'],
                'motivo' => $attributes['motivo'],
                'fundamento' => $attributes['fundamento'] ?? null,
                'idempotency_key' => $attributes['idempotency_key'],
            ]);
            $movimentacao->created_by = $lockedActor->id;
            $movimentacao->recorded_at = $recordedAt;
            $movimentacao->save();

            $this->audit->record(
                'acolhimento.opened',
                'success',
                $lockedActor,
                $acolhimento,
                [
                    'ingresso_em', 'motivo', 'fundamento', 'origem_codigo',
                    'origem_complemento', 'orgao_condutor_codigo',
                    'orgao_condutor_complemento', 'pessoa_condutora',
                    'processo_numero_snapshot', 'vara_snapshot', 'comarca_snapshot',
                ],
            );

            return $acolhimento->load('ultimaMovimentacao.criador');
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    private function assertSameReplay(Acolhimento $existing, array $attributes, User $actor): void
    {
        $same = $existing->created_by === $actor->id
            && $existing->ingresso_em->equalTo($attributes['ingresso_em'])
            && $existing->motivo === $attributes['motivo']
            && $existing->fundamento === ($attributes['fundamento'] ?? null)
            && $existing->origem_codigo === $attributes['origem_codigo']
            && $existing->origem_complemento === ($attributes['origem_complemento'] ?? null)
            && $existing->orgao_condutor_codigo === $attributes['orgao_condutor_codigo']
            && $existing->orgao_condutor_complemento === ($attributes['orgao_condutor_complemento'] ?? null)
            && $existing->pessoa_condutora === $attributes['pessoa_condutora'];

        if (! $same) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Esta chave de repetição já foi usada com dados diferentes.',
            ]);
        }
    }
}

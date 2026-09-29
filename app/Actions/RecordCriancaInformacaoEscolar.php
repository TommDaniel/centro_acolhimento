<?php

namespace App\Actions;

use App\Models\Crianca;
use App\Models\CriancaInformacaoEscolar;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\CriancaInformacaoEscolarHistory;
use App\Support\InstitutionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordCriancaInformacaoEscolar
{
    /** @var list<string> */
    private const SNAPSHOT_FIELDS = [
        'situacao_codigo', 'situacao_complemento', 'escola_nome', 'rede_codigo',
        'rede_complemento', 'matricula', 'ano_serie', 'turma', 'turno_codigo',
        'turno_complemento', 'vigente_em', 'fonte_codigo', 'fonte_complemento',
    ];

    public function __construct(
        private AuditRecorder $audit,
        private InstitutionContext $context,
        private CriancaInformacaoEscolarHistory $history,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Crianca $crianca, array $attributes, User $actor): CriancaInformacaoEscolar
    {
        return DB::transaction(function () use ($crianca, $attributes, $actor): CriancaInformacaoEscolar {
            $lockedActor = User::query()->whereKey($actor)->lockForUpdate()->first();

            if ($lockedActor === null
                || ! $lockedActor->hasApprovedAccess()
                || (int) $lockedActor->unidade_id !== $this->context->unit()->id) {
                throw new AuthorizationException;
            }

            $lockedChild = Crianca::query()
                ->whereKey($crianca)
                ->where('organizacao_id', $this->context->organization()->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($lockedActor)->authorize('update', $lockedChild);

            $replayed = CriancaInformacaoEscolar::query()
                ->where('crianca_id', $lockedChild->id)
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->first();

            if ($replayed !== null) {
                $this->assertSameReplay($replayed, $attributes, $lockedActor);

                return $replayed;
            }

            $previous = $this->history->current($lockedChild);

            $version = new CriancaInformacaoEscolar($attributes);
            $version->crianca_id = $lockedChild->id;
            $version->versao_anterior_id = $previous?->id;
            $version->created_by = $lockedActor->id;
            $version->recorded_at = now('UTC');
            $version->save();

            $this->audit->record(
                'crianca.informacao_escolar.created',
                'success',
                $lockedActor,
                $lockedChild,
                self::SNAPSHOT_FIELDS,
            );

            return $version->load('criador:id,name');
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    private function assertSameReplay(
        CriancaInformacaoEscolar $existing,
        array $attributes,
        User $actor,
    ): void {
        $same = $existing->created_by === $actor->id;

        foreach (self::SNAPSHOT_FIELDS as $field) {
            $incoming = $attributes[$field] ?? null;
            $stored = $existing->getRawOriginal($field);

            if ((string) ($stored ?? '') !== (string) ($incoming ?? '')) {
                $same = false;
                break;
            }
        }

        if (! $same) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Esta chave de repetição já foi usada com dados diferentes.',
            ]);
        }
    }
}

<?php

namespace App\Actions;

use App\Models\Acolhimento;
use App\Models\Crianca;
use App\Models\Pia;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\InstitutionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatePia
{
    public function __construct(
        private AuditRecorder $audit,
        private InstitutionContext $context,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes, User $actor): Pia
    {
        return DB::transaction(function () use ($attributes, $actor): Pia {
            $lockedActor = User::query()->whereKey($actor)->lockForUpdate()->first();

            if ($lockedActor === null
                || ! $lockedActor->hasApprovedAccess()
                || (int) $lockedActor->unidade_id !== $this->context->unit()->id) {
                throw new AuthorizationException;
            }

            Gate::forUser($lockedActor)->authorize('create', Pia::class);

            $child = Crianca::query()
                ->whereKey($attributes['crianca_id'])
                ->where('organizacao_id', $this->context->organization()->id)
                ->lockForUpdate()
                ->firstOrFail();
            $episode = Acolhimento::query()
                ->where('crianca_id', $child->id)
                ->where('unidade_id', $this->context->unit()->id)
                ->whereNull('encerrado_em')
                ->lockForUpdate()
                ->first();

            $expectedEpisodeId = $attributes['expected_acolhimento_id'] === null
                ? null
                : (int) $attributes['expected_acolhimento_id'];

            if ($expectedEpisodeId !== $episode?->id) {
                throw ValidationException::withMessages([
                    'expected_acolhimento_id' => 'O episódio de acolhimento mudou. Atualize o formulário antes de registrar o PIA.',
                ]);
            }

            unset($attributes['expected_acolhimento_id']);

            $pia = new Pia($attributes);
            $pia->crianca_id = $child->id;
            $pia->acolhimento_id = $episode?->id;
            $pia->created_by = $lockedActor->id;
            $pia->setor_id = $lockedActor->setor_id;
            $pia->save();

            $this->audit->record(
                'pia.created',
                'success',
                $lockedActor,
                $pia,
                $episode === null ? [] : ['acolhimento_id'],
            );

            return $pia;
        }, 3);
    }
}

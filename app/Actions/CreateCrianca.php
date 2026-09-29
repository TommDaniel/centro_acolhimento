<?php

namespace App\Actions;

use App\Models\Crianca;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class CreateCrianca
{
    public function __construct(
        private AuditRecorder $audit,
        private RecordCriancaInformacaoEscolar $recordSchoolInformation,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes, User $actor, ?array $schoolInformation = null): Crianca
    {
        return DB::transaction(function () use ($attributes, $actor, $schoolInformation): Crianca {
            $crianca = new Crianca($attributes);
            $crianca->created_by = $actor->id;
            $crianca->save();

            $this->audit->record(
                'crianca.created',
                'success',
                $actor,
                $crianca,
                array_keys($attributes),
            );

            if ($schoolInformation !== null) {
                $this->recordSchoolInformation->handle($crianca, $schoolInformation, $actor);
            }

            return $crianca;
        });
    }
}

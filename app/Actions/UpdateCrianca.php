<?php

namespace App\Actions;

use App\Models\Crianca;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class UpdateCrianca
{
    public function __construct(private AuditRecorder $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Crianca $crianca, array $attributes, User $actor): Crianca
    {
        return DB::transaction(function () use ($crianca, $attributes, $actor): Crianca {
            $crianca->fill($attributes);
            $crianca->updated_by = $actor->id;

            $changedFields = array_keys($crianca->getDirty());
            $crianca->save();

            $this->audit->record(
                'crianca.updated',
                'success',
                $actor,
                $crianca,
                $changedFields,
            );

            return $crianca;
        });
    }
}

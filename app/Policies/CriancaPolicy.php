<?php

namespace App\Policies;

use App\Models\Crianca;
use App\Models\User;

class CriancaPolicy extends BaseAssistentialPolicy
{
    public function viewPortrait(User $user, Crianca $crianca): bool
    {
        return $user->hasApprovedAccess()
            && $crianca->organizacao_id !== null
            && $user->unidade()
                ->where('organizacao_id', $crianca->organizacao_id)
                ->exists();
    }
}

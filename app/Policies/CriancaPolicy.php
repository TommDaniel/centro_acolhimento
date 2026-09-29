<?php

namespace App\Policies;

use App\Models\Crianca;
use App\Models\User;

class CriancaPolicy extends BaseAssistentialPolicy
{
    public function view(User $user, ?Crianca $crianca = null): bool
    {
        return $crianca !== null && $this->belongsToUsersOrganization($user, $crianca);
    }

    public function update(User $user, ?Crianca $crianca = null): bool
    {
        return $crianca !== null && $this->belongsToUsersOrganization($user, $crianca);
    }

    public function viewPortrait(User $user, Crianca $crianca): bool
    {
        return $this->belongsToUsersOrganization($user, $crianca);
    }

    private function belongsToUsersOrganization(User $user, Crianca $crianca): bool
    {
        return $user->hasApprovedAccess()
            && $crianca->organizacao_id !== null
            && $user->unidade()
                ->where('organizacao_id', $crianca->organizacao_id)
                ->exists();
    }
}

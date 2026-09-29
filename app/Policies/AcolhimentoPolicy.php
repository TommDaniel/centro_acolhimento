<?php

namespace App\Policies;

use App\Models\Acolhimento;
use App\Models\User;

class AcolhimentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasApprovedAccess();
    }

    public function create(User $user): bool
    {
        return $user->hasApprovedAccess();
    }

    public function view(User $user, Acolhimento $acolhimento): bool
    {
        return $user->hasApprovedAccess()
            && (int) $user->unidade_id === (int) $acolhimento->unidade_id;
    }

    public function recordMovement(User $user, Acolhimento $acolhimento): bool
    {
        return $this->view($user, $acolhimento);
    }

    public function delete(User $user, Acolhimento $acolhimento): bool
    {
        return false;
    }
}

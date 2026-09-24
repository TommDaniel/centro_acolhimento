<?php

namespace App\Policies;

use App\Models\Setor;
use App\Models\User;

class SetorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasApprovedAccess();
    }

    public function view(User $user, Setor $setor): bool
    {
        return $user->hasApprovedAccess();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Setor $setor): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Setor $setor): bool
    {
        return false;
    }
}

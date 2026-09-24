<?php

namespace App\Policies;

use App\Models\User;

abstract class BaseAssistentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasApprovedAccess();
    }

    public function view(User $user): bool
    {
        return $user->hasApprovedAccess();
    }

    public function create(User $user): bool
    {
        return $user->hasApprovedAccess();
    }

    public function update(User $user): bool
    {
        return $user->hasApprovedAccess();
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function download(User $user): bool
    {
        return $user->hasApprovedAccess();
    }
}

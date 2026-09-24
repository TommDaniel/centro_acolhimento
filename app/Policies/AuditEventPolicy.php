<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;

class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, AuditEvent $auditEvent): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditEvent $auditEvent): bool
    {
        return false;
    }

    public function delete(User $user, AuditEvent $auditEvent): bool
    {
        return false;
    }
}

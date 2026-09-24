<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Auth\Events\PasswordReset;

class AuditPasswordReset
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(PasswordReset $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->record('user.password_reset', 'success', subject: $user);
    }
}

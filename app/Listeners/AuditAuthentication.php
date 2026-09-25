<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

class AuditAuthentication
{
    public function __construct(private AuditRecorder $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->remember || request()->attributes->getBoolean('_auth_login_audited')) {
            return;
        }

        request()->attributes->set('_auth_login_audited', true);
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->record('auth.password_verified', 'success', $user, $user);
    }

    public function handleFailed(Failed $event): void
    {
        if (request()->attributes->getBoolean('_auth_failure_audited')) {
            return;
        }

        request()->attributes->set('_auth_failure_audited', true);
        $this->audit->record('auth.login_failed', 'denied');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /** Handle an incoming password reset link request. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        $normalizedEmail = Str::lower(trim($request->string('email')->toString()));
        $subject = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        $this->audit->record(
            'auth.password_reset_requested',
            $status === Password::RESET_LINK_SENT ? 'success' : 'denied',
            subject: $subject,
        );

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}

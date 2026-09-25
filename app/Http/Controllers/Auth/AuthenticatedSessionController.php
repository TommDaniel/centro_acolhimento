<?php

namespace App\Http\Controllers\Auth;

use App\Actions\EstablishPasswordOnlyLogin;
use App\Actions\StartMfaEnrollment;
use App\Exceptions\MfaSessionRevokedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TerminateAuthenticatedSession;
use App\Services\TerminateRestrictedMfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        LoginRequest $request,
        EstablishPasswordOnlyLogin $establishLogin,
        StartMfaEnrollment $startMfaEnrollment,
        TerminateRestrictedMfaSession $terminateSession,
    ): RedirectResponse {
        $request->authenticate();

        $authenticatedUser = $request->user();
        $expectedGeneration = $authenticatedUser->access_generation;
        $expectedPasswordHash = $authenticatedUser->getAuthPassword();

        try {
            $user = $establishLogin->handle(
                $authenticatedUser,
                $expectedGeneration,
                $expectedPasswordHash,
                $request->string('password')->toString(),
            );
        } catch (ValidationException $exception) {
            Auth::guard('web')->logout();

            throw $exception;
        }

        $request->session()->regenerate();
        Auth::guard('web')->setUser($user);

        $now = now('UTC')->getTimestamp();
        $request->session()->put([
            'auth.level' => 'password_only',
            'auth.password_verified_at' => $now,
            'auth.access_generation' => $user->access_generation,
            'auth.restricted_session_id' => Str::random(40),
        ]);

        if (! $user->hasActiveMfa()) {
            try {
                $enrollment = $startMfaEnrollment->handle($user, $user->access_generation);
            } catch (MfaSessionRevokedException) {
                return $terminateSession->revoked($request);
            }
            $request->session()->put('auth.mfa_enrollment_id', $enrollment->getKey());
        }

        return $user->hasActiveMfa()
            ? redirect()->route('mfa.challenge')
            : redirect()->route('mfa.enrollment');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(
        Request $request,
        TerminateAuthenticatedSession $terminateSession,
    ): RedirectResponse {
        $terminateSession->handle($request);

        return redirect('/');
    }
}

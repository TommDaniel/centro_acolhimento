<?php

namespace App\Http\Controllers\Auth;

use App\Actions\VerifyMfaChallenge;
use App\Exceptions\MfaCooldownException;
use App\Exceptions\MfaSessionRevokedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyMfaChallengeRequest;
use App\Services\TerminateRestrictedMfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MfaChallengeController extends Controller
{
    public function __construct(
        private VerifyMfaChallenge $verifyChallenge,
        private TerminateRestrictedMfaSession $terminateSession,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->hasActiveMfa()) {
            return redirect()->route('mfa.enrollment');
        }

        return Inertia::render('Auth/MfaChallenge');
    }

    public function verify(VerifyMfaChallengeRequest $request): RedirectResponse|HttpResponse
    {
        try {
            $user = $this->verifyChallenge->handle(
                $request->user(),
                $request->string('code')->toString(),
                (int) $request->session()->get('auth.access_generation'),
                (string) $request->session()->get('auth.level'),
            );
        } catch (MfaCooldownException $exception) {
            return $this->terminateSession->tooManyAttempts($request, $exception->retryAfterSeconds);
        } catch (MfaSessionRevokedException) {
            return $this->terminateSession->revoked($request);
        }

        $request->session()->regenerate();
        $now = now('UTC')->getTimestamp();
        $request->session()->put([
            'auth.level' => 'mfa_verified',
            'auth.access_generation' => $user->access_generation,
            'auth.mfa_issued_at' => $now,
            'auth.mfa_last_activity_at' => $now,
        ]);
        $request->session()->forget('auth.password_verified_at');

        return redirect()->intended(route('dashboard', absolute: false));
    }
}

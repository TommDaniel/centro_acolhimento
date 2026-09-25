<?php

namespace App\Http\Controllers\Auth;

use App\Actions\ConfirmMfaEnrollment;
use App\Exceptions\MfaCooldownException;
use App\Exceptions\MfaSessionRevokedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmMfaEnrollmentRequest;
use App\Models\MfaEnrollment;
use App\Services\AuditRecorder;
use App\Services\TerminateRestrictedMfaSession;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MfaEnrollmentController extends Controller
{
    public function __construct(
        private ConfirmMfaEnrollment $confirmEnrollment,
        private TotpService $totp,
        private AuditRecorder $audit,
        private TerminateRestrictedMfaSession $terminateSession,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasActiveMfa()) {
            return redirect()->route('mfa.challenge');
        }

        $enrollment = MfaEnrollment::query()
            ->whereKey($request->session()->get('auth.mfa_enrollment_id'))
            ->whereBelongsTo($request->user())
            ->where('state', 'pending')
            ->first();

        abort_if($enrollment === null, 403, 'Este vínculo de autenticação não está mais disponível.');

        Inertia::encryptHistory();

        return Inertia::render('Auth/MfaEnroll', [
            'enrollmentId' => $enrollment->getKey(),
            'qrCodeSvg' => $this->totp->qrCodeSvg($request->user()->email, $enrollment->secret),
            'setupKey' => $enrollment->secret,
        ]);
    }

    public function confirm(ConfirmMfaEnrollmentRequest $request): RedirectResponse|HttpResponse
    {
        if ($request->integer('enrollment_id') !== $request->session()->get('auth.mfa_enrollment_id')) {
            $this->audit->record(
                'auth.mfa_enrollment_confirmed',
                'denied',
                $request->user(),
                $request->user(),
            );

            throw ValidationException::withMessages([
                'code' => 'Este vínculo não está mais disponível.',
            ]);
        }

        try {
            $user = $this->confirmEnrollment->handle(
                $request->user(),
                $request->integer('enrollment_id'),
                $request->string('code')->toString(),
                (int) $request->session()->get('auth.access_generation'),
                (string) $request->session()->get('auth.level'),
            );
        } catch (MfaCooldownException $exception) {
            return $this->terminateSession->tooManyAttempts($request, $exception->retryAfterSeconds);
        } catch (MfaSessionRevokedException) {
            return $this->terminateSession->revoked($request);
        }
        Auth::guard('web')->setUser($user);

        $request->session()->regenerate();
        $now = now('UTC')->getTimestamp();
        $request->session()->put([
            'auth.level' => 'mfa_verified',
            'auth.access_generation' => $user->access_generation,
            'auth.mfa_issued_at' => $now,
            'auth.mfa_last_activity_at' => $now,
        ]);
        $request->session()->forget('auth.password_verified_at');
        $request->session()->forget('auth.mfa_enrollment_id');
        Inertia::clearHistory();

        return redirect()->route('dashboard');
    }
}

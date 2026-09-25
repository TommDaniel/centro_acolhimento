<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\PasswordResetTokenService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    private const RESET_EMAIL_SESSION_KEY = 'auth.password_reset_email';

    private const RESET_TOKEN_SESSION_KEY = 'auth.password_reset_token';

    private const RESET_CAPTURED_AT_SESSION_KEY = 'auth.password_reset_captured_at';

    private const RESET_CONTEXT_TTL_SECONDS = 900;

    public function __construct(
        private AuditRecorder $audit,
        private PasswordResetTokenService $passwordResetTokens,
    ) {}

    /** Capture the single-use bearer token from a same-origin request body. */
    public function capture(Request $request): RedirectResponse
    {
        $token = $request->input('token');
        $email = $request->input('email');

        if (! is_string($token)
            || $token === ''
            || mb_strlen($token) > 512
            || ! is_string($email)
            || mb_strlen($email) > 255
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->invalidResetResponse($request);
        }

        $request->session()->regenerate();
        $request->session()->put([
            self::RESET_TOKEN_SESSION_KEY => $token,
            self::RESET_EMAIL_SESSION_KEY => $email,
            self::RESET_CAPTURED_AT_SESSION_KEY => now('UTC')->getTimestamp(),
        ]);

        return redirect()->route('password.reset');
    }

    /**
     * Display the password reset view without exposing the token to Inertia history.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $context = $this->capturedResetContext($request);

        if ($context === null) {
            if ($request->session()->has([
                self::RESET_TOKEN_SESSION_KEY,
                self::RESET_EMAIL_SESSION_KEY,
                self::RESET_CAPTURED_AT_SESSION_KEY,
            ])) {
                return $this->invalidResetResponse($request);
            }

            Inertia::encryptHistory();

            return Inertia::render('Auth/CaptureResetPassword');
        }

        Inertia::encryptHistory();

        return Inertia::render('Auth/ResetPassword', [
            'email' => $context['email'],
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $sessionHasToken = $request->session()->has(self::RESET_TOKEN_SESSION_KEY);
        $context = $sessionHasToken
            ? $this->capturedResetContext($request)
            : [
                'email' => $request->input('email'),
                'token' => $request->input('token'),
            ];
        $email = $context['email'] ?? null;
        $token = $context['token'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($token) || $token === '') {
            return $this->invalidResetResponse($request);
        }

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $passwordWasChanged = false;
        $status = Password::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $request->string('password')->toString(),
                'password_confirmation' => $request->string('password_confirmation')->toString(),
            ],
            function ($user) use ($request, $token, &$passwordWasChanged): void {
                $passwordWasChanged = DB::transaction(function () use ($user, $request, $token): bool {
                    $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

                    if (! $this->passwordResetTokens->consumeLocked($lockedUser, $token)) {
                        return false;
                    }

                    $lockedUser->forceFill([
                        'password' => Hash::make($request->password),
                        'remember_token' => Str::random(60),
                        'access_generation' => $lockedUser->access_generation + 1,
                    ])->save();

                    event(new PasswordReset($lockedUser));

                    return true;
                });
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status === Password::PASSWORD_RESET && $passwordWasChanged) {
            $this->forgetResetContext($request);
            Inertia::clearHistory();

            return redirect()->route('login')->with('status', __($status));
        }

        return $this->invalidResetResponse($request);
    }

    private function invalidResetResponse(Request $request): RedirectResponse
    {
        $this->forgetResetContext($request);
        Inertia::clearHistory();
        $this->audit->record('auth.password_reset_failed', 'denied');

        return redirect()->route('password.request')->withErrors([
            'email' => 'Não foi possível redefinir a senha com as informações fornecidas. Solicite um novo link.',
        ]);
    }

    /** @return array{email: string, token: string}|null */
    private function capturedResetContext(Request $request): ?array
    {
        $email = $request->session()->get(self::RESET_EMAIL_SESSION_KEY);
        $token = $request->session()->get(self::RESET_TOKEN_SESSION_KEY);
        $capturedAt = $request->session()->get(self::RESET_CAPTURED_AT_SESSION_KEY);
        $isCurrent = is_int($capturedAt)
            && now('UTC')->getTimestamp() - $capturedAt <= self::RESET_CONTEXT_TTL_SECONDS;

        if (! $isCurrent || ! is_string($email) || $email === '' || ! is_string($token) || $token === '') {
            return null;
        }

        return ['email' => $email, 'token' => $token];
    }

    private function forgetResetContext(Request $request): void
    {
        $request->session()->forget([
            self::RESET_EMAIL_SESSION_KEY,
            self::RESET_TOKEN_SESSION_KEY,
            self::RESET_CAPTURED_AT_SESSION_KEY,
        ]);
    }
}

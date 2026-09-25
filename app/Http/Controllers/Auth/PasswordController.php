<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditRecorder;
use App\Services\PasswordResetTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function __construct(
        private AuditRecorder $audit,
        private PasswordResetTokenService $passwordResetTokens,
    ) {}

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $expectedGeneration = $request->session()->get('auth.access_generation');

        $user = DB::transaction(function () use ($request, $validated, $expectedGeneration) {
            $lockedUser = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->getKey());

            if (! is_int($expectedGeneration)
                || $lockedUser->access_generation !== $expectedGeneration
                || ! Hash::check($validated['current_password'], $lockedUser->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'current_password' => 'A sessão ou a senha atual não é mais válida.',
                ]);
            }

            $lockedUser->forceFill([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
                'access_generation' => $lockedUser->access_generation + 1,
            ])->save();
            $this->passwordResetTokens->revokeLocked($lockedUser);
            $this->audit->record('user.password_changed', 'success', $lockedUser, $lockedUser);

            return $lockedUser;
        });

        Auth::guard('web')->setUser($user);
        $request->session()->regenerate();
        $request->session()->put('auth.access_generation', $user->access_generation);
        $request->session()->put('password_hash_web', $user->getAuthPassword());

        return back();
    }
}

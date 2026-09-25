<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaEnrollmentController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('reset-password', [NewPasswordController::class, 'create'])
        ->middleware('sensitive.no-store')
        ->name('password.reset');

    Route::post('reset-password/capture', [NewPasswordController::class, 'capture'])
        ->middleware(['sensitive.no-store', 'throttle:password-reset-capture'])
        ->name('password.reset.capture');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware(['sensitive.no-store', 'throttle:password-reset-confirm'])
        ->name('password.store');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware(['auth', 'auth.session', 'mfa.restricted', 'sensitive.no-store'])->group(function () {
    Route::get('mfa/enroll', [MfaEnrollmentController::class, 'show'])->name('mfa.enrollment');
    Route::post('mfa/enroll/confirm', [MfaEnrollmentController::class, 'confirm'])
        ->middleware('mfa.throttle')
        ->name('mfa.enrollment.confirm');
    Route::get('mfa/challenge', [MfaChallengeController::class, 'show'])->name('mfa.challenge');
    Route::post('mfa/challenge', [MfaChallengeController::class, 'verify'])
        ->middleware('mfa.throttle')
        ->name('mfa.challenge.verify');
});

Route::middleware(['auth', 'auth.session', 'mfa.verified', 'approved'])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

});

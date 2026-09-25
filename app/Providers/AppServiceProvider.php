<?php

namespace App\Providers;

use App\Models\Crianca;
use App\Models\Evento;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\Report;
use App\Models\Setor;
use App\Models\User;
use App\Models\VisitaTecnica;
use App\Observers\InstitutionContextObserver;
use App\Services\AuditRecorder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $fragment = http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], '', '&', PHP_QUERY_RFC3986);

            return url(route('password.reset', absolute: false))."#{$fragment}";
        });

        $passwordResetLimits = function (Request $request, string $scope, bool $clearResetSession): array {
            $rawEmail = $request->input('email', $request->session()->get('auth.password_reset_email'));
            $normalizedEmail = is_string($rawEmail) ? Str::lower(trim($rawEmail)) : 'invalid-input';
            $key = (string) config('app.key');
            $ipDigest = hash_hmac('sha256', (string) $request->ip(), $key);
            $emailDigest = hash_hmac('sha256', $normalizedEmail, $key);
            $rateLimitedResponse = function (Request $limitedRequest, array $headers) use ($clearResetSession): HttpResponse {
                if ($clearResetSession) {
                    $limitedRequest->session()->forget([
                        'auth.password_reset_email',
                        'auth.password_reset_token',
                        'auth.password_reset_captured_at',
                    ]);
                    Inertia::clearHistory();
                }

                app(AuditRecorder::class)->record('auth.password_reset_rate_limited', 'denied');

                return response('Muitas tentativas. Tente novamente mais tarde.', 429, [
                    ...$headers,
                    'Cache-Control' => 'private, no-store',
                    'Pragma' => 'no-cache',
                    'Referrer-Policy' => 'no-referrer',
                ]);
            };

            return [
                Limit::perMinute(10)->by("{$scope}|ip|{$ipDigest}")->response($rateLimitedResponse),
                Limit::perMinute(5)->by("{$scope}|email|{$emailDigest}")->response($rateLimitedResponse),
            ];
        };

        RateLimiter::for(
            'password-reset',
            fn (Request $request): array => $passwordResetLimits($request, 'password-reset-request', false),
        );
        RateLimiter::for(
            'password-reset-confirm',
            fn (Request $request): array => $passwordResetLimits($request, 'password-reset-confirm', true),
        );
        RateLimiter::for(
            'password-reset-capture',
            fn (Request $request): array => $passwordResetLimits($request, 'password-reset-capture', true),
        );

        foreach ([Crianca::class, User::class, Setor::class, Pia::class, VisitaTecnica::class, Report::class, Pertence::class, Evento::class] as $model) {
            $model::observe(InstitutionContextObserver::class);
        }

        Vite::prefetch(concurrency: 3);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}

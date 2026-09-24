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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Crianca::class, User::class, Setor::class, Pia::class, VisitaTecnica::class, Report::class, Pertence::class, Evento::class] as $model) {
            $model::observe(InstitutionContextObserver::class);
        }

        Vite::prefetch(concurrency: 3);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}

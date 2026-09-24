<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RejectClientInstitutionContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RejectClientInstitutionContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

// Exceção temporária para a demonstração sintética legada na Vercel.
// Ambientes PostgreSQL, inclusive produção futura, nunca entram neste bloco.
if (filter_var($_ENV['VERCEL'] ?? getenv('VERCEL'), FILTER_VALIDATE_BOOL)) {
    $app->useStoragePath('/tmp/storage');
    foreach ([
        '/tmp/storage',
        '/tmp/storage/app',
        '/tmp/storage/app/public',
        '/tmp/storage/framework',
        '/tmp/storage/framework/cache',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/views',
        '/tmp/storage/logs',
    ] as $dir) {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    $buildDatabase = $app->basePath('database/database.sqlite');
    $runtimeDatabase = '/tmp/database.sqlite';
    if (! file_exists($runtimeDatabase) && file_exists($buildDatabase)) {
        copy($buildDatabase, $runtimeDatabase);
    }

    foreach ([
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $runtimeDatabase,
        'DB_URL' => '',
    ] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

return $app;

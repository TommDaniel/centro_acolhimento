<?php

namespace Tests\Feature;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SessionBlockingTest extends TestCase
{
    private ?string $sessionFile = null;

    protected function tearDown(): void
    {
        if ($this->sessionFile !== null && is_file($this->sessionFile)) {
            unlink($this->sessionFile);
        }

        parent::tearDown();
    }

    public function test_global_session_blocking_has_bounded_wait_and_lease(): void
    {
        $this->assertTrue(config('session.block'));
        $this->assertSame('array', config('session.block_store'));
        $this->assertSame(300, config('session.block_lock_seconds'));
        $this->assertSame(10, config('session.block_wait_seconds'));
        $this->assertGreaterThan(120, config('session.block_lock_seconds'));
    }

    public function test_e2e_multi_worker_runtime_uses_the_shared_database_lock_store(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));

        $this->assertIsString($compose);
        $this->assertMatchesRegularExpression(
            '/app-e2e:.*?PHP_CLI_SERVER_WORKERS: 4.*?CACHE_STORE: database.*?SESSION_DRIVER: file.*?SESSION_BLOCK: "true".*?SESSION_BLOCK_STORE: database.*?SESSION_BLOCK_LOCK_SECONDS: 300.*?SESSION_BLOCK_WAIT_SECONDS: 10/s',
            $compose,
        );
    }

    public function test_session_lock_timeout_fails_closed_without_caching_or_sensitive_context(): void
    {
        $response = app(ExceptionHandler::class)->render(
            Request::create('/busca', 'POST'),
            new LockTimeoutException,
        );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('1', $response->headers->get('Retry-After'));
        $this->assertSame(
            'Não foi possível concluir a requisição com segurança. Tente novamente.',
            $response->getContent(),
        );
    }

    public function test_slow_session_writer_cannot_remove_a_handle_saved_by_a_concurrent_request(): void
    {
        $sessionId = Str::random(40);
        $this->sessionFile = storage_path('framework/sessions/'.$sessionId);
        $environment = $this->workerEnvironment();

        $setup = $this->process($this->setupWorker(), [$sessionId], $environment);
        $setup->run();

        $this->assertSame(0, $setup->getExitCode(), $setup->getErrorOutput());

        $startAt = microtime(true) + 0.75;
        $slowRequest = $this->process(
            $this->requestWorker(),
            ['slow', $sessionId, (string) $startAt],
            $environment,
        );
        $handleRequest = $this->process(
            $this->requestWorker(),
            ['handle', $sessionId, (string) ($startAt + 0.25)],
            $environment,
        );

        $slowRequest->start();
        $handleRequest->start();
        $slowRequest->wait();
        $handleRequest->wait();

        $this->assertSame(0, $slowRequest->getExitCode(), $slowRequest->getErrorOutput());
        $this->assertSame(0, $handleRequest->getExitCode(), $handleRequest->getErrorOutput());

        $inspection = $this->process($this->inspectionWorker(), [$sessionId], $environment);
        $inspection->run();

        $this->assertSame(0, $inspection->getExitCode(), $inspection->getErrorOutput());
        $state = json_decode($inspection->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($state['slow_writer_completed']);
        $this->assertSame(['opaque-test-handle'], $state['handles']);
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     */
    private function process(string $script, array $arguments, array $environment): Process
    {
        return (new Process(
            [PHP_BINARY, '-r', $script, '--', ...$arguments],
            base_path(),
            $environment,
        ))->setTimeout(15);
    }

    /** @return array<string, string> */
    private function workerEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => (string) config('app.key'),
            'CACHE_STORE' => 'database',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => 'centro_acolhimento_test',
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
            'DB_SSLMODE' => (string) config('database.connections.pgsql.sslmode'),
            'SESSION_BLOCK_LOCK_SECONDS' => '5',
            'SESSION_BLOCK_STORE' => 'database',
            'SESSION_BLOCK_WAIT_SECONDS' => '5',
            'SESSION_DRIVER' => 'file',
            'SESSION_ENCRYPT' => 'false',
        ];
    }

    private function setupWorker(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$session = $app->make('session')->driver();
$session->setId($argv[1]);
$session->start();
$session->put('synthetic_baseline', true);
$session->save();
PHP;
    }

    private function requestWorker(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$mode = $argv[1];
$sessionId = $argv[2];
$startAt = (float) $argv[3];
Illuminate\Support\Facades\Route::middleware(Illuminate\Session\Middleware\StartSession::class)
    ->get('/_session-blocking-regression', function (Illuminate\Http\Request $request) use ($mode) {
        if ($mode === 'slow') {
            usleep(1_500_000);
            $request->session()->put('synthetic_slow_writer_completed', true);
        } else {
            $handles = $request->session()->get('search.query_handles', []);
            $handles['opaque-test-handle'] = [
                'query' => 'Termo de concorrência inteiramente fictício',
                'created_at' => 1_799_999_999,
            ];
            $request->session()->put('search.query_handles', $handles);
        }

        return response()->json(['completed' => true]);
    });
while (microtime(true) < $startAt) {
    usleep(1000);
}
$request = Illuminate\Http\Request::create('/_session-blocking-regression', 'GET');
$request->cookies->set(config('session.cookie'), $sessionId);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, 'Unexpected status '.$response->getStatusCode());
    exit(2);
}
PHP;
    }

    private function inspectionWorker(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$session = $app->make('session')->driver();
$session->setId($argv[1]);
$session->start();
echo json_encode([
    'slow_writer_completed' => $session->get('synthetic_slow_writer_completed') === true,
    'handles' => array_keys($session->get('search.query_handles', [])),
], JSON_THROW_ON_ERROR);
PHP;
    }
}

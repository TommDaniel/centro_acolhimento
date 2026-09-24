<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\Unidade;
use App\Support\InstitutionContext;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class InstitutionContextConcurrencyTest extends TestCase
{
    public function test_two_real_processes_provision_the_empty_context_idempotently_without_sensitive_output(): void
    {
        if (getenv('RUN_INSTITUTION_CONCURRENCY_TEST') !== 'true') {
            $this->markTestSkipped('Executado separadamente contra PostgreSQL efêmero vazio.');
        }

        $this->assertDatabaseCount('organizacoes', 0);
        $this->assertDatabaseCount('unidades', 0);

        $logPath = storage_path('logs/laravel.log');
        $logOffset = is_file($logPath) ? filesize($logPath) : 0;
        $results = Process::concurrently(function (Pool $pool): void {
            $command = [PHP_BINARY, 'artisan', 'institution:provision-context'];

            $pool->as('first')->path(base_path())->timeout(60)->command($command);
            $pool->as('second')->path(base_path())->timeout(60)->command($command);
        });

        $this->assertTrue($results->successful());

        $configuredValues = array_values(app(InstitutionContext::class)->configuredValues());
        $sensitiveFragments = array_merge($configuredValues, [
            'pg_advisory_xact_lock',
            'insert into',
            'select *',
            'local_only_change_in_production',
        ]);

        foreach ($results->collect() as $result) {
            $combinedOutput = mb_strtolower($result->output().' '.$result->errorOutput());

            foreach ($sensitiveFragments as $fragment) {
                $this->assertStringNotContainsString(mb_strtolower($fragment), $combinedOutput);
            }
        }

        clearstatcache(true, $logPath);
        $newLogContent = is_file($logPath)
            ? (string) file_get_contents($logPath, false, null, $logOffset)
            : '';

        foreach ($sensitiveFragments as $fragment) {
            $this->assertStringNotContainsString(mb_strtolower($fragment), mb_strtolower($newLogContent));
        }

        $this->assertSame(1, Organizacao::query()->count());
        $this->assertSame(1, Unidade::query()->count());
    }
}

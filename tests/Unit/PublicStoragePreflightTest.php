<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 2).'/scripts/assert-private-storage.php';

class PublicStoragePreflightTest extends TestCase
{
    /** @var list<string> */
    private array $fixtureRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtureRoots as $fixtureRoot) {
            $this->deleteFixture($fixtureRoot);
        }

        parent::tearDown();
    }

    public function test_absent_public_storage_entry_is_allowed(): void
    {
        $fixtureRoot = $this->createFixtureRoot();

        $this->assertFalse(publicStorageEntryExists($fixtureRoot));
    }

    public function test_public_storage_directory_is_blocked(): void
    {
        $fixtureRoot = $this->createFixtureRoot();
        mkdir($fixtureRoot.'/public/storage');

        $this->assertTrue(publicStorageEntryExists($fixtureRoot));
    }

    public function test_public_storage_file_is_blocked(): void
    {
        $fixtureRoot = $this->createFixtureRoot();
        file_put_contents($fixtureRoot.'/public/storage', 'SYNTHETIC_PUBLIC_STORAGE_ENTRY');

        $this->assertTrue(publicStorageEntryExists($fixtureRoot));
    }

    public function test_public_storage_symlink_and_broken_symlink_are_blocked(): void
    {
        $targetRoot = $this->createFixtureRoot();
        $linkedRoot = $this->createFixtureRoot();
        $brokenRoot = $this->createFixtureRoot();
        mkdir($targetRoot.'/private-target');

        if (! @symlink($targetRoot.'/private-target', $linkedRoot.'/public/storage')) {
            throw new RuntimeException('O ambiente de teste precisa permitir symlinks para validar o preflight.');
        }

        if (! @symlink($targetRoot.'/missing-target', $brokenRoot.'/public/storage')) {
            throw new RuntimeException('O ambiente de teste precisa permitir symlinks quebrados para validar o preflight.');
        }

        $this->assertTrue(publicStorageEntryExists($linkedRoot));
        $this->assertTrue(publicStorageEntryExists($brokenRoot));
    }

    public function test_real_preflight_process_fails_closed_for_every_public_storage_entry_type(): void
    {
        $script = dirname(__DIR__, 2).'/scripts/assert-private-storage.php';
        $absentRoot = $this->createFixtureRoot();
        $allowed = new Process([PHP_BINARY, $script, $absentRoot]);
        $allowed->run();

        $this->assertSame(0, $allowed->getExitCode(), $allowed->getErrorOutput());

        $targetRoot = $this->createFixtureRoot();
        mkdir($targetRoot.'/private-target');

        $blockedRoots = [
            'directory' => $this->createFixtureRoot(),
            'file' => $this->createFixtureRoot(),
            'symlink' => $this->createFixtureRoot(),
            'broken-symlink' => $this->createFixtureRoot(),
        ];

        mkdir($blockedRoots['directory'].'/public/storage');
        file_put_contents($blockedRoots['file'].'/public/storage', 'SYNTHETIC_PUBLIC_STORAGE_ENTRY');

        if (! @symlink($targetRoot.'/private-target', $blockedRoots['symlink'].'/public/storage')) {
            throw new RuntimeException('O ambiente de teste precisa permitir symlinks para validar o preflight real.');
        }

        if (! @symlink($targetRoot.'/missing-target', $blockedRoots['broken-symlink'].'/public/storage')) {
            throw new RuntimeException('O ambiente de teste precisa permitir symlinks quebrados para validar o preflight real.');
        }

        foreach ($blockedRoots as $entryType => $fixtureRoot) {
            $blocked = new Process([PHP_BINARY, $script, $fixtureRoot]);
            $blocked->run();

            $this->assertNotSame(0, $blocked->getExitCode(), "O preflight aceitou {$entryType}.");
            $this->assertStringContainsString('Inicialização bloqueada', $blocked->getErrorOutput());
        }
    }

    public function test_all_official_demo_launchers_run_preflight_before_artisan_serve(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $compose = file_get_contents($projectRoot.'/docker-compose.yml');
        $composer = json_decode(
            file_get_contents($projectRoot.'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertIsString($compose);
        $this->assertMatchesRegularExpression(
            '/php scripts\/assert-private-storage\.php\s+&& exec php artisan serve/',
            $compose,
        );
        $this->assertMatchesRegularExpression(
            '/php scripts\/assert-private-storage\.php\s+&& php artisan e2e:reset-database\s+&& exec php artisan serve/',
            $compose,
        );

        $devScripts = $composer['scripts']['dev'];
        $preflightPosition = array_search('@php scripts/assert-private-storage.php', $devScripts, true);
        $launcherPosition = array_search(
            'npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" "php artisan serve" "php artisan queue:listen --tries=1 --timeout=0" "php artisan pail --timeout=0" "npm run dev" --names=server,queue,logs,vite --kill-others',
            $devScripts,
            true,
        );

        $this->assertIsInt($preflightPosition);
        $this->assertIsInt($launcherPosition);
        $this->assertLessThan($launcherPosition, $preflightPosition);
    }

    private function createFixtureRoot(): string
    {
        $fixtureRoot = sys_get_temp_dir().'/public-storage-preflight-'.bin2hex(random_bytes(8));
        mkdir($fixtureRoot.'/public', 0755, true);
        $this->fixtureRoots[] = $fixtureRoot;

        return $fixtureRoot;
    }

    private function deleteFixture(string $fixtureRoot): void
    {
        if (! is_dir($fixtureRoot)) {
            return;
        }

        $entries = scandir($fixtureRoot);
        if (! is_array($entries)) {
            return;
        }

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $path = $fixtureRoot.'/'.$entry;

            if (is_link($path) || is_file($path)) {
                unlink($path);

                continue;
            }

            if (is_dir($path)) {
                $this->deleteFixture($path);
            }
        }

        rmdir($fixtureRoot);
    }
}

<?php

namespace Tests\Feature;

use App\Actions\CreateCrianca;
use App\Actions\StorePrivatePortrait;
use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\CriancaDocumento;
use App\Models\Pia;
use App\Models\PiaAnexo;
use App\Models\User;
use App\Services\PrivatePortraitStorage;
use App\Support\InstitutionContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PrivatePortraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_valid_portrait_is_reencoded_under_an_opaque_private_context_path(): void
    {
        $user = User::factory()->create();
        $name = 'Acolhido Retrato Privado Fictício';

        $this->actingAsWithVerifiedMfa($user)
            ->post(route('criancas.store'), [
                'nome_completo' => $name,
                'foto' => UploadedFile::fake()->image('retrato-ficticio.png', 160, 120),
            ])
            ->assertSessionHasNoErrors();

        $child = Crianca::query()->where('nome_completo', $name)->sole();
        $path = $child->getRawOriginal('foto');

        $this->assertMatchesRegularExpression(
            '#\Aportraits/v1/org/[1-9][0-9]*/unit/[1-9][0-9]*/[0-9a-f-]{36}\.png\z#',
            $path,
        );
        $this->assertStringNotContainsString('acolhido', mb_strtolower($path));
        Storage::disk('local')->assertExists($path);

        $imageInfo = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertIsArray($imageInfo);
        $this->assertSame('image/png', $imageInfo['mime']);
        $this->assertNull($child->toArray()['foto'] ?? null);
    }

    public function test_all_canonical_jpeg_orientations_are_applied_to_asymmetric_pixels_and_exif_is_removed(): void
    {
        $expectations = [
            2 => [40, 60, ['green', 'red', 'yellow', 'blue']],
            3 => [40, 60, ['yellow', 'blue', 'green', 'red']],
            4 => [40, 60, ['blue', 'yellow', 'red', 'green']],
            5 => [60, 40, ['red', 'blue', 'green', 'yellow']],
            6 => [60, 40, ['blue', 'red', 'yellow', 'green']],
            7 => [60, 40, ['yellow', 'green', 'blue', 'red']],
            8 => [60, 40, ['green', 'yellow', 'red', 'blue']],
        ];

        foreach ($expectations as $orientation => [$width, $height, $corners]) {
            $path = app(StorePrivatePortrait::class)->handle($this->orientedSyntheticJpeg($orientation));
            $stored = Storage::disk('local')->get($path);
            $info = getimagesizefromstring($stored);
            $image = imagecreatefromstring($stored);

            $this->assertSame($width, $info[0], "largura EXIF {$orientation}");
            $this->assertSame($height, $info[1], "altura EXIF {$orientation}");
            $this->assertSame($corners, $this->cornerColorNames($image), "pixels EXIF {$orientation}");
            $this->assertStringNotContainsString('SYNTHETIC_EXIF_MARKER', $stored);
            $this->assertStringNotContainsString('Exif', $stored);

            imagedestroy($image);
        }
    }

    public function test_disguised_or_oversized_images_are_rejected_without_logging_payloads(): void
    {
        $user = User::factory()->create();
        Log::spy();

        $this->actingAsWithVerifiedMfa($user)
            ->post(route('criancas.store'), [
                'nome_completo' => 'Acolhido MIME Fictício',
                'foto' => UploadedFile::fake()->createWithContent(
                    'retrato-ficticio.jpg',
                    "%PDF-1.4\nSYNTHETIC_SECRET_PAYLOAD",
                ),
            ])
            ->assertSessionHasErrors('foto');

        $this->actingAsWithVerifiedMfa($user)
            ->post(route('criancas.store'), [
                'nome_completo' => 'Acolhido Dimensão Fictício',
                'foto' => UploadedFile::fake()->image('retrato-grande-ficticio.jpg', 4097, 64),
            ])
            ->assertSessionHasErrors('foto');

        $oversized = UploadedFile::fake()->image('retrato-pesado-ficticio.png', 64, 64);
        file_put_contents($oversized->getRealPath(), str_repeat('SYNTHETIC_PADDING', 300_000), FILE_APPEND);
        clearstatcache(true, $oversized->getRealPath());

        $this->actingAsWithVerifiedMfa($user)
            ->post(route('criancas.store'), [
                'nome_completo' => 'Acolhido Tamanho Fictício',
                'foto' => $oversized,
            ])
            ->assertSessionHasErrors('foto');

        $this->assertSame(0, Crianca::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('portraits'));
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_portrait_read_requires_session_mfa_and_approved_access(): void
    {
        $user = User::factory()->create();
        $inactive = User::factory()->inactive()->create();
        $child = $this->childWithPortrait($user, 'Acolhido Leitura Fictício');
        $url = route('criancas.portrait', $child);

        $this->app['auth']->guard()->logout();
        $this->flushSession();
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->actingAsWithVerifiedMfa($inactive)->get($url)->assertForbidden();

        $deniedEvent = AuditEvent::query()
            ->where('action', 'access.denied.child_portrait.view')
            ->where('result', 'denied')
            ->sole();
        $this->assertSame($inactive->id, $deniedEvent->actor_id);
        $this->assertSame((string) $inactive->id, $deniedEvent->subject_id);

        $response = $this->actingAsWithVerifiedMfa($user)->get($url);
        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $event = AuditEvent::query()->where('action', 'child_portrait.view')->where('result', 'success')->sole();
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame((string) $child->id, $event->subject_id);
        $this->assertStringNotContainsString((string) $child->getRawOriginal('foto'), $event->toJson());

        $this->actingAsWithVerifiedMfa($user)
            ->get('/criancas/'.($child->id + 999).'/portrait')
            ->assertNotFound();
    }

    public function test_active_unauthorized_portrait_request_is_denied_by_policy_and_audited_without_target(): void
    {
        $user = User::factory()->create();
        $child = $this->childWithPortrait($user, 'Acolhido Negativa Ativa Fictício');
        $foreignChild = $child->replicate();
        $foreignChild->forceFill([
            'id' => $child->id,
            'organizacao_id' => $child->organizacao_id + 999,
        ]);
        $foreignChild->exists = true;

        Route::bind('crianca', fn (): Crianca => $foreignChild);

        $this->actingAsWithVerifiedMfa($user)
            ->get(route('criancas.portrait', $child))
            ->assertForbidden();

        $event = AuditEvent::query()
            ->where('action', 'access.denied.child_portrait.view')
            ->where('result', 'denied')
            ->sole();
        $this->assertSame($user->id, $event->actor_id);
        $this->assertNull($event->subject_type);
        $this->assertNull($event->subject_id);
        $this->assertStringNotContainsString($child->getRawOriginal('foto'), $event->toJson());
    }

    public function test_managed_jpeg_with_a_valid_header_but_failed_full_decode_is_not_served(): void
    {
        $user = User::factory()->create();
        $context = app(InstitutionContext::class);
        $path = sprintf(
            'portraits/v1/org/%d/unit/%d/%s.jpg',
            $context->organization()->getKey(),
            $context->unit()->getKey(),
            Str::uuid(),
        );
        $truncated = $this->truncatedJpegWithReadableHeader();
        Storage::disk('local')->put($path, $truncated);
        $child = Crianca::query()->create([
            'nome_completo' => 'Acolhido JPEG Truncado Fictício',
            'foto' => $path,
        ]);

        $this->assertIsArray(getimagesizefromstring($truncated));
        $this->assertFalse(@imagecreatefromstring($truncated));
        $this->assertNull(app(PrivatePortraitStorage::class)->read($path));

        $this->actingAsWithVerifiedMfa($user)
            ->get(route('criancas.portrait', $child))
            ->assertNotFound();

        $this->assertFalse(AuditEvent::query()
            ->where('action', 'child_portrait.view')
            ->where('result', 'success')
            ->exists());
    }

    public function test_portrait_route_ignores_swapped_query_identifiers(): void
    {
        $user = User::factory()->create();
        $first = $this->childWithPortrait($user, 'Primeiro Acolhido de Troca Fictício', 120, 80);
        $second = $this->childWithPortrait($user, 'Segundo Acolhido de Troca Fictício', 64, 48);

        $response = $this->actingAsWithVerifiedMfa($user)->get(
            route('criancas.portrait', $first).'?crianca_id='.$second->id,
        );

        $info = getimagesizefromstring($response->getContent());
        $this->assertSame(120, $info[0]);
        $this->assertSame(80, $info[1]);
    }

    public function test_inertia_exposes_only_authenticated_portrait_route_and_no_attachment_paths(): void
    {
        $user = User::factory()->create();
        $child = $this->childWithPortrait($user, 'Acolhido Props Fictício');
        $child->documentos()->create([
            'nome_original' => 'anexo-ficticio.pdf',
            'path' => 'documentos/legado-ficticio.pdf',
            'mime' => 'application/pdf',
            'tamanho' => 20,
            'uploaded_by' => $user->id,
        ]);

        $this->actingAsWithVerifiedMfa($user)
            ->get(route('criancas.show', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('crianca.foto_url', route('criancas.portrait', $child))
                ->missing('crianca.foto')
                ->missing('crianca.documentos'));
    }

    public function test_replacement_preserves_prior_private_object(): void
    {
        $user = User::factory()->create();
        $child = $this->childWithPortrait($user, 'Acolhido Retenção Fictício');
        $oldPath = $child->getRawOriginal('foto');

        $this->actingAsWithVerifiedMfa($user)
            ->put(route('criancas.update', $child), [
                'nome_completo' => $child->nome_completo,
                'foto' => UploadedFile::fake()->image('retrato-substituto-ficticio.png', 96, 96),
            ])
            ->assertSessionHasNoErrors();

        $newPath = $child->fresh()->getRawOriginal('foto');
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_failed_database_write_cleans_only_the_new_private_object(): void
    {
        $user = User::factory()->create();

        $this->mock(CreateCrianca::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Falha sintética de banco.'));
        });

        $this->actingAsWithVerifiedMfa($user)
            ->post(route('criancas.store'), [
                'nome_completo' => 'Acolhido Falha Parcial Fictício',
                'foto' => UploadedFile::fake()->image('retrato-falha-ficticio.jpg', 72, 72),
            ])
            ->assertStatus(500);

        $this->assertSame([], Storage::disk('local')->allFiles('portraits'));
    }

    public function test_pia_pdf_auto_includes_only_valid_managed_private_portraits_and_never_leaks_paths(): void
    {
        $user = User::factory()->create();
        $validChild = $this->childWithPortrait($user, 'Acolhido PDF Com Retrato Fictício');
        $withoutChild = Crianca::query()->create(['nome_completo' => 'Acolhido PDF Sem Retrato Fictício']);
        $portraitChild = $this->childWithPortrait($user, 'Acolhido PDF Retrato Extremo Fictício', 32, 4096);
        $landscapeChild = $this->childWithPortrait($user, 'Acolhido PDF Paisagem Extrema Fictício', 4096, 32);
        $corruptChild = Crianca::query()->create([
            'nome_completo' => 'Acolhido PDF Corrompido Fictício',
            'foto' => 'portraits/v1/org/1/unit/1/00000000-0000-4000-8000-000000000001.jpg',
        ]);
        Storage::disk('local')->put($corruptChild->getRawOriginal('foto'), 'CORRUPT_SYNTHETIC_BYTES');
        $legacyChild = Crianca::query()->create([
            'nome_completo' => 'Acolhido PDF Legado Fictício',
            'foto' => 'fotos/retrato-legado-ficticio.jpg',
        ]);

        $cases = [
            'with' => $validChild,
            'without' => $withoutChild,
            'extreme-portrait' => $portraitChild,
            'extreme-landscape' => $landscapeChild,
            'corrupt' => $corruptChild,
            'legacy' => $legacyChild,
        ];

        foreach ($cases as $fixtureName => $child) {
            $pia = Pia::query()->create([
                'crianca_id' => $child->id,
                'created_by' => $user->id,
            ]);

            $response = $this->actingAsWithVerifiedMfa($user)->get(route('pias.pdf', $pia));
            $response->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertStringNotContainsString('portraits/v1/', $response->getContent());
            $this->assertStringNotContainsString('fotos/retrato-legado', $response->getContent());
            $this->assertStringNotContainsString('CORRUPT_SYNTHETIC_BYTES', $response->getContent());
            $this->assertSame(
                1,
                preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()),
                "O retrato {$fixtureName} não pode criar páginas adicionais.",
            );

            if (getenv('WRITE_SYNTHETIC_PDF_FIXTURES') === 'true'
                && in_array($fixtureName, ['with', 'without', 'extreme-portrait', 'extreme-landscape'], true)) {
                $fixtureDirectory = storage_path('framework/testing/pdf');
                if (! is_dir($fixtureDirectory)) {
                    mkdir($fixtureDirectory, 0755, true);
                }
                file_put_contents(
                    $fixtureDirectory.'/pia-'.$fixtureName.'-portrait.pdf',
                    $response->getContent(),
                );
            }
        }
    }

    public function test_pia_pdf_bounds_portrait_width_and_height_while_preserving_aspect_ratio(): void
    {
        $user = User::factory()->create();
        $children = [
            $this->childWithPortrait($user, 'Acolhido Limite Vertical Fictício', 32, 4096),
            $this->childWithPortrait($user, 'Acolhido Limite Horizontal Fictício', 4096, 32),
        ];
        $captured = [];
        $renderer = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $renderer->shouldReceive('setPaper')->twice()->with('a4')->andReturnSelf();
        $renderer->shouldReceive('stream')->twice()->andReturnUsing(
            fn (string $filename) => response('%PDF-1.4 SYNTHETIC', 200, ['Content-Type' => 'application/pdf']),
        );
        Pdf::shouldReceive('loadView')->twice()->andReturnUsing(
            function (string $view, array $data) use (&$captured, $renderer) {
                $this->assertSame('pdf.pia', $view);
                $captured[] = $data['portrait'];

                return $renderer;
            },
        );

        foreach ($children as $child) {
            $pia = Pia::query()->create([
                'crianca_id' => $child->id,
                'created_by' => $user->id,
            ]);

            $this->actingAsWithVerifiedMfa($user)
                ->get(route('pias.pdf', $pia))
                ->assertOk();
        }

        foreach ($captured as $index => $portrait) {
            $naturalWidth = $index === 0 ? 32 : 4096;
            $naturalHeight = $index === 0 ? 4096 : 32;

            $this->assertLessThanOrEqual(88, $portrait['width']);
            $this->assertLessThanOrEqual(110, $portrait['height']);
            $this->assertEqualsWithDelta(
                $naturalWidth / $naturalHeight,
                $portrait['width'] / $portrait['height'],
                max(0.0001, ($naturalWidth / $naturalHeight) * 0.02),
            );
        }
    }

    public function test_failed_pia_pdf_render_does_not_record_success_audit(): void
    {
        $user = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Acolhido Falha PDF Fictício']);
        $pia = Pia::query()->create([
            'crianca_id' => $child->id,
            'created_by' => $user->id,
        ]);

        Pdf::shouldReceive('loadView')
            ->once()
            ->andThrow(new RuntimeException('Falha sintética de renderização.'));

        $this->actingAsWithVerifiedMfa($user)
            ->get(route('pias.pdf', $pia))
            ->assertStatus(500);

        $this->assertFalse(AuditEvent::query()
            ->where('action', 'pia.pdf_view')
            ->where('result', 'success')
            ->exists());
    }

    public function test_attachment_uploads_are_disabled_in_backend_and_paths_are_not_serialized(): void
    {
        $user = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Acolhido Anexos Fictício']);

        $this->actingAsWithVerifiedMfa($user)
            ->post(route('criancas.documentos.store', $child), [
                'anexos' => [UploadedFile::fake()->create('anexo-ficticio.pdf', 10, 'application/pdf')],
            ])
            ->assertStatus(423);

        $this->actingAsWithVerifiedMfa($user)
            ->post(route('pias.store'), [
                'crianca_id' => $child->id,
                'anexos' => [UploadedFile::fake()->create('anexo-pia-ficticio.pdf', 10, 'application/pdf')],
            ])
            ->assertStatus(423);

        $this->assertSame(0, CriancaDocumento::query()->count());
        $this->assertSame(0, PiaAnexo::query()->count());
        $this->assertSame(0, Pia::query()->count());
        $this->assertArrayNotHasKey('path', (new CriancaDocumento(['path' => 'legado/ficticio']))->toArray());
        $this->assertArrayNotHasKey('url', (new CriancaDocumento(['path' => 'legado/ficticio']))->toArray());
        $this->assertArrayNotHasKey('path', (new PiaAnexo(['path' => 'legado/ficticio']))->toArray());
        $this->assertArrayNotHasKey('url', (new PiaAnexo(['path' => 'legado/ficticio']))->toArray());
    }

    private function childWithPortrait(
        User $user,
        string $name,
        int $width = 80,
        int $height = 60,
    ): Crianca {
        $this->actingAsWithVerifiedMfa($user)
            ->post(route('criancas.store'), [
                'nome_completo' => $name,
                'foto' => UploadedFile::fake()->image('retrato-sintetico.jpg', $width, $height),
            ])
            ->assertSessionHasNoErrors();

        return Crianca::query()->where('nome_completo', $name)->sole();
    }

    private function orientedSyntheticJpeg(int $orientation): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'portrait-test-');
        $image = imagecreatetruecolor(40, 60);
        imagefilledrectangle($image, 0, 0, 19, 29, imagecolorallocate($image, 240, 20, 20));
        imagefilledrectangle($image, 20, 0, 39, 29, imagecolorallocate($image, 20, 240, 20));
        imagefilledrectangle($image, 0, 30, 19, 59, imagecolorallocate($image, 20, 20, 240));
        imagefilledrectangle($image, 20, 30, 39, 59, imagecolorallocate($image, 240, 240, 20));
        imagejpeg($image, $path, 100);
        imagedestroy($image);

        $jpeg = file_get_contents($path);
        $tiff = 'MM'.pack('n', 42).pack('N', 8).pack('n', 1)
            .pack('n', 0x0112).pack('n', 3).pack('N', 1).pack('n', $orientation)."\0\0"
            .pack('N', 0);
        $payload = "Exif\0\0".$tiff.'SYNTHETIC_EXIF_MARKER';
        $jpegWithExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
        file_put_contents($path, $jpegWithExif);

        return new UploadedFile($path, 'retrato-orientado-ficticio.jpg', 'image/jpeg', null, true);
    }

    /** @return list<'red'|'green'|'blue'|'yellow'> */
    private function cornerColorNames(\GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        return [
            $this->nearestColorName($image, 5, 5),
            $this->nearestColorName($image, $width - 6, 5),
            $this->nearestColorName($image, 5, $height - 6),
            $this->nearestColorName($image, $width - 6, $height - 6),
        ];
    }

    /** @return 'red'|'green'|'blue'|'yellow' */
    private function nearestColorName(\GdImage $image, int $x, int $y): string
    {
        $sample = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        $palette = [
            'red' => [240, 20, 20],
            'green' => [20, 240, 20],
            'blue' => [20, 20, 240],
            'yellow' => [240, 240, 20],
        ];
        $nearest = 'red';
        $nearestDistance = PHP_INT_MAX;

        foreach ($palette as $name => [$red, $green, $blue]) {
            $distance = ($sample['red'] - $red) ** 2
                + ($sample['green'] - $green) ** 2
                + ($sample['blue'] - $blue) ** 2;

            if ($distance < $nearestDistance) {
                $nearest = $name;
                $nearestDistance = $distance;
            }
        }

        return $nearest;
    }

    private function truncatedJpegWithReadableHeader(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'portrait-truncated-');
        $image = imagecreatetruecolor(160, 120);
        imagefilledrectangle($image, 0, 0, 159, 119, imagecolorallocate($image, 40, 90, 140));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        $jpeg = file_get_contents($path);
        unlink($path);
        $scanOffset = strpos($jpeg, "\xFF\xDA");

        if ($scanOffset === false) {
            throw new RuntimeException('JPEG sintético sem marcador de início de scan.');
        }

        $candidate = substr($jpeg, 0, $scanOffset);
        if (is_array(@getimagesizefromstring($candidate)) && @imagecreatefromstring($candidate) === false) {
            return $candidate;
        }

        for ($length = $scanOffset + 2; $length < strlen($jpeg); $length++) {
            $candidate = substr($jpeg, 0, $length);
            if (is_array(@getimagesizefromstring($candidate)) && @imagecreatefromstring($candidate) === false) {
                return $candidate;
            }
        }

        throw new RuntimeException('Não foi possível produzir um JPEG truncado sintético para o teste.');
    }
}

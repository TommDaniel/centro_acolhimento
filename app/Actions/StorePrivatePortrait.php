<?php

namespace App\Actions;

use App\Support\InstitutionContext;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StorePrivatePortrait
{
    public function __construct(private InstitutionContext $context) {}

    public function handle(UploadedFile $portrait): string
    {
        $mime = $portrait->getMimeType();
        $source = @file_get_contents($portrait->getRealPath());
        $image = is_string($source) ? @imagecreatefromstring($source) : false;

        if (! in_array($mime, ['image/jpeg', 'image/png'], true) || ! $image instanceof GdImage) {
            throw ValidationException::withMessages([
                'foto' => 'O retrato deve ser uma imagem JPEG ou PNG válida.',
            ]);
        }

        try {
            if ($mime === 'image/jpeg') {
                $image = $this->orientJpeg($image, $portrait->getRealPath());
            }

            $extension = $mime === 'image/png' ? 'png' : 'jpg';
            $path = sprintf(
                'portraits/v1/org/%d/unit/%d/%s.%s',
                $this->context->organization()->getKey(),
                $this->context->unit()->getKey(),
                Str::uuid(),
                $extension,
            );

            ob_start();
            $encoded = $mime === 'image/png'
                ? imagepng($image, null, 6)
                : imagejpeg($image, null, 90);
            $contents = ob_get_clean();

            if (! $encoded || ! is_string($contents) || $contents === '') {
                throw new RuntimeException('Não foi possível processar o retrato privado.');
            }

            if (! Storage::disk('local')->put($path, $contents)) {
                throw new RuntimeException('Não foi possível armazenar o retrato privado.');
            }

            return $path;
        } finally {
            imagedestroy($image);
        }
    }

    private function orientJpeg(GdImage $image, string $sourcePath): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $metadata = @exif_read_data($sourcePath, 'IFD0', true, false);
        $orientation = is_array($metadata) ? (int) ($metadata['IFD0']['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), 90),
            6 => $this->rotate($image, -90),
            7 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), -90),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        if (! imageflip($image, $mode)) {
            throw new RuntimeException('Não foi possível orientar o retrato privado.');
        }

        return $image;
    }

    private function rotate(GdImage $image, int $degrees): GdImage
    {
        $rotated = imagerotate($image, $degrees, 0);
        if (! $rotated instanceof GdImage) {
            throw new RuntimeException('Não foi possível orientar o retrato privado.');
        }

        imagedestroy($image);

        return $rotated;
    }
}

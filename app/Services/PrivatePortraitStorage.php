<?php

namespace App\Services;

use App\Support\InstitutionContext;
use GdImage;
use Illuminate\Support\Facades\Storage;

class PrivatePortraitStorage
{
    private const MANAGED_PATH_PATTERN = '#\Aportraits/v1/org/[1-9][0-9]*/unit/[1-9][0-9]*/[0-9a-f-]{36}\.(jpg|png)\z#';

    public function __construct(private InstitutionContext $context) {}

    /** @return array{contents: string, mime: 'image/jpeg'|'image/png', width: int, height: int}|null */
    public function read(?string $path): ?array
    {
        if (! $this->isManagedPathForActiveContext($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($path);
        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $info = @getimagesizefromstring($contents);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;

        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if (! $image instanceof GdImage) {
            return null;
        }

        try {
            return [
                'contents' => $contents,
                'mime' => $mime,
                'width' => imagesx($image),
                'height' => imagesy($image),
            ];
        } finally {
            imagedestroy($image);
        }
    }

    public function hasValid(?string $path): bool
    {
        return $this->read($path) !== null;
    }

    public function deleteFailedWrite(?string $path): void
    {
        if ($this->isManagedPathForActiveContext($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function isManagedPath(?string $path): bool
    {
        return is_string($path) && preg_match(self::MANAGED_PATH_PATTERN, $path) === 1;
    }

    private function isManagedPathForActiveContext(?string $path): bool
    {
        if (! $this->isManagedPath($path)) {
            return false;
        }

        $expectedPrefix = sprintf(
            'portraits/v1/org/%d/unit/%d/',
            $this->context->organization()->getKey(),
            $this->context->unit()->getKey(),
        );

        return str_starts_with($path, $expectedPrefix);
    }
}

<?php

namespace App\Services;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

class TotpService
{
    public function __construct(
        private TwoFactorAuthenticationProvider $provider,
        private Google2FA $engine,
    ) {}

    public function generateSecret(): string
    {
        return $this->provider->generateSecretKey();
    }

    public function qrCodeSvg(string $email, string $secret): string
    {
        $url = $this->provider->qrCodeUrl((string) config('app.name'), $email, $secret);
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(220, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(5, 83, 69))),
            new SvgImageBackEnd,
        )))->writeString($url);

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    public function verifyNewer(string $secret, string $code, ?int $lastAcceptedTimeStep): int|false
    {
        $result = $this->engine->verifyKeyNewer($secret, $code, $lastAcceptedTimeStep ?? -1, 1);

        return is_int($result) ? $result : false;
    }
}

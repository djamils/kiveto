<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * An inline SVG QR code (the otpauth:// key of an authenticator app),
 * rendered on the server: the key never goes through a third party.
 */
final class QrCode
{
    public static function svg(string $text, int $size = 184): string
    {
        $svg = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()))->writeString($text);

        return trim((string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg));
    }
}

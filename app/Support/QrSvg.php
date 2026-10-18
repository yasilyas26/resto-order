<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrSvg
{
    /** Hasilnya SVG (vektor), jadi tetap tajam saat dicetak sebesar apa pun. */
    public static function make(string $text, int $size = 320): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($text);
    }
}

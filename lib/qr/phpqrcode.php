<?php
// Minimal placeholder QR generator using GD to draw text (not full QR encoding)
class SimpleQR
{
    public static function png(string $text, string $outfile, int $size = 4)
    {
        $width = 200;
        $height = 200;
        $im = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $width, $height, $white);
        imagestring($im, 5, 10, 90, substr($text, 0, 20), $black);
        imagepng($im, $outfile);
        imagedestroy($im);
    }
}

<?php

namespace Tests\Feature\Concerns;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Imagens sintéticas para os testes de mídia: quatro quadrantes coloridos (topo esquerdo vermelho, topo direito verde, base
 * esquerda azul, base direita amarelo), o que permite provar rotação e espelhamento. EXIF com orientação, câmera, data e GPS
 * é inserido à mão, sem a extensão `exif`.
 */
trait BuildsImages
{
    private const COLORS = ['tl' => [220, 30, 30], 'tr' => [30, 200, 30], 'bl' => [30, 30, 220], 'br' => [230, 220, 30]];

    protected function pattern(int $w, int $h, bool $alpha = false): GdImage
    {
        $img = imagecreatetruecolor($w, $h);

        if ($alpha) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        $c = fn (array $rgb) => imagecolorallocate($img, ...$rgb);
        imagefilledrectangle($img, 0, 0, intdiv($w, 2) - 1, intdiv($h, 2) - 1, $c(self::COLORS['tl']));
        imagefilledrectangle($img, intdiv($w, 2), 0, $w - 1, intdiv($h, 2) - 1, $c(self::COLORS['tr']));
        imagefilledrectangle($img, 0, intdiv($h, 2), intdiv($w, 2) - 1, $h - 1, $c(self::COLORS['bl']));
        imagefilledrectangle($img, intdiv($w, 2), intdiv($h, 2), $w - 1, $h - 1, $c(self::COLORS['br']));

        return $img;
    }

    protected function encode(GdImage $img, string $type): string
    {
        ob_start();
        match ($type) {
            'png' => imagepng($img),
            'webp' => imagewebp($img, null, 90),
            default => imagejpeg($img, null, 90),
        };

        return (string) ob_get_clean();
    }

    protected function imageFile(string $type = 'jpg', int $w = 800, int $h = 600, string $name = 'foto'): UploadedFile
    {
        $kind = $type === 'jpg' ? 'jpeg' : $type;

        return UploadedFile::fake()->createWithContent($name.'.'.$type, $this->encode($this->pattern($w, $h), $kind));
    }

    /** JPEG com APP1 EXIF: orientação, fabricante, modelo, data e um IFD de GPS. */
    protected function jpegWithExif(int $orientation, int $w = 400, int $h = 300): string
    {
        $jpeg = $this->encode($this->pattern($w, $h), 'jpeg');
        $make = "CameraMaker\0";
        $model = "Model-S08\0";
        $date = "2026:11:26 10:00:00\0";
        $entries = 5;
        $dataStart = 8 + 2 + $entries * 12 + 4;
        $makeOff = $dataStart;
        $modelOff = $makeOff + strlen($make);
        $dateOff = $modelOff + strlen($model);
        $gpsOff = $dateOff + strlen($date);
        $entry = fn (int $tag, int $type, int $count, int $value) => pack('vvVV', $tag, $type, $count, $value);
        $ifd0 = pack('v', $entries)
            .$entry(0x010F, 2, strlen($make), $makeOff)
            .$entry(0x0110, 2, strlen($model), $modelOff)
            .$entry(0x0112, 3, 1, $orientation)
            .$entry(0x0132, 2, strlen($date), $dateOff)
            .$entry(0x8825, 4, 1, $gpsOff)
            .pack('V', 0);
        $gpsData = $gpsOff + 2 + 2 * 12 + 4;
        $gps = pack('v', 2).$entry(0x0002, 5, 3, $gpsData).$entry(0x0004, 5, 3, $gpsData + 24).pack('V', 0)
            .pack('V6', 12, 1, 34, 1, 5600, 100).pack('V6', 38, 1, 45, 1, 1200, 100);
        $app1 = "Exif\0\0".'II'.pack('v', 42).pack('V', 8).$ifd0.$make.$model.$date.$gps;

        return "\xFF\xD8\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
    }

    /** Cor esperada de cada quadrante visual de uma imagem 400x300 com a orientação EXIF dada (definição da especificação). */
    protected function expectedQuadrants(int $orientation, int $sw = 400, int $sh = 300): array
    {
        $source = function (int $x, int $y) use ($sw, $sh): string {
            return ($y < intdiv($sh, 2) ? 't' : 'b').($x < intdiv($sw, 2) ? 'l' : 'r');
        };
        $swap = $orientation >= 5;
        $ow = $swap ? $sh : $sw;
        $oh = $swap ? $sw : $sh;
        $map = [
            1 => fn ($x, $y) => [$x, $y],
            2 => fn ($x, $y) => [$sw - 1 - $x, $y],
            3 => fn ($x, $y) => [$sw - 1 - $x, $sh - 1 - $y],
            4 => fn ($x, $y) => [$x, $sh - 1 - $y],
            5 => fn ($x, $y) => [$y, $x],
            6 => fn ($x, $y) => [$y, $sh - 1 - $x],
            7 => fn ($x, $y) => [$sw - 1 - $y, $sh - 1 - $x],
            8 => fn ($x, $y) => [$sw - 1 - $y, $x],
        ][$orientation];
        $out = [];

        foreach (['tl' => [0.25, 0.25], 'tr' => [0.75, 0.25], 'bl' => [0.25, 0.75], 'br' => [0.75, 0.75]] as $name => [$fx, $fy]) {
            [$sx, $sy] = $map((int) ($fx * $ow), (int) ($fy * $oh));
            $out[$name] = $source($sx, $sy);
        }

        return ['width' => $ow, 'height' => $oh, 'quadrants' => $out];
    }

    /** Quadrante cujo RGB mais se aproxima da cor lida (tolera a perda do JPEG). */
    protected function nearestQuadrant(array $rgb): string
    {
        $best = 'tl';
        $distance = PHP_INT_MAX;

        foreach (self::COLORS as $name => $color) {
            $d = ($rgb[0] - $color[0]) ** 2 + ($rgb[1] - $color[1]) ** 2 + ($rgb[2] - $color[2]) ** 2;

            if ($d < $distance) {
                $distance = $d;
                $best = $name;
            }
        }

        return $best;
    }

    /** @return array{width: int, height: int, quadrants: array<string,string>} lido de uma imagem em bytes */
    protected function readQuadrants(string $bytes): array
    {
        $img = imagecreatefromstring($bytes);
        $w = imagesx($img);
        $h = imagesy($img);
        $out = [];

        foreach (['tl' => [0.25, 0.25], 'tr' => [0.75, 0.25], 'bl' => [0.25, 0.75], 'br' => [0.75, 0.75]] as $name => [$fx, $fy]) {
            $px = imagecolorat($img, (int) ($fx * $w), (int) ($fy * $h));
            $out[$name] = $this->nearestQuadrant([($px >> 16) & 255, ($px >> 8) & 255, $px & 255]);
        }

        return ['width' => $w, 'height' => $h, 'quadrants' => $out];
    }

    /** PNG cinza completo e válido de largura x altura, com poucos KB (linhas zeradas comprimidas): testa as barreiras sem alocar pixels. */
    protected function hugePng(int $width, int $height): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $ctx = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        $row = str_repeat("\0", $width + 1);
        $idat = '';

        for ($y = 0; $y < $height; $y++) {
            $idat .= deflate_add($ctx, $row, ZLIB_NO_FLUSH);
        }

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0))
            .$chunk('IDAT', $idat.deflate_add($ctx, '', ZLIB_FINISH)).$chunk('IEND', '');
    }

    /** Marcadores de metadados (APP1 a APP15, comentário) presentes antes da varredura de um JPEG; só o APP0 JFIF e a assinatura do GD são aceitos. @return list<string> */
    protected function jpegMetadataSegments(string $jpeg): array
    {
        $found = [];
        $i = 2;

        while ($i + 4 <= strlen($jpeg) && $jpeg[$i] === "\xFF") {
            $marker = ord($jpeg[$i + 1]);

            if ($marker === 0xDA) {
                break;
            }

            $length = unpack('n', substr($jpeg, $i + 2, 2))[1];

            // O comentário "CREATOR: gd-jpeg" é a assinatura do próprio GD (versão e qualidade), sem dado da câmera nem do usuário.
            $gdSignature = $marker === 0xFE && str_starts_with(substr($jpeg, $i + 4, $length - 2), 'CREATOR: gd-jpeg');

            if ((($marker >= 0xE1 && $marker <= 0xEF) || $marker === 0xFE) && ! $gdSignature) {
                $found[] = sprintf('0xFF%02X', $marker);
            }

            $i += 2 + $length;
        }

        return $found;
    }

    /** PNG animado e WebP animado mínimos, só com a estrutura que as barreiras reconhecem. */
    protected function animatedPng(): string
    {
        $png = $this->encode($this->pattern(40, 30), 'png');
        $pos = strpos($png, 'IDAT') - 4;
        $body = 'acTL'.pack('NN', 2, 0);

        return substr($png, 0, $pos).pack('N', 8).$body.pack('N', crc32($body)).substr($png, $pos);
    }

    protected function animatedWebp(): string
    {
        $body = 'WEBP'.'VP8X'.pack('V', 10).chr(0x02)."\0\0\0\0\0\0\0\0\0".'ANIM'.pack('V', 6)."\0\0\0\0\0\0";

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}

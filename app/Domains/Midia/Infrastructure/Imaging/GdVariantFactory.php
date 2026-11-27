<?php

declare(strict_types=1);

namespace App\Domains\Midia\Infrastructure\Imaging;

use App\Domains\Midia\Domain\Contracts\ImageVariantFactory;
use App\Domains\Midia\Domain\Enums\MediaPurpose;
use App\Domains\Midia\Domain\Exceptions\InvalidMedia;
use App\Domains\Midia\Domain\ValueObjects\ImageInspection;
use App\Domains\Midia\Domain\ValueObjects\ImageVariant;
use GdImage;

/**
 * Gera os derivados com o GD. Cada derivado é uma reencodificação: EXIF, GPS, miniaturas embutidas e perfis ICC não
 * passam. Nunca amplia. Decodifica uma vez, gera a maior variante, libera a imagem original e gera as menores a partir
 * da maior. A orientação EXIF (lida em PHP, sem a extensão `exif`) é aplicada sobre a variante já reduzida, nunca sobre a
 * imagem inteira, para não duplicar a memória da decodificação.
 */
final class GdVariantFactory implements ImageVariantFactory
{
    public function make(string $file, ImageInspection $inspection, MediaPurpose $purpose, callable $checkpoint): array
    {
        $source = $this->decode($file, $inspection->mime);

        $orientedWidth = $inspection->swapsAxes() ? $inspection->height : $inspection->width;
        $orientedHeight = $inspection->swapsAxes() ? $inspection->width : $inspection->height;
        $targets = $this->targets($purpose, $orientedWidth);

        $variants = [];
        $largest = null;

        foreach (array_reverse($targets, true) as $kind => $targetWidth) {
            $checkpoint();

            $targetHeight = max(1, (int) round($orientedHeight * $targetWidth / $orientedWidth));

            if ($largest === null) {
                $resized = $this->resize($source, $inspection->mime, $inspection->swapsAxes() ? $targetHeight : $targetWidth, $inspection->swapsAxes() ? $targetWidth : $targetHeight);
                unset($source);
                $largest = $this->orient($resized, $inspection->orientation, $inspection->mime);
                unset($resized);
                $image = $largest;
            } else {
                $image = $this->resize($largest, $inspection->mime, $targetWidth, $targetHeight);
            }

            $variants[] = new ImageVariant(
                kind: $kind,
                mime: $inspection->mime,
                width: imagesx($image),
                height: imagesy($image),
                contents: $this->encode($image, $inspection->mime),
            );

            if ($image !== $largest) {
                unset($image);
            }
        }

        unset($largest);

        return array_reverse($variants);
    }

    /**
     * Larguras alcançadas: as nominais do uso que não ampliam a imagem; se nenhuma cabe, a menor nominal limitada à largura
     * do original.
     *
     * @return array<string, int> tipo (`w640`) para largura real
     */
    private function targets(MediaPurpose $purpose, int $width): array
    {
        $targets = [];

        foreach ($purpose->widths() as $nominal) {
            if ($nominal <= $width) {
                $targets['w'.$nominal] = $nominal;
            }
        }

        if ($targets === []) {
            $targets['w'.$purpose->widths()[0]] = $width;
        }

        return $targets;
    }

    private function decode(string $file, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => @imagecreatefromwebp($file),
            default => @imagecreatefromjpeg($file),
        };

        if ($image === false) {
            throw InvalidMedia::file('Não foi possível decodificar a imagem. O arquivo pode estar corrompido.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    private function resize(GdImage $source, string $mime, int $width, int $height): GdImage
    {
        $target = $this->canvas($width, $height);
        $this->prepareAlpha($target, $mime);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $target;
    }

    private function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));

        if ($image === false) {
            throw InvalidMedia::file('Não foi possível preparar a imagem para processar.');
        }

        return $image;
    }

    private function prepareAlpha(GdImage $image, string $mime): void
    {
        if ($mime === 'image/jpeg') {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    }

    /** Aplica a orientação EXIF (1 a 8) à imagem já reduzida. */
    private function orient(GdImage $image, int $orientation, string $mime): GdImage
    {
        $transparent = (int) imagecolorallocatealpha($image, 0, 0, 0, 127);

        $rotate = function (GdImage $source, float $angle) use ($transparent, $mime): GdImage {
            $rotated = imagerotate($source, $angle, $transparent);

            if ($rotated === false) {
                throw InvalidMedia::file('Não foi possível girar a imagem.');
            }

            if ($mime !== 'image/jpeg') {
                imagealphablending($rotated, false);
                imagesavealpha($rotated, true);
            }

            return $rotated;
        };

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 3:
                return $rotate($image, 180);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);

                return $image;
            case 5:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $rotate($image, 90);
            case 6:
                return $rotate($image, -90);
            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $rotate($image, -90);
            case 8:
                return $rotate($image, 90);
            default:
                return $image;
        }
    }

    private function encode(GdImage $image, string $mime): string
    {
        ob_start();

        match ($mime) {
            'image/png' => imagepng($image, null, (int) config('media.quality.png_compression')),
            'image/webp' => imagewebp($image, null, (int) config('media.quality.webp')),
            default => imagejpeg($image, null, (int) config('media.quality.jpeg')),
        };

        return (string) ob_get_clean();
    }
}

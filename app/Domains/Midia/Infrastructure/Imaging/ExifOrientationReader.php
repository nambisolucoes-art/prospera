<?php

declare(strict_types=1);

namespace App\Domains\Midia\Infrastructure\Imaging;

/**
 * Leitura mínima e limitada da orientação EXIF de um JPEG, em PHP puro (sem a extensão `exif`, PD2-13). Lê no máximo os
 * primeiros 64 KB, percorre os segmentos até o APP1 `Exif`, valida cada deslocamento contra o tamanho lido e devolve 1
 * quando algo está ausente, malformado ou fora do intervalo de 1 a 8. Nunca lê GPS nem qualquer outra etiqueta.
 */
final class ExifOrientationReader
{
    private const WINDOW = 65536;

    public function read(string $file): int
    {
        $data = file_get_contents($file, false, null, 0, self::WINDOW);

        if ($data === false || strlen($data) < 12 || ! str_starts_with($data, "\xFF\xD8")) {
            return 1;
        }

        $length = strlen($data);
        $offset = 2;

        while ($offset + 4 <= $length) {
            if ($data[$offset] !== "\xFF") {
                return 1;
            }

            $marker = ord($data[$offset + 1]);

            // Fim dos metadados: início da varredura, fim da imagem ou marcador sem tamanho.
            if ($marker === 0xDA || $marker === 0xD9 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                return 1;
            }

            $size = (ord($data[$offset + 2]) << 8) | ord($data[$offset + 3]);

            if ($size < 2) {
                return 1;
            }

            if ($marker === 0xE1 && substr($data, $offset + 4, 6) === "Exif\0\0") {
                return $this->tiff(substr($data, $offset + 10, max(0, min($size - 8, $length - $offset - 10))));
            }

            $offset += 2 + $size;
        }

        return 1;
    }

    private function tiff(string $tiff): int
    {
        $length = strlen($tiff);

        if ($length < 14) {
            return 1;
        }

        $order = substr($tiff, 0, 2);

        if ($order !== 'II' && $order !== 'MM') {
            return 1;
        }

        $little = $order === 'II';
        $short = fn (int $at): int => $little
            ? ord($tiff[$at]) | (ord($tiff[$at + 1]) << 8)
            : (ord($tiff[$at]) << 8) | ord($tiff[$at + 1]);
        $long = fn (int $at): int => $little
            ? $short($at) | ($short($at + 2) << 16)
            : ($short($at) << 16) | $short($at + 2);

        if ($short(2) !== 42) {
            return 1;
        }

        $ifd = $long(4);

        if ($ifd < 8 || $ifd + 2 > $length) {
            return 1;
        }

        $entries = min($short($ifd), 256);

        for ($index = 0; $index < $entries; $index++) {
            $entry = $ifd + 2 + $index * 12;

            if ($entry + 12 > $length) {
                return 1;
            }

            // Etiqueta 0x0112 (orientação), tipo SHORT, uma ocorrência.
            if ($short($entry) === 0x0112 && $short($entry + 2) === 3 && $long($entry + 4) === 1) {
                $orientation = $short($entry + 8);

                return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
            }
        }

        return 1;
    }
}

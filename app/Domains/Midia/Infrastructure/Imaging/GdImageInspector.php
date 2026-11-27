<?php

declare(strict_types=1);

namespace App\Domains\Midia\Infrastructure\Imaging;

use App\Domains\Midia\Domain\Contracts\ImageInspector;
use App\Domains\Midia\Domain\Exceptions\InvalidMedia;
use App\Domains\Midia\Domain\ValueObjects\ImageInspection;
use finfo;

/**
 * Barreiras anteriores à decodificação, na ordem da seção 13.1 do plano 002: 1) bytes; 2) tipo pelo conteúdo (assinatura
 * e `finfo`, nunca nome, extensão ou Content-Type), sem SVG, GIF nem imagem animada, e sem arquivo truncado; 3) dimensões
 * pelo cabeçalho (`getimagesize`, que não decodifica) e megapixels; 4) estimativa de memória contra o orçamento. Só
 * depois de todas passarem alguém decodifica a imagem.
 */
final class GdImageInspector implements ImageInspector
{
    public function __construct(
        private readonly ExifOrientationReader $orientation,
    ) {}

    public function inspect(string $file): ImageInspection
    {
        $bytes = is_file($file) ? (int) filesize($file) : 0;

        if ($bytes === 0) {
            throw InvalidMedia::file('O arquivo está vazio ou não pôde ser lido.');
        }

        $maxBytes = (int) config('media.limits.max_bytes');

        if ($bytes > $maxBytes) {
            throw InvalidMedia::file('O arquivo deve ter até '.intdiv($maxBytes, 1048576).' MB.');
        }

        $mime = $this->typeByContent($file);

        match ($mime) {
            'image/png' => $this->assertStaticPng($file),
            'image/webp' => $this->assertStaticWebp($file, $bytes),
            default => $this->assertCompleteJpeg($file, $bytes),
        };

        [$width, $height] = $this->dimensions($file, $mime);

        $limit = (int) config('media.limits.max_side');
        $maxPixels = (int) config('media.limits.max_megapixels') * 1_000_000;

        if ($width > $limit || $height > $limit) {
            throw InvalidMedia::file('A imagem tem '.$width.' x '.$height.' pixels. O lado máximo é '.$limit.' pixels.');
        }

        if ($width * $height > $maxPixels) {
            throw InvalidMedia::file(
                'A imagem tem '.number_format($width * $height / 1_000_000, 1, ',', '').' megapixels. O máximo é '
                .$this->effectiveMegapixels().' megapixels.',
            );
        }

        $estimate = $this->estimate($width, $height, $bytes);

        if ($estimate > $this->budget()) {
            throw InvalidMedia::file(
                'A imagem tem '.number_format($width * $height / 1_000_000, 1, ',', '').' megapixels e é grande demais para ser '
                .'processada neste servidor, que garante o processamento seguro de até '.$this->effectiveMegapixels().' megapixels. '
                .'Reduza as dimensões e envie de novo.',
            );
        }

        return new ImageInspection(
            mime: $mime,
            bytes: $bytes,
            width: $width,
            height: $height,
            sha256: (string) hash_file('sha256', $file),
            orientation: $mime === 'image/jpeg' ? $this->orientation->read($file) : 1,
            estimatedMemory: $estimate,
        );
    }

    /** Memória estimada da decodificação: pixels x bytes por pixel x fator de cópias, mais o arquivo e a base. */
    public function estimate(int $width, int $height, int $bytes): int
    {
        $pixels = $width * $height * (int) config('media.memory.bytes_per_pixel') * (float) config('media.memory.copies_factor');

        return (int) ceil($pixels) + $bytes + (int) config('media.memory.overhead_bytes');
    }

    /**
     * Megapixels que qualquer arquivo permitido (até o tamanho máximo) garante caber no orçamento de memória: é o limite
     * prático, aplicado sobre o teto nominal `max_megapixels`. Com `memory_limit` de 128 MB dá 18, não 20. Um arquivo
     * pequeno em bytes pode passar de 18 até cerca de 19,7 MP, porque a estimativa soma os bytes do arquivo, mas 18 é o
     * que a tela promete e a recusa informa. Fonte única: usada na mensagem de recusa e na tela de envio.
     */
    public function effectiveMegapixels(?int $budget = null): int
    {
        $room = ($budget ?? $this->budget()) - (int) config('media.memory.overhead_bytes') - (int) config('media.limits.max_bytes');
        $perPixel = (int) config('media.memory.bytes_per_pixel') * (float) config('media.memory.copies_factor');
        $practical = (int) floor(max(0, $room) / $perPixel / 1_000_000);

        return min((int) config('media.limits.max_megapixels'), $practical);
    }

    /** Fração do `memory_limit` real que o envio pode usar. Sem limite no PHP, vale o teto de 512 MB. */
    public function budget(): int
    {
        $limit = $this->bytesOf((string) ini_get('memory_limit'));
        $limit = $limit <= 0 ? 512 * 1048576 : $limit;

        return (int) floor($limit * (float) config('media.memory.budget_fraction'));
    }

    private function typeByContent(string $file): string
    {
        $head = (string) file_get_contents($file, false, null, 0, 512);
        $expected = null;

        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            $expected = 'image/jpeg';
        } elseif (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
            $expected = 'image/png';
        } elseif (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') {
            $expected = 'image/webp';
        } elseif (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
            throw InvalidMedia::file('GIF não é aceito. Envie JPEG, PNG ou WebP estático.');
        }

        if ($expected === null) {
            $text = ltrim($head, "\xEF\xBB\xBF \t\r\n");

            if (str_starts_with($text, '<')) {
                throw InvalidMedia::file('SVG e HTML não são aceitos. Envie JPEG, PNG ou WebP.');
            }

            throw InvalidMedia::file('O arquivo não é uma imagem JPEG, PNG ou WebP.');
        }

        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($file);

        if ($detected !== $expected) {
            throw InvalidMedia::file('O conteúdo do arquivo não corresponde ao tipo da imagem.');
        }

        return $expected;
    }

    private function assertCompleteJpeg(string $file, int $bytes): void
    {
        $tail = (string) file_get_contents($file, false, null, max(0, $bytes - 64), 64);
        $tail = rtrim($tail, "\0");

        if (! str_ends_with($tail, "\xFF\xD9")) {
            throw InvalidMedia::file('O arquivo está truncado ou corrompido.');
        }
    }

    private function assertStaticPng(string $file): void
    {
        $stream = $this->open($file);
        fseek($stream, 8);
        $ended = false;

        for ($chunks = 0; $chunks < 100000; $chunks++) {
            $header = fread($stream, 8);

            if ($header === false || strlen($header) < 8) {
                break;
            }

            $type = substr($header, 4, 4);
            $size = (int) (unpack('N', substr($header, 0, 4))[1] ?? 0);

            if ($type === 'acTL') {
                fclose($stream);
                throw InvalidMedia::file('PNG animado não é aceito. Envie uma imagem estática.');
            }

            if ($type === 'IEND') {
                $ended = true;
                break;
            }

            if (fseek($stream, $size + 4, SEEK_CUR) !== 0) {
                break;
            }
        }

        fclose($stream);

        if (! $ended) {
            throw InvalidMedia::file('O arquivo está truncado ou corrompido.');
        }
    }

    private function assertStaticWebp(string $file, int $bytes): void
    {
        $stream = $this->open($file);
        $riff = (string) fread($stream, 12);
        $declared = (int) (unpack('V', substr($riff, 4, 4))[1] ?? 0) + 8;

        if ($declared > $bytes) {
            fclose($stream);
            throw InvalidMedia::file('O arquivo está truncado ou corrompido.');
        }

        $offset = 12;

        while ($offset + 8 <= $declared) {
            fseek($stream, $offset);
            $header = (string) fread($stream, 8);

            if (strlen($header) < 8) {
                break;
            }

            $type = substr($header, 0, 4);
            $size = (int) (unpack('V', substr($header, 4, 4))[1] ?? 0);

            if ($type === 'ANIM' || $type === 'ANMF' || ($type === 'VP8X' && (ord((string) fread($stream, 1)) & 0x02) !== 0)) {
                fclose($stream);
                throw InvalidMedia::file('WebP animado não é aceito. Envie uma imagem estática.');
            }

            $offset += 8 + $size + ($size % 2);
        }

        fclose($stream);
    }

    /** @return array{0: int, 1: int} */
    private function dimensions(string $file, string $mime): array
    {
        $info = @getimagesize($file);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw InvalidMedia::file('Não foi possível ler as dimensões da imagem. O arquivo pode estar corrompido.');
        }

        $types = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];

        if ($info[2] !== $types[$mime]) {
            throw InvalidMedia::file('O conteúdo do arquivo não corresponde ao tipo da imagem.');
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /** @return resource */
    private function open(string $file)
    {
        $stream = fopen($file, 'rb');

        if ($stream === false) {
            throw InvalidMedia::file('O arquivo não pôde ser lido.');
        }

        return $stream;
    }

    private function bytesOf(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            'G' => $number * 1073741824,
            'M' => $number * 1048576,
            'K' => $number * 1024,
            default => $number,
        };
    }
}

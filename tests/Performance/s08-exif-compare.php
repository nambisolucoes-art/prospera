<?php

// S08 (PD2-13): comparação reproduzível do GD SEM correção de orientação contra o processamento atual (leitura mínima da
// orientação em PHP, sem a extensão `exif`), com as orientações EXIF 1 a 8 do corpus (s08-corpus.php, quadrantes
// vermelho, verde, azul e amarelo, 400x300, com EXIF de câmera, data e GPS). Sem banco e sem rede.
// Uso: php s08-exif-compare.php [pasta-do-corpus]      (sai com código 1 se alguma verificação falhar)
use App\Domains\Midia\Application\Actions\GenerateMediaVariantsAction;
use App\Domains\Midia\Domain\Contracts\ImageInspector;
use App\Domains\Midia\Domain\Enums\MediaPurpose;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$dir = rtrim($argv[1] ?? '/tmp/s08-corpus', '/');

const REF = ['R' => [255, 0, 0], 'G' => [0, 255, 0], 'B' => [0, 0, 255], 'Y' => [255, 255, 0]];
const SW = 400;
const SH = 300;

function nearest(int $px): string
{
    [$r, $g, $b] = [($px >> 16) & 255, ($px >> 8) & 255, $px & 255];
    $best = 'R';
    $min = PHP_INT_MAX;
    foreach (REF as $name => $c) {
        $d = ($r - $c[0]) ** 2 + ($g - $c[1]) ** 2 + ($b - $c[2]) ** 2;
        if ($d < $min) {
            [$min, $best] = [$d, $name];
        }
    }

    return $best;
}

/** Quadrantes TL TR BL BR lidos numa imagem. */
function quadrants(string $bytes): array
{
    $img = imagecreatefromstring($bytes);
    [$w, $h] = [imagesx($img), imagesy($img)];
    $out = '';
    foreach ([[0.25, 0.25], [0.75, 0.25], [0.25, 0.75], [0.75, 0.75]] as [$fx, $fy]) {
        $out .= nearest(imagecolorat($img, (int) ($fx * $w), (int) ($fy * $h)));
    }

    return [$w, $h, $out];
}

/** Definição da especificação EXIF: o que cada orientação manda exibir, a partir do "sensor" 400x300. */
function expected(int $o): array
{
    $source = fn (int $x, int $y): string => ($y < SH / 2 ? ['L' => 'T', 'R' => 'T'] : ['L' => 'B', 'R' => 'B'])['L'].($x < SW / 2 ? 'L' : 'R');
    $color = ['TL' => 'R', 'TR' => 'G', 'BL' => 'B', 'BR' => 'Y'];
    $swap = $o >= 5;
    [$ow, $oh] = $swap ? [SH, SW] : [SW, SH];
    $map = [
        1 => fn ($x, $y) => [$x, $y], 2 => fn ($x, $y) => [SW - 1 - $x, $y], 3 => fn ($x, $y) => [SW - 1 - $x, SH - 1 - $y],
        4 => fn ($x, $y) => [$x, SH - 1 - $y], 5 => fn ($x, $y) => [$y, $x], 6 => fn ($x, $y) => [$y, SH - 1 - $x],
        7 => fn ($x, $y) => [SW - 1 - $y, SH - 1 - $x], 8 => fn ($x, $y) => [SW - 1 - $y, $x],
    ][$o];
    $out = '';
    foreach ([[0.25, 0.25], [0.75, 0.25], [0.25, 0.75], [0.75, 0.75]] as [$fx, $fy]) {
        [$sx, $sy] = $map((int) ($fx * $ow), (int) ($fy * $oh));
        $out .= $color[$source($sx, $sy)];
    }

    return [$ow, $oh, $out];
}

/** Marcadores JPEG antes do início da varredura: quais segmentos de metadados sobraram. */
function metadata(string $jpeg): array
{
    $found = [];
    $i = 2;
    while ($i + 4 <= strlen($jpeg) && $jpeg[$i] === "\xFF") {
        $marker = ord($jpeg[$i + 1]);
        if ($marker === 0xDA) {
            break;
        }
        if ($marker === 0xE1) {
            $found[] = 'APP1';
        }
        if ($marker === 0xED) {
            $found[] = 'APP13';
        }
        $length = unpack('n', substr($jpeg, $i + 2, 2))[1];
        // Comentário que não seja a assinatura do próprio GD (versão e qualidade) também conta como metadado.
        if ($marker === 0xFE && ! str_starts_with(substr($jpeg, $i + 4, $length - 2), 'CREATOR: gd-jpeg')) {
            $found[] = 'COM';
        }
        $i += 2 + $length;
    }

    return [$found, str_contains($jpeg, 'Exif') || str_contains($jpeg, 'GPS') || str_contains($jpeg, 'CameraMaker') || str_contains($jpeg, 'Model-S08')];
}

/** Processamento SEM correção: decodifica e reduz como o GD faz sozinho (o GD não aplica a orientação EXIF). */
function baseline(string $file): string
{
    $src = imagecreatefromjpeg($file);
    $dst = imagecreatetruecolor(400, 300);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, 400, 300, imagesx($src), imagesy($src));
    ob_start();
    imagejpeg($dst, null, 82);

    return (string) ob_get_clean();
}

printf("PHP %s, GD %s, extensão exif: %s\n\n", PHP_VERSION, gd_info()['GD Version'], extension_loaded('exif') ? 'carregada' : 'ausente');
printf("%-3s %-9s | %-22s | %-30s | %-22s | %s\n", 'EXIF', 'esperado', 'GD sem correção', 'processamento atual (maior variante)', 'metadados nos derivados', 'tempo/pico');
$failed = 0;
$baselineWrong = 0;
for ($o = 1; $o <= 8; $o++) {
    $file = "$dir/exif-orientacao-$o.jpg";
    [$ew, $eh, $eq] = expected($o);
    [$bw, $bh, $bq] = quadrants(baseline($file));
    $baseOk = [$bw, $bh, $bq] === [$ew, $eh, $eq]; // 400x300 fixo: o GD não troca os eixos
    $baselineWrong += $baseOk ? 0 : 1;

    $start = hrtime(true);
    $inspection = $app->make(ImageInspector::class)->inspect($file);
    $variants = $app->make(GenerateMediaVariantsAction::class)->execute($file, $inspection, MediaPurpose::EmployeePhoto, (float) $start);
    $ms = (hrtime(true) - $start) / 1e6;
    usort($variants, fn ($a, $b) => $b->width <=> $a->width);
    [$cw, $ch, $cq] = quadrants($variants[0]->contents);
    // As variantes nunca ampliam: a largura pode ser menor que a do original, então se confere a proporção e o formato.
    $shape = fn (int $w, int $h): bool => ($w > $h) === ($ew > $eh) && abs($w / $h - $ew / $eh) < 0.02;
    $ok = $cq === $eq && $shape($cw, $ch);
    $detail = '';
    $clean = true;
    foreach ($variants as $variant) {
        [$vw, $vh, $vq] = quadrants($variant->contents);
        [$segments, $strings] = metadata($variant->contents);
        $ok = $ok && $vq === $eq && $shape($vw, $vh);
        $clean = $clean && $segments === [] && ! $strings;
    }
    $failed += ($ok && $clean) ? 0 : 1;
    printf("%-3d %-9s | %-22s | %-30s | %-22s | %.0f ms, %.0f MB\n", $o, "{$ew}x{$eh} $eq", "{$bw}x{$bh} $bq ".($baseOk ? 'OK' : 'ERRADO'),
        "{$cw}x{$ch} $cq ({$variants[0]->kind}) ".($ok ? 'OK' : 'ERRADO'), $clean ? 'sem APP1/APP13/COM, sem EXIF/GPS' : 'METADADO PRESENTE', $ms, memory_get_peak_usage(true) / 1048576);
}
printf("\nGD sem correção: %d de 8 orientações corretas (esperado: apenas a 1). Processamento atual: %d de 8 corretas e limpas.\n", 8 - $baselineWrong, 8 - $failed);
exit($failed === 0 && $baselineWrong === 7 ? 0 : 1);

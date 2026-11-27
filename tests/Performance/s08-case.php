<?php

// S08: processa UM arquivo do corpus como a requisição de envio faria (barreiras, variantes e codificação, sem banco) e
// imprime uma linha JSON com tempo, pico de memória e resultado. Cada caso roda num processo novo: o pico é por requisição.
// Uso: php s08-case.php <arquivo> <uso>
use App\Domains\Midia\Application\Actions\GenerateMediaVariantsAction;
use App\Domains\Midia\Domain\Contracts\ImageInspector;
use App\Domains\Midia\Domain\Enums\MediaPurpose;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$file, $purpose] = [$argv[1], MediaPurpose::from($argv[2] ?? 'gallery')];
$start = hrtime(true);
$out = ['file' => basename($file), 'bytes' => filesize($file)];

try {
    $inspection = $app->make(ImageInspector::class)->inspect($file);
    $out += ['w' => $inspection->width, 'h' => $inspection->height, 'estimate_mb' => round($inspection->estimatedMemory / 1048576, 1)];
    $variants = $app->make(GenerateMediaVariantsAction::class)->execute($file, $inspection, $purpose, (float) $start);
    $out += ['result' => 'aceito', 'variants' => count($variants), 'variant_bytes' => array_sum(array_map(fn ($v) => $v->bytes(), $variants)),
        'largest' => max(array_map(fn ($v) => $v->width, $variants)).'x'.max(array_map(fn ($v) => $v->height, $variants))];
} catch (ValidationException $e) {
    $out += ['result' => 'recusado', 'reason' => $e->errors()['file'][0] ?? 'validação'];
} catch (Throwable $e) {
    $out += ['result' => 'ERRO', 'reason' => get_class($e).': '.substr($e->getMessage(), 0, 80)];
}

$out['ms'] = round((hrtime(true) - $start) / 1e6, 1);
$out['peak_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";

<?php

// S08: orquestra as medições do corpus: cada caso N vezes, cada vez num processo PHP novo com o memory_limit real.
// Uso: php s08-bench.php <pasta-do-corpus> [repeticoes] [memory_limit]
$dir = $argv[1] ?? '/tmp/s08-corpus';
$rounds = (int) ($argv[2] ?? 8);
$limit = $argv[3] ?? '128M';
$cases = [
    ['jpeg-1600x1200.jpg', 'gallery'], ['jpeg-pequeno-300x200.jpg', 'gallery'], ['jpeg-12mp.jpg', 'gallery'], ['jpeg-12mp-progressivo.jpg', 'gallery'],
    ['jpeg-17mp.jpg', 'banner'], ['jpeg-18mp.jpg', 'banner'], ['jpeg-20mp.jpg', 'banner'], ['png-grande-8mp.png', 'gallery'], ['png-alpha.png', 'logo'], ['webp-12mp.webp', 'gallery'],
    ['exif-orientacao-6.jpg', 'employee_photo'],
    ['jpeg-21mp-acima-do-limite.jpg', 'gallery'], ['jpeg-48mp.jpg', 'gallery'], ['png-minusculo-enorme.png', 'gallery'],
    ['jpeg-truncado.jpg', 'gallery'], ['disfarcado-html.jpg', 'gallery'], ['svg-com-script.svg', 'gallery'], ['gif-animado.gif', 'gallery'],
    ['png-animado.png', 'gallery'], ['webp-animado.webp', 'gallery'], ['png-truncado.png', 'gallery'], ['webp-truncado.webp', 'gallery'], ['exif-malformado.jpg', 'gallery'],
];
printf("memory_limit=%s, %d repetições por caso\n", $limit, $rounds);
printf("%-32s %-14s %8s %8s %9s %9s %8s %9s  %s\n", 'arquivo', 'resultado', 'MP', 'bytes', 'média ms', 'p95 ms', 'pico MB', 'deriv. KB', 'motivo/variantes');
foreach ($cases as [$name, $purpose]) {
    $times = [];
    $peaks = [];
    $last = [];
    for ($i = 0; $i < $rounds; $i++) {
        $json = shell_exec('php -d memory_limit='.escapeshellarg($limit).' /app/tests/Performance/s08-case.php '.escapeshellarg("$dir/$name").' '.$purpose.' 2>/dev/null');
        $last = json_decode((string) trim((string) $json), true) ?: ['result' => 'FATAL (sem JSON)', 'ms' => 0, 'peak_mb' => 0];
        $times[] = $last['ms'];
        $peaks[] = $last['peak_mb'];
    }
    sort($times);
    $p95 = $times[(int) floor(count($times) * 0.95) - ($rounds >= 20 ? 0 : 0)] ?? end($times);
    printf("%-32s %-14s %8s %8.2f %9.1f %9.1f %8.1f %9s  %s\n", $name, $last['result'], isset($last['w']) ? round($last['w'] * $last['h'] / 1e6, 1) : '-', $last['bytes'] / 1048576,
        array_sum($times) / count($times), $p95, max($peaks), isset($last['variant_bytes']) ? round($last['variant_bytes'] / 1024) : '-',
        ($last['reason'] ?? '').(isset($last['variants']) ? $last['variants'].' variantes, maior '.$last['largest'] : ''));
}

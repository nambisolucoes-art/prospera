<?php

// S08: gera o corpus reproduzível de imagens (sem fotos reais, sem dados pessoais). Uso: php -d memory_limit=1G s08-corpus.php <pasta>
// Cada imagem tem um padrão assimétrico (quatro quadrantes coloridos e um marcador), o que permite provar rotação e espelhamento.
$dir = rtrim($argv[1] ?? '/tmp/s08-corpus', '/');
@mkdir($dir, 0777, true);

/** Quadrantes: topo esquerdo vermelho, topo direito verde, base esquerda azul, base direita amarelo, marcador branco no topo esquerdo. */
function pattern(int $w, int $h, bool $alpha = false): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    if ($alpha) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }
    $c = fn ($r, $g, $b) => imagecolorallocate($img, $r, $g, $b);
    imagefilledrectangle($img, 0, 0, intdiv($w, 2) - 1, intdiv($h, 2) - 1, $c(220, 30, 30));
    imagefilledrectangle($img, intdiv($w, 2), 0, $w - 1, intdiv($h, 2) - 1, $c(30, 200, 30));
    imagefilledrectangle($img, 0, intdiv($h, 2), intdiv($w, 2) - 1, $h - 1, $c(30, 30, 220));
    imagefilledrectangle($img, intdiv($w, 2), intdiv($h, 2), $w - 1, $h - 1, $c(230, 220, 30));
    imagefilledrectangle($img, intdiv($w, 20), intdiv($h, 20), intdiv($w, 20) * 3, intdiv($h, 20) * 3, $c(255, 255, 255));

    return $img;
}

/** Ruído suave por linhas, para que o arquivo comprimido tenha tamanho realista (não de cor chapada). */
function noisy(GdImage $img): void
{
    $w = imagesx($img);
    $h = imagesy($img);
    for ($y = 0; $y < $h; $y += 3) {
        $c = imagecolorallocate($img, ($y * 7) % 256, ($y * 13) % 256, ($y * 29) % 256);
        imageline($img, 0, $y, $w - 1, $y + ($y % 5), $c);
    }
}

function save(GdImage $img, string $path, string $type, int $q = 85, bool $progressive = false): void
{
    if ($progressive) {
        imageinterlace($img, true);
    }
    match ($type) {
        'png' => imagepng($img, $path, 6), 'webp' => imagewebp($img, $path, $q), default => imagejpeg($img, $path, $q)
    };
}

/** Insere um APP1 EXIF com orientação, câmera, data e um IFD de GPS (para provar a remoção nos derivados). */
function withExif(string $jpeg, int $orientation): string
{
    $le = fn (int $n, int $bytes) => substr(pack('V', $n), 0, $bytes);
    $make = "CameraMaker\0";
    $model = "Model-S08\0";
    $date = "2026:11:26 10:00:00\0";
    // IFD0 com 5 entradas: Make, Model, Orientation, DateTime, GPSInfo. Deslocamentos a partir do início do TIFF.
    $ifd0Offset = 8;
    $entries = 5;
    $ifdSize = 2 + $entries * 12 + 4;
    $dataStart = $ifd0Offset + $ifdSize;
    $makeOff = $dataStart;
    $modelOff = $makeOff + strlen($make);
    $dateOff = $modelOff + strlen($model);
    $gpsIfdOff = $dateOff + strlen($date);
    $entry = fn (int $tag, int $type, int $count, int $value) => pack('v', $tag).pack('v', $type).pack('V', $count).pack('V', $value);
    $ifd0 = pack('v', $entries)
        .$entry(0x010F, 2, strlen($make), $makeOff)
        .$entry(0x0110, 2, strlen($model), $modelOff)
        .$entry(0x0112, 3, 1, $orientation)
        .$entry(0x0132, 2, strlen($date), $dateOff)
        .$entry(0x8825, 4, 1, $gpsIfdOff)
        .pack('V', 0);
    // GPS: latitude e longitude (racionais) com valores reconhecíveis.
    $gpsEntries = 2;
    $gpsData = $gpsIfdOff + 2 + $gpsEntries * 12 + 4;
    $gps = pack('v', $gpsEntries)
        .$entry(0x0002, 5, 3, $gpsData)
        .$entry(0x0004, 5, 3, $gpsData + 24)
        .pack('V', 0)
        .pack('V6', 12, 1, 34, 1, 5600, 100)
        .pack('V6', 38, 1, 45, 1, 1200, 100);
    $tiff = 'II'.pack('v', 42).pack('V', $ifd0Offset).$ifd0.$make.$model.$date.$gps;
    $app1 = "Exif\0\0".$tiff;
    $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

    return "\xFF\xD8".$segment.substr($jpeg, 2);
}

/** PNG gigante em pixels e minúsculo em bytes: cinza 8 bits, IDAT comprimido em blocos (nada é alocado em memória). */
function hugePng(string $path, int $w, int $h): void
{
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $fh = fopen($path, 'wb');
    fwrite($fh, "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 0, 0, 0, 0)));
    $ctx = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
    $row = str_repeat("\0", $w + 1);
    $idat = '';
    for ($y = 0; $y < $h; $y++) {
        $idat .= deflate_add($ctx, $row, ZLIB_NO_FLUSH);
        if (strlen($idat) > 65536) {
            fwrite($fh, $chunk('IDAT', $idat));
            $idat = '';
        }
    }
    $idat .= deflate_add($ctx, '', ZLIB_FINISH);
    fwrite($fh, $chunk('IDAT', $idat).$chunk('IEND', ''));
    fclose($fh);
}

$make = function (string $name, int $w, int $h, string $type, bool $noise = true, bool $progressive = false, bool $alpha = false, int $quality = 85) use ($dir): void {
    $img = pattern($w, $h, $alpha);
    if ($noise) {
        noisy($img);
    }
    save($img, "$dir/$name", $type, $quality, $progressive);
    unset($img);
    printf("%-34s %6.1f MP %8.2f MB\n", $name, $w * $h / 1e6, filesize("$dir/$name") / 1048576);
};

$make('jpeg-12mp.jpg', 4000, 3000, 'jpeg');
$make('jpeg-12mp-progressivo.jpg', 4000, 3000, 'jpeg', true, true);
$make('jpeg-20mp.jpg', 5000, 4000, 'jpeg', true, false, false, 55);
$make('jpeg-1600x1200.jpg', 1600, 1200, 'jpeg');
$make('png-grande-8mp.png', 3200, 2500, 'png');
$make('png-alpha.png', 1200, 800, 'png', false, false, true);
$make('webp-12mp.webp', 4000, 3000, 'webp');
$make('jpeg-pequeno-300x200.jpg', 300, 200, 'jpeg', false);
$make('jpeg-17mp.jpg', 5000, 3400, 'jpeg', true, false, false, 60);
$make('jpeg-18mp.jpg', 5000, 3600, 'jpeg', true, false, false, 55);
$make('jpeg-21mp-acima-do-limite.jpg', 5250, 4000, 'jpeg', true, false, false, 55);
$make('jpeg-48mp.jpg', 8000, 6000, 'jpeg', false);
hugePng("$dir/png-minusculo-enorme.png", 30000, 30000);
printf("%-34s %6.1f MP %8.4f MB\n", 'png-minusculo-enorme.png', 900, filesize("$dir/png-minusculo-enorme.png") / 1048576);

// Malformados.
$ok = file_get_contents("$dir/jpeg-1600x1200.jpg");
file_put_contents("$dir/jpeg-truncado.jpg", substr($ok, 0, intdiv(strlen($ok), 2)));
file_put_contents("$dir/disfarcado-html.jpg", '<html><script>alert(1)</script></html>');
file_put_contents("$dir/svg-com-script.svg", '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>');
file_put_contents("$dir/svg-como-png.png", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
file_put_contents("$dir/gif-animado.gif", base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='));
$png = file_get_contents("$dir/png-alpha.png");
file_put_contents("$dir/png-truncado.png", substr($png, 0, strlen($png) - 20));
// PNG animado: insere acTL antes do primeiro IDAT.
$pos = strpos($png, 'IDAT') - 4;
$actl = pack('N', 8).'acTL'.pack('NN', 2, 0);
$actl .= pack('N', crc32('acTL'.pack('NN', 2, 0)));
file_put_contents("$dir/png-animado.png", substr($png, 0, $pos).$actl.substr($png, $pos));
// WebP animado: VP8X com o bit de animação, seguido de ANIM e ANMF mínimos.
$vp8x = 'VP8X'.pack('V', 10).chr(0x02)."\0\0\0".pack('V', 0).substr(pack('V', 0), 0, 3);
$riffBody = 'WEBP'.$vp8x.'ANIM'.pack('V', 6)."\0\0\0\0\0\0";
file_put_contents("$dir/webp-animado.webp", 'RIFF'.pack('V', strlen($riffBody)).$riffBody);
$webp = file_get_contents("$dir/webp-12mp.webp");
file_put_contents("$dir/webp-truncado.webp", substr($webp, 0, intdiv(strlen($webp), 2)));
// Extensão dupla e nome malicioso (só o nome muda: o conteúdo é um JPEG válido).
copy("$dir/jpeg-1600x1200.jpg", "$dir/foto.php.jpg");
copy("$dir/jpeg-1600x1200.jpg", "$dir/..%2f..%2fetc%2fpasswd.jpg");

// EXIF 1 a 8 (com GPS), em 400x300: o armazenado é o "sensor"; a orientação diz como exibir.
for ($o = 1; $o <= 8; $o++) {
    $img = pattern(400, 300);
    ob_start();
    imagejpeg($img, null, 90);
    $jpeg = (string) ob_get_clean();
    file_put_contents("$dir/exif-orientacao-$o.jpg", withExif($jpeg, $o));
}
// Um JPEG com EXIF malformado (tamanhos que apontam para fora do segmento).
$bad = "\xFF\xD8\xFF\xE1".pack('n', 40)."Exif\0\0"."II\x2A\x00\xFF\xFF\xFF\x7F".str_repeat("\xFF", 20).substr($ok, 2);
file_put_contents("$dir/exif-malformado.jpg", $bad);
echo "corpus em $dir: ".count(glob("$dir/*"))." arquivos\n";

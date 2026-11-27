<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Midia\Application\Actions\RemoveUnusedMediaAction;
use App\Domains\Midia\Domain\Contracts\ImageInspector;
use App\Domains\Midia\Domain\Contracts\ImageVariantFactory;
use App\Domains\Midia\Domain\Contracts\MediaPipelineProbe;
use App\Domains\Midia\Domain\Contracts\MediaStorage;
use App\Domains\Midia\Domain\Contracts\MediaUsageChecker;
use App\Domains\Midia\Domain\Exceptions\MediaInUse;
use App\Domains\Midia\Domain\Models\MediaAsset;
use App\Domains\Midia\Domain\Models\MediaVariant;
use App\Domains\Midia\Infrastructure\Storage\DiskMediaStorage;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\Feature\Concerns\BuildsImages;
use Tests\Support\FlakyMediaStorage;
use Tests\TestCase;

/**
 * S08 do plano 002: biblioteca de mídia (upload seguro, variantes, EXIF, atomicidade, isolamento, exclusão e resíduos).
 */
class MediaLibraryTest extends TestCase
{
    use BuildsCommercialScenario;
    use BuildsImages;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private Establishment $guanambi;

    private Establishment $caetite;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media-originals');
        Storage::fake('media-variants');

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->guanambi = $this->unit($this->roupas, 'Guanambi');
        $this->caetite = $this->unit($this->roupas, 'Caetité');
        $this->owner = $this->administrator($this->gartan);

        $this->actingAs($this->owner);
    }

    private function url(Company|Establishment|null $owner = null, string $suffix = ''): string
    {
        $owner ??= $this->roupas;

        return $owner instanceof Company
            ? '/conta/empresa/'.$owner->code.'/midia'.$suffix
            : '/conta/estabelecimento/'.$owner->code.'/midia'.$suffix;
    }

    private function upload(Company|Establishment|null $owner, UploadedFile $file, string $purpose = 'gallery', array $extra = [])
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->post($this->url($owner), ['file' => $file, 'purpose' => $purpose, ...$extra]);
    }

    /** @param list<string> $slugs */
    private function managerOf(Company|Establishment $node, array $slugs): User
    {
        static $sequence = 0;

        $role = $this->role($this->gartan, 'Gestor de mídia '.++$sequence, $slugs);
        $access = $node instanceof Company
            ? AccessNode::company($this->gartan->id, $node->id)
            : AccessNode::establishment($this->gartan->id, $node->company_id, $node->id);

        return $this->operator($this->gartan, [[$role, $access]]);
    }

    private function files(string $disk): int
    {
        return count(Storage::disk($disk)->allFiles());
    }

    private function made(string $type = 'jpg', ?Company $company = null): MediaAsset
    {
        $this->upload($company ?? $this->roupas, $this->imageFile($type))->assertNoContent();

        return MediaAsset::latest('id')->firstOrFail();
    }

    // Upload ---------------------------------------------------------------------------------------------------------

    public function test_jpeg_png_and_webp_are_accepted_optimized_and_never_expose_paths(): void
    {
        foreach (['jpg', 'png', 'webp'] as $type) {
            $this->upload($this->roupas, $this->imageFile($type, 1400, 1000, 'minha foto'), 'gallery', ['alt_text' => 'Fachada <b>da</b> loja'])
                ->assertNoContent();
        }

        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], MediaAsset::orderBy('id')->pluck('mime')->all());
        $asset = MediaAsset::first();
        $this->assertSame('Fachada da loja', $asset->alt_text);
        $this->assertSame(1400, $asset->width);
        $this->assertSame($this->gartan->id, $asset->account_id);
        $this->assertSame($this->roupas->id, $asset->company_id);
        $this->assertNull($asset->establishment_id);
        $this->assertSame($this->owner->id, $asset->uploaded_by);
        $this->assertSame(64, strlen($asset->sha256));

        // Galeria: larguras 320, 960 e 1600; 1400 não amplia, então só 320 e 960. Proporção mantida.
        $variants = $asset->variants()->orderBy('width')->get();
        $this->assertSame(['w320', 'w960'], $variants->pluck('kind')->all());
        $this->assertSame([320, 960], $variants->pluck('width')->all());
        $this->assertSame([229, 686], $variants->pluck('height')->all());

        // Original e derivados em discos privados, com caminhos opacos; nada vai para um disco público.
        $this->assertSame(3, $this->files('media-originals'));
        $this->assertSame(6, $this->files('media-variants'));
        $this->assertStringNotContainsString('minha foto', $asset->original_path);
        $this->assertMatchesRegularExpression('#^v/[0-9a-z]{2}/[0-9a-z]{26}\.jpg$#', $variants[0]->public_path);

        $json = $this->getJson($this->url())->assertOk()->json();
        $this->assertCount(3, $json['media']['data']);
        $this->assertStringNotContainsString('original_path', json_encode($json));
        $this->assertStringNotContainsString('public_path', json_encode($json));
        $this->assertStringNotContainsString($asset->original_path, json_encode($json));
        $this->assertSame('w320', $json['media']['data'][0]['thumb_kind']);
    }

    public function test_variants_never_enlarge_and_keep_the_alpha_of_png(): void
    {
        $this->upload($this->roupas, $this->imageFile('png', 100, 50), 'banner')->assertNoContent();
        $variant = MediaAsset::sole()->variants()->sole();

        // Menor que todas as larguras nominais: uma variante só, com a largura do original.
        $this->assertSame([100, 50], [$variant->width, $variant->height]);
        $this->assertSame('w640', $variant->kind);

        $alpha = imagecreatetruecolor(900, 600);
        imagealphablending($alpha, false);
        imagesavealpha($alpha, true);
        imagefill($alpha, 0, 0, imagecolorallocatealpha($alpha, 10, 20, 30, 127));
        $this->upload($this->roupas, UploadedFile::fake()->createWithContent('logo.png', $this->encode($alpha, 'png')), 'logo')->assertNoContent();
        $logo = MediaAsset::latest('id')->first();
        $biggest = $logo->variants()->orderByDesc('width')->first();
        $pixel = imagecolorat(imagecreatefromstring(Storage::disk('media-variants')->get($biggest->public_path)), 10, 10);
        $this->assertSame(127, ($pixel >> 24) & 127, 'O PNG manteve a transparência.');
    }

    /** @return array<string, array{0: callable(self): UploadedFile, 1: string}> */
    public static function refusedFiles(): array
    {
        return [
            'SVG com script' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>'), 'SVG'],
            'SVG disfarçado de PNG' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'), 'SVG'],
            'HTML disfarçado de JPEG' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.jpg', '<html><script>alert(1)</script></html>'), 'HTML'],
            'GIF' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.gif', base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==')), 'GIF'],
            'PNG animado' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.png', $t->animatedPng()), 'animado'],
            'WebP animado' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.webp', $t->animatedWebp()), 'animado'],
            'texto qualquer' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.jpg', str_repeat('texto ', 50)), 'não é uma imagem'],
            'arquivo vazio' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.jpg', ''), 'vazio'],
            'JPEG truncado' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.jpg', substr($t->encode($t->pattern(300, 200), 'jpeg'), 0, 400)), 'truncado'],
            'PNG truncado' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.png', substr($t->encode($t->pattern(300, 200), 'png'), 0, -30)), 'truncado'],
            'WebP truncado' => [fn (self $t) => UploadedFile::fake()->createWithContent('a.webp', substr($t->encode($t->pattern(300, 200), 'webp'), 0, 100)), 'truncado'],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function test_dangerous_invalid_and_truncated_files_are_refused_without_a_trace(callable $make, string $reason): void
    {
        $this->upload($this->roupas, $make($this))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'])
            ->assertJsonPath('errors.file.0', fn (string $message) => str_contains($message, $reason));

        $this->assertSame(0, MediaAsset::count());
        $this->assertSame(0, $this->files('media-originals') + $this->files('media-variants'));
    }

    public function test_the_content_decides_the_type_and_hostile_names_are_harmless(): void
    {
        // PNG com extensão .jpg e nomes com travessia de caminho, extensão dupla e Unicode: vale o conteúdo.
        foreach (['foto.php.jpg', '../../etc/passwd.jpg', '..%2f..%2fsegredo.jpg', 'ação-𝔘𝔫𝔦𝔠𝔬𝔡𝔢.jpg'] as $name) {
            $file = UploadedFile::fake()->createWithContent($name, $this->encode($this->pattern(400, 300), 'png'));
            $this->upload($this->roupas, $file)->assertNoContent();
        }

        $this->assertSame(['image/png'], MediaAsset::pluck('mime')->unique()->values()->all());

        foreach (MediaAsset::all() as $asset) {
            $this->assertMatchesRegularExpression('#^o/[0-9a-z]{2}/[0-9a-z]{26}$#', $asset->original_path);
        }

        // O corpo da requisição não escolhe o tipo: um purpose arbitrário é recusado.
        $this->upload($this->roupas, $this->imageFile(), 'documento')->assertUnprocessable()->assertJsonValidationErrors(['purpose']);
        $this->withHeaders(['Accept' => 'application/json'])->post($this->url(), ['purpose' => 'gallery'])
            ->assertUnprocessable()->assertJsonValidationErrors(['file']);
        $this->upload($this->roupas, $this->imageFile(), 'gallery', ['alt_text' => str_repeat('x', 301)])
            ->assertUnprocessable()->assertJsonValidationErrors(['alt_text']);
    }

    public function test_the_barriers_run_in_order_and_before_any_decoding(): void
    {
        // Se a decodificação fosse tentada, a fábrica falharia: nenhuma barreira deixa chegar até ela.
        $this->app->bind(ImageVariantFactory::class, fn () => new class implements ImageVariantFactory
        {
            public function make(string $file, $inspection, $purpose, callable $checkpoint): array
            {
                throw new RuntimeException('decodificou uma imagem que devia ter sido recusada');
            }
        });

        $file = fn (int $w, int $h) => UploadedFile::fake()->createWithContent('a.png', $this->encode($this->pattern($w, $h), 'png'));
        $bytes = strlen($this->encode($this->pattern(300, 200), 'png'));

        // 1) bytes
        config(['media.limits.max_bytes' => $bytes - 1]);
        $this->upload($this->roupas, $file(300, 200))->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, 'até'));

        // 2) tipo: antes de olhar as dimensões
        config(['media.limits.max_bytes' => 5242880, 'media.limits.max_side' => 100]);
        $this->upload($this->roupas, UploadedFile::fake()->createWithContent('a.png', '<svg/>'))
            ->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, 'SVG'));

        // 3) dimensões: lado e megapixels
        $this->upload($this->roupas, $file(300, 200))->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, 'lado máximo'));
        config(['media.limits.max_side' => 12000, 'media.limits.max_megapixels' => 0.01]);
        $this->upload($this->roupas, $file(300, 200))->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, 'megapixels'));

        // 4) estimativa de memória acima do orçamento (aceita pelas três primeiras barreiras)
        config(['media.limits.max_megapixels' => 20, 'media.memory.budget_fraction' => 0.001]);
        $this->upload($this->roupas, $file(300, 200))->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, 'grande demais'));

        $this->assertSame(0, MediaAsset::count() + $this->files('media-originals') + $this->files('media-variants'));
    }

    /**
     * O teto nominal é 20 MP, mas com `memory_limit` de 128 MB o limite prático é 18 MP. A conta fica em um só lugar
     * (`GdImageInspector::effectiveMegapixels`), vale antes de decodificar e aparece igual na recusa e na tela de envio.
     */
    public function test_the_practical_megapixel_limit_is_central_applied_before_decoding_and_told_to_the_user(): void
    {
        $previous = ini_get('memory_limit');
        ini_set('memory_limit', '128M');

        try {
            $inspector = app(ImageInspector::class);

            $this->assertSame(20, (int) config('media.limits.max_megapixels'));
            $this->assertSame(18, $inspector->effectiveMegapixels(), '128 MB garantem 18 MP, não 20');
            $this->assertSame(20, $inspector->effectiveMegapixels(256 * 1048576), 'com mais memória vale o teto nominal');
            $this->assertSame(0, $inspector->effectiveMegapixels(30 * 1048576), 'sem folga nenhuma imagem cabe');

            // A tela anuncia o mesmo número que a recusa usa.
            $this->getJson($this->url())->assertOk()->assertJsonPath('limits.max_megapixels', 18)->assertJsonPath('limits.nominal_megapixels', 20);

            // 16 MP cabe; 18 MP exatos (4242 x 4243) cabem; 20 MP e 21 MP não, e a recusa é antes de qualquer decodificação.
            $before = memory_get_peak_usage(true);
            $this->assertSame(16_000_000, $inspector->inspect($this->tempFile($this->hugePng(4000, 4000)))->width * 4000);
            $this->assertSame(4242, $inspector->inspect($this->tempFile($this->hugePng(4242, 4243)))->width);

            $this->upload($this->roupas, UploadedFile::fake()->createWithContent('vinte.png', $this->hugePng(5000, 4000)))
                ->assertUnprocessable()
                ->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, '20,0 megapixels') && str_contains($m, 'até 18 megapixels') && ! str_contains($m, '/'));
            $this->upload($this->roupas, UploadedFile::fake()->createWithContent('vinte-e-um.png', $this->hugePng(5000, 4200)))
                ->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, 'O máximo é 18 megapixels'));

            // Nenhuma decodificação: o pico de memória da recusa fica muito abaixo do que 20 MP exigiriam (80 MB).
            $this->assertLessThan(48 * 1048576, memory_get_peak_usage(true) - $before);
            $this->assertSame(0, MediaAsset::count() + $this->files('media-originals') + $this->files('media-variants'));
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 's08');
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    public function test_an_enormous_image_that_is_tiny_in_bytes_is_refused_by_the_header_alone(): void
    {
        // PNG de 30000 x 30000 pixels (900 MP) com poucos KB: só o cabeçalho é lido, nada é alocado.
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $ctx = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        $idat = '';

        for ($y = 0; $y < 400; $y++) {
            $idat .= deflate_add($ctx, str_repeat("\0", 30001), ZLIB_NO_FLUSH);
        }

        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 30000, 30000, 8, 0, 0, 0, 0)).$chunk('IDAT', $idat.deflate_add($ctx, '', ZLIB_FINISH)).$chunk('IEND', '');
        $before = memory_get_peak_usage(true);

        $this->upload($this->roupas, UploadedFile::fake()->createWithContent('big.png', $png))
            ->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, 'lado máximo'));

        $this->assertLessThan(8 * 1048576, memory_get_peak_usage(true) - $before);
    }

    // EXIF, GPS e orientação (PD2-13) -------------------------------------------------------------------------------------

    public function test_derivatives_drop_exif_and_gps_while_the_private_original_keeps_its_bytes(): void
    {
        $content = $this->jpegWithExif(1);
        $this->assertStringContainsString('CameraMaker', $content);
        $this->upload($this->roupas, UploadedFile::fake()->createWithContent('gps.jpg', $content))->assertNoContent();

        $asset = MediaAsset::sole();

        foreach ($asset->variants as $variant) {
            $bytes = Storage::disk('media-variants')->get($variant->public_path);
            $this->assertStringNotContainsString('Exif', $bytes);
            $this->assertStringNotContainsString('CameraMaker', $bytes);
            $this->assertStringNotContainsString('Model-S08', $bytes);
            $this->assertStringNotContainsString('2026:11:26', $bytes);
            $this->assertStringNotContainsString('GPS', $bytes);
            $this->assertStringNotContainsString("\xFF\xE1", $bytes, 'Nenhum segmento APP1 sobrou.');
        }

        // O original privado é guardado como veio.
        $this->assertSame(hash('sha256', $content), $asset->sha256);
        $this->assertSame($content, Storage::disk('media-originals')->get($asset->original_path));
    }

    public function test_all_eight_exif_orientations_produce_the_correctly_oriented_derivative(): void
    {
        for ($orientation = 1; $orientation <= 8; $orientation++) {
            $this->upload($this->roupas, UploadedFile::fake()->createWithContent("o$orientation.jpg", $this->jpegWithExif($orientation)), 'employee_photo')
                ->assertNoContent();
            $asset = MediaAsset::latest('id')->first();
            $expected = $this->expectedQuadrants($orientation);
            $largest = $asset->variants()->orderByDesc('width')->first();
            $read = $this->readQuadrants(Storage::disk('media-variants')->get($largest->public_path));

            $this->assertSame($expected['quadrants'], $read['quadrants'], "Orientação $orientation: quadrantes");
            $this->assertSame($expected['width'] > $expected['height'], $read['width'] > $read['height'], "Orientação $orientation: formato");
            $this->assertEqualsWithDelta($expected['width'] / $expected['height'], $read['width'] / $read['height'], 0.02);
            // O original continua na orientação gravada, e as dimensões cadastradas são as do original.
            $this->assertSame([400, 300], [$asset->width, $asset->height]);
        }
    }

    /**
     * PD2-13, prova comparativa: o GD sozinho (sem correção) erra as orientações 2 a 8; o processamento atual acerta as oito
     * em todas as variantes, e nenhuma variante carrega segmento de metadados, EXIF, GPS, câmera ou data.
     */
    public function test_gd_without_correction_fails_orientations_2_to_8_and_the_current_pipeline_fixes_all_eight_without_metadata(): void
    {
        for ($orientation = 1; $orientation <= 8; $orientation++) {
            $content = $this->jpegWithExif($orientation, 1000, 800);
            $expected = $this->expectedQuadrants($orientation, 1000, 800);

            // Linha de base: o GD decodifica e reduz sem aplicar a orientação (o GD embutido não a lê).
            $source = imagecreatefromstring($content);
            $plain = imagecreatetruecolor(400, 320);
            imagecopyresampled($plain, $source, 0, 0, 0, 0, 400, 320, 1000, 800);
            $baseline = $this->readQuadrants($this->encode($plain, 'jpeg'));
            $this->assertSame($orientation === 1, $baseline['quadrants'] === $expected['quadrants'] && $baseline['width'] > $baseline['height'] === ($expected['width'] > $expected['height']), "Orientação $orientation: o GD sem correção só acerta a 1");

            $this->upload($this->roupas, UploadedFile::fake()->createWithContent("o$orientation.jpg", $content), 'employee_photo')->assertNoContent();
            $asset = MediaAsset::latest('id')->first();
            $this->assertCount(3, $asset->variants);

            foreach ($asset->variants as $variant) {
                $bytes = Storage::disk('media-variants')->get($variant->public_path);
                $read = $this->readQuadrants($bytes);

                $this->assertSame($expected['quadrants'], $read['quadrants'], "Orientação $orientation, {$variant->kind}: quadrantes");
                $this->assertSame($expected['width'] > $expected['height'], $read['width'] > $read['height'], "Orientação $orientation, {$variant->kind}: formato");
                $this->assertEqualsWithDelta($expected['width'] / $expected['height'], $read['width'] / $read['height'], 0.02);
                $this->assertSame([$read['width'], $read['height']], [$variant->width, $variant->height]);
                $this->assertSame([], $this->jpegMetadataSegments($bytes), "Orientação $orientation, {$variant->kind}: segmento de metadados");

                foreach (['Exif', 'GPS', 'CameraMaker', 'Model-S08', '2026:11:26'] as $needle) {
                    $this->assertStringNotContainsString($needle, $bytes);
                }
            }

            // O original privado guarda os bytes enviados; só a interface autorizada o entrega.
            $this->assertSame($content, Storage::disk('media-originals')->get($asset->original_path));
        }
    }

    public function test_malformed_exif_is_ignored_safely(): void
    {
        $jpeg = $this->encode($this->pattern(400, 300), 'jpeg');
        $bad = "\xFF\xD8\xFF\xE1".pack('n', 40)."Exif\0\0"."II\x2A\x00\xFF\xFF\xFF\x7F".str_repeat("\xFF", 20).substr($jpeg, 2);

        $this->upload($this->roupas, UploadedFile::fake()->createWithContent('bad.jpg', $bad))->assertNoContent();
        $this->assertGreaterThan(0, MediaAsset::sole()->variants()->count());
    }

    // Atomicidade ---------------------------------------------------------------------------------------------------------

    /** @return array<string, array{0: string}> */
    public static function failurePoints(): array
    {
        return ['depois do original' => ['after_original'], 'depois da primeira variante' => ['after_variant'], 'antes de confirmar' => ['before_commit']];
    }

    #[DataProvider('failurePoints')]
    public function test_a_failure_at_a_controlled_point_leaves_no_usable_media_and_no_residue(string $point): void
    {
        $this->app->instance(MediaPipelineProbe::class, new class($point) implements MediaPipelineProbe
        {
            public function __construct(private string $point) {}

            public function reach(string $point): void
            {
                if ($point === $this->point) {
                    throw new RuntimeException('falha simulada em '.$point);
                }
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->upload($this->roupas, $this->imageFile('jpg', 1400, 1000));
            $this->fail('O envio deveria falhar.');
        } catch (RuntimeException $failure) {
            $this->assertSame('falha simulada em '.$point, $failure->getMessage());
        }

        $this->assertSame(0, MediaAsset::count() + MediaVariant::count());
        $this->assertSame(0, $this->files('media-originals') + $this->files('media-variants'));
        $this->assertSame(0, AuditRecord::where('entity_type', 'media-asset')->count());
    }

    public function test_a_failing_variant_write_and_a_failing_database_insert_are_compensated(): void
    {
        $flaky = new FlakyMediaStorage(app(DiskMediaStorage::class), failOnVariantWrite: 2);
        $this->app->instance(MediaStorage::class, $flaky);
        $this->withoutExceptionHandling();

        try {
            $this->upload($this->roupas, $this->imageFile('jpg', 1400, 1000));
            $this->fail('O envio deveria falhar.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame(0, MediaAsset::count() + $this->files('media-originals') + $this->files('media-variants'));

        // Falha do banco na segunda variante: as linhas voltam e os arquivos já gravados são removidos.
        $this->app->instance(MediaStorage::class, app(DiskMediaStorage::class));
        $inserts = 0;
        DB::listen(function ($query) use (&$inserts): void {
            if (str_starts_with($query->sql, 'insert into "media_variants"') && ++$inserts === 2) {
                throw new RuntimeException('banco indisponível');
            }
        });

        try {
            $this->upload($this->roupas, $this->imageFile('jpg', 1400, 1000));
            $this->fail('O envio deveria falhar.');
        } catch (RuntimeException $failure) {
            $this->assertSame('banco indisponível', $failure->getMessage());
        }

        $this->assertSame(0, MediaAsset::count() + MediaVariant::count());
        $this->assertSame(0, $this->files('media-originals') + $this->files('media-variants'));
    }

    public function test_the_time_budget_aborts_before_anything_is_written(): void
    {
        config(['media.time_budget_seconds' => -1]);

        $this->upload($this->roupas, $this->imageFile())
            ->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, 'tempo'));

        $this->assertSame(0, MediaAsset::count() + $this->files('media-originals') + $this->files('media-variants'));
    }

    // Autorização e isolamento --------------------------------------------------------------------------------------------

    public function test_the_help_article_is_served_only_to_who_can_list_media(): void
    {
        $help = $this->role($this->gartan, 'Ajuda', ['self.help.list', 'self.help.view']);
        $media = $this->role($this->gartan, 'Leitura de mídia', ['account.media.list']);
        $tenant = AccessNode::tenant($this->gartan->id);
        $reader = $this->operator($this->gartan, [[$help, $tenant], [$media, AccessNode::company($this->gartan->id, $this->roupas->id)]]);
        $other = $this->operator($this->gartan, [[$help, $tenant]]);

        $this->actingAs($reader)->getJson('/conta/ajuda/midia')->assertOk()->assertJsonPath('article.title', 'Mídia');
        $this->actingAs($other)->getJson('/conta/ajuda/midia')->assertNotFound();
    }

    public function test_each_action_has_its_own_permission_and_denied_attempts_change_nothing(): void
    {
        $viewer = $this->managerOf($this->roupas, ['account.media.list']);
        $uploader = $this->managerOf($this->roupas, ['account.media.list', 'account.media.create']);
        $editor = $this->managerOf($this->roupas, ['account.media.list', 'account.media.update']);
        $blind = $this->managerOf($this->roupas, ['account.media.create']);
        $asset = $this->made();
        $events = AuditRecord::count();

        $this->actingAs($viewer);
        $this->getJson($this->url())->assertOk()->assertJsonPath('canCreate', false)->assertJsonPath('media.data.0.can_update', false);
        $this->upload($this->roupas, $this->imageFile())->assertForbidden();
        $this->patchJson($this->url(null, '/'.$asset->id), ['alt_text' => 'X'])->assertForbidden();
        $this->deleteJson($this->url(null, '/'.$asset->id))->assertForbidden();
        $this->get($this->url(null, '/'.$asset->id.'/original'))->assertForbidden();

        $this->actingAs($uploader);
        $this->upload($this->roupas, $this->imageFile())->assertNoContent();
        $this->deleteJson($this->url(null, '/'.$asset->id))->assertForbidden();

        $this->actingAs($editor);
        $this->patchJson($this->url(null, '/'.$asset->id), ['alt_text' => 'Novo'])->assertNoContent();
        $this->upload($this->roupas, $this->imageFile())->assertForbidden();
        $this->deleteJson($this->url(null, '/'.$asset->id))->assertForbidden();
        $this->get($this->url(null, '/'.$asset->id.'/original'))->assertOk();

        // Sem `list` nem a tela abre, mesmo com `create`.
        $this->actingAs($blind);
        $this->getJson($this->url())->assertForbidden();
        $this->upload($this->roupas, $this->imageFile())->assertForbidden();

        $this->assertSame(2, MediaAsset::count());
        $this->assertSame($events + 2, AuditRecord::count());
    }

    public function test_units_see_the_corporate_library_read_only_and_never_another_units_media(): void
    {
        $manager = $this->managerOf($this->guanambi, ['account.media.list', 'account.media.create', 'account.media.update', 'account.media.delete']);
        $corporate = $this->made();
        $this->upload($this->caetite, $this->imageFile())->assertNoContent();
        $other = MediaAsset::latest('id')->first();

        $this->actingAs($manager);
        $this->upload($this->guanambi, $this->imageFile('png'), 'gallery', [
            'company_id' => $this->express->id, 'establishment_id' => $this->caetite->id, 'account_id' => 999,
        ])->assertNoContent();
        $local = MediaAsset::latest('id')->first();

        // O dono vem da URL: o corpo do pedido não muda conta, empresa nem unidade.
        $this->assertSame([$this->gartan->id, $this->roupas->id, $this->guanambi->id], [$local->account_id, $local->company_id, $local->establishment_id]);

        // Vê a corporativa e a própria; não vê a da outra unidade.
        $ids = collect($this->getJson($this->url($this->guanambi))->assertOk()->json('media.data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$corporate->id, $local->id], $ids);
        $this->getJson($this->url($this->guanambi, '/'.$other->id))->assertNotFound();
        $this->get($this->url($this->guanambi, '/'.$other->id.'/variantes/w320'))->assertNotFound();

        // A corporativa é só leitura para a unidade: a autoridade é a do dono da mídia.
        $this->get($this->url($this->guanambi, '/'.$corporate->id.'/variantes/w320'))->assertOk();
        $this->patchJson($this->url($this->guanambi, '/'.$corporate->id), ['alt_text' => 'X'])->assertForbidden();
        $this->deleteJson($this->url($this->guanambi, '/'.$corporate->id))->assertForbidden();
        $this->get($this->url($this->guanambi, '/'.$corporate->id.'/original'))->assertForbidden();
        $this->assertNotNull($corporate->fresh());

        // Outra unidade e a empresa não existem para ele.
        $this->getJson($this->url($this->caetite))->assertNotFound();
        $this->getJson($this->url())->assertNotFound();
        $this->upload($this->caetite, $this->imageFile())->assertNotFound();
        $this->upload($this->roupas, $this->imageFile())->assertNotFound();

        // O que é dele, edita e exclui.
        $this->patchJson($this->url($this->guanambi, '/'.$local->id), ['alt_text' => 'Minha'])->assertNoContent();
        $this->deleteJson($this->url($this->guanambi, '/'.$local->id))->assertNoContent();
        $this->assertNull(MediaAsset::find($local->id));
    }

    public function test_tenants_and_companies_never_reach_each_others_media_by_identifier(): void
    {
        $mine = $this->made();
        $expressMedia = $this->made('jpg', $this->express);

        $other = $this->account('Outro Grupo');
        $foreign = $this->company($other, 'Outro', '77888999');
        $stranger = $this->administrator($other);

        $this->actingAs($stranger);
        foreach ([$this->roupas, $this->express] as $company) {
            $this->getJson($this->url($company))->assertNotFound();
            $this->upload($company, $this->imageFile())->assertNotFound();
        }

        // Dentro da própria empresa, o identificador de outra conta (ou empresa) é inexistente.
        $this->getJson($this->url($foreign, '/'.$mine->id))->assertNotFound();
        $this->patchJson($this->url($foreign, '/'.$mine->id), ['alt_text' => 'X'])->assertNotFound();
        $this->deleteJson($this->url($foreign, '/'.$mine->id))->assertNotFound();
        $this->get($this->url($foreign, '/'.$mine->id.'/variantes/w320'))->assertNotFound();
        $this->get($this->url($foreign, '/'.$mine->id.'/original'))->assertNotFound();

        // Uma empresa da mesma conta não alcança a mídia de outra empresa pela URL da sua.
        $this->actingAs($this->owner);
        $this->getJson($this->url($this->express, '/'.$mine->id))->assertNotFound();
        $this->deleteJson($this->url($this->express, '/'.$mine->id))->assertNotFound();
        $this->assertNull(MediaAsset::find($expressMedia->id)->establishment_id);
        $this->assertSame(2, MediaAsset::count());
    }

    public function test_saas_inspection_in_read_only_mode_cannot_write_media(): void
    {
        $role = $this->role($this->gartan, 'Gestor da Roupas', ['account.media.list', 'account.media.create', 'account.media.update', 'account.media.delete']);
        $ana = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->roupas->id)]]);
        $asset = $this->made();
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $start = '/saas/contas/'.$this->gartan->id.'/inspecao';

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'read-only'])->assertOk();
        $this->getJson($this->url())->assertOk();
        $this->upload($this->roupas, $this->imageFile())->assertForbidden();
        $this->patchJson($this->url(null, '/'.$asset->id), ['alt_text' => 'X'])->assertForbidden();
        $this->deleteJson($this->url(null, '/'.$asset->id))->assertForbidden();
        $this->assertSame(1, MediaAsset::count());
        $this->postJson('/inspecao/encerrar')->assertOk();
    }

    public function test_files_are_delivered_only_through_authorized_routes_with_safe_headers(): void
    {
        $this->upload($this->roupas, $this->imageFile('png'), 'employee_photo')->assertNoContent();
        $asset = MediaAsset::sole();

        $response = $this->get($this->url(null, '/'.$asset->id.'/variantes/w160'));
        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('attachment', (string) $this->get($this->url(null, '/'.$asset->id.'/original'))->headers->get('Content-Disposition'));

        // <img> e download não pedem JSON: o navegador recebe o arquivo, nunca o HTML inicial da aplicação.
        $this->get($this->url(null, '/'.$asset->id.'/variantes/w160'), ['Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8'])
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($this->url(null, '/'.$asset->id.'/original'), ['Accept' => 'text/html,application/xhtml+xml'])
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($this->url(null, '/'.$asset->id.'/variantes/w9999'))->assertNotFound();
        $this->get($this->url(null, '/'.$asset->id.'/variantes/..%2f..%2f.env'))->assertNotFound();

        // Sem login não há arquivo; foto profissional não ganha endereço público só por ter sido enviada.
        auth()->logout();
        $this->get($this->url(null, '/'.$asset->id.'/variantes/w160'))->assertUnauthorized();
        $this->assertStringStartsNotWith(storage_path('app/public'), Storage::disk('media-variants')->path('x'));
        $this->assertFalse(is_link(public_path('storage')) && is_dir(public_path('storage/media')));
    }

    // Metadados e auditoria -------------------------------------------------------------------------------------------------

    public function test_metadata_edits_are_audited_in_the_owner_node_and_owner_and_purpose_never_change(): void
    {
        $this->upload($this->guanambi, $this->imageFile(), 'banner')->assertNoContent();
        $asset = MediaAsset::sole();
        $created = AuditRecord::where('entity_type', 'media-asset')->where('event', 'created')->sole();
        $this->assertSame($this->roupas->id, $created->company_id);
        $this->assertSame($this->guanambi->id, $created->establishment_id);
        $this->assertSame($this->owner->id, $created->actor_id);

        $body = ['alt_text' => ' Vitrine  <i>nova</i> ', 'license_note' => 'Foto própria', 'purpose' => 'logo', 'company_id' => $this->express->id,
            'establishment_id' => $this->caetite->id, 'status' => 'removal_requested', 'original_path' => 'o/xx/hack', 'mime' => 'image/gif'];
        $this->patchJson($this->url($this->guanambi, '/'.$asset->id), $body)->assertNoContent();

        $fresh = $asset->fresh();
        $this->assertSame('Vitrine nova', $fresh->alt_text);
        $this->assertSame('Foto própria', $fresh->license_note);
        $this->assertSame('banner', $fresh->purpose);
        $this->assertSame([$this->roupas->id, $this->guanambi->id, 'ready', 'image/jpeg'], [$fresh->company_id, $fresh->establishment_id, $fresh->status, $fresh->mime]);
        $this->assertSame($asset->original_path, $fresh->original_path);

        $events = AuditRecord::where('entity_type', 'media-asset')->where('event', 'updated')->get();
        $this->assertCount(1, $events);
        $this->assertSame($this->guanambi->id, $events[0]->establishment_id);
        $this->assertEqualsCanonicalizing(['alt_text', 'license_note'], array_keys($events[0]->changes));

        // Sem mudança, sem evento; limpar é enviar vazio.
        $this->patchJson($this->url($this->guanambi, '/'.$asset->id), ['alt_text' => 'Vitrine nova', 'license_note' => 'Foto própria'])->assertNoContent();
        $this->assertSame(1, AuditRecord::where('entity_type', 'media-asset')->where('event', 'updated')->count());
        $this->patchJson($this->url($this->guanambi, '/'.$asset->id), ['alt_text' => '', 'license_note' => ''])->assertNoContent();
        $this->assertNull($asset->fresh()->alt_text);

        // O histórico nunca guarda caminho, binário, EXIF ou GPS.
        $everything = json_encode(AuditRecord::where('entity_type', 'media-asset')->get()->toArray());
        $this->assertStringNotContainsString($asset->original_path, $everything);
        $this->assertStringNotContainsString('original_path', $everything);
        $this->assertStringNotContainsString('CameraMaker', $everything);
        $this->assertStringNotContainsString('base64', $everything);
        $this->patchJson($this->url($this->guanambi, '/'.$asset->id), ['alt_text' => str_repeat('x', 301)])->assertUnprocessable();
    }

    // Exclusão, retenção e resíduos -----------------------------------------------------------------------------------------

    private function usage(bool $used): object
    {
        $checker = new class implements MediaUsageChecker
        {
            public bool $used = false;

            public function isUsed(MediaAsset $media): bool
            {
                return $this->used;
            }
        };
        $checker->used = $used;
        $this->app->instance('test.usage', $checker);
        $this->app->tag(['test.usage'], 'media.usage');

        return $checker;
    }

    public function test_unused_media_is_removed_with_its_files_and_events(): void
    {
        $asset = $this->made();
        $this->assertSame(1, $this->files('media-originals'));

        $this->deleteJson($this->url(null, '/'.$asset->id))->assertNoContent();

        $this->assertSame(0, MediaAsset::count() + MediaVariant::count());
        $this->assertSame(0, $this->files('media-originals') + $this->files('media-variants'));
        $deleted = AuditRecord::where('entity_type', 'media-asset')->where('event', 'deleted')->sole();
        $this->assertSame($this->roupas->id, $deleted->company_id);
        $this->assertStringNotContainsString($asset->original_path, json_encode($deleted->toArray()));
    }

    public function test_media_in_use_is_only_marked_and_leaves_when_it_stops_being_used(): void
    {
        $checker = $this->usage(true);
        $asset = $this->made();

        $this->deleteJson($this->url(null, '/'.$asset->id))->assertNoContent();

        $this->assertSame('removal_requested', $asset->fresh()->status);
        $this->assertSame(1, $this->files('media-originals'));
        $this->assertGreaterThan(0, $this->files('media-variants'));
        $this->assertSame(1, AuditRecord::where('entity_type', 'media-asset')->where('event', 'updated')->count());
        $this->assertSame(0, AuditRecord::where('event', 'deleted')->where('entity_type', 'media-asset')->count());

        // O comando não remove enquanto houver uso e, depois que o uso acaba, libera.
        $this->artisan('media:purge')->assertSuccessful();
        $this->assertNotNull(MediaAsset::find($asset->id));

        $checker->used = false;
        $this->artisan('media:purge --dry-run')->expectsOutputToContain('Mídias com exclusão solicitada e sem uso: 1')->assertSuccessful();
        $this->assertNotNull(MediaAsset::find($asset->id));
        $this->artisan('media:purge')->assertSuccessful();
        $this->assertNull(MediaAsset::find($asset->id));
        $this->assertSame(0, $this->files('media-originals') + $this->files('media-variants'));

        // A operação reutilizável pelos eventos futuros recusa mídia em uso.
        $checker->used = true;
        $again = $this->made();
        $this->expectException(MediaInUse::class);
        app(RemoveUnusedMediaAction::class)->execute($again);
    }

    public function test_removal_failures_and_missing_files_never_leave_an_inconsistent_state(): void
    {
        $asset = $this->made();

        // O arquivo já não existe: a exclusão funciona mesmo assim.
        Storage::disk('media-originals')->delete($asset->original_path);
        $this->deleteJson($this->url(null, '/'.$asset->id))->assertNoContent();
        $this->assertSame(0, MediaAsset::count() + MediaVariant::count());

        // A remoção do arquivo falha: o banco já não o referencia, e o `media:purge` o encontra como resíduo.
        $second = $this->made();
        $this->app->instance(MediaStorage::class, new FlakyMediaStorage(app(DiskMediaStorage::class), failOnDelete: true));
        $this->deleteJson($this->url(null, '/'.$second->id))->assertNoContent();
        $this->assertSame(0, MediaAsset::count() + MediaVariant::count());
        $this->assertSame(1, $this->files('media-originals'));

        $this->app->instance(MediaStorage::class, app(DiskMediaStorage::class));
        $this->artisan('media:purge --grace=0')->assertSuccessful();
        $this->assertSame(0, $this->files('media-originals') + $this->files('media-variants'));
    }

    public function test_purge_is_read_only_in_dry_run_limited_to_managed_prefixes_and_idempotent(): void
    {
        $live = $this->made();
        Storage::disk('media-originals')->put('o/zz/orfao-original', 'x');
        Storage::disk('media-variants')->put('v/zz/orfao-variante.jpg', 'x');
        // Arquivos fora dos prefixos gerenciados (de outro módulo, por exemplo) nunca são tocados.
        Storage::disk('media-originals')->put('outro-modulo/arquivo.bin', 'x');
        Storage::disk('media-variants')->put('site/pagina.html', 'x');
        // Resíduo recente (dentro da tolerância) não é apagado.
        $recent = 'o/zz/recente';
        Storage::disk('media-originals')->put($recent, 'x');
        touch(Storage::disk('media-originals')->path($recent), time());
        foreach (['o/zz/orfao-original' => 'media-originals', 'v/zz/orfao-variante.jpg' => 'media-variants'] as $path => $disk) {
            touch(Storage::disk($disk)->path($path), time() - 7200);
        }

        $before = [$this->files('media-originals'), $this->files('media-variants'), MediaAsset::count()];
        $this->artisan('media:purge --dry-run')
            ->expectsOutputToContain('[simulação] Originais órfãos: 1')
            ->expectsOutputToContain('[simulação] Derivados órfãos: 1')
            ->assertSuccessful();
        $this->assertSame($before, [$this->files('media-originals'), $this->files('media-variants'), MediaAsset::count()]);

        $this->artisan('media:purge')->assertSuccessful();
        $this->assertFalse(Storage::disk('media-originals')->exists('o/zz/orfao-original'));
        $this->assertFalse(Storage::disk('media-variants')->exists('v/zz/orfao-variante.jpg'));
        $this->assertTrue(Storage::disk('media-originals')->exists($recent));
        $this->assertTrue(Storage::disk('media-originals')->exists('outro-modulo/arquivo.bin'));
        $this->assertTrue(Storage::disk('media-variants')->exists('site/pagina.html'));
        $this->assertTrue(Storage::disk('media-originals')->exists($live->original_path));
        $this->assertGreaterThan(0, $live->variants()->count());

        // Segunda execução: nada a fazer.
        $this->artisan('media:purge')->expectsOutputToContain('Originais órfãos: 0')->assertSuccessful();
        $this->assertNotNull(MediaAsset::find($live->id));
    }

    // Banco ---------------------------------------------------------------------------------------------------------------------

    public function test_the_database_refuses_incoherent_media(): void
    {
        $asset = $this->made();
        $base = [
            'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'establishment_id' => null, 'purpose' => 'gallery',
            'original_path' => 'o/aa/'.uniqid(), 'mime' => 'image/png', 'bytes' => 10, 'width' => 5, 'height' => 5,
            'sha256' => str_repeat('a', 64), 'status' => 'ready', 'uploaded_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ];
        $refused = function (array $change) use ($base): bool {
            try {
                DB::table('media_assets')->insert([[...$base, ...$change, 'original_path' => 'o/aa/'.uniqid()]]);
            } catch (QueryException) {
                return true;
            }

            return false;
        };

        $this->assertTrue($refused(['purpose' => 'documento']));
        $this->assertTrue($refused(['status' => 'processing']));
        $this->assertTrue($refused(['mime' => 'image/svg+xml']));
        $this->assertTrue($refused(['mime' => 'image/gif']));
        $this->assertTrue($refused(['bytes' => 0]));
        $this->assertTrue($refused(['width' => 0]));
        $this->assertTrue($refused(['sha256' => 'curto']));
        // Unidade de outra empresa, empresa de outra conta e duplicidade do caminho do original.
        $this->assertTrue($refused(['establishment_id' => $this->express->primaryEstablishment()->sole()->id]));
        $this->assertTrue($refused(['company_id' => $this->express->id, 'establishment_id' => $this->guanambi->id]));
        $this->assertTrue($refused(['account_id' => 999999]));
        $this->assertTrue($refused(['uploaded_by' => 999999]));
        $this->assertTrue(rescue(fn () => ! DB::table('media_assets')->insert([[...$base, 'original_path' => $asset->original_path]]), true, false));
        $this->assertFalse($refused([]));

        // Variante: ativo de outra empresa, tipo duplicado e caminho duplicado.
        $variant = $asset->variants()->first();
        $row = ['account_id' => $this->gartan->id, 'company_id' => $this->express->id, 'media_asset_id' => $asset->id, 'kind' => 'w1', 'public_path' => 'v/aa/novo',
            'mime' => 'image/jpeg', 'bytes' => 1, 'width' => 1, 'height' => 1, 'created_at' => now(), 'updated_at' => now()];
        $this->assertTrue(rescue(fn () => ! DB::table('media_variants')->insert([$row]), true, false));
        $this->assertTrue(rescue(fn () => ! DB::table('media_variants')->insert([[...$row, 'company_id' => $this->roupas->id, 'kind' => $variant->kind, 'public_path' => 'v/aa/outro']]), true, false));
        $this->assertTrue(rescue(fn () => ! DB::table('media_variants')->insert([[...$row, 'company_id' => $this->roupas->id, 'public_path' => $variant->public_path]]), true, false));
        $this->assertTrue(rescue(fn () => ! DB::table('media_variants')->insert([[...$row, 'company_id' => $this->roupas->id, 'width' => 0]]), true, false));
    }

    public function test_media_code_reaches_storage_only_through_the_contract_and_never_leaks_paths(): void
    {
        $violations = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app/Domains'))) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace(base_path().'/', '', $file->getPathname());
            $source = (string) file_get_contents($file->getPathname());

            // Fora do contrato de armazenamento e do Model, ninguém lê Storage nem os caminhos privados.
            if (preg_match('/media-originals|media-variants|config\(.media\.disks/', $source) && ! str_contains($path, 'Infrastructure/Storage/')) {
                $violations[] = $path.' (disco)';
            }

            if (str_starts_with($path, 'app/Domains/Midia/UI/') && preg_match("/'original_path'|'public_path'|->original_path|->public_path/", $source)
                && ! str_contains($source, '$variant->public_path')) {
                $violations[] = $path.' (caminho)';
            }
        }

        $this->assertSame([], $violations);
    }
}

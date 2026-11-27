<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Midia\Domain\Contracts\MediaPipelineProbe;
use App\Domains\Midia\Domain\Contracts\MediaStorage;
use App\Domains\Midia\Domain\ValueObjects\ImageVariant;
use App\Domains\Midia\Domain\ValueObjects\StoredMediaFiles;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava o original (privado) e os derivados com caminhos opacos. Uma transação SQL não desfaz o disco, então qualquer
 * falha no meio remove o que já foi gravado antes de relançar: ou tudo fica gravado, ou nada.
 */
final readonly class WriteMediaFilesAction
{
    public function __construct(
        private MediaStorage $storage,
        private MediaPipelineProbe $probe,
        private DiscardMediaFilesAction $discard,
    ) {}

    /**
     * @param  list<ImageVariant>  $variants
     *
     * @throws Throwable
     */
    public function execute(string $sourceFile, array $variants): StoredMediaFiles
    {
        $originalPath = 'o/'.substr((string) Str::ulid(), 0, 2).'/'.Str::lower((string) Str::ulid());
        $variantPaths = [];

        try {
            $this->storage->putOriginal($originalPath, $sourceFile);
            $this->probe->reach('after_original');

            foreach ($variants as $variant) {
                $path = 'v/'.substr((string) Str::ulid(), 0, 2).'/'.Str::lower((string) Str::ulid()).'.'.$variant->extension();
                $this->storage->putVariant($path, $variant->contents);
                $variantPaths[$variant->kind] = $path;
                $this->probe->reach('after_variant');
            }
        } catch (Throwable $failure) {
            $this->discard->execute(new StoredMediaFiles($originalPath, $variantPaths));

            throw $failure;
        }

        return new StoredMediaFiles($originalPath, $variantPaths);
    }
}

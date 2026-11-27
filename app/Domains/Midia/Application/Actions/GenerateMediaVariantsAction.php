<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Midia\Domain\Contracts\ImageVariantFactory;
use App\Domains\Midia\Domain\Enums\MediaPurpose;
use App\Domains\Midia\Domain\Exceptions\InvalidMedia;
use App\Domains\Midia\Domain\ValueObjects\ImageInspection;
use App\Domains\Midia\Domain\ValueObjects\ImageVariant;

/**
 * Gera os derivados de uma imagem já inspecionada, sob o orçamento de tempo do envio. Nada é gravado aqui: se o tempo
 * estourar, o envio aborta sem resíduo algum no armazenamento.
 */
final readonly class GenerateMediaVariantsAction
{
    public function __construct(
        private ImageVariantFactory $factory,
    ) {}

    /**
     * @param  float  $startedAt  `hrtime(true)` do começo do envio, em nanossegundos
     * @return list<ImageVariant>
     *
     * @throws InvalidMedia
     */
    public function execute(string $file, ImageInspection $inspection, MediaPurpose $purpose, float $startedAt): array
    {
        $budget = (float) config('media.time_budget_seconds');

        $checkpoint = function () use ($startedAt, $budget): void {
            if ((hrtime(true) - $startedAt) / 1e9 > $budget) {
                throw InvalidMedia::file('O processamento da imagem excedeu o tempo permitido. Envie uma imagem menor.');
            }
        };

        $checkpoint();

        return $this->factory->make($file, $inspection, $purpose, $checkpoint);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Contracts;

use App\Domains\Midia\Domain\Exceptions\InvalidMedia;
use App\Domains\Midia\Domain\ValueObjects\ImageInspection;

/** Barreiras anteriores à decodificação (seção 13.1): bytes, tipo pelo conteúdo, dimensões e estimativa de memória. */
interface ImageInspector
{
    /**
     * @throws InvalidMedia
     */
    public function inspect(string $file): ImageInspection;

    /** Megapixels garantidos no ambiente (teto nominal limitado pelo orçamento de memória). Fonte única da tela e da recusa. */
    public function effectiveMegapixels(?int $budget = null): int;
}

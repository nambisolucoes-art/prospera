<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Contracts;

/**
 * Ponto de falha controlado do envio. Em produção não faz nada; os testes e os ensaios de laboratório o substituem para
 * simular falha depois do original, no meio das variantes ou antes da confirmação.
 */
interface MediaPipelineProbe
{
    public function reach(string $point): void;
}

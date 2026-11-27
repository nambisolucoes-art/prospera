<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Contracts;

use App\Domains\Midia\Domain\Models\MediaAsset;

/**
 * Contrato de verificação de uso. Cada módulo que referenciar mídia (perfil institucional, sites, equipe) registra uma
 * implementação com a tag `media.usage` e responde se a mídia está em uso, inclusive em revisão publicada. Na S08 não há
 * consumidor, então nenhuma implementação existe; os testes registram uma para provar a proteção.
 */
interface MediaUsageChecker
{
    public function isUsed(MediaAsset $media): bool;
}

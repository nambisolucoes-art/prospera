<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Queries;

use App\Domains\Midia\Domain\Contracts\MediaUsageChecker;
use App\Domains\Midia\Domain\Models\MediaAsset;
use Illuminate\Contracts\Container\Container;

/** A mídia está em uso por algum consumidor? Pergunta a cada `MediaUsageChecker` registrado com a tag `media.usage`. */
final readonly class MediaIsInUseQuery
{
    public function __construct(
        private Container $container,
    ) {}

    public function execute(MediaAsset $media): bool
    {
        foreach ($this->container->tagged('media.usage') as $checker) {
            if ($checker instanceof MediaUsageChecker && $checker->isUsed($media)) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Midia\Infrastructure\Storage;

use App\Domains\Midia\Domain\Contracts\MediaPipelineProbe;

final class NullMediaPipelineProbe implements MediaPipelineProbe
{
    public function reach(string $point): void {}
}

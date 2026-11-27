<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Enums;

/** Catálogo fechado de usos da mídia (seção 13.1). O cliente nunca cria um uso novo nem envia dimensões. */
enum MediaPurpose: string
{
    case Logo = 'logo';
    case Banner = 'banner';
    case Gallery = 'gallery';
    case EmployeePhoto = 'employee_photo';
    case OgImage = 'og_image';

    public function label(): string
    {
        return (string) config('media.purposes.'.$this->value.'.label');
    }

    /** @return list<int> larguras nominais das variantes, da menor para a maior */
    public function widths(): array
    {
        $widths = array_map('intval', config('media.purposes.'.$this->value.'.widths'));
        sort($widths);

        return $widths;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $purpose): array => ['value' => $purpose->value, 'label' => $purpose->label()],
            self::cases(),
        );
    }
}

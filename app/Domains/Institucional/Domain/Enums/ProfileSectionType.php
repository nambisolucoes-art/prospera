<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Enums;

use App\Domains\Institucional\Application\Data\Profile\Sections\AboutValue;
use App\Domains\Institucional\Application\Data\Profile\Sections\ContactsValue;
use App\Domains\Institucional\Application\Data\Profile\Sections\PaletteValue;
use App\Domains\Institucional\Application\Data\Profile\Sections\SeoDefaultsValue;
use App\Domains\Institucional\Application\Data\Profile\Sections\SocialLinksValue;
use App\Domains\Institucional\Application\Data\Profile\Sections\TypographyValue;
use App\Domains\Institucional\Domain\Contracts\SectionValue;

/**
 * Catálogo de seções do perfil institucional (seção 09.4 do plano 002). Cada seção tem classe (editorial ou
 * operacional), regra de desligamento e escopo. Seções que dependem de etapas futuras ficam declaradas e
 * indisponíveis: mídia (S08) e equipe (S09 e S12). `hours_weekly` entra com a S07.
 */
enum ProfileSectionType: string
{
    case Logo = 'logo';
    case Palette = 'palette';
    case Typography = 'typography';
    case About = 'about';
    case Contacts = 'contacts';
    case SocialLinks = 'social_links';
    case Banners = 'banners';
    case Gallery = 'gallery';
    case SeoDefaults = 'seo_defaults';
    case TeamDisplay = 'team_display';

    public function label(): string
    {
        return match ($this) {
            self::Logo => 'Logotipo',
            self::Palette => 'Paleta de cores',
            self::Typography => 'Tipografia',
            self::About => 'Sobre',
            self::Contacts => 'Contatos públicos',
            self::SocialLinks => 'Redes sociais',
            self::Banners => 'Banners',
            self::Gallery => 'Galeria',
            self::SeoDefaults => 'SEO padrão',
            self::TeamDisplay => 'Equipe exibida',
        };
    }

    /** Editorial: só muda o público por publicação explícita. Operacional: vale ao salvar (DA2-16). */
    public function isOperational(): bool
    {
        return in_array($this, [self::Contacts, self::SocialLinks], true);
    }

    public function canDisable(): bool
    {
        return in_array($this, [self::About, self::SocialLinks, self::Banners, self::Gallery, self::TeamDisplay], true);
    }

    /** A galeria é local por natureza: existe só no estabelecimento e não herda da empresa. */
    public function isEstablishmentOnly(): bool
    {
        return $this === self::Gallery;
    }

    public function isAvailable(): bool
    {
        return ! in_array($this, [self::Logo, self::Banners, self::Gallery, self::TeamDisplay], true);
    }

    public function unavailableReason(): ?string
    {
        return match ($this) {
            self::Logo, self::Banners, self::Gallery => 'Disponível com a biblioteca de mídia (S08).',
            self::TeamDisplay => 'Disponível com o perfil público dos colaboradores (S09).',
            default => null,
        };
    }

    /** @return class-string<SectionValue>|null */
    public function valueClass(): ?string
    {
        return match ($this) {
            self::Palette => PaletteValue::class,
            self::Typography => TypographyValue::class,
            self::About => AboutValue::class,
            self::Contacts => ContactsValue::class,
            self::SocialLinks => SocialLinksValue::class,
            self::SeoDefaults => SeoDefaultsValue::class,
            default => null,
        };
    }

    /** @return array<string,mixed> */
    public function defaultValue(): array
    {
        $class = $this->valueClass();

        return $class === null ? [] : $class::default()->toArray();
    }

    /** @return array<string,mixed> */
    public function emptyValue(): array
    {
        $class = $this->valueClass();

        return $class === null ? [] : $class::empty()->toArray();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}

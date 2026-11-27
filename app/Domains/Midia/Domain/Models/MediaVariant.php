<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Models;

use App\Domains\Midia\Domain\ValueObjects\ImageVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Derivado processado de uma mídia, com caminho opaco no disco de derivados. É um registro técnico filho de
 * `media_assets`: a auditoria é a do ativo (config/audit.php). Existir não significa estar autorizado para exposição
 * pública, que só vale com a publicação (S11).
 *
 * @property int $id
 * @property int $account_id
 * @property int $company_id
 * @property int $media_asset_id
 * @property string $kind
 * @property string $public_path
 * @property string $mime
 * @property int $bytes
 * @property int $width
 * @property int $height
 */
#[Fillable(['account_id', 'company_id', 'media_asset_id', 'kind', 'public_path', 'mime', 'bytes', 'width', 'height'])]
class MediaVariant extends Model
{
    protected $table = 'media_variants';

    public static function register(MediaAsset $asset, ImageVariant $variant, string $publicPath): self
    {
        return new self([
            'account_id' => $asset->account_id,
            'company_id' => $asset->company_id,
            'media_asset_id' => $asset->id,
            'kind' => $variant->kind,
            'public_path' => $publicPath,
            'mime' => $variant->mime,
            'bytes' => $variant->bytes(),
            'width' => $variant->width,
            'height' => $variant->height,
        ]);
    }
}

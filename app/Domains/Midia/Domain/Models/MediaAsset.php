<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Midia\Domain\DTOs\Media\CreateMediaAssetDTO;
use App\Domains\Midia\Domain\DTOs\Media\UpdateMediaAssetDTO;
use App\Domains\Midia\Domain\Enums\MediaPurpose;
use App\Domains\Midia\Domain\Enums\MediaStatus;
use App\Domains\Midia\Domain\ValueObjects\MediaOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Mídia da biblioteca de uma empresa (corporativa) ou de um estabelecimento (local). O original fica em disco privado e
 * o caminho (`original_path`) nunca sai do servidor: não é exibido, auditado nem devolvido pela API. O dono (conta,
 * empresa e unidade) é definido no cadastro e nunca muda por edição de metadados.
 *
 * @property int $id
 * @property int $account_id
 * @property int $company_id
 * @property int|null $establishment_id
 * @property string $purpose
 * @property string $original_path
 * @property string $mime
 * @property int $bytes
 * @property int $width
 * @property int $height
 * @property string $sha256
 * @property string|null $alt_text
 * @property string|null $license_note
 * @property string $status
 * @property int $uploaded_by
 * @property Carbon|null $created_at
 */
#[Fillable([
    'account_id', 'company_id', 'establishment_id', 'purpose', 'original_path', 'mime', 'bytes', 'width', 'height',
    'sha256', 'alt_text', 'license_note', 'status', 'uploaded_by',
])]
class MediaAsset extends Model implements Auditable
{
    use AuditsChanges;

    protected $table = 'media_assets';

    protected $attributes = ['status' => 'ready'];

    public static function register(
        CreateMediaAssetDTO $data,
        MediaOwner $owner,
        int $uploadedBy,
    ): self {
        return new self([
            'account_id' => $owner->accountId,
            'company_id' => $owner->companyId,
            'establishment_id' => $owner->establishmentId,
            'purpose' => $data->purpose->value,
            'original_path' => $data->originalPath,
            'mime' => $data->mime,
            'bytes' => $data->bytes,
            'width' => $data->width,
            'height' => $data->height,
            'sha256' => $data->sha256,
            'alt_text' => self::normalizeNullableText($data->altText),
            'license_note' => self::normalizeNullableText($data->licenseNote),
            'status' => MediaStatus::Ready->value,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /** Só os metadados mudam. Dono, uso, arquivo e dimensões são imutáveis. */
    public function revise(UpdateMediaAssetDTO $data): void
    {
        $this->alt_text = self::normalizeNullableText($data->altText);
        $this->license_note = self::normalizeNullableText($data->licenseNote);
    }

    public function requestRemoval(): void
    {
        $this->status = MediaStatus::RemovalRequested->value;
    }

    public function owner(): MediaOwner
    {
        if ($this->establishment_id === null) {
            return MediaOwner::company($this->account_id, $this->company_id);
        }

        return MediaOwner::establishment($this->account_id, $this->company_id, $this->establishment_id);
    }

    public function purpose(): MediaPurpose
    {
        return MediaPurpose::from($this->purpose);
    }

    /** @return HasMany<MediaVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class, 'media_asset_id');
    }

    // Nome do histórico: o uso e o dono, sem caminho nem conteúdo.
    public function getNameAttribute(): string
    {
        return $this->purpose()->label().' #'.$this->id.($this->establishment_id === null ? ' (corporativa)' : ' (local)');
    }

    public static function auditType(): string
    {
        return 'media-asset';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'company_id' => 'Empresa',
            'establishment_id' => 'Estabelecimento',
            'purpose' => 'Uso',
            'mime' => 'Tipo',
            'bytes' => 'Tamanho',
            'width' => 'Largura',
            'height' => 'Altura',
            'sha256' => 'Impressão digital (SHA-256)',
            'alt_text' => 'Texto alternativo',
            'license_note' => 'Licença ou crédito',
            'status' => 'Situação',
            'uploaded_by' => 'Enviada por',
        ];
    }

    /** @return array<string,string> */
    public static function unauditedFields(): array
    {
        return [
            'original_path' => 'Caminho privado do armazenamento: nunca entra no histórico, nem técnico.',
        ];
    }

    private static function normalizeNullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

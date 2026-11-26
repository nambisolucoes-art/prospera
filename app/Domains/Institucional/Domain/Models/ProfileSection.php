<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Institucional\Domain\DTOs\Profile\CreateProfileSectionDTO;
use App\Domains\Institucional\Domain\DTOs\Profile\UpdateProfileSectionDTO;
use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\Exceptions\InvalidProfileSection;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seção do perfil institucional de uma empresa ou de um estabelecimento (S06). Guarda o par de trabalho
 * (`mode` e `value`) e o par publicado (`published_*`). A leitura dos valores efetivos passa sempre pelo
 * `ProfileResolver`: este Model serve às Actions de gravação e à auditoria.
 *
 * @property int $id
 * @property int $account_id
 * @property int $company_id
 * @property int|null $establishment_id
 * @property string $section
 * @property string $mode
 * @property array<string,mixed>|null $value
 * @property string|null $published_mode
 * @property array<string,mixed>|null $published_value
 * @property Carbon|null $published_at
 * @property int|null $published_by
 */
#[Fillable([
    'account_id', 'company_id', 'establishment_id', 'section', 'mode', 'value',
    'published_mode', 'published_value', 'published_at', 'published_by',
])]
class ProfileSection extends Model implements Auditable
{
    use AuditsChanges;

    protected $table = 'profile_sections';

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'published_value' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @throws InvalidProfileSection
     */
    public static function register(
        CreateProfileSectionDTO $data,
        int $accountId,
        int $companyId,
        ?int $establishmentId,
        string $section,
    ): self {
        return new self([
            'account_id' => $accountId,
            'company_id' => $companyId,
            'establishment_id' => $establishmentId,
            'section' => $section,
            ...self::attributesFrom($data, $section, $establishmentId),
        ]);
    }

    /**
     * Altera só o par de trabalho. O publicado muda por `publish()` ou, nas operacionais, por `revisePublished()`.
     *
     * @throws InvalidProfileSection
     */
    public function revise(UpdateProfileSectionDTO $data): void
    {
        $this->fill(self::attributesFrom($data, $this->section, $this->establishment_id));
    }

    /** Seção operacional: o par publicado acompanha o de trabalho ao salvar (DA2-16). */
    public function revisePublished(CreateProfileSectionDTO|UpdateProfileSectionDTO $data, int $userId): void
    {
        $this->published_mode = $data->mode;
        $this->published_value = $data->mode === 'override' ? $data->value : null;

        if ($this->isDirty(['published_mode', 'published_value'])) {
            $this->published_at = Carbon::now();
            $this->published_by = $userId;
        }
    }

    /** O dono da seção: a empresa, ou o estabelecimento quando há `establishment_id`. */
    public function owner(): ProfileOwner
    {
        if ($this->establishment_id === null) {
            return ProfileOwner::company($this->account_id, $this->company_id);
        }

        return ProfileOwner::establishment($this->account_id, $this->company_id, $this->establishment_id);
    }

    /**
     * Regras que dependem do dono: a empresa só personaliza, a galeria é local e a desativação só vale onde o catálogo
     * permite. Modo e valor ficam coerentes (valor só no `override`).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidProfileSection
     */
    private static function attributesFrom(
        CreateProfileSectionDTO|UpdateProfileSectionDTO $data,
        string $section,
        ?int $establishmentId,
    ): array {
        $type = ProfileSectionType::from($section);

        if ($establishmentId === null && $data->mode !== 'override') {
            throw InvalidProfileSection::in('mode', 'A empresa define o padrão: só é possível personalizar.');
        }

        if ($establishmentId === null && $type->isEstablishmentOnly()) {
            throw InvalidProfileSection::in('section', 'Esta seção existe só nos estabelecimentos.');
        }

        if ($data->mode === 'inherit' && $type->isEstablishmentOnly()) {
            throw InvalidProfileSection::in('mode', 'Esta seção é local e não herda da empresa.');
        }

        if ($data->mode === 'disabled' && ! $type->canDisable()) {
            throw InvalidProfileSection::in('mode', 'Esta seção não pode ser desativada.');
        }

        return [
            'mode' => $data->mode,
            'value' => $data->mode === 'override' ? $data->value : null,
        ];
    }

    /** Copia o valor de trabalho para o publicado. Devolve falso quando já são iguais. */
    public function publish(int $userId): bool
    {
        $this->published_mode = $this->mode;
        $this->published_value = $this->mode === 'override' ? $this->value : null;

        if (! $this->isDirty(['published_mode', 'published_value'])) {
            return false;
        }

        $this->published_at = Carbon::now();
        $this->published_by = $userId;

        return true;
    }

    public function type(): ?ProfileSectionType
    {
        return ProfileSectionType::tryFrom($this->section);
    }

    // Nome do histórico: a seção e o dono, lidos dentro da mesma conta.
    public function getNameAttribute(): ?string
    {
        $type = $this->type();
        $row = DB::table('companies')
            ->where('account_id', $this->account_id)
            ->where('id', $this->company_id)
            ->first(['name', 'trade_name']);

        if ($row === null) {
            return null;
        }

        $owner = $this->establishment_id === null
            ? ($row->trade_name ?: $row->name)
            : (string) DB::table('establishments')->where('id', $this->establishment_id)->value('name');

        return ($type?->label() ?? $this->section).' ('.$owner.')';
    }

    public static function auditType(): string
    {
        return 'profile-section';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'company_id' => 'Empresa',
            'establishment_id' => 'Estabelecimento',
            'section' => 'Seção',
            'mode' => 'Modo',
            'value' => 'Conteúdo',
            'published_mode' => 'Modo publicado',
            'published_value' => 'Conteúdo publicado',
            'published_at' => 'Publicado em',
            'published_by' => 'Publicado por',
        ];
    }
}

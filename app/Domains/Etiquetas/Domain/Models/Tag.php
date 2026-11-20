<?php

namespace App\Domains\Etiquetas\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Etiquetas\Domain\DTOs\TagDTO;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['account_id', 'module', 'name', 'color', 'system_key', 'active'])]
class Tag extends Model implements Auditable
{
    use AuditsChanges;

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsToMany<Person, $this> */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_tag');
    }

    public static function auditType(): string
    {
        return 'tag';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'active' => 'Ativa',
            'name' => 'Nome',
            'module' => 'Módulo',
            'color' => 'Cor',
            'account_id' => 'Conta',
            'system_key' => 'Etiqueta especial',
        ];
    }

    public static function register(TagDTO $data, int $accountId): self
    {
        $tag = new self(['account_id' => $accountId]);
        $tag->revise($data);

        return $tag;
    }

    public function revise(TagDTO $data): void
    {
        $this->fill([
            'name' => $data->name,
            'module' => $data->module,
            'color' => $data->color,
            'active' => $data->active,
        ]);
    }

    public static function supplierTag(int $accountId): self
    {
        return self::specialTag($accountId, 'supplier', 'Fornecedor', '#7266ba');
    }

    public static function customerTag(int $accountId): self
    {
        return self::specialTag($accountId, 'customer', 'Cliente', '#23b7e5');
    }

    private static function specialTag(int $accountId, string $key, string $label, string $color): self
    {
        $existing = self::where('account_id', $accountId)
            ->where('module', 'people')
            ->where('system_key', $key)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Uma etiqueta comum com o mesmo nome recebe um sufixo para não colidir.
        $name = $label;
        $suffix = 1;

        while (self::nameInUse($accountId, $name)) {
            $name = $label.' (especial '.$suffix++.')';
        }

        return self::firstOrCreate(
            ['account_id' => $accountId, 'module' => 'people', 'system_key' => $key],
            ['name' => $name, 'color' => $color, 'active' => true],
        );
    }

    private static function nameInUse(int $accountId, string $name): bool
    {
        return self::where('account_id', $accountId)
            ->where('module', 'people')
            ->where('name', $name)
            ->exists();
    }

    public static function userTag(int $accountId): self
    {
        return self::firstOrCreate(
            ['account_id' => $accountId, 'module' => 'people', 'system_key' => 'user'],
            ['name' => 'Usuário', 'color' => '#3563e9'],
        );
    }
}

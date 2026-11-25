<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Pessoas\Application\Data\CommercialTagsData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SyncCommercialTagsAction
{
    public function execute(CommercialRole $role, Customer|Supplier $relation, CommercialTagsData $selection): void
    {
        $table = $role->table().'_tags';
        $scope = DB::table($table)
            ->where('account_id', $relation->account_id)
            ->where('company_id', $relation->company_id)
            ->where('relation_id', $relation->id);

        $before = (clone $scope)->orderBy('tag_id')->pluck('tag_id')->map(fn ($id): int => (int) $id)->all();
        $after = $selection->ids;
        sort($after);

        if ($before === $after) {
            return;
        }

        $added = array_diff($after, $before);
        $removed = array_diff($before, $after);
        $valid = Tag::where('account_id', $relation->account_id)
            ->where('module', $role->table())
            ->whereNull('system_key')
            ->where('active', true)
            ->whereIn('id', $added)
            ->lockForUpdate()
            ->get(['id']);

        if ($valid->count() !== count($added)) {
            throw ValidationException::withMessages(['tag_ids' => 'Selecione etiquetas ativas deste módulo.']);
        }

        $scope->whereIn('tag_id', $removed)->delete();

        if ($added !== []) {
            DB::table($table)->insert(array_map(fn (int $id) => [
                'relation_id' => $relation->id,
                'company_id' => $relation->company_id,
                'account_id' => $relation->account_id,
                'tag_id' => $id,
            ], $added));
        }

        $relation->recordAuditFieldChange('tag_ids', $before, $after, 'Etiquetas');
    }
}

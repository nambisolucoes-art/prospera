<?php

namespace Database\Seeders\Etiquetas;

use App\Domains\Etiquetas\Application\Actions\SaveTagAction;
use App\Domains\Etiquetas\Application\Data\SaveTagData;
use Database\Seeders\Support\SeedsAccountScenario;
use Illuminate\Database\Seeder;

/** Etiquetas comerciais de Clientes e Fornecedores. Pessoas não recebe etiquetas próprias. */
final class CommercialTagSeeder extends Seeder
{
    use SeedsAccountScenario;

    public const CUSTOMER_TAGS = [
        'Cliente VIP' => '#2b7de9',
        'Fidelidade' => '#1f9d55',
        'Compra no atacado' => '#7a3ee8',
        'Inadimplente' => '#d6336c',
    ];

    public const SUPPLIER_TAGS = [
        'Fornecedor homologado' => '#0f766e',
        'Entrega rápida' => '#f59f00',
        'Importado' => '#495057',
        'Crítico para o estoque' => '#c92a2a',
    ];

    public function run(): void
    {
        $this->actAsAccountAdministrator();
        $save = app(SaveTagAction::class);
        $accountId = $this->account()->id;

        foreach (['customers' => self::CUSTOMER_TAGS, 'suppliers' => self::SUPPLIER_TAGS] as $module => $tags) {
            foreach ($tags as $name => $color) {
                $input = ['name' => $name, 'module' => $module, 'color' => $color, 'active' => true];
                $data = SaveTagData::fromInput($input, $accountId, null);

                $save->execute($accountId, null, $data);
            }
        }

        $this->stopActing();
    }
}

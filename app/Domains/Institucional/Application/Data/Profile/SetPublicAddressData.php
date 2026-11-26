<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Opção "Exibir endereço" do estabelecimento (DA2-23): desligada por padrão e sempre uma escolha explícita. */
final readonly class SetPublicAddressData
{
    public function __construct(public bool $show) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $values = Validator::validate($input, ['show' => ['required', 'boolean']], [
            'show.required' => 'Informe se o endereço será exibido.',
            'show.boolean' => 'Informe se o endereço será exibido.',
        ]);

        return new self((bool) $values['show']);
    }
}

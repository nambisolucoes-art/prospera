<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Employees;

use App\Domains\Colaboradores\Domain\Exceptions\CompanyNotAvailable;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;
use App\Domains\Pessoas\Application\Data\CommercialRelations\CommercialTagsData;
use App\Domains\Pessoas\Application\Data\People\CreatePersonData;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Support\ContactInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de colaborador: a pessoa (existente ou nova) e um vínculo por empresa escolhida. Só vale uma empresa
 * que o descritor da permissão de cadastrar cobre.
 */
final readonly class CreateEmployeeData
{
    /** @param list<NewEmployeeLink> $links */
    public function __construct(
        public int|CreatePersonData $person,
        public array $links,
        public CommercialTagsData $tags,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     * @param  list<NavigableCompany>  $allowedCompanies
     *
     * @throws CompanyNotAvailable
     * @throws ValidationException
     */
    public static function fromInput(array $input, array $allowedCompanies, int $accountId): self
    {
        // Com uma única empresa autorizada, a escolha é dispensada.
        if (! array_key_exists('companies', $input) && count($allowedCompanies) === 1) {
            $input['companies'] = [$allowedCompanies[0]->code];
        }

        if (is_array($input['work'] ?? null)) {
            foreach ($input['work'] as $code => $contacts) {
                if (is_array($contacts)) {
                    $input['work'][$code] = ContactInput::normalize($contacts);
                }
            }
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        $links = [];

        foreach (array_unique($values['companies']) as $code) {
            $company = collect($allowedCompanies)->first(fn (NavigableCompany $item) => $item->code === $code);

            // Empresa fora do acesso responde como inexistente.
            if ($company === null) {
                throw new CompanyNotAvailable;
            }

            $contacts = $values['work'][$code] ?? [];

            $links[] = new NewEmployeeLink(
                company: $company,
                workEmail: $contacts['email'] ?? null,
                workPhone: $contacts['phone'] ?? null,
                workWhatsapp: $contacts['whatsapp'] ?? null,
            );
        }

        if (array_key_exists('person_id', $values)) {
            $person = (int) $values['person_id'];
        } else {
            $fields = array_intersect_key($input, array_flip([...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS]));
            $person = CreatePersonData::fromInput($fields, $accountId);
        }

        return new self(person: $person, links: $links, tags: CommercialTagsData::fromInput($input['tag_ids'] ?? null));
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'person_id' => ['sometimes', 'integer', 'min:1'],
            'companies' => ['required', 'array', 'min:1', 'max:200'],
            'companies.*' => ['string', 'max:40'],
            'work' => ['sometimes', 'array'],
            'work.*' => ['array'],
            'work.*.email' => ['nullable', 'email', 'max:255'],
            'work.*.phone' => ['nullable', 'string', 'max:30'],
            'work.*.whatsapp' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'person_id.integer' => 'Busque a pessoa pelo CPF ou CNPJ antes de salvar.',
            'person_id.min' => 'Busque a pessoa pelo CPF ou CNPJ antes de salvar.',
            'companies.required' => 'Escolha ao menos uma empresa.',
            'companies.array' => 'Escolha ao menos uma empresa.',
            'companies.min' => 'Escolha ao menos uma empresa.',
            'companies.*.string' => 'Escolha empresas válidas.',
            'work.*.email.email' => 'Informe um e-mail profissional válido.',
            'work.*.email.max' => 'O e-mail deve ter até 255 caracteres.',
            'work.*.phone.max' => 'O telefone deve ter até 30 caracteres.',
            'work.*.whatsapp.max' => 'O WhatsApp deve ter até 30 caracteres.',
        ];
    }
}

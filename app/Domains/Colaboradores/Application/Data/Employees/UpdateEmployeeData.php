<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Employees;

use App\Domains\Pessoas\Application\Data\CommercialRelations\CommercialTagsData;
use App\Support\ContactInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Edição de um vínculo de colaborador: situação e contatos profissionais daquela empresa. */
final readonly class UpdateEmployeeData
{
    public function __construct(
        public ?bool $active,
        public bool $editsContacts,
        public ?string $workEmail,
        public ?string $workPhone,
        public ?string $workWhatsapp,
        public ?CommercialTagsData $tags,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $editsContacts = array_key_exists('work_email', $input)
            || array_key_exists('work_phone', $input)
            || array_key_exists('work_whatsapp', $input);

        $editsTags = array_key_exists('tag_ids', $input) || filter_var($input['edit_tags'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $contacts = ContactInput::normalize([
            'email' => $input['work_email'] ?? null,
            'phone' => $input['work_phone'] ?? null,
            'whatsapp' => $input['work_whatsapp'] ?? null,
        ]);

        $values = Validator::validate(
            [
                'active' => $input['active'] ?? null,
                'work_email' => $contacts['email'],
                'work_phone' => $contacts['phone'],
                'work_whatsapp' => $contacts['whatsapp'],
            ],
            self::rules(),
            self::messages(),
        );

        return new self(
            active: array_key_exists('active', $input) ? (bool) $values['active'] : null,
            editsContacts: $editsContacts,
            workEmail: $values['work_email'] ?? null,
            workPhone: $values['work_phone'] ?? null,
            workWhatsapp: $values['work_whatsapp'] ?? null,
            tags: $editsTags ? CommercialTagsData::fromInput($input['tag_ids'] ?? null) : null,
        );
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'active' => ['nullable', 'boolean'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'work_phone' => ['nullable', 'string', 'max:30'],
            'work_whatsapp' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'active.boolean' => 'Selecione uma situação válida.',
            'work_email.email' => 'Informe um e-mail profissional válido.',
            'work_email.max' => 'O e-mail deve ter até 255 caracteres.',
            'work_phone.max' => 'O telefone deve ter até 30 caracteres.',
            'work_whatsapp.max' => 'O WhatsApp deve ter até 30 caracteres.',
        ];
    }
}

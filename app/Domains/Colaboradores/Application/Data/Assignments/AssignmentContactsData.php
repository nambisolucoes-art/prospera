<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Assignments;

use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use App\Support\ContactInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Contatos profissionais próprios de uma lotação. `inherit` acompanha os contatos do vínculo e não guarda valor.
 * `override` guarda os contatos da unidade, e todos em branco é a ausência intencional de contato local.
 * Nada vem de Pessoa: os valores são sempre digitados.
 */
final readonly class AssignmentContactsData
{
    /** @param array{email: string|null, phone: string|null, whatsapp: string|null} $contacts */
    public function __construct(
        public string $mode,
        public array $contacts,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $values = Validator::validate(
            ['contact_mode' => $input['contact_mode'] ?? EmployeeAssignment::INHERIT],
            ['contact_mode' => ['required', 'in:inherit,override']],
            ['contact_mode.in' => 'Escolha herdar, personalizar ou deixar sem contato.'],
        );

        if ($values['contact_mode'] === EmployeeAssignment::INHERIT) {
            return new self(EmployeeAssignment::INHERIT, ['email' => null, 'phone' => null, 'whatsapp' => null]);
        }

        $contacts = ContactInput::normalize([
            'email' => $input['work_email'] ?? null,
            'phone' => $input['work_phone'] ?? null,
            'whatsapp' => $input['work_whatsapp'] ?? null,
        ]);

        $valid = Validator::validate(
            [
                'work_email' => $contacts['email'],
                'work_phone' => $contacts['phone'],
                'work_whatsapp' => $contacts['whatsapp'],
            ],
            self::rules(),
            self::messages(),
        );

        return new self(EmployeeAssignment::OVERRIDE, [
            'email' => $valid['work_email'] ?? null,
            'phone' => $valid['work_phone'] ?? null,
            'whatsapp' => $valid['work_whatsapp'] ?? null,
        ]);
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'work_email' => ['nullable', 'email', 'max:255'],
            'work_phone' => ['nullable', 'string', 'max:30'],
            'work_whatsapp' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'work_email.email' => 'Informe um e-mail profissional válido.',
            'work_email.max' => 'O e-mail deve ter até 255 caracteres.',
            'work_phone.max' => 'O telefone deve ter até 30 caracteres.',
            'work_whatsapp.max' => 'O WhatsApp deve ter até 30 caracteres.',
        ];
    }
}

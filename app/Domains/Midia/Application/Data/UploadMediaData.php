<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Data;

use App\Domains\Midia\Domain\Enums\MediaPurpose;
use App\Domains\Midia\Domain\Exceptions\InvalidMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Envio de uma imagem. Aqui só o formato do pedido é validado: o arquivo foi recebido, o uso é do catálogo e os textos
 * cabem. O conteúdo do arquivo é inspecionado depois, pelas barreiras anteriores à decodificação. O dono (empresa ou
 * unidade) vem da URL e nunca do corpo do pedido.
 */
final readonly class UploadMediaData
{
    public function __construct(
        public UploadedFile $file,
        public MediaPurpose $purpose,
        public ?string $altText,
        public ?string $licenseNote,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws InvalidMedia
     * @throws ValidationException
     */
    public static function fromInput(array $input, ?UploadedFile $file): self
    {
        if ($file === null) {
            throw InvalidMedia::file('Envie uma imagem. Se o arquivo for grande, confira o limite de 5 MB.');
        }

        if (! $file->isValid()) {
            throw InvalidMedia::file(match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite de envio de 5 MB.',
                UPLOAD_ERR_PARTIAL => 'O envio foi interrompido. Tente de novo.',
                default => 'Não foi possível receber o arquivo. Tente de novo.',
            });
        }

        foreach (['alt_text', 'license_note'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim(preg_replace('/\s+/', ' ', strip_tags($input[$field])) ?? '');
            }
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        return new self(
            file: $file,
            purpose: MediaPurpose::from($values['purpose']),
            altText: $values['alt_text'] ?? null,
            licenseNote: $values['license_note'] ?? null,
        );
    }

    /** @return array<string,list<mixed>> */
    public static function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(MediaPurpose::class)],
            'alt_text' => ['nullable', 'string', 'max:300'],
            'license_note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'purpose.required' => 'Escolha o uso da imagem.',
            'purpose.enum' => 'Escolha um uso da lista.',
            'alt_text.max' => 'O texto alternativo deve ter até 300 caracteres.',
            'license_note.max' => 'A licença ou o crédito deve ter até 300 caracteres.',
        ];
    }
}

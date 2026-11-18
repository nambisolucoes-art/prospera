<?php

namespace App\Domains\Usuarios\Application\Data;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

final readonly class ProfilePhotoData
{
    private function __construct(public UploadedFile $photo) {}

    public static function fromFile(mixed $photo): self
    {
        $data = Validator::validate(['photo' => $photo], [
            'photo' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb'), 'dimensions:max_width=4096,max_height=4096'],
        ], [
            'photo.required' => 'Selecione uma foto.',
            'photo.mimes' => 'Use uma foto JPG, PNG ou WebP.',
            'photo.max' => 'A foto deve ter até 5 MB.',
            'photo.dimensions' => 'A foto deve ter até 4096 pixels por lado.',
            'photo.uploaded' => 'Não foi possível enviar a foto.',
        ]);

        return new self($data['photo']);
    }
}

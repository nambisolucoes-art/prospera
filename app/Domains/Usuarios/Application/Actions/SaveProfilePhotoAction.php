<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Usuarios\Application\Data\ProfilePhotoData;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SaveProfilePhotoAction
{
    public function execute(User $user, ProfilePhotoData $data): void
    {
        $source = @imagecreatefromstring($data->photo->getContent());

        if ($source === false) {
            throw ValidationException::withMessages(['photo' => 'Não foi possível ler a imagem.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $crop = min($width, $height);
        $size = min($crop, 320);
        $avatar = imagecreatetruecolor($size, $size);
        imagealphablending($avatar, false);
        imagesavealpha($avatar, true);

        // Recorta o quadrado central e reduz para no máximo 320 px.
        imagecopyresampled(
            $avatar,
            $source,
            0,
            0,
            intdiv($width - $crop, 2),
            intdiv($height - $crop, 2),
            $size,
            $size,
            $crop,
            $crop,
        );

        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Não foi possível preparar a foto do perfil.');
        }

        $version = (string) Str::uuid();
        $disk = Storage::disk('local');
        $path = User::profilePhotoPath($user->id, $version);

        try {
            if (! imagewebp($avatar, $stream, 92) || ftell($stream) === 0) {
                throw new RuntimeException('Não foi possível converter a foto do perfil.');
            }

            rewind($stream);

            if (! $disk->put($path, $stream)) {
                throw new RuntimeException('Não foi possível armazenar a foto do perfil.');
            }

            $previous = $user->getConnection()->transaction(function () use ($user, $version) {
                $current = User::query()->lockForUpdate()->findOrFail($user->id);
                $previous = $current->profile_photo;
                $current->profile_photo = $version;
                $current->saveOrFail();

                return $previous;
            });
        } catch (Throwable $failure) {
            $disk->delete($path);

            throw $failure;
        } finally {
            fclose($stream);
        }

        if ($previous !== null) {
            $disk->delete(User::profilePhotoPath($user->id, $previous));
        }
    }
}

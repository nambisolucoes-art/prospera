<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Contracts;

/**
 * O único ponto de contato do domínio com o armazenamento. Caminhos são opacos e nunca vêm do cliente. Trocar o disco
 * (S3, CDN) é trocar a implementação, sem alterar Actions (PD2-05 segue pendente para produção).
 */
interface MediaStorage
{
    /** Grava o original, copiando o arquivo temporário do envio. */
    public function putOriginal(string $path, string $sourceFile): void;

    public function putVariant(string $path, string $contents): void;

    public function deleteOriginal(string $path): void;

    public function deleteVariant(string $path): void;

    /** @return resource|null */
    public function readOriginal(string $path);

    /** @return resource|null */
    public function readVariant(string $path);

    public function originalExists(string $path): bool;

    public function variantExists(string $path): bool;

    /** @return list<array{path: string, modified: int}> */
    public function listOriginals(): array;

    /** @return list<array{path: string, modified: int}> */
    public function listVariants(): array;
}

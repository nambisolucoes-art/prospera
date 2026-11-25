<?php

declare(strict_types=1);

namespace App\Domains\Auditoria\Domain\Contracts;

interface Auditable
{
    public static function auditType(): string;

    /** @return array<string,string> */
    public static function auditedFields(): array;

    /** @return array<string,string> */
    public static function unauditedFields(): array;

    public function auditAccountId(): ?int;

    public function auditCompanyId(): ?int;

    public function auditEstablishmentId(): ?int;

    /**
     * Nomes legíveis dos itens de um campo de relacionamento, na ordem dos ids, ou null se o campo não for um.
     *
     * @param  list<int>  $ids
     * @return list<string>|null
     */
    public function auditRelationNames(string $field, array $ids): ?array;
}

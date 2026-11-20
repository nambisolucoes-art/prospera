<?php

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
}

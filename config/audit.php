<?php

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Auditoria\Domain\Models\AuditRecord;

return [
    'entities' => ['account' => 'Conta', 'point' => 'Ponto', 'person' => 'Pessoa', 'supplier' => 'Fornecedor', 'customer' => 'Cliente', 'user' => 'Usuário', 'role' => 'Cargo', 'tag' => 'Etiqueta', 'permission-catalog' => 'Catálogo de permissões'],
    'events' => ['created' => 'Cadastro', 'updated' => 'Alteração', 'deleted' => 'Exclusão', 'login' => 'Entrada', 'logout' => 'Saída', 'inspection-started' => 'Inspeção iniciada', 'inspection-ended' => 'Inspeção encerrada', 'catalog-synchronized' => 'Sincronização'],
    'excluded_models' => [
        AuditRecord::class => 'Histórico imutável, sem auditoria recursiva.',
        Permission::class => 'Catálogo gerado pelo código, auditado por SyncPermissionsAction.',
    ],
];

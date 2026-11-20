<?php

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Auditoria\Domain\Models\AuditRecord;

return [
    'entities' => [
        'account' => 'Conta',
        'company' => 'Empresa',
        'establishment' => 'Estabelecimento',
        // Histórico anterior à E04: o cadastro de pontos foi removido (DA-14).
        'point' => 'Ponto',
        'person' => 'Pessoa',
        'supplier' => 'Fornecedor',
        'customer' => 'Cliente',
        'user' => 'Usuário',
        'role' => 'Cargo',
        'account-membership' => 'Vínculo com a conta',
        'role-assignment' => 'Atribuição de cargo',
        'role-availability' => 'Abrangência do cargo',
        'tag' => 'Etiqueta',
        'permission-catalog' => 'Catálogo de permissões',
    ],
    'events' => [
        'created' => 'Cadastro',
        'updated' => 'Alteração',
        'deleted' => 'Exclusão',
        'login' => 'Entrada',
        'logout' => 'Saída',
        'inspection-started' => 'Inspeção iniciada',
        'inspection-ended' => 'Inspeção encerrada',
        'password-reset-requested' => 'Redefinição de senha solicitada',
        'invitation-requested' => 'Reenvio de convite solicitado',
        'catalog-synchronized' => 'Sincronização',
    ],
    'excluded_models' => [
        AuditRecord::class => 'Histórico imutável, sem auditoria recursiva.',
        Permission::class => 'Catálogo gerado pelo código, auditado por SyncPermissionsAction.',
    ],
];

<?php

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Plataforma\Domain\Enums\AccountInspectionMode;

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
        'account-invitation' => 'Convite para a conta',
        'role-assignment' => 'Atribuição de cargo',
        'role-availability' => 'Abrangência do cargo',
        'tag' => 'Etiqueta',
        'department' => 'Departamento',
        'job-title' => 'Função',
        'employee' => 'Colaborador',
        'employee-assignment' => 'Lotação',
        'profile-section' => 'Seção do perfil institucional',
        'public-category' => 'Categoria pública',
        'permission-catalog' => 'Catálogo de permissões',
    ],
    'events' => [
        'created' => 'Cadastro',
        'updated' => 'Alteração',
        'deleted' => 'Exclusão',
        'published' => 'Publicação',
        'login' => 'Entrada',
        'logout' => 'Saída',
        'inspection-started' => 'Inspeção iniciada',
        'inspection-ended' => 'Inspeção encerrada',
        'password-reset-requested' => 'Redefinição de senha solicitada',
        'invitation-requested' => 'Reenvio de convite solicitado',
        'invitation-sent' => 'Convite enviado',
        'catalog-synchronized' => 'Sincronização',
    ],
    // Origem da operação: quem a executou, não o nó organizacional afetado.
    'origins' => [
        'account' => 'Conta',
        'platform' => 'Plataforma SaaS',
        'platform-account' => 'Inspeção da plataforma',
        'system' => 'Sistema',
    ],
    'levels' => [
        'platform' => 'Plataforma SaaS',
        'tenant' => 'Conta',
        'company' => 'Empresa',
        'establishment' => 'Estabelecimento',
    ],
    // Rótulos das opções por tipo e campo, aplicados na leitura; o valor gravado no histórico não muda.
    'values' => [
        'account' => [
            'mode' => collect(AccountInspectionMode::cases())
                ->mapWithKeys(fn (AccountInspectionMode $mode) => [$mode->value => $mode->label()])
                ->all(),
            'person_type' => ['company' => 'Pessoa jurídica', 'individual' => 'Pessoa física'],
        ],
        'person' => ['person_type' => ['company' => 'Pessoa jurídica', 'individual' => 'Pessoa física']],
        'supplier' => ['person_type' => ['company' => 'Pessoa jurídica', 'individual' => 'Pessoa física']],
        'company' => [
            'person_type' => ['company' => 'Pessoa jurídica', 'individual' => 'Pessoa física'],
            'tax_regime' => Company::TAX_REGIMES,
        ],
        'tag' => [
            'module' => [
                'people' => 'Cadastro anterior',
                'customers' => 'Clientes',
                'suppliers' => 'Fornecedores',
                'employees' => 'Colaboradores',
            ],
            'system_key' => [
                'user' => 'Usuário',
                'supplier' => 'Fornecedor',
                'customer' => 'Cliente',
                'employee' => 'Colaborador',
            ],
        ],
        'profile-section' => [
            'section' => [
                'logo' => 'Logotipo', 'palette' => 'Paleta de cores', 'typography' => 'Tipografia', 'about' => 'Sobre',
                'contacts' => 'Contatos públicos', 'social_links' => 'Redes sociais', 'banners' => 'Banners',
                'gallery' => 'Galeria', 'seo_defaults' => 'SEO padrão', 'team_display' => 'Equipe exibida',
            ],
            'mode' => ['inherit' => 'Herdar', 'override' => 'Personalizado', 'disabled' => 'Desativado'],
            'published_mode' => ['inherit' => 'Herdar', 'override' => 'Personalizado', 'disabled' => 'Desativado'],
        ],
        'role' => ['system_key' => ['administrator' => 'Administrador da conta', 'member' => 'Usuário']],
        'account-invitation' => [
            'status' => ['pending' => 'Pendente', 'accepted' => 'Aceito', 'revoked' => 'Revogado'],
            'scope' => ['tenant' => 'Toda a conta', 'company' => 'Empresa', 'establishment' => 'Estabelecimento'],
        ],
    ],
    'excluded_models' => [
        AuditRecord::class => 'Histórico imutável, sem auditoria recursiva.',
        Permission::class => 'Catálogo gerado pelo código, auditado por SyncPermissionsAction.',
    ],
];

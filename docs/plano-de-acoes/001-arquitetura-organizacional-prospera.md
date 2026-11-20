# 001: Arquitetura organizacional do Prospera

| Campo | Valor |
| --- | --- |
| Documento | Plano oficial de execução da arquitetura multi-tenant, multiempresa e multiestabelecimento |
| Situação do documento | Aprovado. E01 concluída em 19/11/2026. E02 em validação desde 20/11/2026 |
| Base de código auditada | Branch `feat/filtros-calendario-titulos`, commit `76c698c` |
| Data da auditoria complementar | 19/11/2026 |
| Revisão | 5 (20/11/2026), em validação. Revisão 4 (19/11/2026): fechamento da E01 com dois refinamentos obrigatórios para a E02 (identidade global sem alteração direta pelo tenant; contatos centrais separados dos contatos da relação comercial). As revisões 2 e 3 seguem válidas. Detalhes no registro da seção 26 |
| Revisão 5 | Execução da E02 (contrato organizacional). Convenções publicadas em `docs/convencoes/`, `AGENTS.md` emendado e nomenclaturas fechadas (DA-26 a DA-30). Seções 6.2, 6.3, 7.2, 8.2.1, 10.2.1, 12.1, 12.2, E04, R07, 26, 27 e 28 ajustadas. Detalhes no registro da seção 26 |
| Antecessor | Relatório de auditoria arquitetural apresentado na sessão anterior (não versionado) |
| Regra de uso | Executar uma etapa por vez, somente após autorização explícita |

Legenda usada em todo o documento:

- **[C]** comprovado no código ou no banco local nesta auditoria.
- **[P]** parcialmente implementado.
- **[A]** ausente.
- **[R]** recomendação arquitetural deste plano.
- **[DP]** decisão de produto já tomada no briefing.
- **[PD]** pendente de decisão (seção 25).

## Índice

1. [Resumo executivo](#01-resumo-executivo)
2. [Objetivos e limites](#02-objetivos-e-limites)
3. [Diagnóstico do projeto atual](#03-diagnóstico-do-projeto-atual)
4. [Decisões de produto e arquiteturais](#04-decisões-de-produto-e-arquiteturais)
5. [Arquitetura organizacional](#05-arquitetura-organizacional)
6. [Modelo jurídico e estabelecimentos](#06-modelo-jurídico-e-estabelecimentos)
7. [Identidade e vínculos](#07-identidade-e-vínculos)
8. [RBAC contextual](#08-rbac-contextual)
9. [Cargos, disponibilidade e atribuições](#09-cargos-disponibilidade-e-atribuições)
10. [Propriedade e compartilhamento dos dados](#10-propriedade-e-compartilhamento-dos-dados)
11. [Auditoria contextual](#11-auditoria-contextual)
12. [Contexto de navegação](#12-contexto-de-navegação)
13. [Segurança e isolamento](#13-segurança-e-isolamento)
14. [Laravel Octane e FrankenPHP](#14-laravel-octane-e-frankenphp)
15. [Contrato de performance](#15-contrato-de-performance)
16. [Banco de dados e índices](#16-banco-de-dados-e-índices)
17. [Componentes e padrões UBold](#17-componentes-e-padrões-ubold)
18. [Telas administrativas e sidebar](#18-telas-administrativas-e-sidebar)
19. [Plano incremental](#19-plano-incremental)
20. [Matriz de dependências](#20-matriz-de-dependências)
21. [Matriz de testes](#21-matriz-de-testes)
22. [Critérios gerais de aceite](#22-critérios-gerais-de-aceite)
23. [Estratégia de migração](#23-estratégia-de-migração)
24. [Riscos e reversão](#24-riscos-e-reversão)
25. [Decisões pendentes](#25-decisões-pendentes)
26. [Quadro de acompanhamento](#26-quadro-de-acompanhamento)
27. [Contrato para módulos futuros](#27-contrato-para-módulos-futuros)
28. [Registro de decisões](#28-registro-de-decisões)

---

## 01. Resumo executivo

O Prospera tem uma base sólida para evoluir sem reescrita:

- RBAC com catálogo descoberto no código.
- Auditoria central com `operation_id`.
- Padrão Data, Action, Query e DTO.
- Componentes de listagem e formulário reutilizáveis.
- Aplicação sem estado estático mutável, segura para o modelo persistente do Octane.

A estrutura organizacional, porém, ainda é de **um tenant, uma empresa implícita e nenhum estabelecimento real**:

- **[C]** A conta (`clients`) também faz o papel de "estabelecimento" na interface.
- **[C]** A tabela `establishments` existe, mas está vazia e não tem model.
- **[C]** `points` é um exemplo didático do padrão de código, sem regra de negócio.
- **[C]** Cada usuário pertence a uma conta (`users.account_id`) e tem um cargo (`users.role_id`).
- **[C]** A autorização reconsulta o banco a cada checagem. Na Central de Ajuda, isso gera 26 queries, 13 delas idênticas.

O plano introduz, em nove etapas de implementação incrementais e reversíveis:

- **Empresas** (pessoa jurídica) e **Estabelecimentos** (unidade operacional), com provisionamento automático e transacional da unidade principal.
- **Vínculos** entre identidade e tenant (`account_memberships`), preparando uma mesma identidade para vários tenants.
- **Disponibilidade de cargos** e **atribuições contextuais** em três escopos (tenant, empresa e estabelecimento), com herança formal. Cada permissão declara os **tipos de recurso-alvo** que admite, e a checagem usa o nó do recurso realmente afetado. Isso impede que um acesso operacional vire acesso administrativo e permite que administradores de empresa e de estabelecimento administrem o próprio escopo.
- Um **resolvedor de permissões efetivas** com número fixo de queries por requisição (meta: no máximo 2 queries de autorização, independentemente do número de checagens). Linhas retornadas, tempo de processamento e memória também têm orçamentos medidos, porque uma query não equivale a custo constante.
- Um **contexto organizacional** resolvido pela URL e validado no servidor, seguro para várias abas e para os workers do Octane.
- **Auditoria contextual** filtrada no banco pelo escopo autorizado, consolidando a trilha legada `DirectoryAudit`.

A padronização visual (UBold) é distribuída nas etapas que já alteram cada tela, para evitar reescrever a mesma tela duas vezes.

---

## 02. Objetivos e limites

### Objetivos

1. Estabelecer o contrato organizacional (tenant, empresa, estabelecimento) reutilizado por todos os módulos.
2. Evoluir o RBAC existente para autorização contextual, sem criar um segundo motor.
3. Garantir isolamento entre tenants, empresas e estabelecimentos no backend, inclusive em buscas, contagens, filtros e exportações.
4. Tornar performance um requisito verificável: linha de base, orçamento de queries e índices justificados.
5. Manter a experiência simples para tenants com uma empresa e uma unidade.

### Limites desta rodada

- Somente este documento foi criado. Nenhum código, migration, dado ou commit foi alterado.
- Fora do escopo do plano: emissão fiscal, produtos, estoque, financeiro, PDV e transferências. O plano só garante que a fundação os comporte (seção 27).
- A infraestrutura de produção não foi acessada. Os números de dados e de desempenho vêm do ambiente local e de dados sintéticos.

---

## 03. Diagnóstico do projeto atual

### 3.1 Ambiente

| Item | Valor encontrado | Fonte |
| --- | --- | --- |
| PHP | 8.5.13 ZTS (container), 8.5.12 NTS (host) | `php -v` no container e no host |
| Laravel | 13.43.0 | `composer.lock` e `artisan --version` |
| Octane | 2.25.0 | `composer.lock` |
| FrankenPHP | 1.13.6 (Caddy 2.11.9) | `frankenphp version` no container |
| Fortify | 1.43.0 | `composer.lock` |
| Banco | PostgreSQL 18.7 (aplicação); SQLite em memória (testes) | `select version()`; `tests/bootstrap.php` |
| Execução | `octane:frankenphp --workers=2`; `max-requests` padrão 500 | `Dockerfile`, `StartFrankenPhpCommand` |
| Sessão, cache e fila | driver `database` | `.env.example`, `config/queue.php` |
| Uso de cache, jobs e filas no código | nenhum | busca por `Cache::`, `dispatch(`, `ShouldQueue` em `app/` |
| Análise estática | Larastan 3.15, PHPStan 2.3.6, Pint, PHPUnit 12.5 | `composer.lock` |

### 3.2 Mudanças desde a auditoria anterior

Posteriormente à auditoria anterior, entraram três commits (`e826f7a`, `30613c0`, `76c698c`):

- Atalhos `make`.
- Barra de filtros padronizada (`BusinessListSearch`, `useBusinessListFilters`).
- Filtros de Etiquetas e Cargos (`TagFiltersData`, `RoleFiltersData`, `PeopleDirectoryQuery::tagList`, `AccessDirectoryQuery::roleList`).
- Componente `DatePicker`.
- Títulos no padrão Visdom.

Nenhum deles altera a estrutura organizacional. Todos os achados anteriores foram reconfirmados, com dois ajustes de precisão:

- A "conta atual" é lida diretamente do usuário em **29 pontos, distribuídos em 10 arquivos**. No total, há **41 ocorrências de `->account_id` em 15 arquivos**. O número 44 da auditoria anterior usava um filtro mais amplo.
- `DirectoryAudit::record` continua sendo chamado em 5 pontos.

### 3.3 Estrutura organizacional existente

```text
PROSPERA
├── Plataforma: users.is_saas_admin; permissions (scope platform | account | self)
│   └── Inspeção de conta: troca de sessão para um usuário da conta, auditada
├── Identidade: users (e-mail único global, account_id, role_id, person_id únicos)
└── Conta = clients (nome, CPF/CNPJ único global, plano, active, details JSON)
    ├── points                 exemplo didático (nome, active)
    ├── establishments         tabela vazia, sem model (client_id)
    ├── roles -> permission_role
    ├── people -> person_tag -> tags
    │   ├── customers (1:1 com person, por conta)
    │   └── suppliers (1:1 com person, por conta)
    ├── users (account_id)
    └── audit_records (account_id) + directory_audit_events (legado, ainda gravado)
```

### 3.4 Achados por tema

| # | Tema | Achado | Evidência |
| --- | --- | --- | --- |
| A01 | Tenant | [C] `clients` é o tenant. A interface chama a conta de "estabelecimento". `account.establishment.update` edita o próprio `Client` | `ClientController::editTenant/updateTenant`, `routes/saas.php` |
| A02 | Dados fiscais do tenant | [C] CPF/CNPJ em `clients.document` (único global); endereço e contatos em `clients.details` (JSON) | migration `2026_11_17_000002`, `Client::auditedFields` |
| A03 | Estabelecimentos | [C] Tabela `establishments(client_id, name)` criada em `2026_11_17_000001`, sem model, rota ou tela; 0 linhas no banco local | migrations, `pg` |
| A04 | Points | [C] Exemplo do padrão Data, Action, DTO e Model ("O cadastro de pontos é uma referência curta", README). Nenhum módulo depende dele. Permissões `account.points.list/create/update`; o cargo inicial Usuário recebe `account.points.list`. Foi religado de `establishments` para `clients` em `2026_11_17_000004`; `establishment_id` ficou como legado. 0 linhas no banco local | `README.md`, `DefaultRolesAction`, `tenant.tsx`, testes `SaasPlatformTest`, `AccountInspectionTest` |
| A05 | Identidade | [C] Um usuário pertence a uma conta e tem um cargo e uma pessoa. O e-mail é único global | `users` (migrations `0001`, `2026_11_17_000003`, `2026_11_18_000002`), `User` |
| A06 | Conta implícita | [C] 29 leituras de `user()->account_id`, `user()->account()` ou `$user->account_id` em 10 arquivos | `DirectoryController` (10), `AccessController` (4), `AssignRoleAction` (4), `UserController` (3), outros |
| A07 | RBAC | [C] Catálogo descoberto no código (`PermissionRegistry`), slugs `escopo.modulo.acao`, `Gate::before` restrito ao padrão de slug, proteção contra escalada em `SaveRoleAction` e `AssignRoleAction`, último administrador protegido | `AppServiceProvider`, `Acessos/*` |
| A08 | Custo do RBAC | [C] Cada `can()` executa `User::permissionSlugs()`: 1 query de conta ativa e 1 de permissões. A Central de Ajuda faz 26 queries, 13 idênticas | Medição da seção 15.2 |
| A09 | Administrador | [C] `DefaultRolesAction` acrescenta ao cargo `administrator` todas as permissões `account` e `self` em cada sincronização. Nunca inclui `platform` | `DefaultRolesAction::execute` |
| A10 | Auditoria | [C] Central (`audit_records`, `Auditable`, `AuditsChanges`, `AuditLog::transaction`, `AuditContext` com binding scoped), filtrada por `account_id`; opções de filtro e detalhe usam a mesma consulta com escopo | `Auditoria/*` |
| A11 | Trilha paralela | [C] `DirectoryAudit` grava em `directory_audit_events` em 5 pontos (`AssignRoleAction`, `SaveRoleAction`, `CreateUserAction` e outros), duplicando eventos já registrados por `AuditsChanges` | `grep DirectoryAudit::record` |
| A12 | Auditoria da plataforma | [C] `/saas/auditoria` não filtra conta (`AuditQuery::scoped` com `platform = true`). O administrador SaaS vê os eventos de negócio de todos os tenants sem inspeção | `AuditQuery::scoped` |
| A13 | Inspeção | [C] Troca de sessão para um usuário da conta, nos modos Somente leitura e Leitura e edição. A autoria registra o administrador e o usuário representado. Escritas fora de `account.*` são bloqueadas | `StartAccountInspectionAction`, `EnforceAccountInspection`, `AuditContext::begin` |
| A14 | Clientes e fornecedores | [C] `customers` e `suppliers` são 1:1 com a pessoa e pertencem à conta (`person_id` único); não há empresa | migrations `2026_11_18_000007` e `000010` |
| A15 | Documento fiscal | [C] Dois validadores divergentes: `SavePersonDetailsData::validDocument` aceita CNPJ alfanumérico; `SaveAccountData::validDocument` aceita só dígitos | os dois arquivos |
| A16 | Índices | [C] Sem índice em FKs muito consultadas: `permission_role.permission_id`, `person_tag.tag_id` (usado em `withCount('people')`), `users.role_id`, `customers.account_id`, `suppliers.account_id` | `pg_indexes` (seção 16.1) |
| A17 | Navegação | [C] A sidebar da conta tem Minha conta, Pessoas, Etiquetas, Cargos, Auditoria e Central de Ajuda. `/conta/usuarios` existe, mas não está na sidebar. As configurações pessoais ficam em `/settings` (Perfil, Segurança, Aparência), pelo menu do avatar. Não existe tela de sessões | `accountNavigation.ts`, `TopBarProfileDropdown.tsx`, `SettingsLayout.tsx` |
| A18 | Interface | [C] Telas de negócio usam `BusinessList`, `BusinessForm`, `SelectControl`, `Modal`, `PageHeader` e `CardBox`. A exceção é `tenant.tsx` (Minha conta e Pontos), que usa `<details>` e `AccountForm` | inventário de imports |
| A19 | Octane | [C] Não há singletons de domínio nem estado estático mutável. O único serviço `scoped` é `AuditContext`. A configuração inclui `FlushOnce` e `FlushTemporaryContainerInstances` | `AppServiceProvider`, `config/octane.php` |
| A20 | Identidade e pessoa acopladas | [C] Editar nome ou e-mail da pessoa propaga para a identidade (`SavePersonAction` chama `User::applyIdentity`). A verificação "Já existe um usuário com este e-mail" (`SavePersonAction`, `CreateUserAction`) consulta `users` de todos os tenants, então revela a existência do e-mail em outra conta | `SavePersonAction`, `CreateUserAction` |

### 3.5 O que será reaproveitado

- **Banco:** `clients` (tenant); `establishments` (evoluída); `roles`, `permissions` e `permission_role`; `audit_records`; `people`, `tags`, `customers` e `suppliers`.
- **Backend:**
  - `PermissionRegistry`, `SyncPermissionsAction` e `DefaultRolesAction` (ajustada);
  - as regras de antiescalada de `SaveRoleAction` e `AssignRoleAction`;
  - `AuditLog`, `AuditContext` e `Auditable`;
  - o padrão Data, Action, Query e DTO;
  - `PageSize`, `ApiPage` e as Queries por domínio.
- **Frontend:**
  - `BusinessList` e `BusinessListSearch`, `useBusinessListFilters`, `BusinessForm`;
  - `SelectControl`, `SelectChecklist` e `TagSelect`, `Modal`, `PreviewSection`, `StatusLabel`, `PageHeader`;
  - `GuidedTour`, `BusinessListTour` e `PageHeaderTour`;
  - os campos de identidade, endereço e contato de Pessoas.

---

## 04. Decisões de produto e arquiteturais

Decisões consolidadas. O registro formal está na seção 28.

| Código | Decisão | Origem |
| --- | --- | --- |
| DA-01 | Hierarquia Tenant > Empresa > Estabelecimento. `clients` continua sendo o tenant (nome físico preservado; conceito "Conta") | [DP] + [R] |
| DA-02 | Empresa é entidade própria (`companies`), proprietária lógica do catálogo comercial, das relações comerciais e do financeiro | [DP] |
| DA-03 | Toda empresa tem pelo menos um estabelecimento ativo. A unidade principal é provisionada na mesma transação da empresa | [DP] |
| DA-04 | Matriz e filial são atributos explícitos do estabelecimento, nunca inferidos do número de ordem do CNPJ | [R] |
| DA-05 | Documento fiscal tratado por um objeto de valor único (`TaxDocument`): raiz, ordem e dígitos, com suporte a CNPJ alfanumérico; a comparação de pessoa jurídica é feita pela raiz | [R] |
| DA-06 | Identidade global (`users`) e vínculo por tenant (`account_memberships`) com pessoa própria em cada tenant. `users.account_id`, `role_id` e `person_id` viram legado e são removidos ao final | [DP] + [R] |
| DA-07 | O RBAC evolui com três escopos de atribuição (tenant, empresa e estabelecimento). Cada permissão declara os tipos de recurso-alvo admitidos (`tenant`, `company`, `establishment`, `person`). A checagem é feita contra o nó do recurso afetado, e não contra um nível fixo por slug (revisão 2, seção 8.2) | [DP] + [R] |
| DA-08 | Disponibilidade do cargo e atribuição são tabelas distintas. Disponibilidade nunca concede acesso | [DP] |
| DA-09 | O Administrador integral do tenant continua recebendo automaticamente todas as permissões de escopo `account` e `self`, nunca as de `platform` | [DP] |
| DA-10 | Permissões efetivas resolvidas uma vez por requisição em um serviço `scoped`, sem cache persistente. A revogação vale na requisição seguinte | [R] |
| DA-11 | O contexto organizacional vem da URL e é validado no servidor. A sessão nunca é autoridade de contexto | [R] |
| DA-12 | Auditoria com `company_id` e `establishment_id` opcionais, **gravados desde a E04** em todo evento organizacional novo. Eventos administrativos ficam sem unidade fictícia. A visibilidade filtrada no banco, os filtros e a consolidação ficam para a E08 | [DP] + [R] |
| DA-13 | `DirectoryAudit` e `directory_audit_events` são consolidados em `audit_records` | [R] |
| DA-14 | `points` é removido de forma controlada (exemplo didático sem regra de negócio). Ponto de venda e caixa serão entidades próprias do futuro módulo de PDV | [R] (com aprovação, PD-05) |
| DA-15 | Clientes e fornecedores passam a ser relações comerciais por empresa. A pessoa continua única no tenant | [DP] + [R] |
| DA-16 | O monólito modular continua em `app/Domains`, com dois domínios novos: `Organizacao` e o contexto de acesso dentro de `Acessos` | [R] |
| DA-17 | A padronização visual é transversal: cada etapa entrega suas telas já no padrão, sem uma etapa separada de reescrita | [R] |
| DA-18 | Identificação fiscal de empresa e estabelecimento em colunas próprias, e não em Pessoas. É uma exceção justificada à regra do AGENTS.md (PD-01) | [R] |
| DA-19 | Administração de usuários em três camadas (seção 8.5): **identidade global** só pela própria pessoa, pelos fluxos seguros, e pela plataforma (sem exceção para vínculo único, DA-24); **vínculo com o tenant** (criar, suspender, revogar) só por atribuição de escopo tenant; **atribuições locais** por qualquer administrador sobre os nós que ele gerencia, mesmo que a pessoa atue em outras empresas, sem ver nem alterar as atribuições de fora do seu escopo | [R] Aprovada com ajuste |
| DA-20 | Pessoas (revisão 3, seção 10.2): leitura do cadastro central compartilhada no tenant; contatos e endereço editáveis por quem atende a pessoa em pelo menos uma empresa; identificação (nome, tipo, documento, inscrições) editável sem restrição só quando a pessoa não tem relação com empresas fora do alcance do editor, caso contrário exige escopo tenant; dados comerciais por empresa, editados só na empresa | [R] Aprovada com ajuste |
| DA-21 | Invariantes organizacionais garantidos em camadas: FK composta e índice parcial (pertencimento e unicidade), CHECK (consistência da própria linha), transação com trava da linha da empresa (concorrência), regra de domínio (contagens entre linhas) e verificação de consistência (detecção). Sem exclusão física de empresas e estabelecimentos na v1 (seção 6.6) | [R] |
| DA-22 | Contratante, tenant, empresa e estabelecimento têm unicidades distintas. Empresas e estabelecimentos são únicos por tenant (PD-03 aprovada). `clients.document` mantém a unicidade global como identificação do contratante, avaliada separadamente (PD-07) | [R] |
| DA-23 | Cada migration é classificada como reversível, reversível com perda ou de recuperação somente por backup. Etapas com ponto de não retorno usam janela de reversão, backup verificado e correção para frente (seção 23.1) | [R] |
| DA-24 | Identidade autenticada, cadastro de pessoa e vínculo organizacional são separados. Nenhum administrador de tenant altera a identidade de login, mesmo com vínculo único. Alteração de e-mail e recuperação de acesso só pelos fluxos seguros da própria pessoa (revisão 4) | [DP] |
| DA-25 | Contatos centrais da pessoa separados dos dados e contatos da relação comercial de cada empresa. Alteração local não modifica dados comerciais de outras empresas. A política campo a campo de edição e visibilidade é fechada na E02 (revisão 4) | [DP] |
| DA-26 | O contrato organizacional é publicado em convenções separadas por assunto (`contexto-organizacional.md`, `identidade-e-vinculos.md`, `pessoas-e-contatos.md`, `performance.md`, `checklist-modulos.md`), além de `permissoes.md` e `auditoria.md` atualizadas, e referenciado no `AGENTS.md` (revisão 5) | [R] Em validação |
| DA-27 | Nomes de código: domínio `App\Domains\Organizacao`; `App\Support\TaxDocument`; serviços `scoped` em `App\Domains\Acessos\Application\Context` (`AccountContext`, `EffectivePermissions`, `OrganizationContext`); middleware `ResolveOrganizationContext`; models `Company`, `Establishment`, `AccountMembership`, `RoleAssignment`, `RoleAvailability`; permissões `account.account-data.update`, `account.establishments.promote`, `account.users.reactivate`, `account.users.reset-password` e `account.users.invite` (revisão 5) | [R] Em validação |
| DA-28 | URLs e identificadores: operação em `/conta/empresa/{empresa}` e `/conta/estabelecimento/{estabelecimento}`, consolidado pelo código reservado `todos`, tenant em `/contas/{conta}` (E09); administração com ID numérico; código com letras minúsculas, números e hífen, de 2 a 40 caracteres, único por tenant, imutável na v1, com palavras reservadas; terminologia de menus da convenção (revisão 5) | [R] Em validação |
| DA-29 | Fluxos de identidade: troca de e-mail só depois da confirmação no novo endereço, com aviso ao anterior e encerramento das demais sessões; respostas neutras também na recuperação de senha e na troca do próprio e-mail; disparo do link de redefinição e reenvio de convite só por escopo tenant (revisão 5) | [R] Em validação |
| DA-30 | Pessoas: classificação dos campos atuais (identificação central, contatos centrais, endereço, dados comerciais por empresa, contatos da relação, dados operacionais restritos), `contact_name` mantido central, seções "Dados centrais" e "Dados desta empresa" só com mais de uma empresa ativa e aviso ao editar contatos centrais de pessoa com várias empresas (revisão 5) | [R] Em validação |

---

## 05. Arquitetura organizacional

### 5.1 Árvore recomendada

```text
PROSPERA
├── Plataforma SaaS (users.is_saas_admin; catálogo de permissões; contas; inspeção)
├── Identidades (users: credenciais, 2FA, passkeys, perfil)
│   └── Vínculos (account_memberships: identidade x tenant x pessoa, situação)
└── Tenant (clients: contrato, plano, situação, contato do contratante)
    ├── Empresas (companies: razão social, raiz fiscal, regime)
    │   └── Estabelecimentos (establishments: matriz/filial, principal, CNPJ, IE, endereço)
    │       └── [futuro] depósitos, PDV, caixas, preços, saldos, vendas, OS
    ├── Cargos (roles + disponibilidade: tenant | empresas | estabelecimentos)
    │   └── Atribuições (vínculo x cargo x escopo)
    ├── Cadastros compartilhados do tenant (pessoas, etiquetas)
    │   └── Relações comerciais por empresa (clientes, fornecedores)
    ├── Configurações da conta
    └── Auditoria (account_id + company_id? + establishment_id?)
```

### 5.2 Conceitos

| Conceito | Definição | Não é |
| --- | --- | --- |
| Tenant (Conta) | Contratante do Prospera e fronteira de isolamento | Empresa jurídica nem estabelecimento |
| Empresa | Pessoa jurídica (ou empresário individual) administrada pelo tenant | Marca, nome fantasia nem unidade |
| Estabelecimento | Unidade operacional da empresa, com ou sem CNPJ próprio | Depósito nem ponto de venda |
| Matriz e filial | Classificação jurídica do estabelecimento dentro da empresa | Sinônimo de "principal" |
| Estabelecimento principal | Unidade padrão de operação da empresa, criada automaticamente | Necessariamente a matriz |
| Nome fantasia e marca | Atributos de apresentação da empresa ou da unidade | Identidade jurídica |
| Depósito (futuro) | Local de estoque de um estabelecimento | Estabelecimento |
| Ponto de venda (futuro) | Terminal ou caixa de um estabelecimento | Estabelecimento |

### 5.3 Invariantes organizacionais

1. Toda empresa pertence a exatamente um tenant. Todo estabelecimento pertence a exatamente uma empresa e ao tenant dela.
2. Toda empresa tem exatamente um estabelecimento principal. Ele pertence à própria empresa e está sempre ativo.
3. Empresa ativa tem pelo menos um estabelecimento ativo (o principal, pelo invariante 2).
4. Cada empresa tem no máximo uma matriz. Estabelecimentos com CNPJ próprio têm a mesma raiz da empresa.
5. Inativar o estabelecimento principal exige promover outro antes. O último estabelecimento ativo só fica inativo se a empresa for inativada.
6. Empresas e estabelecimentos não são excluídos fisicamente na v1, apenas inativados.
7. Nada é compartilhado automaticamente entre tenants. Entre empresas do mesmo tenant, só se compartilham o cadastro base de pessoas e as etiquetas não comerciais (seção 10.2).
8. Operações administrativas da conta não recebem empresa nem estabelecimento fictícios.

A forma de garantir cada invariante está na seção 6.6.

---

## 06. Modelo jurídico e estabelecimentos

### 6.1 Identificação fiscal brasileira

- **Estrutura do CNPJ:** raiz (8 posições), ordem do estabelecimento (4) e dígitos verificadores (2). Desde a IN RFB 2.229/2024, raiz e ordem podem ser alfanuméricas, e os dígitos verificadores continuam numéricos. O cálculo usa o código ASCII menos 48.
- **Matriz e filial:** compartilham a raiz e têm CNPJs completos distintos. A ordem `0001` é usual para a matriz, mas não é regra para inferir a classificação. Por isso a classificação é um campo explícito (DA-04).
- **Comparação:** empresas jurídicas distintas têm raízes distintas. Sócios em comum não unem empresas. Comparar pessoa jurídica é comparar raízes normalizadas pelo `TaxDocument`, nunca strings brutas ou formatadas.
- **Empresário individual e produtor rural:** a empresa pode ter CPF. Nesse caso não há raiz de CNPJ, e os estabelecimentos não têm CNPJ (CAEPF ou inscrição estadual de produtor ficam para o módulo fiscal).

### 6.2 Objeto de valor `TaxDocument` [R]

Consolida os dois validadores divergentes (A15). Fica em `app/Support/TaxDocument.php` (`App\Support\TaxDocument`, novo), local compartilhado fechado na E02 (DA-27), porque é usado por vários domínios. Responsabilidades:

- normalizar (remove a máscara e converte para maiúsculas);
- validar CPF e CNPJ, inclusive alfanumérico;
- expor `type()`, `root()` (CNPJ), `order()` (CNPJ), `isSameLegalEntityAs()` e `formatted()`.

É usado por Pessoas, Conta, Empresas e Estabelecimentos. Os testes cobrem CNPJ numérico, alfanumérico, CPF, sequências repetidas e comparação por raiz.

### 6.3 Dados por nível

| Dado | Empresa | Estabelecimento | Observação |
| --- | --- | --- | --- |
| Razão social | sim | | |
| Nome fantasia | sim (padrão) | sim (opcional, sobrescreve) | |
| Raiz do CNPJ ou CPF | sim | | Única por tenant |
| CNPJ completo | | sim (opcional) | Único por tenant; raiz igual à da empresa |
| Inscrição estadual | | sim (opcional) | Por UF da unidade |
| Inscrição municipal | | sim (opcional) | Por município |
| Regime tributário | sim | | Aplica-se à pessoa jurídica inteira |
| CNAE | principal da empresa | da unidade (opcional, futuro) | |
| Matriz ou filial | | sim | No máximo uma matriz por empresa |
| Endereço e contatos | | sim | A empresa usa o da matriz ou o da unidade principal |
| Situação | sim | sim | |
| Código interno (URL) | sim | sim | Único por tenant; imutável na v1 (E02, DA-28) |

### 6.4 Casos especiais

- **Empresa com um único endereço:** uma empresa, um estabelecimento principal que também é a matriz, com o CNPJ completo da matriz.
- **Estabelecimento sem inscrição própria** (quiosque ou ponto avançado sem CNPJ): é permitido. Não pode ser emitente fiscal, e o futuro módulo fiscal exigirá um estabelecimento emitente com CNPJ e IE.
- **Estabelecimentos com inscrições distintas:** IE e IM ficam na unidade.
- **Emitente fiscal futuro:** é sempre um estabelecimento com CNPJ, da empresa responsável pela operação. O modelo não impede essa representação.
- **Mesmo CNPJ em tenants diferentes:** permitido. A unicidade é por tenant (seção 6.7, PD-03).

### 6.5 Provisionamento da unidade principal

`CreateCompanyAction` (novo), dentro de `AuditLog::transaction()`:

1. Trava a linha do tenant (`clients ... for update`), o mesmo padrão de `AssignRoleAction`.
2. Cria a empresa.
3. Cria o estabelecimento com `is_primary = true`.
   - O endereço e o nome padrão vêm do formulário. O nome sugerido é "Unidade principal" mais o município, e o usuário pode editar.
   - Se houver CNPJ completo, a unidade é marcada como matriz.
4. Registra a auditoria das duas entidades com o mesmo `operation_id`.

Qualquer falha reverte tudo. O índice parcial único `(company_id) where is_primary` impede duas unidades principais, inclusive em concorrência. O provisionamento de um tenant novo (`SaveAccountAction`) chama a mesma Action. Não existe Action separada para "criar principal": é sempre parte da criação da empresa (idempotência pelo próprio índice).

A migração da E04 é idempotente por tenant: só cria a empresa para contas que ainda não têm nenhuma, e roda dentro de transação por conta.

### 6.6 Integridade dos estabelecimentos (revisão 2)

**Por que não usar `companies.primary_establishment_id`:** a garantia "exatamente uma" por FK circular exige restrição adiável (`DEFERRABLE INITIALLY DEFERRED`). No Laravel 13, isso só existe na gramática do PostgreSQL ([C] `Schema/Grammars/PostgresGrammar.php`), e a suíte roda em SQLite por regra do projeto. O modelo adotado usa a flag `is_primary` no estabelecimento. A unicidade fica no banco, e a existência fica em regra de domínio com trava.

| Invariante | Índice ou unicidade | FK | CHECK | Transação e trava | Regra de domínio | Detecção |
| --- | --- | --- | --- | --- | --- | --- |
| Estabelecimento pertence à empresa e ao tenant | `unique(id, account_id)` em `companies` (alvo de FK) | `(company_id, account_id)` → `companies(id, account_id)` | | | | |
| No máximo uma principal por empresa | parcial `unique(company_id) where is_primary` | | | | | |
| A principal pertence à própria empresa | (garantido porque a flag está na linha da própria unidade) | FK acima | | | | |
| A principal está ativa | | | `not is_primary or active` | | | |
| Pelo menos uma principal por empresa | | | | criação da empresa e da principal na mesma transação; promoção na mesma transação | proibido desmarcar a principal sem marcar outra | `organization:check` |
| Empresa ativa com unidade ativa | | | | inativação com trava da empresa | decorre da principal ativa; bloqueio de inativar a principal | `organization:check` |
| No máximo uma matriz | parcial `unique(company_id) where is_headquarters` | | | | | |
| Raiz do CNPJ igual à da empresa | | | | | `TaxDocument::isSameLegalEntityAs` | `organization:check` |

**Concorrência:**
- Toda operação que altera `active` ou `is_primary` de estabelecimentos, ou `active` da empresa, começa com `select ... for update` na linha da empresa. Isso serializa as operações daquela empresa.
- Exemplo do risco: duas inativações simultâneas das duas últimas unidades. Sem a trava, as duas veriam "existe outra ativa" e passariam.
- A promoção desmarca a antiga e marca a nova na mesma transação, nessa ordem, para respeitar o índice parcial, que é verificado por instrução.

**Exclusão:** não há exclusão física de empresas e estabelecimentos na v1 (FKs `restrict`). Uma exclusão futura de registro nunca usado exige regra própria.

**Empresa ou estabelecimento inativo:**
- O middleware de contexto (E06) recusa rotas operacionais de empresa ou unidade inativa.
- Leitura de histórico e auditoria continua disponível para quem tem permissão.
- Telas administrativas permitem consultar e reativar.
- O seletor lista só contextos ativos.
- Antes da E06, como não há rotas operacionais de unidade, a regra vale para as Actions de cadastro (por exemplo, não promover unidade inativa).

**Detecção:** `organization:check` (novo, somente leitura, no mesmo modelo de `audit:check`) lista violações de invariantes que dependem de várias linhas. Roda nos testes, na homologação e opcionalmente na inicialização Docker. Não corrige dados automaticamente.

### 6.7 Unicidade fiscal entre contratante, tenant, empresa e estabelecimento (revisão 2)

| Entidade | Documento | Unicidade proposta | Quem vê o erro de unicidade |
| --- | --- | --- | --- |
| Contratante (dados de contrato do tenant em `clients`) | `clients.document` | **Global [C], mantida** (revisão 3, PD-07): um contratante, um tenant. Um grupo com várias pessoas jurídicas usa um tenant com várias empresas | Somente o administrador SaaS (tenants só são criados e editados pela plataforma; o tenant não edita o documento) |
| Tenant | não tem documento próprio | | |
| Empresa | `companies.tax_root` (CNPJ) ou `companies.individual_document` (CPF) | `unique(account_id, ...)` parcial quando não nulo | Usuários do próprio tenant |
| Estabelecimento | `establishments.tax_document` (CNPJ completo) | `unique(account_id, tax_document)` parcial | Usuários do próprio tenant |
| Pessoa | `people.document` | `unique(account_id, document)` [C] | Usuários do próprio tenant |

Regras:
- Nenhuma unicidade checada em cadastro feito por usuários de tenant pode envolver outro tenant. Assim, a mensagem de erro nunca revela que um documento existe em outra conta. O mesmo vale para o e-mail de identidade (A20), que é único global por natureza e por isso usa resposta neutra (8.5).
- **Avaliação separada de `clients.document` (PD-07, revisão 3):** a unicidade global **é mantida**.
  - Ela não vaza entre tenants: [C] só o fluxo da plataforma valida o documento; `SaveAccountData::fromTenantInput` não aceita `document`.
  - Ela identifica quem contrata e evita contas duplicadas por engano para o mesmo contratante.
  - Com o modelo multiempresa, um grupo com várias pessoas jurídicas cabe num único tenant, então o motivo para duplicar contratantes praticamente desaparece.
  - Ela não conflita com a PD-03, porque a unicidade de empresas e estabelecimentos é por tenant e independe de `clients`.
  - Se surgir o caso de um contratante com vários tenants (por exemplo, um escritório contábil), a solução será uma entidade explícita de contratante, numa decisão própria, e não o relaxamento silencioso da unicidade.
  - Consequência: a E04 não altera `clients.document`.
- Os dados de `clients` (documento, endereço, contatos) passam a significar **dados do contratante**. Os dados fiscais operacionais ficam na empresa e no estabelecimento. Na migração, os dados do contratante são copiados, nunca movidos.
- Um eventual cadastro público de contas (autoatendimento) não pode reintroduzir unicidade global visível. Se ele surgir, a duplicidade deve ser tratada fora da resposta ao usuário.

---

## 07. Identidade e vínculos

### 7.1 Modelo

| Tabela | Papel | Colunas principais | Restrições |
| --- | --- | --- | --- |
| `users` (existente) | Identidade global | e-mail (único), senha, 2FA, passkeys, foto, `is_saas_admin`, `access_enabled` | Sem novos vínculos de tenant |
| `account_memberships` (nova) | Vínculo identidade x tenant | `id`, `user_id`, `account_id`, `person_id`, `status` (`active`, `suspended`), `last_used_at`, timestamps | `unique(user_id, account_id)`, `unique(account_id, person_id)`, FK composta `(person_id, account_id)` para `people(id, account_id)` |

- A pessoa é do tenant: a mesma identidade tem uma pessoa diferente em cada tenant. Dados cadastrais não atravessam tenants.
- O nome e o e-mail da identidade continuam em `users`. A pessoa do tenant é sincronizada na criação do vínculo, e depois só pelos fluxos do próprio tenant **[R]**.
- `users.account_id`, `users.person_id` e `users.role_id` ficam como legado durante a transição (expandir, migrar, contrair) e são removidos na E09.

### 7.2 Regras

- **Revogar o vínculo** (`status != active`) bloqueia todas as permissões daquele tenant na requisição seguinte, sem afetar outros vínculos. É uma operação de escopo tenant (8.5).
- **`access_enabled = false`** bloqueia a identidade inteira e é uma operação da camada de identidade: só a plataforma (suporte, auditado). Administradores de tenant retiram o acesso suspendendo o **vínculo**, nunca a identidade (revisão 4, DA-24).
- **Identidade, pessoa e vínculo separados (revisão 4, DA-24):** a identidade autenticada (`users`), o cadastro da pessoa no tenant (`people`) e o vínculo organizacional (`account_memberships`) são entidades distintas.
  - O nome e o e-mail da pessoa deixam de alterar a identidade (E05).
  - **Nenhum administrador de tenant altera diretamente a identidade de login, mesmo com um único vínculo.**
  - O e-mail de acesso só muda pelo fluxo seguro da própria pessoa: o novo endereço recebe um link de confirmação, a troca só acontece depois da confirmação, o endereço anterior recebe aviso e as demais sessões são encerradas (detalhado na E02, DA-29, `docs/convencoes/identidade-e-vinculos.md`).
  - A recuperação de acesso usa o fluxo de redefinição de senha do Fortify, enviado ao e-mail da própria identidade. O administrador pode apenas **disparar** esse envio, sem ver nem definir a senha.
  - Verificações de e-mail respondem de forma neutra (A20).
- **Administrador SaaS:** `is_saas_admin` continua sendo um atributo da identidade. Ele não tem vínculo com tenants. O acesso a dados de tenant acontece apenas por inspeção.
- **Inspeção:** continua trocando para um usuário do tenant. Com vínculos, passa a apontar para o vínculo (`membership_id`) e herda as atribuições dele. A autoria segue com o administrador e o usuário representado.

---

## 08. RBAC contextual

### 8.1 Escopos de atribuição

| Escopo | Alcance da atribuição |
| --- | --- |
| Tenant | O tenant, todas as empresas e todos os estabelecimentos, atuais e futuros |
| Empresa | A empresa e todos os seus estabelecimentos, atuais e futuros |
| Estabelecimento | Somente aquela unidade |

### 8.2 Recurso-alvo e tipos de alvo admitidos [R] (revisão 2)

**Problema da versão 1:** o modelo de "um nível por slug" (`account`, `shared`, `company`, `establishment`) tinha três defeitos:
- classificava gestão de usuários e auditoria como nível `account`, o que impedia administradores de empresa e de estabelecimento de administrar o próprio escopo;
- tratava `shared` como "qualquer atribuição concede", o que permitia que uma atribuição de unidade editasse sem limites um cadastro usado por outras empresas;
- uma mesma ação (por exemplo, editar estabelecimento ou consultar auditoria) incide sobre recursos de níveis diferentes.

**Modelo corrigido:** a autorização sempre avalia uma **operação sobre um recurso-alvo**. Todo recurso tem um **nó organizacional**:

| Recurso | Nó |
| --- | --- |
| Conta, cargos, catálogo de etiquetas, eventos administrativos | `tenant` |
| Empresa, dados e catálogos da empresa, relação comercial | `company:{id}` |
| Estabelecimento e dados operacionais da unidade | `establishment:{id}` |
| Vínculo de usuário (situação e dados de acesso) | `tenant` |
| Atribuição de cargo | nó do escopo da atribuição |
| Pessoa (cadastro central) | conjunto das empresas com as quais a pessoa tem relação comercial ativa (vazio quando não tem); a regra depende da classe do dado editado (10.2) |

Cada permissão declara em `config/permissions.php` os **tipos de alvo admitidos** (`targets`). Um módulo pode declarar um padrão e uma ação pode sobrescrevê-lo. Os slugs não mudam.

**Regra de concessão (alvo simples):** a atribuição A concede a permissão P sobre o nó N se, e somente se:
- P pertence ao cargo de A;
- o vínculo e o tenant estão ativos;
- o tipo de N está entre os alvos admitidos de P;
- o escopo de A é ancestral ou igual a N (tenant ⊇ empresa ⊇ estabelecimento).

**Regra de concessão (alvo composto, pessoa):** a 10.2 define, por classe de dado, se basta cobrir **uma** empresa do conjunto (contatos e endereço) ou se é preciso cobrir **todas** (identificação). As regras para conjunto vazio estão na tabela 8.2.1.

Consequências:
- Uma atribuição de estabelecimento nunca alcança a empresa, o tenant ou outra unidade.
- Para o alvo de pessoa, "cobre a empresa C" significa ter atribuição de tenant, da empresa C **ou de qualquer estabelecimento de C**. Assim, o atendente de uma loja atualiza o telefone de um cliente da própria empresa mesmo que ele também seja cliente de outra empresa, mas não corrige o CPF dele sozinho (10.2).
- Não há exceção genérica: o que antes era `shared` agora é a regra explícita do alvo `person` e a regra explícita de leitura do cadastro base (10.2).

#### 8.2.1 Classificação das permissões atuais e previstas

| Permissão | Alvos admitidos | Nó avaliado | Conjunto vazio |
| --- | --- | --- | --- |
| `account.roles.list/view` | tenant, company, establishment | Qualquer nó onde o operador possa atribuir cargos (para escolher cargos disponíveis ali) | |
| `account.roles.create/update` | tenant | Tenant (cargos são catálogo do tenant e a disponibilidade afeta todas as empresas) | |
| `account.tags.list/view` | tenant, company, establishment | Qualquer atribuição (leitura do catálogo de etiquetas) | |
| `account.tags.create/update/delete` | tenant | Tenant | |
| `account.people.list/view` | tenant, company, establishment | Leitura do cadastro base: qualquer atribuição (10.2) | |
| `account.people.create` | tenant, company, establishment | Qualquer atribuição; a relação comercial criada junto exige a permissão da empresa | |
| `account.people.update` | person | Por classe de dado (10.2): contatos e endereço exigem cobertura de pelo menos uma empresa relacionada; identificação exige cobertura de todas as empresas relacionadas, senão tenant | Qualquer atribuição |
| `account.customers.*`, `account.suppliers.*` | company | Empresa da relação | |
| `account.users.list/view` | tenant, company, establishment | Lista os vínculos com atribuição dentro do escopo do operador e mostra **somente** as atribuições desse escopo; vínculos sem atribuição e atribuições de fora do escopo só com atribuição de tenant | |
| `account.users.create` | tenant, company, establishment | Nó da primeira atribuição criada na mesma operação. Só cria identidade nova; nunca altera uma identidade existente (8.5) | Exige tenant (vínculo sem atribuição) |
| `account.users.update` (dados de acesso do vínculo), `account.users.revoke` (suspender o vínculo), `account.users.reactivate` (reativar), `account.users.reset-password` (disparar o link de redefinição) e `account.users.invite` (reenviar convite) (nomes fechados na E02) | tenant | Tenant. O vínculo é do tenant inteiro; administradores de empresa e de unidade retiram o acesso local removendo as atribuições que gerenciam (`assign-role`) | |
| `account.users.assign-role` | tenant, company, establishment | Nó da atribuição criada ou removida, independentemente das demais atribuições da pessoa (8.5) | |
| `account.audit.list/view/technical` | tenant, company, establishment | Nó do evento (`establishment_id`, senão `company_id`, senão tenant) | |
| `account.companies.list/view` | tenant, company | Empresa (a listagem mostra só as empresas cobertas) | |
| `account.companies.create` | tenant | Tenant | |
| `account.companies.update` | company | A própria empresa | |
| `account.companies.activate/deactivate` | tenant | Tenant (afeta a estrutura da conta) | |
| `account.establishments.list/view` | tenant, company, establishment | Unidade | |
| `account.establishments.create` | company | Empresa da nova unidade | |
| `account.establishments.update` | establishment | A própria unidade (o administrador de unidade edita os dados dela; mudar a empresa não é permitido) | |
| `account.establishments.activate/deactivate/promote` (`promote` promove a principal; nome fechado na E02) | company | Empresa (afeta os invariantes da 6.6) | |
| `account.account-data.update` (Dados da conta; hoje `account.establishment.update`, renomeada na E04) | tenant | Tenant | |
| Módulos operacionais futuros (vendas, estoque, caixa, OS) | establishment | Unidade da operação | |
| Catálogos de empresa futuros (produtos, financeiro) | company | Empresa | |
| Habilitação de produto em unidade (futuro) | company | Empresa dona do catálogo | |

A E05 classifica **todas** as permissões existentes. A sincronização falha se um slug descoberto não tiver alvos declarados.

### 8.3 Tipos de administrador (revisão 2)

| Tipo | Como existe | O que pode | O que não pode |
| --- | --- | --- | --- |
| Administrador SaaS | `users.is_saas_admin` + `platform.*` | Plataforma, contas, inspeção auditada | Ler dados de tenant fora da inspeção (PD-02); qualquer `account.*` |
| Administrador integral do tenant | Cargo de sistema `administrator`, disponibilidade tenant, atribuição tenant; recebe automaticamente todas as permissões `account` e `self` (DA-09) | Tudo no tenant: cargos, empresas, unidades, usuários, auditoria de todos os nós | `platform.*`; outro tenant |
| Administrador de empresa | Cargo personalizado com, por exemplo, `companies.update`, `establishments.*`, `users.create`, `users.assign-role`, `audit.list/view`, atribuído no escopo da empresa | Editar a empresa; criar, editar, inativar e promover as unidades dela; criar usuários novos com a primeira atribuição na empresa; conceder e retirar cargos na empresa e nas unidades dela, inclusive de pessoas que também trabalham em outras empresas; ver a auditoria da empresa e das unidades | Criar ou alterar cargos (tenant); criar ou inativar empresas; ver eventos de tenant ou de outra empresa; alterar a identidade global, o vínculo com o tenant ou atribuições de fora da empresa; ver atribuições de fora da empresa |
| Administrador de estabelecimento | Cargo personalizado com, por exemplo, `establishments.update`, `users.create`, `users.assign-role`, `audit.list/view`, atribuído no escopo da unidade | Editar os dados da própria unidade; criar usuários novos com a primeira atribuição na unidade; conceder e retirar cargos na unidade, inclusive de pessoas que atuam em outras unidades; ver a auditoria da unidade | Inativar a unidade ou promover principal (empresa); qualquer ação sobre outra unidade, a empresa ou o tenant; alterar a identidade global, o vínculo ou atribuições de fora da unidade |

### 8.4 Sincronização de permissões

- `SyncPermissionsAction` continua descobrindo os slugs. Na E05, passa também a validar que todo slug descoberto tem alvos admitidos declarados em `config/permissions.php` (8.2.1), e falha com mensagem clara se faltar.
- `DefaultRolesAction` continua concedendo ao `administrator` todas as permissões `account` e `self`. Os cargos de sistema não recebem disponibilidade de empresa ou estabelecimento.
- O cargo inicial `member` perde `account.points.list` quando `points` for removido (E04), e recebe um substituto equivalente definido na E04 (por exemplo, `account.establishments.list`), para não perder o acesso inicial ao painel.

### 8.5 Proteção contra escalada (revisão 2)

1. **Concessão de permissões ao cargo** (`SaveRoleAction`): exige `account.roles.update` sobre o tenant e que o operador possua cada permissão concedida com atribuição de escopo tenant (regra atual, explicitada). Alterar a disponibilidade do cargo segue a mesma exigência.

2. **Três camadas de administração de usuários** (revisão 3, DA-19):

   | Camada | O que inclui | Quem altera |
   | --- | --- | --- |
   | Identidade global | E-mail de acesso, senha, 2FA, chaves de acesso, nome da identidade, `access_enabled` | Somente a própria pessoa (`self.*`), pelos fluxos seguros, e a plataforma (suporte, auditado). **Nenhum administrador de tenant, empresa ou unidade altera a identidade, nem com vínculo único** (revisão 4, DA-24). O administrador do tenant pode apenas disparar o envio do link de redefinição de senha ou reenviar o convite para o e-mail da própria identidade |
   | Vínculo com o tenant | Criar, suspender, reativar ou revogar o vínculo; pessoa vinculada | Somente atribuição de escopo tenant (`account.users.update` e `account.users.revoke`) |
   | Atribuições locais | Cargo em um nó (tenant, empresa ou unidade) | Qualquer operador com `account.users.assign-role` sobre o nó da atribuição (item 3) |

   **Criação de usuário:** com `account.users.create`, o operador cria uma identidade nova e o vínculo, junto com a primeira atribuição dentro do próprio escopo. Ele não modifica nada que já existe.
   - Se o e-mail já pertence a uma identidade, a resposta é neutra ("Convite enviado"), sem revelar se a identidade existe nem em qual tenant. A vinculação só se completa quando a pessoa aceita (E09).
   - Até a E09, sem convite entre tenants, a criação com e-mail já usado em **outro** tenant falha com mensagem neutra que não confirma a existência (corrige A20).

3. **Conceder ou retirar cargo no nó N** (`SaveRoleAssignmentsAction`). O operador precisa, ao mesmo tempo:
   - ter `account.users.assign-role` concedida sobre N;
   - estar concedendo um cargo disponível em N (9.1);
   - ter cada permissão do cargo concedida a ele por atribuições cujo escopo cubra N, ignorando aqui os alvos admitidos.

   As outras atribuições da pessoa **não** entram na decisão. Um administrador de empresa gerencia a atribuição de Ana na Empresa A mesmo que Ana também trabalhe na Empresa B.

   Retirar uma atribuição exige as mesmas condições sobre o nó da atribuição retirada. Assim:
   - ninguém retira cargo de fora do próprio escopo (o administrador da Empresa A não toca na atribuição de Ana na Empresa B, nem na de escopo tenant);
   - ninguém retira um cargo com permissões maiores que as próprias naquele nó.

   **Visibilidade:** a tela de acesso da pessoa mostra e edita só as atribuições dentro do escopo do operador. Atribuições de outros escopos não são listadas, porque revelariam onde a pessoa trabalha. Apenas operadores de escopo tenant veem todas.

   **Último acesso local:** retirar a última atribuição que um operador gerencia remove o acesso da pessoa àquele escopo, mas não suspende o vínculo nem afeta a identidade. Um vínculo sem atribuições continua existindo, sem permissões de tenant, até que um operador de escopo tenant o revogue.

4. **Horizontal:** todo identificador recebido (empresa, estabelecimento, cargo, vínculo, pessoa) é buscado com `account_id` do contexto, por FK composta e Query com escopo.

5. **Último administrador:** a regra atual é preservada. Precisa existir pelo menos um vínculo ativo com o cargo `administrator` em escopo tenant.

6. **Inspeção:** o administrador SaaS em inspeção tem exatamente a autoridade do vínculo representado. Nada da autoridade de plataforma é somado.

---

## 09. Cargos, disponibilidade e atribuições

### 9.1 Modelo físico [R]

| Tabela | Colunas | Restrições |
| --- | --- | --- |
| `roles` (existente) | acrescenta `availability` (`tenant` ou `restricted`; padrão `tenant`) | `system_key` continua único por conta |
| `role_availability` (nova) | `id`, `account_id`, `role_id`, `company_id`, `establishment_id` (nulo para empresa inteira) | CHECK: `company_id` obrigatório; FK composta `(role_id, account_id)`, `(company_id, account_id)` e `(establishment_id, company_id)`; únicos parciais `(role_id, company_id) where establishment_id is null` e `(role_id, establishment_id) where establishment_id is not null`. Usada só quando `roles.availability = restricted` |
| `role_assignments` (nova) | `id`, `account_id`, `membership_id`, `role_id`, `scope` (`tenant`, `company`, `establishment`), `company_id`, `establishment_id`, `granted_by`, timestamps | CHECK por escopo: tenant exige ambos nulos; company exige `company_id` e `establishment_id` nulo; establishment exige os dois. FKs compostas com `account_id`. Três índices únicos parciais, um por escopo, para impedir duplicatas (compatíveis com PostgreSQL e SQLite) |

**Validação de cobertura** (na Action, com teste dedicado): uma atribuição só é válida se o escopo dela estiver coberto pela disponibilidade do cargo:

- disponibilidade `tenant` cobre qualquer escopo;
- uma linha com `company_id` cobre a empresa e as unidades dela;
- uma linha com `establishment_id` cobre só a unidade;
- uma atribuição de escopo tenant exige disponibilidade `tenant`.

**Por que não uma tabela genérica de "nós":** os três escopos são fixos e pequenos. Colunas explícitas com CHECK e FKs compostas são verificáveis pelo banco, simples de indexar e não permitem combinações inválidas.

**Ao alterar a disponibilidade:** as atribuições que ficarem descobertas são listadas e precisam ser removidas na mesma operação, com confirmação na interface. O sistema nunca mantém atribuições inválidas silenciosamente.

### 9.2 Resolução das permissões efetivas [R] (revisão 2)

`EffectivePermissions` (novo, binding `scoped`, em `Acessos/Application`), carregado sob demanda na primeira checagem da requisição. São **duas queries**, separadas para não multiplicar linhas:

```sql
-- 1) atribuições do vínculo (linhas = quantidade de atribuições)
select ra.scope, ra.company_id, ra.establishment_id, ra.role_id
from role_assignments ra
join account_memberships m on m.id = ra.membership_id and m.status = 'active'
join clients c on c.id = m.account_id and c.active = true
where ra.membership_id = :membership

-- 2) permissões dos cargos distintos (linhas = soma das permissões dos cargos distintos)
select pr.role_id, p.slug
from permission_role pr
join permissions p on p.id = pr.permission_id and p.is_system = true
  and p.scope in ('account', 'self')
where pr.role_id in (:roles_distintos)
```

**Por que duas e não uma:** com uma única junção, as linhas retornadas seriam atribuições x permissões. Por exemplo, o cargo Vendedor com 40 permissões atribuído em 30 unidades daria 1.200 linhas. Separando, o mesmo caso retorna 30 + 40 linhas.

**Em memória:**
- cargo → conjunto de slugs, compartilhado entre as atribuições do mesmo cargo;
- lista de atribuições com `scope`, `company_id` e `establishment_id`. As atribuições de estabelecimento já trazem `company_id`, que é a coluna desnormalizada protegida por FK composta. Por isso "cobre a empresa C" não exige query.

**Custo da checagem:**
- `allows(P, N)` percorre as atribuições do vínculo e é O(número de atribuições);
- o resultado por `(P, N)` é memorizado na requisição;
- não há promessa de O(1). O custo real é medido (15.3).

Permissões `self` e `platform` continuam como hoje para identidades sem vínculo e para administradores SaaS, também memorizadas na requisição.

**Conjuntos autorizados para consultas consolidadas**, por exemplo "estabelecimentos onde posso `X`":
- Devolve um descritor SQL derivado das atribuições já carregadas, sem query extra e nunca montado em loop sobre os estabelecimentos do tenant: `tenant inteiro`, ou `company_id in (...) or establishment_id in (...)`.
- A Query aplica o descritor diretamente no `where`, antes de paginar, contar ou agregar.
- O tamanho do descritor é limitado pelo número de atribuições, não pelo número de estabelecimentos.

**Revogação:** sem cache entre requisições, então qualquer alteração vale na requisição seguinte. Um cache persistente só será considerado com medição que o justifique e um contador de versão por vínculo (seção 15.4).

---

## 10. Propriedade e compartilhamento dos dados

### 10.1 Classes de propriedade

| Classe | Colunas obrigatórias | Exemplos | Visibilidade padrão |
| --- | --- | --- | --- |
| Plataforma | nenhuma de tenant | catálogo de permissões, planos | Plataforma |
| Tenant | `account_id` | contas de usuário, cargos, empresas, estabelecimentos, pessoas, etiquetas, configurações | Conforme o recurso-alvo de cada permissão (8.2.1) |
| Empresa | `account_id`, `company_id` | produtos e serviços, clientes, fornecedores, contas financeiras, centros de custo | Atribuições de tenant ou da empresa |
| Estabelecimento | `account_id`, `company_id`, `establishment_id` | saldos, preços locais, vendas, caixas, OS, depósitos | Atribuições que cubram a unidade |
| Habilitação | pivô com `establishment_id` | `product_establishment` | Leitura do cadastro; não concede dados operacionais |

Regras:

- Colunas organizacionais redundantes são intencionais: `account_id` em tudo e `company_id` em dados de unidade. Assim os filtros ficam baratos e a integridade é garantida por FKs compostas `(establishment_id, company_id)` → `establishments(id, company_id)` e `(company_id, account_id)` → `companies(id, account_id)`.
- As consultas passam sempre por Queries que recebem o contexto ou o descritor autorizado (padrão atual). Escopos globais do Eloquent não são usados para isolamento (seção 13.3).

### 10.2 Pessoas, clientes e fornecedores (revisões 2 e 3)

O cadastro central de Pessoas é mantido (decisão aprovada). Os refinamentos da revisão 4 (DA-25) separam os contatos centrais dos contatos da relação comercial.

#### 10.2.1 Classes de dado da pessoa (revisão 3)

| Classe | Campos | Onde fica | Quem edita | Motivo |
| --- | --- | --- | --- | --- |
| **Contato e endereço centrais** (compartilhados) | telefone, WhatsApp, e-mails e telefones adicionais, endereço principal, site, etiquetas convencionais | `people` | `account.people.update` cobrindo **pelo menos uma** empresa com a qual a pessoa tem relação; sem relação, qualquer atribuição com a permissão. A interface identifica esses campos como "Dados centrais: valem para todas as empresas" | São os contatos da pessoa. A alteração é explícita, auditada como evento do cadastro central e não toca dados de nenhuma relação comercial |
| **Identificação** (central, compartilhada, efeito fiscal) | nome ou razão social, tipo de pessoa, CPF/CNPJ, nome fantasia, inscrições estadual e municipal | `people` | `account.people.update` cobrindo **todas** as empresas com as quais a pessoa tem relação; sem relação, qualquer atribuição com a permissão. Fora disso, só com escopo tenant | Mudar o documento ou a razão social altera a identificação usada nos documentos e no histórico das outras empresas |
| **Dados comerciais e contatos da relação** (por empresa) | situação da relação, condições, limite, observações comerciais, contato comercial, e-mail e telefone de cobrança ou de pedidos, endereço de entrega ou cobrança e campos futuros | `customers` e `suppliers` (por empresa) | `account.customers.update` ou `account.suppliers.update` sobre **aquela** empresa. Visíveis só a quem tem a permissão de visualizar a relação naquela empresa | Cada empresa mantém os próprios contatos e condições com a pessoa. Uma alteração local nunca modifica a relação de outra empresa |
| **Etiquetas comerciais** | Cliente, Fornecedor | derivadas das relações | não são editadas diretamente | Seguem a relação comercial |
| **Acesso de usuário** | etiqueta Usuário, atribuições | `account_memberships`, `role_assignments` | Regras da 8.5 | Separado do cadastro |

Regras de aplicação:
- **Uma permissão, regras por campo:** o formulário continua com uma única permissão (`account.people.update`). O servidor compara os campos alterados com a classe de cada um e aplica a regra correspondente. Campos de identificação que o operador não pode alterar aparecem só para leitura, com a explicação "Alterável por quem atende todas as empresas desta pessoa ou pela administração da conta".
- **Validação no servidor:** um envio que altere a identificação sem autorização é rejeitado (403), mesmo que a interface tenha liberado o campo.
- **Pessoa que é usuário:** o nome e o e-mail da pessoa são dados de contato do tenant. Eles **deixam de propagar** para a identidade global a partir da E05 (corrige A20). O e-mail de acesso segue a camada de identidade da 8.5.
- **Sem bloqueio excessivo:** um operador de uma empresa só fica sem editar a identificação quando a pessoa também tem relação com outra empresa que ele não atende. Contato e endereço centrais continuam editáveis. Um operador de escopo tenant edita tudo.
- **Contatos centrais e contatos da relação (revisão 4, DA-25):**
  - Quando um contato só interessa a uma empresa (por exemplo, o e-mail de cobrança usado pela Empresa A), ele é gravado na relação comercial daquela empresa, e não no cadastro central.
  - A Empresa B não vê nem herda esse dado.
  - Nenhuma rotina copia ou sincroniza automaticamente dados entre o cadastro central e as relações.
  - **Fechamento na E02:** a classificação campo a campo, a visibilidade, a edição, os efeitos, a auditoria e os textos "Dados centrais" e "Dados desta empresa" estão em `docs/convencoes/pessoas-e-contatos.md` (revisão 5). Nenhum campo novo de contato é criado antes da E07.
  - **Migração:** os campos atuais de `people` continuam centrais. Nenhum dado existente é movido para as relações sem decisão campo a campo na E02.

#### 10.2.2 Operações

A política separa as operações abaixo:

| Operação | Recurso | Regra |
| --- | --- | --- |
| **Ler o cadastro central** (identificação, contatos, endereço, etiquetas não comerciais) | `people` (tenant) | `account.people.list/view` com qualquer atribuição ativa no tenant. O cadastro base é a agenda compartilhada do tenant e não contém dados comerciais ou financeiros |
| **Criar pessoa** | `people` | `account.people.create` com qualquer atribuição. Se a criação incluir relação comercial, exige também a permissão da empresa dessa relação |
| **Editar o cadastro central** | alvo `person` | Por classe de dado (10.2.1) |
| **Vincular, desvincular ou editar a relação comercial** (cliente ou fornecedor numa empresa) | `customers` ou `suppliers` (empresa) | `account.customers.*` ou `account.suppliers.*` sobre a empresa da relação |
| **Habilitação por unidade** (futuro, por exemplo produto em estabelecimento) | pivô de habilitação | Permissão de catálogo sobre a empresa dona; não concede dados operacionais da unidade |
| **Histórico operacional e financeiro** (futuro) | tabelas com `company_id` e `establishment_id` | Permissão do módulo sobre a empresa ou a unidade do registro |
| **Usuário** | `account_memberships` | Regras de controle de acesso (8.5) |

**Etiquetas especiais e o que revelam:**
- Hoje [C], as etiquetas Cliente e Fornecedor ficam gravadas em `person_tag` e aparecem para todos que leem a pessoa.
- Com várias empresas, isso revelaria a um usuário da Empresa B que a pessoa é cliente da Empresa A.
- A partir da E07, as etiquetas comerciais são **derivadas** das relações e calculadas por quem está vendo. Cliente aparece somente se existir relação ativa numa empresa onde o leitor tem `account.customers.view`. Fornecedor segue a mesma regra.
- `person_tag` passa a guardar apenas etiquetas não comerciais (as convencionais e a etiqueta Usuário).
- A etiqueta Usuário revela só que a pessoa tem acesso ao sistema, e aparece para quem tem `account.users.list` em algum escopo. Os cargos e escopos nunca aparecem fora das telas de acesso.
- Os filtros "Clientes" e "Fornecedores" da listagem de Pessoas filtram apenas pelas relações em empresas autorizadas ao leitor.
- A busca não procura por dados de relação ou histórico.

**Outras regras:**
- A mesma pessoa pode ser cliente da Empresa A e da Empresa B, com relações e históricos separados.
- No formulário de pessoa, com várias empresas, o usuário escolhe em quais empresas autorizadas a relação existe. Com uma empresa só, a escolha é implícita e a interface atual continua igual.
- **Migração (E07):** `customers` e `suppliers` existentes recebem a única empresa do tenant criada na E04 (equivalente ao estado atual, não é suposição). Os índices únicos mudam de `(person_id)` para `(company_id, person_id)`. As linhas comerciais de `person_tag` são substituídas pela derivação.

**Decisão de produto possível no futuro:** um modo "cadastro de pessoas restrito por empresa", em que a leitura do cadastro base exija relação com a empresa do leitor. Ele **não** faz parte deste plano. O modelo atual (agenda compartilhada) é a regra.

### 10.3 Produtos (contrato futuro)

- O produto pertence à empresa (`company_id`). Empresas do mesmo tenant não compartilham produtos.
- `product_establishment(product_id, establishment_id, active)` habilita o produto por unidade. Preço local, custo e saldo ficam em tabelas próprias por estabelecimento ou depósito.
- Ver o cadastro exige a permissão de catálogo (`company`). Ver saldo, custo ou movimentação de uma unidade exige a permissão de estoque (`establishment`) **naquela** unidade.

### 10.4 Financeiro (contrato futuro)

- **Responsável:** a empresa é a responsável jurídica (`company_id`) por contas bancárias, contas a receber e a pagar e obrigações. O estabelecimento identifica a origem operacional (`establishment_id`, opcional conforme o documento).
- **Centros de custo:** pertencem à empresa.
- **Caixas:** pertencem ao estabelecimento.

### 10.5 Transferências (contrato futuro)

| Situação | Natureza | Tratamento |
| --- | --- | --- |
| Entre estabelecimentos da mesma empresa | Movimentação interna da mesma pessoa jurídica | Documento de transferência com origem e destino. Cada passo é autorizado na própria unidade (solicitar no destino; aprovar e expedir na origem; receber no destino). Auditoria nas duas unidades com o mesmo `operation_id`. A nota de transferência é tratada pelo módulo fiscal |
| Entre empresas distintas do mesmo tenant | Operação comercial entre pessoas jurídicas | Venda ou compra entre as empresas: a empresa destinatária aparece como Pessoa (cliente ou fornecedor) na empresa emitente. Exige documento fiscal e lançamentos financeiros próprios. Nunca é modelada como movimentação entre depósitos |

---

## 11. Auditoria contextual

### 11.1 Modelo [R] (revisão 2: contexto gravado desde a E04)

- **Colunas novas em `audit_records`:** `company_id` e `establishment_id`, ambas nulas. São criadas **na E04**, junto com as primeiras entidades organizacionais, para que nenhum histórico empresarial novo nasça sem contexto.
- **Contrato `Auditable`:** passa a ter `auditCompanyId()` e `auditEstablishmentId()` **na E04**, com implementação padrão no trait `AuditsChanges` lendo os atributos homônimos, como já acontece com `auditAccountId()`. `AuditLog::record` e `AuditLog::relationship` recebem e gravam esses valores.
- **Contexto gravado por tipo de evento:**

| Evento | `company_id` | `establishment_id` | Desde |
| --- | --- | --- | --- |
| Empresa criada, alterada, ativada ou inativada | a empresa | nulo | E04 |
| Estabelecimento criado, alterado, promovido ou inativado | a empresa | a unidade | E04 |
| Provisionamento da unidade principal | a empresa (os dois eventos) | nulo no evento da empresa; a unidade no evento da unidade | E04 |
| Atribuição criada ou removida | empresa do escopo, quando houver | unidade do escopo, quando houver | E05 |
| Cargo e disponibilidade | nulo (tenant) | nulo | E05 |
| Vínculo, identidade e acesso | nulo (tenant) | nulo | E05 |
| Relação comercial (cliente ou fornecedor) | a empresa | nulo | E07 |
| Cadastro base de pessoa e etiquetas | nulo (tenant) | nulo | atual |
| Eventos operacionais futuros | a empresa | a unidade | módulo |

- **Eventos sem model** (login, sincronização do catálogo, inspeção) ficam sem empresa e sem estabelecimento.
- **Operação entre duas unidades:** gera dois eventos, um por unidade, com o mesmo `operation_id`. Não há pivô.
- **Limitação aceita:** alterações no cadastro base de uma pessoa são eventos de tenant. Um auditor restrito a uma empresa vê a alteração da relação comercial, mas não a do cadastro base. Isso é coerente com o cadastro ser compartilhado.
- A E08 trata consulta, filtros, consolidação, índices de leitura e remoção do legado.

### 11.1.1 Consulta contextual mínima na E05 (revisão 3)

A versão anterior deixava auditores locais sem tela entre a E05 e a E08. Isso foi removido. Regra: **nenhuma atribuição de auditoria local é concedida sem uma tela funcional e segura.**

**A E05 entrega a consulta contextual mínima na tela de auditoria atual (`/conta/auditoria`):**
- a condição de visibilidade da 11.2, aplicada no SQL, para `account.audit.list`, `account.audit.view` e `account.audit.technical`;
- listagem, contagem e paginação filtradas;
- opções de filtro existentes (autores, tipos, ações e datas), calculadas sobre a mesma consulta com escopo;
- detalhe e dados técnicos com `findOrFail` sobre o escopo;
- a visão é sempre a união dos nós autorizados. Não há seletor de empresa ou unidade nem filtro organizacional novo;
- os eventos anteriores à E04 são de nível tenant e continuam visíveis só para auditores de escopo tenant.

**Proteção:** `SaveRoleAssignmentsAction` só aceita conceder fora do escopo tenant um cargo com permissões `account.audit.*` se a consulta contextual estiver ativa. Ela é verificada pelos testes de segurança D da E05 (cenários 22 mínimo e 41).
- Se a consulta mínima não puder ser entregue na E05, a etapa sai com essa concessão bloqueada (a mensagem explica que a auditoria local fica disponível numa etapa seguinte).
- O desbloqueio passa para a E08.
- Em nenhum caso existe atribuição local de auditoria sem tela.

**Fica para a E08:** filtros por empresa e por unidade, a escolha explícita da visão consolidada, índices de leitura baseados em medição, consolidação do legado (`DirectoryAudit`, `access_catalog_events`), restrição da auditoria da plataforma (PD-02) e as medições em volume (200 mil registros).

### 11.2 Visibilidade (aplicada em SQL)

Um registro do tenant aparece para o usuário se for verdadeira a condição abaixo, montada a partir do descritor de `EffectivePermissions` para `account.audit.list`:

```text
(establishment_id is not null and (establishment_id in E or company_id in C or T))
or (establishment_id is null and company_id is not null and (company_id in C or T))
or (company_id is null and T)
```

- **T:** `account.audit.list` concedida por atribuição de escopo tenant.
- **C:** empresas onde a permissão foi concedida por atribuição de escopo empresa.
- **E:** estabelecimentos onde a permissão foi concedida por atribuição da própria unidade.

Isso é a regra de alvo da 8.2 aplicada em SQL: o nó do evento precisa estar coberto por uma atribuição com a permissão.
- Ter acesso a unidades não concede auditoria: `account.audit.list` é sempre exigida.
- Eventos de cadastros compartilhados (pessoas, etiquetas) e administrativos (cargos, usuários, conta) são de nó tenant e só aparecem com T.
- O detalhe (`account.audit.view`) e os dados técnicos (`account.audit.technical`) seguem a mesma regra sobre o nó do evento, avaliada para cada uma dessas permissões.

### 11.3 O que usa a mesma condição

- Listagem, contagem e paginação.
- Opções de filtro (autores, tipos, ações, empresas, estabelecimentos), já montadas hoje a partir da mesma consulta com escopo.
- Detalhe (`findOrFail` sobre o escopo).
- Exportação futura.
- A visão consolidada é a própria condição.
- O filtro de unidade só restringe dentro do conjunto autorizado.

### 11.4 Consolidação da trilha legada

1. Substituir as 5 chamadas de `DirectoryAudit::record` por `AuditLog` (ou removê-las quando `AuditsChanges` já registra o mesmo fato), sem perder campos.
2. Migrar as linhas de `directory_audit_events` posteriores à migration `2026_11_18_000004` para `audit_records`, com `legacy_key`, o mesmo mecanismo já usado.
3. Remover a classe e, numa migration posterior e reversível, a tabela, com exportação prévia.

Além disso, `access_catalog_events` continua recebendo a sincronização do catálogo em paralelo com `audit_records`. Ela será tratada da mesma forma.

### 11.5 Auditoria da plataforma (A12)

Recomendação: `/saas/auditoria` mostra os eventos de contexto `platform` e os eventos administrativos das contas (provisionamento, situação, inspeções). Os eventos de negócio do tenant passam a ser visíveis apenas durante uma inspeção, que já é registrada (PD-02).

### 11.6 Índices

Ver a seção 16.

---

## 12. Contexto de navegação

### 12.1 `OrganizationContext` [R]

Serviço `scoped` (novo, `App\Domains\Acessos\Application\Context\OrganizationContext`), preenchido pelo middleware `ResolveOrganizationContext` (novo) depois da autenticação e de `EnforceAccountInspection`.

| Campo | Origem |
| --- | --- |
| `actorId` | Usuário autenticado; na inspeção, o `AuditContext` mantém o administrador |
| `accountId` e `membershipId` | Até a E09, o vínculo único do usuário. Depois, o tenant da URL validado contra os vínculos ativos |
| `companyId` | Parâmetro de rota `{empresa}`, validado com `account_id` |
| `establishmentId` | Parâmetro de rota `{estabelecimento}`, validado com `company_id` e `account_id` |
| `mode` | `tenant`, `company`, `establishment` ou `consolidated` |

**Validação no middleware:**

1. O vínculo está ativo.
2. A empresa e o estabelecimento pertencem ao tenant.
3. Existe **alguma** atribuição que cubra o nó solicitado. Isso é acesso ao contexto.
4. A empresa e a unidade estão ativas para rotas operacionais. Contextos inativos só aceitam consulta de histórico e as telas administrativas de reativação (6.6).

A permissão da operação continua sendo verificada pela rota ou Action. São verificações complementares: o contexto não concede permissões. O `AccountContext` da E03 é o embrião deste serviço.

### 12.2 URLs [R]

| Tipo de tela | Padrão | Exemplo |
| --- | --- | --- |
| Administração da conta (recursos de nó tenant, pessoas e cadastros da organização) | URLs atuais preservadas | `/conta/pessoas`, `/conta/cargos`, `/conta/empresas`, `/conta/estabelecimentos` |
| Módulo de empresa | `/conta/empresa/{empresa}/...` | `/conta/empresa/gartan/clientes` |
| Módulo de estabelecimento | `/conta/estabelecimento/{estabelecimento}/...` | `/conta/estabelecimento/gartan-guanambi/vendas` |
| Consolidado | código reservado `todos` no lugar do código | `/conta/estabelecimento/todos/vendas` (decidido por módulo) |

- **Códigos:** são únicos por tenant e o estabelecimento determina a empresa. Prefixos, formato do código e palavras reservadas foram fechados na E02 (DA-28, `docs/convencoes/contexto-organizacional.md`). O singular identifica o contexto operacional; o plural, o cadastro administrativo.
- **Empresa pequena:** se o tenant tem uma empresa e uma unidade, uma rota sem código redireciona para a unidade principal e o seletor fica oculto. O contexto continua sempre definido na URL.
- **Tenant na URL:** só é necessário com vários vínculos (E09). Prefixo de caminho aprovado (PD-04): `/contas/{conta}/...` no lugar de `/conta/...` (E02, DA-28).

### 12.3 Várias abas

- **Frontend:** `apiRequest`, `ApiLink`, `ApiForm` e `navigate` montam URLs relativas ao contexto da página atual. Nenhum estado global de contexto é guardado no frontend.
- **Escrita:** toda escrita vai para uma URL que contém o contexto, e o servidor valida que o recurso alterado pertence a ele (`where account_id = ? and establishment_id = ?`).
- **Sessão:** guarda no máximo o "último contexto usado", e só para redirecionar após o login.

Resultado: uma operação da aba 1 nunca usa o contexto da aba 2 (cenário 24).

### 12.4 Seletor organizacional

- **Onde:** na `TopBarPrimary`, reutilizando `SelectControl` ou `DropdownMenu` (o `DropdownMenu` já existe e ainda não é usado pela aplicação).
- **O que lista:** só os contextos onde existe alguma atribuição, a partir de uma única query de empresas e estabelecimentos autorizados, ordenada por empresa.
- **Simplificação:**
  - uma empresa e uma unidade: o seletor fica oculto;
  - uma empresa e várias unidades: lista só as unidades;
  - várias empresas: agrupa por empresa.
- **Consolidado:** a opção "Todos os autorizados" aparece só em telas que declaram suporte e quando há permissão em mais de uma unidade.
- **Troca:** navega para a mesma rota com outro código. A sidebar é a mesma; o contexto só muda os dados exibidos.

---

## 13. Segurança e isolamento

### 13.1 Riscos e controles

| Risco | Controle |
| --- | --- |
| IDOR/BOLA por ID | Toda busca por ID inclui `account_id` e, conforme o nó do recurso, `company_id` ou `establishment_id`; FKs compostas no banco; testes de manipulação de ID por recurso |
| Manipulação de URL | O middleware valida o contexto contra o tenant e as atribuições; o código de outra empresa ou de outro tenant retorna 404 |
| Escalada vertical | Alvos admitidos e nó do recurso (8.2); camadas de identidade, vínculo e atribuição (8.5); antiescalada na concessão e na atribuição (8.5) |
| Escalada horizontal | Atribuição avaliada no escopo pretendido; edição de pessoa e de usuário pelo conjunto de nós afetados (8.2, 8.5); cargos e vínculos sempre buscados no tenant do contexto |
| Vazamento entre tenants por unicidade | Unicidade de CNPJ por tenant em empresas e estabelecimentos (6.4) |
| Busca, relatório e exportação | Sempre pela mesma Query com o descritor autorizado, antes de paginar ou agregar |
| Cache sem escopo | Não há cache hoje. Contrato: toda chave inclui o tenant e o nó; permissões não ficam em cache entre requisições |
| Jobs sem contexto | Não há jobs hoje. Contrato: o job leva `account_id`, `company_id`, `establishment_id`, `membership_id` e `actor_id`, e reautoriza ao executar |
| Autorizações obsoletas na sessão | Permissões nunca na sessão; resolução por requisição; o vínculo e o tenant são checados na mesma query |
| Plataforma vendo dados de tenant | Inspeção auditada; restrição da auditoria da plataforma (PD-02) |
| Etiquetas e filtros revelando relações comerciais | Etiquetas comerciais derivadas e filtradas pelas empresas autorizadas (10.2) |
| Interface como barreira | Proibido: rotas e Actions sempre verificam no servidor (convenção atual reafirmada) |

### 13.2 Antiescalada da sincronização

`DefaultRolesAction` concede somente escopos `account` e `self`, já comprovado. A E05 acrescenta testes que falham se uma permissão `platform` aparecer em qualquer cargo de tenant.

### 13.3 Escopos globais do Eloquent

Não serão usados para isolamento:

- dependem de estado ambiente, o que é arriscado em console, jobs e Octane;
- ficam invisíveis na leitura do código;
- são fáceis de desligar sem perceber (`withoutGlobalScopes`).

O padrão é Query explícita mais FK composta mais testes de isolamento obrigatórios por módulo (seção 27).

---

## 14. Laravel Octane e FrankenPHP

### 14.1 Capacidades disponíveis no FrankenPHP

| Recurso | Disponível | Evidência |
| --- | --- | --- |
| Workers persistentes, `--workers`, `--max-requests` | Sim | `StartFrankenPhpCommand` |
| `once()` limpo por operação | Sim | `FlushOnce` em `OperationTerminated` |
| Bindings `scoped` limpos por operação | Sim | `FlushTemporaryContainerInstances`; `AuditContext` já usa |
| Gate, Auth, Session e Request novos por requisição | Sim | Listeners `GiveNewApplicationInstanceToAuthorizationGate`, `FlushAuthenticationState`, `FlushSessionState`, `GiveNewRequestInstanceToApplication` |
| `Octane::concurrently`, ticks, `Octane::table`, cache `octane` | **Não** (dependem do Swoole) | `ProvidesConcurrencySupport` usa `SwooleTaskDispatcher`; a fachada tipa `Swoole\Table` |

O plano não usa recursos exclusivos do Swoole.

### 14.2 Regras de ciclo de vida [R]

1. **Escopo por requisição:** `EffectivePermissions`, `OrganizationContext`, `AccountContext` e `AuditContext` são `scoped`. Nunca `singleton`.
2. **Sem estado estático:** proibido estado estático mutável em Actions, Queries, Models ou Providers (hoje não existe). Um teste de arquitetura em PHP verifica `static $` e `private static` com atribuição fora de constantes.
3. **Sem objetos de requisição guardados:** serviços não guardam `Request`, `User` nem `Session` em propriedades de objetos longevos. O `Gate::before` atual recebe o usuário por parâmetro e é seguro.
4. **Memoização:** por requisição via `scoped`. `once()` só para valores derivados do próprio objeto de modelo, porque o usuário é recarregado a cada requisição.
5. **Workers:** `--workers=2` e `max-requests` 500 em produção são para revisar com medição (seção 15). `max-requests` limita vazamentos de memória, mas não substitui as regras acima.

### 14.3 Verificação de vazamento de contexto (revisão 2)

- **Teste automatizado (necessário, mas não suficiente):** duas requisições sequenciais de tenants diferentes na mesma instância da aplicação, chamando `forgetScopedInstances()` entre elas como o Octane faz. A segunda nunca vê o contexto, as permissões ou o `AuditContext` da primeira. Isso simula o ciclo, mas não exercita o runtime real.
- **Verificação com worker real (obrigatória nas etapas E03, E06 e E10):**
  - FrankenPHP com Octane no ambiente isolado, `--workers=1`, para que todas as requisições passem pelo mesmo worker.
  - Um script alterna centenas de requisições entre contas e vínculos diferentes, incluindo requisições com erro (403, 404 e 422) no meio.
  - Cada resposta é verificada para conter só dados do próprio tenant e contexto.
  - Também se verifica que o `AuditContext` de uma escrita não herda o autor da anterior.
  - Executar também com `--max-requests` alto, para garantir que a reciclagem do worker não esconda o problema.

---

## 15. Contrato de performance

### 15.1 Método de medição (revisão 2)

**Harness de testes** em `tests/Performance` (novo, grupo PHPUnit `performance`, fora da suíte padrão):

- usa seeders sintéticos e registra, por requisição: total de queries, queries repetidas, tempo de banco, tempo total e pico de memória (`DB::listen`, `hrtime`, `memory_get_peak_usage`);
- instrumenta o resolvedor para registrar: linhas retornadas por query, tamanho dos conjuntos em memória (atribuições, cargos distintos e slugs), número de checagens e tempo acumulado das checagens;
- tem asserções de orçamento de queries, usadas como regressão;
- `Model::preventLazyLoading()` fica ativo nos testes (no `TestCase`).

**PostgreSQL obrigatório para performance e concorrência.** A suíte padrão continua em SQLite em memória por regra do projeto (`tests/bootstrap.php`), sem alterar esse isolamento. Medições de tempo, planos de execução, seletividade e testes de concorrência só valem em PostgreSQL. O harness roda num ambiente isolado com banco próprio, nunca no banco da aplicação.

**Ambiente isolado** (projeto Compose separado, como o `prospera-verify` já usado):

- PostgreSQL 18 com volume próprio, dados sintéticos e `ANALYZE` executado após a carga;
- `EXPLAIN (ANALYZE, BUFFERS)` nas consultas críticas;
- latência por HTTP com uma ferramenta de carga leve a definir na E03 (por exemplo, `hey` ou `oha`), com 1 e 2 workers FrankenPHP reais;
- memória por worker observada durante a carga.

**Cenários de dados:**

- 1 empresa x 1 estabelecimento;
- 1 x 3;
- 3 empresas com 2 a 5 unidades;
- 10 empresas;
- 50 estabelecimentos;
- usuários com 1, 5 e 30 atribuições, com 1 cargo repetido e com cargos todos distintos;
- cargos com 10, 40 e todas as permissões do catálogo;
- 10 mil e 200 mil registros de auditoria;
- distribuição de auditoria concentrada numa unidade e espalhada entre todas (para avaliar a seletividade).

As metas numéricas finais são fixadas **depois** da linha de base de cada cenário. Exceções: as metas de crescimento abaixo são estruturais e não dependem de valor absoluto.

### 15.2 Linha de base medida (19/11/2026)

Medição feita com o teste temporário (fora do repositório) em SQLite, dados de 1 conta, 40 pessoas, 8 etiquetas e 7 cargos. As requisições foram feitas com `actingAs`, que não inclui as queries de sessão e de usuário de uma requisição real (mais 2).

| Rota (administrador) | Queries | Queries em `permissions` | Repetidas |
| --- | --- | --- | --- |
| `/conta` | 8 | 3 | 4 |
| `/conta/pessoas` (página de 25) | 11 | 2 | 2 |
| `/conta/pessoas/cadastrar` | 8 | 3 | 4 |
| `/conta/etiquetas` | 8 | 3 | 4 |
| `/conta/cargos` | 6 | 3 | 2 |
| `/conta/cargos/cadastrar` | 5 | 3 | 2 |
| `/conta/auditoria` | 9 | 2 | 2 |
| `/conta/usuarios` | 8 | 2 | 2 |
| `/ajuda` | **26** | **13** | **24** |

Leitura:

- Não há N+1 proporcional ao número de linhas nas listagens atuais (Pessoas carrega etiquetas por eager loading; Etiquetas usa `withCount`).
- O custo variável vem da autorização: cada `can()` custa 2 queries (A08). Na Central de Ajuda, `HelpCatalog` chama `can()` 13 vezes.

### 15.3 Metas estruturais (revisão 2)

Número de queries é uma das medidas, não o custo. Cada meta tem a medida correspondente:

1. **Queries de autorização:** no máximo 2 por requisição (atribuições e permissões dos cargos distintos), mais 1 para a lista de contextos autorizados só quando a página tem o seletor. Esse número não cresce com o número de checagens, de atribuições ou de estabelecimentos.
2. **Linhas do resolvedor:** iguais a atribuições + soma das permissões dos cargos **distintos** (9.2), nunca atribuições x permissões. Medidas em todos os cenários.
3. **Processamento e memória do resolvedor:** tempo acumulado das checagens e memória dos conjuntos medidos nos cenários de 1, 5 e 30 atribuições. Meta fixada após a linha de base, com crescimento no máximo linear no número de atribuições. Se o cenário de 30 atribuições mostrar custo relevante, adota-se pré-indexação por nó na própria requisição, ainda sem cache persistente.
4. **Listagens:** número de queries constante em relação ao número de linhas da página (sem N+1). Filtros aplicados no SQL antes de paginar e contar.
5. **Auditoria consolidada:** 1 query de página e 1 de contagem, mais as queries das opções de filtro, sem consulta por estabelecimento. O tempo da contagem é medido em 200 mil registros. Se for alto, avaliam-se alternativas como contagem estimada ou paginação sem total, com aprovação.
6. **Planos de execução:** toda consulta crítica tem o plano analisado com `EXPLAIN (ANALYZE, BUFFERS)` em PostgreSQL nos cenários grandes, e o relatório da etapa registra a estratégia escolhida pelo planejador. Usar índice **não** é obrigatório: varredura sequencial, bitmap ou outra estratégia são aceitas quando o plano medido for melhor (por exemplo, tabelas pequenas ou filtros pouco seletivos, como um administrador do tenant que vê todas as unidades). O critério é tempo e buffers medidos, não a presença de índice.
7. **Seletividade:** índices compostos só entram quando o filtro organizacional for seletivo nos cenários medidos. Índices que o planejador não usa nos cenários realistas são removidos antes de concluir a etapa.
8. **Escrita:** cada índice novo é justificado por uma consulta existente ou planejada (seção 16), e o impacto de escrita fica registrado no relatório da etapa.
9. **Workers:** memória por worker estável durante a carga (sem crescimento contínuo) e isolamento verificado (14.3).

### 15.4 Cache persistente

Não é adotado. Se a medição mostrar que a query de permissões efetivas é relevante no total, a alternativa é cache por vínculo com chave `(membership_id, permissions_version)`. O `permissions_version` seria incrementado em toda alteração de atribuição, cargo, disponibilidade, vínculo ou situação da conta, na mesma transação. Isso só entra com aprovação e medição.

---

## 16. Banco de dados e índices

### 16.1 Índices existentes relevantes [C]

| Tabela | Índices |
| --- | --- |
| `clients` | PK; `document` único; `(name, id)` |
| `users` | PK; `email` único; `person_id` único; `account_id` |
| `roles` | PK; `(account_id, name)` único; `(account_id, system_key)` único |
| `permissions` | PK; `slug` único |
| `permission_role` | PK `(role_id, permission_id)` |
| `establishments` | PK; `client_id` |
| `points` | PK; `client_id`; `establishment_id` |
| `audit_records` | PK; `operation_id`; `(account_id, created_at, id)`; `(actor_id, created_at)`; `(entity_type, event)`; `legacy_key` único |
| `people` | PK; `(account_id, document)` único (cobre buscas por `account_id`) |
| `person_tag` | PK `(person_id, tag_id)` |
| `tags` | PK; `(account_id, module, name)` único; `(account_id, module, system_key)` único |
| `customers`, `suppliers` | PK; `person_id` único |
| `sessions` | PK; `user_id`; `last_activity` |

### 16.2 Lacunas atuais (E03)

| Índice proposto | Justificativa |
| --- | --- |
| `permission_role(permission_id)` | Junção permissão para cargo na resolução e no `whereHas('roles')`; exclusões em cascata |
| `person_tag(tag_id)` | `withCount('people')` em Etiquetas e a verificação de exclusão de etiqueta |
| `users(role_id)` | Contagem do último administrador em `AssignRoleAction`; FK. Deixa de ser necessário quando `role_id` for removido (E09) |
| `customers(account_id)`, `suppliers(account_id)` | Revisar na E07, quando passam a ter `company_id` (os índices vão para `(company_id, ...)`) |

### 16.3 Novas tabelas e índices

| Tabela | Índices e restrições | Consulta atendida |
| --- | --- | --- |
| `companies` | PK; `unique(id, account_id)` (alvo de FK composta); `unique(account_id, code)`; `unique(account_id, tax_root) where tax_root is not null`; `unique(account_id, individual_document) where individual_document is not null`; `(account_id, active, name)` | Listagem por tenant, URL, unicidade fiscal por tenant (6.7) |
| `establishments` (evoluída) | PK; `unique(id, company_id)` e `unique(id, account_id)` (alvos de FK); `unique(account_id, code)`; `unique(company_id) where is_primary`; `unique(company_id) where is_headquarters`; CHECK `not is_primary or active`; `unique(account_id, tax_document) where tax_document is not null`; `(company_id, active, name)` | Seletor, listagem, URL, invariantes |
| `account_memberships` | PK; `unique(user_id, account_id)`; `unique(account_id, person_id)`; `(account_id, status)` | Login e seleção de conta, listagem de usuários, resolução |
| `role_availability` | Únicos parciais da seção 9.1; `(role_id)` coberto pelo prefixo | Validação de cobertura, formulário de cargo |
| `role_assignments` | Três únicos parciais por escopo, começando por `membership_id`; `(account_id, role_id)`; `(account_id, company_id)`; `(account_id, establishment_id)` | Resolução por vínculo, último administrador, impacto ao alterar disponibilidade, remoção de unidades |
| `audit_records` | **E04:** colunas `company_id` e `establishment_id`, sem índice novo (ainda não há consulta por elas). **E08:** candidatos `(account_id, establishment_id, created_at, id)` e `(account_id, company_id, created_at, id)`, mantendo `(account_id, created_at, id)` para T. Só entram se o `EXPLAIN` nos cenários de 15.1 mostrar ganho (15.3, itens 6 e 7) | Listagem e contagem filtradas pelo escopo, ordenadas por data |
| `clients` | Sem alteração: `document` continua único global (PD-07) | |

**Impacto na escrita:**

- `audit_records` poderá passar de 5 para 7 índices na E08, só se os planos justificarem. A escrita é de baixa frequência relativa (uma linha por entidade e operação). Será medida no cenário de 200 mil registros.
- As tabelas organizacionais têm escrita rara.

### 16.4 Integridade entre níveis

- **FKs compostas:**
  - `establishments(company_id, account_id)` → `companies(id, account_id)`;
  - nas tabelas com unidade, `(establishment_id, company_id)` → `establishments(id, company_id)`;
  - em `role_assignments` e `role_availability`, os mesmos pares.
- **Compatibilidade:** o SQLite dos testes aceita FKs compostas, índices parciais e CHECK. Restrições adiáveis (`DEFERRABLE`) só existem na gramática PostgreSQL do Laravel 13 e por isso não são usadas (6.6).
- **Camadas de garantia:** a tabela da seção 6.6 indica, para cada invariante, o que é garantido por índice, FK, CHECK, transação com trava ou regra de domínio. A verificação `organization:check` detecta o que depende de várias linhas.
- **Migrations:** só o necessário para cada etapa, classificadas quanto à reversibilidade (seção 23.1), e `DB::prohibitDestructiveCommands` respeitado em produção.

---

## 17. Componentes e padrões UBold

### 17.1 Componentes a reutilizar (existentes)

| Necessidade | Componente |
| --- | --- |
| Moldura e título | `SaasLayout`, `PageHeader` (padrão Visdom), `PageHeaderTour` |
| Listagens | `BusinessList`, `BusinessListSearch`, `useBusinessListFilters`, `BusinessListTour`, `StatusLabel`, `TagLabel`, `Badge` |
| Formulários | `BusinessForm`, `ApiForm`, `TextInput`, `InputGroup`, `SelectControl`, `SelectChecklist`, `TagSelect`, `ChoiceControl`, `DatePicker`, `MaskedInput`; campos de identidade, endereço e contato de Pessoas (`PersonIdentityFields`, `PersonContactFields`, `ContactFields`) |
| Modais e visualização | `Modal`, `PreviewSection` |
| Ações | `Button` (Salvar verde, Cancelar branco, Excluir vermelho; ações de tabela com ícones `fa-info`, `fa-pencil`, `fa-eraser`), `RecordActions` |
| Navegação | `SidebarPrimary`, `TopBarPrimary`, `DropdownMenu`, `PageTabs`, `Tabs` |
| Estados | `AppLoader`, `ErrorState`, `InlineAlert`, `NotificationToast` |
| Ajuda e tours | `GuidedTour`, Central de Ajuda (`docs/ajuda`), roteiros (`docs/tours`) |

### 17.2 Desvios encontrados

- `tenant.tsx` (Minha conta e Pontos) usa `<details>` e `AccountForm`. Será substituído na E04.
- `/conta/usuarios` (`users.tsx`) está fora da sidebar e sem filtros. Entra no grupo Controle de acesso na E05, com `BusinessListSearch`.
- `DropdownMenu` e `ErrorState` existem mas não são usados nas telas de negócio. Serão avaliados no seletor e nos estados de erro.

### 17.3 Convenção para as telas desta frente

- Listagens: sempre `BusinessList` com filtros pelo hook.
- Formulários: `BusinessForm` com seções.
- Visualização: em `Modal` com `PreviewSection`.
- Título: com `PageHeader`.
- Rótulos de ação conforme o AGENTS.md.
- Ajuda e tour obrigatórios.
- Validação visual em 1440, 390 e 320 px.
- Sem componentes paralelos aos listados.

---

## 18. Telas administrativas e sidebar

### 18.1 Sidebar proposta

```text
Início                       (/conta, hoje rotulado "Minha conta")
Cadastros
├── Pessoas
└── Etiquetas
Organização
├── Dados da conta           (/conta/editar)
├── Empresas                 (novo; oculto quando há uma só empresa, exibindo o atalho para os dados dela)
└── Estabelecimentos         (novo)
Controle de acesso
├── Usuários                 (/conta/usuarios, hoje fora da sidebar)
└── Cargos
Auditoria
Central de Ajuda
```

**Minha conta** (pessoal) continua no menu do avatar e em `/settings`: Meu perfil, Segurança (inclui chaves de acesso e segundo fator) e Preferências (Aparência). "Sessões" não existe hoje e fica como item futuro dentro de Segurança.

O briefing sugere um item **Permissões**. O AGENTS.md proíbe telas de permissões. A proposta é substituí-lo por um **Resumo de acesso**, só leitura, dentro de Usuários e Cargos (PD-06).

URLs atuais preservadas. Os grupos usam o suporte a títulos de seção que `SidebarPrimary` já tem (`{ key, title }`, usado pela plataforma).

### 18.2 Empresas (E04)

- **Listagem:**
  - colunas: razão social, nome fantasia, CNPJ ou raiz formatados (ou "Não informado"), quantidade de estabelecimentos (`withCount`), situação e ações;
  - filtros: busca e situação.
- **Cadastro e edição** (`BusinessForm`):
  - Identificação: razão social, nome fantasia, tipo, documento, código;
  - Dados fiscais: regime, CNAE principal;
  - Unidade principal: nome, endereço, CNPJ completo, matriz. Aparece só no cadastro e provisiona a unidade (6.5);
  - Situação.
- **Visualização:** modal com dados e a lista de estabelecimentos.
- **Com uma empresa:** o menu leva direto à edição dela.

### 18.3 Estabelecimentos (E04)

- **Listagem:**
  - colunas: nome, empresa, badges Matriz/Filial e Principal, município/UF, CNPJ, situação e ações;
  - filtros: busca, empresa (oculto com uma empresa só) e situação.
- **Cadastro e edição:**
  - empresa (fixa na edição);
  - nome, nome fantasia, código;
  - CNPJ completo (validado contra a raiz da empresa), IE, IM;
  - endereço e contatos, reutilizando os campos de Pessoas;
  - Matriz/Filial, Principal (com promoção atômica), situação.
- **Ações:** Inativar exige que não seja a unidade principal e respeita a invariante do último ativo.
- **Acessos:** o vínculo de usuários e cargos não fica neste formulário. O modal mostra só o resumo de acesso daquela unidade.

### 18.4 Cargos (E05)

- **Seção Abrangência:**
  - opções "Toda a conta (inclui empresas e estabelecimentos futuros)" ou "Restrita";
  - no modo restrito, uma árvore com checkboxes de empresas (marcar a empresa cobre as unidades atuais e futuras) e estabelecimentos (`SelectChecklist`);
  - o texto fixo: "Define onde o cargo pode ser atribuído. Não concede acesso a ninguém".
- **Combinações inválidas:** marcar uma empresa desabilita as unidades dela.
- **Alteração que descobre atribuições:** exige confirmação, listando o que será removido.
- **Listagem:** ganha as colunas Abrangência e Atribuições (`withCount`).

### 18.5 Usuários e acesso da pessoa (E05)

- **Seção Acesso de usuário** (no formulário de pessoa, sem duplicar a identidade): troca o select único por uma tabela de atribuições.
  - Cada linha tem Cargo e "Onde" (Toda a conta, Empresa ou Estabelecimento).
  - Mostra e edita **só** as atribuições do escopo do operador. As de outros escopos não aparecem; operadores de escopo tenant veem todas.
  - As opções são filtradas pela disponibilidade do cargo e pela autoridade do operador.
  - Adicionar e remover linhas.
- **Dados de acesso:** o e-mail de acesso aparece só para leitura para todos os administradores (DA-24), com a ação "Enviar link de redefinição de senha" para operadores de escopo tenant. A situação do vínculo é editável só por operadores de escopo tenant.
- **Revogação:**
  - remover a linha retira a atribuição local;
  - suspender ou revogar o vínculo (hoje: retirar a etiqueta Usuário) é de escopo tenant;
  - a retirada da última atribuição local não suspende o vínculo.
- **Resumo de permissões efetivas:** no modal da pessoa e de Usuários, agrupado por contexto.
- **Listagem de usuários:** coluna Acessos (por exemplo, "Gartan / Guanambi: Gerente").

### 18.6 Seletor organizacional (E06)

Conforme a seção 12.4.

### 18.7 Ajuda e tours

| Tela | Artigo | Tour |
| --- | --- | --- |
| Empresas | `docs/ajuda/empresas.md` (novo) | `docs/tours/empresas.md` (novo) |
| Estabelecimentos | `docs/ajuda/estabelecimentos.md` (novo) | `docs/tours/estabelecimentos.md` (novo) |
| Dados da conta | `docs/ajuda/minha-conta.md` (revisado) | revisado |
| Cargos | `docs/ajuda/cargos-permissoes.md` (revisado) | `docs/tours/cargos.md` (revisado) |
| Usuários e acesso | `docs/ajuda/usuarios.md` e `pessoas.md` (revisados) | revisados |
| Seletor | `docs/ajuda/central-de-ajuda.md` ou artigo próprio | passo no `PageHeaderTour` ou na barra superior |
| Auditoria | `docs/ajuda/auditoria.md` (revisado) | `docs/tours/auditoria.md` (revisado) |

---

## 19. Plano incremental

### 19.1 Ordem e relação com a sequência do briefing

| Etapa | Nome | Corresponde no briefing | Ajuste |
| --- | --- | --- | --- |
| E01 | Auditoria complementar e fechamento arquitetural | E01 | Este documento |
| E02 | Contrato organizacional | E02 | |
| E03 | Contexto da conta e otimização do RBAC | E03 | Inclui harness de performance e índices faltantes |
| E04 | Empresas e estabelecimentos | E04 | Inclui `TaxDocument`, remoção de `points` e as telas no padrão (parte da E08 do briefing) |
| E05 | Vínculos, cargos e atribuições contextuais | E05 + parte da E09 | Os vínculos (`account_memberships`) entram aqui porque as atribuições dependem deles; adiá-los obrigaria refazer a E05 |
| E06 | Contexto de navegação e seletor | E06 | |
| E07 | Relações comerciais por empresa | nova | Primeiro módulo com recursos de nó `company`; valida o contrato com um caso real (clientes e fornecedores) |
| E08 | Auditoria contextual | E07 | Consolida a trilha legada; restrição da plataforma conforme PD-02 |
| E09 | Identidade em vários tenants | E09 | Interface de seleção de conta e tenant na URL; remoção dos legados de `users` |
| E10 | Homologação integrada | E10 | |

A E08 do briefing (padronização dos componentes) foi distribuída: cada etapa entrega as próprias telas no padrão (DA-17).

### Etapas

---

### E01: Auditoria complementar e fechamento arquitetural

**Identificação.** Objetivo: confirmar o diagnóstico e consolidar o contrato. Situação inicial: auditoria anterior não versionada.

**Diagnóstico.** Seção 03.

**Regras de negócio.** Seções 04 a 13.

**Escopo técnico.** Somente documentação.

**Componentes envolvidos.** Este arquivo.

**Dependências.** Nenhuma.

**Performance.** Linha de base da seção 15.2.

**Segurança.** Achados A12, A13 e A15.

**Testes.** Medição temporária fora do repositório, já removida.

**Critérios de aceite.** Documento revisado; decisões da seção 25 respondidas; registro da seção 28 atualizado.

**Riscos.** Decisões em aberto bloqueando a E02.

**Reversão.** Não se aplica.

**Entregáveis.** Este documento.

---

### E02: Contrato organizacional

**Identificação.** Objetivo: transformar as seções 05 a 13 e 27 numa convenção curta e normativa para quem implementa. Situação inicial: não existe convenção organizacional.

**Diagnóstico.** As convenções atuais (`docs/convencoes/permissoes.md`, `auditoria.md`) pressupõem uma conta única.

**Regras de negócio.**
- Invariantes da seção 5.3.
- Escopos, alvos admitidos e classificação das permissões (8.1, 8.2 e 8.2.1).
- Classes de propriedade (10.1).
- Regras de contexto e consolidado (12).
- Representação de operações sem estabelecimento: recurso de nó tenant (ou pessoa), rota sem segmento de contexto e auditoria sem unidade.

**Refinamentos obrigatórios desta etapa (registrados no fechamento da E01):**
1. **Identidade global (DA-24):**
   - formalizar a separação entre identidade autenticada, cadastro de pessoa e vínculo organizacional, com o que pertence a cada um e quem pode alterar;
   - documentar os fluxos seguros de alteração de e-mail (pela própria pessoa, com confirmação no novo endereço e aviso ao anterior) e de recuperação de acesso (redefinição de senha pelo Fortify, disparável pelo administrador sem acesso à senha);
   - registrar que suspender o acesso no tenant é operação do vínculo, e não da identidade;
   - definir as respostas neutras para e-mail existente (A20).
2. **Contatos compartilhados (DA-25):**
   - classificar campo a campo os dados de pessoa em centrais, de identificação e da relação comercial;
   - definir a visibilidade e a regra de edição de cada campo;
   - fechar o texto de interface que distingue "dados centrais" de "dados desta empresa";
   - registrar que nenhum dado é sincronizado automaticamente entre as camadas e que nenhum dado existente muda de camada sem decisão explícita.

**Escopo técnico.**
- `docs/convencoes/contexto-organizacional.md` (novo).
- Atualização de `docs/convencoes/permissoes.md` e `auditoria.md`.
- Proposta de emenda ao `AGENTS.md`: linhas sobre contexto organizacional, alvos de permissão e exceção fiscal (PD-01).
- Nomes finais dos prefixos de URL e do namespace `Organizacao`.

**Componentes envolvidos.** Somente documentação.

**Dependências.** E01 concluída; PD-01, PD-03 e PD-06 (aprovadas).

**Performance.** Registrar as metas estruturais (15.3) como regra.

**Segurança.** Checklist obrigatório por módulo (seção 27).

**Testes.** Revisão de texto; sem travessões, conforme o AGENTS.md.

**Critérios de aceite.** Convenção aprovada e referenciada no `AGENTS.md`; checklist de 10 perguntas publicado; política de identidade (DA-24) e política campo a campo de contatos (DA-25) aprovadas antes do início da E03.

**Riscos.** A convenção divergir do código das etapas seguintes. Mitigação: cada etapa atualiza a convenção.

**Reversão.** Reverter os documentos.

**Entregáveis.** Convenção, emenda ao `AGENTS.md` e relatório curto de execução.

**Execução.** Revisão 5. As convenções foram divididas por assunto (DA-26). Arquivos, decisões, conferências e pendências no registro da seção 26.

---

### E03: Contexto da conta e otimização do RBAC

**Identificação.** Objetivo: centralizar a conta atual e tornar a autorização de custo constante, **sem mudar comportamento**. Situação inicial: 29 leituras implícitas da conta; 2 queries por `can()`.

**Diagnóstico.**
- Existe: `Gate::before`, `permissionSlugs()`, `ApiPage` e `HelpCatalog`.
- Reaproveitado: toda a lógica de permissões atual (mesmo resultado, só memorizado).
- Problemas: A06, A08 e A16.

**Regras de negócio.**
- As permissões resultantes são idênticas às atuais para todos os perfis: administrador, membro, administrador SaaS, sem conta, conta inativa e acesso desativado.
- A revogação continua valendo na requisição seguinte.

**Escopo técnico.**
- **Backend:**
  - `AccountContext` (novo, `scoped`), que expõe a conta ativa do usuário autenticado e substitui as 29 leituras;
  - `EffectivePermissions` (novo, `scoped`), implementado nesta etapa sobre o modelo atual (`users.role_id`), com a mesma interface que a E05 vai estender;
  - `User::permissionSlugs()` delega a ele;
  - `Gate::before` usa o resolvedor.
- **Banco:** índices da seção 16.2 (`permission_role.permission_id`, `person_tag.tag_id`, `users.role_id`).
- **Testes:**
  - harness `tests/Performance`;
  - `Model::preventLazyLoading()` no `TestCase`;
  - teste de arquitetura proibindo `user()->account_id` em controllers e Actions.
- **Frontend:** nenhuma alteração.
- **Auditoria:** nenhuma alteração.

**Componentes envolvidos.**
- Existentes:
  - `app/Providers/AppServiceProvider.php`, `app/Models/User.php`, `app/Support/ApiPage.php`;
  - `app/Domains/Ajuda/Application/Queries/HelpCatalog.php`;
  - `DirectoryController`, `AccessController`, `UserController`, `ClientController`, `ProfilePhotoController`;
  - `AssignRoleAction`, `AuditQuery`, `EnforceAccountInspection`, `EnforceAudit`.
- Novos: `AccountContext`, `EffectivePermissions` e testes.

**Dependências.** E02 (nomes e namespaces).

**Performance.**
- Meta: queries de autorização por requisição no máximo 2, constantes (a Central de Ajuda cai de 26 para no máximo 15).
- Comparar antes e depois em todas as rotas da 15.2, também em PostgreSQL no ambiente isolado.
- Registrar linhas retornadas, tempo de processamento das checagens e memória (15.3, itens 2 e 3).
- `EXPLAIN (ANALYZE, BUFFERS)` da resolução com e sem os índices novos. Índices sem ganho medido não entram (15.3, item 7).

**Segurança.**
- Teste de vazamento entre requisições e verificação com worker FrankenPHP real (14.3).
- Testes de revogação existentes continuam verdes.

**Testes.**
- Unitário: o resolvedor.
- Feature: equivalência de permissões por perfil.
- Performance: orçamento por rota.
- Isolamento Octane.
- Regressão: os 110 testes atuais.

**Critérios de aceite.**
- Suíte completa verde.
- Orçamentos atingidos e registrados.
- Zero leituras implícitas da conta fora do `AccountContext`.
- `audit:check` e `permissions:sync` sem alterações.

**Riscos.** Regressão silenciosa de autorização. Mitigação: teste de equivalência por perfil antes e depois.

**Reversão.** Reversível sem perda (23.1): reverter o commit da etapa; a migration de índices tem `down()` e não altera dados.

**Entregáveis.** Código, testes, relatório de performance antes e depois, atualização de `docs/convencoes/permissoes.md`.

---

### E04: Empresas e estabelecimentos

**Identificação.** Objetivo: criar a estrutura organizacional real, com as telas. Situação inicial: conta tratada como estabelecimento; tabela `establishments` vazia; `points` como exemplo.

**Diagnóstico.**
- Reaproveitado: `establishments` (evoluída), `ClientController::editTenant` (vira Dados da conta), os campos de endereço e contato de Pessoas, `BusinessList` e `BusinessForm`.
- Problemas: A01 a A04, A15, A18.

**Regras de negócio.**
- Invariantes da seção 5.3.
- Provisionamento da 6.5.
- Dados por nível (6.3).
- Unicidade fiscal por tenant.
- Promoção atômica da unidade principal.
- Inativação com invariantes.
- Integridade e concorrência conforme a seção 6.6 (trava da empresa, CHECK da principal ativa, sem exclusão física).
- Empresa ou unidade inativa: não é promovida, não recebe unidades novas e não aparece em seleções. O bloqueio de rotas operacionais vem na E06.
- Unicidade fiscal da 6.7 (empresas e estabelecimentos por tenant; `clients.document` inalterado).
- Remoção de `points` (DA-14), com rota de escrita substituta nos testes de inspeção.
- Até a E05, todas as atribuições são de escopo tenant (`users.role_id`). Por isso a autorização das telas novas da E04 equivale à atual, avaliada sobre o tenant.
- Na migração, cada tenant recebe uma empresa e uma unidade principal criadas a partir dos dados atuais do `Client` (seção 23).

**Escopo técnico.**
- **Banco:**
  - `companies` (nova);
  - `establishments` evoluída: `account_id` no lugar de `client_id`, `company_id`, código, nomes, fiscais, endereço, flags, situação;
  - índices da 16.3;
  - `audit_records.company_id` e `establishment_id` (sem índice novo);
  - remoção de `points` (com PD-05).
- **Backend:**
  - domínio `app/Domains/Organizacao` (novo) com models `Company` e `Establishment` (`Auditable`);
  - `CreateCompanyAction`, `SaveCompanyAction`, `SaveEstablishmentAction`, `PromotePrimaryEstablishmentAction`;
  - Data e Queries correspondentes;
  - `TaxDocument` usado por Pessoas e Conta (A15);
  - `SaveAccountAction` passa a provisionar empresa e unidade;
  - comando `organization:check` (somente leitura, 6.6).
- **Autorização:** novas permissões `account.companies.list/view/create/update/activate/deactivate` e `account.establishments.list/view/create/update/activate/deactivate`. Os alvos admitidos ficam declarados conforme a 8.2.1, mas até a E05 só existem atribuições de tenant.
  - `account.establishment.update` vira `account.account-data.update` (nome fechado na E02), com migração do pivô.
  - `account.establishments.promote` para a promoção da unidade principal.
  - `account.points.*` são removidas com `permissions:sync --prune` auditado.
- **Frontend:**
  - telas Empresas e Estabelecimentos;
  - Dados da conta reescrita com `BusinessForm`;
  - `tenant.tsx` substituído pelo Início;
  - sidebar reorganizada (18.1, sem Controle de acesso, que vem na E05).
- **Auditoria:**
  - contrato `Auditable` com `auditCompanyId()` e `auditEstablishmentId()`;
  - `AuditLog::record` e `relationship` gravam os ids organizacionais;
  - empresas e estabelecimentos auditados com contexto (11.1), com o mesmo `operation_id` no provisionamento;
  - `audit:check` passa a exigir que todo model com `company_id` ou `establishment_id` informe esses ids.

**Componentes envolvidos.**
- Existentes:
  - `ClientController`, `SaveAccountAction`, `SaveAccountData`, `SavePersonDetailsData`;
  - `Auditable`, `AuditsChanges`, `AuditLog`, `CheckAuditCommand`;
  - testes `SaasPlatformTest` e `AccountInspectionTest` (usam as rotas de Pontos como escrita de prova);
  - `Point`, `SavePointAction`, `SavePointData`, `PointConfigurationDTO`;
  - `DefaultRolesAction`, `config/permissions.php`, `routes/saas.php`;
  - `tenant.tsx`, `account-form.tsx`, `accountNavigation.ts`, `guidance.ts`.
- Novos: domínio `Organizacao`, telas e artigos.

**Dependências.** E03; PD-01, PD-03 e PD-05 (aprovadas).

**Performance.**
- Listagens com `withCount` e seleção de colunas.
- Seletor e listagens filtrados por `account_id` com índices.
- Medição nos cenários de 10 empresas e 50 unidades.

**Segurança.**
- FKs compostas.
- Testes de ID de outro tenant em cada rota.
- Concorrência no provisionamento, na promoção e na inativação, em PostgreSQL (duas operações simultâneas não violam a 6.6).
- CNPJ igual em tenants diferentes sem revelar existência.
- Unidade de outra empresa do mesmo tenant não pode ser promovida nem movida de empresa.

**Testes.**
- Unitário: `TaxDocument`.
- Feature: cadastro, edição, promoção, inativação (principal, última ativa, empresa inativa), provisionamento transacional (falha no meio não deixa sobras), migração idempotente com dados do `Client`, remoção de `points`, permissões, eventos de auditoria com `company_id` e `establishment_id`, `organization:check`.
- Integração (PostgreSQL, ambiente isolado): concorrência da 6.6.
- Interface: 1440, 390 e 320 px.

**Critérios de aceite.**
- Cenários 1 a 5, 26 e 27 da matriz.
- Nenhum tenant sem empresa e unidade principal após a migração; `organization:check` sem violações.
- Nenhum evento novo de empresa ou unidade sem `company_id`.
- Ajuda e tours publicados.
- `audit:check` verde.
- Orçamentos de query cumpridos.

**Riscos.**
- Migração dos dados fiscais do JSON do `Client`.
- Renomeação de menus afetando tours.
- Remoção de `points` com dados em produção.

**Reversão** (23.1).
- Reversível sem perda **até** a criação de empresas ou unidades adicionais. A `down()` remove as empresas e unidades geradas pela migração.
- Depois que um tenant cadastra uma segunda empresa ou unidade, a `down()` passa a ser **reversível com perda**: os dados extras e os eventos de auditoria com contexto não têm representação no modelo anterior. Nesse caso, a recuperação é por backup e o caminho preferido é corrigir para frente.
- `points`: exportado antes da remoção e recriável pela migration reversa, com importação dos dados exportados.
- Permissões renomeadas: restauradas pelo mapeamento inverso.

**Entregáveis.** Código, migrations, testes, artigos `empresas.md` e `estabelecimentos.md`, tours, convenção atualizada, relatório.

---

### E05: Vínculos, cargos e atribuições contextuais

**Identificação.** Objetivo: autorização contextual completa. Situação inicial: um cargo por usuário, sem abrangência.

**Diagnóstico.**
- Reaproveitado: `roles`, `permission_role`, as regras de antiescalada, o resolvedor da E03 e a seção Acesso de usuário de Pessoas.
- Problemas: A05, A07 e A09.

**Regras de negócio.**
- Seções 07 a 09.
- Atribuições migradas de `users.role_id` com escopo tenant (comportamento idêntico).
- Cargos existentes com disponibilidade `tenant`.
- Administrador integral (DA-09) e tipos de administrador da 8.3.
- Classificação de **todas** as permissões por alvo admitido (8.2.1).
- Três camadas de administração de usuários (DA-19, 8.5): identidade global, vínculo com o tenant e atribuições locais.
- Antiescalada da 8.5.
- Consulta contextual mínima da auditoria e proteção da concessão de auditoria local (11.1.1).
- Edição de pessoas por classe de dado (10.2.1). Até a E07, toda relação comercial é da única empresa do tenant, então a regra de identificação só passa a restringir alguém quando houver mais de uma empresa.
- Nome e e-mail da pessoa deixam de propagar para a identidade (A20). A verificação de e-mail de identidade usa resposta neutra.

**Escopo técnico.**
- **Banco:**
  - `account_memberships` (a partir de `users.account_id` e `person_id`);
  - `roles.availability`, `role_availability`, `role_assignments`;
  - índices da 16.3.
- **Backend:**
  - `EffectivePermissions` com escopos, alvos e as duas queries da 9.2;
  - `config/permissions.php` com `targets`, e a validação na sincronização;
  - separação das camadas da 8.5: identidade só pelo próprio usuário, pelos fluxos seguros, e pela plataforma, sem exceção para vínculo único (DA-24); vínculo só por escopo tenant; atribuições pelo nó;
  - `SavePersonAction` com regras por classe de dado (10.2.1) e sem propagação para a identidade;
  - `CreateUserAction` e `SavePersonAction` com mensagem neutra para e-mail de identidade existente;
  - `AuditQuery` com a consulta contextual mínima (11.1.1);
  - `SaveRoleAction` com disponibilidade;
  - `AssignRoleAction` substituída por `SaveRoleAssignmentsAction` (atribuições em lote, transacional, auditada);
  - `CreateUserAction` e `SavePersonAction` criam o vínculo;
  - último administrador por vínculo.
- **Frontend:**
  - formulário de cargo com Abrangência;
  - seção de atribuições no formulário de pessoa, listando só as atribuições do escopo do operador;
  - campos de identificação da pessoa somente leitura quando o operador não cobre todas as empresas relacionadas;
  - Usuários na sidebar, com filtros;
  - resumos de acesso.
- **Auditoria:**
  - criação e remoção de atribuições com `company_id` e `establishment_id` do escopo (11.1);
  - mudanças de disponibilidade registradas como relação do cargo;
  - novas chamadas a `DirectoryAudit` proibidas; as chamadas de `AssignRoleAction` saem junto com ela;
  - consulta contextual mínima em `/conta/auditoria` (11.1.1); concessão local de `account.audit.*` liberada só com ela ativa.

**Componentes envolvidos.**
- Existentes:
  - `Role`, `SaveRoleAction`, `SaveRoleData`, `AssignRoleAction`, `DefaultRolesAction`, `SyncPermissionsAction`, `PermissionRegistry`;
  - `CreateUserAction`, `ProvisionUserData`, `SavePersonAction`, `SavePersonData`, `UserDirectoryQuery`, `User`;
  - `AuditQuery`, `AuditController` (consulta contextual mínima);
  - `role-form.tsx`, `AccessList.tsx`, `UserFields.tsx`, `person-form.tsx`, `users.tsx`, `PersonPreview.tsx`.
- Novos: `AccountMembership`, `RoleAssignment`, `SaveRoleAssignmentsAction`.

**Dependências.** E04 (empresas e unidades existem); E03.

**Performance.**
- Resolução em 2 queries para 1, 5 e 30 atribuições e 50 unidades, com linhas, tempo e memória registrados (15.3, itens 1 a 3), em PostgreSQL.
- Formulário de cargo: árvore de empresas e unidades em 2 queries.
- Contagem de atribuições com `withCount`.

**Segurança.**
- Matriz de escalada: atribuir fora da própria autoridade, cargo indisponível, permissões além das próprias, escopo de outro tenant, administrador de unidade tentando ação de empresa.
- Tomada de conta: administrador de empresa ou unidade tentando alterar e-mail de acesso, senha, situação da identidade ou o vínculo de qualquer usuário.
- Administração local legítima: administrador de unidade concede e retira o cargo de uma pessoa que também atua em outra empresa, sem ver nem alterar as atribuições de lá.
- Retirada de atribuição de fora do escopo ou de nível superior.
- Auditor local vê só os eventos dos nós autorizados na lista, na contagem, nas opções de filtro e no detalhe; sem eventos de tenant.
- Concessão local de auditoria bloqueada se a consulta contextual não estiver ativa.
- Existência de e-mail em outro tenant não revelada.
- Nenhuma permissão `platform` em cargos de tenant.

**Testes.**
- Cenários 6 a 16, 22 (consulta mínima), 29, 36 a 42 e 50 a 53.
- Equivalência pós-migração.
- Alteração de disponibilidade que descobre atribuições.

**Critérios de aceite.**
- Matriz de cenários verde.
- Usuários existentes com exatamente as mesmas permissões após a migração.
- 2 queries de autorização no máximo.
- Ajuda e tours de Cargos, Usuários e Pessoas revisados.

**Riscos.**
- Complexidade do formulário de atribuições.
- Divergência temporária entre `users.role_id` e `role_assignments`. Mitigação: na E05, `role_id` deixa de ser lido e passa a ser só espelho até a E09.

**Reversão** (23.1). **Reversível com perda.**
- A `down()` recria a leitura de `users.role_id`, que foi mantido como espelho, a partir da atribuição de escopo tenant de cada vínculo.
- Atribuições de empresa e de unidade, vários cargos por vínculo e disponibilidades restritas **não têm representação** no modelo anterior e seriam perdidas. Um usuário que só tem atribuição de unidade ficaria sem cargo.
- Antes de qualquer `down()` em produção: exportar `role_assignments` e `role_availability`, registrar os vínculos afetados e obter aprovação.
- **Janela de reversão:** enquanto nenhum tenant tiver atribuições fora do escopo tenant, a reversão é sem perda. Depois disso, o caminho preferido é corrigir para frente.

**Entregáveis.** Código, migrations, testes, convenção de permissões atualizada, ajuda, tours, relatório.

---

### E06: Contexto de navegação e seletor organizacional

**Identificação.** Objetivo: operações de empresa e de estabelecimento com contexto na URL, validado e seguro para várias abas. Situação inicial: não há contexto de unidade nas requisições.

**Diagnóstico.** Reaproveitado: `AccountContext` (E03), `EffectivePermissions` (E05), `TopBarPrimary`, `apiRequest` e `useApiPage`.

**Regras de negócio.** Seção 12. Rotas administrativas sem contexto continuam funcionando (cenário 23).

**Escopo técnico.**
- **Backend:**
  - `OrganizationContext`;
  - middleware de contexto;
  - grupos de rota `/conta/empresa/{empresa}` e `/conta/estabelecimento/{estabelecimento}` (DA-28);
  - redirecionamento para empresas pequenas;
  - descritor de conjunto autorizado para consultas consolidadas;
  - um módulo-fixture **somente em testes** para os cenários 17, 18, 19, 20 e 24.
- **Frontend:**
  - seletor na barra superior;
  - URLs relativas ao contexto em `apiRequest`, `ApiLink` e `ApiForm`;
  - `ApiPage` passa a enviar os contextos autorizados só quando a página usa o seletor.
- **Banco:** nenhuma alteração.

**Componentes envolvidos.**
- Existentes: `bootstrap/app.php`, `ApiPage`, `routes/*`, `TopBarPrimary`, `apiRequest.ts`, `ApiLink`, `ApiForm`, `useApiPage`, `SaasLayout`, `guidance.ts`.
- Novos: `OrganizationContext`, middleware, seletor.

**Dependências.** E05.

**Performance.**
- Middleware com no máximo 1 query (contexto e empresa juntos) além da resolução de permissões.
- Lista do seletor em 1 query.

**Segurança.** Manipulação de código na URL; código de outro tenant; abas simultâneas; consolidado restrito ao conjunto autorizado.

**Testes.**
- Cenários 17, 18, 19, 20, 23, 24 e 28.
- Navegador: duas abas em unidades diferentes executando escritas.

**Critérios de aceite.**
- Cenários verdes.
- Seletor oculto para tenant com 1 empresa e 1 unidade.
- Nenhuma operação de unidade sem contexto na URL.

**Riscos.** Rotas existentes quebrando com o novo middleware. Mitigação: ele só se aplica aos novos grupos.

**Reversão.** Remover os grupos de rota e o middleware. Não há alteração de banco.

**Entregáveis.** Código, testes, artigo sobre troca de contexto, tours, relatório.

---

### E07: Relações comerciais por empresa (clientes e fornecedores)

**Identificação.** Objetivo: primeiro módulo com recursos de nó `company`, validando o contrato com dados reais. Situação inicial: clientes e fornecedores 1:1 com a pessoa e por conta (A14).

**Diagnóstico.** Reaproveitado: Pessoas, etiquetas especiais, `SaveCustomerAction`, `SaveSupplierAction` e os filtros Clientes/Fornecedores da listagem de Pessoas.

**Regras de negócio.**
- Seção 10.2.
- A mesma pessoa pode ter relação com várias empresas.
- Etiquetas comerciais derivadas das relações e filtradas pelas empresas autorizadas a quem lê (10.2); `person_tag` deixa de guardar Cliente e Fornecedor.
- Edição do cadastro base pelo alvo `person`, agora com várias empresas possíveis.
- Filtros Clientes e Fornecedores restritos às empresas autorizadas.
- Com uma empresa só, a interface atual não muda.
- Na migração, as relações recebem a empresa única de cada tenant.

**Escopo técnico.**
- **Banco:** `customers.company_id` e `suppliers.company_id`; únicos `(company_id, person_id)`; índices `(company_id, active)`.
- **Backend:** Actions e Queries com contexto de empresa; alvo `company` para `account.customers.*` e `account.suppliers.*`; derivação das etiquetas comerciais em lote na listagem (uma query para as relações da página, sem N+1).
- **Frontend:** escolha de empresas no formulário de pessoa, só quando houver mais de uma; filtro de empresa na listagem.
- **Auditoria:** eventos com `company_id`.

**Componentes envolvidos.**
- Existentes: `Customer`, `Supplier`, `SaveCustomerAction`, `SaveSupplierAction`, `SavePersonAction`, `PeopleDirectoryQuery`, `people.tsx`, `person-form.tsx`, `PersonPreview.tsx`.

**Dependências.** E06.

**Performance.** Filtro por empresa no SQL; eager loading das relações na listagem.

**Segurança.** Usuário de uma empresa não vê a relação com outra, nem pela etiqueta, filtro, busca ou visualização; IDs de relação de outra empresa negados; edição do cadastro base negada quando a pessoa tem relação com empresa não coberta.

**Testes.** Cenários 21, 43 e 44; regressões de `CustomerDirectoryTest`, `SupplierDirectoryTest` e `PeopleDirectoryTest`.

**Critérios de aceite.** Cenário 21 verde; tenants de uma empresa sem mudança visível; ajuda de Clientes e Fornecedores revisada.

**Riscos.** Mudança na semântica das etiquetas especiais.

**Reversão** (23.1).
- **Sem perda** enquanto cada pessoa tiver no máximo uma relação de cada tipo. A `down()` remove `company_id`, recria o único por `person_id` e regrava as etiquetas comerciais em `person_tag`.
- **Ponto de não retorno:** quando alguma pessoa passar a ter relação com duas empresas, a `down()` não consegue representar o dado (o único por `person_id` volta), e precisaria descartar relações. Ela verifica e **aborta** com relatório. Não descarta dados.
- A recuperação a partir daí é restaurar o backup tirado antes da implantação, com perda das alterações posteriores, ou corrigir para frente (preferido).
- Para reduzir o risco, a escolha de várias empresas no formulário só é liberada depois de um período de validação com a migração já aplicada.

**Entregáveis.** Código, migrations, testes, ajuda, tours, relatório.

---

### E08: Auditoria contextual

**Identificação.** Objetivo: auditoria filtrada pelo escopo autorizado, sem trilha paralela. Situação inicial: A10, A11 e A12.

**Diagnóstico.** Reaproveitado: `audit_records`, `AuditLog`, `AuditQuery`, `AuditController` e a interface atual.

**Regras de negócio.** Seção 11. As colunas e a gravação do contexto existem desde a E04 (11.1), e a consulta contextual mínima desde a E05 (11.1.1). Esta etapa trata filtros por empresa e unidade, escolha explícita da visão consolidada, índices de leitura medidos, legado e plataforma. Se a E05 tiver saído com a auditoria local bloqueada, esta etapa entrega a consulta contextual e o desbloqueio.

**Escopo técnico.**
- **Banco:** índices de leitura candidatos (16.3), só com ganho medido; migração de `directory_audit_events` pendentes; remoção posterior das tabelas legadas.
- **Backend:**
  - evolução da consulta contextual da E05 (ou sua entrega e o desbloqueio, se a E05 tiver saído bloqueada);
  - filtros de empresa e unidade em `AuditFilterData`;
  - restrição da plataforma (PD-02).
- **Frontend:** filtros de empresa e unidade (ocultos quando não houver mais de uma opção); detalhe mostra empresa e unidade.

**Componentes envolvidos.**
- Existentes: `Auditable`, `AuditsChanges`, `AuditLog`, `AuditContext`, `AuditQuery`, `AuditController`, `AuditFilterData`, `DirectoryAudit`, `audit.tsx`, `AuditRecordDetails.tsx`, `CheckAuditCommand`.

**Dependências.** E06; E07 recomendada (gera eventos de empresa reais).

**Performance.**
- Cenário de 200 mil registros e 50 unidades: página e contagem com índice, sem consulta por unidade.
- `EXPLAIN ANALYZE` registrado.

**Segurança.** Cenários 22 e 26; detalhe de um evento fora do escopo retorna 404; opções de filtro sem vazamento.

**Testes.** Cenário 22, consolidação legada sem duplicatas, plataforma conforme PD-02, `audit:check`.

**Critérios de aceite.**
- Nenhuma escrita em `directory_audit_events`.
- Cenários verdes.
- Metas da 15.3 cumpridas.
- Ajuda e tour de Auditoria revisados.

**Riscos.** Perda de informação na consolidação. Mitigação: comparação de contagens e amostras antes de remover a tabela.

**Reversão** (23.1).
- Consulta com escopo e índices: reversível sem perda (as colunas são da E04).
- Ao reverter o código da E08, a consulta contextual mínima da E05 continua valendo. Nunca se volta à visibilidade anterior à E05.
- Migração das linhas legadas: reversível, porque as linhas originais são mantidas até a remoção.
- Remoção das tabelas legadas: migration separada, só depois de validar contagens e amostras, com exportação prévia. É de recuperação somente por backup ou pela importação da exportação.

**Entregáveis.** Código, migrations, testes, convenção de auditoria atualizada, ajuda, tours, relatório.

---

### E09: Identidade em vários tenants

**Identificação.** Objetivo: a mesma identidade operando tenants distintos, com autorizações independentes. Situação inicial: os vínculos já existem (E05), mas há apenas um por usuário.

**Diagnóstico.** Reaproveitado: `account_memberships`, `AccountContext`, `OrganizationContext`, inspeção e Fortify.

**Regras de negócio.**
- Seção 07.
- Seleção de conta após o login quando houver mais de um vínculo.
- Tenant na URL (PD-04).
- Convite de identidade existente para outro tenant cria só o vínculo e a pessoa naquele tenant.
- Revogar o vínculo afeta só aquele tenant.
- A liberação de vários vínculos por identidade fica atrás de uma chave de configuração, desligada por padrão, até concluir a validação.
- A contração das colunas legadas de `users` é a última entrega da etapa e só ocorre depois de um período de estabilidade com a chave ligada.

**Escopo técnico.**
- **Banco:** remoção de `users.account_id`, `users.role_id`, `users.person_id` e `users.is_account_admin` (contração).
- **Backend:** resolução do tenant pela URL ou pelo host; seleção de conta; `EnforceAccountInspection` e `AccountInspectionSession` por vínculo; `CreateUserAction` com identidade existente.
- **Frontend:** tela de seleção de conta; seletor de conta na barra superior quando houver mais de uma.

**Componentes envolvidos.**
- Existentes: `User`, `CreateUserAction`, `ProvisionUserData`, `FortifyServiceProvider`, `JsonNavigation`, `EnforceAccountInspection`, `AccountInspectionSession`, `StartAccountInspectionAction`, `AccountInspectionUsersQuery`, `TopBarPrimary`.

**Dependências.** E05 a E08; PD-04.

**Performance.** Seleção de conta em 1 query; nenhum impacto nas requisições comuns.

**Segurança.** Cenários 25 e 26; abas em tenants diferentes; cookies e domínio (se for subdomínio).

**Testes.** Cenários 25, 26 e 16 por vínculo; inspeção com vínculos; regressão de autenticação (Fortify, 2FA, passkeys).

**Critérios de aceite.** Cenários verdes; nenhuma referência às colunas removidas; ajuda de acesso revisada.

**Riscos.** Configuração de domínio (subdomínio); sessões existentes durante a contração. Mitigação: forçar novo login controlado na implantação.

**Reversão** (23.1).
- **Antes da contração e enquanto nenhuma identidade tiver dois vínculos:** reversível sem perda. Basta desligar a chave e reverter o código, porque as colunas legadas ainda existem.
- **Depois que alguma identidade tiver dois vínculos:** **sem reversão para o modelo anterior.** O e-mail é único em `users` e `users.account_id` só representa um tenant. Voltar exigiria descartar vínculos ou duplicar identidades, que é inaceitável.
  - Nesse ponto, a recuperação é por correção para frente.
  - Em caso de incidente grave, restaurar o backup anterior à liberação da chave, com perda de todas as alterações posteriores, mediante aprovação explícita.
- **Contração das colunas:** de recuperação somente por backup. A `down()` recria as colunas, mas só consegue preenchê-las para identidades com um único vínculo. Para as demais, aborta com relatório.
- Antes da contração: backup verificado com restauração testada no ambiente isolado.

**Entregáveis.** Código, migrations, testes, ajuda, relatório.

---

### E10: Homologação integrada

**Identificação.** Objetivo: validar o conjunto em ambiente isolado com dados sintéticos e regressões.

**Escopo técnico.**
- Matriz completa (seção 21).
- Benchmarks dos cenários da 15.1 comparados à linha de base.
- Verificação de workers (14.3).
- Revisão dos índices com `EXPLAIN`.
- Validação visual de todas as telas afetadas.
- Revisão de ajuda e tours.

**Dependências.** E02 a E09.

**Critérios de aceite.** Seção 22 integralmente atendida; relatório final com evidências.

**Reversão.** Não se aplica (etapa de verificação).

**Entregáveis.** Relatório de homologação, benchmarks e pendências para a próxima frente.

---

## 20. Matriz de dependências

| Etapa | Depende de | Decisões necessárias | Libera |
| --- | --- | --- | --- |
| E01 | | | E02 |
| E02 | E01 | Nenhuma pendente (PD-01 a PD-06 aprovadas) | E03 |
| E03 | E02 | | E04 |
| E04 | E03 | Nenhuma pendente (PD-03 e PD-05 aprovadas) | E05 |
| E05 | E04 | | E06 |
| E06 | E05 | | E07, E08 |
| E07 | E06 | | E08 (recomendado) |
| E08 | E06 (E07 recomendado) | Nenhuma pendente (PD-02 aprovada) | E09 |
| E09 | E05 a E08 | Nenhuma pendente (PD-04 aprovada) | E10 |
| E10 | E02 a E09 | | |

A E07 e a E08 podem ser feitas em paralelo se for necessário, mas a E08 testa melhor com eventos reais de empresa.

**Mudanças de dependência na revisão 2:**
- A ordem das etapas não mudou.
- A E04 assumiu as colunas e o contrato de contexto da auditoria, antes da E08.
- A E05 passou a classificar todas as permissões por alvo e, na revisão 3, a entregar a consulta contextual mínima da auditoria (11.1.1). Sem ela, a concessão local de auditoria fica bloqueada até a E08.
- A E08 ficou com leitura, filtros, consolidação, índices de leitura, legado e plataforma.

---

## 21. Matriz de testes

| # | Cenário | Etapa | Tipo |
| --- | --- | --- | --- |
| 1 | Tenant com 1 empresa e 1 estabelecimento: tudo funciona sem seleção | E04, E06 | Feature + navegador |
| 2 | 1 empresa e várias filiais | E04 | Feature |
| 3 | Várias empresas de ramos diferentes, sem compartilhamento de dados de empresa | E04, E07 | Feature |
| 4 | Empresas com quantidades diferentes de unidades | E04 | Feature + performance |
| 5 | Criação automática e transacional da unidade principal (incluindo falha no meio) | E04 | Feature |
| 6 | Usuário com acesso a uma empresa só | E05 | Feature |
| 7 | Usuário com acesso a várias empresas | E05 | Feature |
| 8 | Usuário com acesso a algumas unidades | E05 | Feature |
| 9 | Cargos diferentes por unidade | E05, E06 | Feature |
| 10 | Cargo disponível em várias empresas, atribuído em uma | E05 | Feature |
| 11 | Cargo atribuído para a empresa inteira (unidade nova incluída) | E05 | Feature |
| 12 | Administrador integral do tenant recebe permissões novas e nenhuma de plataforma | E05 | Feature |
| 13 | Administrador restrito a uma empresa | E05 | Segurança |
| 14 | Administrador restrito a uma unidade não executa ação de empresa ou de tenant | E05 | Segurança |
| 15 | Novas permissões no catálogo: alvos obrigatórios; administrador recebe; demais não | E05 | Feature |
| 16 | Revogação de cargo ou de vínculo durante sessão ativa | E03, E05, E09 | Segurança |
| 17 | Manipulação de URL (código de outra empresa ou tenant) | E06 | Segurança |
| 18 | Manipulação de identificador em escrita e leitura | E04 a E08 | Segurança |
| 19 | Produto compartilhado entre filiais (módulo-fixture) | E06 | Feature |
| 20 | Consulta de estoque de outra unidade negada (módulo-fixture) | E06 | Segurança |
| 21 | Pessoa cliente em duas empresas com históricos separados | E07 | Feature |
| 22 | Auditoria local e consolidada, com contagens e opções de filtro | E08 | Feature + performance |
| 23 | Operação administrativa sem estabelecimento | E06 | Feature |
| 24 | Abas simultâneas em unidades diferentes | E06 | Feature + navegador |
| 25 | Identidade com vínculos em dois tenants | E09 | Segurança |
| 26 | Acesso a empresa de outro tenant | E04, E09 | Segurança |
| 27 | Criação concorrente de empresas e unidades principais | E04 | Integração (PostgreSQL) |
| 28 | Worker atendendo requisições de contas diferentes sem vazamento | E03, E06 | Integração (Octane) |
| 29 | Permissões com vários cargos e escopos em 2 queries, com linhas igual a atribuições + permissões dos cargos distintos, e tempo e memória medidos | E05 | Performance (PostgreSQL) |
| 30 | Listagens sem N+1 proporcional às linhas | E03 a E08 | Performance |
| 31 | Inspeção SaaS com atribuições contextuais preserva a autoria | E05, E09 | Segurança |
| 32 | Auditoria da plataforma sem eventos de negócio fora da inspeção (PD-02) | E08 | Segurança |
| 33 | `TaxDocument`: CNPJ alfanumérico, raiz, CPF, comparação de pessoa jurídica | E04 | Unitário |
| 34 | Mesmo CNPJ em tenants diferentes sem revelar existência | E04 | Segurança |
| 35 | Alteração de disponibilidade que descobre atribuições | E05 | Feature |
| 36 | Administrador de empresa cria e edita unidades da própria empresa, atribui cargos nelas e vê a auditoria delas; não cria cargos nem vê eventos de tenant | E05, E08 | Feature |
| 37 | Administrador de unidade edita os dados da unidade, atribui cargos nela e vê a auditoria dela; não inativa a unidade nem promove principal | E05, E08 | Feature |
| 38 | Administrador de empresa ou unidade tenta alterar e-mail de acesso, senha, situação da identidade ou o vínculo de qualquer usuário (negado) | E05 | Segurança |
| 39 | Operador retira atribuição de fora do próprio escopo ou de nível superior ao seu (negado) | E05 | Segurança |
| 40 | Operador atribui cargo com permissão que ele não tem naquele nó | E05 | Segurança |
| 41 | Auditor local vê só eventos dos nós autorizados (lista, contagem, opções, detalhe, técnico); concessão local de auditoria bloqueada sem consulta contextual ativa | E05 | Segurança |
| 42 | Pessoa com relação em empresa não coberta: contato e endereço editáveis, identificação negada (403 mesmo com envio forjado); sem relação, tudo editável | E05, E07 | Segurança |
| 43 | Etiquetas Cliente e Fornecedor, filtros e visualização não revelam relação com empresa não autorizada | E07 | Segurança |
| 44 | `down()` da E07 aborta, sem descartar dados, quando há pessoa com relação em duas empresas | E07 | Integração |
| 45 | Invariantes da 6.6: inativar a principal, inativar a última ativa, promover unidade inativa ou de outra empresa, inativações concorrentes | E04 | Feature + integração (PostgreSQL) |
| 46 | Eventos de empresa, unidade e atribuição gravados com `company_id` e `establishment_id` desde a sua etapa | E04, E05, E07 | Feature |
| 47 | Contexto de empresa ou unidade inativa recusa operações e permite consultar histórico | E06 | Feature |
| 48 | Isolamento com worker FrankenPHP real (`--workers=1`), requisições alternadas e com erro | E03, E06, E10 | Integração (Octane) |
| 49 | `clients.document` mantém a unicidade global só no fluxo da plataforma; empresas de tenants distintos com o mesmo CNPJ são aceitas sem revelar existência | E04 | Feature + segurança |
| 50 | Administrador de unidade concede e retira cargo, na própria unidade, de pessoa que também atua em outra empresa; as atribuições de lá não aparecem nem mudam | E05 | Feature |
| 51 | Retirar a última atribuição local não suspende o vínculo nem altera a identidade | E05 | Feature |
| 52 | Edição de nome e e-mail da pessoa não altera a identidade global; e-mail usado em outro tenant recebe resposta neutra | E05 | Segurança |
| 53 | Nenhum administrador de tenant altera e-mail de acesso, senha ou situação da identidade, mesmo com vínculo único; troca de e-mail só pelo fluxo da própria pessoa, com confirmação; o administrador apenas dispara o link de redefinição | E05 | Segurança |

Os testes 27, 29, 45 (parte concorrente), 48 e as medições exigem PostgreSQL e, no caso do 48, Octane real. A suíte padrão usa SQLite em memória por regra do projeto (`tests/bootstrap.php`), então ele roda no ambiente isolado do harness, sem alterar esse isolamento. Os demais cenários continuam na suíte padrão em SQLite.

---

## 22. Critérios gerais de aceite

Uma etapa só é **Concluída** quando:

1. A suíte completa está verde, junto com os testes novos dos cenários da etapa.
2. `audit:check`, `permissions:sync`, Pint, PHPStan e `npm run check` passam.
3. Os orçamentos de performance estão medidos e registrados, comparados com a linha de base.
4. Ajuda, tours e convenções foram atualizados conforme o AGENTS.md.
5. A validação visual foi feita (1440, 390 e 320 px) nas telas alteradas.
6. O relatório de execução está registrado na seção 26, com evidências e pendências.
7. Houve revisão e aprovação pelo responsável do produto.

---

## 23. Estratégia de migração

Padrão expandir, migrar, contrair. Uma etapa por vez e nenhuma exclusão de dados sem aprovação explícita.

### 23.1 Classes de reversibilidade (revisão 2)

| Classe | Significado | Exigência antes de implantar |
| --- | --- | --- |
| **R: reversível** | A `down()` restaura o estado anterior sem perder dados | Teste da `down()` em ambiente isolado |
| **RP: reversível com perda** | A `down()` funciona, mas descarta dados que o modelo anterior não representa. A partir de certo ponto, deve abortar em vez de descartar | Lista do que se perde; verificação na `down()` que aborta com relatório; exportação das tabelas afetadas; aprovação para executar |
| **B: recuperação somente por backup** | Não existe `down()` que reconstrua o estado anterior sem decisão humana | Backup verificado com restauração testada no ambiente isolado; janela de reversão definida; preferência por corrigir para frente |

Regras gerais:
- Nenhuma etapa é declarada integralmente reversível se a `down()` descartar dados criados depois da implantação.
- Para cada implantação em produção: backup prévio (`pg_dump`) com restauração ensaiada; janela de reversão registrada no quadro (seção 26); ponto de não retorno identificado.
- Mudanças com ponto de não retorno são liberadas por chave de configuração ou em duas implantações (estrutura primeiro, uso depois). Assim, a reversão é sem perda até o primeiro uso real.

### 23.2 Migrações por etapa

| Etapa | Migração | Dados existentes | Classe |
| --- | --- | --- | --- |
| E03 | Índices | Sem impacto | R |
| E04 | `companies`; evolução de `establishments`; provisionamento por tenant a partir de `clients` | Para cada conta: empresa com razão social = `clients.name`, nome fantasia = `details.trade_name`, documento = `clients.document` via `TaxDocument` (raiz se for CNPJ; CPF se for pessoa física); unidade principal com o endereço de `details.address` e o CNPJ completo. Os dados do `Client` são preservados como dados do contratante. Linhas em `establishments` (esperado: 0) mapeadas para a empresa criada ou, se houver ambiguidade, a migração aborta com relatório. Idempotente por conta | R até existir segunda empresa ou unidade; depois RP |
| E04 | `audit_records.company_id`, `establishment_id` | Histórico anterior com valores nulos (corretamente de nível tenant), sem preenchimento retroativo por suposição | R |
| E04 | `clients.document` | Sem alteração (PD-07) | |
| E04 | Remoção de `points` | Só com PD-05 aprovada: exportação, conferência de que nenhum módulo depende, migration reversa que recria a tabela e importa a exportação | R (com a exportação) |
| E04 | Permissões renomeadas e removidas | Mapeamento no pivô na mesma transação, auditado | R |
| E05 | `account_memberships`, `role_availability`, `role_assignments` | Um vínculo por usuário com conta; uma atribuição de escopo tenant por `users.role_id`; todos os cargos com disponibilidade `tenant`. Reproduz exatamente o acesso atual. `users.role_id` mantido como espelho | R até a primeira atribuição fora do escopo tenant; depois RP |
| E07 | `company_id` em `customers` e `suppliers`; etiquetas comerciais derivadas | A empresa única de cada tenant (equivalente ao estado atual) | R até existir pessoa com relação em duas empresas; depois a `down()` aborta (recuperação B) |
| E08 | Índices de leitura; consolidação legada | Linhas legadas copiadas com `legacy_key`; originais mantidas até a validação | R; a remoção das tabelas legadas é B (com exportação) |
| E09 | Liberação de vários vínculos; remoção das colunas legadas de `users` | Liberação por chave; contração só depois do período de estabilidade | R até a primeira identidade com dois vínculos; depois B. A contração é B |

**Antes da E04**, executar no ambiente de produção, em modo leitura e com autorização, as contagens de `clients`, `establishments`, `points`, `users` e `directory_audit_events`. O banco local tem 1 conta, 0 estabelecimentos, 0 pontos, 2 usuários e 2 cargos.

---

## 24. Riscos e reversão

| # | Risco | Prob. | Impacto | Mitigação | Reversão |
| --- | --- | --- | --- | --- | --- |
| R01 | Regressão de autorização na centralização (E03) | Média | Alto | Teste de equivalência por perfil antes e depois | Reverter o commit da etapa |
| R02 | Vazamento entre tenants ao trocar a conta implícita | Baixa | Crítico | `AccountContext` único; teste de arquitetura; testes de isolamento | Reverter a etapa |
| R03 | Dados fiscais do `Client` inconsistentes no JSON | Média | Médio | Validação com `TaxDocument`; relatório da migração; campos inválidos ficam como "Não informado" para correção manual | `down()` remove empresas e unidades geradas (R até existir segunda empresa) |
| R04 | Produção com `points` ou `establishments` preenchidos | Baixa | Médio | Contagem prévia; PD-05; migração aborta em ambiguidade | Migration reversa |
| R05 | Formulário de atribuições confuso | Média | Médio | Simplificação automática para 1 empresa e 1 unidade; texto fixo sobre disponibilidade; resumo de acesso | Ajuste de interface |
| R06 | Performance da auditoria com o filtro de escopo, inclusive a contagem | Média | Médio | `EXPLAIN (ANALYZE, BUFFERS)` com 200 mil registros e distribuições diferentes; índices só com ganho medido; alternativa de contagem com aprovação | Remover índice ou ajustar a condição |
| R07 | Prefixos de URL conflitando com rotas atuais | Baixa | Baixo | Prefixos exclusivos no singular (`empresa`, `estabelecimento`), distintos dos cadastros no plural; palavras reservadas; testes de rota | Ajuste de rota |
| R08 | Divergência entre a convenção e o código | Média | Médio | Cada etapa atualiza a convenção (critério de aceite) | |
| R09 | Remoção das colunas de `users` com sessões abertas (E09) | Média | Médio | Novo login controlado na implantação | B (23.1) |
| R10 | Testes de concorrência e Octane fora do SQLite | Média | Baixo | Ambiente isolado com PostgreSQL e Octane real | |
| R11 | Regra de alvo mal classificada para alguma permissão (8.2.1) | Média | Alto | Sincronização falha sem alvo declarado; matriz de testes 36 a 42; revisão da classificação na E05 | Corrigir a classificação (sem migration) |
| R12 | Etiquetas derivadas mudando a experiência de quem já usa Clientes e Fornecedores | Média | Médio | Tenants com uma empresa não percebem mudança; ajuda revisada | R até o ponto de não retorno da E07 |
| R13 | Consulta contextual mínima não ficar pronta na E05 | Baixa | Baixo | Concessão local de auditoria bloqueada até a entrega; nenhuma atribuição sem tela (11.1.1) | |
| R16 | Regras por classe de dado em Pessoas confundirem quem edita | Média | Baixo | Campos somente leitura com explicação; auditoria das alterações; ajuda revisada | Ajuste de interface |
| R14 | Ponto de não retorno da E07 e da E09 atingido antes da validação completa | Baixa | Alto | Liberação por chave e em duas implantações; janela de reversão registrada | B |
| R15 | Contagem da auditoria cara em tenants grandes | Média | Médio | Medição na E08; alternativas aprovadas | |

---

## 25. Decisões de produto

Revisão 3: decisões PD-01 a PD-06 **aprovadas** pelo responsável do produto em 19/11/2026. As incompatibilidades técnicas abaixo viram tarefas das etapas indicadas.

| Código | Decisão | Incompatibilidades técnicas e tratamento | Estado | Etapa |
| --- | --- | --- | --- | --- |
| PD-01 | Campos fiscais próprios de empresa e estabelecimento, com componentes de interface e validação (`TaxDocument`) reutilizáveis | O AGENTS.md diz que CPF/CNPJ pertence a Pessoas: emenda na E02. Os dados de `clients` passam a significar contratante (6.7) | **Aprovada** | E02, E04 |
| PD-02 | Auditoria global da plataforma restrita aos eventos de plataforma e administrativos das contas, preservando a inspeção autorizada | `AuditAndGuidanceTest` (linhas 95 e 96) espera hoje a visão global: o teste será reescrito. A ajuda `auditoria.md` será revisada. A lista exata de eventos administrativos visíveis (provisionamento, situação, plano, inspeções) será fechada na E08 | **Aprovada** | E08 |
| PD-03 | Mesmo CNPJ permitido em empresas de tenants distintos (unicidade por tenant) | Sem conflito com `clients.document`, avaliado em separado (PD-07) | **Aprovada** | E02, E04 |
| PD-04 | Prefixo de caminho para o tenant, inicialmente | As URLs `/conta/...` continuam sem prefixo enquanto houver um vínculo só. Com vários vínculos, o prefixo é obrigatório e as URLs antigas redirecionam para o tenant escolhido. Cookie de sessão único por domínio, compatível porque o contexto vem da URL | **Aprovada** | E09 |
| PD-05 | Remoção controlada de Pontos, condicionada à verificação de dados e dependências | Rotas de Pontos são a escrita de prova em `AccountInspectionTest` (61, 80 e 84) e `SaasPlatformTest` (98 a 116): substituir por rota de estabelecimento. O cargo inicial `member` precisa de permissão substituta (8.4). Ajuda e tours de Minha conta e Contas. Contagem em produção antes da E04 | **Aprovada** | E04 |
| PD-06 | Resumo de Acesso no lugar de uma tela de Permissões | Nenhuma | **Aprovada** | E02, E05 |
| PD-07 | Unicidade global de `clients.document` (avaliação separada pedida na aprovação da PD-03) | Recomendação: **manter** como está (6.7). Não exige alteração de código nem de banco; a E04 deixa de tocar nessa coluna. Uma mudança futura exigiria uma entidade de contratante explícita | **Recomendação: manter o estado atual.** Confirmação desejável, mas não bloqueia nenhuma etapa, porque não há mudança | |

**Regras do plano também aprovadas** (revisão 3):
- cargos pertencem ao tenant e só são criados no escopo tenant (8.2.1);
- Pessoas é o cadastro central do tenant, com edição por classe de dado (10.2.1);
- empresas e estabelecimentos são inativados, sem exclusão física, na v1 (6.6).

---

## 26. Quadro de acompanhamento

Estados: Pendente, Em execução, Bloqueada, Em validação, Concluída.

| Etapa | Estado | Início | Conclusão | Evidências (testes, medições, telas) | Observações |
| --- | --- | --- | --- | --- | --- |
| E01 | Concluída | 19/11/2026 | 19/11/2026 | Seção 03, linha de base 15.2, revisões 2 a 4 | Arquitetura e PD-01 a PD-06 aprovadas; refinamentos DA-24 e DA-25 registrados para a E02 |
| E02 | Em validação | 20/11/2026 | | Convenções publicadas, `AGENTS.md` emendado, conferência de coerência e de texto (registro abaixo) | Aguarda revisão do responsável do produto; DA-26 a DA-30 em validação |
| E03 | Pendente | | | | |
| E04 | Pendente | | | | |
| E05 | Pendente | | | | |
| E06 | Pendente | | | | |
| E07 | Pendente | | | | |
| E08 | Pendente | | | | |
| E09 | Pendente | | | | |
| E10 | Pendente | | | | |

### Registro de execução

#### E01, revisão 2 (19/11/2026)

- **Escopo:** revisão do plano, sem alterar código, migrations, banco ou interfaces.
- **Verificações adicionais no código:**
  - `AuditAndGuidanceTest` cobre a visão global da plataforma (afeta PD-02);
  - rotas de Pontos são usadas como escrita de prova nos testes de inspeção (afeta PD-05);
  - `deferrable` só existe em `PostgresGrammar` no Laravel 13 (afeta 6.6).
- **Correções:**
  - modelo de autorização por recurso-alvo (8.2 e 8.2.1);
  - tipos de administrador (8.3);
  - antiescalada e autoridade sobre vínculos (8.5, DA-19);
  - política de pessoas e etiquetas derivadas (10.2, DA-20);
  - contexto de auditoria desde a E04 e regra de transição (11.1, 11.1.1);
  - invariantes e camadas de garantia (5.3, 6.6, DA-21);
  - unicidade fiscal por papel (6.7, DA-22);
  - resolvedor em duas queries com orçamento de linhas (9.2);
  - métricas de performance, PostgreSQL e worker real (14.3, 15.1, 15.3);
  - classes de reversibilidade (23.1, DA-23);
  - etapas E03 a E09 ajustadas;
  - testes 36 a 49.
- **Ordem das etapas:** inalterada.

#### E01, revisão 3 (19/11/2026)

- **Escopo:** ajustes finais pedidos na aprovação da revisão 2, sem alterar código, migrations, banco ou interfaces.
- **Verificação adicional no código:** `SavePersonAction` propaga nome e e-mail da pessoa para a identidade e responde "Já existe um usuário com este e-mail" consultando todos os tenants (A20).
- **Ajuste 1, administração de usuários:**
  - três camadas (identidade global, vínculo com o tenant e atribuições locais) na 8.5 e na DA-19;
  - administradores de empresa e unidade gerenciam atribuições locais de pessoas que atuam também em outras empresas, sem ver nem alterar as de fora;
  - classificação de `account.users.*` revista na 8.2.1; tipos de administrador na 8.3.
- **Ajuste 2, Pessoas:** classes de dado na 10.2.1 (contato e endereço; identificação; dados comerciais por empresa; etiquetas comerciais; acesso), com regra de edição própria para cada uma. A DA-20 foi revista.
- **Ajuste 3, auditoria:**
  - a regra de transição que deixava auditores locais sem tela foi removida;
  - a E05 entrega a consulta contextual mínima, e a concessão local de auditoria fica bloqueada sem ela (11.1.1);
  - E05 e E08 foram ajustadas.
- **Decisões:**
  - PD-01 a PD-06 aprovadas;
  - PD-07 (unicidade global de `clients.document`) avaliada separadamente, com recomendação de manter, sem alteração na E04;
  - cargos do tenant, Pessoas como cadastro central e inativação sem exclusão física aprovados.
- **Testes:** 38, 39, 41, 42 e 49 revistos; 50 a 53 novos.
- **Ordem das etapas:** inalterada.

#### E01, revisão 4 e fechamento (19/11/2026)

- **Aprovação:** arquitetura multiempresa e multiestabelecimento, PD-01 a PD-06, planejamento incremental e requisitos de performance, segurança e padronização visual.
- **Refinamentos obrigatórios para a E02:**
  - DA-24: identidade global sem alteração direta por administradores de tenant, inclusive com vínculo único. A exceção da revisão 3 foi removida nas seções 7.2, 8.5, 18.5 e no teste 53;
  - DA-25: contatos centrais separados dos contatos da relação comercial, com a política campo a campo fechada na E02 (10.2.1).
- **Estado:** E01 concluída. E02 a E10 pendentes. Nenhuma alteração de código, banco, migrations, interfaces ou serviços; sem commit.

#### E02, revisão 5 (20/11/2026)

- **Início:** 20/11/2026, após autorização explícita. Somente documentação. Nenhuma alteração de código, banco, migrations, interfaces, serviços ou infraestrutura; sem commit.
- **Leitura prévia:** este plano, o `AGENTS.md`, o `README.md` e as convenções existentes. Leitura de código só para classificar campos e localizar incompatibilidades: migrations de `people`, `customers` e `suppliers`, `Person::auditedFields`, `User::applyIdentity`, `UpdateProfileAction`, `UpdateProfileData`, `SavePersonAction`, `CreateUserAction`, `SendUserInvitationAction`, `config/fortify.php`, `config/permissions.php`, `routes/*.php` e `accountNavigation.ts`.
- **Documentos criados:**
  - `docs/convencoes/contexto-organizacional.md`: hierarquia, conceitos, invariantes, propriedade, operações sem estabelecimento, empresa pequena, contexto da requisição e nomenclatura;
  - `docs/convencoes/identidade-e-vinculos.md`: DA-24 com camadas, fluxos, vários tenants, respostas neutras e permissões;
  - `docs/convencoes/pessoas-e-contatos.md`: DA-25 com a classificação campo a campo e os textos do formulário;
  - `docs/convencoes/performance.md`: contrato de Octane e FrankenPHP, autorização, consultas, índices e medição;
  - `docs/convencoes/checklist-modulos.md`: ficha de 10 perguntas e verificações obrigatórias.
- **Documentos atualizados:**
  - `docs/convencoes/permissoes.md`: modelo atual preservado e modelo contextual (catálogo, cargos, disponibilidade, atribuições, escopos, recurso-alvo, herança, administradores, antiescalada, revogação, atribuições locais, nomes fechados);
  - `docs/convencoes/auditoria.md`: contexto organizacional, níveis de evento, várias unidades, leitura, consolidado, autoria, plataforma e inspeções; "dados do estabelecimento" passou a "dados da conta";
  - `AGENTS.md`: tabela de convenções, exceção fiscal (PD-01), dados centrais e da relação, seções Organização e isolamento e Performance, recurso-alvo, administradores e contexto da auditoria;
  - este plano: seções 04, 6.2, 6.3, 7.2, 8.2.1, 10.2.1, 12.1, 12.2, E02, E04, R07, 26, 27 e 28.
- **Decisões formalizadas:** DA-26 a DA-30 (seção 04), em validação.
- **Conferências:**
  - DA-01 a DA-25 e PD-01 a PD-06 comparadas, uma a uma, com as convenções e o `AGENTS.md`; nenhuma contradição encontrada;
  - termos antigos de prefixo (`/conta/emp`, `/conta/e/`) e "a confirmar na E02" eliminados do plano;
  - busca de travessão e meia-risca nos arquivos alterados: nenhuma ocorrência;
  - links relativos entre as convenções, o plano e o `AGENTS.md` conferidos;
  - `git status`: só arquivos de documentação alterados.
- **Incompatibilidades encontradas:**
  - **I1.** `UpdateProfileAction` troca o e-mail de acesso imediatamente e só zera `email_verified_at`. Não há confirmação prévia no novo endereço, aviso ao anterior nem encerramento de sessões (DA-24 e DA-29);
  - **I2.** A recuperação de senha usa a resposta padrão do Fortify, que informa quando o e-mail não existe, e `UpdateProfileData` responde "Já existe um usuário com este e-mail". As duas revelam a existência de identidades (DA-29);
  - **I3.** `README.md` e `docs/convencoes/ajuda.md` descrevem a conta como estabelecimento e citam Pontos. É o comportamento atual; a correção acompanha a E04, fora da autorização desta etapa;
  - **I4.** O escopo da E02 previa uma única convenção. A divisão por assunto (DA-26) é um desvio do plano, mantido para que o `AGENTS.md` aponte cada tarefa ao contrato certo;
  - **I5.** `contact_name` (pessoa de contato) é central hoje, mas costuma variar por empresa. Mantido central (DA-30); um campo equivalente na relação fica para avaliação na E07.
- **Pendências para o responsável do produto:**
  - aprovar DA-26 a DA-30, em especial os prefixos no singular por extenso, que substituem `emp` e `e`;
  - decidir a etapa que corrige I1 e I2. Recomendação: incluir na E05, junto com a separação das camadas;
  - confirmar o código imutável na v1 e o encerramento das demais sessões na troca de e-mail;
  - confirmar que a distinção "Dados centrais" e "Dados desta empresa" só aparece com mais de uma empresa ativa;
  - definir na E09 a coluna do código da conta para `/contas/{conta}`.
- **Critérios de aceite da etapa:** os 10 critérios da autorização foram conferidos e estão atendidos, com as pendências acima. A E02 fica em validação até a revisão.

Cada etapa acrescenta aqui um bloco com:

- data;
- commits;
- testes executados e resultados;
- medições antes e depois;
- telas e larguras verificadas;
- desvios do plano e decisões tomadas;
- pendências.

---

## 27. Contrato para módulos futuros

### 27.1 Checklist oficial

Desde a revisão 5, a ficha de 10 perguntas e as verificações obrigatórias estão em `docs/convencoes/checklist-modulos.md`, que é a fonte normativa e o critério de aceite de módulos novos. Esta seção não repete a lista, para evitar divergência.

### 27.2 Itens obrigatórios de implementação

Ver o checklist oficial: propriedade e escopo, permissões e autorização, compartilhamento, integridade, auditoria, consultas contextualizadas, segurança, performance, componentes e testes.

### 27.3 Aplicação aos módulos previstos

| Módulo | Proprietário | Alvo típico | Observação |
| --- | --- | --- | --- |
| CRM, leads, oportunidades | Empresa | `company` | Pessoa do tenant como contato |
| Produtos e serviços | Empresa | `company` (cadastro), `establishment` (preço local) | Habilitação por unidade |
| Estoque e depósitos | Estabelecimento | alvo `establishment` | Depósito pertence à unidade |
| Compras | Empresa (documento), unidade (recebimento) | `company` e `establishment` | |
| Vendas e PDV | Estabelecimento (operação), empresa (responsável) | `establishment` | Ponto de venda é entidade do módulo |
| Financeiro | Empresa | `company`; caixa em `establishment` | Entre empresas é operação comercial (10.5) |
| Ordens de serviço | Estabelecimento | `establishment` | |
| Relatórios | Conforme a fonte | Conforme a fonte | Sempre pelo descritor autorizado |
| Auditoria | Conforme o evento | `establishment`, `company` ou `account` | Seção 11 |
| Portal do cliente | Empresa | identidade externa (futuro) | Fora do RBAC interno; contrato próprio |
| White label | Tenant | `account` | Relacionado à PD-04 |

---

## 28. Registro de decisões

| Código | Decisão | Estado | Data | Observações |
| --- | --- | --- | --- | --- |
| DA-01 a DA-18 | Seção 04 | Aprovada (revisão 2) | 19/11/2026 | DA-07 e DA-12 com a redação da revisão 2 |
| DA-19 | Administração de usuários em três camadas | Aprovada com ajuste (revisão 3) | 19/11/2026 | Seção 8.5 |
| DA-20 | Pessoas com edição por classe de dado | Aprovada com ajuste (revisão 3) | 19/11/2026 | Seção 10.2.1 |
| DA-21 a DA-23 | Seção 04 | Aprovada (revisão 2) | 19/11/2026 | |
| PD-01 a PD-06 | Seção 25 | Aprovada | 19/11/2026 | Incompatibilidades viram tarefas das etapas |
| PD-07 | Manter a unicidade global de `clients.document` | Recomendação (estado atual, sem mudança) | 19/11/2026 | Não bloqueia etapas |
| Regras do plano | Cargos do tenant; Pessoas como cadastro central; inativação sem exclusão física | Aprovada | 19/11/2026 | Seção 25 |
| DA-24 | Identidade global sem alteração direta pelo tenant; fluxos seguros de e-mail e recuperação | Aprovada; detalhamento na E02 | 19/11/2026 | Revisão 4 |
| DA-25 | Contatos centrais separados dos contatos da relação comercial | Aprovada; política campo a campo na E02 | 19/11/2026 | Revisão 4 |
| E01 | Fechamento da etapa | Concluída | 19/11/2026 | |
| DA-26 | Convenções separadas por assunto | Em validação | 20/11/2026 | Revisão 5; desvio do escopo previsto (uma convenção) |
| DA-27 | Namespaces, serviços de contexto, models e nomes de permissão | Em validação | 20/11/2026 | Revisão 5 |
| DA-28 | Prefixos de URL, identificadores e terminologia | Em validação | 20/11/2026 | Revisão 5; substitui `emp` e `e` da 12.2 |
| DA-29 | Fluxos seguros de identidade e respostas neutras | Em validação | 20/11/2026 | Revisão 5; incompatibilidades I1 e I2 |
| DA-30 | Classificação campo a campo de Pessoas e textos do formulário | Em validação | 20/11/2026 | Revisão 5; I5 |
| E02 | Execução da etapa | Em validação | 20/11/2026 | Aguarda revisão |

Decisões novas surgidas durante a execução devem ser registradas aqui antes de alterar o contrato, com a justificativa e o impacto nas etapas.

# 001: Arquitetura organizacional do Prospera

| Campo | Valor |
| --- | --- |
| Documento | Plano oficial de execução da arquitetura multi-tenant, multiempresa e multiestabelecimento |
| Situação do documento | Aprovado. E01 a E09 concluídas. E06 homologada conforme autorização da E07. E07 homologada em 21/11/2026. E08 homologada formalmente em 23/11/2026, com referência em `7cd9b9d` e complementação validada sobre `845218a` (seção 26). E09 homologada formalmente em 23/11/2026 sobre o HEAD `48e2191` com a árvore de trabalho validada (seção 26). E10 pendente. Revisão complementar dos documentos fiscais e de matriz e filial em 23/11/2026, encerrada no mesmo dia com a suíte estabilizada (seção 26), sem reabrir etapas homologadas |
| Base de código auditada | Branch `feat/filtros-calendario-titulos`, commit `76c698c` |
| Data da auditoria complementar | 19/11/2026 |
| Homologação técnica da E08 | 23/11/2026: Concluída; evidências, limitações aceitas e condições de implantação na seção 26 |
| Homologação técnica da E09 | 23/11/2026: Concluída; evidências, limitações aceitas e condições de implantação na seção 26 e no relatório da E09 |
| Revisão | 6 (20/11/2026): encerramento da E02. Revisão 5 (20/11/2026): execução da E02. Revisão 4 (19/11/2026): fechamento da E01 com dois refinamentos obrigatórios para a E02 (identidade global sem alteração direta pelo tenant; contatos centrais separados dos contatos da relação comercial). As revisões 2 e 3 seguem válidas. Detalhes no registro da seção 26 |
| Revisões 5 e 6 | Revisão 6: ajustes da validação (identidade, contatos, segurança de e-mail na E05, URLs) e aprovação de DA-26 a DA-30. Revisão 5: execução da E02 (contrato organizacional). Convenções publicadas em `docs/convencoes/`, `AGENTS.md` emendado e nomenclaturas fechadas (DA-26 a DA-30). Seções 6.2, 6.3, 7.2, 8.2.1, 10.2.1, 12.1, 12.2, E04, R07, 26, 27 e 28 ajustadas. Detalhes no registro da seção 26 |
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
| A11 | Trilha paralela | [C] (atualização de 20/11/2026: removida no commit `a661b8d`; a tabela continua no banco sem novas gravações) `DirectoryAudit` gravava em `directory_audit_events` em 5 pontos (`AssignRoleAction`, `SaveRoleAction`, `CreateUserAction` e outros), duplicando eventos já registrados por `AuditsChanges` | `grep DirectoryAudit::record` |
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
| DA-06 | Identidade global (`users`) e vínculo por tenant (`account_memberships`) com pessoa própria em cada tenant. A E05 remove `users.role_id`, `users.person_id` e `users.is_account_admin`. A E09 substitui `users.account_id` pela seleção do vínculo | [DP] + [R] |
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
| DA-26 | O contrato organizacional é publicado em convenções separadas por assunto (`contexto-organizacional.md`, `identidade-e-vinculos.md`, `pessoas-e-contatos.md`, `performance.md`, `checklist-modulos.md`), além de `permissoes.md` e `auditoria.md` atualizadas, e referenciado no `AGENTS.md` (revisão 5) | [R] Aprovada |
| DA-27 | Nomes de código: domínio `App\Domains\Organizacao`; `App\Support\TaxDocument`; serviços `scoped` em `App\Domains\Acessos\Application\Context` (`AccountContext`, `EffectivePermissions`, `OrganizationContext`); middleware `ResolveOrganizationContext`; models `Company`, `Establishment`, `AccountMembership`, `RoleAssignment`, `RoleAvailability`; permissões `account.account-data.update`, `account.establishments.promote`, `account.users.reactivate`, `account.users.send-password-reset` e `account.users.invite` (revisões 5 e 6) | [R] Aprovada |
| DA-28 | URLs e identificadores: operação em `/contas/{conta}/empresa/{empresa}` e `/contas/{conta}/estabelecimento/{estabelecimento}`, consolidado pelo código reservado `todos`, tenant em `/contas/{conta}` (E09); administração com ID numérico; código com letras minúsculas, números e hífen, de 2 a 40 caracteres, único por tenant, imutável na v1, com palavras reservadas; terminologia de menus da convenção; o identificador técnico do tenant é definido na E09 (revisões 5 e 6) | [R] Aprovada |
| DA-29 | Fluxos de identidade: troca de e-mail só depois da confirmação no novo endereço, com aviso ao anterior e encerramento das demais sessões; respostas neutras também na recuperação de senha e na troca do próprio e-mail; disparo do link de redefinição e reenvio de convite só por escopo tenant; limitação de tentativas; correções I1 e I2 na E05, com I2 como condição para disponibilizar publicamente os fluxos afetados (revisões 5 e 6) | [R] Aprovada |
| DA-30 | Pessoas: classificação dos campos atuais (identificação central, contatos centrais, endereço, dados comerciais por empresa, contatos da relação, dados operacionais restritos), `contact_name` mantido central, seções "Dados centrais" e "Dados desta empresa" só com mais de uma empresa ativa, aviso permanente de cadastro compartilhado ao editar contatos e endereço centrais e aviso adicional para pessoa com várias empresas; "Pessoa de contato" por empresa avaliada na E07 (revisões 5 e 6) | [R] Aprovada |

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
   - Se houver CNPJ completo, a classificação Matriz ou Filial é informada pelo operador, inclusive no provisionamento automático da conta; nada é deduzido da ordem do CNPJ. A unidade principal pode ser uma filial, e a empresa pode existir sem matriz cadastrada (revisão complementar de 23/11/2026, seção 26).
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
- A E05 remove `users.person_id`, `users.role_id` e `users.is_account_admin`. `users.account_id` é a referência atual de navegação e atribuição do contexto de auditoria antes da E09. A autorização exige o vínculo ativo correspondente.

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
| `account.users.update` (dados de acesso do vínculo), `account.users.revoke` (suspender o vínculo), `account.users.reactivate` (reativar o vínculo, sem reativar a identidade), `account.users.send-password-reset` (disparar o link de redefinição) e `account.users.invite` (reenviar convite) (nomes fechados na E02) | tenant | Tenant. O vínculo é do tenant inteiro; administradores de empresa e de unidade retiram o acesso local removendo as atribuições que gerenciam (`assign-role`) | |
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
   | Identidade global | Foto, e-mail de acesso, senha, 2FA, chaves de acesso, nome da identidade, `access_enabled` | Somente a própria pessoa (`self.*`), pelos fluxos seguros, e a plataforma (suporte, auditado). **Nenhum administrador de tenant, empresa ou unidade altera a identidade, nem com vínculo único** (revisão 4, DA-24). O administrador do tenant pode apenas disparar o envio do link de redefinição de senha ou reenviar o convite para o e-mail da própria identidade |
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
| Cargo e abrangência geral | nulo (tenant) | nulo | E05 |
| Inclusão ou remoção de disponibilidade restrita | empresa do nó | unidade do nó, quando houver | E05, consolidada na E08-D4 |
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
- Eventos de cadastros compartilhados (pessoas, etiquetas) e administrativos da conta são de nó tenant e só aparecem com T. Restrições de disponibilidade e atribuições seguem o nó descrito na 11.1.
- O detalhe (`account.audit.view`) e os dados técnicos (`account.audit.technical`) seguem a mesma regra sobre o nó do evento, avaliada para cada uma dessas permissões.

### 11.3 O que usa a mesma condição

- Listagem, contagem e paginação.
- Opções de filtro (autores, tipos, ações, empresas, estabelecimentos), já montadas hoje a partir da mesma consulta com escopo.
- Detalhe (`findOrFail` sobre o escopo).
- Exportação futura.
- A visão consolidada é a própria condição.
- O filtro de unidade só restringe dentro do conjunto autorizado.

### 11.4 Consolidação da trilha legada

As chamadas de `DirectoryAudit::record` foram substituídas em `a661b8d`. A E08 centralizou também a sincronização do catálogo. O código de execução passou a usar exclusivamente `audit_records`.

A migration `2026_11_21_000003` importa registros sem chave legada e sem correspondência temporal no histórico central, preservando somente campos permitidos. A correspondência usa conta, tipo e registro, ou sincronização do catálogo, em uma janela de cinco segundos. É uma conciliação heurística. A ausência de duplicatas foi demonstrada para as amostras do harness, e não prova equivalência de todos os dados de origem. Valores descartados por privacidade, nomes históricos indisponíveis e metadados sem mapeamento não são reconstruídos.

A migration `2026_11_21_000006` remove as duas tabelas antigas do esquema ativo, conforme autorização para o ambiente em desenvolvimento. A implantação em banco com dados a preservar exige cópia verificada e exportação das tabelas antes da remoção. O rollback recria o esquema vazio e a restauração dos dados depende dessa exportação.

### 11.5 Auditoria da plataforma (A12)

Recomendação: `/saas/auditoria` mostra os eventos de contexto `platform` e os eventos administrativos das contas (provisionamento, situação, inspeções). Os eventos de negócio do tenant passam a ser visíveis apenas durante uma inspeção, que já é registrada (PD-02).

**Lista fechada na E08.** A plataforma vê eventos com `account_id` nulo (catálogo, identidade dos administradores SaaS), eventos do tipo Conta (cadastro, situação, plano, dados da conta, inspeção iniciada e encerrada) e eventos de origem `platform` (provisionamento feito pela plataforma). As escritas feitas durante uma inspeção são eventos de negócio da conta e não aparecem em `/saas/auditoria`.

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
- **Tenant na URL:** obrigatório para qualquer quantidade de vínculos (E09, revisão da PD-04 de 23/11/2026). Prefixo de caminho aprovado (PD-04): `/contas/{conta}/...` no lugar de `/conta/...` (E02, DA-28).

### 12.3 Várias abas

- **Frontend:** `contextualUrl` é o único resolvedor de `~/`. `navigate` o aplica com a página atual da aba, por isso `ApiLink`, `ApiForm`, `useApiForm` e os filtros de listagem aceitam `~/`. `apiRequest` não conhece a página e recebe endereços já resolvidos por `useContextUrl`. Nenhum estado global de contexto é guardado no frontend.
- **Escrita:** toda escrita vai para uma URL que contém o contexto, e o servidor valida que o recurso alterado pertence a ele (`where account_id = ? and establishment_id = ?`).
- **Seleção temporária:** acompanha a navegação da aba pelo armazenamento da sessão do navegador. A URL explícita determina o escopo operacional.

Resultado: uma operação da aba 1 nunca usa o contexto da aba 2 (cenário 24).

### 12.4 Seletor organizacional

- A área de trabalho na barra superior lista as unidades ativas e navegáveis, com caixas de seleção agrupadas por empresa. É possível marcar um subconjunto, uma empresa inteira ou todas as unidades. Fechar a lista aplica a seleção nesta aba e a salva como padrão, exceto em inspeção. Uma seleção vazia mantém a seleção anterior. O seletor fica à esquerda do tutorial. A seleção acompanha a navegação nas telas da conta. Com uma unidade autorizada, o seletor fica oculto.
- O padrão pertence ao vínculo usuário e conta. O acesso sem código usa a seleção enviada pela aba (`?unidades=`), revalidada, ou o padrão revalidado e, se ele estiver vazio, a primeira unidade disponível. Inspeções não editam o padrão.
- Nas telas sem área de trabalho, o seletor oferece **Abrir área de trabalho**, que aplica a seleção da aba e passa pela entrada sem código. As telas administrativas mantêm o próprio escopo.
- `?para=modulo` só aceita uma rota GET registrada no grupo de unidade com o middleware de contexto; o resto responde 404. Com uma unidade, abre o módulo nela. Com várias, preserva a seleção se o módulo declara `consolidated` e, caso contrário, mostra a escolha do estabelecimento na área de trabalho, sem escolher pelo usuário.
- Uma unidade usa uma URL com seu código. Várias unidades usam `/conta/estabelecimento/todos?unidades=codigo-a,codigo-b` nas telas com suporte. O código reservado aceita somente leitura. Uma seleção explícita contendo unidade indisponível responde 404.
- Consultas compatíveis aplicam no SQL a interseção do tenant, da seleção e da permissão específica da operação. Escritas usam uma URL com destino inequívoco. O contexto de empresa e a administração da conta mantêm seus próprios escopos.

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
| `users(role_id)` | Contagem do último administrador em `AssignRoleAction`; FK. Removido com a coluna na E05 |
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
Organização                  (/conta/organizacao, conta, empresas e estabelecimentos na mesma árvore)
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

**Navegação.** Organização exibe a conta contratante acima das empresas. A seta abre os estabelecimentos da empresa. O botão de adição fica no nível que recebe o cadastro, e o lápis abre o formulário existente. Ao salvar ou cancelar, o usuário volta ao ramo correspondente. As URLs de listagem e edição continuam disponíveis.

- **Listagem:**
  - colunas: razão social, nome fantasia, CNPJ ou raiz formatados (ou "Não informado"), quantidade de estabelecimentos (`withCount`), situação e ações;
  - filtros: busca e situação.
- **Cadastro e edição** (`BusinessForm`):
  - Identificação: razão social, nome fantasia, tipo, documento, código;
  - Dados fiscais: regime, CNAE principal;
  - Unidade principal: nome, endereço, CNPJ completo, matriz. Aparece só no cadastro e provisiona a unidade (6.5);
  - Situação.
- **Visualização:** modal com dados e a lista de estabelecimentos.
- **Com uma empresa:** Organização mostra a empresa com seus estabelecimentos abertos. A listagem direta de Empresas também exibe o registro.

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


#### Registro E03, 20/11/2026

**Estado:** Concluída em 20/11/2026, após homologação. A execução partiu de `c9719a1` com working tree limpo. Os commits imediatamente anteriores continham o encerramento da E02 e a padronização do backend. As seis subfrentes cobriram diagnóstico, conta de execução, RBAC, índices, medições e regressão.

**Diagnóstico e escopo.** A leitura da conta do operador foi classificada separadamente da propriedade do recurso, da associação de um usuário cadastrado e do vínculo reportado em eventos de identidade. Os acessos de propriedade em `User`, `Person`, `Supplier`, `Customer` e `AuditRecord`, a associação em `CreateUserAction` e o vínculo do usuário-alvo travado por `AssignRoleAction` permaneceram explícitos. O catálogo e o provisionamento de cargos continuam nas estruturas atuais. A sidebar usa o JSON de `ApiPage`, que já delegava a `User::permissionSlugs()`. A Central de Ajuda usa o mesmo Gate e a mesma lista. A autorização HTTP, o modo de inspeção e a autoria auditável mantêm seus pontos de controle.

**Arquivos e decisões.**

- `AccountContext.php`, `EffectivePermissions.php` e `EffectivePermissionSlugsQuery.php` centralizam a conta ativa e o conjunto de slugs de sistema aplicável ao sujeito, ao cargo e à conta. `AppServiceProvider.php` registra ambos os contextos como `scoped`. O Gate resolve do container no momento da checagem e `User.php` delega a lista. O ciclo de vida real usa a limpeza de serviços temporários do Octane.
- `AccessController.php`, `DirectoryController.php`, `UserController.php`, `ProfilePhotoController.php` e `ClientController.php` obtêm o contexto central antes de consultar ou executar Actions. `AccountDirectoryQuery.php` busca a conta ativa pelo ID resolvido. `AuditController.php` entrega o ID explícito a `AuditQuery.php`.
- `AssignRoleAction.php` valida a conta do operador pelo contexto. `SaveRoleAction.php` invalida o conjunto de slugs apenas quando muda o pivô, preservando as checagens posteriores à escrita. `EnforceAccountInspection.php` compara o vínculo com os dados da inspeção. `EnforceAudit.php` e `AppServiceProvider.php` usam o vínculo contextual para o escopo dos eventos de credenciais e sessão. `AuditContext` continua `scoped` e mantém autor e representado em campos distintos.
- `2026_11_20_000001_add_reverse_lookup_indexes.php` acrescenta `permission_role(permission_id)`, `person_tag(tag_id)` e `users(role_id)`. Os ganhos observados foram nas buscas reversas de FKs e na contagem por etiqueta. A migration é reversível (R), com rollback e reaplicação verificados no PostgreSQL isolado.
- `tests/TestCase.php`, `AccountContextTest.php` e `AccountContextArchitectureTest.php` cobrem a fronteira de requisição, perfis, escopo e revogação. A regra de arquitetura usa AST e reconhece leituras diretas do guard e seus aliases locais, preservando relacionamentos de domínio. O harness em `tests/Performance` cobre as nove rotas da linha de base, volume de slugs, índices e inspeção no worker.
- `docs/convencoes/permissoes.md` registra a resolução atual e o relatório [001-e03-medicoes.md](001-e03-medicoes.md) contém comandos, tabelas de resultados e limitações.

**Resultados.** A suíte SQLite em Docker passou com 114 testes e 1.020 asserções. A referência passou com 110 testes e 990 asserções no mesmo runtime. O harness SQLite passou com 1 teste e 45 asserções. `audit:check` confirmou os 8 modelos cobertos. `permissions:sync` descobriu 68 slugs, com zero inserções, atualizações ou exclusões. O PHPStan passou. O Pint passou nos arquivos PHP da E03. A execução global apontou o mesmo desvio de estilo preexistente em `bootstrap/app.php`.

**Medições e aceite.** A Central de Ajuda caiu de 26 para 2 consultas no SQLite sem sessão de banco e de 29 para 5 no PostgreSQL via HTTP com sessão. Do total, 13 leituras de slugs caíram para 1 e 24 duplicatas caíram para zero nos dois ambientes. O orçamento de autorização foi atingido nas rotas de leitura com uma consulta de conta ativa e uma de slugs. No fluxo que altera o próprio cargo, a checagem do destino após a escrita pode requerer uma terceira consulta, restrita à releitura dos slugs. O mesmo worker real alternou contas, preservou isolamento após resposta 403, aplicou revogação com sessão ativa e preservou o autor SaaS e o representado na inspeção. As suítes também cobriram a separação entre plataforma e conta, identificação de recursos de outro tenant e antiescalada.

**Limitações e riscos remanescentes.** A medição foi feita com dados sintéticos e tráfego sequencial. O catálogo observado tem 68 slugs, dos quais 56 aplicáveis ao administrador da conta. A verificação de arquitetura detecta cadeias diretas e aliases simples, com revisão necessária para novos padrões de obtenção do usuário. As interfaces e o conteúdo de ajuda mantiveram o comportamento, portanto a verificação ocorreu nas respostas HTTP.

**Retomada da validação, 20/11/2026.** O harness em `OctaneMetrics.php`, `octane-client.php` e `octane-fixtures.php` passou a verificar 300 requisições alternadas no mesmo worker após respostas 403, 404 e 422, a memória PHP no início das requisições e a autoria de uma gravação normal logo após uma gravação em inspeção. A sequência passou na referência e na E03. A suíte em Docker passou novamente com 114 testes e 1.020 asserções. O harness SQLite passou com 45 asserções, e PHPStan e Pint dos arquivos da etapa passaram. O PHP local pulou o teste de redimensionamento de foto por falta de GD, coberto pela execução completa em Docker. As novas amostras de HTTP, memória e índices constam no [relatório de medições](001-e03-medicoes.md).

**Homologação final, 20/11/2026.** O diff efetivo, incluindo arquivos ainda não rastreados, foi conferido contra `c9719a1` e corresponde ao escopo da E03. A revisão confirmou os bindings `scoped`, a limpeza do Octane, a invalidação após alteração do próprio cargo e a ausência de leituras implícitas da conta de execução fora do contexto. A suíte repetida em SQLite em memória passou com 114 testes e 1.020 asserções. O harness passou com 45 asserções, PHPStan e Pint dos arquivos da etapa passaram, `audit:check` confirmou 8 modelos e `permissions:sync` confirmou 68 slugs sem alterações. O worker real repetiu as 300 requisições com isolamento, revogação na requisição seguinte e autoria correta durante e após a inspeção. O PostgreSQL isolado confirmou os ganhos nos três planos de busca reversa e o rollback e a reaplicação da migration. A conferência de schema identificou zero índices com as colunas-alvo na primeira posição antes da E03 e um por alvo após a migration. A migration preservou integralmente um registro sintético em `directory_audit_events`. A tabela histórica e suas migrations anteriores estão fora do diff.

**Pendências para trabalho futuro, sem bloqueio da E03.** Executar testes adicionais de concorrência e medir bloqueios e custos de escrita das migrations em bases maiores.

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
- **Segurança de e-mail (I1 e I2, revisão 6):**
  - alteração do e-mail de acesso com verificação do novo endereço antes da troca (I1);
  - aviso ao endereço anterior (I1);
  - encerramento das demais sessões da identidade após a troca de e-mail e após a redefinição de senha, preservando a sessão que concluiu a operação (I1);
  - respostas neutras na recuperação de acesso, na troca do próprio e-mail, na criação de usuário e na edição de pessoa (I2);
  - limitação de tentativas nas rotas de recuperação de acesso, verificação e troca de e-mail, e proteção contra enumeração de contas (I2);
  - a correção de I2 é condição para disponibilizar publicamente os fluxos afetados.
- Reativar o vínculo não reativa a identidade global; foto, e-mail, senha e fatores de autenticação continuam exclusivos do titular.

**Escopo técnico.**
- **Banco:**
  - `account_memberships` (a partir de `users.account_id` e `person_id`);
  - `roles.availability`, `role_availability`, `role_assignments`;
  - retirada de `users.role_id`, `users.is_account_admin` e `users.person_id` após a migração dos dados;
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
  - trilha única em `audit_records` (a classe `DirectoryAudit` já foi removida no commit `a661b8d`);
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
- Enumeração de contas pela recuperação de acesso e pela troca de e-mail, com limitação de tentativas.
- Nenhuma permissão `platform` em cargos de tenant.

**Testes.**
- Cenários 6 a 16, 22 (consulta mínima), 29, 36 a 42 e 50 a 57.
- Equivalência pós-migração.
- Alteração de disponibilidade que descobre atribuições.

**Critérios de aceite.**
- Matriz de cenários verde.
- Usuários existentes com exatamente as mesmas permissões após a migração.
- 2 queries de autorização no máximo.
- I1 e I2 corrigidas, com os cenários 54 a 57 verdes.
- Ajuda e tours de Cargos, Usuários e Pessoas revisados.

**Riscos.**
- Complexidade do formulário de atribuições.
- Transição em ambientes de desenvolvimento já migrados. Aplicar a migration de retirada das colunas depois da criação e migração dos vínculos.

**Reversão** (23.1). A expansão `000007` pode ser revertida antes da contração enquanto os vínculos forem representáveis. A contração `000008` exige restauração de cópia anterior. O `down()` interrompe a reversão antes de alterar o esquema.
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
  - URLs relativas ao contexto resolvidas por `contextualUrl`, aplicado em `navigate`, `ApiLink` e `ApiForm` (`apiRequest` usa `useContextUrl`);
  - `ApiPage` passa a enviar os contextos autorizados só quando a página usa o seletor.
- **Banco:** preferência de navegação em coluna JSON opcional do vínculo `account_memberships`, com chave única existente por usuário e conta.

**Componentes envolvidos.**
- Existentes: `bootstrap/app.php`, `ApiPage`, `routes/*`, `TopBarPrimary`, `apiRequest.ts`, `ApiLink`, `ApiForm`, `useApiPage`, `SaasLayout`, `guidance.ts`.
- Novos: `OrganizationContext`, middleware, seletor.

**Dependências.** E05.

**Performance.**
- Middleware com no máximo 1 query (contexto e empresa juntos) além da resolução de permissões.
- Lista do seletor em 1 query.

**Segurança.** Manipulação de código na URL; código de outro tenant; abas simultâneas; consolidado restrito ao conjunto autorizado.

**Testes.**
- Cenários 17, 18, 19, 20, 23, 24 e 28, mais seleção múltipla, persistência isolada e revalidação após revogação.
- Navegador: duas abas em unidades diferentes executando escritas.

**Critérios de aceite.**
- Cenários verdes.
- Seletor oculto para tenant com 1 empresa e 1 unidade.
- Nenhuma operação de unidade sem contexto na URL.

**Riscos.** Rotas existentes quebrando com o novo middleware. Mitigação: ele só se aplica aos novos grupos.

**Reversão.** Remover os grupos de rota e o middleware. A reversão da migration remove somente os padrões salvos.

**Entregáveis.** Código, testes, artigo sobre troca de contexto, tours, relatório.

**Estado:** Em validação desde 20/11/2026. Execução, medições e desvios no registro da E06 na seção 26.

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

**Registro de execução.** A retomada encontrou `34ed162` em `feat/filtros-calendario-titulos`, com a E07 na árvore de trabalho. O registro local de homologação da E06 citado no checkpoint não apareceu no diff. A homologação da E06 foi confirmada na autorização desta etapa. Os registros históricos anteriores foram preservados.

| Responsabilidade | Diagnóstico na E06 | Implementação na E07 |
| --- | --- | --- |
| Pessoa | `people` era o cadastro da conta. CPF/CNPJ normalizado tinha unicidade por conta. Nome, natureza, inscrições, contato, endereço e meios adicionais eram centrais | Mantidos no cadastro compartilhado. `PersonDTO`, `SavePersonData`, `PersonDocumentExistsQuery` e `SavePersonAction` preservam o contrato. `PersonAccessQuery` distingue identificação e contatos pela cobertura das empresas com relações ativas |
| Cliente e fornecedor | `customers` e `suppliers` tinham situação própria e unicidade global por pessoa. As Actions afetavam a relação única e sincronizavam a etiqueta especial | Cada tabela possui `id`, `account_id`, `company_id`, `person_id`, `active` e timestamps. FKs compostas validam Pessoa e empresa do mesmo tenant. A unicidade `(company_id, person_id)` por tabela admite os dois papéis na mesma empresa |
| Etiquetas e filtros | Cliente e Fornecedor ficavam materializados em `person_tag`, sujeitos a aparecer em leituras do cadastro central | Catálogo especial preservado. O vínculo comercial materializado foi removido. Busca, contagem e filtros respeitam escopo SQL. As etiquetas comerciais são derivadas em lote das relações ativas em empresas visíveis |
| Identidade e situação | Identidade de login e presença na conta já pertenciam a `users` e `account_memberships`. A Pessoa central não tinha situação comercial própria | Ativar ou inativar uma relação mantém a Pessoa, as demais relações e o vínculo de acesso. FKs restritivas preservam a referência. A empresa inativa mantém as relações existentes e impede alterações comerciais |
| Escritas e leitura | A manutenção comercial fazia parte da ficha de Pessoas. O backend já usava classes Data; o domínio não dependia de Form Requests, factories comerciais ou seeders de relações | `SaveCommercialRelationsAction` aplica escolhas explícitas da ficha e `SetCommercialRelationAction` atende vínculo e situação. `CommercialRelationDirectoryQuery` e `PersonCommercialRelationsQuery` projetam registros autorizados. Os testes usam cenários sintéticos por empresa |
| Auditoria | Os Models comerciais já usavam a trilha central | O evento comercial contém conta, empresa, Pessoa, tipo, ator e mudanças efetivas. A unidade fica nula. A inspeção preserva a autoria real. Alterações centrais são eventos do tenant |

**Fluxo e navegação.** A ficha de Pessoas mostra os dois tipos de relação autorizados e mantém os componentes de dados centrais. Clientes e Fornecedores reutilizam `SaasLayout`, `BusinessList`, `BusinessListSearch`, `SelectControl`, `Modal` e `PersonPreview`. A leitura usa `/conta/estabelecimento/{estabelecimento}/{clientes|fornecedores}`, inclusive `todos?unidades=...`. A consulta elimina repetição por empresa antes de paginar. As escritas usam `/conta/empresa/{empresa}/{clientes|fornecedores}` e as rotas da relação para situação. A busca exata de documento, restrita a quem pode vincular naquela empresa, retorna nome, tipo e situação local do papel. Os slugs `list`, `view`, `create`, `activate` e `deactivate` já existiam, com alvo `company`. As políticas e a preferência da E06 são reutilizadas.

**Exemplo.** João é uma Pessoa na conta. Duas linhas de cliente podem apontar para João, uma da Gartan Roupas e outra da Gartan Express. Uma linha de fornecedor da Gartan Roupas pode coexistir com ambas. Guanambi e Janaúba compartilham a linha da Gartan Roupas. Ana pode atuar apenas nas linhas cobertas pelas permissões dela. Contato e identificação centrais seguem a autoridade de `account.people.update`.

**Migração.** `2026_11_21_000001_add_company_to_commercial_relations.php` usa a empresa única do tenant. Contas com relação legada e zero ou várias empresas, ou etiquetas comerciais incoerentes, interrompem a migração para revisão. Nenhum dado recebe empresa arbitrária. A reversão recompõe as etiquetas ativas e recusa a perda de relações repetidas por tipo. As consultas pela ficha usam o índice de `person_id`. A listagem usa a unicidade iniciada por `company_id`; o recorte amplo medido teve baixa seletividade por situação e não motivou um índice adicional.

**Validação.** A suíte PHP em contêiner descartável com SQLite em memória e e-mail em array passou com 215 testes e 2.342 asserções. PostgreSQL 18 descartável validou instalação limpa, aplicação sobre uma relação ativa anterior e reversão para o formato anterior. O PostgreSQL também recusou a reversão incompatível e preservou 60.003 relações das duas empresas do cenário. A suíte SQLite verificou bloqueios por ambiguidade e perda de dados. Com dois workers FrankenPHP, 200 requisições alternaram três sessões de duas contas, incluindo respostas 404. O harness com um worker identificado repetiu a alternância e verificou inspeção somente leitura e de edição, autoria, bloqueio de preferências, revogação na próxima requisição e continuidade da sessão independente do representado. Dois lotes de cinco escritas concorrentes produziram uma criação e quatro respostas 422 para o CPF e para o vínculo. `permissions:sync` descobriu 81 permissões. `audit:check` validou 12 modelos. `organization:check` passou. A suíte de frontend, arquitetura, tipos, build, PHPStan e Pint foram executados.

**Medições.** Listagem consolidada com duas empresas autorizadas, 50 registros por página, situação ativa e 30 amostras HTTP depois de uma requisição de aquecimento. O volume abaixo exclui os poucos cadastros dos fixtures. Os tempos são locais, em milissegundos.

| Pessoas sintéticas | Relações de cliente sintéticas | HTTP mediana | HTTP p95 | Execução SQL da consulta de página |
| ---: | ---: | ---: | ---: | ---: |
| 100 | 200 | 13,04 | 18,37 | 0,301 |
| 1.000 | 2.000 | 15,60 | 24,47 | 1,697 |
| 10.000 | 20.000 | 31,08 | 34,19 | 12,547 |
| 30.000 | 60.000 | 64,05 | 69,91 | 36,040 |

Em 30 mil, `EXPLAIN (ANALYZE, BUFFERS)` registrou varredura de clientes com 54.002 linhas após o filtro, bitmap no cadastro de Pessoas, junções hash e ordenação top-N com 28 kB, 1.078 hits de buffers e 50 linhas devolvidas. O teste de regressão compara a contagem de consultas SQL entre páginas pequenas e maiores para detectar N+1. No ensaio instrumentado com um worker e 30 mil Pessoas, a página consolidada fez 9 consultas, sem duplicações. O tempo total de banco foi de 47,58 ms na mediana e 49,82 ms no p95. O pico adicional de memória PHP foi de aproximadamente 274 KiB por requisição na mediana e no p95.

A segunda carga usou 30 mil Pessoas e 60 mil relações, distribuídas por cinco empresas e 50 unidades. As consultas consolidaram as unidades navegáveis de cada sessão. Foram 20 amostras por perfil após aquecimento, com um worker e 50 registros paginados, sem filtro de situação.

| Perfil ou atribuições | Unidades selecionadas | Empresas comerciais | Relações visíveis | HTTP mediana (ms) | HTTP p95 (ms) | Banco mediana (ms) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Administrador integral | 50 | 5 | 60.000 | 56,81 | 58,52 | 46,65 |
| 1 atribuição | 10 | 1 | 12.000 | 30,15 | 32,21 | 21,25 |
| 5 atribuições | 14 | 1 | 12.000 | 29,87 | 32,68 | 20,75 |
| 30 atribuições | 29 | 1 | 12.000 | 30,73 | 32,39 | 20,61 |

Os quatro perfis fizeram 9 consultas sem repetição de SQL e parâmetros. O pico PHP adicional das amostras registradas ficou entre 266 e 421 KiB.

A comparação de Pessoas usou o mesmo banco isolado antes e depois da migração, 30.002 pessoas, 15.001 clientes ativos, uma empresa, um worker e página de 50 registros. A versão intermediária com `withExists` elevou a mediana sem filtro de 30,96 ms para 48,78 ms. A consulta final deriva as etiquetas em uma consulta em lote com os IDs da página e mantém o filtro de listagem e a contagem no SQL.

| Consulta | Versão | HTTP mediana (ms) | HTTP p95 (ms) | Banco mediana (ms) | Queries | Pico adicional mediano PHP (KiB) |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| Pessoas | HEAD E06 | 30,96 | 34,29 | 18,29 | 13 | 447 |
| Pessoas | E07 | 29,81 | 34,51 | 17,72 | 14 | 363 |
| Pessoas com filtro Clientes | HEAD E06 | 35,39 | 38,18 | 20,52 | 13 | 613 |
| Pessoas com filtro Clientes | E07 | 31,63 | 38,07 | 19,66 | 14 | 362 |

**Validação visual e pendências.** Pessoas, Clientes, Fornecedores e o modal de vínculo foram exercitados em Chromium a 1440, 1024, 768, 390 e 320 px, sem rolagem horizontal da página. As duas larguras menores preservaram a empresa na linha compacta. Os tours dos três fluxos foram percorridos a 1440 e 390 px. O teste a 390 px incluiu CPF inválido, descoberta, vínculo, inativação e reativação. Em três lotes adicionais de 200 requisições alternadas, o RSS do processo FrankenPHP foi de 330.364, 332.912 e 334.552 KiB após cada lote, com reciclagem do worker PHP durante o ensaio. A auditoria final abaixo registra a carga prolongada e a disponibilidade de tema escuro. A E07 aguarda homologação formal.

**Arquivos.** A integração está nos domínios Pessoas, Clientes, Fornecedores, Acessos e Organização e em `routes/context.php`. No frontend, `commercial-relations.tsx`, `CommercialRelationFields.tsx`, `CommercialRelations.tsx` e os estilos comerciais foram adicionados. A ficha e o formulário de Pessoas, o catálogo de orientação e a navegação da área de trabalho foram atualizados. A cobertura adicional está em `CommercialRelationAccessTest`, `CommercialRelationIntegrityTest`, `CommercialRelationWorkspaceTest` e `BuildsCommercialScenario`, com regressões em `CustomerDirectoryTest`, `SupplierDirectoryTest` e `organizationContext.test.ts`. Ajuda e tours de Pessoas, Clientes e Fornecedores foram atualizados, assim como a convenção de Pessoas e contatos. O script `test:frontend` passou a indicar os arquivos de teste TypeScript para funcionar com Node 24.

#### Auditoria final da E07

A auditoria partiu de `34ed162` na branch `feat/filtros-calendario-titulos`, com a implementação da E07 na árvore local. O registro de homologação da E06 foi preservado. A regra de dados compartilhados da seção 10.2 e da DA-25 foi mantida.

| Dado | Consulta | Criação | Alteração | Exclusão |
| --- | --- | --- | --- | --- |
| Nome ou razão social | Ficha com `account.people.view`; nome também na lista com `account.people.list` | `account.people.create` | `account.people.update` e todas as empresas com relação ativa, ou escopo conta | O cadastro de Pessoas não oferece exclusão |
| CPF ou CNPJ | Ficha com `account.people.view`; projeção comercial com a permissão de listar na empresa | `account.people.create`; validação e unicidade por conta | Mesma regra de identificação | O documento opcional pode ser limpo com a mesma autorização de edição |
| Nome fantasia | Ficha com `account.people.view` | `account.people.create` para Pessoa jurídica | Mesma regra de identificação | Campo opcional, mesma autorização de edição |
| Inscrições fiscais | Ficha com `account.people.view` | `account.people.create` | Mesma regra de identificação | Campos opcionais, mesma autorização de edição |
| Telefones | Ficha com `account.people.view` | `account.people.create` | `account.people.update` e cobertura de uma empresa com relação ativa, ou escopo conta | Remoção de contato opcional segue a mesma autorização |
| E-mails de contato | Ficha com `account.people.view`; e-mail principal também na lista com `account.people.list` e na projeção comercial autorizada | `account.people.create` | Mesma regra de contatos | Remoção segue a regra de contatos e as validações aplicáveis |
| Endereços | Ficha com `account.people.view` | `account.people.create` | Mesma regra de contatos | Remoção segue a regra de contatos |
| Etiquetas convencionais | Lista e ficha de Pessoas com a permissão da operação | Associação inicial com `account.people.create`; catálogo com `account.tags.create` | Associação com a regra de contatos; edição do catálogo com `account.tags.update` | Desassociar segue a regra de contatos; excluir do catálogo exige `account.tags.delete` e as restrições do catálogo |
| Situação da Pessoa | `people` não tem situação global | Situações são da relação comercial, do vínculo ou da identidade | Cliente e fornecedor exigem `activate` ou `deactivate` do papel na empresa; vínculo exige `account.users.revoke` ou `account.users.reactivate` no tenant | Cada relação fica retida após inativação |
| Exclusão da Pessoa | Fora dos fluxos de Pessoas | A criação segue `account.people.create` | A edição segue as regras das categorias | Não há rota ou permissão de exclusão de Pessoas nesta versão |
| Identidade de autenticação | Titular pelos fluxos `self.*`; dados limitados de vínculo com `account.users.view` no escopo | Provisionamento com `account.users.create` e autorização da primeira atribuição | Titular pelos fluxos `self.*` e plataforma nos fluxos auditados de suporte | Titular com `self.profile.delete`, senha e proteção do último administrador; Pessoas e relações comerciais são preservadas |

Listagem e ficha central exigem atribuição ativa na conta. A abertura do formulário também depende da autorização de edição. Sem relação ativa, qualquer atribuição com `account.people.update` pode editar identificação e contatos. A cobertura para o alvo Pessoa admite a empresa ou um de seus estabelecimentos com a permissão aplicável. No teste de três empresas, a operadora de uma empresa alterou telefone, e-mail, endereço e etiqueta convencional. Os leitores das três empresas viram o mesmo cadastro atualizado. Nome, CPF, nome fantasia e inscrições foram protegidos enquanto havia relações ativas fora do escopo. As relações inativas perderam efeito sobre a cobertura, e nome e e-mail de autenticação permaneceram iguais.

**Etiquetas e descoberta.** A auditoria encontrou uma divergência entre `list` usado originalmente nos filtros comerciais de Pessoas e `view` usado pelas etiquetas. Corrigida a seleção dos filtros, opções de empresa, contagens e paginação para exigir os dois na mesma empresa. Os testes usam atribuições disjuntas e confirmam 403 sem interseção e 404 ao tentar filtrar por empresa fora do recorte autorizado. As listagens próprias de Clientes e Fornecedores preservam a permissão de listar. O pivô armazena etiquetas convencionais e Usuário, e a validação recusa a associação manual das etiquetas comerciais. Uma relação ativa remanescente mantém a etiqueta visível no escopo permitido. O módulo não possui exportações de Pessoas, Clientes ou Fornecedores. A ajuda e o roteiro de Pessoas foram alinhados ao fluxo efetivo.

A descoberta avalia `create` no contexto empresarial antes de validar o documento, normaliza e verifica CPF/CNPJ completo e busca somente no tenant. A resposta sem cache contém ID, nome, tipo de Pessoa e situação do papel somente na empresa escolhida. Documento de outra conta recebe a mesma resposta que um documento ausente. O teste inclui parâmetro malformado, negação e reutilização. Em duas corridas HTTP de cinco tentativas, o cadastro central retornou um 200 e quatro 422, e o vínculo retornou um 204 e quatro 422. A restrição de unicidade e as travas foram exercitadas em PostgreSQL descartável. Na auditoria final, o documento ainda estava na query string. O fechamento da E07 abaixo registra a correção e a nova evidência de privacidade.

**Área de Trabalho e esquema.** A regressão cobre filiais da mesma empresa, empresas diferentes, permissões distintas por papel, filtros, paginação, escrita com empresa explícita e revogação. No Chromium, **Abrir área de trabalho** a partir de Pessoas fez o PUT da preferência com resposta 204, abriu Janaúba e preservou o contexto da outra aba. O GET da preferência confirmou o código salvo. O ensaio de inspeção confirmou os modos leitura e edição, autoria, bloqueio de gravação de preferências e encerramento na próxima requisição após revogação, com a sessão independente preservada. A migração foi reaplicada a PostgreSQL limpo e a uma relação legada identificável e revertida no caso representável. A migração aborta diante de conta com zero ou várias empresas e de etiqueta legada incoerente. A reversão aborta quando há mais de uma relação do mesmo tipo por pessoa. O ensaio PostgreSQL preservou as 60.003 linhas de clientes após essa recusa. Atribuição arbitrária, sincronização legada e esquema paralelo estão ausentes.

**Visual.** Chromium foi executado com preferência escura de sistema em 1440, 1024, 768, 390 e 320 px. Foram conferidos Pessoas, Clientes, Fornecedores, ficha de visualização, etiquetas, badges e modal de vínculo, com a empresa visível no modo compacto e sem rolagem horizontal da página. Os tours foram percorridos em 1440 e 390 px. A ajuda dos três fluxos e os estados vazios por falta de relação e por filtro sem resultado foram verificados. Em 390 px foram conferidos descoberta, validação, vínculo, inativação com confirmação e reativação. A tela Aparência só oferece recolher o menu lateral. A aplicação manteve as mesmas cores de superfície e texto nas preferências clara e escura, em linha com o registro dos componentes compartilhados. Portanto, a evidência visual cobre o tema claro existente, inclusive com a preferência escura do sistema. Uma paleta escura da aplicação exigiria trabalho próprio, fora da E07.

**Carga prolongada.** PostgreSQL 18 descartável com 30 mil Pessoas, 60 mil clientes e 12 mil fornecedores sintéticos. Um worker FrankenPHP instrumentado, reciclagem configurada para 20 mil requisições somente no ambiente de ensaio. Após 180 requisições de aquecimento, foram medidas 3.600 requisições sequenciais, alternando três usuários em duas contas, Pessoas, Clientes, Fornecedores, filtros, paginação, contextos consolidados e acessos negados. Houve 2.800 respostas 200 e 800 respostas 404 esperadas, zero erros inesperados e zero reinícios durante a medição. HTTP mediana de 22,91 ms e p95 de 58,24 ms. Contagem por requisição de 14 a 16 queries em Pessoas, 9 a 10 em Clientes, 10 a 11 em Fornecedores e 5 a 8 em rejeições contextuais.

| Requisições após aquecimento | RSS do processo FrankenPHP (KiB) | Uso de PHP no início, mediana recente (bytes) |
| ---: | ---: | ---: |
| 0 | 324.140 | 9.291.864 (amostra) |
| 450 | 327.228 | 9.292.360 |
| 900 | 330.264 | 9.292.360 |
| 1.350 | 296.068 | 9.292.360 |
| 1.800 | 296.400 | 9.292.360 |
| 2.250 | 296.748 | 9.292.360 |
| 2.700 | 296.836 | 9.292.360 |
| 3.150 | 296.948 | 9.292.360 |
| 3.600 | 297.140 | 9.292.360 |

O pico adicional do PHP foi 282.224 bytes por requisição. O uso inicial permaneceu estável e o RSS do processo oscilou, caiu abaixo do ponto de aquecimento e variou 1.072 KiB nos 2.250 últimos atendimentos. O comportamento é compatível com alocação e retenção transitórias, sem indício de retenção PHP persistente nesta carga. O ensaio sequencial em um worker não representa todos os perfis de concorrência da implantação.

**Regressão.** PHP completo com SQLite em memória e e-mail em array passou com 217 testes e 2.387 asserções. O recorte comercial passou com 39 testes e 566 asserções. Passaram teste de frontend, arquitetura, tipos, build, PHPStan, Pint, `permissions:sync` (81 permissões), `audit:check` (12 modelos) e `organization:check`. A E07 está pronta para homologação formal do escopo disponível e permanece **Em validação**. A auditoria não exigiu redesenho ou mudança no seletor e não iniciou a E08.

**Entregáveis.** Código, migrations, testes, ajuda, tours, relatório.

---

### E08: Auditoria contextual

**Identificação.** Objetivo: auditoria filtrada pelo escopo autorizado, sem trilha paralela. Situação inicial: A10, A11 e A12.

**Diagnóstico.** Reaproveitado: `audit_records`, `AuditLog`, `AuditQuery`, `AuditController` e a interface atual.

**Regras de negócio.** Seção 11. As colunas e a gravação do contexto existem desde a E04 (11.1), e a consulta contextual mínima desde a E05 (11.1.1). Esta etapa trata filtros por empresa e unidade, escolha explícita da visão consolidada, índices de leitura medidos, legado e plataforma. Se a E05 tiver saído com a auditoria local bloqueada, esta etapa entrega a consulta contextual e o desbloqueio.

**Escopo técnico.**
- **Banco:** índices de leitura candidatos (16.3), só com ganho medido; migração das linhas de `directory_audit_events` e `access_catalog_events`; remoção posterior das tabelas legadas, com aprovação.
- **Backend:**
  - evolução da consulta contextual da E05 (ou sua entrega e o desbloqueio, se a E05 tiver saído bloqueada);
  - filtros de empresa e unidade em `AuditFilterData`;
  - restrição da plataforma (PD-02).
- **Frontend:** filtros de empresa e unidade (ocultos quando não houver mais de uma opção); detalhe mostra empresa e unidade.

**Componentes envolvidos.**
- Existentes: `Auditable`, `AuditsChanges`, `AuditLog`, `AuditContext`, `AuditQuery`, `AuditController`, `AuditFilterData`, `audit.tsx`, `AuditRecordDetails.tsx`, `CheckAuditCommand`.

**Dependências.** E06; E07 recomendada (gera eventos de empresa reais).

**Performance.**
- Cenário de 200 mil registros e 50 unidades: página e contagem com índice, sem consulta por unidade.
- `EXPLAIN ANALYZE` registrado.

**Segurança.** Cenários 22 e 26; detalhe de um evento fora do escopo retorna 404; opções de filtro sem vazamento.

**Testes.** Cenário 22, consolidação legada sem duplicatas, plataforma conforme PD-02, `audit:check`.

**Critérios de aceite.**
- Nenhuma escrita em `directory_audit_events` (já atendido desde o commit `a661b8d`) nem em `access_catalog_events`.
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
- A remoção definitiva de `users.account_id` foi autorizada sem período artificial de estabilidade, após substituir suas dependências. As demais colunas citadas na revisão anterior foram removidas na E05.

**Escopo técnico.**
- **Banco:** remoção de `users.account_id` após a seleção de tenant usar o vínculo.
- **Backend:** resolução do tenant exclusivamente pela URL; seleção de conta; `EnforceAccountInspection` e `AccountInspectionSession` por vínculo; `CreateUserAction` com identidade existente.
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
- **Antes da contração e enquanto nenhuma identidade tiver dois vínculos:** reversível sem perda. Basta desligar a chave e reverter o código, porque a referência de conta em `users.account_id` ainda está disponível.
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
| 53 | Nenhum administrador de tenant altera foto, e-mail de acesso, senha, fatores de autenticação ou situação da identidade, mesmo com vínculo único; troca de e-mail só pelo fluxo da própria pessoa, com confirmação; o administrador apenas dispara o link de redefinição | E05 | Segurança |
| 54 | Troca do próprio e-mail: a troca só ocorre após a verificação do novo endereço; o anterior recebe aviso; as demais sessões são encerradas e a sessão atual é preservada (I1) | E05 | Segurança |
| 55 | Respostas neutras: recuperação de acesso, troca do próprio e-mail, criação de usuário e edição de pessoa respondem igual para e-mail existente ou inexistente (I2) | E05 | Segurança |
| 56 | Limitação de tentativas nas rotas de recuperação de acesso, verificação e troca de e-mail (I2) | E05 | Segurança |
| 57 | Reativar o vínculo não religa `access_enabled` da identidade; disparar a recuperação não expõe nem define a senha | E05 | Segurança |

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
| E05 | `account_memberships`, `role_availability`, `role_assignments` | Um vínculo por usuário com conta; uma atribuição de escopo tenant por `users.role_id`; todos os cargos com disponibilidade `tenant`. Reproduz exatamente o acesso atual. `000008` remove o cargo único, o indicador de administrador e o vínculo direto com Pessoa | Expansão reversível com dados representáveis. Contração por restauração de cópia anterior |
| E07 | `company_id` em `customers` e `suppliers`; etiquetas comerciais derivadas | A empresa única de cada tenant (equivalente ao estado atual) | R até existir pessoa com relação em duas empresas; depois a `down()` aborta (recuperação B) |
| E08 | Índices de leitura; consolidação legada | Linhas legadas copiadas com `legacy_key`; originais mantidas até a validação | R; a remoção das tabelas legadas é B (com exportação) |
| E09 | Liberação de vários vínculos e remoção de `users.account_id` | Liberação por chave; contração autorizada na E09 após substituir dependências e validar, sem espera artificial | R até a primeira identidade com dois vínculos; depois B. A contração é B |

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
| PD-04 | Prefixo de caminho para o tenant, inicialmente | Decisão anterior substituída em 23/11/2026: todas as URLs usam `/contas/{conta}/...`, inclusive com vínculo único. GET/HEAD antigos só redirecionam sem ambiguidade; com várias contas, abrem a seleção. Escritas antigas não são executadas nem redirecionadas. Cookie de sessão único por domínio, compatível porque o contexto vem da URL | **Aprovada** | E09 |
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
| E02 | Concluída | 20/11/2026 | 20/11/2026 | Convenções publicadas, `AGENTS.md` emendado, conferência de coerência e de texto (registros abaixo) | DA-26 a DA-30 aprovadas; I1 e I2 atribuídas à E05 |
| E03 | Concluída | 20/11/2026 | 20/11/2026 | Registro E03 e relatório de medições | Concorrência e custos de migrations em bases maiores ficam para trabalho futuro |
| E04 | Concluída | 20/11/2026 | 20/11/2026 | Registro de execução e homologação final da E04 na seção 26 | Implantação pendente de conferência da base oficial, backup e confirmação fiscal legada |
| E05 | Concluída | 20/11/2026 | 20/11/2026 | Registro, auditoria, revisão, verificação da inspeção e homologação final da E05 na seção 26 | Implantação pendente de provedor de e-mail, backup, migrations e reinício controlado dos workers; `users.account_id` preservada até a E09 |
| E06 | Concluída (homologada) | 20/11/2026 | | Registro de execução na seção 26 e homologação confirmada na autorização da E07 | Pendências de implantação e de módulos operacionais preservadas |
| E07 | Concluída | 21/11/2026 | 21/11/2026 | Auditoria final na subseção E07 e homologação nesta seção | Privacidade da descoberta corrigida e verificada; tema disponível aprovado |
| E08 | Concluída | 21/11/2026 | 23/11/2026 | `7cd9b9d`, retomada, matriz de cobertura, medições e homologação formal na seção 26 | Homologação técnica aprovada; limitações sintéticas aceitas e condições futuras de implantação preservadas |
| E09 | Concluída | 23/11/2026 | 23/11/2026 | [Validação integrada](001-e09-validacao-integrada.md): PostgreSQL 18, concorrência, FrankenPHP, Chromium e suíte completa, refeitos sobre a árvore final; homologação formal na seção 26 | Homologação técnica aprovada; implantação não executada e condicionada às pendências operacionais registradas |
| E10 | Em validação | 24/11/2026 | | [Homologação integrada](001-e10-homologacao-integrada.md): laboratório PostgreSQL 18, FrankenPHP, Mailpit e Chromium; 494 verificações HTTP, 301 no navegador, evolução e reversão, concorrência, 8.404 requisições em workers e suíte com 278 testes | Aguardando homologação formal; defeitos D1 (deadlock) e D2 (Etiquetas por atribuição local) corrigidos e revalidados; implantação não executada |

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

#### E02, revisão 6 e encerramento (20/11/2026)

- **Aprovação:** DA-26 a DA-30 e a publicação das convenções organizacionais, de identidade, contatos, permissões, auditoria e performance.
- **Ajustes da validação:**
  - identidade: `account.users.photo` confirmado como nome de rota de leitura da foto (protegida por `account.users.view`), não como permissão; nenhuma permissão `account.*` altera foto, e-mail, senha ou fatores de autenticação do titular; reativar o vínculo não reativa a identidade; `account.users.reset-password` renomeada para `account.users.send-password-reset`, que descreve o envio do link;
  - contatos: aviso permanente de cadastro compartilhado ao editar contatos e endereço centrais, inclusive com uma empresa só; DA-25 preservada; sem sincronização entre empresas; "Pessoa de contato" por empresa avaliada na E07;
  - segurança de e-mail: I1 e I2 registradas como tarefas da E05 (verificação do novo endereço, aviso ao anterior, encerramento das demais sessões, respostas neutras, limitação de tentativas e proteção contra enumeração), com os cenários 54 a 57; a correção de I2 é condição para disponibilizar publicamente os fluxos afetados;
  - URLs: prefixos, formato e imutabilidade dos códigos e o código reservado `todos` aprovados; o identificador técnico do tenant fica para a E09, sem bloquear a E02.
- **Coerência:** o plano foi atualizado para refletir a remoção de `DirectoryAudit` no commit `a661b8d`, feita em trabalho separado e autorizado (seções A11, 11.4, E05 e E08). A remoção da tabela `directory_audit_events` continua dependendo de aprovação.
- **Arquivos alterados nesta revisão:** este plano; `AGENTS.md`; `docs/convencoes/identidade-e-vinculos.md`, `permissoes.md`, `pessoas-e-contatos.md` e `contexto-organizacional.md`.
- **Conferências:** plano, `AGENTS.md`, convenções novas e existentes, registro de decisões, matriz de testes e critérios de performance comparados; termos antigos (`reset-password`, `DirectoryAudit` como pendência) eliminados; nenhum travessão ou meia-risca; links válidos.
- **Estado:** E02 concluída. E03 a E10 pendentes. Nenhuma alteração de código, banco, migrations, interfaces ou infraestrutura nesta revisão; sem commit.

Cada etapa acrescenta aqui um bloco com:

- data;
- commits;
- testes executados e resultados;
- medições antes e depois;
- telas e larguras verificadas;
- desvios do plano e decisões tomadas;
- pendências.

---

### Registro da E04 em 20/11/2026

**Estado na entrega: Em validação.** A retomada encontrou a E03 versionada em `00f04d6`, com staging vazio. A E04 foi continuada na árvore de trabalho existente, sem commit.

**Implementação e arquivos.**

- `app/Domains/Organizacao/` reúne Models, DTOs, Data, Actions, Queries e Controllers. As rotas em `routes/saas.php` usam `AccountContext` e `EffectivePermissions`. Empresa e unidade têm permissões por ação. Alterar situação exige a permissão dessa transição e promover tem operação própria. `config/permissions.php` e `DefaultRolesAction` declaram os novos acessos do RBAC por conta.
- O cadastro de empresa provisiona a unidade principal ativa na mesma transação. As escritas de empresa e unidade serializam na linha da empresa, preservam a raiz fiscal e impedem inativar a unidade principal antes de uma substituição. A classificação jurídica é explícita, independente de principal. O CHECK da principal ativa e os índices parciais garantem condições locais. O comando somente leitura `organization:check` verifica os invariantes entre linhas e lista pendências fiscais.
- `app/Support/TaxDocument.php` normaliza e formata CPF, CNPJ numérico ou alfanumérico e raiz. Valida os dígitos verificadores de documentos completos e compara empresas pela raiz. Conta, Pessoas, Empresas e Estabelecimentos reutilizam o validador. A compatibilidade com o módulo 11 e a conversão ASCII menos 48 foi conferida com a [documentação técnica da Receita Federal](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj).
- `Auditable`, `AuditsChanges`, `AuditObserver`, `AuditLog`, o formatador, a configuração e `audit:check` cobrem os novos eventos. Empresa grava `company_id`. Estabelecimento grava os dois IDs. O provisionamento compartilha `operation_id`. A inspeção mantém o autor SaaS e a representação. Eventos anteriores continuam com IDs organizacionais nulos e `directory_audit_events` é preservada.
- `resources/js/app/saas/` traz Início, Empresas, Estabelecimentos, formulários, visualizações e confirmação de promoção. Dados da conta mantém `/conta/editar` e usa cadastro do contratante. O acesso pessoal fica no menu de perfil e segurança. Com uma empresa, a edição abre diretamente para quem tem acesso e o filtro de empresa fica oculto. Foram reutilizados `BusinessList`, `BusinessListSearch`, `BusinessForm`, `PageHeader`, `SelectControl`, `Modal`, `PreviewSection`, `StatusLabel` e os campos de endereço e contatos. `guidance.ts`, `HelpCatalog`, `docs/ajuda/` e `docs/tours/` integram os artigos e passos existentes.
- Cobertura em `OrganizationTest`, `OrganizationMigrationTest`, `TaxDocumentTest`, `AccountInspectionTest` e `SaasPlatformTest`. `tests/Performance/e04-check.sh` reproduz medições, planos, isolamento e concorrência em PostgreSQL descartável. `e04-stack.sh` prepara o ambiente visual.

**Banco e migração.**

| Migration | Resultado |
| --- | --- |
| `000002_remove_points` | Remove Pontos quando a tabela está vazia, audita a remoção do catálogo e troca a listagem do cargo inicial Usuário por Estabelecimentos. Com dados, a migração exige análise e exportação prévias. A reversão recria a tabela e os acessos iniciais. Atribuições de cargos personalizados exigem backup para restauração exata. |
| `000003_create_organization_structure` | Cria `companies` sob `clients` e substitui o esquema vazio de `establishments`, com FK de `(company_id, account_id)`. Se houver estabelecimentos legados, interrompe antes de substituí-los. |
| `000004_add_organization_to_audit_records` | Adiciona os dois IDs opcionais. A reversão do esquema retira os IDs das linhas históricas, mantendo os eventos. |
| `000005_rename_account_data_permission` | Renomeia a edição dos dados contratuais para `account.account-data.update`, preserva os vínculos e audita a mudança. |
| `000006_provision_account_companies` | Provisiona por conta de forma idempotente. Reversão bloqueada quando existem cadastros além do inicial ou alterações organizacionais auditadas. O procedimento seguro para preservar histórico contextual e permissões personalizadas é restaurar o backup anterior à E04 ou corrigir para frente. |

A cópia local da E03 tinha 1 conta, 2 usuários e zero linhas em `establishments`, `points` e `directory_audit_events`. A migração, reversão das cinco migrations e reaplicação passaram em PostgreSQL 18.7 descartável. A conta recebeu empresa inicial e unidade principal ativa. O nome da conta serviu como identificação inicial, e os dados fiscais, endereço e contatos ficaram pendentes quando a identidade com o contratante não estava comprovada. `clients.document` conserva a unicidade global. Em novo tenant, a plataforma confirma explicitamente a eventual identidade fiscal entre empresa e contratante.

**Índices e performance.** As chaves únicas de código por conta e de `companies(id, account_id)` garantem a identidade e a FK. Os índices únicos parciais cobrem raiz e CPF de empresa por conta, CNPJ de estabelecimento por conta, matriz por empresa e principal por empresa. O índice `establishments(company_id, active, name)` atende contagem, seleção de unidades e busca por estado da unidade. Cada índice acrescenta manutenção em inserções e na alteração de suas chaves. A auditoria recebeu apenas colunas.

Em 74 unidades sintéticas, o PostgreSQL preferiu a varredura sequencial para o filtro por empresa, situação e conta. Em uma tabela temporária com 74.000 unidades de 5.000 contas, a contagem de 10 unidades por empresa passou de 3,347 ms por varredura sequencial a 0,026 ms com o índice. A listagem filtrada passou de 0,047 ms a 0,025 ms com bitmap combinado. A prova usou `EXPLAIN (ANALYZE, BUFFERS)` após `ANALYZE`. O custo de escrita em volume e o impacto sob carga sustentada seguem sem medição.

Medições HTTP sequenciais com PostgreSQL 18.7 e FrankenPHP 1.13.6, 10 amostras por rota e cenário após aquecimento. Empresas usa `withCount`, 7 queries. Estabelecimentos usa eager loading e paginação SQL, 9 queries. Ambos mantiveram 1 consulta de slugs e zero duplicatas nas amostras de listagem.

| Empresas / unidades | Empresas: linhas, HTTP 1/2 workers (ms) | Estabelecimentos: linhas, HTTP 1/2 workers (ms) | Banco, pico PHP em Estabelecimentos com 2 workers |
| --- | --- | --- | --- |
| 1 / 1 | 1, 5,42 / 5,01 | 1, 5,91 / 6,20 | 2,29 ms, 98.808 bytes |
| 1 / 3 | 1, 5,22 / 4,54 | 3, 5,98 / 5,19 | 2,04 ms, 100.240 bytes |
| 3 / 10 | 3, 7,30 / 6,21 | 10, 7,31 / 5,71 | 1,99 ms, 129.200 bytes |
| 10 / 10 | 10, 4,83 / 4,94 | 10, 6,41 / 6,25 | 2,15 ms, 148.368 bytes |
| 5 / 50 | 5, 5,66 / 4,86 | 50, 9,13 / 7,74 | 2,09 ms, 318.128 bytes |

Na conta com 50 unidades, filtrar uma empresa devolveu 10 linhas em 9 consultas, 5,72 ms HTTP e 128.824 bytes de pico PHP com dois workers. O SQL de slugs ficou em 1 consulta assim como na E03. Na comparação indicativa com a E03, que usou fixtures diferentes, `/conta` foi de 6 para 7 consultas com o novo resumo. `/ajuda` manteve 5 consultas e passou de aproximadamente 255 KB a 282 KB de pico PHP com os artigos adicionais. Em uma instância de worker, 300 requisições alternaram duas contas após respostas 403, 404 e 422. A memória no início das últimas quatro requisições válidas foi 9.134.704 bytes. Com dois workers, ambas as promoções concorrentes aguardaram a trava da empresa e concluíram com uma única principal ativa e dois IDs de operação. No conflito entre promoção e inativação da mesma unidade, uma requisição concluiu e a outra recebeu 422. Em dois cadastros concorrentes com o mesmo documento, somente um concluiu e deixou uma empresa com sua unidade principal ativa. A autoria de inspeção foi verificada nos testes funcionais.

**Validação visual.** Chrome em 1440, 390 e 320 px. Conferidos Início, Dados da conta, listagens, visualizações, cadastro, edição, erros de validação e confirmação de promoção, além do redirecionamento e ocultação do filtro com uma empresa. O percurso dos tours incluiu Início, Dados da conta, listagens e cadastros nas três larguras. As capturas da organização e os logs de interação estão em `/tmp/e04/recheck`, `/tmp/e04-visual.log` e `/tmp/e04-tours.log`. O navegador confirmou a promoção após o salvamento e validou os filtros de empresa e situação. Também foram conferidos os artigos, o cadastro SaaS com a confirmação de identidade do contratante e as telas pessoais de Perfil e Segurança.

**Testes e verificações.** A suíte SQLite isolada em container com GD passou com 156 testes e 1.420 asserções. O teste dedicado de performance da E03 passou com 45 asserções e manteve uma consulta de permissões nas rotas medidas. PHPStan, build com checagem de arquitetura e TypeScript, `permissions:sync`, `audit:check` e Pint no escopo da E04 passaram. O Pint global sinalizou a ordem de imports e um nome qualificado já presentes em `bootstrap/app.php` no checkpoint da E03.

**Pendências registradas na entrega.** Confirmar o documento e os dados da empresa inicial legada. Fazer as contagens de pré-implantação em ambiente oficial com autorização própria e guardar backup recuperável, incluindo as permissões personalizadas. O ensaio de migração usou somente a cópia local. Nenhuma implantação foi executada.

### Homologação final da E04 em 20/11/2026

**Resultado: Concluída.** Revisão do diff desde a E03 (`00f04d6`), incluindo o commit da E04 (`cad33d8`) e as alterações locais. O escopo termina na gestão administrativa da organização por conta. E05 a E10 continuam pendentes.

- **Integridade e acesso.** As FKs impedem vínculos inconsistentes com a conta, os índices parciais limitam a uma principal e a uma matriz por empresa, e o CHECK exige que a unidade principal permaneça ativa. O provisionamento e a promoção usam transações auditadas. As escritas concorrentes seguem a trava da empresa e o cadastro também trava a conta. A leitura usa `AccountContext` e queries com escopo de tenant. A suíte confirmou separação de ações, rejeição de IDs externos, administração integral sem acesso à plataforma, preservação de atribuições e autoria SaaS em inspeção.
- **Banco, Fiscal e Pontos.** PostgreSQL 18.7 descartável aplicou, reverteu e reaplicou as cinco migrations a partir do esquema da E03. O ensaio preservou o documento do contratante e o evento sintético em `directory_audit_events`. A empresa legada entrou com identificação fiscal pendente e unidade principal ativa. `organization:check` reportou a pendência fiscal e confirmou os invariantes. A remoção de Pontos exige tabela vazia e retira suas permissões. A suíte cobre CPF, CNPJ numérico e alfanumérico, comparação pela raiz, bloqueio da remoção de Pontos com registros e o mapeamento de permissões de Dados da conta.
- **Suíte e análise estática.** O registro de entrega conserva as 156 execuções e 1.420 asserções da rodada anterior. A repetição com PHP 8.5.13, GD e SQLite em memória passou com 157 testes e 1.448 asserções. A prova dedicada de custo de permissões passou com 45 asserções. PHPStan, Pint nos arquivos PHP da E04, build, checagem de arquitetura, TypeScript, `permissions:sync`, `audit:check` e `git diff --check` passaram.
- **FrankenPHP e desempenho.** Com FrankenPHP 1.13.6, um worker repetiu 300 requisições alternadas entre contas após respostas 403, 404 e 422. Dois workers repetiram os conflitos entre promoções, entre promoção e inativação e entre cadastros fiscais duplicados. A repetição manteve uma consulta de permissões por requisição e nenhuma consulta duplicada nas listagens. Empresas manteve 7 consultas e Estabelecimentos teve 9 como comportamento regular, com uma amostra de 10 em um worker. As medianas HTTP com um ou dois workers nos cinco cenários foram de 4,39 a 5,38 ms em Empresas e de 5,14 a 8,44 ms em Estabelecimentos. A execução usou amostras sequenciais após aquecimento, com dados sintéticos. Custos de escrita em volume e carga sustentada permanecem fora das medições.
- **Interface e documentação.** Chromium com aplicação real, PostgreSQL descartável e dados sintéticos em 1440, 390 e 320 px. Conferidos Início, Organização, Empresas, Estabelecimentos, Dados da conta, cadastro de empresa, edição de estabelecimento e tours. Foram repetidos a expansão da árvore, o modal de detalhes, a escolha de classificação, a inclusão e remoção de e-mail e a ocultação do filtro com uma empresa. A conta de 50 unidades confirmou filtro por empresa e situação. A revisão corrigiu o destaque de Organização no menu, a referência ao controle de expansão na ajuda e a classificação explícita no fixture de concorrência. Logs e capturas da repetição em `/tmp/prospera-e04-homologacao/`.

**Pendências de implementação.** Nenhuma para fechar a E04.

**Pendências de implantação.** Obter autorização para conferir contagens de `clients`, `establishments`, `points`, `users` e `directory_audit_events` na base oficial e guardar backup recuperável, incluindo permissões personalizadas. Se houver dados em Pontos ou na tabela legada de estabelecimentos, analisar e aprovar a destinação antes da implantação. Confirmar a identificação fiscal da empresa inicial legada com o responsável pela conta. A homologação usou ambientes descartáveis e dados sintéticos.

### Registro da E05 em 20/11/2026

**Estado: Em validação.** A E03 está versionada em `00f04d6`. A implementação e a homologação da E04 estão versionadas em `cad33d8` e `abd35bb`, na branch remota. A E05 partiu de `abd35bb` e foi versionada em `1a8a84d` para homologação. E06 a E10 continuam pendentes.

#### Implementação

- A migration `2026_11_20_000007_create_contextual_access.php` cria `account_memberships`, `role_availability` e `role_assignments`, acrescenta a disponibilidade de cargos, a situação de convite nas identidades e o identificador do vínculo de autoria em `audit_records`. FKs compostas protegem o tenant, a pessoa, o cargo e o par empresa e estabelecimento. Os CHECKs validam a situação do vínculo e a forma de cada escopo. Índices parciais impedem atribuições e disponibilidades duplicadas. Os índices de conta e cargo, empresa e unidade sustentam a revisão de abrangência e as consultas de escopo. Nenhuma tabela histórica foi removida.
- `AccountMembership`, `RoleAssignment`, `RoleAvailability`, `SaveRoleAssignmentsAction` e `SetMembershipStatusAction` implementam concessão, revogação e suspensão por tenant. A trava da conta serializa alterações de disponibilidade, atribuições e suspensão. A ação valida a abrangência do cargo e a autoridade do operador no nó, inclusive para remoção. `SaveRoleAction` lista as atribuições descobertas e exige a confirmação dos IDs afetados dentro da transação que grava a mudança.
- `EffectivePermissions` continua com binding `scoped`. Carrega as atribuições válidas do vínculo ativo e as permissões dos cargos distintos, em duas consultas. O Gate aceita o recurso-alvo e o resolvedor disponibiliza descritores para SQL antes da paginação. Operações sem alvo explícito ficam limitadas a atribuições de tenant. A sincronização valida os alvos em `config/permissions.php`, preserva a concessão automática ao administrador integral e mantém `account.users.photo` como rota de leitura protegida por `account.users.view`.
- `AuditQuery` aplica o descritor na listagem, na contagem e nas opções de filtro. O detalhe exige cobertura de listagem e visualização para o mesmo evento, e os dados técnicos exigem a autorização própria. Atribuições, disponibilidade, suspensão e disparos de links são auditados. A inspeção referencia o vínculo ativo e mantém a autoria real do administrador SaaS.
- `ChangeAccessEmailAction` e `AccessEmailController` verificam a senha atual, enviam link assinado temporário ao novo endereço, confirmam a identidade autenticada e notificam o endereço anterior. O Fortify permanece responsável pela redefinição. As respostas públicas de recuperação e as respostas de solicitação de acesso são neutras, com limitação de tentativas nos fluxos de recuperação e troca de e-mail. A revisão da sessão protege requisições de sessões antigas após troca de e-mail ou senha. A edição de Pessoas mantém nome e contato no cadastro do tenant. Reativar o vínculo preserva a situação da identidade global.
- Cargos, Usuários, Pessoas e Perfil usam os componentes de formulário, listagem, modal e tour existentes. A interface distingue disponibilidade, atribuição e permissões efetivas. Usuários apresenta o escopo das atribuições, busca e ações conforme a autoridade. Ajuda e tours foram atualizados.

#### Migração e reversão

A primeira entrega migrou o cargo anterior em escopo tenant e preservou temporariamente as colunas de `users`. A contração posterior está registrada na seção Revisão da E05 após a auditoria. O escopo de autorização usa o vínculo e as atribuições, e o esquema suporta mais de um vínculo por identidade.

O ensaio em PostgreSQL 18.7 aplicou, reverteu e reaplicou a E05 após inserir um vínculo legado sintético no esquema da E04. O vínculo, a atribuição tenant e o cargo espelhado sobreviveram à ida e volta. A reversão direta é recusada quando há vínculos ou atribuições que o modelo anterior perderia, incluindo suspensão, vários cargos ou disponibilidades restritas. Após uso contextual, preservar a exportação e seguir o procedimento de recuperação aprovado antes de reverter. Histórico de auditoria da organização e dados fiscais da E04 preservados.

#### Verificações e medições

- 170 testes e 1.548 asserções em SQLite em memória no contêiner PHP 8.5.13 com GD. A prova dedicada de orçamento de consultas acrescentou 45 asserções. PHPStan, Pint no escopo, build, arquitetura, TypeScript, `permissions:sync`, `audit:check` e `organization:check` aprovados em ambiente isolado.
- FrankenPHP 1.13.6 com PostgreSQL 18.7. Um worker passou por 300 requisições da regressão entre contas e mais 300 da E05 alternando perfis de 1, 5 e 30 atribuições com administração integral e erros 404. A regressão entre contas também exercitou 403 e 422. Com dois workers, duas concessões duplicadas disputaram a trava da conta. Uma retornou 200 e a outra 422, com uma única atribuição persistida. As duas suspensões concorrentes de administradores retornaram 200 e 403, preservando um administrador integral ativo.
- Em 5 empresas e 50 estabelecimentos, Estabelecimentos manteve 10 consultas nos quatro perfis abaixo e nenhuma consulta duplicada nas amostras. Empresas manteve 8 consultas como comportamento regular. A E04 tinha 9 e 7 consultas, respectivamente. A consulta adicional é a carga separada das atribuições. A estimativa de linhas das duas consultas vem das cardinalidades das fixtures. Tempos são medianas de 10 amostras após aquecimento, em sequência, em ambiente descartável. Medições HTTP e planos de consulta ficam no runner `E05_CONTEXTUAL=1 bash tests/Performance/e04-check.sh <cópia> 1|2`. Carga simultânea sustentada e entrega real de e-mail não foram medidas.

| Perfil | Linhas de atribuições + permissões | Autorização 1 / 2 workers (ms) | HTTP da listagem 1 / 2 workers (ms) | Memória mediana da autorização (bytes) |
| --- | --- | --- | --- | --- |
| 1 atribuição local | 1 + 2 | 1,306 / 1,490 | 7,139 / 7,392 | 5.416 |
| 5 atribuições, 2 cargos | 5 + 4 | 1,541 / 1,687 | 7,739 / 8,282 | 5.416 |
| 30 atribuições, 2 cargos | 30 + 4 | 2,127 / 2,243 | 9,956 / 10,718 | 6.376 |
| Administrador integral | 1 + 69 | 1,508 / 1,674 | 10,239 / 10,747 | 23.160 |

- Chromium em 1440, 390 e 320 px contra instância descartável com dados sintéticos. Foram conferidos Cargos, Usuários, Pessoas, Perfil e abertura dos tours. No desktop, o percurso gravou cargo, usuário, segunda atribuição e redução de abrangência com confirmação, preservando a atribuição coberta. O modal de atribuições foi aberto nas três larguras. Logs e capturas em `/tmp/prospera-e05/`.

#### Pendências de homologação e implantação

- Homologar a matriz de papéis com operadores reais e a configuração de entrega de e-mail do ambiente. Convites criados antes do marcador de senha pendente preservam o fluxo de recuperação de senha. A migração não tenta inferir o estado deles a partir de hashes.
- Antes da E07, relações comerciais legadas não identificam empresa. Para pessoa com relação ativa em conta com mais de uma empresa e sem identificação inequívoca do vínculo comercial, a edição dos dados centrais fica reservada ao escopo tenant. A tela apresenta os dados para leitura quando permitida. O cadastro sem relação e o caso de empresa única usam a cobertura do operador.
- Antes da implantação, revisar backup e sequência de migração, sincronização de permissões e reinício dos workers. As verificações desta entrega usaram somente bancos descartáveis e dados sintéticos.

### Auditoria final da E05 em 20/11/2026

**Decisão na auditoria.** A E05 permaneceu **Em validação**. A revisão partiu de `1a8a84d`, com a árvore limpa e 122 arquivos alterados desde `abd35bb`. O diff cobre vínculos, identidade, cargos, atribuições, autorização e as integrações previstas. E06 a E10 seguem pendentes. A inspeção SaaS, a auditoria contextual mínima e o preparo dos vínculos para vários tenants já pertencem à E05.

#### Achados identificados na auditoria

| Origem | Evidência na revisão de código | Condição para fechar |
| --- | --- | --- |
| E05 | `SaveRoleAssignmentsAction::execute` busca o vínculo antes de verificar a autoridade para gerenciar atribuições. Uma alteração sem concessões ou revogações passa pelas duas verificações por item e ainda executa `syncLegacyRole`. A escrita fica acessível sem a permissão de atribuição e a resposta distingue vínculos existentes na conta. | Verificar a autoridade antes de consultar o vínculo, rejeitar operações vazias e preservar a validação sobre cada atribuição alterada. |
| Presente em `abd35bb` | `EnforceAccountInspection` verifica o usuário representado. A condição de administrador SaaS do autor real só é conferida no início e em `EndAccountInspectionAction`, ao encerrar. Uma inspeção aberta pode continuar com as permissões do representado após a revogação da autoridade de seu autor. | Revalidar o autor real durante a inspeção e encerrar a sessão de inspeção assim que a autoridade SaaS for revogada. |

#### Matriz executada

A matriz em `tests/Feature/ContextualAccessTest.php` usa duas empresas na mesma conta e uma conta separada. Confere listagem e total, acesso por ID, filtro por empresa, formulário de criação, alteração, concessão e retirada de cargo, listagem e detalhe de auditoria. Verificações complementares cobrem concessão de autoridade superior, revogação fora do escopo, disponibilidade, efeito na requisição seguinte e manutenção do vínculo após retirada da última atribuição local.

| Perfil sintético | Permitido e confirmado | Negado e confirmado |
| --- | --- | --- |
| Administrador integral | Listagem das duas empresas por unidade, alterações, atribuições e histórico da conta, inclusive eventos de Pessoas | Unidade de outra conta |
| Administrador de empresa | Criar, listar e editar unidades da própria empresa, inclusive unidade nova, atribuir e revogar localmente e consultar dados técnicos da auditoria | Unidade, criação e revogação em outra empresa, eventos de conta e de Pessoas |
| Administrador de estabelecimento | Ler e editar a própria unidade, conceder e revogar cargo nela e consultar auditoria local | Criação de unidade, edição fora do escopo, eventos compartilhados e detalhes técnicos sem permissão |
| Gerente de unidade | Leitura e auditoria da unidade | Edição, criação e atribuição |
| Operador em duas unidades | Listar as duas unidades e editar a unidade com o cargo de edição | Editar a unidade em que o cargo é de leitura, gerir atribuições e ler auditoria |
| Usuário sem atribuição | Vínculo ativo preservado | Listar unidades, cadastrar unidade e acessar auditoria |
| Usuário com vínculo suspenso | Identidade global preservada, verificada no teste de suspensão | Acesso ao tenant |
| Administrador SaaS em inspeção de leitura | Leitura e auditoria da unidade representada, retorno ao autor real ao encerrar | Escrita, outra unidade, segredo de segundo fator e registro de passkey |

A consulta de auditoria utiliza o descritor SQL antes da contagem, da paginação e das opções de filtros. O detalhe intersecta os escopos de listagem e visualização, com checagem própria para dados técnicos. A matriz distingue eventos de tenant, empresa, unidade, Pessoa compartilhada e atribuição. A suíte também confere autoria na inspeção. O histórico legado permanece preservado.

#### Identidade e regressão

- Mailpit em rede interna capturou convite, reenvio com token novo, recuperação de senha, confirmação assinada no novo endereço e aviso no endereço anterior. O ensaio verificou destinatários, conteúdo, assinatura, prazo, manutenção da sessão que confirmou a troca, encerramento da outra sessão e autenticação com a senha renovada. As mensagens circularam somente entre serviços descartáveis com endereços sintéticos.
- A suíte verificou respostas neutras, rate limiting, confirmação por identidade correspondente, preservação do login ao editar Pessoas e reativação do vínculo sem reativar a identidade. O Fortify passou pelos testes do desafio e da configuração de 2FA. A passkey foi verificada na configuração das rotas e no bloqueio de cadastro em inspeção, sem cerimônia com autenticador físico.
- SQLite em memória, 172 testes e 1.666 asserções. Prova de orçamento de consultas, 45 asserções. PHPStan, Pint no escopo, build, TypeScript, arquitetura, `permissions:sync` (81 permissões), `audit:check` (12 modelos) e `organization:check` aprovados. A revisão de ajuda e tours conferiu textos e alvos de Cargos, Usuários, Pessoas e Perfil. O navegador exercitou as quatro telas, o modal de atribuições e a confirmação de abrangência a 1440, 390 e 320 px.

#### PostgreSQL, reversão e desempenho

A migração foi aplicada, revertida e reaplicada em PostgreSQL 18.7, com vínculo legado sintético. A suíte confirmou equivalência de permissões, rejeição de referência entre tenants e recusa da reversão diante de atribuições contextuais. A reversão direta exige vínculo representável no modelo anterior, disponibilidade de conta e no máximo uma atribuição de tenant por vínculo, sem suspensão ou vínculo adicional. O marcador de convite pendente e a referência de autoria ao vínculo também saem no `down()`. Incluí-los na exportação de recuperação antes de uma eventual reversão. `directory_audit_events` fica preservada.

FrankenPHP 1.13.6 passou por 300 requisições alternadas da regressão da E04 e 300 da E05 no mesmo worker, com os totais restritos aos perfis usados. Com dois workers, concessões duplicadas retornaram 200 e 422 e deixaram uma atribuição. Suspensões simultâneas retornaram 200 e 403 e preservaram um administrador integral ativo.

| Perfil | Linhas de atribuições + permissões nas fixtures | Autorização 1 / 2 workers (ms) | HTTP de Estabelecimentos 1 / 2 workers (ms) | Memória mediana da autorização (bytes) |
| --- | --- | --- | --- | --- |
| 1 atribuição | 1 + 2 | 1,245 / 1,292 | 6,629 / 6,517 | 5.416 |
| 5 atribuições | 5 + 4 | 1,301 / 1,410 | 6,420 / 6,751 | 5.416 |
| 30 atribuições | 30 + 4 | 1,940 / 1,981 | 8,594 / 8,548 | 6.376 |
| Administrador integral | 1 + 69 | 1,479 / 1,354 | 9,962 / 8,914 | 23.160 |

Duas consultas de autorização por requisição, sem SQL duplicado nas amostras de listagem. Houve 10 consultas em Estabelecimentos na maioria das amostras e 11 em algumas. Na comparação direta com `abd35bb`, a listagem integral de 50 unidades foi de 9 para 10 consultas, com mediana HTTP de 8,395 para 8,357 ms com um worker. Empresas passou de 7 para 8 consultas na maioria das amostras, com ocorrências de 9 e mediana de 5,216 para 5,362 ms. Tempos são medianas de 10 requisições após aquecimento, em 5 empresas e 50 estabelecimentos. Os dados não representam carga simultânea sustentada nem uma base de produção.

#### Condições de implantação

A entrega por provedor externo continua pendente de verificação no ambiente de destino. Até a E07, a edição de dados centrais de Pessoas com relações comerciais legadas sem empresa identificável exige escopo de conta. A revisão não mudou esse comportamento. A árvore recebeu somente a matriz automatizada e este registro. Nenhum commit ou push foi realizado.

### Revisão da E05 após a auditoria

**Estado: Em validação.** A decisão de desenvolvimento antecipa parte da contração da E09. A migration `2026_11_20_000008_remove_single_role_from_users.php` remove `users.role_id`, `users.is_account_admin` e `users.person_id`, além dos índices e chaves correspondentes. `roles`, `permissions`, `permission_role`, `account_memberships`, `role_assignments`, `role_availability` e o histórico permanecem.

O cadastro de identidade gera a pessoa e a atribuição inicial somente ao provisionar um vínculo. Na plataforma, o resumo de Contas identifica o administrador por atribuição ativa de escopo tenant e conta os vínculos da conta. Editar Pessoas mantém a identidade separada. Para um vínculo existente, as concessões e revogações são feitas por **Editar atribuições**.

`users.account_id` permanece como referência explícita da conta para `AccountContext::linkedId` em requisições normais. Ela também dá o contexto dos eventos de autenticação e não concede acesso. A conta e o vínculo precisam estar ativos para operações do tenant. A E09 substitui a referência pela seleção do vínculo. Nenhum seletor multi-tenant foi acrescentado.

As duas correções da auditoria foram aplicadas. A Action de atribuições recusa solicitações vazias antes de buscar o vínculo e verifica a autoridade para atribuir antes de consultar a pessoa-alvo. As verificações de nó, cargo e delegação continuam por item. A inspeção revalida a identidade SaaS, seu acesso, a verificação do e-mail e a permissão de inspeção em cada requisição. A revogação encerra a sessão de inspeção na requisição seguinte e preserva o acesso original do usuário representado.

A retirada das colunas usa uma migration nova e preserva as migrations compartilhadas. Quem já aplicou a expansão pode aplicar a contração diretamente. A expansão anterior continua verificável em ambiente descartável. Reverter a contração exige restaurar uma cópia anterior, porque o antigo vínculo de Pessoa para identidades sem tenant não é reconstituível com precisão. Nenhum registro de Pessoas foi excluído.

- SQLite em memória, 174 testes e 1.697 asserções, mais 45 asserções de orçamento de consultas. As requisições vazias retornaram 403 para operador sem permissão e 422 para operador autorizado, independentemente da existência do vínculo. O log SQL confirmou ausência de consulta ao vínculo-alvo ou de gravação de usuário, atribuição e auditoria. A inspeção em edição foi encerrada após perda do papel SaaS, desativação da identidade ou perda da verificação de e-mail do autor.
- PostgreSQL 18.7 descartável aplicou a expansão a partir da E04 com dados sintéticos, reverteu a expansão e reaplicou ambas as migrations. O vínculo, a atribuição e os registros de Pessoas foram preservados. O banco final manteve apenas `account_id` entre as quatro colunas verificadas. A reversão direta da contração foi recusada. `permissions:sync`, `audit:check` e `organization:check` passaram.
- FrankenPHP 1.13.6 com um worker processou 300 requisições alternadas da E04 e 300 da E05. A inspeção em leitura foi encerrada na requisição seguinte à revogação, e o usuário representado continuou com seu acesso original no mesmo worker. Com dois workers, a concessão duplicada retornou 200 e 422, e a suspensão concorrente retornou 200 e 403, mantendo um administrador integral. A resolução permaneceu em duas consultas nas listagens com 1, 5 e 30 atribuições.
- PHPStan, Pint no escopo e build aprovados. Atualizados a ajuda de Pessoas e de Inspeção e o tour de Pessoas. Cargos, Usuários, Pessoas, Perfil e tours exercitados em Chromium a 1440, 390 e 320 px, com dados sintéticos.

### Verificação do encerramento da inspeção na E05

A verificação confirma o comportamento existente com sessões em banco. Após a perda da autoridade SaaS, a primeira requisição JSON da sessão em inspeção retorna 401, remove a identidade do guard e o estado de `AccountInspectionSession`, destrói o registro da sessão anterior no PostgreSQL e renova o ID e o token CSRF. Nas requisições seguintes, a sessão fica anônima. O teste recebeu 401 no Início da conta, na listagem de estabelecimentos, na edição dos dados da conta e ao chamar o encerramento da inspeção novamente.

O teste fez dois logins independentes pelo Fortify. Depois da revogação, o cookie anterior da inspeção continuou sem acesso mesmo após a restauração da autoridade SaaS. A sessão legítima do usuário representado manteve seu ID, token CSRF, identidade e token de lembrança. Ela continuou a ler e a editar a conta. O ensaio rodou no mesmo worker FrankenPHP 1.13.6, com PostgreSQL 18.7 descartável. A suíte SQLite em memória passou com 174 testes e 1.731 asserções. A prova de orçamento de consultas, o PHPStan e o Pint dos testes também passaram. Esta verificação acrescentou cobertura e manteve o fluxo de encerramento existente.

A E05 está tecnicamente pronta para aceite no ambiente de desenvolvimento validado e permanece **Em validação** até a aprovação formal de produto. A matriz foi exercitada com perfis sintéticos, pois o Prospera ainda não tem clientes reais. A entrega do provedor de e-mail externo e a sequência de backup, migração e reinício dos workers ficam para a implantação. A restrição sobre relações comerciais legadas está documentada para a E07. E06 a E10 continuam pendentes.

### Homologação final da E05 em 20/11/2026

**Resultado: Concluída.** A homologação técnica foi aprovada sobre `c476de6`, com a implementação em `1a8a84d` e a árvore limpa. E06 a E10 continuam **Pendentes**. A implementação da E05 só é reaberta diante de evidência de nova falha.

**Bloqueadores da auditoria resolvidos.**

- `SaveRoleAssignmentsAction` recusa a alteração sem autoridade para gerenciar atribuições antes de consultar o vínculo-alvo, e não há mais sincronização de cargo legado. Solicitações vazias retornam 403 para quem não tem a permissão e 422 para quem tem, com ou sem vínculo existente, sem consulta ao vínculo nem gravação.
- `EnforceAccountInspection` revalida o autor real a cada requisição. A revogação da autoridade SaaS encerra a inspeção na requisição seguinte, torna anônima a sessão do inspetor e não interfere nas sessões legítimas e independentes do usuário representado.

**Contração aprovada.** A remoção definitiva de `users.role_id`, `users.is_account_admin` e `users.person_id` pela migration `2026_11_20_000008_remove_single_role_from_users.php` foi aprovada. `users.account_id` permanece até a E09 como referência explícita da conta para `AccountContext::linkedId` e para o contexto dos eventos de autenticação, sem conceder acesso, conforme a Revisão da E05 após a auditoria.

**Resultado final.** SQLite em memória com 174 testes e 1.731 asserções, mais 45 asserções da prova de orçamento de consultas. PHPStan, Pint, build, TypeScript, checagem de arquitetura, `permissions:sync`, `audit:check` e `organization:check` aprovados nas rodadas registradas acima.

**Evidências de sessão, revogação e isolamento no FrankenPHP.** FrankenPHP 1.13.6 com PostgreSQL 18.7 descartável e sessões em banco:

- após a perda da autoridade SaaS, a primeira requisição da sessão em inspeção retornou 401, removeu a identidade do guard e o estado de `AccountInspectionSession`, destruiu o registro da sessão anterior e renovou o ID e o token CSRF. As requisições seguintes ficaram anônimas e o cookie anterior continuou sem acesso mesmo após a restauração da autoridade;
- a sessão do usuário representado, aberta por login independente no Fortify, manteve ID, token CSRF, identidade e token de lembrança, e continuou a ler e a editar a conta no mesmo worker;
- um worker processou 300 requisições alternadas da E04 e 300 da E05, com perfis de 1, 5 e 30 atribuições, administração integral e respostas 403, 404 e 422, sem vazamento de permissões entre requisições. A resolução ficou em duas consultas nas listagens;
- com dois workers, a concessão duplicada retornou 200 e 422 e persistiu uma atribuição, e a suspensão concorrente retornou 200 e 403, preservando um administrador integral ativo.

**Limitações das medições e dos testes concorrentes.** Os tempos são medianas de 10 requisições sequenciais após aquecimento, com dados sintéticos em 5 empresas e 50 estabelecimentos, em ambiente descartável. Não representam carga simultânea sustentada nem uma base de produção. A concorrência cobriu apenas pares de operações em dois workers (concessão duplicada e suspensão de administradores). A suíte usa SQLite em memória; o PostgreSQL foi exercitado em ensaios separados. A passkey foi verificada na configuração e no bloqueio em inspeção, sem autenticador físico. A matriz de papéis usou perfis sintéticos, pois o Prospera ainda não tem clientes reais.

**Pendências operacionais de implantação.**

- Verificar a entrega pelo provedor externo de e-mail no ambiente de destino (convite, reenvio, recuperação, confirmação e aviso de troca de e-mail). Os ensaios usaram somente Mailpit.
- Guardar backup recuperável antes de migrar, incluindo `role_assignments`, `role_availability`, o marcador de convite pendente e a referência de autoria ao vínculo. A contração `000008` só se desfaz restaurando essa cópia.
- Aplicar as migrations na ordem `000007` e `000008`, seguidas de `permissions:sync`, `audit:check` e `organization:check`.
- Reiniciar os workers de forma controlada após a migração e a sincronização, para que nenhum worker atenda com o esquema ou o código anteriores.

**Pendências preservadas para as próximas etapas.** `users.account_id` sai na E09, depois da seleção do vínculo. Até a E07, a edição de dados centrais de Pessoas com relação comercial legada sem empresa identificável exige escopo de conta. Convites anteriores ao marcador de senha pendente seguem pelo fluxo de recuperação. A homologação com operadores reais acompanha a primeira implantação.

### Registro de execução da E06 em 20/11/2026

**Estado: Em validação.** A execução partiu de `3b27a7e`, com a E05 versionada em `1a8a84d` e `c476de6`. A única alteração local anterior era o registro de encerramento da E05 neste documento, preservado sem commit; a cópia está em `/tmp/prospera-e06/e05-encerramento.patch`. Nenhum commit ou push foi feito. E07 a E10 continuam pendentes.

#### Revisão funcional da área de trabalho

A decisão vigente está na seção 12.4 e substitui o seletor de uma unidade e a última unidade em sessão descritos no registro original abaixo. A revisão das migrations e do código encontrou preferências de colunas de listagem em `localStorage` (`BusinessList`), indexadas por usuário e listagem, sem persistência no servidor nem chave de tenant. `account_memberships` já garante unicidade de `(user_id, account_id)` e guarda metadados de navegação. O padrão é armazenado nesse vínculo, sem tabela ou índice novos. A gravação é uma preferência pessoal e não modifica acessos ou dados de negócio. A integração fica no Início do estabelecimento e nas consultas compatíveis da infraestrutura. A E07 continua pendente. Na revisão visual, as caixas de seleção passaram para o menu compacto do Bold na barra superior, com uma caixa por empresa no fim da linha tracejada e gravação ao clicar fora.

- **Verificação funcional:** a suíte SQLite em memória registrou 191 testes, 1.952 asserções e um teste do Fortify pulado por configuração. A matriz da E06 verifica padrão por usuário e conta, subconjuntos em duas abas, revalidação após revogação, interseção com a permissão da operação, rejeição de parâmetros forjados e escrita com destino explícito. `audit:check` cobre 12 modelos.
- **Navegador:** Chrome em 1440, 390 e 320 px. Conferidos o seletor na Organização, o resumo consolidado e o Início da unidade. Exercitados marcação individual e por empresa, selecionar todas, salvar e restaurar, recarregar, alternar em duas abas, abrir uma unidade e retornar ao conjunto. Com uma unidade autorizada, o seletor ficou oculto.
- **PostgreSQL 18.7 e FrankenPHP 1.13.6:** o harness descartável passou com um e dois workers. Com um worker houve 300 requisições intercaladas entre usuários e contas, incluindo 30 leituras de subconjuntos. Em ambos, 40 respostas simultâneas de duas abas respeitaram as URLs. A abertura consolidada de duas unidades retornou duas linhas no resumo, com sete consultas, mediana HTTP de 5,24 ms e pico mediano de 79.544 bytes. Na conta de 50 unidades, o início da unidade permaneceu em oito consultas (mediana de 6,52 ms com um worker, ante 6,50 ms no registro original). A listagem administrativa de estabelecimentos passou de 11 para 12 consultas com a leitura do padrão na barra superior. Medianas atuais de 11,88 ms com um worker e 9,65 ms com dois. Nenhum índice novo.
- **Entrega:** migration aditiva em `account_memberships`, contrato e ajuda atualizados. A E06 segue em validação para homologação de produto.

#### Auditoria final antes da homologação (20/11/2026)

Auditoria direcionada sobre `13a0a29`, sem reimplementar o que funcionava. O código de contexto do backend não mudou desde `250b8e5` (depois dele só mudaram o cadastro de contas e de usuários), por isso as medições em PostgreSQL e FrankenPHP acima continuam válidas. Nenhum commit, push ou alteração do ambiente de produção.

- **Seleção em várias empresas:** o consolidado aceita unidades de empresas diferentes. Cada unidade passa pela cobertura de atribuição, e a consulta aplica no SQL a interseção com a permissão da operação (`scopeFor`, `authorizedIds`). Verificado em teste e no navegador (Gartan 3 com Gartan Express; Ana com Gartan Roupas e Janaúba da Gartan Express).
- **Escrita:** o middleware recusa escrita em `todos` (403), e as escritas usam o nó da URL, independentemente da seleção ou de outras abas (cenários 21 a 24).
- **Preferência:** `account_memberships.workspace_establishments` é gravada só no vínculo ativo do próprio usuário na conta, fica fora da inspeção e não é lida por `EffectivePermissions` nem pela autorização. O campo está justificado em `unauditedFields()`.
- **Revogação:** no navegador, a coluna continuou com a unidade revogada, mas o padrão efetivo, o seletor e a seleção da aba a excluíram na recarga seguinte. A URL antiga respondeu com a orientação, e Abrir área de trabalho levou à unidade restante.
- **Consultas e workers:** o seletor custa 1 consulta com 5 ou 50 unidades (teste 29). `OrganizationContext` e `EffectivePermissions` são `scoped`, sem estado estático, e as respostas usam `private, no-store`.
- **Defeito corrigido (seletor e URL):** ao abrir uma URL consolidada numa aba que já tinha passado por uma tela da conta, ao voltar no histórico ou ao recarregar depois disso, o seletor mostrava a seleção guardada na aba, e não a da URL. O servidor e as operações seguiam a URL. Agora, nas telas com contexto, a seleção do seletor vem da URL (`pageWorkspace` em `organizationContext.ts`); nas telas da conta, continua vindo da aba.
- **Defeito corrigido (gatilho):** acima de 767 px, o contador de unidades ficava cortado pelas reticências do rótulo. O gatilho passou de 180 para 200 px, e só o rótulo pode receber reticências. O nome acessível passou a usar o singular com uma unidade.
- **Verificação:** suíte SQLite com 192 testes e 1.989 asserções, `OrganizationContextTest` com 17 testes, `npm run check` e `npm run build` aprovados. O ambiente descartável (`e04-stack.sh`, PostgreSQL 18 e FrankenPHP com dois workers) executou migrations, `permissions:sync`, `audit:check` e `organization:check`. No Chrome headless, com 1440, 1024, 768, 390 e 320 px, foram verificados: URL consolidada, troca pelo seletor, voltar, recarga, unidade aberta pelo resumo, trilha de retorno, telas da conta, duas abas isoladas e revogação. O contador ficou visível, a barra superior manteve 60 px e não houve rolagem horizontal.
- **Telas integradas:** Início do estabelecimento, Área de trabalho consolidada, entrada `/conta/estabelecimento` e seletor em todas as rotas `account.*`. Nas telas administrativas (Início da conta, Pessoas, Etiquetas, Cargos, Organização, Empresas, Estabelecimentos, Usuários, Auditoria e Central de Ajuda), o seletor só guarda a seleção da aba e o padrão; elas mantêm o escopo da conta.
- **Infraestrutura disponível e sem tela real:** grupo `/conta/empresa/{empresa}` (exercitado pelo fixture), opção `history`, `?para=modulo`, listagens operacionais com filtros e paginação no contexto, e formulários e modais com `~/`. Cada módulo da E07 em diante comprova o uso nos próprios testes. Nenhuma regra de vendas, estoque ou financeiro foi antecipada.
- **Pontos para a homologação de produto:** o atalho para a área de trabalho e o tratamento de `?para=` foram resolvidos nos ajustes finais abaixo. Contas com centenas de unidades continuam como risco registrado.

#### Ajustes finais de navegação (20/11/2026)

Os três ajustes foram autorizados depois da auditoria final e não ampliam a arquitetura da E06: não há rota, tabela, permissão nem serviço novo. A E05 não foi alterada. As correções de `pageWorkspace` e do contador foram preservadas.

- **Atalho para a área de trabalho:** antes, o seletor das telas da conta só guardava a seleção; nada levava à área de trabalho. Agora, nas telas sem área de trabalho (`consolidated` falso), o menu termina com **Abrir área de trabalho**. O atalho aplica a seleção marcada nesta aba, grava o padrão (exceto na inspeção) e navega para `/conta/estabelecimento?unidades=...`. Com Ctrl ou Command, abre outra aba sem mudar esta. As telas administrativas não recebem filtros.
- **Entrada e `?para=`:** `EstablishmentContextController::enter` passou a aceitar a seleção explícita da aba. `OrganizationContext::workspaceCodes` a revalida contra as unidades navegáveis (404 para unidade indisponível) e, sem ela, usa o padrão revalidado. `WorkspaceDestinationData` aceita como `para` só um caminho que corresponda a uma rota GET do grupo `/conta/estabelecimento/{estabelecimento}` com `organization:establishment`. Endereços externos, `..`, query string, rotas de escrita e destinos desconhecidos respondem 404. Resultado: com uma unidade, abre o módulo nela; com várias, `todos/{modulo}` se o módulo declara `consolidated`; senão, `todos?unidades=...&para=modulo`, onde a área de trabalho mostra **Escolha o estabelecimento** com um link por unidade. URLs consolidadas incompatíveis pedidas diretamente continuam respondendo 404. Trocar a seleção na tela de escolha passa de novo pela entrada com o mesmo destino.
- **Contrato de navegação:** `navigate` não resolvia `~/`, ao contrário do que dizia a seção 12.3. Agora `contextualUrl` é o único resolvedor e `navigate` o aplica com a página atual da aba. Como a resolução é idempotente, `ApiLink` e `useApiForm` continuam funcionando sem mudança, e `useBusinessListFilters('~/modulo')` passa a funcionar. `apiRequest` continua sem resolver `~/`, porque não conhece a página; as chamadas diretas usam `useContextUrl`. As seções 12.3 e 12.4 e a convenção foram corrigidas.
- **Testes:** `OrganizationContextTest` ganhou a entrada com seleção da aba, destinos compatíveis e incompatíveis, a tela de escolha, destinos arbitrários, a revogação e a inspeção (18 testes e 272 asserções). `tests/Frontend/organizationContext.test.ts` (`npm run test:frontend`, `node --test`, 9 testes) fixa o contrato de `contextualUrl`, a idempotência usada por `navigate`, `workspaceEntryUrl`, `workspaceUrl` e `pageWorkspace`. O harness `e06-client.php` alterna a entrada com seleção explícita e um destino inválido entre usuários e contas.
- **Resultados:** suíte SQLite com 193 testes e 2.026 asserções. Pint e PHPStan sem erros, além de `npm run check` e `npm run build` aprovados. `E05_CONTEXTUAL=1 E06_CONTEXT=1 e04-check.sh` com PostgreSQL 18 e FrankenPHP: com 1 worker, 420 verificações de isolamento da E06 (antes 300), 300 da E04 e 300 da E05, revogação na requisição seguinte e inspeção encerrada após a revogação; com 1 e 2 workers, 40 requisições simultâneas de duas abas.
- **Navegador:** Chrome headless no ambiente descartável `e04-stack.sh`. Foram verificados: atalho numa tela da conta com unidades de duas empresas, padrão salvo, voltar, avançar e recarregar, ausência do atalho na área de trabalho, aplicação na aba, atalho sem mudança de seleção, aba nova restaurando o padrão, duas abas independentes, destinos inválidos abertos diretamente e revogação com a seleção guardada na aba. Em 1440, 390 e 320 px, o menu ficou dentro da tela, o atalho visível e sem rolagem horizontal.
- **Limitações:** não há módulo operacional real. A tela de escolha e os destinos com `?para=` foram verificados nos testes de feature com o módulo-fixture, sem demonstração no navegador. As escritas em contexto explícito e a inspeção SaaS foram verificadas nos testes de feature e no harness, não no navegador.

#### E06.1 Diagnóstico

Não havia rota com contexto de empresa ou de unidade: todas as telas da conta são administrativas (nó `tenant`) e recebem a conta de `AccountContext`. O `ApiPage` já entregava `auth.permissions` por página; `apiRequest`, `ApiLink`, `ApiForm` e `useApiForm` recebiam URLs absolutas; o `ApiApplication` remontava a tela só quando o componente mudava. `EffectivePermissions` já carregava as atribuições do vínculo em uma consulta, base suficiente para o acesso ao contexto sem consulta nova. `EnforceAccountInspection` libera escritas em inspeção de edição só para rotas `account.*`, por isso os grupos novos usam esse prefixo de nome.

#### E06.2 a E06.4 Backend

- **`OrganizationContext`** (`App\Domains\Acessos\Application\Context`, `scoped`): modo (`tenant`, `company`, `establishment`, `consolidated`), conta, vínculo (`AuditContext::membershipIdFor`), autor real (`AuditContext::actorId`, que preserva o administrador SaaS na inspeção), empresa, unidade, `node()` e `scopeFor($user, $permissao)`. O descritor em modo de unidade só contém a unidade da URL e fica vazio sem a permissão da operação; no consolidado, é o `nodesFor` da permissão. Sem grupo de contexto, o modo é `tenant` e nada fictício é criado.
- **Acesso ao contexto:** `EffectivePermissions::navigableNodes` deriva da mesma consulta de atribuições os nós cobertos por alguma atribuição válida. Atribuição de estabelecimento não abre o contexto da empresa (herança descendente).
- **`ResolveOrganizationContext`** (alias `organization`): confere o usuário, a conta e o vínculo ativos (`AccountContext::requireId`, 403), resolve o código dentro do tenant numa consulta com a empresa (FK composta), exige cobertura (404 igual para inexistente, outro tenant ou sem atribuição) e a situação ativa (403 com orientação; `history` aceita leitura). `todos` só é aceito com `consolidated` e só para leitura. Guarda na sessão apenas a última unidade aberta, fora da inspeção, para o redirecionamento sem código. A abertura direta pelo navegador recebe só o HTML inicial; a validação acontece na requisição JSON da aplicação.
- **Rotas** (`routes/context.php`): `GET /conta/estabelecimento` (escolha automática, com `?para=modulo` validado) e `GET /conta/estabelecimento/{estabelecimento}` (início da unidade). O grupo `/conta/empresa/{empresa}` está implementado no middleware e exercitado pelo módulo-fixture; a primeira rota real de empresa fica com o módulo que a usar (E07). As rotas administrativas `/conta/...` não mudaram.
- **Contextos navegáveis:** `OrganizationContextQuery::navigable`, uma junção de `establishments` com `companies` filtrada pelo tenant, pela situação e pelos nós cobertos, ordenada por empresa, principal e nome. `ApiPage` entrega `organization_context` (modo, empresa, unidade, suporte ao consolidado e lista com duas ou mais unidades) apenas nas rotas `account.*`.
- **Central de Ajuda:** o artigo `estabelecimento-atual` aparece para quem tem alguma unidade navegável, sem consulta extra.

#### E06.5 e E06.6 Frontend

- Seletor `OrganizationSwitcher` (pasta de integração) com o `SelectControl` na `TopBarPrimary`, que recebeu um espaço `context`. O `SelectControl` ganhou título de grupo por opção, texto de espera e valor compacto, sem componente paralelo. Busca a partir de 9 opções, teclado (setas, Enter, Esc com retorno do foco), selos Principal e Matriz ou Filial separados, ícone de unidade e opção "Todos os autorizados" só em telas que declaram suporte. Até 400 px o gatilho mostra só o ícone, com 40 px; o nome da unidade continua no título e na trilha.
- Com uma unidade navegável, o seletor não aparece; `/conta/estabelecimento` leva direto a ela.
- `~/` em `ApiLink`, `ApiForm`, `useApiForm` e `useContextUrl` resolve o endereço no contexto da página que o declarou. A tela é remontada quando o contexto da URL muda, para que formulários e modais não levem dados da unidade anterior. Nenhum estado global de contexto foi criado.
- Erros 403 e 404 numa URL de contexto mostram a orientação e o link "Abrir um estabelecimento autorizado".
- Início do estabelecimento (`establishment-home.tsx`): `PreviewSection` com unidade, empresa e classificação; Ver cadastro abre o modal existente (`EstablishmentPreview`) e Editar leva ao cadastro, cada um conforme a permissão no nó; aviso de que a unidade vale só para a aba.

#### Arquivos

- **Criados:** `app/Domains/Acessos/Application/Context/OrganizationContext.php`, `app/Http/Middleware/ResolveOrganizationContext.php`, `app/Domains/Organizacao/Application/Queries/OrganizationContextQuery.php`, `app/Domains/Organizacao/Domain/DTOs/NavigableCompany.php`, `app/Domains/Organizacao/Domain/DTOs/NavigableEstablishment.php`, `app/Domains/Organizacao/UI/Controllers/EstablishmentContextController.php`, `routes/context.php`, `resources/js/app/saas/OrganizationSwitcher.tsx`, `resources/js/app/saas/OrganizationSwitcher.css`, `resources/js/app/saas/establishment-home.tsx`, `resources/js/utils/organizationContext.ts`, `resources/js/hooks/useContextUrl.ts`, `tests/Feature/OrganizationContextTest.php`, `tests/Performance/e06-fixtures.php`, `tests/Performance/e06-client.php`, `docs/ajuda/estabelecimento-atual.md`, `docs/tours/estabelecimento-atual.md`.
- **Alterados:** `EffectivePermissions.php`, `OrganizationCode.php` (`ROUTE_PATTERN`), `HelpCatalog.php`, `ApiPage.php`, `AppServiceProvider.php`, `bootstrap/app.php`, `routes/web.php`, `ApiApplication.tsx`, `App.tsx`, `guidance.ts`, `Organization.css`, `ApiLink.tsx`, `useApiForm.ts`, `SelectControl.tsx` e `.css`, `TopBarPrimary.tsx`, `Session.types.ts`, `tests/Performance/AccountRequestsTest.php`, `octane-routes.php`, `e04-check.sh`, `e04-stack.sh`, `docs/convencoes/contexto-organizacional.md`, `docs/convencoes/performance.md`, `docs/tours/organizacao.md`, `README.md` e este plano.
- **Banco:** nenhuma migration, tabela ou índice.

#### E06.7 Testes

`tests/Feature/OrganizationContextTest.php` registra, só no teste, um módulo de estoque por unidade e de produtos por empresa (cenários 17 a 20 e 24). São 14 testes que conferem os dados retornados e o registro alterado, não apenas o status:

| Itens da matriz pedida | Cobertura |
| --- | --- |
| 1 a 4, 17, 18 | Resolução de conta, empresa e unidade por código; mesmo código em outro tenant resolve só no próprio; códigos inválidos, `todos`, outra empresa e outro tenant respondem 404 sem dados, na leitura e na escrita |
| 5 a 8, 13, 14 | Seletor da Ana (gerente na Gartan Roupas e operadora em Janaúba da Gartan Express) com três unidades, agrupadas e ordenadas; unidade criada depois coberta pela empresa; administrador integral com 6 unidades; administrador local sem seletor e com redirecionamento à própria unidade |
| 9 a 12 | Cargo retirado, unidade inativa (403 com orientação, histórico declarado legível e escrita recusada), empresa inativa e vínculo suspenso, todos na requisição seguinte |
| 15, 16 | Inspeção de leitura com os contextos do representado, escrita recusada, preferência não gravada e encerramento com 401 após a revogação; inspeção de edição grava na unidade da URL com o administrador SaaS como autor e o vínculo representado |
| 19, 20, 25 | Consolidado restrito às unidades com a permissão; módulo sem suporte e escrita em `todos` recusados |
| 21 a 24 | Duas abas em empresas e unidades diferentes: leituras e escritas pertencem à URL; o modal da aba 1 não lê o registro da aba 2; a preferência da sessão só define o destino sem código |
| 26 | Requisições alternadas entre duas contas, com `forgetScopedInstances` entre elas |
| 27, 28 | Auditoria continua filtrada pelo RBAC dentro do contexto; artigo de ajuda disponível e sem `organization_context` fora das rotas da conta |
| 29 | Seletor com 5 e 50 unidades: duas consultas sobre `establishments as e` (código e lista), sem repetição e com o mesmo total de consultas |
| 30 | Acesso ao contexto não concede edição; suíte da E05 inalterada |

Resultados no contêiner descartável `prospera-dev`, sem rede e com o código atual:

- `php artisan test`: **188 testes e 1.917 asserções** (E05: 174 e 1.731). Executado com `MAIL_MAILER=array`, porque o ambiente Docker define `MAIL_MAILER=smtp`, que prevalece sobre o `phpunit.xml` e derruba um teste de e-mail da E05 mesmo sem a E06.
- `tests/Performance/AccountRequestsTest.php`: verde, com o limite das telas da conta ajustado de 9 para 10 pela consulta do seletor e uma asserção nova de no máximo 1 consulta de unidades por página.
- PHPStan sem erros; Pint sem pendências nos arquivos da etapa; `npm run check` (arquitetura e TypeScript) e `npm run build` aprovados; `permissions:sync` (81 permissões, nenhuma criada), `audit:check` (12 modelos) e `organization:check` aprovados em SQLite descartável.

#### PostgreSQL e FrankenPHP

`E05_CONTEXTUAL=1 E06_CONTEXT=1 bash tests/Performance/e04-check.sh <cópia> 1|2`, com FrankenPHP 1.13.6, PHP 8.5.13, Laravel 13.43.0, Octane 2.25.0 e PostgreSQL 18.7 descartável, dados sintéticos e `ANALYZE`. A cópia anterior (`3b27a7e`) passou pelo mesmo runner como linha de base.

- **Um worker:** 300 requisições alternadas da E06 entre a Ana (Gartan, 3 unidades), um operador de uma unidade e o administrador de uma conta de unidade única, intercaladas com 404 de outra empresa e de outro tenant. Cada resposta trouxe a unidade pedida e exatamente o conjunto de unidades esperado. As provas de 300 requisições da E04 e da E05 continuaram verdes.
- **Revogação no mesmo worker:** a retirada da atribuição de Janaúba deu 404 na requisição seguinte, e a unidade saiu do seletor.
- **Inspeção:** o inspetor viu os contextos da Ana, recebeu 404 fora deles e 401 depois da revogação da autoridade SaaS, com a sessão anônima; a sessão própria da Ana continuou com acesso no mesmo worker.
- **Concorrência:** 40 requisições simultâneas de duas abas da mesma sessão em unidades diferentes, com 1 e com 2 workers; cada resposta correspondeu à própria URL. As disputas da E04 e da E05 com 2 workers mantiveram os resultados homologados.

| Tela (administrador integral) | Consultas antes / depois | HTTP 1 worker antes / depois (ms) |
| --- | --- | --- |
| `/conta`, 1x1 | 8 / 9 | 5,21 / 5,65 |
| `/conta`, 50 unidades | 8 / 9 | 5,32 / 6,19 |
| Empresas, 50 unidades | 8 / 9 | 5,79 / 6,70 |
| Estabelecimentos, 50 unidades | 10 / 11 | 9,52 / 9,33 |
| Central de Ajuda | 5 / 5 | 7,24 / 7,54 |

Listagem de Estabelecimentos nos perfis da E05, 1 worker: 1 atribuição 6,38 / 6,96 ms; 5 atribuições 7,58 / 7,33 ms; 30 atribuições 8,38 / 10,71 ms; integral 9,44 / 10,40 ms, com 10 / 11 consultas. Com 2 workers, depois: 6,76; 7,20; 10,01 e 10,34 ms. A resolução continua em 1 consulta de atribuições e 1 de permissões, sem consultas repetidas.

| Perfil (início da unidade) | Unidades no seletor | Consultas | HTTP 1 / 2 workers (ms) | Resolução / seletor (ms) | Memória do contexto (bytes) |
| --- | --- | --- | --- | --- | --- |
| Integral, 1 empresa e 1 unidade | 1 (oculto) | 8 | 6,95 / 5,50 | 2,38 / 0,51 | 31.904 |
| Integral, 1 empresa e 3 unidades | 3 | 8 | 6,88 / 5,62 | 2,53 / 0,58 | 32.856 |
| Integral, 3 empresas e 10 unidades | 10 | 8 | 6,58 / 6,11 | 2,54 / 0,61 | 36.376 |
| Integral, 5 empresas e 50 unidades | 50 | 8 | 6,50 / 6,21 | 2,45 / 0,70 | 56.448 |
| Uma unidade | 1 (oculto) | 8 | 6,64 / 5,46 | 2,46 / 0,64 | 14.664 |
| Várias unidades | 5 | 8 | 7,01 / 5,44 | 2,62 / 0,64 | 16.592 |
| Empresa inteira | 10 | 8 | 6,47 / 5,73 | 2,42 / 0,59 | 20.352 |
| Vários cargos | 13 | 8 | 6,78 / 5,88 | 2,54 / 0,70 | 23.104 |

- **Leitura:** a resolução inclui as 2 consultas de permissões da requisição e a consulta do código; o seletor é 1 consulta com linhas iguais às unidades navegáveis. O pico de memória de `/conta` com 50 unidades foi de 116 KB para 180 KB. A troca de unidade é uma navegação comum: mediana de 6,66 ms com 1 worker e 5,65 ms com 2.
- **Planos:** com 80 unidades na base, o planejador escolheu varredura sequencial com hash join para a lista (0,15 a 0,23 ms) e para o código (0,02 ms). Nenhum índice foi criado.

#### Validação visual

Chrome headless contra o ambiente descartável (`STACK_PREFIX=prospera-e06-visual E06_CONTEXT=1 bash tests/Performance/e04-stack.sh up 8096`), dados sintéticos, em 1440, 390 e 320 px. Capturas e roteiro em `/tmp/prospera-e06/`.

- Início da unidade e seletor aberto nas três larguras, com grupos Gartan Express e Gartan Roupas, selos e unidade atual destacada; sem rolagem horizontal e com a topbar em 60 px.
- Teclado: setas, Enter e Esc com o foco de volta ao gatilho.
- Duas abas: a aba 2 trocou para Janaúba da Gartan Express e o modal Ver cadastro da aba 1 mostrou Guanambi da Gartan Roupas.
- Unidade sem acesso aberta diretamente: orientação e link para uma unidade autorizada.
- Conta de 50 unidades: busca por "caetite" com 11 opções agrupadas.
- Conta de unidade única em 1440 e 320 px: sem seletor e com redirecionamento direto.
- Tour em 1440 e 390 px: Início do estabelecimento, trilha, ações, Escolher o estabelecimento, Unidade atual e Uma unidade por aba.
- Tela administrativa (Organização) com o seletor sem unidade atual ("Selecionar estabelecimento").
- Correções feitas durante a validação: grid do resumo da unidade, rótulos quebrados no celular e topbar em duas linhas a 320 px.

#### Desvios e decisões para revisão

- **Início do estabelecimento:** o plano não listava uma tela; ela foi criada como destino do seletor e base das futuras rotas da unidade. Mostra só dados já presentes no seletor e ações condicionadas às permissões, sem permissão própria: o acesso é a cobertura do nó.
- **Empresa inativa ou unidade inativa:** 403 com orientação para quem tem atribuição, em vez de 404, porque a existência já é conhecida por quem tem acesso. Quem não tem atribuição continua recebendo 404.
- **Consolidado somente leitura:** escritas em `todos` respondem 403.
- **Seletor em todas as telas da conta:** custa 1 consulta por página, como prevê a meta 15.3.1. Com uma unidade, a lista não é enviada.
- **Abertura direta pelo navegador:** devolve o HTML inicial sem dados, para que a orientação apareça na aplicação. As respostas JSON mantêm 401, 403 e 404.
- **Preferência após o login:** a sessão é invalidada no logout, por isso a última unidade só vale dentro da sessão. O destino do login não mudou (`/dashboard`, que leva ao Início da conta).
- **Primeiras rotas de empresa:** a infraestrutura de `/conta/empresa/{empresa}` está pronta e testada com o fixture; nenhuma tela real de empresa foi criada, para não antecipar a E07.

#### Riscos e pendências

- Rever o desempenho do seletor em contas com centenas de unidades: a lista vai inteira à página, com busca no cliente.
- Módulos operacionais futuros precisam filtrar as escritas pelo nó da URL; o contrato e o fixture servem de modelo, mas cada módulo comprova isso nos próprios testes.
- `make test` no Docker herda `MAIL_MAILER=smtp`; ajustar o ambiente de testes é uma tarefa separada da E06.
- Durante a execução, o container `app` do Compose local foi recriado em modo de produção por outra sessão. A E06 não alterou esse ambiente e executou testes e medições em containers descartáveis.
- A implantação continua sujeita às pendências da E05 (e-mail externo, backup, migrations e reinício controlado dos workers); a E06 não tem migration, mas o reinício dos workers é necessário para carregar o código novo.

#### E07, homologação final (21/11/2026)

- **Decisão.** Homologados o cadastro central de Pessoas, as relações de Cliente e Fornecedor por empresa, o compartilhamento entre filiais, a matriz de autorização dos dados compartilhados, as etiquetas derivadas, a correção do recorte dos filtros, a Área de Trabalho e a auditoria empresarial. A integridade referencial, a concorrência e a remoção das sincronizações legadas foram aprovadas.
- **Privacidade.** A descoberta comercial usa `POST /conta/empresa/{empresa}/{clientes|fornecedores}/pessoa` e lê `documento` exclusivamente do corpo. O cliente usa o mecanismo de sessão e CSRF da API existente. A autorização `create` do papel na empresa precede a validação e a consulta. A busca é restrita à conta, retorna apenas identificação mínima e situação da relação local e não grava cadastro, vínculo ou evento de auditoria. As rotas consultivas mantêm o acesso em inspeção somente leitura dentro das permissões do representado.
- **Respostas e logs.** As respostas da descoberta usam `Cache-Control: private, no-store`. Erros de validação retornam JSON e evitam guardar o documento como entrada anterior na sessão. Uma falha de consulta retorna 503 com mensagem genérica. O log de erro registra conta, empresa e papel, sem a exceção SQL que pode expor os parâmetros. Em Chromium e FrankenPHP descartáveis, a busca dos dois papéis enviou JSON sem query string, e a tentativa sem token foi recusada com 419. O documento sintético ficou ausente dos logs do aplicativo e do PostgreSQL no ensaio. A revisão de parâmetros de logging e captura de corpo em infraestrutura externa permanece fora da validação local.
- **Regressão aplicável.** A auditoria final anterior registrou 217 testes e 2.387 asserções, com 39 testes comerciais e 566 asserções. Após a correção da descoberta, a suíte completa passou com 218 testes e 2.409 asserções, e o recorte comercial com 40 testes e 588 asserções. Passaram o teste de frontend, a checagem de arquitetura e tipos, build, PHPStan, Pint, `permissions:sync`, `audit:check` e `organization:check`.
- **Performance.** Mantidas as medições homologadas de 3.600 requisições após 180 de aquecimento, com 2.800 respostas 200, 800 respostas 404 esperadas, zero erros inesperados e zero reinícios do worker. Mediana HTTP de 22,91 ms e p95 de 58,24 ms. O uso inicial de memória PHP ficou estável. O ensaio foi sequencial com um worker e não representa todos os perfis de concorrência da implantação.
- **Visual.** Homologada a validação do tema disponível, incluindo as cinco larguras registradas na auditoria final. A ausência de tema escuro nativo foi aceita. O fluxo atualizado de descoberta foi confirmado a 390 px em Clientes e Fornecedores.
- **Git e implantação.** HEAD `34ed162`, branch `feat/filtros-calendario-titulos`. O registro de encerramento da E06 foi preservado. A validação usou contêineres e dados descartáveis, sem alterações em bases oficiais, commits ou push. A implantação continua sujeita à conferência dos dados legados, backup e reinício controlado dos workers. E08 a E10 seguem pendentes.

#### E07, ajustes posteriores à homologação

- Clientes e Fornecedores passaram a ter edição direta na listagem. O formulário reutiliza a ficha comercial, fixa a empresa e o documento e mantém a situação da relação. A inativação segue disponível na ficha de Pessoas, conforme autorização.
- As permissões `account.customers.update` e `account.suppliers.update` avaliam o escopo da empresa. Alterar dados centrais continua exigindo a autoridade de Pessoas. Etiquetas atualizadas são auditadas na empresa da relação, sem gravar alterações efetivamente iguais.

<details>
<summary>Inventário da árvore de trabalho da E07 na homologação</summary>

**Arquivos rastreados modificados (40).** Inclui o ajuste local preexistente em `.dockerignore`, preservado.

- `.dockerignore`
- `app/Domains/Acessos/Application/Context/EffectivePermissions.php`
- `app/Domains/Acessos/Domain/DTOs/AuthorizedNodes.php`
- `app/Domains/Clientes/Application/Actions/SaveCustomerAction.php`
- `app/Domains/Clientes/Domain/Models/Customer.php`
- `app/Domains/Fornecedores/Application/Actions/SaveSupplierAction.php`
- `app/Domains/Fornecedores/Domain/Models/Supplier.php`
- `app/Domains/Organizacao/Application/Queries/CompanyDirectoryQuery.php`
- `app/Domains/Pessoas/Application/Actions/SavePersonAction.php`
- `app/Domains/Pessoas/Application/Data/SavePersonData.php`
- `app/Domains/Pessoas/Application/Queries/PeopleDirectoryQuery.php`
- `app/Domains/Pessoas/Application/Queries/PersonAccessQuery.php`
- `app/Domains/Pessoas/Domain/Models/Person.php`
- `app/Domains/Pessoas/UI/Controllers/DirectoryController.php`
- `app/Http/Middleware/EnforceAccountInspection.php`
- `bootstrap/app.php`
- `docs/ajuda/clientes.md`
- `docs/ajuda/fornecedores.md`
- `docs/ajuda/pessoas.md`
- `docs/convencoes/pessoas-e-contatos.md`
- `docs/plano-de-acoes/001-arquitetura-organizacional-prospera.md`
- `docs/tours/clientes.md`
- `docs/tours/fornecedores.md`
- `docs/tours/pessoas.md`
- `package.json`
- `resources/js/app/ApiApplication.tsx`
- `resources/js/app/guidance.ts`
- `resources/js/app/saas/Directory.css`
- `resources/js/app/saas/Directory.types.ts`
- `resources/js/app/saas/OrganizationSwitcher.tsx`
- `resources/js/app/saas/PersonPreview.tsx`
- `resources/js/app/saas/SaasLayout.tsx`
- `resources/js/app/saas/accountNavigation.ts`
- `resources/js/app/saas/people.tsx`
- `resources/js/app/saas/person-form.tsx`
- `resources/js/utils/organizationContext.ts`
- `routes/context.php`
- `tests/Feature/CustomerDirectoryTest.php`
- `tests/Feature/SupplierDirectoryTest.php`
- `tests/Frontend/organizationContext.test.ts`

**Arquivos novos ainda não rastreados (19).**

- `app/Domains/Pessoas/Application/Actions/SaveCommercialRelationsAction.php`
- `app/Domains/Pessoas/Application/Actions/SetCommercialRelationAction.php`
- `app/Domains/Pessoas/Application/Data/CommercialRelationFiltersData.php`
- `app/Domains/Pessoas/Application/Data/CommercialRelationLinkData.php`
- `app/Domains/Pessoas/Application/Data/CommercialRelationsData.php`
- `app/Domains/Pessoas/Application/Queries/CommercialRelationDirectoryQuery.php`
- `app/Domains/Pessoas/Application/Queries/PersonCommercialRelationsQuery.php`
- `app/Domains/Pessoas/Domain/DTOs/PersonEditAuthority.php`
- `app/Domains/Pessoas/Domain/Enums/CommercialRole.php`
- `app/Domains/Pessoas/UI/Controllers/CommercialRelationController.php`
- `database/migrations/2026_11_21_000001_add_company_to_commercial_relations.php`
- `resources/js/app/saas/CommercialRelationFields.tsx`
- `resources/js/app/saas/CommercialRelations.css`
- `resources/js/app/saas/CommercialRelations.tsx`
- `resources/js/app/saas/commercial-relations.tsx`
- `tests/Feature/CommercialRelationAccessTest.php`
- `tests/Feature/CommercialRelationIntegrityTest.php`
- `tests/Feature/CommercialRelationWorkspaceTest.php`
- `tests/Feature/Concerns/BuildsCommercialScenario.php`

</details>

### Registro de execução da E08 em 21/11/2026

**Checkpoint.** Branch `feat/filtros-calendario-titulos`, HEAD `e0d3cce` (E07 publicada e permite editar clientes e fornecedores), árvore limpa no início. A E07 estava integralmente versionada em `41628d1` e `e0d3cce`, posteriores ao `34ed162` do último relatório. Esse HEAD é o checkpoint anterior à E08. Linha de base da suíte nesse ponto: 225 testes e 2.526 asserções. Sem commits, push nem alteração de bases oficiais.

#### E08.1 Diagnóstico

- **Infraestrutura existente, preservada.** `audit_records` com autor, conta, origem (`context`), representado, tipo, id e nome do registro, evento, alterações em JSON, `operation_id`, `membership_id`, `company_id` e `establishment_id` (E04 e E05). `Auditable`, `AuditsChanges` e `AuditObserver` gravam criação, alteração e exclusão de 12 models, com antes e depois só dos campos alterados, máscara de segredos e descarte de alterações vazias. `AuditLog::transaction()` agrupa a operação; `AuditLog::record()` grava eventos sem model; `recordAuditFieldChange()` registra relacionamentos. `AuditContext` (`scoped`) é aberto por `EnforceAudit`, que roda cada escrita numa transação e desfaz o histórico em respostas 4xx e 5xx. Consulta por `AuditQuery`, `AuditController`, `AuditFilterData` e `AuditValueFormatter`, com a condição da 11.2 aplicada no SQL desde a E05. Interface `audit.tsx` e `AuditRecordDetails.tsx`, tour e ajuda. `audit:check` cobre models, colunas e ids organizacionais.
- **Caminhos que produzem eventos.** Observer dos models; `recordAuditFieldChange()` (etiquetas de Pessoas e das relações, permissões dos cargos); `AuditLog::record()` direto em inspeção (início e fim), envio de link de acesso, chaves de acesso (`EnforceAudit`), entrada e saída (`AppServiceProvider`) e sincronização do catálogo.
- **Lacunas encontradas.**
  1. PD-02 não aplicada: `/saas/auditoria` lia o histórico de negócio de todas as contas (A12).
  2. `access_catalog_events` ainda recebia gravações em paralelo, e as linhas legadas posteriores à importação original não estavam conciliadas.
  3. Sem integridade organizacional no banco: empresa e unidade sem FK, e evento de unidade sem empresa possível.
  4. Inspeção sem identificação histórica: só o id do representado, sem nome da época, sem referência da inspeção e sem modo por evento.
  5. Encerramento automático da inspeção (autoridade revogada) sem evento.
  6. Filtro de período com `whereDate`, que converte cada linha e impede o uso do índice.
  7. Opções de filtro com três varreduras completas do escopo por requisição; índices `(actor_id, created_at)` e `(entity_type, event)` sem a conta; nenhum índice para auditores de empresa e de unidade.
  8. `Schema::hasTable()` consultado a cada evento gravado fora do console.
  9. Histórico alterável pelo próprio Model fora da operação que o gravou.
  10. Falha de gravação da auditoria levava ao log o SQL com os valores alterados.
  11. Interface sem empresa, unidade, nome do representado e origem legível; conta exibida como `#id`; sem filtros organizacionais.
- **Sem duplicidade encontrada** nos eventos de uma mesma operação: o agrupamento por `operation_id` e o descarte de alterações vazias já funcionavam.

#### E08.2 Contrato e integridade

- **Migration `2026_11_21_000003_consolidate_contextual_audit` (RP).**
  - Recusa aplicar se houver evento com unidade sem empresa, empresa sem conta, conta inexistente, empresa de outra conta ou unidade de outra empresa. Não corrige o histórico por suposição.
  - Acrescenta `represented_user_name` e `inspection_id`, nulos no histórico anterior.
  - FKs `restrict`, sem cascata: `account_id` para `clients`; `(company_id, account_id)` para `companies`; `(establishment_id, company_id, account_id)` para `establishments`, com o índice único `(id, company_id, account_id)` em `establishments`. No PostgreSQL, CHECKs fecham os casos que a FK composta não verifica quando uma coluna é nula: unidade exige empresa e empresa exige conta.
  - Importa as linhas legadas sem evento central equivalente.
  - A reversão remove as colunas novas, as constraints e as linhas importadas por ela, preservando o restante.
- **`AuditLog::record`.** Valida o nó do evento (unidade exige empresa, empresa exige conta), grava o nome do representado e a inspeção a partir do `AuditContext` e só consulta o esquema no console. Uma falha de gravação vira `AuditWriteFailed`, com tipo, operação e SQLSTATE, sem SQL nem valores, e desfaz a operação de negócio.
- **Inspeção.** `AccountInspectionDTO` e a sessão ganham `id` (UUID gerado no início). Início, escritas e encerramento gravam o mesmo `inspection_id`. O encerramento registra o motivo: Solicitado pelo administrador ou Automático, quando `EnforceAccountInspection` encerra a sessão por perda de autoridade. O autor real continua sendo o administrador SaaS. O mecanismo de inspeção não foi redesenhado.
- **Imutabilidade.** `AuditRecord` recusa alteração e exclusão fora da operação que gravou o evento. Não existe rota, Action, permissão ou comando de edição ou exclusão de eventos (verificado em teste sobre o roteamento).
- **Legado.** `SyncPermissionsAction` deixou de gravar em `access_catalog_events`. `directory_audit_events` e `access_catalog_events` permanecem no banco até aprovação da remoção (11.4).

#### E08.3 Cobertura dos domínios

| Operação | Evento | Nó gravado | Autor | Dados registrados | Consulta | Cobertura automatizada |
| --- | --- | --- | --- | --- | --- | --- |
| Cadastro, situação e plano da conta (plataforma) | `account` criado ou alterado | conta | administrador SaaS (`platform`) | nome, e-mail, tipo, documento mascarado, plano, situação, contatos e endereço | conta (T) e plataforma | `SaasPlatformTest`, `ContextualAuditTest` |
| Dados da conta (tenant) | `account` alterado | conta | operador | mesmos campos | conta (T) e plataforma | `OrganizationTest` |
| Empresa: cadastro, edição, ativação, inativação | `company` | empresa | operador ou sistema | identificação fiscal, regime, situação | T ou empresa | `ContextualAuditTest`, `OrganizationTest` |
| Estabelecimento: cadastro, edição, situação, promoção | `establishment` | empresa e unidade | operador ou sistema | dados da unidade, CNPJ, situação, principal | T, empresa ou unidade | `ContextualAuditTest`, `OrganizationTest` |
| Pessoa: cadastro, identificação, contatos, endereço, etiquetas | `person` | conta | operador | campos alterados; documento mascarado; etiquetas por nome | T | `ContextualAuditTest`, `PeopleDirectoryTest`, `PersonAdditionalContactsTest` |
| Etiqueta: cadastro, edição, situação, exclusão | `tag` | conta | operador | nome, cor, módulo, situação | T | `PeopleDirectoryTest`, `ContextualAuditTest` |
| Cliente e fornecedor: vínculo, Pessoa existente, edição, etiquetas, inativação, reativação | `customer`, `supplier` | empresa | operador | situação e etiquetas da relação | T ou empresa | `ContextualAuditTest`, `CustomerDirectoryTest`, `SupplierDirectoryTest`, `CommercialRelation*Test` |
| Cargo e permissões | `role` (`permission_ids`) | conta | operador ou sistema | nome, permissões por nome | T | `AccessControlTest`, `ContextualAuditTest` |
| Disponibilidade restrita do cargo | `role-availability` | empresa e, quando houver, unidade da restrição | operador | cargo, empresa, unidade | T ou o nó restrito | `ContextualAuditTest`, `ContextualAccessTest` |
| Atribuição concedida ou retirada | `role-assignment` | nó do escopo | operador | cargo, escopo | T ou o nó | `ContextualAuditTest`, `ContextualAccessTest` |
| Vínculo: criação, suspensão, reativação | `account-membership` | conta | operador | situação, pessoa | T | `ContextualAccessTest` |
| Envio de link de redefinição e de convite | `password-reset-requested`, `invitation-requested` | conta | operador | nenhum valor (sem token) | T | `ContextualAuditTest`, `ContextualAccessTest` |
| Identidade: nome, e-mail, senha, segundo fator, foto, acesso, chaves | `user` | conta do vínculo ou nulo | a própria pessoa ou a plataforma | segredos como Configurado ou Atualizado | T ou plataforma | `AuditAndGuidanceTest` |
| Entrada e saída | `login`, `logout` | conta do vínculo ou nulo | a própria pessoa | nenhum valor | T ou plataforma | `AuditAndGuidanceTest` |
| Inspeção iniciada e encerrada (manual ou automática) | `inspection-started`, `inspection-ended` | conta | administrador SaaS, com representado | modo, representado, motivo do encerramento | T e plataforma | `ContextualAuditTest`, `AuditAndGuidanceTest` |
| Escrita durante inspeção | evento do recurso | nó do recurso | administrador SaaS, com representado e `inspection_id` | campos do recurso | conforme o nó; não aparece na plataforma | `ContextualAuditTest`, harness FrankenPHP |
| Sincronização do catálogo | `catalog-synchronized` e cargos padrão | nulo e conta | sistema | permissões alteradas | plataforma; cargos em T | `AccessControlTest`, `ContextualAuditTest` |

- **Fora da auditoria de alteração:** leituras, buscas, descoberta de Pessoa por documento, navegação, abertura de modal, preferências de colunas e tentativas negadas. Não há política aprovada de eventos de segurança para negações.
- **Disponibilidade restrita.** A tabela da 11.1 classificava a disponibilidade como evento da conta. Desde a E05, a linha de `role_availability` carrega a empresa e a unidade da restrição, e o `audit:check` exige que o evento as informe. A E08 mantém esse nó e registra a decisão abaixo para homologação.

#### E08.4 Autorização

- A condição da 11.2 continua no SQL (`AuditQuery::scoped`) e alimenta listagem, contagem, paginação, busca, opções e detalhe. O detalhe aplica também `account.audit.view` e responde 404 fora do escopo; os dados técnicos exigem `account.audit.technical` sobre o nó.
- Novos filtros (`company`, `establishment`, `level`, `origin`) só restringem o conjunto autorizado. As opções de empresa e unidade vêm dos nós autorizados, numa consulta. O nível Conta só é oferecido a quem tem escopo tenant. A unidade da Área de Trabalho não altera a auditoria.
- PD-02 aplicada em `/saas/auditoria`, com filtros organizacionais proibidos na plataforma.
- Sem bypass: o administrador SaaS lê eventos de negócio de uma conta apenas pela auditoria da conta, durante uma inspeção autorizada e com a autoridade do representado.

#### E08.5 Privacidade

- Segredos, tokens e imagens continuam mascarados no observer. A descoberta por documento segue por POST sem evento. O envio de link grava só o fato, sem token nem e-mail. Os dados técnicos têm apenas identificadores.
- E-mail, telefone e endereço de Pessoas aparecem como valores para quem audita o nó da conta. Quem audita uma empresa vê a relação comercial, não o cadastro central (limitação aceita na 11.1).
- O log da inspeção leva só identificadores (agora com a referência da inspeção). A falha de gravação não leva SQL nem valores ao log nem à resposta (teste com `Log::spy`).

#### E08.6 Interface

- `audit.tsx`: filtros Origem, Nível, Empresa e Estabelecimento com o `SelectControl` existente, ocultos quando não há mais de uma opção. Escolher uma empresa limita as unidades oferecidas. A nova coluna Onde (`resources/js/utils/auditPlace.ts`) mostra a unidade com a empresa, a empresa ou a conta. Quem fez mostra a marca roxa Inspeção (`Label`) e o representado.
- `AuditRecordDetails.tsx`: seções Operação, Autoria e Contexto organizacional com o `PreviewSection` existente. Autor real e Usuário representado aparecem separados, com Modo da inspeção. Não se aplica indica evento da conta, e Não registrado neste evento indica histórico anterior sem o dado. Tabela de campos e Log técnico preservados.
- Tour: novo passo `audit-modal-context` na sessão de demonstração do modal; textos de filtros e tabela revisados. Ajuda `docs/ajuda/auditoria.md` e roteiro `docs/tours/auditoria.md` atualizados.
- Ajustes visuais: a marca Inspeção fica na segunda linha, para não ser cortada pela reticência. No mobile, os rótulos longos da ficha quebram entre palavras, com coluna limitada a 40%, só no escopo da auditoria.

#### E08.7 Performance

PostgreSQL 18.7 e FrankenPHP 1.14.0 descartáveis, um worker. Eventos sintéticos: 40% de conta, 30% de empresa e 30% de unidade, 2% em inspeção, 200 autores, dois anos de histórico. A conta Cenário 50 (5 empresas, 50 unidades) concentra 68% dos eventos. O mesmo harness (`tests/Performance/e08-check.sh`) mediu o HEAD `e0d3cce` (antes) e a árvore da E08 (depois). Cada caso tem 10 amostras após aquecimento; tempos em milissegundos. As contagens de cada perfil foram conferidas contra a condição normativa calculada fora da aplicação.

| Volume | Perfil | Caso | Eventos visíveis | HTTP mediana antes | HTTP mediana depois | p95 depois | Banco depois | Consultas antes / depois |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 10000 | integral | lista | 6890 | 20,7 | 18,7 | 20,5 | 8,1 | 13 / 15 |
| 10000 | integral | detalhe | 6890 | 5,3 | 5,2 | 7,8 | 2,1 | 7 / 8/9 |
| 10000 | empresa | lista | 839 | 19,8 | 16,0 | 17,4 | 6,0 | 13/14 / 15 |
| 10000 | empresa | detalhe | 839 | 5,8 | 5,3 | 7,6 | 2,2 | 7 / 9 |
| 10000 | estabelecimento | lista | 56 | 17,2 | 11,5 | 14,9 | 3,7 | 12 / 14 |
| 10000 | estabelecimento | detalhe | 56 | 5,2 | 5,9 | 8,2 | 2,4 | 7 / 10 |
| 10000 | atribuicoes-1 | lista | 31 | 15,8 | 11,5 | 14,8 | 3,7 | 12 / 14 |
| 10000 | atribuicoes-5 | lista | 206 | 19,7 | 14,3 | 17,2 | 5,0 | 13 / 15 |
| 10000 | atribuicoes-30 | lista | 1282 | 19,4 | 15,9 | 19,6 | 5,7 | 13/14 / 15/16 |
| 100000 | integral | lista | 67707 | 80,2 | 25,5 | 31,1 | 16,2 | 13 / 15 |
| 100000 | integral | busca | 67707 | 156,8 | 93,8 | 98,1 | 82,9 | 13 / 15 |
| 100000 | integral | periodo | 67707 | 89,9 | 18,5 | 20,5 | 8,5 | 13/14 / 15 |
| 100000 | integral | autor | 67707 | 72,1 | 17,4 | 19,8 | 7,2 | 13 / 15 |
| 100000 | integral | pagina-profunda | 67707 | 84,4 | 29,6 | 32,9 | 18,6 | 13 / 15 |
| 100000 | integral | empresa | 67707 | novo | 18,9 | 20,7 | 8,7 | novo / 15 |
| 100000 | integral | estabelecimento | 67707 | novo | 16,8 | 19,1 | 7,2 | novo / 15 |
| 100000 | integral | detalhe | 67707 | 5,7 | 5,4 | 7,2 | 2,2 | 7/8 / 8/9 |
| 100000 | empresa | lista | 8134 | 89,0 | 20,3 | 22,8 | 9,8 | 13 / 15 |
| 100000 | empresa | busca | 8134 | 114,1 | 34,5 | 36,0 | 22,9 | 13/14 / 15 |
| 100000 | empresa | periodo | 8134 | 105,7 | 19,4 | 23,3 | 8,5 | 13 / 15 |
| 100000 | empresa | autor | 8134 | 75,8 | 21,4 | 23,0 | 10,0 | 13/14 / 15 |
| 100000 | empresa | pagina-profunda | 8134 | 105,1 | 22,5 | 24,9 | 14,8 | 13 / 13 |
| 100000 | empresa | empresa | 8134 | novo | 16,0 | 17,0 | 8,3 | novo / 12 |
| 100000 | empresa | estabelecimento | 8134 | novo | 16,2 | 17,8 | 8,4 | novo / 12 |
| 100000 | empresa | detalhe | 8134 | 5,7 | 6,0 | 8,1 | 2,4 | 7 / 9/10 |
| 100000 | estabelecimento | lista | 542 | 80,8 | 13,2 | 16,9 | 4,9 | 12 / 14 |
| 100000 | estabelecimento | busca | 542 | 93,8 | 13,1 | 15,4 | 6,5 | 12 / 14 |
| 100000 | estabelecimento | periodo | 542 | 94,0 | 14,1 | 18,3 | 5,1 | 12 / 14 |
| 100000 | estabelecimento | autor | 542 | 62,9 | 11,9 | 13,3 | 4,8 | 12 / 14 |
| 100000 | estabelecimento | pagina-profunda | 542 | 90,5 | 9,3 | 10,9 | 4,1 | 12 / 12 |
| 100000 | estabelecimento | empresa | 542 | novo | 14,5 | 15,8 | 5,7 | novo / 14 |
| 100000 | estabelecimento | estabelecimento | 542 | novo | 14,2 | 15,1 | 5,0 | novo / 14 |
| 100000 | estabelecimento | detalhe | 542 | 5,5 | 5,5 | 6,4 | 2,3 | 7 / 10 |
| 100000 | atribuicoes-1 | lista | 274 | 68,8 | 11,8 | 14,0 | 4,1 | 12/13 / 14/15 |
| 100000 | atribuicoes-1 | busca | 274 | 76,0 | 10,1 | 11,9 | 5,0 | 12/13 / 14 |
| 100000 | atribuicoes-1 | periodo | 274 | 78,4 | 11,2 | 12,3 | 4,2 | 12 / 14 |
| 100000 | atribuicoes-1 | autor | 274 | 51,4 | 8,8 | 11,1 | 3,7 | 11 / 11 |
| 100000 | atribuicoes-1 | pagina-profunda | 274 | 76,6 | 8,8 | 10,2 | 3,8 | 12 / 12 |
| 100000 | atribuicoes-1 | empresa | 274 | novo | 8,5 | 9,1 | 3,8 | novo / 11 |
| 100000 | atribuicoes-1 | estabelecimento | 274 | novo | 8,3 | 9,3 | 3,5 | novo / 11 |
| 100000 | atribuicoes-5 | lista | 1908 | 73,8 | 16,9 | 20,2 | 7,7 | 13 / 15 |
| 100000 | atribuicoes-5 | busca | 1908 | 202,6 | 19,2 | 23,7 | 10,2 | 13 / 15 |
| 100000 | atribuicoes-5 | periodo | 1908 | 90,6 | 17,0 | 20,6 | 6,8 | 13 / 15/16 |
| 100000 | atribuicoes-5 | autor | 1908 | 60,5 | 17,2 | 18,9 | 7,6 | 13 / 15 |
| 100000 | atribuicoes-5 | pagina-profunda | 1908 | 83,5 | 12,8 | 15,0 | 6,8 | 13 / 13/14 |
| 100000 | atribuicoes-5 | empresa | 1908 | novo | 12,9 | 15,2 | 6,9 | novo / 12 |
| 100000 | atribuicoes-5 | estabelecimento | 1908 | novo | 11,9 | 12,9 | 6,3 | novo / 12 |
| 100000 | atribuicoes-30 | lista | 12228 | 74,6 | 24,6 | 29,2 | 13,8 | 13 / 15 |
| 100000 | atribuicoes-30 | busca | 12228 | 106,6 | 54,1 | 59,8 | 42,1 | 13 / 15/16 |
| 100000 | atribuicoes-30 | periodo | 12228 | 87,8 | 25,7 | 28,4 | 13,2 | 13 / 15/16 |
| 100000 | atribuicoes-30 | autor | 12228 | 63,1 | 24,2 | 26,0 | 12,8 | 13/14 / 15/16 |
| 100000 | atribuicoes-30 | pagina-profunda | 12228 | 91,3 | 29,0 | 34,0 | 20,5 | 13 / 13/14 |
| 100000 | atribuicoes-30 | empresa | 12228 | novo | 20,4 | 23,6 | 12,2 | novo / 12 |
| 100000 | atribuicoes-30 | estabelecimento | 12228 | novo | 18,2 | 19,8 | 10,8 | novo / 12 |
| 500000 | integral | lista | 337983 | 215,0 | 47,6 | 48,8 | 36,1 | 13 / 15/16 |
| 500000 | integral | detalhe | 337983 | 5,9 | 5,7 | 8,9 | 2,2 | 7/8 / 8 |
| 500000 | empresa | lista | 40567 | 200,2 | 51,4 | 55,7 | 39,1 | 13/14 / 15 |
| 500000 | empresa | detalhe | 40567 | 6,5 | 5,6 | 7,2 | 2,1 | 7/8 / 9 |
| 500000 | estabelecimento | lista | 2704 | 180,0 | 18,0 | 20,0 | 8,2 | 12 / 14 |
| 500000 | estabelecimento | detalhe | 2704 | 5,8 | 6,0 | 8,2 | 2,4 | 7 / 10 |
| 500000 | atribuicoes-1 | lista | 1355 | 194,8 | 14,8 | 16,8 | 5,8 | 12/13 / 14/15 |
| 500000 | atribuicoes-5 | lista | 9475 | 177,9 | 31,5 | 35,2 | 20,7 | 13 / 15 |
| 500000 | atribuicoes-30 | lista | 60876 | 206,2 | 74,3 | 77,9 | 62,6 | 13 / 15 |
| 1000000 | integral | lista | 675827 | 383,8 | 59,4 | 65,8 | 49,6 | 13/14 / 15/16 |
| 1000000 | integral | busca | 675827 | 598,4 | 249,8 | 386,2 | 238,8 | 13 / 15 |
| 1000000 | integral | periodo | 675827 | 413,2 | 28,9 | 33,1 | 18,7 | 13 / 15 |
| 1000000 | integral | autor | 675827 | 332,2 | 19,3 | 21,9 | 8,7 | 13 / 15 |
| 1000000 | integral | pagina-profunda | 675827 | 366,1 | 69,2 | 71,5 | 58,4 | 13 / 15 |
| 1000000 | integral | empresa | 675827 | novo | 34,1 | 36,8 | 22,9 | novo / 15 |
| 1000000 | integral | estabelecimento | 675827 | novo | 19,0 | 21,4 | 8,9 | novo / 15/16 |
| 1000000 | integral | detalhe | 675827 | 5,3 | 5,2 | 6,1 | 2,1 | 7 / 8 |
| 1000000 | empresa | lista | 81107 | 338,4 | 77,8 | 87,3 | 66,9 | 13 / 15 |
| 1000000 | empresa | busca | 81107 | 379,6 | 149,2 | 156,7 | 137,6 | 13 / 15 |
| 1000000 | empresa | periodo | 81107 | 307,4 | 62,2 | 67,0 | 51,0 | 13 / 15 |
| 1000000 | empresa | autor | 81107 | 248,6 | 62,8 | 65,8 | 51,9 | 13 / 15 |
| 1000000 | empresa | pagina-profunda | 81107 | 335,1 | 73,5 | 78,8 | 63,6 | 13/14 / 15 |
| 1000000 | empresa | empresa | 81107 | novo | 58,5 | 61,2 | 50,8 | novo / 12/13 |
| 1000000 | empresa | estabelecimento | 81107 | novo | 58,6 | 62,9 | 51,5 | novo / 12 |
| 1000000 | empresa | detalhe | 81107 | 5,1 | 5,2 | 6,3 | 2,1 | 7/8 / 9 |
| 1000000 | estabelecimento | lista | 5407 | 242,0 | 17,6 | 19,5 | 9,9 | 12 / 14 |
| 1000000 | estabelecimento | busca | 5407 | 294,8 | 25,9 | 28,6 | 17,2 | 12 / 14 |
| 1000000 | estabelecimento | periodo | 5407 | 294,1 | 20,0 | 21,9 | 10,6 | 12 / 14 |
| 1000000 | estabelecimento | autor | 5407 | 196,0 | 19,8 | 23,3 | 11,0 | 12 / 14 |
| 1000000 | estabelecimento | pagina-profunda | 5407 | 288,0 | 20,4 | 22,5 | 14,9 | 12 / 12 |
| 1000000 | estabelecimento | empresa | 5407 | novo | 22,7 | 25,2 | 13,4 | novo / 14 |
| 1000000 | estabelecimento | estabelecimento | 5407 | novo | 25,6 | 48,5 | 13,4 | novo / 14 |
| 1000000 | estabelecimento | detalhe | 5407 | 5,6 | 6,1 | 7,4 | 2,5 | 7/8 / 10 |
| 1000000 | atribuicoes-1 | lista | 2707 | 237,7 | 15,0 | 16,6 | 7,2 | 12 / 14/15 |
| 1000000 | atribuicoes-1 | busca | 2707 | 293,1 | 20,5 | 23,1 | 12,1 | 12 / 14 |
| 1000000 | atribuicoes-1 | periodo | 2707 | 288,9 | 15,6 | 17,0 | 6,9 | 12 / 14 |
| 1000000 | atribuicoes-1 | autor | 2707 | 192,8 | 12,0 | 14,1 | 6,7 | 11 / 11 |
| 1000000 | atribuicoes-1 | pagina-profunda | 2707 | 299,6 | 12,6 | 14,0 | 7,1 | 12 / 12 |
| 1000000 | atribuicoes-1 | empresa | 2707 | novo | 14,0 | 15,1 | 8,5 | novo / 11/12 |
| 1000000 | atribuicoes-1 | estabelecimento | 2707 | novo | 9,6 | 11,7 | 5,4 | novo / 11/12 |
| 1000000 | atribuicoes-5 | lista | 18936 | 264,6 | 41,2 | 47,9 | 32,2 | 13 / 15 |
| 1000000 | atribuicoes-5 | busca | 18936 | 545,2 | 161,9 | 185,9 | 151,6 | 13 / 15 |
| 1000000 | atribuicoes-5 | periodo | 18936 | 385,6 | 39,3 | 47,1 | 29,4 | 13 / 15/16 |
| 1000000 | atribuicoes-5 | autor | 18936 | 244,0 | 43,5 | 49,1 | 32,5 | 13 / 15 |
| 1000000 | atribuicoes-5 | pagina-profunda | 18936 | 393,8 | 50,5 | 62,8 | 41,1 | 13 / 15 |
| 1000000 | atribuicoes-5 | empresa | 18936 | novo | 39,2 | 42,4 | 32,2 | novo / 12 |
| 1000000 | atribuicoes-5 | estabelecimento | 18936 | novo | 31,8 | 39,8 | 26,5 | novo / 12 |
| 1000000 | atribuicoes-30 | lista | 121688 | 307,6 | 108,3 | 115,0 | 96,8 | 13/14 / 15 |
| 1000000 | atribuicoes-30 | busca | 121688 | 364,1 | 159,0 | 163,6 | 145,6 | 13 / 15 |
| 1000000 | atribuicoes-30 | periodo | 121688 | 321,5 | 87,4 | 93,1 | 75,7 | 13 / 15 |
| 1000000 | atribuicoes-30 | autor | 121688 | 245,0 | 91,2 | 98,3 | 79,9 | 13/14 / 15/16 |
| 1000000 | atribuicoes-30 | pagina-profunda | 121688 | 348,7 | 138,0 | 146,1 | 125,8 | 13 / 15 |
| 1000000 | atribuicoes-30 | empresa | 121688 | novo | 88,0 | 101,7 | 78,5 | novo / 12 |
| 1000000 | atribuicoes-30 | estabelecimento | 121688 | novo | 78,6 | 92,2 | 70,6 | novo / 12 |

- **Planos em 1 milhão (`EXPLAIN (ANALYZE, BUFFERS)`, `tests/Performance/e08-plans.sql`).** Página inicial da conta: 0,07 ms pelo índice `(account_id, created_at, id)`. Contagem da conta: 24,9 ms por index only scan paralelo (antes 42,7 ms). Empresa: página em 0,03 ms e contagem em 17,5 ms por `(company_id, created_at, id)` (antes 72,5 ms por varredura sequencial). 30 unidades: contagem em 26,5 ms por bitmap em `(establishment_id, created_at, id)` (antes 72,6 ms). Período: 3,3 ms com intervalo semiaberto, contra 42,3 ms com o cast anterior. Autor: 0,37 ms (antes 4,0 ms). Opções de autores por varredura solta: 3,3 ms (antes 191 ms). Tipos e ações: 0,35 ms (antes cerca de 82 ms cada). A busca textual continua em varredura sequencial (218,9 ms na conta inteira). Plataforma: 41 a 43 ms por varredura sequencial, por ter poucas linhas qualificadas.
- **Índices.** Removidos `(actor_id, created_at)` e `(entity_type, event)`, que não começavam pela conta. Criados `(account_id, actor_id, created_at)`, `(account_id, entity_type, event)`, `(company_id, created_at, id)` e `(establishment_id, created_at, id)`, todos usados nos planos acima. Em 1 milhão, os índices de `audit_records` passaram de cerca de 148 MB para 260 MB. Sem índice para a busca textual: `pg_trgm` fica como evolução, a ser decidida com volume real.
- **Escrita.** Cadastro de Pessoa com auditoria, sobre 1 milhão de eventos: de 17 ou 18 para 16 ou 17 consultas; mediana HTTP de 13,03 para 9,80 ms; banco de 4,75 para 3,80 ms. Em inserção em lote sobre 800 mil eventos, 200 mil linhas levaram 4.292 ms com os índices da E08 e 3.589 ms com os anteriores, cerca de 21,5 e 17,9 µs por linha (`e08-migrations.sh`, etapa 7).
- **Paginação.** A paginação por deslocamento foi mantida: página 500 (deslocamento 9.980) custa 14 ms no banco em 1 milhão. Paginação por cursor fica como evolução se o uso real exigir páginas mais profundas.

#### FrankenPHP, isolamento e carga

- **Isolamento.** Com um worker identificado, 300 escritas alternaram quatro contas e uma sessão de inspeção com edição. Entre elas houve consultas a IDs inexistentes com 404. Cada evento foi conferido no banco: autor, nome do autor, conta, origem e ausência de empresa e unidade. Nas escritas da inspeção, também representado, nome do representado e `inspection_id`. A revogação do administrador durante a inspeção encerrou a sessão na requisição seguinte, com 401. Nenhum evento foi gravado em nome do representado, e o encerramento automático foi registrado. A escrita seguinte do titular não herdou representado nem inspeção. Memória PHP no início da requisição: 9.279.752 bytes na primeira e 9.346.184 na última.
- **Carga prolongada.** Sobre 1 milhão de eventos, 8 lotes de 450 requisições sequenciais (3.600) alternaram o titular da Cenário 50, o auditor de empresa e o titular de outra conta. Os casos foram lista, página 50, período, busca, detalhe, autor, ação, ID inexistente e evento de outra conta. Todas as respostas tiveram o status esperado: 2.800 respostas 200 e 800 respostas 404, sempre no mesmo worker, sem reinício. A mediana HTTP ficou entre 13,2 e 13,5 ms, e o p95 entre 222 e 227 ms; antes da E08, eram 37,3 a 41,8 ms e 488 a 559 ms. O p95 vem da busca textual da conta inteira. A memória PHP no início da requisição ficou constante (9.344.912 bytes), com pico adicional de 363 KiB por requisição. A memória do contêiner variou de 201,0 a 203,6 MiB entre os lotes (antes, de 196,5 a 199,0 MiB), sem crescimento contínuo.
- **Concorrência.** Uma segunda instância FrankenPHP com dois workers, só no contêiner descartável, recebeu dez alterações simultâneas da mesma Pessoa. As dez responderam 200 e geraram dez eventos com `operation_id` distintos. O antes de cada evento é o depois do anterior, e o último corresponde ao nome gravado. A trava de `SavePersonAction` e a transação de `EnforceAudit` mantêm o histórico coerente sob concorrência. A repetição com o código final também refez as 300 escritas de isolamento.

#### Migrations em PostgreSQL

`tests/Performance/e08-migrations.sh` partiu do HEAD `e0d3cce` com 155 eventos (94 com empresa) e duas linhas em `directory_audit_events`: uma com evento central equivalente e outra só no legado.

- **Recusa.** Com uma linha de unidade sem empresa, a migration abortou com a mensagem de contexto incoerente e o esquema ficou intacto. Removida a linha de teste, a evolução foi aplicada.
- **Evolução.** Contagem preservada mais uma linha importada (`e08:`), nenhuma duplicata e os 94 eventos com empresa mantidos. Criadas as FKs `account_id`, `(company_id, account_id)` e `(establishment_id, company_id, account_id)`, os CHECKs e os índices da E08.7. `audit:check` e `organization:check` aprovados.
- **Proteções no banco.** Recusados: unidade sem empresa (`audit_records_establishment_requires_company`), empresa de outra conta (`audit_records_company_id_account_id_foreign`), empresa sem conta (`audit_records_company_requires_account`) e exclusão física de unidade com histórico (`audit_records_establishment_id_company_id_account_id_foreign`).
- **Reversão e reaplicação.** `migrate:rollback --step=2` removeu a importação, as colunas, as constraints e os índices novos, preservou os demais eventos e restaurou os índices anteriores. A reaplicação repetiu as contagens.
- **Instalação limpa** em PostgreSQL 18 aprovada, com `permissions:sync` e `audit:check`. Em SQLite, `migrate`, `migrate:rollback --step=2` e nova `migrate` passaram.
- **Classificação.** `2026_11_21_000003` é RP: a reversão perde o nome do representado e a referência da inspeção gravados depois dela. `2026_11_21_000004` é R.

#### E08.8 Documentação e testes

- `docs/convencoes/auditoria.md` reescrita com o contrato para módulos: registro, recurso afetado, contexto, autoria, inspeção, alterações, dados protegidos, transação, imutabilidade, consulta, filtros, plataforma e testes. `docs/convencoes/checklist-modulos.md` ganhou os itens de auditoria da E08.
- `tests/Feature/ContextualAuditTest.php` (14 testes): nós por tipo de evento; provisionamento pela plataforma, entrada, saída e disponibilidade restrita; Pessoa compartilhada sem duplicação; identificação histórica após renomear, suspender, inativar e remover; contexto incoerente; atualização sem mudança, reversão e falha obrigatória com log sanitizado; imutabilidade; inspeção somente leitura e com edição, encerramentos e requisição seguinte; matriz de visibilidade com integral, empresa, unidade, várias atribuições, sem permissão, suspensão e revogação, em lista, contagem, paginação, busca, opções e detalhe; filtros e Área de Trabalho; PD-02; privacidade; eventos antigos incompletos; consolidação legada.
- Ajustados: `AuditAndGuidanceTest` (PD-02, previsto na decisão) e `OrganizationMigrationTest` (reversão na ordem do `migrate:rollback`). `tests/Frontend/auditPlace.test.ts` fixa a coluna Onde.
- Cobertura já existente e preservada: sessão independente do representado e revogação (`AccountInspectionTest`, harness E05), bloqueio da preferência da Área de Trabalho em inspeção (`OrganizationContextTest`), duas abas (`CommercialRelationWorkspaceTest`). Na auditoria, os filtros ficam só na URL, por isso cada aba tem o próprio estado.

#### Verificações

- PHP completo em contêiner descartável, com SQLite em memória e e-mail em array: 239 testes e 2.832 asserções (linha de base: 225 e 2.526). `ContextualAuditTest`: 14 testes e 304 asserções.
- `npm run test:frontend` com 16 testes, `npm run check` (arquitetura de 824 arquivos e tipos), `npm run build`, PHPStan sem erros e Pint em 280 arquivos.
- `permissions:sync` descobriu 83 permissões, nenhuma nova (a E08 não cria ações). `audit:check` validou 12 modelos e `organization:check` passou, em SQLite e PostgreSQL.
- Validação visual em Chromium headless no ambiente descartável `e04-stack.sh` com os dados da E06. Listagem e ficha de um evento de inspeção em 1440, 1024, 768, 390 e 320 px, sem rolagem horizontal. Também filtro por empresa com unidades restritas, tour completo de 17 passos com o novo passo e a ficha fechada ao final, auditor de empresa (1440 e 390 px, sem eventos da conta e com uma empresa no filtro), estado vazio e plataforma (1440 e 390 px, sem a escrita feita na inspeção). A validação levou a dois ajustes: a marca Inspeção passou para a segunda linha, e os rótulos da ficha quebram entre palavras no mobile.
- `make test` no contêiner oficial não foi usado, para não herdar `MAIL_MAILER=smtp` nem tocar no ambiente oficial. As bases oficiais não foram migradas.

#### Decisões para homologação

- **E08-D1.** Lista fechada da PD-02 (11.5): eventos com conta nula, tipo Conta e origem `platform`.
- **E08-D2.** Integridade organizacional por FKs compostas `restrict` e CHECKs, com recusa da migration diante de histórico incoerente.
- **E08-D3.** Nome do representado e referência da inspeção gravados no evento; empresa e unidade exibidas com o nome atual.
- **E08-D4.** Disponibilidade restrita de cargo como evento do nó restrito, mantendo o comportamento da E05 em vez da classificação da tabela 11.1.
- **E08-D5.** Índices da E08.7 e varredura solta das opções no escopo da conta.
- **E08-D6.** Importação legada só das linhas sem equivalente central; tabelas legadas mantidas até aprovação.

#### Riscos, limitações e pendências

- Medições sequenciais com um worker em dados sintéticos. A concorrência cobriu dez escritas simultâneas na mesma Pessoa. Carga simultânea sustentada e volumes de produção não foram medidos.
- A busca textual da conta inteira passa de 200 ms a partir de 1 milhão de eventos. As contagens dos auditores com dezenas de unidades crescem com o volume visível (cerca de 100 ms com 121 mil eventos). Evoluções possíveis com volume real: `pg_trgm`, paginação por cursor e contagem estimada.
- Na primeira execução, a remoção de `directory_audit_events` e `access_catalog_events` ainda aguardava aprovação. O tratamento final está na revisão de encerramento.
- Eventos de segurança para tentativas negadas, retenção, assinatura e arquivamento do histórico não foram implementados (fora do escopo e sem política aprovada).
- A implantação exige backup, conferência do histórico (a migration recusa incoerências) e reinício controlado dos workers. Nenhuma base oficial foi migrada.
- E09 e E10 não foram iniciadas.


### Revisão técnica de encerramento da E08 em 21/11/2026

**Checkpoint.** A revisão começou na branch `feat/filtros-calendario-titulos`, com HEAD `e0d3cce` e índice Git vazio. A implementação E08 já estava na árvore de trabalho. A revisão manteve o núcleo transversal, a consulta contextual, os índices e a interface.

**Correções verificadas.** O agrupamento considera também o nó, e a leitura e a exclusão empregadas na consolidação ficam dentro do tratamento de exceções de persistência. O observer preserva listas de contatos sem convertê-las em texto. O início da inspeção grava a auditoria antes de trocar a sessão. Saída explícita e revogação da identidade representada registram o encerramento. A expiração passiva do armazenamento de sessões encerra o acesso, sem execução de evento de saída. No Chromium com sessão real em PostgreSQL, a primeira consulta após a expiração retornou 401. A falha de um registro obrigatório desfaz as escritas de negócio da mesma transação.

**Decisões.** D1 mantém a política da seção 11.5. D2 mantém FKs compostas e CHECKs. D3 preserva nome histórico do representado e referência da inspeção. D4 foi aprovada e validada com restrições simultâneas de empresa e unidade e posterior remoção de uma delas. Cada auditor vê o evento do seu nó e recebe 404 no detalhe do outro. D5 conserva os índices usados pelos planos. D6 foi aplicada com retirada das tabelas do esquema ativo, autorizada para o ambiente em desenvolvimento. A conciliação de origem não prova equivalência integral. A recuperação dos registros de origem requer backup verificado e exportação, conforme a 11.4.

**PostgreSQL.** A evolução a partir da E07 manteve 155 registros centrais, dos quais 94 com empresa, e importou um evento da amostra. Instalação limpa, exportação e restauração da origem, reversão e reaplicação passaram. Os ensaios recusaram contexto incoerente, referência a empresa de outra conta, unidade de outra empresa e exclusão física de unidade referenciada. A migration `000005` protege UPDATE, DELETE e TRUNCATE mesmo via SQL e operações em lote; a consolidação na transação original passou com savepoint. A proteção depende da configuração de privilégios do banco. A `000005` é reversível sem perda de eventos. A `000006` recupera dados de origem somente por backup.

**Performance.** PostgreSQL 18.7 e FrankenPHP 1.14.0, com volumes de 200 mil e um milhão e repetição dos caminhos afetados no código final em um milhão. Medianas HTTP finais em ms, na ordem dos cenários da E08.7, de 62,94 (lista integral), 263,06 (busca integral), 79,31 (empresa), 21,45 (unidade) e 109,20 (30 atribuições). Os planos usaram os índices novos de empresa, unidade, autor e entidade/ação. A varredura solta de autores levou 3,08 ms no banco. Cadastro com auditoria ficou em 9,90 ms, com 16 consultas. Dez alterações concorrentes produziram dez eventos com cadeia coerente. Um worker alternou contas e inspeção em 300 escritas com conferência da autoria no banco.

O ensaio prolongado da revisão, anterior ao último ajuste de encerramento da inspeção, registrou 2.800 respostas 200 e 800 respostas 404 em um worker. A memória PHP inicial teve mediana constante de 9.346.984 bytes por lote e o p95 HTTP ficou entre 226 e 243 ms. No ensaio de lote, 200 mil inserções com os mesmos índices levaram 5,01 s com a proteção e 5,10 s sem ela. Essa comparação de uma amostra por configuração não isola pequenas diferenças. A busca textual e a contagem de dezenas de nós continuam candidatas a revisão quando houver volume real.

**Cobertura final.** 243 testes PHP, 2.869 asserções, 18 testes específicos de auditoria contextual e 16 casos de frontend. Arquitetura, tipos, build, PHPStan, Pint, `permissions:sync`, `audit:check` e `organization:check` passaram. Em Chromium, lista, ficha e tour passaram em 1440, 1024, 768, 390 e 320 px, sem transbordamento horizontal da página. O tour concluiu 17 passos nas três larguras maiores e 15 nas duas menores, pulando a demonstração de largura de colunas. Filtros hierárquicos, estado vazio, perfil local, plataforma e identificação da inspeção foram verificados. Carregamento, paginação e comparação com texto extenso foram conferidos a 320 px.

**Estado.** A revisão técnica e visual automatizada foi concluída. O quadro mantém a E08 em validação para a homologação formal indicada no plano. Nenhum banco oficial foi migrado. Segurança de tentativas negadas, retenção, arquivamento e descarte controlado são decisões próprias. A E09 segue pendente.

### Revisão complementar dos documentos fiscais e de matriz e filial em 23/11/2026

**Escopo.** Revisão pedida antes de continuar a E08, sobre a branch `feat/filtros-calendario-titulos` (HEAD `1b78a4b`, árvore limpa no início). A E08 não foi iniciada nem alterada, nenhum ambiente oficial foi tocado e nada foi enviado ao Git. As etapas homologadas continuam com o estado do quadro acima.

**Conceitos preservados.**

- Conta: ambiente contratado do SaaS (`clients`, `account_id`).
- Empresa: entidade jurídica identificada fiscalmente pela raiz do CNPJ ou pelo CPF (`companies`).
- Estabelecimento: unidade pertencente à empresa (`establishments`).
- Matriz ou filial: classificação fiscal do estabelecimento (`is_headquarters` e CNPJ completo), explícita conforme a DA-04.
- Estabelecimento principal: referência operacional no Prospera (`is_primary`), independente da classificação fiscal.

**Decisões.** RF-01 a RF-03 (seção 28). O cadastro obrigatório da matriz antes da filial não foi aprovado e não existe no código.

**Verificação da E04: já correto antes desta revisão.**

| Regra | Evidência |
| --- | --- |
| Empresa só com filiais, sem matriz cadastrada | Nenhuma regra, consulta ou `organization:check` exige matriz. `SaveEstablishmentData` só exige o CNPJ da empresa para classificar Matriz ou Filial |
| Filial como estabelecimento principal | `forPrimary` aceita `branch`; `PromotePrimaryEstablishmentAction` não consulta a classificação |
| No máximo uma matriz por empresa | Regra `unique` na Data, índice parcial `establishments_company_id_headquarters_unique` e `organization:check` |
| CNPJ do estabelecimento com a raiz da empresa | `Company::admitsEstablishmentTaxDocument` na Data e de novo na Action com a empresa travada; troca do documento da empresa bloqueada com unidades de outra raiz |
| Raízes diferentes em empresas diferentes | `unique(account_id, tax_root)`; CNPJ de outra raiz é recusado no estabelecimento |
| Sem dedução pelo sufixo `0001` | Classificação explícita no Model, na Data e nas telas; nenhuma regra lê a ordem do CNPJ |
| Sem matriz fictícia nem principal duplicada | `CreateCompanyAction` cria uma única unidade; a migração `000006` cria a principal sem documento quando o contratante não é declarado |
| Criação automática da principal | Mantida na mesma transação, com auditoria pelo mesmo `operation_id` |
| Backend sem perda de letras | `TaxDocument::normalize` remove só a máscara e converte para maiúsculas; formato `[A-Z0-9]{12}\d{2}`; buscas de Empresas, Estabelecimentos, Clientes e Fornecedores usam o documento normalizado |
| Exibição | Listagens e fichas de Empresas, Estabelecimentos, Clientes, Fornecedores e Contas formatam o CNPJ alfanumérico pelo `TaxDocument` ou por `formatTaxDocument` |
| Nomenclatura | Estabelecimentos na navegação administrativa; Unidades na Área de Trabalho e no seletor. Nada foi renomeado |

**Correções realizadas.**

1. **Busca de contas da plataforma removia as letras do CNPJ.** `AccountDirectoryQuery` usava `preg_replace('/\D/', ...)`: `12.ABC.345/01DE-35` virava `123450135` e `12abc` encontrava qualquer documento com `12`. Agora a busca usa `TaxDocument::normalize`, com curinga escapado. O teste novo falha com a consulta anterior e passa com a correção.
2. **Provisionamento da conta presumia Matriz.** No modo automático, `SaveCompanyData::forProvisioning` marcava a principal como matriz sempre que o contratante era pessoa jurídica; no modo manual, o formulário vinha com Matriz pré-selecionada. Agora o modo automático exige `contractor_classification` (Matriz ou Filial) para contratante pessoa jurídica, e os dois modos começam sem seleção. Contratante pessoa física continua com unidade sem CNPJ próprio, sem pergunta.
3. **Matriz como informação complementar.** O formulário do estabelecimento recebe `headquarters`, consultado por `EstablishmentDirectoryQuery::headquarters` e filtrado no banco pelos nós de `account.establishments.view`. Com Filial selecionada, mostra "Matriz cadastrada nesta empresa" com nome e CNPJ. Com Matriz selecionada e outra matriz existente, orienta a reclassificar a atual. Sem matriz ou sem permissão, nada aparece e nada é exigido.
4. **Máscara e validação visual no frontend.** `formatPersonDocumentInput` deixava de aplicar a máscara quando havia letra nas duas últimas posições. Agora a máscara acompanha as 14 posições, e `taxDocumentProblem` avisa durante a digitação sobre caractere inválido, letra nos dígitos verificadores, tamanho e dígitos que não conferem. `isValidCnpj` e `isValidCpf` repetem o algoritmo do `TaxDocument`. O servidor continua sendo a validação final.

**Auditoria de letras do CNPJ.** Foram revisadas as normalizações (`TaxDocument`, `SaveAccountData`, `SavePersonDetailsData`, `SaveCompanyData`, `SaveEstablishmentData`, `normalizeTaxDocument`), as máscaras (`TaxDocumentField`, `CompanyDocumentField`), as buscas e os filtros (Contas, Empresas, Estabelecimentos, Clientes, Fornecedores e descoberta por documento), as fichas e as listagens. O único ponto que removia letras era a busca de Contas, corrigida no item 1. A consulta externa de CNPJ exige o documento numérico por limitação do serviço e informa o preenchimento manual para o alfanumérico.

**Arquivos.** `AccountDirectoryQuery`, `SaveAccountData`, `SaveCompanyData`, `EstablishmentDirectoryQuery`, `EstablishmentController`, `AccountOrganizationFields.tsx`, `ClientForm.tsx`, `establishment-form.tsx`, `Organization.types.ts`, `TaxDocumentField.tsx`, `formatTaxDocument.ts`, `guidance.ts`, artigos `estabelecimentos.md`, `empresas.md` e `contas.md`, tours `contas.md` e `organizacao.md`, e os testes `OrganizationTest`, `SaasPlatformTest` e `taxDocument.test.ts`. Não há migration, permissão nova nem campo auditado novo.

**Testes.**

- Linha de base, antes das alterações: 249 testes PHP, 49 falhas.
- Depois das alterações: 253 testes PHP, 2.508 asserções. As mesmas 49 falhas, com lista idêntica à da linha de base, e os 4 testes novos passando.
- As 49 falhas são anteriores à revisão: `PeopleDirectoryTest`, `PersonAdditionalContactsTest`, `SupplierDirectoryTest`, `CustomerDirectoryTest`, `CommercialRelationAccessTest` e `CommercialRelationIntegrityTest` ainda chamam `/conta/pessoas`, rota removida em `2ba26f7` (cadastro avulso de Pessoas). Todas respondem 404. Ficam como pendência própria.
- Testes novos: empresa só com filiais (principal filial, segunda filial, promoção, raiz isolada completada depois como filial, `organization:check` sem violação); ordem do CNPJ sem efeito na classificação e troca de matriz sem coexistência; matriz no formulário só para quem pode visualizá-la; busca de contas com CNPJ alfanumérico. Os testes de provisionamento e de documentos passaram a cobrir a classificação exigida, a principal filial no modo automático, o contratante pessoa física, o dígito verificador com letra e a busca alfanumérica em Empresas e Estabelecimentos.
- Frontend: 19 casos (3 novos). Arquitetura, tipos, build, Pint, PHPStan, `permissions:sync` (nenhuma mudança), `audit:check` e `organization:check` passaram.
- Execução em container descartável da imagem local, com SQLite em memória imposto por `tests/bootstrap.php`.

**Validação visual.** Ambiente descartável de `tests/Performance/e04-stack.sh` (PostgreSQL em tmpfs, FrankenPHP, dados sintéticos), em Chromium a 1440, 390 e 320 px, sem rolagem horizontal e sem erro de JavaScript:

- edição de filial com a referência da matriz, que quebra linha no celular;
- aviso ao escolher Matriz com outra matriz existente;
- matriz sem referência a si mesma;
- máscara com letra nos verificadores, aviso de dígito com letra e de dígito errado, e CNPJ alfanumérico válido sem aviso;
- cadastro de empresa com CNPJ de filial e classificação Filial, salvo como principal sem matriz e sem referência exibida;
- cadastro de conta no modo automático com a pergunta para pessoa jurídica, erro ao salvar sem a classificação e ocultação para pessoa física.

**Pendências e limitações.**

- As 49 falhas da suíte ligadas à remoção do cadastro avulso de Pessoas.
- No cadastro de conta em 1440 px, o rótulo "Cadastrar automaticamente?" encosta no ícone de informação. O problema é anterior a esta revisão e não foi alterado.
- A busca de Contas mantém `like` com curinga inicial, como antes; não houve medição em PostgreSQL porque o custo e o plano da consulta não mudaram.
- A entrada `contractor_is_company` do provisionamento, sem uso no frontend nem nos testes, foi mantida e segue a mesma exigência de classificação.

### Encerramento da revisão fiscal e estabilização da suíte em 23/11/2026

**Escopo.** Complemento da revisão anterior, aprovada pelo usuário com as decisões RF-01 a RF-03, sem reabrir a E04 nem iniciar a E08. Nenhuma base oficial foi alterada. Não há commit nem push.

**Causa das 49 falhas.** O commit `2ba26f7` (cadastro avulso de Pessoas removido) retirou as rotas `/conta/pessoas`, a ficha de Pessoas com a seção Relações comerciais, a concessão de acesso pela etiqueta Usuário e as permissões `account.people.list` e `account.users.update` (migration `000007`, auditada). Os testes de Clientes, Fornecedores, Pessoas, contatos e relações comerciais ainda usavam essas rotas e o envio `relations`, e todos recebiam 404. A decisão de produto está registrada no próprio commit, em `RoleBasedRegistrationTest` (a rota responde 404 por contrato), na ajuda e na convenção de Pessoas, mas não estava neste plano; fica registrada como RF-05.

**Classificação.** As 49 falhas eram de testes obsoletos. Nenhuma regressão funcional exigiu correção de implementação. Cada invariante coberta foi migrada para o caminho vigente:

| Contrato antigo | Caminho vigente |
| --- | --- |
| Cadastro de Pessoa com relações por empresa | `POST /conta/empresa/{empresa}/clientes` ou `/fornecedores`, com `add_companies` para outras empresas |
| Inativar e reativar pela ficha de Pessoas | Ações Ativar e Inativar da listagem ou campo Situação da ficha comercial |
| Edição de dados centrais e contatos adicionais | `PATCH` da relação comercial, com a autoridade de Pessoas por classe de dado |
| Ficha e listagem de Pessoas | Ficha e listagem de Clientes e Fornecedores por empresa e unidade |
| Acesso pela etiqueta Usuário | Usuários: cadastro sobre Pessoa existente, suspensão e reativação |
| Etiquetas convencionais na Pessoa | Etiquetas dos módulos Clientes e Fornecedores na relação |

Duas asserções mudaram de sentido por contrato posterior, e não por defeito:

- O teste da E07 esperava que a edição ignorasse a situação sem permissão. Desde `1b78a4b`, a ficha aplica a situação, recusa com 403 quem não pode inativar e desfaz o envio inteiro, como já exigia `RoleBasedRegistrationTest`.
- A ficha da relação de empresa inativa responde 403 pelo contrato da E06 (rotas operacionais recusadas). O teste prova que a relação fica preservada e volta a ser consultável com a reativação da empresa.

**Consequências do contrato vigente, sem correção.** Operador só com permissões de Pessoas não altera dados centrais, porque a entrada é a ficha comercial e exige a permissão de editar do papel na empresa. Administrador de unidade não alcança dados centrais, porque as rotas comerciais são de empresa. Pessoa sem relação comercial nem acesso não tem tela de edição.

**`contractor_is_company`.** Era a caixa de seleção do primeiro formulário de conta, substituída por `organization_setup` em `49c95fe`. O frontend e os testes migraram naquele commit, e o backend manteve só o fallback, sem consumidor. A entrada foi removida de `SaveAccountData`. A propriedade interna `contractorIsCompany` continua, porque representa a escolha explícita de "Cadastrar automaticamente? Sim". Um teste novo prova que o documento do contratante não vira a identificação da primeira empresa sem essa escolha. Com ela, a classificação Matriz ou Filial é obrigatória (RF-01 a RF-03).

**Correção visual.** No cadastro de Contas, a regra local `white-space: nowrap` do rótulo "Cadastrar automaticamente?" fazia o texto ultrapassar a coluna de rótulos e encostar no ícone. A regra foi removida, e o rótulo passou a quebrar como os demais do `BusinessForm`, com o espaçamento padrão do `FormField`. Em Chromium, a 1440, 390 e 320 px, o rótulo fica dentro da coluna, a 3 px do ícone, sem rolagem horizontal. A 1024 px não há sobreposição, mas a palavra "automaticamente?" avança 15 px além da coluna, dentro do card.

**Convenções.** `pessoas-e-contatos.md` e `contexto-organizacional.md` deixaram de descrever a ficha, a listagem e a URL de Pessoas e passaram a descrever as fichas de Clientes e Fornecedores como entradas da Pessoa.

**Resultados.**

- Suíte PHP completa: 254 testes, 3.279 asserções, sem falhas (antes, 253 testes com 49 falhas).
- Organização, Plataforma, Pessoas, relações comerciais e `TaxDocument`: 147 testes, 2.146 asserções.
- Frontend: 19 casos. Arquitetura, tipos e build passaram.
- PHPStan e Pint sem erros. `permissions:sync` sem mudanças (81 descobertas), `audit:check` com 12 modelos cobertos e `organization:check` sem violações.
- PostgreSQL 18.7 e FrankenPHP descartáveis: busca de Contas com CNPJ alfanumérico completo, parcial e com máscara, sem trazer a conta numérica parecida.

**Pendências.**

- Etiquetas convencionais do módulo Pessoas ainda podem ser criadas, e são a opção padrão do modal, mas nenhuma tela as aplica desde `2ba26f7`. As já vinculadas podem ser inativadas, não desvinculadas. `Person::assignTags` ficou sem chamadores. Decisão de produto pendente: retirar o módulo da criação ou criar uma entrada própria.
- `tests/Performance/AccountRequestsTest.php` e `octane-client.php` ainda medem `/conta/pessoas`; o segundo também `/conta/pontos`, removida na E04. Ficam fora da suíte padrão e devem ser atualizados na próxima medição.
- A 1024 px, o rótulo "Cadastrar automaticamente?" avança 15 px além da coluna de rótulos.

### Estabilização complementar em 23/11/2026, sem avanço de etapa

**Git e proveniência.** Conferência inicial: branch `feat/filtros-calendario-titulos`, HEAD `c4ec867`, três arquivos modificados, nenhum staging, não rastreado ou stash. A revisão fiscal e a migração dos testes estão incorporadas em `c4ec867`, posterior a `1b78a4b`. As alterações já presentes em `AccountRequestsTest.php`, `octane-client.php` e `octane-fixtures.php` foram preservadas e verificadas; sua autoria de sessão não é inferível pelo Git. O histórico já contém `7cd9b9d`, intitulado E08, mas esta intervenção não executa nem homologa essa etapa. Nenhum commit ou push realizado.

**Visual.** `FormField` permite que o item flexível do texto diminua (`min-width: 0`) e que palavras longas quebrem somente quando necessário (`overflow-wrap: anywhere`). O ícone mantém sua largura. Colunas, tokens e margens dos campos foram preservados, sem `!important`. Chromium real, cadastro SaaS de Contas com perfil sintético, larguras 1440, 1024, 768, 390 e 320 px: distância texto/ícone 3 px, rolagem horizontal zero, nenhum erro JavaScript. Em 1024 e 768 px o rótulo ocupa três linhas, inclusive com quebra dentro da palavra longa. Capturas em `/tmp/pw/shots/estabilizacao-conta-*.png`. Ajuda `contas.md`, tour `contas.md` e alvo `account-first-company` revisados: controles, ordem e conteúdo continuam válidos, sem edição necessária. A validação cobre o estado inicial de cadastro; não houve submissão de conta por navegador.

**Responsabilidade dos scripts.** O ensaio PHPUnit mede orçamento e duplicação de SQL em nove telas, com 40 usuários e agora 25 relações de Clientes. Continua exclusivamente em SQLite em memória, inclusive dentro do Docker; seus tempos não são referência de PostgreSQL. Confere também o papel, o total de relações e a empresa do formulário. O cliente HTTP mede nove telas, perfis com 10, 40 e todas as permissões, revogação imediata, isolamento entre contas, inspeção somente leitura e autoria em escrita. As fixtures criam empresas reais e unidades principais, sem contexto fictício.

| Cenário antigo | Substituição | Comparabilidade |
| --- | --- | --- |
| GET `/conta/pessoas` | GET `/conta/estabelecimento/empresa-a/clientes` | Nova base: relações comerciais por empresa, não diretório central |
| GET `/conta/pessoas/cadastrar` | GET `/conta/estabelecimento/empresa-a/clientes/cadastrar` | Nova base: formulário comercial com autoridade central |
| Ficha inexistente de Pessoa (404) | Ficha inexistente de Cliente na empresa (404) | Preserva teste de limpeza de contexto, não latência do recurso antigo |
| POST `/conta/pontos` (403, 422 e sucesso) | POST `/conta/empresa/empresa-a/clientes` (403, 422 e 204) | Nova base de escrita: Pessoa e relação; não custo de Pontos |
| Demais telas e perfis | Mesmas rotas | Mesma responsabilidade, comparação exige mesma revisão, carga e ambiente |

Nenhuma rota antiga foi restaurada. HTTP 200 confere o payload comercial, HTTP 204 é seguido de listagem com exatamente dois Clientes. O ensaio de um worker confirma autoria real/representada e conta na auditoria; a fixture também exige empresa e ausência de estabelecimento no evento comercial. O runner usa rede interna, banco sintético em tmpfs e e-mail em log.

**Medição descartável.** PostgreSQL 18 e FrankenPHP, dez amostras por tela/perfil com um e dois workers. Medianas HTTP em ms (1 / 2 workers): Início 12,81 / 11,93; Clientes 13,50 / 12,04; cadastrar Cliente 13,62 / 11,66; Etiquetas 13,40 / 13,29; Cargos 13,42 / 12,54; cadastrar Cargo 20,64 / 20,68; Auditoria 22,17 / 19,69; Usuários 24,40 / 19,38; Ajuda 17,37 / 15,64. São amostras sequenciais pequenas, sem meta de capacidade ou comparação com endpoints extintos. Queries HTTP: 9 a 10 nas telas comerciais, 12 em Auditoria, 13 em Usuários e 5 em Ajuda (incluem sessão e infraestrutura). O ensaio de um worker alternou 300 requisições com 403, 404 e 422, manteve a memória inicial PHP em 9.449.600 bytes nos cinco pontos coletados e passou revogação e inspeção. Não mede RSS nem prova ausência de crescimento sob carga prolongada. Não houve consulta crítica ou índice alterado; planos grandes e concorrência não foram reexecutados. Logs locais `/tmp/prospera-stabilization-octane1.log` e `octane2.log` (mesmo prefixo).

**Auditoria funcional de Pessoas.** Evidências: `CommercialRelationController`, `CommercialRelationDirectoryQuery`, `PersonAccessQuery`, `PeopleDirectoryQuery`, `UserController`, `RegisterAccountMembershipAction` e `Person`. A tabela central permanece tenant, sem sincronização com login nem cópia entre empresas.

| Situação | Criação e localização | Consulta e edição | Autoridade e restrições |
| --- | --- | --- | --- |
| Somente Cliente | Cadastro de Clientes; busca por nome/e-mail ou documento completo | Listagem e ficha do Cliente | Permissão própria de listar, visualizar, criar ou editar na empresa; dados centrais exigem Pessoas |
| Somente Fornecedor | Mesmo fluxo em Fornecedores | Listagem e ficha do Fornecedor | Permissões do papel Fornecedor na empresa |
| Cliente e Fornecedor | Descoberta por documento completo reutiliza a Pessoa no segundo papel | Cada ficha consulta a mesma Pessoa, com relações independentes | Não revela papel de empresa sem autorização; não há fusão automática por nome |
| Com acesso | Usuários cria Pessoa ou reutiliza candidata sem acesso, por nome/e-mail | Usuários administra vínculo e atribuições; ficha comercial edita dados centrais se houver relação | `account.users.create` no nó e cargo concedível; reutilização exige `account.people.view`; identidade de terceiros não é editável pelo tenant |
| Sem relação nem acesso | Registro anterior preservado; não existe cadastro avulso novo | Descoberta por documento e candidatos em Usuários permitem reaproveitar; não há ficha central isolada | Sem edição direta na interface. Criar relação ou acesso somente para editar não é solução aprovada |
| Perde todas as relações | Inativar preserva linhas e Pessoa | Relação inativa pode ser localizada pelo filtro de situação e aberta em empresa ativa; se não houver linha, aplica-se a lacuna anterior | Ativar/inativar exige ação própria; empresa inativa bloqueia ficha operacional com 403 |
| Várias empresas | `add_companies` e descoberta reutilizam Pessoa | Fichas por empresa e seleção de empresas autorizadas | Permissão de cada empresa; identificação exige cobrir todas as empresas com relação ativa, contatos ao menos uma |
| Atualizar dados compartilhados | Entrada pela edição comercial | Identificação, contatos e endereço centrais na ficha | `account.people.view` para ler e `account.people.update` por classe, além de editar o papel; campos negados são somente leitura e envio forjado recebe 403 |
| Etiquetas convencionais anteriores | Vínculos em `person_tag` preservados | Catálogo mostra etiquetas e quantidade; fichas comerciais não mostram nem editam a associação central | Sem ação atual para associar ou remover; permissões do catálogo não concedem edição da Pessoa |

Ausência de tela própria não quebra integridade referencial, isolamento nem persistência central. Há lacunas de acessibilidade funcional: Pessoa sem relação utilizável e sem acesso não tem ficha direta; Pessoa só com acesso não ganha editor de dados centrais em Usuários; operador só com permissões de Pessoas não alcança edição comercial. Isso precisa de decisão funcional, sem reconstruir o diretório. Pessoa sem documento não pode ser descoberta por CPF/CNPJ, mas pode aparecer como candidata sem acesso pelo nome/e-mail (limite 25).

**Etiquetas convencionais.** `Tag::MODULES` admite `people`, `customers` e `suppliers`; o modal de Etiquetas continua com Pessoas como padrão. O catálogo permite criar, editar, ativar/inativar com permissões `account.tags.*`; especiais com `system_key` são protegidas. Uma etiqueta em uso não pode mudar de módulo. `people_count` consulta os vínculos existentes. `Person::assignTags` mantém sincronização e evento explícito de `tag_ids`, mas não tem chamador do produto. Não foi usado para alterar dados nesta intervenção. Eventos históricos e pivôs permanecem. Clientes e Fornecedores selecionam exclusivamente etiquetas convencionais dos respectivos módulos nas tabelas de relação; não consomem etiquetas centrais convencionais. Usuários mantém somente a etiqueta especial Usuário ao criar/suspender vínculo, preservando as demais. Autoridade deriva de vínculos, cargos e atribuições, não de etiqueta convencional. Cliente e Fornecedor são classificações calculadas de relações ativas e visíveis, independentes de `person_tag`.

| Alternativa avaliada antes da definição funcional abaixo | Vantagens | Desvantagens e mudanças | Permissões |
| --- | --- | --- | --- |
| A: seleção central nas fichas comerciais existentes | Reutiliza formulário e recupera aplicação/remoção sem novo módulo | Seção explícita de etiquetas compartilhadas; payload separado dos `tag_ids` comerciais; ainda não atende Pessoa sem ficha comercial | `account.people.update` com autoridade de contatos, `account.people.view` e acesso à ficha; não usar só `account.tags.update` |
| B: restringir novos cadastros do módulo Pessoas | Remove oferta sem uso e preserva todo legado; intervenção menor | Retirar opção só na criação e validar no servidor, ajustar padrão, ajuda e tour; edição/inativação do legado continua; não resolve desvinculação | Mantém `account.tags.*`; não eliminar permissões de Pessoas |
| C: edição central contextual por ação nas fichas existentes | Pode atender também Pessoa só com acesso, sem diretório novo | Editor compartilhado aberto da ficha comercial ou detalhe de vínculo em Usuários; maior escopo, testes e clareza entre dados centrais e login | Exige autoridade de Pessoa por classe, sem conceder alteração de identidade; definir acesso para Pessoa órfã antes de implementar |

**Definição funcional posterior do usuário.** Não existe cadastro avulso, menu ou etiquetas próprias de Pessoas no produto. Clientes, Fornecedores e Usuários continuam utilizando a estrutura central internamente. As alternativas acima ficam apenas como histórico da auditoria, sem recomendação vigente nem autorização de implementação. Não há proposta de criar editor ou classificação central de Pessoas. A presença de `people` no catálogo de etiquetas descrita acima é um resíduo da implementação a adequar ao contrato, não uma funcionalidade a manter. Qualquer adequação deve preservar dados anteriores e auditoria, sem alterar as classificações comerciais Cliente e Fornecedor. Esta atualização registra a decisão e não modifica código, vínculos ou etiquetas existentes.

**Verificações e limites.** Frontend: 19 testes aprovados; arquitetura, TypeScript e build aprovados. PHPStan sem erros e Pint aprovado (283 arquivos). `permissions:sync`: 81 descobertas, nenhuma alteração; `audit:check`: 12 modelos cobertos; `organization:check`: zero violações, executados no PostgreSQL descartável. A suíte completa inclui Organização, Plataforma, Pessoas, Clientes, Fornecedores e documentos fiscais. Resultado final: 254 testes, 3.279 asserções, zero falhas e zero avisos, em 29,75 s. As tentativas iniciais de container sem chave ou arquivo `.env` geraram erro de preparação e avisos, corrigidos no ambiente descartável sem modificar a suíte ou base oficial.

**Arquivos e estado final.** Além dos três scripts inicialmente modificados, esta intervenção altera `FormField.css`, `run-octane.sh`, este plano e o README de Performance. Sem migration, código fiscal, Organization Switcher ou RBAC alterados. Sete arquivos modificados, nenhum não rastreado, staging ou stash; HEAD permanece `c4ec867`. Sem commit ou push. Ensaio PHPUnit adicional: 1 teste e 66 asserções; Pint dos 15 arquivos de Performance aprovado após os ajustes finais. Reexecução HTTP final com um worker também aprovada, inclusive empresa e autoria dos eventos. E08, E09 e E10 não foram iniciadas nesta intervenção. As alternativas de etiquetas centrais foram superadas pela definição funcional posterior. Resta adequar os resíduos técnicos ao contrato sem cadastro avulso, menu ou etiquetas próprias de Pessoas, preservando a estrutura central interna utilizada por Clientes, Fornecedores e Usuários.

### Adequação do filtro e cadastro de Etiquetas após a definição funcional

A imagem enviada pelo usuário confirmou a opção Pessoas ainda no filtro. A correção remove a opção do filtro e do cadastro, cujo padrão agora é Clientes. Novas etiquetas e mudanças de módulo aceitam somente Clientes ou Fornecedores no servidor. Os registros convencionais anteriores continuam no banco, com vínculos e auditoria preservados; sua manutenção conserva a classificação anterior, exibida como Cadastro anterior. Etiquetas especiais automáticas mostram Sistema na coluna Módulo, sem alterar suas regras. Não existe novo módulo, menu, cadastro ou etiqueta própria de Pessoas.

`Tag::COMMERCIAL_MODULES` define os destinos de novas etiquetas. O controller entrega a classificação persistida à classe Data para distinguir manutenção de registro anterior de uma tentativa de criar outro. Não foi criada permissão, migration ou trilha de auditoria; a manutenção segue `AuditLog::transaction()` e as permissões existentes. Ajuda, tour e convenção de Pessoas atualizados. O tour conserva alvos e ordem.

Validação: 255 testes PHP, 3.291 asserções, zero falhas; o teste novo cobre rejeição de criação, rejeição de mudança comercial para Pessoas, preservação do pivô anterior e evento auditado da inativação. As fixtures HTTP de etiquetas dos testes existentes foram ajustadas a Clientes, mantendo as asserções de autorização e auditoria. Frontend: 19 casos, arquitetura, tipos e build aprovados. PHPStan sem erros, Pint aprovado, `permissions:sync` sem mudanças (81 descobertas), `audit:check` com 12 modelos e `organization:check` sem violações.

Chromium em ambiente PostgreSQL/FrankenPHP descartável: filtro aberto e cadastro aberto em 1440, 1024, 768, 390 e 320 px; opções exatas Clientes e Fornecedores, padrão Clientes, fechamento por Cancelar, nenhum erro JavaScript ou rolagem horizontal. Capturas locais `/tmp/pw/shots/etiquetas-filtro-*.png` e `etiquetas-modal-*.png`. Sem submissão de cadastro pelo navegador; persistência e rejeições cobertas por testes HTTP. O ambiente habitual não foi reconstruído, respeitando o limite de não modificar containers de outras sessões. Para exibir a correção nele, os assets atualizados precisam ser publicados pelo fluxo habitual.

Git: mesmo HEAD e branch, sem commit ou push. Alterações anteriores preservadas; esta correção acrescenta mudanças em Data e Model de Etiquetas, DirectoryController, `tagModules.ts`, `TagEditorModal.tsx`, `tags.tsx`, testes `PeopleDirectoryTest` e `ContextualAuditTest`, ajuda, tour, convenção de Pessoas e este plano. Nenhuma etiqueta excluída, nenhuma base oficial alterada e nenhuma etapa iniciada.

### Retomada e consolidação da E08 em 23/11/2026

**Estado no checkpoint anterior à homologação abaixo: Em validação, aguardando homologação formal.** E09 e E10 não foram iniciadas. Esta retomada aproveita a implementação existente; não cria outra trilha, serviço por domínio, RBAC ou infraestrutura externa.

#### Diagnóstico anterior à programação

Git inicial: branch `feat/filtros-calendario-titulos`, HEAD `845218a4544cd2c7465acb127d8435eb6ea03fb8`, árvore e staging limpos, nenhum não rastreado ou stash. O commit `7cd9b9da93e4def29d7b7d941cf774bccefda759`, de 21/11/2026, pertence exatamente à E08 deste plano: 60 arquivos, 3.725 inserções e 288 remoções, incluindo serviço central, contexto, consultas, interface, quatro migrations, documentação e ensaios. É ancestral do HEAD, não trabalho pendente fora da branch. A revisão fiscal e a retirada da interface avulsa de Pessoas vieram depois dele.

| Classificação inicial | Constatação |
| --- | --- |
| Desenvolvido e aproveitado | `Auditable`, `AuditsChanges`, `AuditObserver`, `AuditLog`, `AuditContext`, transação HTTP, `AuditQuery`, filtros, detalhe, autoria real e representada, inspeção, FKs compostas, CHECKs, índices, proteção de histórico confirmado e migração legada |
| Desenvolvido e revalidado | Matriz de escopos, PD-02, rollback, no-op, privacidade, inspeção revogada, consulta por ID, opções e paginação, interface e responsividade |
| Incorreto na apresentação | Formatação de módulo de etiqueta ainda mostrava Pessoas e deixava Clientes/Fornecedores como códigos |
| Incompleto no registro | Sincronização do catálogo detectava mudanças em metadados, mas registrava somente o nome, inclusive quando o nome não mudava |
| Incompleto nos ensaios | Rollbacks por quantidade presumiam que E08 era a última migration; escritas comerciais exigiam conferir dois eventos de nós diferentes; repetição do isolamento reutilizava nome fixo; conclusão do PHP precisava de marcador explícito |
| Fora desta entrega | E09, E10, módulos comerciais novos, política de retenção e arquivamento, eventos de segurança para tentativas negadas e implantação operacional |

A linha de base foi conferida novamente: 255 testes PHP e 3.291 asserções, zero falhas. O quadro anterior já mantinha a E08 em validação. Não foi encontrada razão para reimplementar o núcleo.

#### Correções desta retomada

- `SyncPermissionsAction` passa a registrar escopo, módulo, classificação de sistema e conta quando esses campos efetivamente mudam. O nome só entra se mudar ou no cadastro. O registro continua como `permission-catalog`, sem conta, na mesma transação; não há alteração na decisão de autorização. Teste novo cobre antes/depois, autoria e silêncio na sincronização seguinte.
- `config/audit.php` traduz os módulos de etiquetas como Clientes, Fornecedores e Cadastro anterior. A tradução ocorre na leitura; o histórico armazenado e textos livres permanecem intactos. Não há menu, cadastro, listagem ou novas etiquetas próprias de Pessoas. `people` continua como estrutura interna para Clientes, Fornecedores e Usuários.
- O cliente de performance verifica separadamente o evento central da Pessoa (conta) e o da relação de Cliente (empresa), ligados pelo `operation_id`. O cenário de escrita atual é identificado como cadastro de Cliente e Pessoa, sem compará-lo ao cadastro avulso antigo. A corrida exige dez eventos, operações distintas, cadeia antes/depois, autor e nó corretos; registra o worker de cada resposta.
- O runner exige marcador final de conclusão além de status HTTP e payload. A exceção renderizada pelo bootstrap do console pode produzir saída parcial sem exit code não zero; essa saída agora falha no runner. A alternância é repetida com dois workers configurados, com IDs observados explícitos. A carga sequencial pode utilizar só um; nos ensaios finais, a alternância e as dez requisições concorrentes comprovaram atendimento pelos dois.
- Os dois processos FrankenPHP descartáveis usam limite de 20 mil requisições para não confundir reciclagem programada com vazamento durante o ensaio. Nenhuma configuração oficial de workers foi alterada. Identificadores sintéticos são únicos entre repetições.
- O harness de migrations seleciona as quatro migrations da E08 por nome, tanto na evolução como na reversão. A instalação limpa continua executando o esquema atual inteiro, inclusive as migrations posteriores.
- Ajuda, tour, convenção de auditoria e README de performance atualizados. Alvos e ordem do tour foram revisados e preservados. Não houve novo componente, mudança de layout, índice ou migration nesta retomada.

#### Matriz de cobertura verificada

As linhas abaixo representam operações existentes. Eventos Eloquent usam `created`, `updated` e, quando há exclusão permitida, `deleted`; mudanças de relacionamento consolidam no evento da mesma entidade e operação. Somente campos efetivamente alterados são registrados.

| Recurso ou operação | Evento e campos | Nó e autoria | Evidência |
| --- | --- | --- | --- |
| Conta: cadastro, edição, plano, situação e provisionamento | `account`, identificação permitida, plano, situação e contatos; documento por marcador | Conta afetada, sem empresa fictícia; administrador SaaS no provisionamento | `SaasPlatformTest`, `ContextualAuditTest`, `AuditAndGuidanceTest` |
| Empresa: cadastro, edição e situação | `company`, nome, raiz empresarial, tipo, regime, situação e inscrições | Conta e própria empresa | `OrganizationTest`, `ContextualAuditTest` |
| Estabelecimento: cadastro, edição fiscal, Matriz/Filial, situação e principal | `establishment`, CNPJ empresarial, `is_headquarters`, `is_primary`, situação e campos autorizados; promoção registra as unidades afetadas | Conta, empresa e próprio estabelecimento; mesmo autor da operação | `OrganizationTest`, `ContextualAuditTest` |
| Cliente e Fornecedor: criação, vinculação, reativação e inativação | `customer` ou `supplier`, pessoa, empresa e situação | Empresa da relação, independentemente da unidade da Área de Trabalho | Testes de diretórios, relações e `CommercialRelationWorkspaceTest`; harness E08 |
| Dados centrais compartilhados | `person`, identificação, contatos e endereço permitidos; CPF/CNPJ por marcador; evento único, sem cópia por empresa | Conta; edição autorizada por classe de dado | `ContextualAuditTest`, `PersonAdditionalContactsTest`, testes de relações |
| Etiquetas convencionais comerciais | Evento da relação com `tag_ids`, nomes anteriores e posteriores | Empresa da relação; criação e manutenção do catálogo em nó conta | `PeopleDirectoryTest`, `RoleBasedRegistrationTest`, `SaveCommercialTagsAction` |
| Etiquetas anteriores | Manutenção do catálogo auditada; vínculos anteriores preservados; não há criação nova do módulo Pessoas | Conta | Testes da estabilização e formatação histórica |
| Cargos e permissões do cargo | `role`, nome, abrangência e `permission_ids` com nomes legíveis | Conta | `AccessControlTest`, `ContextualAuditTest`, `AuditAndGuidanceTest` |
| Disponibilidade restrita e atribuições | `role-availability` e `role-assignment`, cargo, escopo e nó concedido ou retirado | Empresa/unidade do nó; disponibilidade geral é da conta | `ContextualAuditTest`, testes de acesso contextual |
| Suspensão e reativação de vínculo | `account-membership`, situação e mudança explícita da etiqueta automática Usuário | Conta; não altera a identidade global | Testes de acesso e inspeção; `ContextualAuditTest` |
| Sincronização de permissões | `permission-catalog`, `catalog-synchronized`, nomes e metadados alterados; no-op silencioso | Plataforma/sistema, sem conta ou unidade | `AuditAndGuidanceTest` e teste novo de metadados |
| Identidade, segundo fator, foto, chaves e acesso | `user`, mudanças permitidas, marcadores de segredos/foto e fato de criação/remoção de chave | Conta da sessão quando houver; autor real | Testes de autenticação, perfil e `AuditAndGuidanceTest` |
| Entrada, saída, redefinição e convite | `login`, `logout`, `password-reset-requested`, `invitation-requested`; sem token ou corpo da mensagem | Identidade/conta conforme sessão; disparo de link do vínculo em nó conta | Testes de identidade, acesso e auditoria |
| Inspeção: início, encerramento, saída e revogação | `account`, `inspection-started`/`inspection-ended`, modo e motivo; operações internas seguem o recurso real | Conta inspecionada; administrador SaaS como autor, representado separado | `AccountInspectionTest`, `ContextualAuditTest`, harness E08 |

`audit:check` confirma 12 modelos persistentes cobertos. Ele verifica a declaração de campos e nós, mas não substitui os testes de ações, pivôs, autoria, rollback e escrita em lote.

#### Contrato definitivo preservado

O nó vem da entidade afetada. A Área de Trabalho não fornece empresa ou unidade para a auditoria. Pessoas e catálogo de Etiquetas são internos à conta; relações comerciais são de empresa; operações de unidade identificam empresa e estabelecimento. Os serviços `AccountContext`, `OrganizationContext`, `EffectivePermissions` e `AuditContext` mantêm binding `scoped`, sem guardar Request, User ou Session em singleton e sem cache persistente de permissões.

A autoria guarda `actor_id`/nome real, representado id/nome histórico, vínculo e `inspection_id`. O modo é obtido pelo início da mesma inspeção e conta. A inspeção somente leitura bloqueia escritas; revogação encerra a sessão do inspetor na próxima requisição sem encerrar a sessão legítima independente do representado. Fim de sessão passivo pode deixar somente início e operações no histórico, conforme contrato anterior.

Persistência central na mesma transação da alteração: erro HTTP ou falha obrigatória de gravação desfaz a operação; atualização sem mudança é silenciosa; mudanças do mesmo registro e nó se consolidam; nós distintos não se fundem. FKs compostas e CHECKs preservam pertinência; inativação preserva o histórico; eventos anteriores incompletos não recebem contexto inventado. A proteção PostgreSQL preexistente contra UPDATE/DELETE/TRUNCATE de histórico confirmado foi testada, sem criar mecanismo adicional. Proprietário do esquema continua podendo alterar DDL; isso não é prova contra administrador de banco.

A consulta aplica `AuditQuery::scoped` e `AuthorizedNodes` no SQL antes de contar, paginar, buscar, montar opções ou consultar detalhe. O detalhe exige interseção de listar e visualizar, e o técnico exige permissão no nó. Atribuições se unem, sem dar eventos administrativos da conta a um auditor de unidade ou empresa. Parâmetros só restringem o conjunto autorizado; ID de outro escopo responde 404. A plataforma segue a PD-02, sem acesso automático aos eventos comerciais das contas. Leitura de modo de inspeção recupera somente o modo do início da mesma referência, sem expor o restante do evento de conta.

#### Privacidade e logs

Campos permitidos são declarados pelos Models. Senhas/hashes, tokens, segredos de segundo fator, credenciais e conteúdo de imagem ficam fora dos valores gravados; marcadores informam apenas o fato da alteração. Token de sessão é excluído com justificativa. Eventos explícitos continuam obrigados a enviar apenas valores permitidos. As escritas explícitas existentes foram revisadas: links, chaves e inspeção não copiam segredo, corpo de requisição ou cookies.

CPF/CNPJ central de Pessoa e contratante, e CPF da empresa individual, usam marcadores. Raiz do CNPJ empresarial e CNPJ do estabelecimento permanecem conforme contrato fiscal. E-mail, telefone e endereço centrais só aparecem no histórico da conta para quem tem auditoria nesse nó; permissão local de empresa ou unidade não basta. A permissão técnica não amplia o nó nem revela segredos. Esta é a política atual de minimização, não uma autorização universal a dados pessoais.

Descoberta comercial continua POST consultivo, documento no corpo, resposta privada sem cache e sem evento de mudança. Os logs de inspeção registram IDs, rota e status, sem payload. Falha de gravação central é convertida em `AuditWriteFailed` com tipo, operação e SQLSTATE, sem SQL com bindings. Nenhuma captura de todos os dados da entidade foi adicionada. Tentativas negadas não são eventos de alteração; retenção e segurança operacional continuam decisões separadas.

#### Validação e resultados da retomada

Suíte final: 256 testes PHP, 3.315 asserções, zero falhas; E08 específica: 18 testes e 378 asserções. Frontend: 19 testes; arquitetura, tipos e build aprovados. PHPStan sem erros e Pint aprovado. `permissions:sync`: 81 descobertas, nenhuma alteração; `audit:check`: 12 modelos; `organization:check`: nenhuma violação, em PostgreSQL descartável. A suíte permanece em SQLite em memória e e-mail em array, mesmo dentro do Docker.

Migrations em PostgreSQL 18 descartável: recusa de contexto incoerente antes de alterar o esquema; evolução pré-E08; importação de uma linha sem equivalente; contagens preservadas e sem duplicatas; FKs/CHECKs; UPDATE/DELETE/TRUNCATE negados a papel DML; consolidação na transação com savepoint; exportação e restauração das origens para rollback; reversão por nome, reaplicação e instalação atual limpa. Nenhuma migration ou base oficial foi alterada. Medição exploratória de 200 mil inserções sobre 800 mil: 8.585,96 ms com índices e proteção, 10.946,28 ms com os mesmos índices sem proteção e 6.918,21 ms com índices anteriores. Uma amostra por cenário em host compartilhado não permite atribuir a diferença isoladamente à proteção ou afirmar que ela acelera a escrita.

Chromium: lista, ficha com texto longo, autoria/representado/modo, Formatado/Log técnico, tradução histórica e estado vazio em 1440, 1024, 768, 390 e 320 px; perfis de empresa sem permissão técnica e plataforma em 1440 e 390 px. Sem erro JavaScript ou rolagem horizontal da página. Fechamento pelo botão testado; tour revisado sem mudança de alvos ou ordem. Capturas em `/tmp/pw/shots/e08-*.png`. Não houve escrita de negócio pelo navegador; persistência e recusas foram verificadas por testes HTTP e harnesses.

Os ensaios finais usam cópia imutável em `/tmp/prospera-e08-delivery-snapshot`, imagem `prospera-app:latest`, PostgreSQL em tmpfs, rede interna e e-mail em log. Os registros completos ficam em `/tmp/prospera-e08-delivery-volume.log`, `prospera-e08-delivery-endurance.log`, `prospera-e08-migrations-final.log`, `prospera-e08-delivery-tests.log` e `prospera-e08-visual.log`, todos no mesmo diretório temporário. Resultados quantitativos finais e limites estão na subseção seguinte.

#### Medições finais em PostgreSQL e FrankenPHP

PostgreSQL 18.7, FrankenPHP 1.14.0, PHP 8.5.13. Cada cenário tem dez amostras após uma descartada para aquecimento. Os volumes são de eventos sintéticos, além dos eventos gerados pelas fixtures. As linhas visíveis abaixo são os totais autorizados do perfil, não o total global nem linhas materializadas em PHP. Cada página contém no máximo 20 eventos. A matriz completa inclui lista, busca, período, autor, página profunda, empresa, estabelecimento e detalhe quando autorizado: 45 combinações por volume, 180 medições.

| Volume sintético | Perfil e caso | Eventos autorizados | HTTP mediana / p95 (ms) | Banco mediana (ms) | Queries | Pico adicional mediano (KiB) |
| --- | --- | --- | --- | --- | --- | --- |
| 10000 | integral: lista | 6890 | 33.55 / 36.04 | 15.9 | 15 | 622.2 |
| 10000 | integral: busca | 6890 | 52.56 / 56.19 | 33.92 | 15 / 16 | 618.8 |
| 10000 | empresa: lista | 839 | 35.93 / 37.48 | 15.55 | 15 / 16 | 403.1 |
| 10000 | estabelecimento: lista | 56 | 28.27 / 30.6 | 10.28 | 14 | 256.6 |
| 10000 | atribuicoes-30: lista | 1282 | 37.84 / 39.55 | 16.28 | 15 | 375.3 |
| 10000 | integral: detalhe | 6890 | 12.61 / 15.42 | 5.29 | 8 / 9 | 115.5 |
| 100000 | integral: lista | 67707 | 46.89 / 51.48 | 29.93 | 15 | 625.2 |
| 100000 | integral: busca | 67707 | 151.51 / 165.87 | 136.32 | 15 | 629.9 |
| 100000 | empresa: lista | 8134 | 38.01 / 40.43 | 19.98 | 15 / 16 | 403.1 |
| 100000 | estabelecimento: lista | 542 | 28.74 / 30.95 | 11.53 | 14 | 257.3 |
| 100000 | atribuicoes-30: lista | 12228 | 51.52 / 60.78 | 30.46 | 15 | 375.3 |
| 100000 | integral: detalhe | 67707 | 10.85 / 12.16 | 4.53 | 8 / 9 | 115.5 |
| 500000 | integral: lista | 337983 | 69.22 / 76.42 | 54.48 | 15 | 624.5 |
| 500000 | integral: busca | 337983 | 259.82 / 291.31 | 243.61 | 15 | 631.1 |
| 500000 | empresa: lista | 40567 | 80.26 / 122.6 | 61.05 | 15 | 403.1 |
| 500000 | estabelecimento: lista | 2704 | 32.38 / 36.41 | 15.39 | 14 | 257.3 |
| 500000 | atribuicoes-30: lista | 60876 | 114.37 / 134.62 | 97.54 | 15 | 375.3 |
| 500000 | integral: detalhe | 337983 | 24.67 / 26.5 | 10.95 | 8 | 115.5 |
| 1000000 | integral: lista | 675827 | 114.12 / 140.42 | 97.35 | 15 | 624.7 |
| 1000000 | integral: busca | 675827 | 533.45 / 561.15 | 516.5 | 15 | 631.1 |
| 1000000 | empresa: lista | 81107 | 142.42 / 151.04 | 120.73 | 15 | 403.1 |
| 1000000 | estabelecimento: lista | 5407 | 39.42 / 41.76 | 22.51 | 14 / 15 | 257.3 |
| 1000000 | atribuicoes-30: lista | 121688 | 186.16 / 200.85 | 166.77 | 15 / 16 | 375.3 |
| 1000000 | integral: detalhe | 675827 | 10.77 / 12.91 | 4.26 | 8 | 115.6 |

Nenhuma consulta medida teve assinatura SQL repetida. Variações de uma query decorrem da infraestrutura de sessão. `EXPLAIN (ANALYZE, BUFFERS)` confirmou uso dos índices preexistentes de conta/data, empresa/data, unidade/data, autor e tipo/ação. Os índices ocupavam cerca de 263 MB em um milhão de eventos (relação total de aproximadamente 505 MB). Não foi adicionado ou removido índice nesta retomada. A busca ampla continua usando varredura quando esse é o plano escolhido. Sem consulta ou índice de leitura alterado, os valores atuais são revalidação da implementação existente, não uma alegação de ganho frente à E07.

No ensaio final de 10 mil, cadastro de Cliente e Pessoa: 30 amostras, mediana HTTP 20,97 ms, p95 25,50 ms, banco mediano 9,35 ms, 22 queries, pico adicional mediano 142 KiB. Há uma assinatura repetida na escrita: trava da conta na Action comercial e na Action de Pessoa, ambas preexistentes. Isso não é N+1 de listagem nem duplicação de evento; sua otimização comercial não foi antecipada nesta etapa. Os dois eventos produzidos são conferidos em seus respectivos nós e na mesma operação.

Isolamento final: 300 escritas alternadas e leituras negadas com um worker, e mais 300 com dois workers, com conferência individual de autor, representado, conta e ausência de nó fictício no evento central. Os dois IDs de worker foram observados na instância dupla, tanto na alternância como na corrida. Dez PATCH concorrentes retornaram 204 e produziram exatamente dez eventos, operações únicas e cadeia antes/depois coerente. Revogação de inspeção foi testada em ambas as instâncias. Os dois runners finais terminaram com todos os marcadores de conclusão exigidos.

Carga prolongada final no conjunto de 10 mil: oito lotes de 450 consultas, total 3.600 respostas (2.800 HTTP 200 e 800 HTTP 404 esperados), mesmo worker e zero SQL repetido. Medianas HTTP por lote de 20,81 a 25,22 ms; p95 de 44,51 a 51,58 ms. A mediana da memória PHP inicial ficou em 9.219.512 bytes nos oito lotes; pico adicional mediano de 282 KiB. O uso do contêiner variou de 392,8 a 396,2 MiB, com pequenas quedas entre lotes. A variação total de 3,4 MiB merece acompanhamento em carga mais longa; a memória PHP estável e as asserções aprovadas não são prova de RSS invariável ou de capacidade de produção. A carga prolongada não foi repetida integralmente em um milhão; esse volume foi coberto nas consultas, planos, escritas e isolamento.

Os ensaios são sequenciais em host compartilhado; o runner de volume e o de carga pequena se sobrepuseram em parte. Não há comparação causal de latência entre revisões nem dimensionamento de capacidade. Não foram adicionados Redis, filas, particionamento, outro banco ou motor de busca.

#### Limites e estado do Git

Host compartilhado e dados sintéticos; amostras sequenciais não medem capacidade simultânea sustentada. Ensaios exploratórios concorrentes com migrations sofreram pressão de memória e foram substituídos pelas execuções finais em cópia imutável. Duas fragilidades do próprio harness foram corrigidas antes do aceite: nome fixo repetido e reciclagem do worker durante a alternância longa. Código HTTP sozinho ou saída parcial não são aceitos como sucesso. Não houve regressão funcional comprovada no núcleo contextual.

Persistem os limites já documentados: busca textual ampla, contagens em grandes conjuntos, ausência de política de retenção e necessidade de backup/reconciliação antes de evoluir bases operacionais. Nenhum índice novo foi adicionado para antecipar demanda. E08 permanece Em validação, sem homologação automática. Não há autorização para E09, E10 ou implantação nesta entrega.

Git final: branch `feat/filtros-calendario-titulos`, HEAD `845218a`, 11 arquivos modificados, nenhum em staging, não rastreado ou stash. Sem commit ou push nesta retomada. Mudanças limitadas à sincronização auditada do catálogo, rótulos de auditoria, teste de contrato, harnesses e documentação. Todos os containers e redes dos ensaios desta retomada foram removidos ao final. Nenhum arquivo de outra sessão foi descartado. A autorização de commit/push anterior foi consumada na frente de estabilização; esta tarefa observa a restrição atual.

### Homologação formal e encerramento da E08 em 23/11/2026

**Estado definitivo: Concluída.** O usuário aprovou expressamente a homologação técnica da E08, com base na última validação. E01 a E08 estão concluídas; E09 e E10 permanecem pendentes, sem autorização de início. Os registros anteriores de Em validação representam checkpoints históricos, superados por esta homologação. Não há ampliação de escopo nem necessidade de reimplementar a etapa.

**Referência e complementação aprovadas.** O núcleo do commit `7cd9b9da93e4def29d7b7d941cf774bccefda759` foi reaproveitado. A complementação validada sobre o HEAD `845218a` abrange os rótulos históricos de etiquetas na leitura, sem reescrever eventos, o registro dos metadados efetivamente alterados na sincronização de permissões, os testes correspondentes e as correções dos harnesses (conclusão explícita, conferência dos eventos central e comercial, nomes sintéticos únicos, workers e rollback por nome). Essa complementação permanece na árvore de trabalho, ainda sem commit próprio.

Foram aprovados o serviço central existente, o contexto do recurso afetado, a autoria real separada do representado, as proteções da inspeção SaaS, a consulta SQL protegida pelo RBAC contextual, a preservação do histórico, a integridade transacional, a minimização de dados sensíveis, os harnesses e a validação visual. Pessoas permanece entidade técnica interna: sem cadastro avulso, menu, listagem própria ou novas etiquetas de Pessoas; vínculos anteriores e classificações comerciais são preservados.

**Evidências homologadas, sem nova execução nesta formalização documental.**

- Suíte PHP: 256 testes, 3.315 asserções, zero falhas.
- E08 específica: 18 testes, 378 asserções; frontend: 19 testes aprovados.
- Arquitetura, tipos, build, PHPStan e Pint aprovados; `permissions:sync` com 81 permissões descobertas e nenhuma alteração; `audit:check` com 12 modelos cobertos; `organization:check` sem violações.
- PostgreSQL 18.7 e FrankenPHP 1.14.0 descartáveis, com instalação, evolução, reversão, reaplicação, integridade e proteção do histórico verificadas. A suíte continua exclusivamente em SQLite em memória.
- Dez escritas concorrentes com dez eventos e cadeia coerente; alternância de 300 escritas com um worker e mais 300 com dois workers, conferindo autoria, inspeção, conta e nós. Carga prolongada de 3.600 consultas com respostas esperadas.
- 180 medições de consulta nos volumes de 10 mil, 100 mil, 500 mil e um milhão de eventos sintéticos. A matriz de cobertura, os planos SQL e as tabelas de resultados anteriores permanecem integralmente preservados.
- Interface validada em 1440, 1024, 768, 390 e 320 px, incluindo detalhes, inspeção, rótulos e estado vazio.

**Limitações expressamente aceitas, sem bloqueio ao encerramento técnico.**

1. A busca textual ampla em um milhão de eventos apresentou mediana de aproximadamente 533 ms e p95 de 561 ms (valores medidos: 533,45 e 561,15 ms).
2. Essas medições constituem linha de base sintética em host compartilhado, não garantia de desempenho ou dimensionamento de produção.
3. Uma otimização futura deve considerar frequência real de uso, seletividade dos filtros, planos SQL medidos e impacto nas inserções.
4. Esta homologação não autoriza infraestrutura externa, índices especulativos ou particionamento.
5. Retenção e arquivamento dependem de política futura; não há autorização de descarte de histórico.
6. O ensaio prolongado de memória não substitui medições com carga simultânea sustentada em produção. A variação do contêiner e a estabilidade da memória PHP permanecem registradas como evidência limitada.

**Condições futuras de implantação.** O encerramento técnico não representa implantação. Permanecem necessários autorização operacional própria, backup verificado, exportação e reconciliação das origens legadas quando aplicável, ensaio prévio em cópia isolada, conferência de escopo e privacidade e atualização controlada dos workers. A credencial de execução deve ter os privilégios necessários de dados, separada da credencial administrativa de migrations. Bases oficiais e produção não foram alteradas; nenhum histórico foi excluído nesta formalização. Monitoramento de consultas e memória em carga real e política de retenção/arquivamento continuam pendências operacionais futuras.

**Checkpoint documental e Git.** Conferência desta homologação: branch `feat/filtros-calendario-titulos`, HEAD `845218a`, 11 arquivos modificados, nenhum em staging, não rastreado ou stash. Os diffs correspondem à complementação da E08 apresentada na validação anterior: `SyncPermissionsAction.php`, `config/audit.php`, ajuda e convenção de auditoria, este plano, tour de auditoria, `AuditAndGuidanceTest.php`, README de Performance e os três harnesses E08. Não foi identificada alteração adicional de outra frente pelos caminhos e conteúdo atuais; a autoria de sessão não é um atributo recuperável do Git. Todos os diffs existentes foram preservados.

Esta intervenção acrescenta somente a homologação neste plano (cabeçalho, quadro, registro de decisões e este encerramento). Essa alteração documental fica identificada para um futuro checkpoint junto da complementação já validada. Nenhum código, teste, migration, configuração ou outro documento foi modificado nesta formalização. Sem commit ou push; a autorização anterior de envio dizia respeito à frente já encerrada de estabilização. E01 a E07 não foram reabertas, e E09 e E10 não foram iniciadas.

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
| DA-26 | Convenções separadas por assunto | Aprovada | 20/11/2026 | Revisão 5; desvio do escopo previsto (uma convenção) |
| DA-27 | Namespaces, serviços de contexto, models e nomes de permissão | Aprovada | 20/11/2026 | Revisões 5 e 6; `send-password-reset` |
| DA-28 | Prefixos de URL, identificadores e terminologia | Aprovada | 20/11/2026 | Revisão 5; substitui `emp` e `e` da 12.2 |
| DA-29 | Fluxos seguros de identidade e respostas neutras | Aprovada | 20/11/2026 | Revisões 5 e 6; I1 e I2 na E05 |
| DA-30 | Classificação campo a campo de Pessoas e textos do formulário | Aprovada | 20/11/2026 | Revisão 5; I5 |
| E02 | Fechamento da etapa | Concluída | 20/11/2026 | Revisão 6 |
| E05 | Remoção de `users.role_id`, `users.is_account_admin` e `users.person_id`; `users.account_id` mantida até a E09 | Aprovada | 20/11/2026 | Homologação final da E05 |
| E05 | Fechamento da etapa | Concluída | 20/11/2026 | Homologação final da E05 |
| E06 | Início do estabelecimento como destino do seletor; 403 orientado para contexto inativo; consolidado somente leitura; seletor em todas as telas da conta | Proposta, em validação | 20/11/2026 | Registro de execução da E06 |
| E07 | Descoberta comercial por POST consultivo, com documento no corpo e resposta privada sem cache | Aprovada | 21/11/2026 | Preserva CSRF, escopo empresarial, autorização e inspeção somente leitura |
| E07 | Fechamento da etapa e aceite do tema disponível | Concluída | 21/11/2026 | Homologação final na seção 26 |
| E08 | E08-D1 a E08-D6: lista da PD-02, integridade por FKs compostas e CHECKs, identificação histórica da inspeção, nó da disponibilidade restrita, índices de leitura e importação legada | Aprovadas na homologação técnica | 23/11/2026 | Revisão técnica de 21/11/2026 preservada; homologação formal na seção 26 |
| E08 | Encerramento técnico: núcleo `7cd9b9d` e complementação validada sobre `845218a`, com contrato de Pessoas interno preservado | Concluída | 23/11/2026 | 256 testes PHP e 3.315 asserções; 18 testes E08 e 378 asserções; 19 testes frontend; sem implantação ou autorização de E09/E10 |
| E08 | Limitações de busca ampla, linha de base sintética, memória, retenção e arquivamento | Aceitas, sem bloqueio técnico | 23/11/2026 | Busca em um milhão: mediana aproximada 533 ms e p95 561 ms; otimização futura baseada em uso, planos e custo de inserção; sem infraestrutura ou índices especulativos |
| E09 | Identidade global sem `users.account_id`; conta na URL `/contas/{conta}` validada pelo vínculo ativo; `clients.code` único, com formato e imutável; seleção automática com um vínculo e explícita com vários; convite só para identidade existente, token SHA-256 de uso único preso ao destinatário; flag `prospera.multiple_accounts` sem efeito sobre vínculos existentes | Aprovadas na homologação técnica | 23/11/2026 | Relatório da E09 e homologação formal na seção 26 |
| E09 | Encerramento técnico sobre o HEAD `48e2191` com a árvore de trabalho validada, sem commit | Concluída | 23/11/2026 | 274 testes PHP e 3.748 asserções; 18 testes E09 e 390 asserções; 20 testes frontend; 12.000 requisições FrankenPHP amostradas; sem implantação nem início da E10 |
| E09 | Limitações de medição sintética, acessibilidade básica e flag desligada por padrão até liberação operacional | Aceitas, sem bloqueio técnico | 23/11/2026 | Medições em host compartilhado e bcrypt de custo 4; auditoria com leitor de tela futura |
| RF-01 | Padronização de CPF e CNPJ: máscara na digitação, validação visual, CNPJ alfanumérico e correção de listagens e fichas | Aprovada | 23/11/2026 | Revisão complementar fiscal na seção 26 |
| RF-02 | A matriz não é pré-requisito da filial: uma empresa pode administrar só filiais no Prospera; a matriz cadastrada aparece só como referência, conforme permissão | Aprovada (cadastro obrigatório da matriz rejeitado) | 23/11/2026 | Complementa DA-04 e a seção 6.5. Sem matriz fictícia nem principal duplicada |
| RF-03 | Estabelecimentos na navegação administrativa e na arquitetura técnica; Unidades na Área de Trabalho e no seletor organizacional | Aprovada | 23/11/2026 | Sem renomear tabelas, Models, rotas ou domínios |
| RF-04 | Remoção da entrada obsoleta `contractor_is_company`; só `organization_setup` automático usa os dados do contratante na primeira empresa | Aprovada | 23/11/2026 | Encerramento da revisão fiscal na seção 26 |
| RF-05 | Pessoas sem tela própria (`2ba26f7`): Clientes, Fornecedores e Usuários são as entradas; `account.people.list` e `account.users.update` retiradas | Registro de decisão já implementada | 21/11/2026 | Registrada em 23/11/2026; testes migrados aos caminhos vigentes |

Decisões novas surgidas durante a execução devem ser registradas aqui antes de alterar o contrato, com a justificativa e o impacto nas etapas.


### Checkpoint de retomada da E09 em 23/11/2026

**Estado: Em validação, com implementação e evidências ainda incompletas.** Não representa conclusão
técnica nem homologação. A autorização do usuário cobre E09.2 a E09.10. E10 não foi iniciada.

Decisões aprovadas: código público `clients.code` único na plataforma, imutável e com 2 a 40 caracteres;
URL explícita para todos os tenants; remoção de `users.account_id` sem espera artificial; convites de
identidades existentes com hash, validade, revogação e aceite autenticado. O código não autoriza acesso.
A chave `prospera.multiple_accounts` controla novas associações por convite, preservando resolução,
autorização e seleção dos vínculos existentes quando desligada. Não escolhe arbitrariamente uma conta.

A revisão da PD-04 substitui a URL implícita para vínculo único para preservar contexto explícito,
independência entre abas e inspeção SaaS. A migration de contração aborta sua reversão antes de alterar
o esquema se houver identidade com vários vínculos. Recuperação nesse caso exige correção para frente
ou restauração de backup verificado com autorização explícita, nunca escolher uma conta arbitrária.

Retomada: preservadas as alterações existentes. Acrescentadas telas ausentes de seleção e aceite,
exibição e revogação de convites pendentes, imports ausentes, tours, ajuda e contratos de navegação.
Pessoa do destinatário é criada no aceite, quando não existe pessoa indicada dentro da própria conta.
Não há concessão de vínculo pendente por correspondência de e-mail.

Verificações: PHPUnit direto em container descartável, sem rede, SQLite em memória: 258 testes e 3.370
asserções aprovados antes do último cenário adicional. Testes específicos depois: 3 testes e 20
asserções aprovados, incluindo chave desligada, isolamento, URL antiga de escrita, destinatário,
uso único, expiração e revogação. Frontend: 20 testes aprovados. PHPStan sem erros e Pint aprovado.
Build inicial aprovado; nova execução após exposição de convites registrada na sessão.

Limitações e pendências para fechar a entrega: medições antes/depois em PostgreSQL isolado, validação
de instalação e evolução em PostgreSQL, concorrência de códigos e aceites, workers reais FrankenPHP,
regressão específica do Fortify para convites revogados, revisão arquitetural das consultas ainda
presentes fora de Query e filtro de convites antes do limite. Seleção usa duas consultas para incluir
cargos, enquanto o plano previa uma; precisa de medição e decisão documentada. Validação visual de
seleção, convite, revogação e tours em 1440, 390 e 320 px ainda não realizada. Não há evidência visual
nem de performance produzida nesta retomada. Esses itens impedem declarar E09.2 a E09.10 concluídas.

Nenhum container oficial ou banco compartilhado foi alterado. Dependências locais instaladas a partir
do lock. Sem commit ou push. Ajuda e tours usam a integração já existente; homologação formal pendente.


### Validação integrada da E09 em 23/11/2026

**Estado: Em validação, aguardando homologação formal.** Este registro supera o checkpoint parcial
anterior quanto às lacunas técnicas. Não homologa automaticamente a E09 e não inicia a E10.

O [relatório integrado](001-e09-validacao-integrada.md) registra diagnóstico, decisões reaproveitadas,
correções, matriz de requisitos, instalação e evolução PostgreSQL 18, remoção de `users.account_id`,
backup/restauração, concorrência, isolamento, Fortify, flag, autoria, inspeção e Chromium em 1440,
1024, 768, 390 e 320 px. Evidências e scripts estão versionados, com limitações das medições explícitas.

Resultado final: 267 testes PHP e 3.494 asserções; 11 testes específicos e 136 asserções; 20 testes
frontend; build, tipos, PHPStan, Pint, permissões, auditoria e estrutura organizacional aprovados.
Ensaios de 6.000 requisições amostradas com um e dois workers FrankenPHP, além das requisições de
controle, sem respostas inesperadas. A seleção agora executa uma consulta própria. Convites de
identidade nova permanecem no Fortify e não permitem operar com senha inicial pendente.

Os bancos descartáveis finais não têm `users.account_id`. Nenhum banco ou container oficial foi
alterado. Sem commit ou push. Implantação, liberação operacional e homologação formal continuam
fora desta execução. Não há integração pendente de ajuda ou tours dessas telas.

### Homologação formal e encerramento da E09 em 23/11/2026

**Estado definitivo: Concluída.** O usuário autorizou expressamente a conferência de homologação da E09
(Identidade global e acesso a múltiplas contas), com base na validação integrada e na retomada. E01 a
E09 estão concluídas; a E10 permanece pendente, sem autorização de início. Os registros anteriores de
Em validação (checkpoint de retomada e validação integrada, cujos números de 267 testes foram superados
pela retomada) são checkpoints históricos preservados. E05 a E08 não foram reabertas.

**Última verificação técnica: consulta adicional em Etiquetas e Área de Trabalho.** A consulta é
`select workspace_establishments from account_memberships where account_id = ? and user_id = ? and
status = 'active' limit 1`, feita por `OrganizationContext::defaultCodes` (E06, sem alteração pela
E09) para ler o padrão pessoal da Área de Trabalho. Ela só ocorre quando a pessoa tem mais de uma
unidade navegável na conta; com uma unidade não há seletor nem preferência a ler. A linha de base
(`octane-final-2.json`, 16:00) foi medida antes de os fixtures ganharem a "Unidade adicional" na conta
1, incluída para a prova da Área de Trabalho; às 16:07 a mesma diferença já aparecia. Prova por
experimento em PostgreSQL descartável: com a unidade adicional inativa, Etiquetas e Área de Trabalho
da conta 1 caem para 9 e 6 consultas, sem a leitura; reativada, voltam a 10 e 7, com a leitura; a conta 2,
de uma unidade, nunca a executa. Conclusão: alteração legítima do fluxo funcional, dependente do dado,
não redundante (uma leitura por requisição, já filtrada pelo vínculo ativo) e não introduzida pela E09.
Nenhuma otimização, cache ou índice foi acrescentado. Evidências em
`docs/evidencias/e09-2026-11-23/homologacao/`; o script reutilizável é `tests/Performance/e09-route-queries.php`.

**Decisões confirmadas.** Identidade global independente dos tenants; `users.account_id` ausente no
esquema e sem dependência de execução; `clients.code` único, com formato, palavras reservadas e
imutabilidade no PostgreSQL; URLs `/contas/{conta}/...`; entrada automática com um vínculo e escolha
explícita com vários, sem seleção implícita para escrita; convites para identidades existentes com
aceite legítimo, token só como SHA-256, uso único e destinatário preso à identidade; flag sem perda de
vínculos ou permissões; permissões, dados e auditoria isolados por conta; inspeção SaaS presa à conta
inspecionada, com autoria real e sem alterar preferências; preferências da Área de Trabalho por usuário
e conta; abas independentes. Pessoas continua estrutura central interna, sem cadastro avulso, e novas
etiquetas só nos módulos comerciais; o domínio de Etiquetas não foi alterado pela E09.

**Evidências homologadas.** Repetição nesta conferência, com a árvore final: PHPUnit completo 274 testes
e 3.748 asserções; específicos da E09 18 testes e 390 asserções; contexto organizacional, Área de
Trabalho e Pessoas 40 testes e 629 asserções; frontend 20 testes; arquitetura, tipos, build, PHPStan e
Pint aprovados. Da validação integrada e da retomada: migrations limpas e evolutivas em PostgreSQL 18
com hashes preservados, reversão condicionada e restauração; corridas reais de aceite, emissão, código,
revogação, cargo, último administrador, concessão e suspensão em voo; 6.000 requisições amostradas com
um worker e 6.000 com dois workers FrankenPHP, sem respostas inesperadas e sem crescimento contínuo de
memória; flag desligada sob HTTP real; Chromium em 1440, 1024, 768, 390 e 320 px; `permissions:sync`
com 82 permissões, `audit:check` com 13 modelos e `organization:check` sem violações.

**Limitações expressamente aceitas, sem bloqueio ao encerramento técnico.**

1. Latências e memória são linha de base sintética em host compartilhado, com bcrypt de custo 4, sem
   garantia de desempenho ou dimensionamento de produção.
2. A acessibilidade foi verificada em nível básico (nomes acessíveis, teclado e Escape), sem auditoria
   com leitor de tela.
3. O e-mail foi validado apenas com transporte em memória; nenhum provedor real foi usado.
4. A flag `prospera.multiple_accounts` tem padrão desligado e só deve ser ligada por decisão operacional.
5. A resolução da conta e, com mais de uma unidade navegável, a leitura da preferência da Área de
   Trabalho são feitas a cada requisição, sem cache entre requisições, por segurança no Octane.

**Homologação técnica não é implantação.** Bases oficiais, containers oficiais e produção não foram
alterados. Permanecem pendências operacionais da implantação futura: backup recuperável e verificado
da base a migrar; janela de migrations, com a contração de `users.account_id` como ponto sem reversão
simples após o primeiro vínculo múltiplo; política de ativação da flag; configuração e validação do
provedor de e-mail; reinício controlado dos workers; tratamento das sessões existentes (novo login
das sessões anteriores à contração); monitoramento inicial de erros, latência, memória e consultas após
a implantação; e revisão aprofundada de acessibilidade quando programada.

**Checkpoint documental e Git.** Branch `feat/filtros-calendario-titulos`, HEAD `48e2191`, nada em
staging nem stash. A árvore contém 100 arquivos rastreados modificados (incluindo a remoção de
`routes/context.php`, substituído por `routes/account.php`) e 283 arquivos não rastreados em 41
entradas: código, migrations e testes da E09, scripts de laboratório e navegador, ajuda, tour,
relatório e 237 arquivos de evidência em `docs/evidencias/e09-2026-11-23/`. Não foi identificada
alteração de outra frente pelos caminhos e conteúdo; a autoria por sessão não é recuperável pelo Git.
Esta formalização alterou somente este plano, o relatório da E09, o README de Performance e acrescentou
`tests/Performance/e09-route-queries.php` e a pasta de evidências `homologacao`. Sem commit, push,
reset ou stash. A E10 não foi iniciada.

### Homologação integrada da E10 em 24/11/2026

**Estado: Em validação, aguardando homologação formal.** Autorizada pelo usuário como última etapa do plano. Não
houve commit, push, implantação, migração de bases oficiais nem alteração dos containers `prospera-*`.

Referência: branch `feat/filtros-calendario-titulos`, HEAD `af96f3e` (seeders), posterior a `cd4305f` (E09). Linha
de base: 277 testes PHP e 3.777 asserções, 20 testes frontend, PHPStan, Pint e build aprovados.

O [relatório da E10](001-e10-homologacao-integrada.md) registra laboratório, massa criada pelas próprias jornadas
HTTP (contas A, B, C e D, empresas com raízes numérica e alfanumérica, filial principal sem matriz e identidades com
uma, duas e três contas), as dez jornadas, matriz de segurança, concorrência, workers, auditoria, desempenho,
interface nas cinco larguras e a matriz de rastreabilidade de E01 a E09. Evidências em
`docs/evidencias/e10-2026-11-24/`; roteiros em `tests/Performance/e10*` e `tests/Browser/e10.mjs`.

Defeitos encontrados, corrigidos na responsabilidade original e revalidados:

- **D1 (E04 e E07):** deadlock entre a escrita de empresa ou estabelecimento e o cadastro comercial na mesma conta
  (500 em 5 de 5 e 3 de 5 rodadas). `SaveCompanyAction`, `SaveEstablishmentAction` e
  `PromotePrimaryEstablishmentAction` passam a travar a conta antes da empresa, a ordem das demais Actions. Uma
  consulta a mais por escrita organizacional.
- **D2 (E05):** `account.tags.list/view` só funcionava com atribuição de conta, contrariando a seção 8.2.1 ("qualquer
  atribuição"). Listar e visualizar Etiquetas passam a aceitar qualquer atribuição com a permissão; a manutenção do
  catálogo continua no escopo da conta. Artigo de ajuda atualizado e `IntegratedArchitectureTest` acrescentado.

Resultado final: 278 testes PHP e 3.792 asserções; 20 testes frontend; arquitetura, tipos, build, PHPStan, Pint e
`git diff --check` aprovados; `permissions:sync` com 82 permissões, `audit:check` com 13 modelos e
`organization:check` sem violações. Instalação limpa, evolução a partir de `48e2191` com hashes preservados,
reversão condicionada e restauração aprovadas. 4.202 requisições integradas com 1 worker e 4.202 com 2 workers, sem
respostas inesperadas e sem crescimento contínuo de memória.

Pendências: as condições operacionais da E09 continuam válidas; acrescenta-se a política de retenção dos logs de
acesso, que mostram parte do token de convite no caminho. A homologação técnica não autoriza a implantação.

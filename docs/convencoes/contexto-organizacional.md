# Contexto organizacional

Contrato oficial da organização do Prospera. Vale para todos os módulos atuais e futuros. A justificativa de cada regra está no [plano 001](../plano-de-acoes/001-arquitetura-organizacional-prospera.md) (decisões DA-01 a DA-25 e PD-01 a PD-06). Quando o código ainda não implementa uma regra, a etapa indicada a implanta; até lá, o comportamento atual continua válido e nenhum código novo pode contrariar este contrato.

Convenções relacionadas:

- [Cargos e permissões](permissoes.md): escopos, recurso-alvo, atribuições e antiescalada.
- [Identidade e vínculos](identidade-e-vinculos.md): identidade global, pessoa, vínculo e atribuições (DA-24).
- [Pessoas e contatos](pessoas-e-contatos.md): dados centrais e dados de cada empresa (DA-25).
- [Auditoria](auditoria.md): contexto organizacional dos eventos e visibilidade.
- [Performance](performance.md): contexto por requisição no Octane, consultas e medição.
- [Checklist de módulos](checklist-modulos.md): critério de aceite de módulos novos.

## Hierarquia

```text
Plataforma SaaS
    └── Tenant (Conta)
         └── Empresas
              └── Estabelecimentos
```

Ao lado da árvore ficam as identidades globais, que se ligam a cada tenant por um vínculo, e os cadastros compartilhados do tenant (Pessoas e Etiquetas).

| Nível | Tabela | Coluna de referência | Nome na interface |
| --- | --- | --- | --- |
| Plataforma | `permissions`, `users.is_saas_admin` | nenhuma | Plataforma SaaS |
| Tenant | `clients` (nome físico preservado) | `account_id` | Conta |
| Empresa | `companies` (E04) | `company_id` | Empresa |
| Estabelecimento | `establishments` (evoluída na E04) | `establishment_id` | Estabelecimento |

No código, o tenant se chama `account` (`account_id`, `AccountContext`). A palavra "tenant" não aparece na interface. A conta nunca é chamada de estabelecimento (corrige A01 na E04).

## Conceitos

| Conceito | Definição | Não é |
| --- | --- | --- |
| Identidade autenticada | Credenciais globais de login (`users`): e-mail de acesso, senha, segundo fator, chaves de acesso | Pessoa do cadastro nem vínculo com uma conta |
| Vínculo com o tenant | Ligação da identidade com uma conta (`account_memberships`, E05), com a pessoa daquela conta e a situação do acesso | Permissão: o vínculo sozinho não concede nada |
| Atribuição | Cargo concedido a um vínculo num escopo (conta, empresa ou estabelecimento) (`role_assignments`, E05) | Disponibilidade do cargo |
| Tenant (Conta) | Contratante do Prospera e fronteira de isolamento | Empresa jurídica nem estabelecimento |
| Empresa | Pessoa jurídica, ou empresário individual, administrada pela conta | Marca, nome fantasia nem unidade |
| Matriz e filial | Classificação jurídica explícita do estabelecimento (DA-04), nunca deduzida da ordem do CNPJ | Sinônimo de principal |
| Estabelecimento principal | Unidade padrão da empresa, criada com ela e sempre ativa | Necessariamente a matriz |
| Estabelecimento operacional | Qualquer estabelecimento ativo onde acontecem operações de unidade (vendas, caixa, estoque, OS) | Depósito nem ponto de venda, que serão entidades do módulo |
| Cadastro compartilhado | Dado do tenant usado por todas as empresas: Pessoas (dados centrais) e Etiquetas convencionais | Dado comercial de uma empresa |
| Recurso de empresa | Dado cuja responsável é uma empresa: relações comerciais, catálogo, financeiro, centros de custo | Dado de uma unidade |
| Recurso de estabelecimento | Dado operacional de uma unidade: saldos, preços locais, vendas, caixas, OS, depósitos | Dado da empresa inteira |

Uma empresa com CNPJ tem raiz própria. Estabelecimentos com CNPJ têm a mesma raiz da empresa. Comparações de pessoa jurídica usam sempre `TaxDocument`, nunca o texto formatado.

## Invariantes

1. Toda empresa pertence a exatamente um tenant. Todo estabelecimento pertence a exatamente uma empresa e ao tenant dela.
2. Toda empresa tem exatamente um estabelecimento principal, da própria empresa e sempre ativo.
3. Empresa ativa tem pelo menos um estabelecimento ativo.
4. Cada empresa tem no máximo uma matriz.
5. Inativar o estabelecimento principal exige promover outro antes. O último estabelecimento ativo só fica inativo junto com a empresa.
6. Empresas e estabelecimentos não são excluídos fisicamente na v1, apenas inativados.
7. Nada é compartilhado automaticamente entre tenants. Entre empresas do mesmo tenant, só se compartilham os dados centrais de Pessoas e as etiquetas convencionais.
8. Operações administrativas da conta não recebem empresa nem estabelecimento fictícios.
9. Empresa, estabelecimento e pessoa são únicos por documento dentro do tenant (PD-03). Nenhuma validação feita por usuário de tenant consulta outro tenant. `clients.document` mantém a unicidade global do contratante, validada só pela plataforma (PD-07).

Como cada invariante é garantido:

| Camada | Uso |
| --- | --- |
| FK composta | Pertencimento: `(company_id, account_id)` e `(establishment_id, company_id)` |
| Índice único parcial | Uma principal e uma matriz por empresa; documentos únicos por tenant |
| CHECK | Consistência da própria linha (a principal está ativa; colunas de escopo das atribuições) |
| Transação com trava | Operações que alteram `active` ou `is_primary` travam a linha da empresa (`select ... for update`) |
| Regra de domínio | Contagens entre linhas (última ativa, promoção antes de inativar) |
| `organization:check` | Detecção, somente leitura, do que depende de várias linhas |

Não use restrições adiáveis (`DEFERRABLE`): a suíte roda em SQLite em memória por regra do projeto.

## Propriedade dos dados

| Classe | Colunas obrigatórias | Exemplos | Nó do recurso |
| --- | --- | --- | --- |
| Plataforma | nenhuma de tenant | catálogo de permissões, planos | plataforma |
| Tenant | `account_id` | cargos, empresas, estabelecimentos (cadastro), pessoas, etiquetas, vínculos, dados da conta | `tenant` |
| Empresa | `account_id`, `company_id` | clientes, fornecedores, produtos, contas financeiras, centros de custo | `company:{id}` |
| Estabelecimento | `account_id`, `company_id`, `establishment_id` | saldos, preços locais, vendas, caixas, OS, depósitos | `establishment:{id}` |
| Habilitação | pivô com `establishment_id` | produto habilitado numa unidade | empresa dona do catálogo |

- As colunas organizacionais redundantes são intencionais: filtro barato e integridade por FK composta.
- Toda leitura passa por uma Query que recebe o contexto ou o descritor autorizado. Escopos globais do Eloquent não são usados para isolamento.
- Nunca buscar por ID sem o tenant e, conforme o nó, a empresa ou a unidade. Um ID de outro escopo responde 404.
- Uma operação entre duas unidades gera um registro em cada unidade, ligados pelo mesmo `operation_id`. Entre empresas distintas, a operação é comercial (venda ou compra entre pessoas jurídicas), nunca movimentação interna.

## Operações sem estabelecimento

São operações sobre recursos de nó `tenant` (ou `person`): dados da conta, cargos, empresas e estabelecimentos como cadastro, pessoas, etiquetas, usuários e vínculos.

- Usam rotas administrativas sem segmento de contexto organizacional (`/conta/...`).
- São auditadas sem `company_id` e sem `establishment_id`, salvo quando o próprio evento é de uma empresa ou unidade (cadastro de empresa, atribuição em um escopo).
- Funcionam igualmente para tenants com uma ou várias empresas.

## Empresa pequena

Um tenant com uma empresa e um estabelecimento não percebe a estrutura:

- o seletor organizacional fica oculto;
- rotas operacionais sem código redirecionam para o estabelecimento principal, e o contexto continua definido na URL;
- o filtro de empresa some das listagens, e o menu Empresas leva direto à edição da empresa;
- formulários de Pessoas mantêm as seções atuais, sem a distinção entre dados centrais e dados desta empresa;
- cargos com abrangência "Toda a conta" são o padrão.

A interface só passa a mostrar empresa, estabelecimento e abrangência quando existir mais de uma opção real.

## Contexto da requisição

- O contexto organizacional vem da URL e é validado no servidor a cada requisição (DA-11). A sessão nunca é autoridade de contexto; guarda no máximo o último contexto usado, para redirecionar após o login.
- O middleware de contexto confere, nesta ordem: vínculo ativo; empresa e unidade pertencentes ao tenant; alguma atribuição que cubra o nó; empresa e unidade ativas nas rotas operacionais. O contexto não concede permissões: a rota ou a Action verifica a permissão da operação.
- Toda escrita vai para uma URL que contém o contexto, e o servidor confere que o recurso alterado pertence a ele. Uma aba nunca usa o contexto de outra.
- O frontend monta as URLs relativas à página atual e não guarda estado global de contexto.
- Jobs futuros levam `account_id`, `company_id`, `establishment_id`, `membership_id` e `actor_id` e reautorizam ao executar. Chaves de cache incluem o tenant e o nó.

## Nomenclatura

### Código

| Item | Nome fechado |
| --- | --- |
| Domínio organizacional | `App\Domains\Organizacao` (`Application/Actions`, `Application/Data`, `Application/Queries`, `Domain/Models`, `Domain/DTOs`, `UI/Controllers`) |
| Models | `Company` (`companies`), `Establishment` (`establishments`) em `Organizacao`; `AccountMembership` (`account_memberships`), `RoleAssignment` (`role_assignments`) e `RoleAvailability` (`role_availability`) em `Acessos` |
| Documento fiscal | `App\Support\TaxDocument`, compartilhado por Pessoas, Conta, Empresas e Estabelecimentos |
| Serviços de contexto | `App\Domains\Acessos\Application\Context\AccountContext` (E03), `EffectivePermissions` (E03, estendido na E05) e `OrganizationContext` (E06), todos com binding `scoped` |
| Middleware | `App\Http\Middleware\ResolveOrganizationContext` (E06), aplicado só aos grupos de rota com contexto |
| Comando | `organization:check` (E04), somente leitura |
| Actions previstas | `CreateCompanyAction`, `SaveCompanyAction`, `SaveEstablishmentAction`, `PromotePrimaryEstablishmentAction`, `SaveRoleAssignmentsAction` |
| Colunas | `account_id`, `company_id`, `establishment_id`, `membership_id`; flags `is_primary`, `is_headquarters`, `active`; código `code` |

### Identificadores de empresa e estabelecimento

- **Administração:** rotas administrativas usam o ID numérico, como as telas atuais (`/conta/empresas/{companyId}/editar`, com `whereNumber`).
- **Operação:** rotas operacionais usam o código (`code`) da empresa ou do estabelecimento.
- **Formato do código:** letras minúsculas sem acento, números e hífen, de 2 a 40 caracteres, começando por letra ou número.
- **Unicidade:** o código é único por tenant, inclusive o de estabelecimento, que por isso determina a empresa.
- **Sugestão:** gerada a partir do nome fantasia. Palavras reservadas não podem ser usadas: `todos`, `cadastrar`, `editar`, `visualizar`.
- **Imutável na v1:** o código não muda depois de criado. Alterá-lo exigirá redirecionamento das URLs antigas, numa decisão própria.

### URLs

| Tipo de tela | Padrão | Exemplo | Etapa |
| --- | --- | --- | --- |
| Administração da conta (nó tenant e cadastros) | URLs atuais preservadas em `/conta/...` | `/conta/pessoas`, `/conta/cargos`, `/conta/editar` | atual |
| Cadastro de empresas e estabelecimentos | `/conta/empresas`, `/conta/estabelecimentos` | `/conta/estabelecimentos/12/editar` | E04 |
| Módulo de empresa | `/conta/empresa/{empresa}/...` | `/conta/empresa/gartan/clientes` | E06 |
| Módulo de estabelecimento | `/conta/estabelecimento/{estabelecimento}/...` | `/conta/estabelecimento/gartan-guanambi/vendas` | E06 |
| Consolidado | código reservado `todos`, somente em telas que declaram suporte | `/conta/estabelecimento/todos/vendas` | por módulo |
| Tenant na URL (PD-04) | `/contas/{conta}/...` no lugar de `/conta/...`, só com vários vínculos; as URLs sem prefixo redirecionam para a conta escolhida | `/contas/grupo-gartan/pessoas` | E09 |
| Plataforma | `/saas/...` preservado | `/saas/contas` | atual |
| Configurações pessoais e ajuda | `/settings/...` e `/ajuda`, sem prefixo de conta | `/settings/security` | atual |

- O singular (`empresa`, `estabelecimento`) identifica o contexto operacional. O plural (`empresas`, `estabelecimentos`) identifica o cadastro administrativo. Os dois não colidem.
- No consolidado, filtros de empresa e de unidade só restringem dentro do conjunto autorizado.
- Os nomes de rota seguem o módulo da permissão (`account.companies.index`, `account.establishments.update`). Rotas operacionais usam o módulo do recurso (`account.customers.index`), com o contexto no grupo.
- Rotas não são criadas nesta convenção. Cada etapa cria as suas.

### Interface

| Termo | Uso |
| --- | --- |
| Conta | O tenant. Menu "Dados da conta" para os dados do contratante (hoje "estabelecimento" em Minha conta) |
| Empresas | Menu e cadastro de empresas |
| Estabelecimentos | Menu e cadastro de unidades. Em textos de ajuda, "unidade" é aceito como sinônimo |
| Estabelecimento principal | Badge "Principal" |
| Matriz e Filial | Badges da classificação jurídica |
| Abrangência | Disponibilidade do cargo: "Toda a conta (inclui empresas e estabelecimentos futuros)" ou "Restrita" |
| Onde | Coluna da atribuição: "Toda a conta", nome da empresa ou nome do estabelecimento |
| Acesso à conta | Vínculo. Ações "Suspender acesso" e "Reativar acesso" |
| E-mail de acesso | E-mail da identidade, sempre somente leitura para administradores |
| Dados centrais e Dados desta empresa | Seções do formulário de Pessoas ([Pessoas e contatos](pessoas-e-contatos.md)) |
| Todos os autorizados | Opção consolidada do seletor |

Sidebar da conta, a partir das etapas indicadas:

```text
Início                     (/conta; hoje rotulado Minha conta)
Cadastros
├── Pessoas
└── Etiquetas
Organização
├── Dados da conta         (/conta/editar)
├── Empresas               (E04)
└── Estabelecimentos       (E04)
Controle de acesso
├── Usuários               (E05)
└── Cargos
Auditoria
Central de Ajuda
```

Itens aparecem conforme `auth.permissions`. Não há item de Permissões; o resumo de acesso fica em Usuários e Cargos (PD-06). Configurações pessoais continuam no menu do avatar.

## Componentes

Telas organizacionais reutilizam `PageHeader`, `BusinessList`, `BusinessListSearch`, `useBusinessListFilters`, `BusinessForm`, `SelectControl`, `SelectChecklist`, `Modal`, `PreviewSection`, `StatusLabel` e os campos de identidade, endereço e contato de Pessoas. O seletor organizacional usa `SelectControl` ou `DropdownMenu` na `TopBarPrimary`. Componentes paralelos a esses não são aceitos. Ajuda, tour e validação visual seguem as convenções do AGENTS.md.

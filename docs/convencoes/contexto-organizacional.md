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

- Usam rotas administrativas sem segmento de contexto organizacional (`/contas/{conta}/...`).
- São auditadas sem `company_id` e sem `establishment_id`, salvo quando o próprio evento é de uma empresa ou unidade (cadastro de empresa, atribuição em um escopo).
- Funcionam igualmente para tenants com uma ou várias empresas.

## Empresa pequena

Um tenant com uma empresa e um estabelecimento não percebe a estrutura:

- o seletor organizacional fica oculto;
- rotas operacionais sem código redirecionam para o estabelecimento principal, e o contexto continua definido na URL;
- o filtro de empresa some das listagens, e o menu Empresas leva direto à edição da empresa;
- as fichas de Clientes e Fornecedores não pedem a escolha de empresa e mantêm apenas o aviso de que os dados centrais pertencem ao cadastro compartilhado da conta;
- cargos com abrangência "Toda a conta" são o padrão.

A interface só passa a mostrar empresa, estabelecimento e abrangência quando existir mais de uma opção real.

## Contexto da requisição

- O contexto organizacional vem da URL e é validado no servidor a cada requisição (DA-11). A área de trabalho da aba é mantida em `sessionStorage`, por usuário e conta, e revalidada contra a lista autorizada nas telas da conta. Nas consultas e escritas operacionais, a URL explícita determina o escopo da requisição. O padrão fica no vínculo entre usuário e conta e só é usado na entrada sem código.
- O middleware de contexto confere, nesta ordem: vínculo ativo; empresa e unidade pertencentes ao tenant; alguma atribuição que cubra o nó; empresa e unidade ativas nas rotas operacionais. O contexto não concede permissões: a rota ou a Action verifica a permissão da operação.
- Toda escrita vai para uma URL que contém o contexto, e o servidor confere que o recurso alterado pertence a ele. Uma aba nunca usa o contexto de outra.
- O frontend monta as URLs relativas à página atual e não guarda estado global de contexto.
- Jobs futuros levam `account_id`, `company_id`, `establishment_id`, `membership_id` e `actor_id` e reautorizam ao executar. Chaves de cache incluem o tenant e o nó.

### Implementação (E06)

- **Serviço:** `OrganizationContext` (`scoped`) informa `mode()` (`tenant`, `company`, `establishment` ou `consolidated`), `accountId()`, `membershipId()`, `actorId()` (na inspeção, o administrador SaaS), `company()`, `establishment()`, `node()` e `scopeFor($user, $permissao)`, o descritor que a Query aplica no SQL já restrito à URL. Fora dos grupos com contexto, o modo é `tenant`.
- **Acesso ao contexto:** é a cobertura do nó por alguma atribuição válida do vínculo ativo (`EffectivePermissions::navigableNodes`), calculada com as atribuições já carregadas, sem consulta extra. Uma atribuição de estabelecimento não abre o contexto da empresa.
- **Middleware:** `organization:company` ou `organization:establishment`, com as opções `consolidated` (o módulo aceita `todos` com `?unidades=codigo-a,codigo-b`, somente leitura) e `history` (leitura em empresa ou unidade inativa). Código inexistente, de outro tenant ou sem atribuição responde 404; contexto inativo responde 403 com orientação; vínculo suspenso responde 403. A abertura direta pelo navegador recebe só o HTML inicial, e a aplicação valida o contexto na requisição JSON.
- **Rotas:** `routes/account.php` define as rotas `account.*` com códigos de `OrganizationCode::ROUTE_PATTERN`. A entrada `/contas/{conta}/estabelecimento` abre a seleção enviada pela aba (`?unidades=`, revalidada; unidade indisponível responde 404) ou, sem ela, o padrão filtrado pelas unidades ainda navegáveis ou a primeira delas. `?para=modulo` só aceita um caminho que corresponda a uma rota GET do grupo de unidade com `organization:establishment` (`WorkspaceDestinationData`); qualquer outro valor responde 404. Com uma unidade, abre o módulo nela; com várias, preserva a seleção em `todos/{modulo}` quando o módulo declara `consolidated` e, sem essa opção, leva à área de trabalho com **Escolha o estabelecimento**. O sistema nunca escolhe uma unidade pelo usuário. `GET /contas/{conta}/area-de-trabalho/padrao` consulta o padrão efetivo e `PUT` grava os códigos no vínculo próprio ativo. A gravação fica indisponível durante inspeção.
- **Leitura consolidada:** `todos` sem parâmetro consulta todas as unidades navegáveis. Com `unidades`, qualquer código inválido ou indisponível retorna 404. O módulo chama `scopeFor($user, $ability)` e aplica o descritor a partir de uma consulta já filtrada pelo tenant, antes da paginação, contagem ou agregação. O descritor consolidado contém apenas IDs de estabelecimentos ativos selecionados cobertos pela permissão da operação.
- **Escrita:** o módulo verifica a permissão da ação sobre `node()` e filtra por `account_id` e pelo nó da URL (`where establishment_id = ?`). Um registro de outra unidade responde 404. A seleção e o padrão não concedem permissões.
- **Frontend:** `contextualUrl` é o único resolvedor de endereços `~/`. `navigate` o aplica com a página atual da aba; `ApiLink` e `useApiForm` resolvem pela página que os declarou, e a resolução é idempotente. Por isso, `ApiLink`, `ApiForm`, `useApiForm`, `navigate` e `useBusinessListFilters('~/modulo')` aceitam `~/`. `apiRequest` não resolve `~/`: as chamadas diretas usam `useContextUrl`. Não crie resolvedores por módulo. Links e filtros relativos ao consolidado preservam `unidades`; a paginação usa `withQueryString()`. O contrato é coberto por `npm run test:frontend`. Nas telas sem área de trabalho, o seletor termina com **Abrir área de trabalho**, que aplica a seleção da aba e passa pela entrada sem código. Ao abrir uma unidade do resumo, `voltar-unidades` preserva o link de retorno e não interfere no destino das operações. O seletor na `TopBarPrimary` é alimentado por `organization_context` das rotas `account.*` e aparece a partir de duas unidades autorizadas. Nas telas com contexto, a seleção exibida vem da URL (os códigos de `todos` ou o `voltar-unidades` da unidade aberta); nas telas da conta, vem do armazenamento da aba e, numa aba nova, do padrão pessoal.

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

- **Administração:** rotas administrativas usam o ID numérico, como as telas atuais (`/contas/{conta}/empresas/{companyId}/editar`, com `whereNumber`).
- **Operação:** rotas operacionais usam o código (`code`) da empresa ou do estabelecimento.
- **Formato do código:** letras minúsculas sem acento, números e hífen, de 2 a 40 caracteres, começando por letra ou número.
- **Unicidade:** o código é único por tenant, inclusive o de estabelecimento, que por isso determina a empresa.
- **Sugestão:** gerada a partir do nome fantasia. Palavras reservadas não podem ser usadas: `todos`, `cadastrar`, `editar`, `visualizar`.
- **Imutável na v1:** o código não muda depois de criado. Alterá-lo exigirá redirecionamento das URLs antigas, numa decisão própria.

### URLs

| Tipo de tela | Padrão | Exemplo | Etapa |
| --- | --- | --- | --- |
| Administração da conta (nó tenant e cadastros) | URLs explícitas em `/contas/{conta}/...` | `/contas/{conta}/etiquetas`, `/contas/{conta}/cargos`, `/contas/{conta}/editar` | atual |
| Cadastro de empresas e estabelecimentos | `/contas/{conta}/empresas`, `/contas/{conta}/estabelecimentos` | `/contas/{conta}/estabelecimentos/12/editar` | E04 |
| Módulo de empresa | `/contas/{conta}/empresa/{empresa}/...` | `/contas/{conta}/empresa/gartan/clientes` | E06 |
| Módulo de estabelecimento | `/contas/{conta}/estabelecimento/{estabelecimento}/...` | `/contas/{conta}/estabelecimento/gartan-guanambi/vendas` | E06 |
| Consolidado | código reservado `todos`, somente em telas que declaram suporte | `/contas/{conta}/estabelecimento/todos?unidades=guanambi,janauba` | por módulo |
| Tenant na URL (PD-04 revisada na E09) | `/contas/{conta}/...` para qualquer número de vínculos. GET/HEAD de `/conta/...` só redirecionam sem ambiguidade; escritas antigas são recusadas | `/contas/grupo-gartan/usuarios` | E09 |
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
| Dados centrais e Dados desta empresa | Seções dos formulários comerciais ([Pessoas e contatos](pessoas-e-contatos.md)) |
| Todos os autorizados | Opção consolidada do seletor |

Sidebar da conta, a partir das etapas indicadas:

```text
Início                     (/contas/{conta}; hoje rotulado Minha conta)
Cadastros
├── Pessoas
└── Etiquetas
Organização
├── Dados da conta         (/contas/{conta}/editar)
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


## Identidade global na E09

`users.account_id` foi removida. `ResolveAccountContext` valida o código público e o vínculo ativo
em cada requisição. A sessão não escolhe o tenant. Contas com vínculo único também usam o código
na URL. A seleção lista só os vínculos da identidade; códigos não autorizam operações.

A chave `prospera.multiple_accounts` controla novos convites para outras contas, não a resolução
nem a autorização. Vínculos existentes continuam independentes com a chave desligada. Preferências
ficam em cada vínculo, e a seleção operacional de unidades permanece própria de cada aba.

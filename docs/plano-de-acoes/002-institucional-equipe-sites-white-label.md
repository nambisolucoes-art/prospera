# 002: Institucional, equipe e sites white label do Prospera

| Campo | Valor |
| --- | --- |
| Documento | Plano oficial de execução da frente Institucional, Equipe e Sites White Label |
| Situação do documento | **Contrato institucional formalizado (S02 concluída em 24/11/2026), com decisões aprovadas e pendências explícitas.** Revisão 3 executou a S02 (seção 27) sobre a investigação da S01 (seção 26). **S01 a S06 estão concluídas e homologadas tecnicamente** (seções 28 a 33; as seções 28 a 31 preservam a classificação original de revisão). S07 a S15 não foram iniciadas |
| Base de código auditada | Branch `feat/pagina-inicial`, commit `33fd8df`, árvore de trabalho limpa |
| Data da auditoria | 24/11/2026 |
| Antecessor | Briefing `prospera_plano_institucional_white_label.md` (fora do repositório) e a análise aprovada na sessão de 24/11/2026 |
| Plano relacionado | [001: Arquitetura organizacional](001-arquitetura-organizacional-prospera.md), E01 a E10 homologadas tecnicamente. A implantação do 001 em produção continua pendente de autorização própria |
| Revisão | 4 (24/11/2026): revisão final dirigida da S02 (seção 27.8): PD2-16 aprovada com `select-team`; regras do host em `public_hosts`; orçamento e segurança do processamento de mídia com fluxo assíncrono incorporado pela etapa se o síncrono reprovar; contrato de frescor do cache. Revisão 3 (24/11/2026): S02 executada; decisões PD2-01, 04, 06, 08, 09, 10, 12, 13 e 15 aprovadas; execução síncrona, assíncrona e agendada separada por etapa (DA2-21); host público do diretório (DA2-19); perfil público do colaborador (DA2-20); DA2-22 a DA2-23; convenção `docs/convencoes/institucional.md` e emendas por adição. Revisão 2: S01 executada, correções A a F, DA2-16 a DA2-18, PD2-13 a PD2-15. Registro na seção 25 e conflitos na seção 27 |
| Regra de uso | Executar uma etapa por vez, somente após autorização explícita. Nenhuma etapa decide sozinha uma decisão pendente da seção 23. **A convenção normativa é [`institucional.md`](../convencoes/institucional.md); este plano a justifica e a detalha. Em caso de divergência, vale a convenção e o plano é corrigido** |

Legenda, a mesma do plano 001:

- **[C]** comprovado no código ou nos arquivos do repositório nesta auditoria.
- **[P]** parcialmente implementado.
- **[A]** ausente.
- **[R]** recomendação arquitetural deste plano.
- **[DP]** decisão de produto já tomada pelo responsável (briefing e sessão de 24/11/2026).
- **[H]** hipótese ainda não verificada. Cada [H] vira tarefa de verificação na S01.
- **[PD]** pendente de decisão (seção 23).

- **[AP]** decisão aprovada pelo responsável na sessão da S02 (24/11/2026).

Códigos próprios deste plano: **DA2-nn** (decisões arquiteturais), **PD2-nn** (decisões de produto e infraestrutura), **S01 a S15** (etapas). Eles não colidem com DA-nn, PD-nn e E01 a E10 do plano 001.

## Índice

1. [Resumo executivo](#01-resumo-executivo)
2. [Inventário da implementação atual](#02-inventário-da-implementação-atual)
3. [Premissas confirmadas no código](#03-premissas-confirmadas-no-código)
4. [Fatos, hipóteses e decisões](#04-fatos-hipóteses-e-decisões)
5. [Modelo conceitual de entidades](#05-modelo-conceitual-de-entidades)
6. [Diagrama de relações e cardinalidades](#06-diagrama-de-relações-e-cardinalidades)
7. [Regras de tenant e escopo organizacional](#07-regras-de-tenant-e-escopo-organizacional)
8. [Decisões arquiteturais (ADRs)](#08-decisões-arquiteturais-adrs)
9. [Herança e resolução efetiva](#09-herança-e-resolução-efetiva)
10. [Contratos de colaboradores e lotações](#10-contratos-de-colaboradores-e-lotações)
11. [Contratos de sites e publicação](#11-contratos-de-sites-e-publicação)
12. [Segurança, RBAC, auditoria e LGPD](#12-segurança-rbac-auditoria-e-lgpd)
13. [Mídia, acessibilidade e SEO](#13-mídia-acessibilidade-e-seo)
14. [Busca e diretório](#14-busca-e-diretório)
15. [Integração futura com produtos e serviços](#15-integração-futura-com-produtos-e-serviços)
16. [Mapa de telas](#16-mapa-de-telas)
17. [APIs e Actions em nível de contrato](#17-apis-e-actions-em-nível-de-contrato)
18. [Migrations propostas](#18-migrations-propostas)
19. [Plano incremental](#19-plano-incremental)
20. [Testes](#20-testes)
21. [Ajuda e tours](#21-ajuda-e-tours)
22. [Riscos, alternativas e reversão](#22-riscos-alternativas-e-reversão)
23. [Decisões de produto e infraestrutura](#23-decisões-de-produto-e-infraestrutura)
24. [Esforço, dependências e evolução sem quebrar o plano 001](#24-esforço-dependências-e-evolução-sem-quebrar-o-plano-001)
25. [Quadro de acompanhamento](#25-quadro-de-acompanhamento)
26. [Registro da S01 e evidências](#26-registro-da-s01-e-evidências)
27. [Registro da S02, conflitos e condições de início](#27-registro-da-s02-conflitos-e-condições-de-início)

---

## 01. Resumo executivo

O Prospera passa a oferecer presença digital institucional administrada pela mesma retaguarda do ERP: ficha institucional de empresa e de estabelecimento, equipe com perfil público opcional, sites corporativos e de unidade, diretório e, em etapa posterior, domínios próprios. O objetivo é um mini CMS white label sobre as entidades canônicas já existentes, sem duplicar cadastros e sem criar uma instalação por cliente.

**Recomendação principal.**

1. **Colaborador é um papel de Pessoa**, no mesmo molde de Cliente e Fornecedor: uma relação por empresa (`employees`), com etiqueta especial derivada, sem repetir identidade, documentos ou contatos centrais. Função, departamento e lotação ficam em períodos (`employee_assignments`), o que dá vigência, histórico e vários estabelecimentos sem copiar pessoa. O vínculo com usuário é opcional e **derivado** de `account_memberships.person_id`, sem coluna nova.
2. **A Empresa continua sendo o agrupamento principal.** Não há entidade Marca neste ciclo. O modelo não impede sua criação futura (DA2-12).
3. **Site é uma entidade editorial própria**, ligada a uma empresa e, quando for de unidade, a um estabelecimento da mesma empresa por FK composta. Uma empresa pode ter vários sites. Páginas têm revisões imutáveis, com blocos tipados em JSON versionado, rascunho, publicação e reversão.
4. **A herança é por seção**, com três modos (`inherit`, `override`, `disabled`) e um único resolvedor. Ausência, limpeza e desativação têm representações distintas e testáveis.
5. **O público nunca lê as tabelas privadas.** Rotas públicas são sem sessão, resolvem o site pelo host e usam Queries e Resources com lista de campos permitidos. O diretório lê uma projeção própria, gravada somente na publicação.
6. **Domínio próprio é a última etapa de entrega**, depois da publicação em subdomínio gerenciado e do diretório (S14).
7. **O GNAI2 é referência de regras e de acessibilidade, não de código.** O módulo `Website` dele é uma linha de configuração com seções fixas em JSON, sem tenant, versões, rascunho ou domínio, e com schema MySQL (seção 02.3).
8. **Dado operacional e conteúdo editorial têm regras diferentes** (DA2-16). Conteúdo editorial tem valor de trabalho e valor publicado, e só chega ao público por publicação explícita. Dado operacional (horário, contato, endereço da unidade) vale ao salvar. Retratações (revogar consentimento, inativar colaborador, despublicar) valem na hora. Editar rascunho nunca altera a publicação atual.
9. **O diretório sem site tem autoridade própria** (DA2-17): a ficha pública é um `directory_listing` com publicação e despublicação próprias, não depende de página nem de `PublishPageAction`.

**Conflitos concretos com decisões vigentes** (detalhados na seção 08), cada um com alternativa:

- A convenção de Pessoas diz que o filtro e o cadastro de Etiquetas oferecem somente Clientes e Fornecedores e que Pessoas não tem menu próprio. Colaborador entra como módulo próprio e etiqueta especial derivada; a convenção é emendada na S02.
- O contexto organizacional vem da URL e da sessão autenticada. Um site público não tem sessão. Cria-se um contexto público separado, resolvido pelo host, sem tocar no `OrganizationContext` (DA2-08).
- O nome "Cargo" já designa o papel de acesso (RBAC). A atividade profissional do colaborador chama-se **Função** na interface (PD2-01, aprovada). Cargo continua reservado a papéis de acesso e permissões.
- O app não traz biblioteca de imagem nem armazenamento além do disco local. O GD instalado atende o primeiro corte sem dependência nova; armazenamento e CDN seguem pendentes (PD2-05). A extensão `exif` só entra se a S08 provar a necessidade (PD2-13, aprovada).
- Não há worker de fila nem agendador. O plano separa o que é síncrono, assíncrono e periódico por etapa (DA2-21 e seção 19.1). **A S14 é a primeira etapa que exige worker e agendador.**
- O diretório sem site precisa de um host público próprio, que o contexto por host da revisão 2 não previa (DA2-19).
- A auditoria vigente não registra tentativa negada. O cenário 17 foi corrigido para negar sem evento de negócio (seção 12.1).

**Tamanho.** São 15 etapas. S03 a S05 formam o ciclo de equipe, sem nada público. S10 é somente retaguarda (editor e modelo editorial). A primeira superfície pública é a S11. A S15 é o fechamento técnico integrado; a homologação funcional com usuários é uma atividade posterior e separada.

---

## 02. Inventário da implementação atual

Verificado em 24/11/2026 sobre o commit `33fd8df`. Itens [H] não foram abertos.

### 02.1 Estrutura organizacional [C]

| Item | Evidência |
| --- | --- |
| Tenant é `clients`, referenciado por `account_id` | `docs/convencoes/contexto-organizacional.md` |
| `companies`: `account_id`, `code`, `name`, `trade_name`, `person_type`, `tax_root`, `individual_document`, `tax_regime`, `main_cnae`, `active`. **Sem endereço, contatos, fuso ou identidade visual** | `database/migrations/2026_11_20_000003_create_organization_structure.php` |
| `establishments`: `account_id`, `company_id`, `code`, `name`, `trade_name`, `tax_document`, inscrições, `is_headquarters`, `is_primary`, `active`, `address` (JSON), `phone`, `whatsapp`, `email`, `additional_phones`, `additional_emails`. **Sem fuso** | mesma migration |
| FKs compostas `(company_id, account_id)` e unicidade `(id, account_id)`; um principal e uma matriz por empresa, por índice parcial | mesma migration |
| Models `Company` e `Establishment` | `app/Domains/Organizacao/Domain/Models/` |
| Código imutável por tenant, regras de URL e nomes | seção "Nomenclatura" de `contexto-organizacional.md` |
| `app.timezone` é `UTC` | `config/app.php` |

### 02.2 Pessoas, papéis, usuários e acesso [C]

| Item | Evidência |
| --- | --- |
| `people` é cadastro central do tenant, com identificação, contatos, endereço, `website` e até 20 telefones e e-mails extras | `app/Domains/Pessoas/Domain/Models/Person.php`, `docs/convencoes/pessoas-e-contatos.md` |
| `customers` e `suppliers` são relações por empresa: `account_id`, `company_id`, `person_id`, `active`, único `(company_id, person_id)`, FKs compostas para `companies` e `people` | `database/migrations/2026_11_21_000001_add_company_to_commercial_relations.php`, `app/Domains/Fornecedores/Domain/Models/Supplier.php` |
| `CommercialRole` tem somente `Customer` e `Supplier`; o método `companies()` resolve o descritor de empresas com um `match` por papel e ação | `app/Domains/Pessoas/Domain/Enums/CommercialRole.php` |
| Etiquetas especiais: migrations criam `supplier` e `customer` para as contas existentes; contas novas as recebem de `Tag::supplierTag` e `Tag::customerTag` (criação preguiçosa, com sufixo se o nome colide), chamadas em `RegisterAccountMembershipAction`. A derivação por relação está em `PeopleDirectoryQuery` e `PersonCommercialRelationsQuery` | `app/Domains/Etiquetas/Domain/Models/Tag.php`, `RegisterAccountMembershipAction.php`, migrations `000007` e `000010` de 18/11 |
| `users` é identidade global; `account_memberships` liga identidade, conta e pessoa; `role_assignments` concede cargo por escopo (`tenant`, `company`, `establishment`) com CHECK de colunas | `2026_11_20_000007_create_contextual_access.php`, `identidade-e-vinculos.md` |
| Não existe Departamento, função profissional, Colaborador ou lotação em `app` nem em `database` | busca por "departamento" sem resultados |

### 02.3 Permissões, auditoria e contexto [C]

| Item | Evidência |
| --- | --- |
| Módulos, ações e **alvos admitidos** em `config/permissions.php`; `account.customers.*` e `account.suppliers.*` admitem só `company` | `config/permissions.php` |
| `OrganizationContext` (`scoped`), middleware `organization:company` e `organization:establishment`, grupos `empresa/{empresa}` e `estabelecimento/{estabelecimento}` sob `contas/{conta}` | `routes/account.php` (linhas 267 e 302), `contexto-organizacional.md` |
| Auditoria central única (`audit_records`), `Auditable`, `AuditsChanges`, `audit:check`, `AuditLog::transaction()` | `docs/convencoes/auditoria.md`, `AGENTS.md` |
| Caddyfile próprio com redação de URLs que levam credencial; o README exige acrescentar o prefixo de toda rota nova com segredo no caminho | `docker/Caddyfile`, `README.md` |

### 02.4 Mídia, fila, cache e site [C] e [H]

| Item | Estado |
| --- | --- |
| Único armazenamento de arquivo de negócio: foto de perfil em `Storage::disk('local')` | [C] `SaveProfilePhotoAction.php`, `ProfilePhotoController.php` |
| Biblioteca de imagem | [C] Não consta em `composer.json`. Na imagem Docker há **GD** com JPEG, PNG, WebP e AVIF; **não há Imagick nem `exif`** (seção 26, H1) |
| `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`; não há serviço Redis no Compose | [C] `.env.example`, `compose.yaml` (serviços `app`, `postgres` e `mailpit`) |
| Fila e agendador | [C] Nenhum worker de fila nem agendador roda no Compose, e o código não despacha nenhum job (seção 26, H3) |
| Site ou CMS para clientes, domínio por tenant | [A] A raiz `/` serve apenas a landing do próprio Prospera (`resources/views/landing.blade.php`) |
| Busca textual | [C] `pg_trgm` 1.6 e `unaccent` 1.1 disponíveis no `postgres:18` (18.7), ambos confiáveis (seção 26, H2). Nenhuma extensão é criada no banco da aplicação |
| Produtos e serviços | [A] só contrato futuro, plano 001 seção 10.3 |

### 02.5 GNAI2 (referência) [C]

Auditado em `/home/marley/Projects/GNAI2`.

| Aspecto | Achado | Consequência |
| --- | --- | --- |
| Modelo | `website_settings` com uma linha: nome, slogan, logo, cor primária, contatos, redes, rodapé e `sections` em JSON | Não é multi-tenant, multi-site nem tem páginas |
| Seções | Enum fixo de 12 tipos (`WebsiteSectionType`: hero, about, pillars, audience, guidance, faq, resources, features, team, events, publications, contact) | É um catálogo de blocos úteis, não um construtor |
| Versões, rascunho, preview, domínio, cache | Não encontrados | Tudo isso é novo |
| Banco | Migration com `InnoDB` e `utf8mb4` (MySQL) | O schema não é portável para PostgreSQL |
| Reaproveitável como ideia | Value objects de aparência (`HeroAppearance`, `TeamAppearance` e afins), regras de contraste (`WebsiteContrast`, `WebsiteAccessibility`, `WebsiteAppearanceAccessibility`), armazenamento de mídia (`WebsiteMediaStorage`), cobertura de testes | Conferir licença e autoria (PD2-03) antes de portar qualquer trecho |
| Licença | `composer.json` do GNAI2 declara `MIT`. Titularidade do código e dos textos não foi confirmada | Bloqueio documentado em PD2-03 |

Conclusão: o GNAI2 fornece o **catálogo de tipos de bloco** e as **regras de acessibilidade**. O modelo de dados, a resolução de site, a publicação e a segurança pública são novos.

---

## 03. Premissas confirmadas no código

1. A árvore Conta, Empresa e Estabelecimento existe com integridade por FK composta. Código novo referencia `(company_id, account_id)` e `(establishment_id, company_id)`.
2. Pessoa é do tenant. Papéis comerciais são relações por empresa e não repetem dados centrais. O mesmo molde serve para Colaborador.
3. Etiquetas especiais são criadas por migration para contas existentes e preguiçosamente por `Tag::specialTag` nas contas novas, e derivadas das relações. O mecanismo comporta uma terceira etiqueta, mas hoje enumera só duas em vários pontos (seção 26, H6).
4. Usuário e Pessoa são separados por `account_memberships`. A ligação colaborador e login pode ser consultada por `person_id` sem coluna nova.
5. Permissão é uma por ação, com alvos declarados em `config/permissions.php`. O alvo de uma relação por empresa é `company`.
6. Rotas operacionais levam o contexto na URL. Rotas administrativas de tenant usam `/contas/{conta}/...`.
7. Os testes usam apenas SQLite em memória. Qualquer recurso exclusivo do PostgreSQL (`pg_trgm`, `unaccent`, exclusão por intervalo) não pode ser a única garantia testada na suíte.
8. Não há mídia, domínio, site ou diretório. Tudo isso é construído do zero.

---

## 04. Fatos, hipóteses e decisões

| Tipo | Item |
| --- | --- |
| **Fato** | Seções 02 e 03 |
| **Decisão do responsável [DP]** | Colaborador é papel de Pessoa. Sem entidade Marca neste ciclo. Empresa e estabelecimento podem ter publicação própria, sem limite permanente de uma página por empresa. Herança explícita com INHERIT, OVERRIDE e DISABLED. Domínio próprio no plano, depois do domínio ou subdomínio gerenciado. GNAI2 é referência, e a titularidade deve ser conferida antes de reutilizar código |
| **Decisão aprovada [AP]** | PD2-01 (Função), PD2-04 (diretório por conta), PD2-06 (editar e publicar distintos, sem aprovação em duas etapas), PD2-08 (fuso IANA por estabelecimento), PD2-09 (prévia autenticada), PD2-10 (Redis só após medição), PD2-12 (categorias em tabela própria), PD2-13 (sem `exif` por ausência), PD2-15 (blocos tipados com formatação restrita) e PD2-16 (gestor de unidade seleciona a equipe da unidade com `select-team`, sem alterar o perfil corporativo), todas em 24/11/2026 |
| **Decisão proposta [R]** | DA2-01 a DA2-23 (seção 08). Os DA2-19 a DA2-23 foram introduzidos pela S02 para fechar contradições e aguardam ratificação junto com o plano |
| **Hipótese [H]** | H1 a H7 foram verificadas na S01 (seção 26). Restam dependências de infraestrutura e de decisão: PD2-02, PD2-05 (armazenamento e CDN), PD2-14. A medição do cache público (H4) só é possível com o site público e fica na S11 |
| **Pendente [PD]** | PD2-02, 03, 05 (parcial), 07, 11 e 14 (seção 23) |

Nenhuma hipótese é tratada como fato neste documento. Cada [H] tem verificação prevista na S01, com o resultado registrado na seção 25.

---

## 05. Modelo conceitual de entidades

Distinções obrigatórias: Conta, Empresa, Estabelecimento, Departamento, Função profissional, Cargo (acesso), Colaborador, Usuário, Site, Página e Publicação são conceitos diferentes.

### 05.1 Equipe

| Entidade | Tabela | Classe de propriedade | Observação |
| --- | --- | --- | --- |
| Departamento | `departments` | Empresa | Organização interna. Nunca é filial. Nome único por empresa |
| Função profissional | `job_titles` | Empresa | Título exibível ("Gerente comercial"). Nada a ver com `roles`. Nome de interface: **Função** (PD2-01, aprovada) |
| Colaborador | `employees` | Empresa | Relação da Pessoa com a empresa. Uma por `(company_id, person_id)` |
| Lotação | `employee_assignments` | Empresa, com `establishment_id` | Período em que o colaborador atua num estabelecimento, com função e departamento |
| Perfil público do colaborador | `employee_public_profiles` | Empresa | Um por colaborador. Autorização e apresentação: consentimento, flags de exposição, textos e foto. **Não guarda contato**, que é lido da lotação (seção 10.6). Classe e permissões em DA2-20 |

Regras:

- O colaborador **não** guarda nome, CPF, endereço, e-mail pessoal ou telefone pessoal. Esses dados são de `people` e seguem [Pessoas e contatos](../convencoes/pessoas-e-contatos.md).
- Contatos profissionais que só interessam à empresa ficam na relação (`employees.work_email`, `work_phone`, `work_whatsapp`), como a convenção prevê para "contatos específicos da relação", com os campos de `ContactFields`. Um colaborador pode ter contatos profissionais próprios de uma lotação (seção 10.6). **Contatos públicos não são digitados em terceira cópia:** o perfil público apenas autoriza a exibição do contato profissional efetivo da lotação aplicável, nunca de `people` (seção 12.4).
- O vínculo com login é derivado: existe usuário quando há `account_memberships` com o mesmo `person_id`. Ter vínculo de colaborador não concede acesso, e ter acesso não cria colaborador. A ficha e o filtro mostram apenas **"Possui acesso à conta"** (sim ou não), sem cargo, escopo nem situação do vínculo, e **somente a quem tem `account.users.list` em algum escopo**, a mesma regra da etiqueta Usuário. Quem não a tem não vê o indicador nem o filtro.
- Salário, regime trabalhista e dados de RH ficam fora do escopo. Um módulo de RH futuro terá tabelas próprias ligadas ao colaborador, sem reaproveitar "função profissional" como cargo trabalhista.

### 05.2 Institucional

| Entidade | Tabela | Classe | Observação |
| --- | --- | --- | --- |
| Seção institucional | `profile_sections` | Empresa ou estabelecimento | Valor de trabalho e valor publicado, ambos tipados por seção, com modo de herança. Seções operacionais gravam os dois valores ao salvar (seção 09.7) |
| Exceção de horário | `hour_exceptions` | Empresa ou estabelecimento | Feriado, fechamento temporário, horário especial, por data ou período |
| Mídia | `media_assets` e `media_variants` | Empresa, com `establishment_id` opcional | Biblioteca corporativa e galeria local. As variantes também carregam `account_id` e `company_id` (seção 06.1) |
| Categorias públicas | `public_categories` | Tenant | Tabela própria, sem reaproveitar `tags` (PD2-12, aprovada; seção 05.4) |

### 05.3 Sites

| Entidade | Tabela | Classe | Observação |
| --- | --- | --- | --- |
| Site | `sites` | Empresa, com `establishment_id` nulo (corporativo) ou preenchido (de unidade) | Várias por empresa |
| Host público | `public_hosts` | Empresa (host de site) ou Tenant (host do diretório), conforme `subject` | Gerenciado (subdomínio) ou próprio. Unicidade global do host. Substitui `site_domains` da revisão 2 (DA2-19) |
| Página | `site_pages` | Empresa | Slug único por site; aponta para a revisão atual e a publicada |
| Revisão da página | `site_page_revisions` | Empresa | Blocos e SEO em JSON, imutável depois de publicada |
| Projeção do diretório | `directory_entries` | Empresa, com `establishment_id` nulo (entrada de empresa) ou preenchido (de unidade) | Somente dados públicos, gravada na publicação (seção 14). A leitura pública é por conta, mas a **propriedade** é do nó de origem |
| Ficha do diretório | `directory_listings` | Empresa ou estabelecimento | Autoridade de publicação e despublicação no modo A (sem site). Alimenta a projeção (DA2-17) |

### 05.4 Categorias e tags

Reaproveitar `tags` exige um módulo novo (`directory`) e respeitar que etiquetas pertencem à conta. Alternativa: tabela própria `public_categories` por conta, com `slug`. **Decisão [AP] (PD2-12):** tabela própria `public_categories`, porque categoria pública tem slug, ordem e conteúdo SEO, e porque misturar com etiquetas de pessoas confundiria a regra "a estrela de etiqueta especial aparece somente na listagem de Etiquetas". A migration é da S06.

### 05.5 Conceitos que não entram neste ciclo

- **Marca:** não existe. Seção 08, DA2-12.
- **Departamento local de unidade:** não existe. O departamento é da empresa e a lotação o aplica a um estabelecimento (DA2-04).
- **Produtos e serviços públicos:** contrato reservado (seção 15).

---

## 06. Diagrama de relações e cardinalidades

```text
clients (Conta, account_id)
 ├── people (1:N)                                     cadastro central
 ├── companies (1:N)
 │    ├── establishments (1:N)
 │    ├── departments (1:N)
 │    ├── job_titles (1:N)
 │    ├── employees (1:N)  ── person_id ──> people (N:1)   único (company_id, person_id)
 │    │     ├── employee_assignments (1:N)
 │    │     │     ├── establishment_id ──> establishments (N:1)  mesma empresa
 │    │     │     ├── department_id ──> departments (N:1, opcional)
 │    │     │     └── job_title_id ──> job_titles (N:1, opcional)
 │    │     └── employee_public_profiles (1:0..1)
 │    ├── profile_sections (1:N)   dono: empresa
 │    ├── media_assets (1:N)
 │    └── sites (1:N)
 │          ├── public_hosts (1:N, subject = site)
 │          └── site_pages (1:N) ── site_page_revisions (1:N)
 ├── establishments ── profile_sections (1:N)   dono: estabelecimento
 │                  └─ hour_exceptions (1:N)
 ├── sites.establishment_id (N:1, opcional)  site de unidade
 ├── public_hosts (0..1, subject = directory)  host público do diretório da conta
 ├── public_categories (1:N)
 ├── directory_listings (1:N)  autoridade da ficha pública (modo A), dono: empresa ou estabelecimento
 └── directory_entries (1:N)   projeção derivada, dono: empresa ou estabelecimento

account_memberships.person_id ──> people     (login opcional do colaborador, derivado)
```

Cardinalidades e invariantes:

| Relação | Cardinalidade | Garantia |
| --- | --- | --- |
| Pessoa para colaborador | 1 pessoa, 0 ou 1 relação por empresa; até N empresas | único `(company_id, person_id)`; FK composta `(person_id, account_id)` |
| Colaborador para lotações | 1:N, sem sobreposição no mesmo estabelecimento | regra de domínio em transação com trava da linha do colaborador; `institutional:check` detecta |
| Lotação principal | no máximo uma aberta por colaborador | índice único parcial onde `is_primary and ends_on is null` |
| Lotação e estabelecimento | o estabelecimento é da mesma empresa do colaborador | FK composta `(establishment_id, company_id)` |
| Departamento e função | pertencem à mesma empresa do colaborador | FKs compostas `(department_id, company_id)` e `(job_title_id, company_id)` |
| Empresa para sites | 1:N | `sites.company_id` com FK composta |
| Site de unidade | `establishment_id` preenchido, da mesma empresa | FK composta `(establishment_id, company_id)` |
| Slug do site | único **por conta** (identificador editorial). **Não** é autoridade do host | índice único `(account_id, slug)`; o host é decidido só por `public_hosts` (DA2-19) |
| Host público | único globalmente, entre sites e diretórios | índice único em `public_hosts.hostname` |
| Revisão publicada | no máximo uma por página. A autoridade é `site_pages.published_revision_id` | FK composta do ponteiro (seção 06.1); `institutional:check` confere a coerência com `state` |

Não há tabela polimórfica. A propriedade do site é um par de colunas validado por FK composta e CHECK (`establishment_id is null` para corporativo).

### 06.1 Alvos de integridade e unicidades (revisão 3)

Toda FK composta precisa de um alvo único declarado. A tabela abaixo fecha os alvos que a revisão 2 deixava implícitos. Os três primeiros já existem [C] nas migrations do plano 001 e **não** são alterados.

| Tabela referenciada | Alvo único | Quem referencia | Situação |
| --- | --- | --- | --- |
| `companies` | `(id, account_id)` | todas as tabelas de empresa, por `(company_id, account_id)` | existente [C] |
| `establishments` | `(id, company_id)` | lotação, seção, exceção, mídia, site, página, ficha e entrada, por `(establishment_id, company_id)` | existente [C] (`2026_11_20_000007`) |
| `people` | `(id, account_id)` | `employees`, por `(person_id, account_id)` | existente [C] |
| `departments`, `job_titles` | `(id, company_id)` | `employee_assignments` | novo, S03 |
| `employees` | `(id, company_id)` | `employee_assignments` e `employee_public_profiles`, por `(employee_id, company_id)` | novo, S04 |
| `media_assets` | `(id, company_id)` | `media_variants` e `employee_public_profiles.photo_media_id`, por `(media_asset_id, company_id)` | novo, S08 |
| `public_categories` | `(id, account_id)` | `directory_listing_categories` | novo, S06 |
| `sites` | `(id, company_id)` | `site_pages`, `site_slug_redirects` e `public_hosts` (host de site), por `(site_id, company_id)` | novo, S10 |
| `site_pages` | `(id, company_id)` e `(id, site_id)` | `site_page_revisions` por `(site_page_id, company_id)`; `site_slug_redirects` | novo, S10 |
| `site_page_revisions` | `(id, site_page_id)` | `site_pages.current_revision_id` e `published_revision_id`, por `(current_revision_id, id)` e `(published_revision_id, id)` | novo, S10 |
| `directory_listings` | `(id, account_id)` | `directory_entries` e `directory_listing_categories` | novo, S13 |

Regras de leitura da tabela:

1. **Ponteiros circulares.** `site_pages` aponta para revisões e as revisões apontam para a página. Os ponteiros são colunas **anuláveis** (a FK composta com coluna nula não é verificada, `MATCH SIMPLE`), e a página é criada antes da primeira revisão. Nenhuma restrição adiável (`DEFERRABLE`) é usada. O par `(current_revision_id, id)` garante que a revisão apontada pertence à própria página.
2. **Tabelas de apoio também carregam as colunas da classe.** `media_variants`, `site_slug_redirects` e `directory_listing_categories` carregam `account_id` e `company_id` (esta última, o `account_id`), cada uma com FK composta própria. A revisão 2 as descrevia só com a chave do pai.
3. **Unicidades parciais por dono.** `profile_sections`: único `(company_id, section)` onde `establishment_id is null` e único `(establishment_id, section)` onde não é nulo. O mesmo vale para `directory_listings` (uma por empresa e uma por estabelecimento) e `hour_exceptions` não tem unicidade por dono.
4. **O que FK não garante** (e que fica em regra de domínio, trava e `institutional:check`): página de unidade em site de unidade com estabelecimento diferente do site; coerência entre `site_pages.published_revision_id` e `state`; sobreposição de lotações; uma principal vigente; referência a mídia, colaborador ou unidade dentro do JSON de bloco.

---

## 07. Regras de tenant e escopo organizacional

Esta seção responde à ficha do [checklist de módulos](../convencoes/checklist-modulos.md) para a frente inteira. Cada etapa repete a ficha para o que entrega.

| Pergunta | Resposta |
| --- | --- |
| 1. Proprietário | Equipe, institucional, mídia e sites: **empresa**, com `establishment_id` quando o dado é de unidade. Projeção e ficha do diretório: **empresa ou estabelecimento**, conforme o nó de origem. A configuração do diretório da conta (host, nome público) é **tenant** |
| 2. Nó e alvos | Colaboradores e departamentos: `company`. Lotação, perfil de unidade, horários e sites de unidade: nó `establishment` quando o recurso é de unidade, `company` caso contrário. Alvos admitidos em `config/permissions.php` (seção 12.1) |
| 3. Compartilhamento | Pessoa continua central. Nada novo é compartilhado entre empresas. O que o site de uma empresa exibe sobre pessoa é projeção opt-in do perfil público, nunca o cadastro |
| 4. Estabelecimentos que usam | A lotação define em quais estabelecimentos o colaborador aparece. Seções e sites de unidade valem para a unidade |
| 5. Quem visualiza | Permissão `view` no nó. Gestor de unidade vê colaboradores lotados na unidade |
| 6. Quem modifica | Permissão da ação no nó. Publicar é permissão separada de editar |
| 7. Auditoria | `company_id` e `establishment_id` conforme o nó afetado, nunca pela unidade selecionada na Área de Trabalho |
| 8. Visão consolidada | Listagem de colaboradores aceita `todos` (leitura), como Clientes, **a partir da S05**; na S04 a listagem é por empresa. Sites e seções, não. Uma pessoa com duas lotações aparece uma vez |
| 9. Contexto no backend | Colaboradores: administração por `empresa/{empresa}/...` (S04) e, a partir da S05, também por `estabelecimento/{estabelecimento}/...` (consolidável). Sites e perfis por `empresa/{empresa}/...` ou `estabelecimento/{estabelecimento}/...` conforme o dono |
| 10. Acesso indevido | Query com tenant e nó, FK composta, 404 para ID de outro escopo, testes negativos |

**Contexto público.** Rotas públicas **não** usam `OrganizationContext`, sessão ou cookie. Elas usam um serviço `PublicContext` com binding `scoped`, preenchido apenas pelo middleware de resolução por host (DA2-08 e DA2-19). O contexto tem dois sujeitos: `site` (`account_id`, `company_id`, `site_id`, `establishment_id` do site) e `directory` (somente `account_id`). Só as Queries públicas o usam. Nenhuma Query pública aceita `account_id`, `company_id` ou `site_id` vindo da requisição. O contexto público **não concede permissão** e nunca é lido por Actions administrativas.

**Empresa pequena.** Uma conta com uma empresa e um estabelecimento: o site corporativo nasce com um clique, sem seletor, e o site de unidade só aparece quando houver mais de um estabelecimento ativo.

**Inspeção.** A inspeção SaaS é somente leitura e bloqueia todas as escritas desta frente, inclusive publicar e verificar domínio.

---

## 08. Decisões arquiteturais (ADRs)

Cada decisão registra alternativas, escolha, trade-offs e o que a invalida. Todas são **[R]** até a aprovação do plano, salvo onde indicado **[AP]**.

### DA2-01: Colaborador é uma relação por empresa com etiqueta derivada

- **Contexto.** Papel de Pessoa, sem duplicar identidade. Cliente e Fornecedor já resolvem isso por empresa.
- **Alternativas.** (a) Etiqueta convencional em `person_tag`. (b) Coluna em `people`. (c) Relação por empresa (`employees`).
- **Escolha.** (c). Colaborador existe numa empresa, com vigência e lotação próprias. (a) não guarda dados da relação nem escopo de empresa. (b) quebra a regra de que `people` só tem dados centrais.
- **Trade-offs.** Uma pessoa que trabalha em duas empresas tem duas relações, cada uma com seu histórico. É o comportamento desejado.
- **Conflito e tratamento.** `CommercialRole` é comercial; Colaborador **não** entra nesse enum. Cria-se o domínio `Colaboradores` com `EmployeeAuthority` equivalente. A derivação da etiqueta, hoje espalhada em `PeopleDirectoryQuery` e `PersonCommercialRelationsQuery`, passa a ler um **registro único de papéis derivados**, em vez de uma segunda trilha paralela. A convenção de Pessoas é emendada na S02. Pontos de adaptação mapeados na seção 26 (H6).

### DA2-02: Lotação é um período, e função e departamento acompanham o período

- **Alternativas.** (a) Tabela N:N simples colaborador e estabelecimento, com função e departamento no colaborador. (b) Período por estabelecimento com função e departamento no período.
- **Escolha.** (b). Resolve "diferentes cargos e contatos em duas unidades", promoção ao longo do tempo e histórico, sem tabela de histórico à parte. A auditoria registra quem alterou; a tabela guarda a vigência de negócio.
- **Regras.** Semântica completa, inclusive trocas no mesmo dia e períodos adjacentes, na seção 10.3.1.
- **Garantia.** Sobreposição por intervalo seria uma exclusion constraint do PostgreSQL, que a suíte SQLite não executa. Por isso a regra fica em transação com `lockForUpdate()` do colaborador, e o `institutional:check` a detecta (mesmo padrão do `organization:check`).

### DA2-03: Função profissional não é Cargo de acesso

- **Contexto.** O README e o AGENTS.md chamam de Cargos os papéis RBAC.
- **Escolha [AP].** Tabela `job_titles`, sem relação com `roles`. Nenhuma permissão deriva da função. Na interface o termo é **Função** (PD2-01): menu "Funções", campo "Função". "Cargo" fica reservado a papéis de acesso. Nenhum texto novo usa "cargo" para a atividade profissional.

### DA2-04: Departamento pertence à empresa

- **Alternativas.** (a) Departamento global da empresa e departamento local da unidade como entidades. (b) Só departamento de empresa; a lotação o aplica a um estabelecimento.
- **Escolha.** (b). O briefing pede "global e local", e (b) cobre os dois: o filtro "Financeiro em Guanambi" é lotação com `department_id` e `establishment_id`. Evita hierarquia nova. Se surgir necessidade de departamento exclusivo de unidade, é uma coluna `establishment_id` opcional em `departments`, aditiva. O departamento **nunca** é filial nem estabelecimento.

### DA2-05: Propriedade do site por colunas validadas

- **Alternativas.** (a) Polimórfica `owner_type` e `owner_id`. (b) `company_id` obrigatório e `establishment_id` opcional.
- **Escolha.** (b). FK composta, CHECK e índices simples. Permite vários sites por empresa e por unidade, sem limite permanente.
- **Modos de publicação.** A: ficha de diretório sem site (nenhuma linha em `sites`, só `directory_entries`). B: site corporativo com páginas de unidade (`site_pages.kind = unit`, apontando `establishment_id`). C: site de unidade (`sites.establishment_id`). D: híbrido, combinação de B e C. Os quatro usam as mesmas tabelas.
- **Páginas de unidade em site corporativo.** `site_pages.establishment_id` opcional, com FK composta, mesmo `company_id` do site.

### DA2-06: Herança por seção com resolvedor único

Seção 09. Rejeita colunas duplicadas por tipo de dado e rejeita EAV genérico de campo a campo.

### DA2-07: Blocos tipados em JSON dentro de revisões imutáveis

- **Alternativas.** (a) Tabela por bloco. (b) JSON versionado na revisão da página.
- **Escolha.** (b). A revisão é um instantâneo completo; reverter é copiar uma revisão antiga para uma nova. Cada tipo de bloco tem uma classe de validação (`BlockType`) com `schema_version`.
- **Custo.** O banco não garante integridade das referências dentro do JSON (mídia, colaborador, unidade). Mitigação: a Action de salvar valida cada referência no escopo do site; o renderizador ignora e registra referência inválida; o `institutional:check` varre revisões publicadas.
- **HTML livre.** Fora do ciclo. **[AP, PD2-15]** O editor é de blocos tipados com **formatação restrita** (parágrafo, negrito, itálico, listas e links), sem HTML arbitrário e sem WYSIWYG completo no início. O servidor nunca confia em HTML vindo do cliente: o texto é validado contra um conjunto fechado de nós e o HTML é gerado pelo renderizador (ou sanitizado por allowlist, conforme a representação escolhida na S10). Blocos `embed` (mapa, vídeo) aceitam apenas provedores de uma lista fixa, sem iframe arbitrário e sem script. A S10 não adota o TinyMCE 4 nem o Summernote.

### DA2-08: Contexto público por host, em domínio registrável diferente do app

- **Risco.** Conteúdo público de cliente servido sob o mesmo domínio registrável do app compartilha escopo de cookie e amplia o impacto de XSS.
- **Escolha.** Sites gerenciados usam um domínio registrável **diferente** do app (PD2-02). Rotas públicas ficam em `routes/public.php`, sem os middlewares `web` de sessão e CSRF, e aceitam apenas GET e HEAD. O app não emite cookie para esse domínio.
- **Resolução.** O host é normalizado (minúsculas, sem porta, IDN em punycode) e buscado em `public_hosts` com situação ativa. O sujeito do host (`site` ou `directory`) define o contexto (DA2-19). Host desconhecido responde 404 neutro, sem revelar se existe.

### DA2-09: Leitura pública somente por Queries e Resources com campos permitidos

- Nenhum Model privado é serializado no público. Cada resposta pública é um Resource com lista explícita de chaves. Um teste varre os payloads e falha se encontrar chaves proibidas (documento, e-mail de login, endereço de pessoa, salário, ID de usuário).
- O diretório usa `directory_entries`, uma projeção desnormalizada gravada na publicação e removida na despublicação. A busca nunca toca em `people`, `employees` ou `establishments` diretamente.

### DA2-10: Mídia com original privado e derivados públicos opacos

- Original no disco privado. Na publicação, geram-se derivados reprocessados (remove EXIF e GPS, limita dimensões) em caminho opaco no disco público. Revogar, excluir ou despublicar apaga os derivados e invalida o cache.
- Somente JPEG, PNG e WebP, validados pelo conteúdo. **SVG fora do ciclo** (vetor de XSS).
- Biblioteca de imagem: GD já instalado, sem dependência nova (PD2-05, evidência H1). **[AP, PD2-13]** A extensão `exif` não é adicionada somente por estar ausente. A S08 comprova a necessidade com fotos de teste nas orientações 1 a 8, considerando rotação, remoção de metadados e processamento seguro, e só então propõe `exif` (alteração do `Dockerfile`, com aprovação) ou outra solução sem extensão. Sem prova, o upload segue sem rotação automática e isso é documentado.
- **Derivados são gerados no envio, nunca numa requisição pública.** GET e HEAD públicos não gravam arquivo (DA2-21). Derivados de foto de pessoa não são imutáveis nem de cache longo (seção 09.7, item 6).

### DA2-11: Domínio próprio em etapa final, com TLS sob demanda validado

Seção 11.6. O Caddy usa TLS sob demanda com endpoint `ask` que só autoriza hosts em `public_hosts` com situação ativa. Sem isso, qualquer host apontado para o servidor emitiria certificado.

### DA2-12: Marca fora deste ciclo, com caminho de evolução

- **Decisão [DP].** Não criar Marca. Empresa é o agrupamento. Estabelecimentos são unidades.
- **Futuro, sem alterar a árvore agora.** Uma entidade `brands` de escopo tenant, com tabela de ligação `brand_companies(brand_id, company_id)`, agruparia empresas juridicamente distintas. Os sites continuariam ligados a empresa. O diretório passaria a agrupar por marca lendo a ligação. Nenhuma coluna de `companies`, `establishments`, `sites` ou `employees` mudaria. **O plano não reserva coluna `brand_id`.** O que o plano garante é que nada aqui depende do agrupamento por nome.
- **Regra permanente.** Empresas juridicamente distintas nunca são agrupadas por semelhança de nome.

### DA2-13: Pré-visualização autenticada

**[AP, PD2-09]** A prévia é **somente autenticada** no primeiro ciclo. É uma rota administrativa dentro de `contas/{conta}/...`, protegida por `account.sites.preview` no nó do site, que renderiza a revisão atual (rascunho) pelo mesmo renderizador público, com `noindex` e sem cache. Link assinado para quem não tem login fica **fora do ciclo**. Se um dia for adotado, será decisão própria: a rota levará segredo no caminho, **precisará** do prefixo no Caddyfile (README) e a geração do link será operação auditável.

### DA2-14: Cache de página por versão

- Páginas públicas são cacheadas por chave que inclui `account_id`, o nó do site (`company_id` e `establishment_id`, ou nulo), `site_id`, `site.content_version` e `page.published_revision_id`, como exige [Performance](../convencoes/performance.md) (toda chave leva tenant e nó). Qualquer alteração que afete o site incrementa `content_version` (alteração de seção herdada, revogação de foto, mudança de equipe publicada).
- Não há varredura de cache por tag. Chaves antigas expiram por TTL. A revogação de consentimento é a exceção: além de incrementar a versão, apaga os derivados da imagem.
- Sem estado estático mutável (Octane). O store e o TTL dependem da medição da S11 (H4). **[AP, PD2-10]** Redis não é dependência automática: só se adota se a medição com worker FrankenPHP real mostrar que cache de banco, cache HTTP por `content_version` e TTL não atendem.
- **Vigência e relógio.** Conteúdo que muda com a data sem evento (lotação que termina, exceção de horário que começa, `starts_on` futuro) segue o contrato de frescor da seção 09.7, item 6: `valid_until` conferido na leitura, revalidação a cada acesso (`no-cache` com `ETag`) e nenhuma resposta vencida servida. O estado "aberto agora" nunca é gravado no HTML em cache (seção 09.5).
- **Invalidar não é varrer.** Nenhum job de limpeza de cache existe: trocar a versão basta. A revogação de consentimento apaga os derivados da imagem na própria Action (síncrono).

### DA2-15: Reaproveitamento do GNAI2

- **Reutilizável como ideia:** catálogo de tipos de bloco, aparências tipadas, regras de contraste, fluxo de validação de mídia.
- **Reutilizável como código somente após PD2-03:** `WebsiteContrast`, `WebsiteAccessibility`, `WebsiteAppearanceAccessibility`, adaptados ao padrão Actions, Data e DTO do Prospera.
- **Não reutilizar:** `website_settings`, o enum fixo como estrutura de dados, controllers, o front React do GNAI2.

### DA2-16: Dado operacional, conteúdo editorial e retratação (revisão 2)

- **Problema.** Uma seção institucional editada ao vivo mudaria sites publicados sem ninguém publicar, o que contraria "editar rascunho não altera a publicação" e "nunca publicar automaticamente alterações sensíveis".
- **Escolha.** Quatro classes, com regra de propagação própria (seção 09.7): **editorial versionado** (valor de trabalho e valor publicado, só muda o público por publicação explícita), **operacional dinâmico** (vale ao salvar, com aviso), **retratação** (vale na hora) e **exposição nova** (exige ato explícito).
- **Consequência.** `profile_sections` e as configurações de tema e navegação do site ganham valor de trabalho e valor publicado. O resolvedor recebe o estado (`draft` ou `published`). O público e o diretório leem sempre `published`.
- **Custo.** Mais colunas e uma Action de publicação de seções. Em troca, o comportamento é verificável e o aceite da herança tem dois estados testáveis.
- **PD2-06 [AP].** Editar e publicar são permissões distintas em todos os recursos publicáveis (`update` e `publish`; no perfil do colaborador, `edit-profile` e `publish-profile`). Não há aprovação em duas etapas obrigatória: quem tem `publish` publica sem segunda pessoa.

### DA2-17: Ficha de diretório com autoridade própria (revisão 2)

- **Problema.** O modo A (ficha sem site) não tem página, então não pode depender de `PublishPageAction`.
- **Escolha.** `directory_listings` é a autoridade: `draft`, `published` e `unpublished`, com `PublishDirectoryListingAction` e `UnpublishDirectoryListingAction`, permissões `account.directory.publish` e `unpublish` no nó (empresa ou estabelecimento) e auditoria própria. `directory_entries` é derivada e reconstruída por `SyncDirectoryEntryAction` a partir da ficha publicada, das seções publicadas e do melhor destino publicado.
- **Regras.** Nada é listado por padrão. Ficha de estabelecimento só é visível se a ficha da empresa estiver publicada. Publicar ou despublicar um **site** não cria nem remove ficha: apenas preenche ou limpa o destino do site na projeção. Inativar empresa ou estabelecimento remove a entrada por chamada explícita da Action de sincronização (sem observer oculto) **e** a leitura pública confere `active` de empresa e estabelecimento, de modo que a retratação não dependa de o gancho ter rodado (seção 09.7). **[AP, PD2-04]** O diretório é **por conta** no primeiro ciclo.

### DA2-18: Permissões de colaborador por lotação (revisão 2)

- **Problema.** Gestor de unidade deve ver só os lotados na sua unidade, mas o colaborador é um recurso de empresa.
- **Precedente [C].** `account.audit.list` e `account.audit.view` já admitem os alvos `tenant`, `company` e `establishment` em `config/permissions.php`.
- **Escolha.** `account.employees.list`, `view` e `assign` admitem `company` e, **a partir da S05**, `establishment`. Para o alvo `establishment`, o recurso avaliado é a **lotação** naquele estabelecimento. `create`, `update`, `activate` e `deactivate` (dados centrais e relação) admitem somente `company`. Na S04, todos admitem somente `company`.
- **Garantia.** Uma atribuição de estabelecimento nunca alcança outra unidade, a empresa ou os dados centrais da pessoa.
- **O que o gestor de unidade vê (revisão 3).** Com alvo `establishment`, a ficha mostra o nome do colaborador, as lotações **daquela unidade** (vigentes e históricas) e o contato profissional efetivo dessa lotação. Não mostra lotações de outras unidades, `employees.work_*` da relação (a menos que a lotação os herde, caso em que aparece o valor efetivo), perfil público nem dados centrais além do que Pessoas já concede por `account.people.view`.
- **Etiqueta Colaborador.** Aparece para quem tem `account.employees.view` cobrindo a empresa da relação ativa (S04) e, a partir da S05, também por lotação numa unidade coberta. Nunca revela relação com empresa não autorizada.

### DA2-19: Host público do diretório e contexto público (revisão 3)

- **Problema.** O contexto público da revisão 2 resolvia o host para um `site_id`. O diretório sem site (modo A, DA2-17) não tem site e, portanto, não tinha host nem contexto, embora a seção 17.3 lhe desse rotas públicas.
- **Alternativas.** (a) Um host por conta guardado em tabela própria. (b) Um caminho `/diretorio/{conta}` no domínio do app. (c) Uma só tabela de hosts públicos com sujeito `site` ou `directory`.
- **Escolha.** (c). (b) quebra DA2-08 (conteúdo público sob o domínio registrável do app). (a) cria um segundo espaço de nomes sem garantia de unicidade global entre sites e diretórios. A tabela `public_hosts` substitui `site_domains`: `subject` (`site` ou `directory`), `site_id` e `company_id` preenchidos só no sujeito `site`, e CHECK dessa coerência. O contexto passa a se chamar `PublicContext` (seção 07). Há no máximo um host `directory` ativo por conta (índice parcial).
- **Classe de propriedade, declarada por linha.** `subject = site`: empresa (`account_id`, `company_id`). `subject = directory`: tenant (`account_id`). A mistura é intencional e limitada: o que as duas coisas compartilham é o **espaço de nomes global** do host, que só um índice único garante.
- **Permissões.** Host de site: `account.domains.*`, alvo `company` ou `establishment` conforme o site. Host do diretório: `account.directory.configure`, alvo `tenant`. É operação de conta e não recebe empresa nem estabelecimento fictícios.
- **Etapas.** A S11 cria a tabela e só aceita `subject = site` e `kind = managed`. A S13 habilita `subject = directory`, também gerenciado. Domínio próprio de diretório fica fora do ciclo; domínio próprio de site é a S14.
- **Consequência para o diretório.** Cada conta publica o próprio diretório no próprio host (PD2-04). Nada reúne contas.

#### Regras do host (revisão 4)

1. **Uma única autoridade.** `public_hosts` é a **única** tabela que decide qual host resolve para quê. `sites.slug` é identificador editorial único **por conta** e nunca resolve host. O rótulo do host gerenciado é escolhido na criação (sugerido a partir do slug), validado contra a lista de reservados e guardado só em `public_hosts.hostname`. Renomear o slug não renomeia o host. Uma única Query (`ResolvePublicHostQuery`) lê a tabela; nenhum middleware, rota ou cache resolve host por outro caminho.
2. **Host e publicação são camadas distintas.** `public_hosts.status` diz se o **host** é válido e está sob controle da conta. `sites.status` e a revisão publicada dizem se há **conteúdo** a servir. O público só recebe conteúdo se as duas estiverem satisfeitas; qualquer falha é o mesmo 404 neutro.
3. **Estados.** Host gerenciado: `active`, `suspended` ou `revoked`, sem `pending` nem `verified`. Host próprio: `pending` (aguardando posse), `verified` (posse comprovada, sem certificado), `active` (servido), `suspended` (divergência de DNS ou ação da plataforma, reversível) e `revoked` (definitivo). Só `active` é servido e só `active` é autorizado pelo `ask` do TLS. `suspended` volta a `active` pela revalidação; `revoked` não volta, e um novo host é uma nova linha.
4. **Quarentena.** O `hostname` de uma linha `revoked` continua reservado (o índice único inclui revogados) e **não** pode ser reivindicado por outra conta, para impedir tomada de subdomínio. A liberação é uma operação explícita da plataforma, fora do ciclo.
5. **Hosts de um site.** Um site pode ter vários hosts (gerenciado, próprio, `www`). Exatamente **um** é canônico e ativo; os demais respondem 301 para o canônico. Domínio apex e `www` são hosts distintos (seção 11.6).
6. **Hosts reservados.** O host do app, hosts da plataforma e os rótulos reservados nunca entram em `public_hosts`. As rotas públicas não respondem no host do app, e as rotas do app não respondem em host público.
7. **Resolução sem sessão e sem confiança no cliente.** O host vem da requisição já normalizada pelo proxy confiável. Cabeçalhos como `X-Forwarded-Host` só valem vindos de proxy confiável. A resolução não toca sessão, cookie nem `OrganizationContext`.
8. **Isolamento.** A Query devolve `account_id` (e `company_id`, `site_id`) **da linha**; nada vem da requisição. Host de outra conta, desconhecido, suspenso, revogado ou pendente responde o mesmo 404 neutro. A FK composta garante que `site_id` e `company_id` pertencem à mesma conta.
9. **Diretório sem site.** O destino de uma entrada do diretório guarda `site_id` e `page_slug`, **nunca o host**: o host canônico é resolvido na leitura por `public_hosts`. Se o site de destino não estiver publicado ou o host não estiver ativo, a entrada cai para a ficha no host do diretório.

**Os quatro modos contra `public_hosts`:**

| Modo | Linhas de `public_hosts` | Observação |
| --- | --- | --- |
| A, ficha sem site | uma linha `subject = directory`, `kind = managed`, por conta | A ficha vive em `/{slug}` desse host. Sem host ativo a ficha existe mas não tem endereço público |
| B, corporativo com páginas de unidade | `subject = site` do site corporativo, um canônico | Páginas de unidade são caminhos do mesmo host |
| C, site de unidade | `subject = site` do site da unidade, com o próprio canônico | `site_id` aponta para site com `establishment_id` preenchido |
| D, híbrido | os hosts de B e de C, cada um com seu site | Nenhum host é compartilhado entre sites; nada é duplicado |

### DA2-20: Perfil público do colaborador, classe e permissões (revisão 3)

- **Problema.** O perfil público (nome público, texto, flags, foto) não estava classificado em 09.7, e uma única permissão `publish-profile` cobria ligar, desligar e editar, contra PD2-06 (editar e publicar distintos). A matriz 12.2 também dava ao gestor de unidade um acesso que as permissões, só de empresa, não permitiam.
- **Escolha.** O perfil é **uma só linha por colaborador, válida para todas as unidades**, sem par de trabalho e publicado. A classe segue o estado:
  1. **Antes da publicação** (`published_at` nulo) é rascunho por natureza: nada é público. `account.employees.edit-profile` cria e edita.
  2. **Publicar** é exposição nova e exige `account.employees.publish-profile`, depois de uma prévia do que será exibido.
  3. **Editar um perfil publicado** vale ao salvar (operacional dinâmico, com aviso), e por isso exige `publish-profile`, não `edit-profile`.
  4. **Despublicar** e **revogar consentimento** são retratação: valem na hora (`publish-profile` e `consent`).
  5. Ligar uma flag `show_*` ou trocar a foto de um perfil publicado é edição de perfil publicado (item 3). A foto também exige consentimento válido.
- **Alvos.** `edit-profile`, `publish-profile` e `consent` admitem somente `company`, porque o perfil público é **corporativo e compartilhado** por todas as unidades. O gestor de unidade não o altera.
- **Seleção por unidade (PD2-16, aprovada).** O gestor de unidade com a permissão específica `account.employees.select-team` (alvos `company` e `establishment`; o recurso avaliado é a lotação) controla **quais colaboradores aparecem na equipe da sua unidade**, gravando essa seleção na seção `team_display` da unidade (editorial, com rascunho e publicação; a publicação exige `account.institutional.publish` no nó). Regras:
  1. A seleção só **restringe e ordena** entre colaboradores já elegíveis: perfil publicado pela empresa, lotação vigente naquela unidade e, para foto, consentimento válido. Ela nunca torna público quem não é.
  2. A unidade usa a função e o contato profissional **da respectiva lotação** (seção 10.6). Os contatos só aparecem se a empresa ligou `show_work_email` ou `show_work_phone` no perfil. O gestor não liga flags, não edita o perfil, não publica nem despublica o perfil e não registra consentimento.
  3. O gestor **não altera dados centrais** da pessoa nem contatos de Pessoas e **não divulga contato privado**: nenhuma tela da unidade lê `people.*`, e o contato profissional da lotação é digitado, nunca pré-preenchido. Quem tem `assign` na unidade edita o contato da lotação, e a tela avisa que ele será exibido quando a empresa autorizar.
  4. A seção `team_display` guarda referências a colaboradores, nunca cópias de dados de pessoa, e **não é editável por `account.institutional.update`**: exige `select-team`, de modo que editar o conteúdo da unidade não permita escolher pessoas.
  5. Atribuição de uma unidade não seleciona colaboradores de outra unidade nem da empresa. Selecionar alguém sem lotação vigente na unidade é recusado com 422.
- **Função exibida.** `public_title`, quando preenchido, vale em qualquer escopo. Quando vazio, vale a função da lotação aplicável (seção 10.6). Para a função mudar por unidade, o campo `public_title` deve ficar vazio, e a tela avisa.

### DA2-21: Execução síncrona, assíncrona e periódica (revisão 3)

- **Problema.** O plano dizia que só a S14 precisava de infraestrutura assíncrona, mas a S08 previa variantes "na fila", a S11 previa "job de limpeza de cache" e a S14 previa verificação e revalidação de domínio. A S01 (H3) confirmou que **não existe worker nem agendador** e que o código não despacha nenhum job.
- **Escolha.** Cada operação é classificada em **síncrona** (na requisição), **assíncrona** (fila) ou **periódica** (agendador). A classificação por etapa está na seção 19.1. Em resumo: S03 a S13 são **inteiramente síncronas e sem agendador**; a **S14 é a primeira etapa que exige worker e agendador**.
- **Por que S08 e S11 não precisam de fila.** S08: o derivado de 1600x1200 levou 25 ms e 30 MB com limites de 5 MB e 20 MP (H1). S11: a invalidação é a troca de `content_version` (um `UPDATE`), e a limpeza de cache por job **foi removida** (DA2-14). Nenhuma das duas é adiada por falta de worker: ambas são síncronas por contrato.
- **Gatilhos que movem uma etapa para a fila.** Se a medição da etapa mostrar que a operação síncrona não cabe no orçamento de latência, de memória ou de concorrência definido após a linha de base (Performance), **a própria etapa incorpora o processamento assíncrono** e a infraestrutura de laboratório necessária (seção 19.1), e só encerra com o fluxo completo. Não se entrega uma funcionalidade com lacuna operacional à espera de uma etapa posterior. A decisão sai da medição registrada, não de preferência.
- **Regra.** Nenhum job é despachado antes da S14 **sem que a medição da etapa o tenha exigido** e sem a infraestrutura do item acima. Quando houver job, ele leva `account_id`, `company_id`, `establishment_id`, `membership_id` e `actor_id` e reautoriza (Contexto organizacional).

### DA2-22: Revisões, ponteiros e despublicação neutra (revisão 3)

- **Imutabilidade.** O que é imutável numa revisão publicada ou arquivada são `blocks`, `seo`, `schema_version`, `number` e `note`. `state`, `published_by` e `published_at` transitam exclusivamente por `PublishPageAction` e pelas Actions de despublicar e reverter. A revisão 2 dizia que `published` e `archived` eram imutáveis e, ao mesmo tempo, previa a passagem de `published` a `archived`.
- **Autoridade da publicação.** `site_pages.published_revision_id` é a autoridade. `state` é derivado e conferido pelo `institutional:check`. O índice único parcial da revisão 2 é dispensado.
- **Despublicação.** Despublicar página ou site responde **404 neutro**, nunca 410 (o 410 revelaria que o recurso existiu, contra a seção 11.4). Slug renomeado responde 301. A página `home` não é despublicada isoladamente: despublicar a `home` despublica o site, numa só Action.

### DA2-23: Endereço, contatos públicos e ficha da empresa (revisão 3)

- **Problema.** `companies` não tem endereço nem contato (02.1), mas a ficha de empresa exigia "cidade vinda de endereço publicado" e "contato publicado". Além disso, `establishments` já tem `address`, `phone`, `whatsapp` e `email`, e a seção `contacts` poderia virar cópia deles.
- **Escolha.**
  1. **Endereço** de unidade é lido de `establishments.address`, sem cópia, e só é público quando a opção **"Exibir endereço"** do estabelecimento está ligada (desligada por padrão). Não é uma seção do resolvedor.
  2. **Contatos públicos** são a seção `contacts`, **digitada** como conteúdo próprio para o público. Nunca são pré-preenchidos nem sincronizados a partir de `establishments.phone`, `whatsapp` ou `email`, nem de Pessoas.
  3. **Ficha de estabelecimento** exige nome, estabelecimento ativo, ficha da empresa publicada, endereço público (cidade e UF) e ao menos um contato publicado, próprio ou herdado da empresa.
  4. **Ficha de empresa** exige empresa ativa, nome e ao menos um contato publicado (`contacts` da empresa). **Não exige cidade**: a empresa não tem endereço. A cidade aparece nos resultados pelas fichas de unidade publicadas.
- **Consequência.** Empresa sem unidade com endereço público é encontrada por nome, categoria e tag, não por cidade.

---

## 09. Herança e resolução efetiva

### 09.1 Princípio

Uma **seção** é a menor unidade de herança. Cada seção tem um tipo (por exemplo `logo`, `palette`, `about`, `contacts`, `social_links`, `hours_weekly`, `banners`, `seo_defaults`), um schema de valor e um **modo** por dono.

| Modo | Significado | Valor gravado |
| --- | --- | --- |
| `inherit` | Usa o valor do nível acima e acompanha as mudanças | **Sempre nulo** (CHECK) |
| `override` | Usa o valor próprio, **inclusive vazio intencional** | Objeto obrigatório, válido pelo schema da seção |
| `disabled` | A seção não aparece neste dono, quando o tipo permite | **Sempre nulo** (CHECK) |

No nível da **empresa**, só existe `override`. A empresa define o padrão; não herda de nível acima. Na falta de linha, vale o padrão da plataforma (constante no código, por tipo).

### 09.2 Ausência, limpeza e vazio intencional

| Situação | Representação |
| --- | --- |
| Nunca configurado | Não existe linha. No estabelecimento, equivale a `inherit` |
| Voltar a herdar | Gravar `mode = inherit` (apagar o override). O valor atual da empresa passa a valer |
| Limpar um valor localmente | `override` com objeto vazio válido do schema (`{"items": []}` para contatos, por exemplo). O resolvedor devolve vazio e marca `source = establishment` |
| Desligar a seção | `disabled`, só para tipos que declaram `can_disable` |
| Campo ausente no PATCH | Significa "não alterar", nunca "limpar" |
| `null` no PATCH | Válido somente para `mode` implícito de `inherit` ou `disabled`. Em valor de campo é rejeitado com 422 |

Esta tabela é o contrato. Não existe outro significado para `null`.

### 09.3 Resolvedor

Um serviço único, `ProfileResolver`, com binding `scoped`, recebe o dono (empresa ou estabelecimento) e a seção e devolve:

```text
{ value, source: 'company'|'establishment'|'site'|'platform', mode, inherited_from }
```

- Ordem de resolução para um estabelecimento: linha do estabelecimento (`override` ou `disabled`) e, se `inherit` ou ausente, linha da empresa, e, se ausente, padrão da plataforma.
- Para um site: as configurações de apresentação do site (tema, navegação) têm seus próprios modos sobre o perfil do dono do site (estabelecimento, depois empresa). O site corporativo resolve a partir da empresa.
- O resolvedor é a **única** porta de leitura. Telas, renderizador público e diretório não leem `profile_sections` diretamente (checagem estática na S06).
- Cada chamada pode carregar todas as seções de um dono em uma consulta e memorizar o resultado na instância `scoped`. Não há cache entre requisições.
- A tela mostra o valor efetivo, a origem ("Herdado da empresa") e a ação "Voltar a herdar".

### 09.4 Seções, granularidade e padrão

| Seção | Valor | Estabelecimento pode | Desligar | Observação |
| --- | --- | --- | --- | --- |
| `logo` | mídia | substituir | não | Separada da paleta para herdar independentemente |
| `palette` | cores validadas por contraste | substituir | não | Contraste mínimo verificado no salvamento |
| `typography` | fonte de lista fixa | substituir | não | Sem fonte arbitrária |
| `about` | texto, missão, valores, diferenciais | substituir | sim | Texto sanitizado |
| `contacts` | telefones, WhatsApp, e-mails | substituir | não | Contato local tem prioridade. **Contato pessoal nunca é herdado como empresarial** |
| `social_links` | redes sociais | substituir | sim | |
| `hours_weekly` | turnos semanais com fuso | substituir | sim | Detalhe na seção 09.5 |
| `banners` | lista de mídia com texto alternativo | substituir | sim | |
| `gallery` | lista de mídia | só do estabelecimento | sim | Local por natureza |
| `seo_defaults` | título, descrição, imagem de compartilhamento | substituir | não | |
| `team_display` | critérios e seleção da equipe exibida | substituir | sim | Não copia pessoas. Em unidade, editável só com `account.employees.select-team` (DA2-20) |

A granularidade é por seção inteira. Uma seção que precise de herança parcial é dividida em duas seções, nunca recebe herança por campo.

**Fora do resolvedor.** O endereço da unidade, a situação (ativo) e o fuso são colunas de `establishments`, lidos por uma Query pública com lista de campos permitidos, nunca copiados para `profile_sections` (DA2-23). O resolvedor não os conhece.

### 09.5 Horários

- `hours_weekly` guarda, por dia da semana, uma lista de turnos (início e fim, mais de um por dia), a marca "atendimento sob agendamento" e o fuso IANA.
- `hour_exceptions` guarda por dono: intervalo de datas, tipo (`holiday`, `temporary_closure`, `special_hours`), turnos do dia quando houver e observação pública opcional.
- Resolução para uma data e um instante: exceção que cobre a data, senão semanal efetivo do estabelecimento, senão semanal da empresa. Estado "aberto agora" calculado no fuso do estabelecimento.
- Exceção do estabelecimento A nunca afeta B. Exceção da empresa vale para os estabelecimentos que **herdam** `hours_weekly`. **[AP, PD2-08]** Unidade com `override` próprio **não** recebe exceção corporativa, a menos que a exceção declare `applies_to_overrides = true`, coluna que só existe nas exceções de empresa, nasce `false` e exige confirmação com o resumo das unidades com `override` que serão alcançadas (contrato explícito). A exceção da própria unidade sempre vale para ela.
- **[AP, PD2-08] Fuso.** O fuso é **IANA por estabelecimento** (`establishments.timezone`), inicialmente `America/Sao_Paulo` e configurável por unidade. `app.timezone` continua `UTC` para armazenamento. A coluna entra na S07 (aditiva, com padrão `America/Sao_Paulo`). Até lá, a S05 usa o mesmo valor por configuração para calcular "hoje" (seção 10.3.1), e a migration da S07 grava o mesmo padrão, de modo que nenhuma vigência muda ao introduzir a coluna. Exceção corporativa **não** altera o fuso de uma unidade.
- **"Aberto agora" nunca vai no HTML em cache.** Muda a cada minuto sem evento. A página em cache mostra a grade semanal e as exceções próximas. O estado é obtido por `/api/publico/horarios`, com TTL curto (no máximo 60 s, valor final medido na S11), como melhoria progressiva. Sem JavaScript a página continua correta, sem o selo.
- A edição de horário da unidade nunca grava na empresa. O histórico é o da auditoria.

### 09.6 Testes de herança (aceite da S06 e S07)

1. Alterar a cor da empresa reflete em quem herda e não toca a unidade com override.
2. Remover o override volta a herdar o valor **atual** da empresa.
3. Override vazio intencional renderiza vazio e não herda.
4. Unidade desativa a seção `team_display` sem remover colaboradores.
5. Editar o horário da unidade A não altera B.
6. Exceção de fechamento em A não fecha B.
7. `null` em valor é rejeitado.

### 09.7 Rascunho e publicação das seções (revisão 2, DA2-16)

| Classe | Exemplos | Valor de trabalho e valor publicado | Quando reflete em site publicado |
| --- | --- | --- | --- |
| Editorial versionado | blocos, textos e SEO da página, navegação e tema do site, seleção de equipe (`team_display`), seções `about`, `logo`, `palette`, `typography`, `banners`, `gallery`, `seo_defaults`, `team_display` | Sim | Somente após publicação explícita |
| Operacional dinâmico | `contacts`, `social_links`, `hours_weekly`, `hour_exceptions`, endereço e situação da unidade | Não: ao salvar, os dois valores são gravados | Imediatamente. A tela avisa antes de salvar: "Esta alteração aparece nos sites publicados" |
| Retratação | revogar consentimento, inativar colaborador, encerrar lotação, inativar unidade, remover mídia, despublicar | Não se aplica | Imediatamente e com prioridade: incrementa a versão, apaga derivados de imagem e remove do diretório |
| Exposição nova | ligar o perfil público de um colaborador, publicar ficha no diretório, publicar página ou site | Ato explícito, com permissão própria | Somente depois do ato |
| Perfil público do colaborador | texto, nome público, flags `show_*`, foto | Uma só linha, sem par de trabalho (DA2-20). Antes de publicar é rascunho; publicado, vale ao salvar | Publicar é exposição nova; editar publicado é operacional com aviso; despublicar e revogar são retratação |

Regras:

1. `profile_sections` guarda `mode` e `value` (trabalho) e `published_mode`, `published_value`, `published_at` e `published_by`. Para seções operacionais, a Action grava os dois pares na mesma transação. CHECKs mantêm a regra de modo e valor nos dois pares.
2. O `ProfileResolver` recebe o estado: `draft` (editor e prévia) ou `published` (público, diretório, projeção). O público nunca lê `draft`.
3. `PublishProfileSectionsAction` copia trabalho para publicado nas seções escolhidas de um dono. Antes de confirmar, a tela mostra quantos sites e unidades serão afetados e quantas unidades com `override` **não** são afetadas.
4. **Efeito sobre quem herda.** Publicar uma seção da empresa atinge as unidades em `inherit` e não toca as que têm `override`. O rascunho da unidade permanece intacto. "Voltar a herdar" numa seção editorial é uma alteração de trabalho até ser publicada.
5. **Invalidação.** A publicação incrementa `content_version` com um único `UPDATE` em `sites`: mudança no nível da empresa afeta todos os sites da empresa; mudança no nível do estabelecimento afeta os sites desse estabelecimento e os sites corporativos da empresa. Não precisa de job.
6. **Vigência que expira sem evento e frescor do cache.** A vigência de uma lotação (`ends_on`, `starts_on` futuro) e a data de uma exceção de horário mudam o que o público deve ver **sem nenhuma escrita**, portanto sem auditoria e sem troca de `content_version`. Regras:
   1. **Elegibilidade na leitura.** É calculada com a data de hoje no fuso do estabelecimento aplicável, nunca gravada.
   2. **`valid_until` por resposta.** Toda resposta que depende de data carrega `valid_until` = o **início do próximo dia civil** no fuso IANA do estabelecimento envolvido, calculado pela biblioteca de datas no fuso (nunca "agora mais 24 horas", por causa de mudança de horário de verão). Com vários estabelecimentos no bloco, vale o menor `valid_until`.
   3. **Validade conferida na leitura.** A entrada em cache guarda `valid_until`, e a leitura a **descarta quando `agora >= valid_until`**, qualquer que seja o TTL do armazenamento. O cache nunca serve conteúdo vencido por ainda não ter sido expulso.
   4. **Sem servir vencido.** Respostas dependentes de data, de foto ou de equipe não usam `stale-while-revalidate` nem `stale-if-error`, nem no servidor nem nos cabeçalhos.
   5. **Cabeçalhos HTTP.** As páginas HTML usam `Cache-Control: public, no-cache` com `ETag` derivada de `content_version`, revisão publicada e dia de validade: navegador e borda **revalidam a cada acesso** (resposta 304 barata, que ainda passa pela conferência de `active` e de vigência). Assim nenhuma cópia de navegador ou de borda fica desatualizada por ainda não ter expirado. Só recursos imutáveis e opacos de mídia que não é foto de pessoa podem ter `max-age` longo.
   6. **Foto de pessoa.** O derivado de foto de colaborador usa `Cache-Control: public, no-cache` com `ETag`, e **não** `immutable` nem `max-age`. Revogar apaga o arquivo e troca a versão: a próxima requisição, inclusive a revalidação de navegador ou de borda, recebe 404. Uma borda ou CDN só pode ser adotada (PD2-05) se respeitar `no-cache` e oferecer remoção imediata de objeto.
   7. **Horários.** A grade semanal e as exceções próximas ficam na página, válidas até o `valid_until`. O estado "aberto agora" sai do HTML e vem de `/api/publico/horarios`, com TTL de no máximo 60 s (seção 09.5). Exceção que começa ou termina hoje troca de efeito à meia-noite do fuso da unidade, coberta pelo `valid_until`.
   8. **Retratação por evento.** Revogar consentimento, inativar colaborador ou encerrar lotação por operação, remover mídia e despublicar trocam `content_version` na própria transação; a leitura seguinte já não encontra a versão antiga.
   9. **Sitemap e API pública** seguem as mesmas regras de `valid_until` e `ETag`.
   10. **Teste obrigatório:** com relógio congelado e cache quente, avançar a data para a virada (inclusive em fuso com mudança de horário de verão e em dois fusos no mesmo bloco) e provar que lotação encerrada, lotação iniciada, exceção de horário e foto revogada **não** aparecem nem são servidas de cache; repetir com revalidação condicional (`If-None-Match`) e verificar 200 ou 404, nunca 304 para conteúdo vencido.
7. **Auditoria.** Publicar seções, publicar página, publicar configurações do site e retratações são eventos explícitos com `operation_id`, quantidade de sites e unidades afetados e `company_id` e `establishment_id` do nó de origem. O evento fica **no nó escrito**: publicar uma seção da empresa grava só a linha da empresa, e as unidades que herdam são afetadas por leitura, não por escrita, logo sem evento próprio. Cada linha efetivamente escrita (por exemplo, páginas de unidade num site corporativo) gera seu evento no nó dela, com o mesmo `operation_id`. `content_version` é contador derivado e entra em `unauditedFields()` com essa justificativa.
8. **Rascunho nunca altera a publicação.** Revisão de página em `draft`, `draft_settings` do site e valor de trabalho de seção não aparecem no público. Teste obrigatório na S06 e na S10.
9. **Retratação por leitura e por evento.** Inativar empresa ou estabelecimento (Actions do plano 001, que **não** são alteradas na S11) e inativar colaborador ou encerrar lotação valem na hora porque a leitura pública confere `active` e a vigência, em consulta indexada por chave primária, **antes** de servir qualquer resposta em cache. Para colaborador, lotação, consentimento e mídia (código novo), as Actions também trocam `content_version`. A chamada explícita de sincronização do diretório (S13) apenas remove a linha da projeção; ela não é a única barreira.
10. **Estado do rascunho na herança.** No estado `draft`, uma unidade em `inherit` resolve o valor de **trabalho** da empresa (a prévia da unidade mostra edições não publicadas da empresa). No estado `published`, resolve o valor **publicado** da empresa. Publicar a seção da unidade em `inherit` publica o modo, não um valor, de modo que a unidade passa a acompanhar o publicado da empresa.

---

## 10. Contratos de colaboradores e lotações

### 10.1 Domínio e nomes [R]

- Domínio `app/Domains/Colaboradores` com `Application/Actions`, `Application/Data`, `Application/Queries`, `Domain/Models`, `Domain/DTOs`, `Domain/Enums`, `UI/Controllers`.
- Models: `Employee`, `EmployeeAssignment`, `EmployeePublicProfile`, `Department`, `JobTitle`.
- Etiqueta especial: `tags.system_key = 'employee'`, módulo `people`, cor própria, criada por migration como as de Cliente e Fornecedor, derivada das relações ativas e visíveis.
- DTOs de domínio e Models com `register` e `revise`, Actions sem funções privadas, validação nas classes Data, conforme [Código do backend](../convencoes/codigo-backend.md).

### 10.2 Tabelas (rascunho de colunas, a confrontar na S03 e S04)

`departments`: `id`, `account_id`, `company_id`, `name`, `active`, timestamps. Único `(company_id, name)`. FK composta `(company_id, account_id)`. Alvo de FK composta `(id, company_id)` (seção 06.1).

`job_titles`: mesmas colunas e mesmo alvo `(id, company_id)` de `departments`. Texto livre curto por empresa, reutilizável nos filtros. Interface: **Função** (PD2-01).

`employees`: `id`, `account_id`, `company_id`, `person_id`, `active`, `work_email` nulo, `work_phone` nulo, `work_whatsapp` nulo, timestamps. Único `(company_id, person_id)`. FK compostas `(company_id, account_id)` e `(person_id, account_id)`. Alvo de FK composta `(id, company_id)`. Índice em `person_id` (ficha da pessoa e regra de edição do cadastro, como em `customers`).

`employee_assignments`: `id`, `account_id`, `company_id`, `employee_id`, `establishment_id`, `department_id` nulo, `job_title_id` nulo, `starts_on`, `ends_on` nulo, `is_primary`, `contact_mode` (`inherit` ou `override`), `work_contacts` JSON (nulo quando `inherit`; objeto, inclusive vazio, quando `override`), timestamps. FKs compostas `(employee_id, company_id)`, `(establishment_id, company_id)`, `(department_id, company_id)`, `(job_title_id, company_id)`. CHECKs: `ends_on is null or ends_on >= starts_on` e coerência de `contact_mode` e `work_contacts`. Índice único parcial da principal sem fim (`is_primary and ends_on is null`). Índice `(establishment_id, ends_on)` para a listagem por unidade, a **justificar por plano medido** na S05.

`employee_public_profiles`: `id`, `account_id`, `company_id`, `employee_id` único (FK composta `(employee_id, company_id)`), `show_in_team`, `show_name`, `show_title`, `show_department`, `show_work_email`, `show_work_phone`, `public_display_name` nulo, `public_title` nulo, `public_bio` nulo, `photo_media_id` nulo, `photo_consent_at` nulo, `photo_consent_revoked_at` nulo, `consent_text_version` nulo, `consent_recorded_by` (membership), `published_at` nulo, `published_by` nulo, `sort_order`, timestamps. **Não guarda contato**: e-mail e telefone exibidos vêm da lotação aplicável (seção 10.6). O perfil só passa a ser considerado pelos sites depois do ato `publish-profile` (`published_at`). Classe e permissões em DA2-20. `photo_media_id` usa FK composta `(photo_media_id, company_id)`.

### 10.3 Regras de negócio

1. Cadastrar colaborador **reutiliza** a Pessoa por documento completo, pela mesma busca de Clientes e Fornecedores, sem duplicar CPF. A resposta nunca revela dados de outra conta.
2. Um colaborador pode coexistir como Cliente, Fornecedor e Usuário da mesma pessoa.
3. Inativar o colaborador encerra as lotações abertas na data da operação (`ends_on = hoje`), na mesma transação, e torna o perfil público inelegível. Reativar **não** reabre lotações antigas: exige nova lotação.
4. Encerrar a última lotação aberta não inativa o colaborador, mas o perfil público deixa de ser elegível por não haver lotação vigente no escopo.
5. Trocar função ou departamento ao longo do tempo é: encerrar o período e abrir outro, em uma Action, com mesmo `operation_id` na auditoria.
6. Departamento ou função inativos não podem ser escolhidos em lotações novas e permanecem nas antigas.
7. Excluir fisicamente colaborador, departamento ou função não existe na v1. Apenas inativar.
8. Dados centrais da pessoa são editados pela regra de `account.people.update` da convenção de Pessoas. A ficha do colaborador exibe a mesma seção "Dados centrais" e o mesmo aviso de impacto.
9. Ter ou não login é exibido na ficha, como somente leitura, vindo do vínculo. Conceder acesso continua sendo feito em Usuários.
10. Nada neste módulo altera identidade de login (foto, e-mail de acesso, senha, 2FA, situação).

### 10.3.1 Semântica da vigência das lotações (revisão 2)

1. **Datas de calendário.** `starts_on` e `ends_on` são `date`, sem hora. `starts_on` é o **primeiro** dia de vigência e `ends_on` é o **último**, ambos inclusivos. Vigente na data D: `starts_on <= D` e (`ends_on` nulo ou `D <= ends_on`). Uma lotação de um dia (`starts_on = ends_on`) é válida.
2. **"Hoje".** É calculado no fuso IANA do estabelecimento da lotação (PD2-08, aprovada). Até a S07 criar `establishments.timezone`, a S05 usa o mesmo padrão (`America/Sao_Paulo`) por configuração, porque `app.timezone` é `UTC` e viraria o dia três horas cedo. A migration da S07 grava o mesmo padrão, então nenhuma vigência muda ao introduzir a coluna.
3. **Períodos adjacentes.** A troca de função em D encerra o período antigo em `D - 1 dia` e abre o novo em D. Não há dia sem lotação nem dia duplicado.
4. **Troca no mesmo dia do início.** Se o período aberto começou em D, encerrá-lo em `D - 1` produziria `ends_on < starts_on`, proibido pelo CHECK. A Action trata a operação como **correção** do período existente (alteração auditada), e não como nova lotação. Para preservar os dois períodos, o operador informa uma data inicial posterior à do período vigente.
5. **Retroativo e futuro.** Lotação retroativa é aceita se não sobrepuser outra do mesmo colaborador e estabelecimento, exige `account.employees.assign` e fica auditada. Lotação futura é aceita e só vale a partir de `starts_on`.
6. **Sobreposição.** Proibida para o mesmo par colaborador e estabelecimento, inclusive com períodos futuros. Estabelecimentos distintos podem coexistir.
7. **Lotação principal.** No máximo uma principal vigente em qualquer data, e os intervalos de principais do mesmo colaborador não se sobrepõem. Encerrar a principal enquanto houver outra lotação vigente exige indicar a nova principal na mesma operação. Sem outras lotações vigentes, o colaborador fica sem principal.
8. **Encerrar.** `ends_on = D` mantém a lotação vigente até o fim de D. **Expirar não é evento:** quando `D` passa, a lotação deixa de ser vigente por cálculo, sem escrita, sem auditoria e sem job (seção 09.7, item 6). Inativar o colaborador define `ends_on = hoje` nas lotações vigentes. Lotações futuras permanecem, mas ficam **inertes** enquanto o colaborador estiver inativo (a elegibilidade exige colaborador ativo). Reativar não reabre períodos antigos.
9. **Garantia transacional.** A Action abre `AuditLog::transaction()` e trava a linha de `employees` com `lockForUpdate()` antes de verificar sobreposição e principal. A trava no pai evita a corrida entre duas escritas do mesmo colaborador, inclusive na suíte SQLite. A integridade que depende de várias linhas é detectada por `institutional:check`. Integridade de empresa vem das FKs compostas.

### 10.4 Leitura e filtros

Queries recebem `OrganizationContext::scopeFor()` e aplicam o descritor no SQL, antes de paginar e contar (padrão de `CommercialRelationDirectoryQuery`).

- **Listagem por empresa (S04):** uma linha por colaborador em `/contas/{conta}/empresa/{empresa}/colaboradores`, com filtros de situação, presença de login e busca por nome ou documento. **Não há** filtro por estabelecimento, departamento ou função, coluna Unidades nem `todos` nesta etapa, porque ainda não existe autoridade para eles.
- **Listagem por unidade (S05):** com `employee_assignments`, entram a coluna Unidades agregada em uma consulta (sem N+1), o consolidado `todos` e os filtros abaixo.
- **Filtros (S05):** estabelecimento, departamento, função, situação, vigência, presença de login, perfil público (publicado, rascunho, sem consentimento).
- **Gestor de unidade (S05):** só vê colaboradores com lotação vigente ou histórica em unidades cobertas pela atribuição (DA2-18). A Query aplica o descritor de nós no SQL, antes de paginar e contar.
- Uma pessoa com duas lotações aparece **uma vez** na listagem.

### 10.5 Seleção para publicação

O bloco `team` de uma página escolhe por **critério** (todos os elegíveis, por estabelecimento, por departamento) e/ou **usa a seleção da unidade ou da empresa** (`team_display`, que guarda referências a colaboradores, nunca dados). Não existe lista manual de pessoas dentro da revisão da página: a escolha de pessoas é da seção `team_display`, editável só com `account.employees.select-team` (DA2-20). A regra é conteúdo editorial e fica na revisão; a **lista de pessoas** é resolvida na renderização e aplica sempre a elegibilidade: colaborador ativo, lotação vigente no escopo do site (seção 10.6), perfil público com ato `publish-profile` e, para a foto, consentimento válido. Nenhum dado de pessoa é gravado na revisão.

### 10.6 Dados profissionais por lotação e perfil público (revisão 2)

**Camadas, da mais central para a mais local.**

1. **Pessoa** (`people`): identidade e contatos centrais. Nunca publicados.
2. **Relação** (`employees`): contatos profissionais padrão da empresa (`work_email`, `work_phone`, `work_whatsapp`).
3. **Lotação** (`employee_assignments`): função, departamento e, opcionalmente, contatos profissionais próprios da unidade. `contact_mode = inherit` usa os da relação; `override` usa `work_contacts`, inclusive vazio intencional (mesma semântica da seção 09.2).
4. **Perfil público** (`employee_public_profiles`): **autorização e apresentação**. Guarda flags `show_*`, nome público opcional, texto, foto com consentimento e o ato de publicação. Não guarda contato e não copia função.

**Lotação aplicável.** Dado um site e um colaborador elegível:

- em site de unidade, a lotação vigente naquele estabelecimento;
- em bloco de uma unidade específica de um site corporativo, a lotação vigente naquela unidade;
- em bloco geral sem unidade, a lotação **principal** vigente e, se não houver, a vigente de menor `starts_on` (desempate por `id`).

O colaborador aparece **uma vez** por bloco.

**O que o público vê.**

- **Função:** `public_title` do perfil, se preenchido (vale em qualquer escopo, DA2-20); senão a função da lotação aplicável.
- **Departamento:** só com `show_department`.
- **Contatos:** o contato profissional efetivo da lotação aplicável (`override` da lotação, senão a relação), somente com `show_work_email` e `show_work_phone`. **Nunca** `people.email`, `people.phone`, `people.whatsapp`, `additional_*` nem e-mail de login.
- Os campos profissionais são **digitados** e nunca pré-preenchidos a partir de Pessoa (teste obrigatório).

**Consequências.** Mudar a função na unidade A não altera o que a unidade B mostra. Nada é duplicado no perfil: o valor é lido da lotação na renderização. Revogar consentimento, inativar colaborador ou encerrar a lotação aplicável retira a exibição na hora (retratação, seção 09.7).

---

## 11. Contratos de sites e publicação

### 11.1 Tabelas (rascunho, a confrontar na S10)

`sites`: `id`, `account_id`, `company_id`, `establishment_id` nulo, `code` (único por conta, usado nas URLs administrativas), `slug` (único global, vira subdomínio), `name`, `status` (`draft`, `published`, `unpublished`), `draft_settings` JSON e `published_settings` JSON (tema e navegação, com modos de herança, validados), `content_version` inteiro, `published_at` nulo, timestamps. Alvo de FK composta `(id, company_id)` para páginas e domínios. CHECKs de escopo. Publicação **desabilitada por padrão**. Um site só fica `published` com a página `home` publicada; o público vê uma página quando o site está `published` **e** a página tem revisão publicada.

`public_hosts` (substitui `site_domains`, DA2-19): `id`, `account_id`, `subject` (`site`, `directory`), `company_id` e `site_id` (preenchidos só quando `subject = site`; FKs compostas `(company_id, account_id)` e `(site_id, company_id)`), `hostname` único global (inclusive revogados), `kind` (`managed`, `custom`), `status` (`pending`, `verified`, `active`, `suspended`, `revoked`), `is_canonical`, `verification_token_hash`, `verified_at`, `tls_status`, `last_checked_at`, timestamps. **A tabela nasce completa na S11**, com os campos de verificação e TLS nulos; a S11 só **habilita** `kind = managed` e a S14 habilita `kind = custom` por regra de domínio, sem alterar o esquema (CHECK não se altera no SQLite). CHECKs: `subject = 'site'` exige `site_id` e `company_id`; `subject = 'directory'` os exige nulos; `kind = 'managed'` nunca está em `pending` nem `verified`. Índices únicos parciais: um host `directory` ativo por conta e um host canônico por site. A regra completa está em DA2-19.

`site_pages`: `id`, `account_id`, `company_id`, `site_id`, `establishment_id` nulo (página de unidade), `slug`, `kind` (`home`, `unit`, `custom`), `title`, `sort_order`, `show_in_menu`, `current_revision_id` nulo, `published_revision_id` nulo. Único `(site_id, slug)`. Uma `home` por site. FKs compostas `(site_id, company_id)`, `(establishment_id, company_id)`, `(current_revision_id, id)` e `(published_revision_id, id)` (seção 06.1). `kind = unit` só existe em site corporativo; em site de unidade, `establishment_id` é nulo ou igual ao do site (regra de domínio e `institutional:check`).

`site_page_revisions`: `id`, `account_id`, `company_id`, `site_page_id`, `number`, `state` (`draft`, `published`, `archived`), `blocks` JSON, `seo` JSON, `schema_version`, `note`, `created_by` (membership), `published_by`, `published_at`. O conteúdo (`blocks`, `seo`, `schema_version`, `number`, `note`) de revisão publicada ou arquivada é **imutável**: o Model recusa alteração e a Action só cria linha nova. `state`, `published_by` e `published_at` mudam somente pelas Actions de publicar, despublicar e reverter (DA2-22). `published_revision_id` é a autoridade da publicação.

Históricos de slug: `site_slug_redirects` (`account_id`, `company_id`, `site_id`, `old_slug`, `page_id`; FKs compostas `(site_id, company_id)` e `(page_id, site_id)`), criada quando um slug muda, para responder 301 (seção 13.3).

### 11.2 Catálogo de blocos tipados

Cada bloco tem `type`, `schema_version`, `data` e uma classe de validação. Catálogo inicial: `hero`, `about`, `differentials`, `unit` (card de unidade), `units_locator`, `gallery`, `cta`, `team`, `faq`, `map`, `contact`, `banner`, `hours`. `products` e `services` ficam **reservados** (seção 15) e rejeitados pelo validador enquanto o catálogo não existir. Depoimentos ficam fora do primeiro corte.

Regras de segurança dos blocos:

- **[AP, PD2-15]** O texto é de **formatação restrita** (parágrafo, negrito, itálico, listas e links), validada no servidor contra um conjunto fechado e gerada pelo renderizador, ou sanitizada por allowlist de elementos e atributos. Sem `script`, `style`, `iframe`, `form`, atributos `on*`, URLs `javascript:` ou `data:` (a mesma ideia já aplicada aos artigos de ajuda). Sem HTML arbitrário e sem WYSIWYG completo no início.
- Links externos recebem `rel="noopener noreferrer"`.
- `map` usa coordenadas ou endereço do estabelecimento e um provedor de uma lista fixa. **Nenhuma URL de iframe vem do usuário.**
- Referências a mídia, colaborador e unidade são validadas no escopo do site ao salvar.
- Tamanho máximo de revisão e de blocos por página definido na S10 (limite numérico fica para a medição).

### 11.3 Estados e fluxo de publicação

```text
rascunho (revisão atual, não pública)
   │ salvar        → nova revisão ou atualização do rascunho
   │ prévia        → renderização autenticada, noindex, sem cache
   │ publicar      → exige account.sites.publish no nó do site
   ▼
publicada (published_revision_id aponta; content_version++)
   │ editar        → cria novo rascunho, a publicada segue no ar
   │ reverter      → copia revisão antiga para novo rascunho; publicar é ato separado
   │ despublicar   → published_revision_id nulo; site ou página sai do ar com 404 neutro (nunca 410)
   ▼
despublicada
```

- **Sem aprovação em dois passos [AP, PD2-06].** Editar (`update`) e publicar (`publish`) são permissões distintas, e quem tem `publish` publica sem segunda pessoa. Um fluxo de aprovação seria decisão futura.
- **Publicar** é feito por três Actions com `AuditLog::transaction()`: `PublishPageAction` (revisão de uma página), `PublishSiteSettingsAction` (tema e navegação) e `ActivateSiteAction` (site passa a `published`, exige `home` publicada). Cada uma incrementa `content_version` e dispara `SyncDirectoryEntryAction` quando altera o destino do site. **Não há job**: a troca de versão invalida o cache (DA2-14, DA2-21). **Não** cria ficha de diretório (DA2-17). Despublicar a `home` despublica o site (DA2-22).
- Alteração de dado do ERP segue a seção 09.7: **operacional** reflete ao salvar nos blocos já publicados que dependem dele; **editorial** exige publicação; **retratação** vale na hora. Nada novo passa a ser exposto sem ato explícito. Itens sem status publicável não aparecem.
- Site novo nasce `draft`. Página vazia ou duplicada sai com `noindex`.

### 11.4 Resolução por host e slug

1. Middleware normaliza o host (regras 1, 6 e 7 de DA2-19) e consulta `ResolvePublicHostQuery`, a única autoridade, com `status = active`.
2. Encontrado o host, preenche `PublicContext` com o sujeito `site` (e o site) ou `directory` (e a conta). Não encontrado: 404 neutro. As rotas de cada sujeito são separadas (seção 17.3): um host de site não serve rotas do diretório, e vice-versa.
3. O caminho resolve a página por `(site_id, slug)`. Slug antigo em `site_slug_redirects` responde 301 para o atual. Antes de servir (inclusive de cache), a leitura confere que empresa e estabelecimento do site estão ativos (seção 09.7, item 9).
4. Site em `draft` ou `unpublished` responde 404 no público (e prévia autenticada continua funcionando).
5. Slugs reservados (`www`, `app`, `admin`, `api`, `saas`, `mail`, nomes da plataforma e rotas) são recusados, tanto para site quanto para o rótulo do host do diretório. Colisão entre tenants é impossível porque `public_hosts.hostname` é único global.
6. O 404 do público não diferencia site inexistente, despublicado e de outro tenant.

### 11.5 Cache e invalidação

Seção DA2-14 e regras da seção 09.7. Invalidadores de `content_version`: publicar página, configurações ou site, despublicar, reverter, publicar seções institucionais, alterar dado operacional exibido (horário, contato, endereço, exceção), alterar lotação ou perfil público exibido, revogar consentimento, trocar mídia. `valid_until` no início do próximo dia civil do fuso do estabelecimento envolvido nas páginas com bloco dependente de data, com revalidação a cada acesso (seção 09.7, item 6). Cada invalidador é coberto por teste que renderiza antes e depois. A limpeza de cache **não** é um invalidador nem um job. O que muda com a data sem evento segue a seção 09.7, item 6. O estado "aberto agora" fica fora da página em cache (seção 09.5).

### 11.6 Domínios próprios (S14)

- **Fluxo.** Tenant informa o host. O sistema gera um token e instrui um registro TXT em `_prospera-verify.{host}` e um CNAME ou A para o destino da plataforma. Uma verificação por DNS (job periódico e também sob demanda no botão "Verificar agora", com tempo limite curto) comprova a posse e muda `pending` para `verified`. A propagação de DNS leva minutos ou horas, por isso o reteste automático exige **agendador e worker**, que só existem a partir da S14 (DA2-21, seção 19.1). Só depois a emissão do certificado é permitida e o domínio vira `active`.
- **Segurança.**
  - Consulta DNS somente a resolvedor configurado, com timeout e sem seguir redirecionamentos. O host informado é validado (sem IP, sem `localhost`, sem faixas privadas, sem domínio da própria plataforma) para evitar SSRF.
  - Prova de posse é por TXT, não por HTTP no host.
  - Um host pertence a um único site; tentar reivindicar host de outro tenant responde de forma neutra.
  - Domínio revogado ou site excluído passa a `revoked`, para de responder e deixa de ser autorizado no `ask`.
  - Revalidação **periódica** (agendador) detecta domínio que apontou para outro lugar e o suspende. Cada verificação é um job com tempo limite, idempotente, que leva `account_id`, `company_id`, `site_id`, `membership_id` e `actor_id` (o agendador usa autoria de sistema) e reautoriza.
  - Domínio apex e `www` são tratados como hosts distintos, com redirecionamento canônico configurável.
- **TLS.** Caddy com TLS sob demanda e endpoint `ask` que só autoriza host `active`. Wildcard do domínio gerenciado depende da hipótese H5 e de PD2-02.
- **Logs.** O prefixo de qualquer rota com segredo no caminho entra no `docker/Caddyfile` na mesma etapa, como o README exige.

---

## 12. Segurança, RBAC, auditoria e LGPD

### 12.1 Permissões (catálogo novo)

Cada ação tem permissão própria, com identificador literal. Todas seguem o `permissions:sync`. Módulos novos em `config/permissions.php`: `employees` (Colaboradores), `departments` (Departamentos), `job-titles` (Funções, PD2-01, aprovada), `institutional` (Perfil institucional), `media` (Mídia), `sites` (Sites), `domains` (Domínios), `directory` (Diretório).

| Módulo | Ações | Alvos admitidos |
| --- | --- | --- |
| `account.employees` | `list`, `view`, `create`, `update`, `activate`, `deactivate` | `company`. `list` e `view` também `establishment` **a partir da S05**, pela lotação (DA2-18) |
| `account.employees` | `select-team` (escolher e ordenar, na unidade, quem aparece na equipe pública; PD2-16) | `company`, `establishment` (pela lotação) |
| `account.employees` | `assign` (gerir lotações), `edit-profile` (criar e editar o perfil público **ainda não publicado**), `publish-profile` (publicar, despublicar e editar perfil publicado), `consent` (registrar e revogar consentimento de foto) | `company`; `assign` também `establishment` a partir da S05. As três de perfil e consentimento são só `company` (DA2-20). `select-team` é a única ação de equipe pública que admite `establishment` |
| `account.departments` e `account.job-titles` | `list`, `view`, `create`, `update`, `activate`, `deactivate` | `company` |
| `account.institutional` | `view`, `update` (por seção do dono), `publish` (publicar seções editoriais) | `company`, `establishment` |
| `account.media` | `list`, `create`, `update`, `delete` | `company`, `establishment` |
| `account.sites` | `list`, `view`, `create`, `update`, `preview`, `publish`, `unpublish`, `revert` | `company`, `establishment` |
| `account.domains` | `list`, `create`, `verify`, `delete` | `company`, `establishment` |
| `account.directory` | `view`, `update` (categorias e tags da ficha), `publish`, `unpublish` (ficha pública, modo A) | `company`, `establishment` |
| `account.directory` | `configure` (host e nome público do diretório da conta, DA2-19) | `tenant` |

- Ações novas no vocabulário (`publish`, `unpublish`, `revert`, `preview`, `verify`, `assign`, `consent`, `edit-profile`, `publish-profile`, `select-team`, `configure`) entram em `actions` de `config/permissions.php`. Subrecursos de uma ficha única (exceção de horário, categorias da ficha, página dentro do site) usam a ação `update` do recurso-pai, sem permissão genérica de gerenciar.
- **PD2-06 [AP].** Em todo recurso publicável, editar e publicar são permissões distintas, e não há aprovação em duas etapas.
- O nó do recurso decide: site de unidade avalia sobre `establishment`; site corporativo, sobre `company`. Atribuição de estabelecimento **não** alcança o site corporativo nem outra unidade.
- O administrador integral da conta recebe as novas permissões `account`. Nenhum cargo de tenant recebe `platform.*`.
- Testes de antiescalada: operador não concede permissão que não tem; editor de unidade A tenta editar B e é **negado (404 para ID de outro escopo, 403 para ação não permitida) sem alterar nada**. **Verificado na S02:** a [convenção de Auditoria](../convencoes/auditoria.md) diz que tentativas negadas não são alterações e que não há política aprovada de eventos de segurança. Portanto **não se grava evento de negócio para tentativa negada**, e esta frente não cria trilha paralela. Registrar tentativas é uma decisão futura da plataforma ("Evoluções independentes" da convenção).
- Papéis do briefing (editor corporativo, gestor de unidade, editor de conteúdo) **não** viram cargos fixos. São combinações sugeridas de permissões em cargos configuráveis, documentadas na ajuda.

### 12.2 Matriz de acesso e privacidade

| Dado ou ação | Visitante | Editor de conteúdo | Gestor de unidade | Administrador de empresa | Administrador da conta |
| --- | --- | --- | --- | --- | --- |
| Página publicada | Vê | Vê | Vê | Vê | Vê |
| Rascunho, revisões, prévia | Não | Conforme `sites.view` e `preview` no nó | Só da unidade | Da empresa | Todas |
| Editar conteúdo | Não | `sites.update` | Só da unidade | Da empresa | Todas |
| Publicar e despublicar | Não | Só com `publish` | Só da unidade | Da empresa | Todas |
| Domínios | Não | Não | Só com `domains.*` da unidade | Da empresa | Todas. Host do diretório: `directory.configure` no tenant |
| Ficha administrativa do colaborador | Não | Não | Só a lotação da unidade e o contato efetivo dela, **a partir da S05** (DA2-18) | Da empresa | Todas |
| CPF, documento, endereço pessoal, salário | **Nunca** | Não | Conforme Pessoas | Conforme Pessoas | Conforme Pessoas |
| E-mail de login | **Nunca** | Não | Não | Não | Só leitura na identidade |
| Perfil público (nome, função, foto, contatos públicos) | Vê se elegível | Não | **Não altera.** Com `select-team`, escolhe e ordena quem aparece na equipe da unidade (PD2-16) | `edit-profile`, `publish-profile` e `consent` da empresa | Todas |
| Mídia original | Não | Conforme `media.list` | Da unidade | Da empresa | Todas |

### 12.3 Dados que nunca saem na API pública

Documento (CPF e CNPJ de pessoa), endereço e contatos pessoais, e-mail de login, IDs internos de usuário, vínculo, cargos de acesso, salário, situação trabalhista, observações internas, estoque, preço e custo (salvo opt-in futuro), tokens, caminhos privados de arquivo. **Endereço doméstico** de pessoa jurídica home office só é publicado por opção explícita (campo "Exibir endereço" no estabelecimento, desligado por padrão).

### 12.4 LGPD, foto e contatos da equipe

1. Exposição pública é **opt-in por colaborador** e por campo. O padrão é nada público.
2. Foto exige consentimento registrado: data, versão do texto e quem registrou. Sem consentimento, a foto não é exibida.
3. **Revogação** é uma Action **síncrona**: marca `photo_consent_revoked_at`, desvincula a mídia, apaga os derivados públicos, incrementa `content_version` dos sites afetados e remove do diretório, tudo na mesma transação e requisição, sem job (DA2-21). Teste exige que a foto deixe de ser servida.
4. **Contato público não é uma terceira cópia.** O perfil público só autoriza a exibição do contato profissional efetivo da lotação aplicável (seção 10.6), por `show_work_email` e `show_work_phone`, com prévia do que será exibido. O sistema nunca lê nem copia `people.email`, `people.phone`, `people.whatsapp` ou `additional_*`, e nunca expõe o e-mail de login.
5. Colaborador inativo, sem lotação vigente no escopo ou com consentimento revogado perde a publicação **por política**, calculada na leitura (elegibilidade), sem depender de alguém desligar o perfil. O perfil pode continuar com `published_at` preenchido, e volta a aparecer se o colaborador for reativado e lotado de novo; isso exige nova lotação e é opt-in já dado, por isso a tela avisa na reativação.
6. Histórico privado é mantido. Fotos e derivados seguem a retenção definida em S08.
7. Texto e base legal do consentimento exigem revisão jurídica (PD2-07). O plano só prevê o registro técnico.
8. Pessoa com login poderá revogar o próprio consentimento por permissão `self.*` em etapa futura, sem alterar a identidade global. Não faz parte deste ciclo.

### 12.5 Auditoria

- Models implementam `Auditable`, usam `AuditsChanges` e declaram `auditedFields()`: `Employee`, `EmployeeAssignment`, `EmployeePublicProfile`, `Department`, `JobTitle`, `ProfileSection`, `HourException`, `Site`, `SitePage`, `PublicHost`, `MediaAsset`, `DirectoryListing`. `SitePageRevision` é imutável: seus eventos (criar, publicar, reverter) são registrados explicitamente. Publicar seções, publicar configurações do site e publicar ou despublicar ficha também são eventos explícitos com `operation_id`.
- Eventos explícitos com `AuditLog::record` e rótulos em `config/audit.php`: publicar, despublicar, reverter, verificar domínio, ativar e revogar domínio, registrar e revogar consentimento, alterar seção para `inherit`, `override` ou `disabled`.
- **Dados protegidos:** imagens, conteúdo de arquivo, tokens de verificação e o texto livre integral de blocos não entram no histórico técnico. Registra-se o fato da alteração e o identificador da revisão.
- Eventos gravam `company_id` e `establishment_id` pelo nó do recurso. Operações que afetam vários nós (publicar site corporativo com páginas de unidade) usam o mesmo `operation_id`.
- Leituras públicas, cache e prévia não são operações auditáveis. A prévia é só autenticada (PD2-09, aprovada); a geração de link compartilhável, se um dia existir, será auditável.
- **Vigência que expira sem edição** não é operação e não gera evento (seção 09.7, item 6). Tentativa negada não gera evento de negócio (seção 12.1).
- Operações automáticas (agendador, a partir da S14) usam autoria de sistema (`context = system`).
- `php artisan audit:check` deve permanecer verde a cada etapa.

### 12.6 Demais controles

| Risco | Controle |
| --- | --- |
| XSS no conteúdo | Allowlist de marcação, ausência de HTML livre e de iframe arbitrário, CSP no público com `default-src 'self'` e origens fixas |
| Upload malicioso | MIME pelo conteúdo, limite de tamanho e dimensões, reprocessamento, sem SVG, nome gerado, varredura de extensão dupla |
| SSRF | Validação de host em domínios, sem requisição a URL de usuário, DNS por resolvedor configurado |
| Enumeração de tenant e slug | 404 neutro e idêntico para site inexistente, despublicado ou de outro tenant. Mensagens de slug ocupado não revelam o dono |
| Domínio tomado | Prova de posse por TXT, `ask` do TLS restrito, revalidação periódica |
| Vazamento entre tenants | Contexto por host, Queries com `account_id`, FKs compostas, testes de leitura cruzada de rascunho e mídia |
| Abuso | Rate limit nas rotas públicas e na verificação de domínio, limites de sites por conta (PD2-11) |
| Indexação indevida | `noindex` em rascunho, prévia, despublicado e páginas vazias; `robots.txt` por host |
| Cookies | Domínio público sem cookie de sessão do app |
| Log | Qualquer rota com segredo no caminho entra no `docker/Caddyfile` |

---

## 13. Mídia, acessibilidade e SEO

### 13.1 Mídia

- `media_assets` (alvo `(id, company_id)`): `id`, `account_id`, `company_id`, `establishment_id` nulo, `purpose` (`logo`, `banner`, `gallery`, `employee_photo`, `og_image`), `original_path`, `mime`, `bytes`, `width`, `height`, `sha256`, `alt_text`, `license_note`, `status`, `uploaded_by` (membership).
- `media_variants`: `account_id`, `company_id`, `media_asset_id` (FK composta `(media_asset_id, company_id)`), `kind` (largura nominal), `public_path` opaco, `bytes`, `width`, `height`. **Gerados de forma síncrona no envio** (limites 5 MB e 20 MP lidos antes de decodificar; H1: 25 ms e 30 MB para 1600x1200). Nunca sob demanda numa requisição pública e nunca na fila antes da decisão do responsável (DA2-21).
- **Texto alternativo é obrigatório** para mídia usada em página. Imagem decorativa exige marcação explícita.
- Reutilização por **referência** com o corte de cada uso guardado no bloco, nunca copiando o arquivo.

**Orçamento e segurança do processamento (S08).** O envio síncrono é a proposta, e só vale enquanto todos os critérios abaixo forem medidos e cumpridos.

1. **Barreiras antes de decodificar**, nesta ordem, cada uma recusando com 422 e mensagem clara: tamanho em bytes (padrão 5 MB, igual a `upload_max_filesize`); tipo pelo **conteúdo** (JPEG, PNG, WebP; sem SVG, sem GIF, sem WebP animado, sem PNG animado, sem múltiplos quadros); dimensões lidas do cabeçalho por `getimagesize` (lado máximo e **megapixels**, padrão 20 MP); e **estimativa de memória** (largura x altura x bytes por pixel do formato, vezes o fator medido de cópias do GD) comparada com o **orçamento** de memória da requisição. O orçamento é uma fração do `memory_limit` real, fixada na S08 a partir da linha de base medida. Um arquivo aceito pelas três primeiras barreiras mas acima do orçamento é recusado **antes** de qualquer decodificação.
2. **Nunca morrer por falta de memória.** Nenhuma entrada aceita pode levar a erro fatal de memória. A recusa é por estimativa, e não por capturar o erro depois (o H1 mostrou que 48 MP em 128 MB falha na alocação). O teste inclui arquivos que **excedem** o ambiente (por exemplo, 48 MP) e exige recusa limpa.
3. **Duração:** orçamento de tempo de parede por envio, fixado após a linha de base e inferior a `max_execution_time` e ao tempo limite do proxy. Estourar o orçamento aborta, remove arquivos parciais e responde erro, sem deixar mídia pela metade.
4. **Atomicidade:** original e derivados só ficam persistidos juntos. Falha em qualquer variante desfaz o envio (transação e remoção de arquivos temporários). Mídia sem derivados nunca fica utilizável.
5. **Concorrência:** o pico de memória por envio multiplicado pelo número de workers e fios simultâneos do FrankenPHP cabe na memória do contêiner. Medido com envios paralelos reais, 1 e 2 workers, e sem crescimento contínuo de memória por worker.
6. **Corpus de medição:** JPEG de 12 e 20 MP (inclusive progressivo), PNG grande, WebP, imagens abaixo do limite de bytes e acima do de pixels, arquivo disfarçado e arquivo truncado. Registra pico de memória, tempo (média e p95) e taxa de recusa.
7. **Se o síncrono não atender**, a S08 **não** entrega com lacuna: a própria S08 incorpora o processamento assíncrono (fila, worker, estado `processing` e `failed` visíveis, mídia indisponível ao público até concluir, reautorização e limpeza em falha) e implanta no laboratório os serviços pelos critérios da seção 19.1. A S08 só encerra com o fluxo escolhido completo, nunca com derivados pendentes sem infraestrutura que os produza.
- Exclusão conservadora: mídia em uso numa revisão publicada não é excluída; é marcada para remoção quando sair de uso. A remoção é **por evento**: ao publicar nova revisão, despublicar ou reverter, a Action recalcula o uso da mídia da revisão anterior e remove o que ficou órfão, na mesma requisição. Resíduos são apontados pelo `institutional:check` e removidos por comando manual (`media:purge`, somente leitura em modo `--dry-run`). **Não há tarefa agendada antes da S14**; quando o agendador existir, o comando passa a rodar nele.
- Biblioteca corporativa (`establishment_id` nulo) e galeria local (preenchido). Unidade vê as duas; a empresa não vê o conteúdo privado de outra empresa.
- Biblioteca: GD, sem dependência nova (PD2-05). `exif` só mediante prova na S08 (PD2-13, aprovada). Armazenamento e CDN: decisão e custo em PD2-05 (parte pendente) e seção 22. O contrato de acesso ao arquivo é isolado em uma interface, para trocar o disco sem alterar Actions.

### 13.2 Acessibilidade

- Contraste mínimo WCAG AA validado ao salvar `palette` (reaproveitando a regra do GNAI2 após PD2-03, ou implementação própria).
- Estrutura semântica: um `h1` por página, hierarquia de títulos, landmarks, `alt` obrigatório, foco visível, navegação por teclado, links com nome acessível, `lang="pt-BR"`.
- Mapa e carrosséis têm alternativa textual. Sem conteúdo que dependa de movimento.
- Teste automatizado simples (ausência de `alt`, `h1` único, contraste do tema) na suíte; verificação manual por leitor de tela no fechamento técnico e na homologação funcional posterior.

### 13.3 SEO

- Metadados por página (`title`, `description`, imagem de compartilhamento) com padrão herdado de `seo_defaults`.
- `canonical` por host. Sitemap por host listando só páginas publicadas e indexáveis. `robots.txt` por host. `noindex` para rascunho, prévia, despublicado, página sem conteúdo e duplicadas.
- **JSON-LD `LocalBusiness`** somente em páginas de estabelecimento com nome, endereço e telefone completos e coerentes (NAP) com a ficha. Nunca gerar `LocalBusiness` para empresa sem unidade física publicada.
- Slugs estáveis. Renomear grava o antigo em `site_slug_redirects` e responde 301. Colisão por site é recusada.
- **Páginas de unidade não são geradas em massa.** Uma página de unidade só existe se alguém a criou e se tem conteúdo próprio mínimo definido na S12 (endereço, horário e ao menos um bloco local). Caso contrário, fica `noindex`.
- Open Graph e Twitter Cards a partir dos mesmos metadados.

---

## 14. Busca e diretório

### 14.1 Escopo e decisão pendente

**Decisão [AP, PD2-04]:** o diretório é **por conta** no primeiro ciclo (cada tenant publica o próprio diretório, coerente com white label), em um host próprio (`public_hosts`, `subject = directory`, DA2-19). Um diretório que reúna várias contas da plataforma é decisão de produto e privacidade posterior e **não** é preparado por este plano. O diretório público não é compartilhamento entre empresas: é projeção opt-in de cada empresa, e nenhum usuário de uma empresa vê dados da outra por ele.

### 14.2 Projeção `directory_entries`

Tabela **derivada**, de propriedade do nó de origem (empresa ou estabelecimento, com `account_id`, `company_id` e `establishment_id` nulo ou preenchido) e lida publicamente por conta: reconstruída por `SyncDirectoryEntryAction` a partir da ficha publicada (`directory_listings`), das seções **publicadas** e do melhor destino publicado, e removida na despublicação. Colunas: `account_id`, `company_id`, `establishment_id` nulo, `listing_id`, `kind` (`company` ou `establishment`), `slug`, `display_name`, `trade_name`, `city`, `neighborhood`, `state`, `categories`, `tags`, `search_text` normalizado, `site_id` e `page_slug` de destino (nulos no modo A), `updated_at`. Não guarda documento, e-mail, telefone pessoal nem dados de pessoas.

A tabela é a **única** fonte de leitura do diretório. Isso isola a busca pública do cadastro e permite medir e indexar sem tocar nos módulos privados.

### 14.2.1 Ficha do diretório: autoridade do modo A (DA2-17)

`directory_listings` (alvo `(id, account_id)`): `id`, `account_id`, `company_id`, `establishment_id` nulo, `slug` (único por conta; compõe o caminho no host do diretório), `status` (`draft`, `published`, `unpublished`), `tags` JSON, `published_at`, `published_by`, timestamps, mais o pivô `directory_listing_categories` (`account_id`, `listing_id`, `category_id`, com FKs compostas) para `public_categories` (PD2-12, aprovada). FKs compostas `(company_id, account_id)` e `(establishment_id, company_id)`; no máximo uma ficha por empresa e uma por estabelecimento.

| Tema | Regra |
| --- | --- |
| Criação | Nenhuma ficha existe por padrão. Criar empresa ou estabelecimento não lista ninguém |
| Publicar | `PublishDirectoryListingAction`, permissão `account.directory.publish` no nó. Ficha de empresa: empresa ativa, nome e ao menos um contato publicado (a empresa não tem endereço). Ficha de estabelecimento: estabelecimento ativo, ficha da empresa publicada, endereço público (opção "Exibir endereço" ligada, com cidade e UF) e ao menos um contato publicado, próprio ou herdado (DA2-23) |
| Despublicar | `UnpublishDirectoryListingAction`, permissão `account.directory.unpublish`. Remove a entrada do diretório e do sitemap. Despublicar a empresa oculta as fichas dos estabelecimentos, que voltam quando a empresa for republicada |
| Projeção | `SyncDirectoryEntryAction` reconstrói ou remove a entrada. É chamada pelas Actions de publicar e despublicar ficha, de publicar seções institucionais, de publicar e despublicar página ou site (só para o destino) e pelas Actions de inativar empresa ou estabelecimento (única alteração em Actions do plano 001, aditiva, coberta por teste de regressão e **não** a única barreira: a leitura pública também confere `active`, seção 09.7) |
| Destino | Com site publicado, o destino é a página do site. Sem site (modo A), é a ficha pública no host do diretório da conta (`/{slug}`), renderizada da projeção. Sem host de diretório ativo, a ficha existe mas não tem endereço público: o diretório precisa ser configurado (`account.directory.configure`) |
| Auditoria | `DirectoryListing` é `Auditable`. Publicar e despublicar são eventos explícitos com `operation_id`, `company_id` e `establishment_id` do nó |
| Permissões | Editar a ficha (`update`) não publica. Quem publica o site não publica a ficha, e vice-versa |

### 14.3 Regras

- **Agrupamento por empresa** (`company_id`), nunca por semelhança de nome. Duas empresas de nome parecido são dois resultados. Franquias e pessoas jurídicas distintas nunca se fundem.
- Resultado de empresa mostra suas unidades. Se a consulta casa com cidade, bairro ou nome da unidade, o resultado traz um deep link para a unidade correta.
- Busca insensível a acentos e caixa, por razão social, nome fantasia, apelido (nome de exibição), cidade, bairro, categoria, tag e, no futuro, produto e serviço.
- Ficha de empresa em modo A (sem site) existe como `directory_entries` com `site_id` nulo. A ficha mostra só campos públicos escolhidos. Empresa sem unidade com endereço público é encontrada por nome, categoria e tag, não por cidade (DA2-23).

### 14.4 Simulação "Gartan Guanambi" versus "Gartan"

| Consulta | Resultado esperado | Regra |
| --- | --- | --- |
| `Gartan` | Um resultado de empresa **Gartan**, com as unidades listadas dentro dele | Agrupamento por `company_id` |
| `Gartan Guanambi` | O mesmo grupo, com a unidade **Gartan Guanambi** em destaque e link direto para a página dela | O termo restante (`Guanambi`) casa com cidade ou nome da unidade e promove o deep link |
| `Gartan` quando existem duas empresas distintas com "Gartan" no nome | Dois resultados separados, nunca fundidos | Sem agrupamento por nome |
| `gartan guanambí` (sem acento e caixa diferente) | Igual a `Gartan Guanambi` | Normalização |

### 14.5 Implementação e medição

- No PostgreSQL, `unaccent` e `pg_trgm` com índice GIN sobre `search_text`, **se as hipóteses H2 e a medição justificarem** (convenção de [Performance](../convencoes/performance.md): índice só com consulta e plano medidos).
- Na suíte SQLite, a normalização é feita em PHP e gravada em `search_text`, de modo que o contrato funcional seja testável sem extensões. Os planos e a relevância com trigram são medidos fora da suíte, em PostgreSQL isolado.
- Sem motor de busca externo no primeiro corte. Reavaliar apenas se a medição mostrar que o PostgreSQL não atende.

---

## 15. Integração futura com produtos e serviços

Nada disto é construído neste plano.

- O plano 001 já fixa: produto pertence à empresa (`company_id`), habilitado por unidade em `product_establishment`, com preço local, custo e saldo em tabelas próprias por estabelecimento ([plano 001, seção 10.3](001-arquitetura-organizacional-prospera.md)).
- Esta frente reserva os tipos de bloco `products` e `services` e rejeita ambos no validador enquanto o módulo de catálogo não existir.
- **Publicação opt-in por item:** o catálogo precisará de um estado publicável por empresa e por unidade, **separado** de "ativo". Preço e estoque públicos são opt-in por item, nunca automáticos.
- Disponibilidade local (serviço global disponível apenas na filial B) é a habilitação por unidade já prevista.
- `directory_entries` aceita novas linhas `kind = product` e `kind = service` sem alterar as existentes (coluna `kind` aberta por enum).
- **Dependência bloqueante:** a etapa reservada de catálogo público (seção 19) só pode ser planejada em detalhe depois que o módulo de catálogo existir. Não há data a prometer.

---

## 16. Mapa de telas

Todas as telas usam `BusinessList`, `BusinessForm`, `BusinessListSearch`, `SelectControl`, `Modal` e `PreviewSection`, tokens de `resources/js/styles/` e os padrões de botão (**Salvar**, **Excluir**, Cancelar). Cada tela de negócio nova tem artigo de ajuda e tour (seção 21). O tour e o botão pertencem à tela, nunca ao modal. Nenhuma tela usa travessão.

### 16.1 Retaguarda

| Tela | Rota (rascunho) | Permissão | Etapa |
| --- | --- | --- | --- |
| Departamentos (lista e modal) | `/contas/{conta}/departamentos` (tela única, empresas pelo acesso, DA2-24) | `account.departments.*` | S03 |
| Funções (lista e modal; "Função" no singular, nunca "Cargo") | `/contas/{conta}/funcoes` (tela única, empresas pelo acesso, DA2-24) | `account.job-titles.*` | S03 |
| Colaboradores, lista por empresa (S04) | `/contas/{conta}/empresa/{empresa}/colaboradores` | `account.employees.list` | S04 |
| Colaboradores, lista por unidade e consolidado `todos` (S05) | `/contas/{conta}/estabelecimento/{estabelecimento}/colaboradores` | `account.employees.list` (alvo `establishment`) | S05 |
| Ficha do colaborador (dados centrais, relação, contatos profissionais, lotações, acesso somente leitura) | `.../colaboradores/{id}/editar` | `account.employees.view` e `update`; lotações com `assign` | S04 (sem lotações), S05 |
| Perfil público do colaborador | seção da ficha | `account.employees.edit-profile` e `publish-profile` | S09 |
| Perfil institucional da empresa (abas Identidade, Sobre, Contatos, Horários, Mídia) | `/contas/{conta}/empresa/{empresa}/institucional` | `account.institutional.*` | S06, S07 |
| Perfil institucional do estabelecimento (valor efetivo, origem, voltar a herdar) | `/contas/{conta}/estabelecimento/{estabelecimento}/institucional` | `account.institutional.*` | S06, S07 |
| Biblioteca de mídia | `.../midia` | `account.media.*` | S08 |
| Sites (lista) | `.../sites` | `account.sites.list` | S10 |
| Editor do site (páginas, blocos, SEO, revisões, prévia desktop e mobile) | `.../sites/{id}/paginas/{pagina}` | `account.sites.update`, `preview`, `publish` | S10, S11 |
| Domínios do site | `.../sites/{id}/dominios` | `account.domains.*` | S14 (host gerenciado criado na S11 junto do site) |
| Diretório da conta (host e nome público) | `/contas/{conta}/diretorio` | `account.directory.configure` (tenant) | S13 |
| Diretório (visibilidade e fichas) | `.../diretorio` | `account.directory.*` | S13 |

Estados vazios obrigatórios: sem colaboradores (convida a cadastrar), sem lotação (explica o efeito na publicação), sem seção configurada (mostra o padrão da plataforma), sem site (oferece criar corporativo), sem mídia, sem domínio, publicação desabilitada.

A navegação entra por **Organização** (Empresa e Estabelecimento ganham as abas) e por um grupo novo **Institucional** na sidebar, com Departamentos, Funções, Colaboradores, Sites, Mídia e Diretório, visíveis só com a permissão de listar (ou, no Diretório, de visualizar). A sidebar e as abas existentes não são removidas, e nenhum fluxo do ERP some.

### 16.2 Público

| Superfície | Descrição |
| --- | --- |
| Site corporativo | Página inicial, sobre, equipe, unidades com localizador e contato |
| Página de unidade | Endereço, horário efetivo, equipe da unidade, galeria local, CTA |
| Site de unidade | Landing própria no subdomínio gerenciado (e, depois, no domínio próprio) |
| Diretório | Lista agrupada por empresa, busca com deep link para unidade, ficha da empresa |
| Utilitários | `robots.txt`, `sitemap.xml`, 404 neutro, 301 de slug antigo |

Exemplos de URL com domínio gerenciado fictício: `gartan.{dominio-gerenciado}/unidades/guanambi` e `gartan-guanambi.{dominio-gerenciado}`.

---

## 17. APIs e Actions em nível de contrato

Os nomes abaixo são intenção de contrato. **Nenhuma rota, controller ou Action é criada neste plano.** Cada etapa confronta os nomes com `routes/account.php` e com as convenções antes de criar.

### 17.1 Actions por domínio

| Domínio | Actions |
| --- | --- |
| `Colaboradores` | `SaveDepartmentAction`, `SaveJobTitleAction`, `RegisterEmployeeAction`, `SaveEmployeeAction`, `SetEmployeeStatusAction`, `SaveEmployeeAssignmentAction`, `EndEmployeeAssignmentAction`, `SaveEmployeePublicProfileAction`, `RecordPhotoConsentAction`, `RevokePhotoConsentAction` |
| `Institucional` | `SaveProfileSectionAction`, `PublishProfileSectionsAction`, `SaveHourExceptionAction`, `DeleteHourExceptionAction` |
| `Midia` | `UploadMediaAction`, `UpdateMediaAction`, `DeleteMediaAction`, `GenerateMediaVariantsAction` (chamada **síncrona** por `UploadMediaAction`) |
| `Sites` | `CreateSiteAction`, `SaveSiteAction`, `SavePageAction`, `SavePageRevisionAction`, `PublishPageAction`, `PublishSiteSettingsAction`, `ActivateSiteAction`, `UnpublishSiteAction`, `RevertPageRevisionAction`, `RenamePageSlugAction` |
| `Dominios` | `RequestDomainAction`, `VerifyDomainAction` (síncrona sob demanda; job periódico a partir da S14), `ActivateDomainAction`, `RevokeDomainAction` |
| `Diretorio` | `SaveDirectoryListingAction`, `PublishDirectoryListingAction`, `UnpublishDirectoryListingAction`, `SyncDirectoryEntryAction`, `ConfigureDirectoryHostAction` |

Cada Action recebe o contexto e a Data, abre `AuditLog::transaction()` quando houver mais de uma escrita, monta o DTO numa variável e entrega ao Model (`register` e `revise`), sem funções privadas.

### 17.2 Rotas administrativas (padrão)

Verbos e formas seguem o padrão existente: telas retornam HTML no acesso direto e JSON com `Accept: application/json`; escritas usam sessão e CSRF; validação responde 422 com mensagens por campo.

| Recurso | Método e rota (rascunho) | Observação |
| --- | --- | --- |
| Colaborador | `POST /empresa/{empresa}/colaboradores`, `PATCH .../{id}` | Busca por documento reutiliza a de Clientes |
| Lotação | `POST .../colaboradores/{id}/lotacoes`, `PATCH .../lotacoes/{id}/encerrar` | Encerrar e trocar em uma operação |
| Perfil público | `PUT .../colaboradores/{id}/perfil-publico`, `POST .../perfil-publico/publicar`, `POST .../perfil-publico/despublicar` | Prévia do que será exibido. Permissões `edit-profile` e `publish-profile` (DA2-20) |
| Consentimento | `POST .../consentimento`, `DELETE .../consentimento` | Registro e revogação |
| Seção institucional | `PUT .../institucional/secoes/{secao}` | Corpo `{ mode, value }` (seção 09.2) |
| Exceção de horário | `POST`, `PATCH`, `DELETE .../institucional/horarios/excecoes` | |
| Mídia | `POST .../midia` (multipart), `PATCH`, `DELETE` | Resposta sem caminho privado |
| Site | `POST .../sites`, `PATCH .../sites/{id}` | |
| Página e revisão | `POST .../sites/{id}/paginas`, `PUT .../paginas/{pagina}/rascunho` | |
| Publicação | `POST .../paginas/{pagina}/publicar`, `POST .../sites/{id}/despublicar`, `POST .../paginas/{pagina}/reverter/{revisao}` | Permissões distintas |
| Prévia | `GET .../paginas/{pagina}/previa` | Autenticada, `noindex`, sem cache |
| Domínio | `POST .../sites/{id}/dominios`, `POST .../dominios/{id}/verificar`, `DELETE` | |
| Publicar seções | `POST .../institucional/publicar` | Corpo com as seções e confirmação do resumo de impacto |
| Ficha do diretório | `PUT .../diretorio/ficha`, `POST .../diretorio/ficha/publicar`, `POST .../diretorio/ficha/despublicar` | Permissões `update`, `publish` e `unpublish` |
| Host do diretório | `PUT /contas/{conta}/diretorio` | `account.directory.configure`, nó tenant, sem empresa nem estabelecimento fictícios |

### 17.3 Rotas públicas (somente GET e HEAD)

`routes/public.php`, sem sessão, cookie e CSRF, com `throttle` próprio. As rotas se dividem por sujeito do host (DA2-19).

| Rota | Resposta |
| --- | --- |
| `GET /` e `GET /{slug}` no host de **site** | HTML renderizado da revisão publicada |
| `GET /sitemap.xml` e `GET /robots.txt` | Por host |
| `GET /api/publico/equipe`, `/unidades`, `/horarios` | JSON de Resources com campos permitidos, consumido pelos blocos dinâmicos |
| `GET /`, `GET /busca?q=` e `GET /{slug}` no host do **diretório** | Lista agrupada, resultados e ficha, pela projeção. `robots.txt` e `sitemap.xml` por host também existem aqui |

Nenhuma rota pública aceita `account_id`, `company_id` ou `site_id` do cliente. Os endpoints `/api/publico/*` só existem em host de site e leem o `PublicContext`.

### 17.4 Versionamento do contrato público

Os Resources públicos carregam `schema_version`. Mudança que remove ou renomeia chave é versão nova; adicionar chave é compatível. Os blocos têm `schema_version` própria, e um conversor explícito migra revisões antigas ao ler, sem reescrever revisões publicadas.

---

## 18. Migrations propostas

**Nenhuma é criada neste plano.** As migrations seguem a convenção do repositório (`AAAA_MM_DD_NNNNNN_descricao.php`, comentário de cabeçalho com a etapa e a classe de reversibilidade, `down()` testada). Todas são **aditivas** (expandir), sem alterar tabelas existentes, exceto onde indicado. Nenhuma depende de `DEFERRABLE`; constraints exclusivos do PostgreSQL são evitados ou duplicados por regra de domínio e `institutional:check`.

| Etapa | Migration | Conteúdo | Classe |
| --- | --- | --- | --- |
| S03 | `create_departments_and_job_titles` | Duas tabelas, FKs compostas, único por empresa | R |
| S04 | `create_employees` | `employees`; etiqueta especial `employee` criada nas contas existentes (idempotente por `system_key`, como as de Cliente e Fornecedor) e, nas contas novas, por `Tag::employeeTag` no provisionamento. Sem linhas de colaborador | R, com a `down()` removendo as etiquetas criadas se não houver vínculo |
| S05 | `create_employee_assignments` | Tabela com CHECK de período e de modo de contato, índice parcial da principal sem fim, FKs compostas | R |
| S06 | `create_profile_sections` e `create_public_categories`; coluna aditiva `show_public_address` em `establishments` | `profile_sections` com par de trabalho e par publicado, CHECKs de modo e valor nos dois pares e modo `override` obrigatório no nível da empresa; únicos parciais por dono e seção (seção 06.1); `public_categories` por conta (PD2-12) | R |
| S07 | `create_hour_exceptions` e `add_timezone_to_establishments` | Tabela (com `applies_to_overrides` só para dono empresa) e coluna `timezone` IANA com padrão `America/Sao_Paulo` (PD2-08), aditiva em tabela existente | R |
| S08 | `create_media_assets` | `media_assets`, `media_variants` | R |
| S09 | `create_employee_public_profiles` | Tabela | R |
| S10 | `create_sites_and_pages` | `sites` (com `draft_settings` e `published_settings`), `site_pages`, `site_page_revisions`, `site_slug_redirects`; alvo `(id, company_id)` para as FKs compostas | R |
| S11 | `create_public_hosts` | `public_hosts` com `subject` e CHECKs; só `subject = site` e `kind = managed` | R |
| S13 | `create_directory_listings`, `create_directory_entries` e `create_directory_listing_categories` | Ficha (autoridade), projeção e pivô, com índices **somente se medidos**; habilita `subject = directory` em `public_hosts` por regra de domínio, sem alterar o esquema | R |
| S14 | nenhuma migration de esquema prevista | `public_hosts` já tem os campos (S11). Só entra migration se a S14 provar uma falta, com índice medido | R |

**Dados existentes.** Nenhum backfill de dado de negócio é necessário: não há colaboradores, sites ou perfis a migrar. A única carga é a etiqueta `employee` por conta (S04) e o catálogo de permissões (`permissions:sync`). Contas existentes não ganham site, perfil público nem entrada no diretório até alguém ativar. **Publicação padrão: desabilitada.**

**Importação de base legada** (colaboradores de planilha ou de outro sistema) não faz parte do ciclo. Se for pedida, será uma etapa própria com dry-run, relatório, idempotência por documento e a busca de Pessoa existente, sem criar duplicatas.

**Reversão.** Todas as tabelas novas são removíveis enquanto estiverem vazias. Depois do primeiro uso real, a reversão é por backup ou correção para frente, como no plano 001 (seção 23.1). A S10 em diante usa **chave de configuração** para liberar a superfície pública, de modo que a estrutura possa ser implantada antes do uso.

**PostgreSQL.** As migrations são exercidas em PostgreSQL isolado, fora da suíte, em cada etapa que cria índice parcial ou CHECK (mesmo procedimento de `tests/Performance/e*-check.sh`).

---

## 19. Plano incremental

Etapas pequenas, em ordem de dependência. Cada uma entrega algo completo e verificável, com ajuda, tour e auditoria quando houver tela ou operação de negócio. Uma etapa só começa com autorização explícita. A estratégia de commits é a do plano 001: commits locais por etapa, sem push, merge ou deploy sem pedido.

Pré-requisito geral: o plano 001 homologado (E01 a E10) e o branch de partida com a suíte e `audit:check` verdes.

A **homologação funcional com usuários** (administrador, gestor de unidade, editor e visitante) é uma atividade posterior e separada, fora das etapas S01 a S15, salvo decisão explícita do responsável. O fechamento técnico (S15) não a substitui.

### S01: Verificação das hipóteses e fechamento das decisões

- **Objetivo.** Resolver as hipóteses H1 a H7 e registrar o que depende do responsável, antes de qualquer código.
- **Escopo.** Somente documentação e medições descartáveis, sem alterar o app, o banco da aplicação ou a infraestrutura.
- **Contratos afetados.** Nenhum.
- **Implementação prevista.** Executada em 24/11/2026 (seção 26): GD, PostgreSQL descartável, fila, cache, Caddy, mapa das etiquetas especiais e componentes do editor.
- **Evidências.** Seção 26, com comandos, ambientes e resultados.
- **Dependências.** Nenhuma.
- **Encerramento.** Hipóteses verificadas ou com impedimento concreto registrado (H4 depende do site público; H5 depende de informação de infraestrutura). **Concluída em 24/11/2026**, com as pendências de decisão da seção 23.

### S02: Contrato institucional

- **Objetivo.** Transformar as seções 07 a 14 em convenção normativa curta, como a E02 fez para a organização.
- **Escopo.** Documentação e emendas por adição ao AGENTS.md e às convenções. Nenhum código, migration, rota, tela, permissão real ou infraestrutura.
- **Contratos afetados.** `AGENTS.md`, `pessoas-e-contatos.md`, `contexto-organizacional.md`, `permissoes.md`, `auditoria.md`, `performance.md`, `checklist-modulos.md` e `docs/convencoes/institucional.md` (novo).
- **Implementação.** **Executada em 24/11/2026 (seção 27).** Convenção normativa criada, emendas aplicadas, contradições corrigidas e dependências de fila e agendador reposicionadas (seção 19.1).
- **Evidências.** Seção 27: conflitos detectados e tratamento, confronto com cada convenção e com o plano 001, condições de início da S03 e da S04.
- **Dependências.** S01.
- **Encerramento.** **Concluída em 24/11/2026** segundo os critérios da seção 27.5. As decisões DA2-19 a DA2-23 aguardam ratificação, sem bloquear a S03 e a S04.

### S03: Departamentos e funções

- **Objetivo.** Entregar o cadastro de organização interna da empresa.
- **Escopo.** `departments` e `job_titles`, com telas de lista e modal, ativar e inativar, sem excluir. A interface chama a segunda de **Função** (menu "Funções", PD2-01) e nunca de Cargo. **Síncrona, sem fila.**
- **Contratos afetados.** `config/permissions.php` (módulos e alvos), `config/audit.php`, sidebar.
- **Implementação prevista.** Domínio `Colaboradores` (parte inicial), Models `Auditable`, Actions, Data, Queries com tenant e empresa, `BusinessList` e modal, ajuda, tour e `permissions:sync`.
- **Evidências.** Testes de escopo (empresa A não vê B, 404), permissões separadas por ação, auditoria, `audit:check`, `organization:check`, verificação visual desktop e mobile.
- **Dependências.** S02. Nenhuma decisão pendente (PD2-01 aprovada).
- **Encerramento.** Critérios da seção 20 para a etapa e `./vendor/bin/pint --test` e `phpstan` verdes.

### S04: Colaborador como papel de Pessoa

- **Objetivo.** Cadastrar colaboradores por empresa, reutilizando a Pessoa, com etiqueta derivada e **sem lotação ainda**.
- **Escopo.** `employees`, etiqueta especial `employee`, busca por documento completo, ficha administrativa (dados centrais, relação, contatos profissionais padrão, login somente leitura) e listagem **por empresa**. Filtros: situação, presença de login e busca por nome ou documento. **Fora de escopo:** filtro por estabelecimento, departamento ou função, coluna Unidades, consolidado `todos` e acesso de gestor de unidade, que não têm autoridade antes das lotações e entram na S05.
- **Contratos afetados.** Pontos mapeados na seção 26 (H6): `Tag` (fábrica `employeeTag`), `RegisterAccountMembershipAction`, derivação de etiquetas (`PeopleDirectoryQuery`, `PersonCommercialRelationsQuery`), `SaveTagData`, telas e filtros de Etiquetas, `Person::employees()`. Permissões `account.employees.*` com alvo `company`. Auditoria.
- **Implementação prevista.** `Employee`, `EmployeeAuthority`, `EmployeeDirectoryQuery`, `RegisterEmployeeAction`, `SaveEmployeeAction`, reuso de `PersonDocumentExistsQuery`. A derivação passa a ler um registro único de papéis derivados, sem segunda trilha e sem ampliar `CommercialRole`. Sem estrela de etiqueta especial fora da listagem de Etiquetas. O indicador e o filtro "Possui acesso à conta" só aparecem para quem tem `account.users.list` em algum escopo. **Síncrona, sem fila.**
- **Evidências.** Não duplica CPF; pessoa cliente e colaborador; colaborador sem usuário e usuário sem colaborador; resposta neutra para documento de outra conta; regressão de Clientes, Fornecedores e Etiquetas; a etiqueta Colaborador não aparece para quem não visualiza colaboradores da empresa; leitor sem `account.users.list` não vê o indicador de acesso; `CommercialRole` e as rotas de etiqueta convencional intactos; plano da consulta medido.
- **Dependências.** S02. Não depende da S03. Nenhuma decisão pendente.
- **Encerramento.** Cenários 8 e 9 da seção 20.

### S05: Lotações e histórico

- **Objetivo.** Lotar o colaborador em um ou mais estabelecimentos, com função, departamento, contatos profissionais da unidade e vigência.
- **Escopo.** `employee_assignments` com a semântica da seção 10.3.1, troca de função por período, encerramento por inativação, coluna Unidades, filtros por unidade, departamento e função, consolidado `todos` e acesso de gestor de unidade (DA2-18). Fuso de negócio padrão (`America/Sao_Paulo`) em configuração, até a S07 criar o fuso por estabelecimento (PD2-08). **Síncrona: encerrar por vigência é cálculo, sem job.**
- **Contratos afetados.** `config/permissions.php` (alvo `establishment` para `list`, `view` e `assign`), Query de listagem, auditoria com `operation_id`, `institutional:check` (primeira versão).
- **Implementação prevista.** `EmployeeAssignment`, `SaveEmployeeAssignmentAction`, `EndEmployeeAssignmentAction`, trava do colaborador, `institutional:check` (sobreposição e principal), ajuda e tour.
- **Evidências.** Testes da seção 10.3.1: períodos adjacentes, troca no mesmo dia (correção), sobreposição, retroativo, futuro, encerramento da principal com e sem outra lotação, inativação com lotação futura; gestor da unidade A não vê só-B; plano da listagem sem N+1; `down()` testada; concorrência de duas lotações em PostgreSQL isolado.
- **Dependências.** S03 e S04. Nenhuma decisão pendente.
- **Encerramento.** Cenário 11 e a parte privada dos cenários 10 e 12.

### S06: Perfil institucional e herança

- **Objetivo.** Entregar o resolvedor, as seções de identidade, sobre, contatos, redes, banners e SEO, com herança, rascunho e publicação (seção 09.7).
- **Escopo.** `profile_sections` com valor de trabalho e valor publicado, `ProfileResolver` com estado, telas de empresa e de estabelecimento com origem e "Voltar a herdar", `PublishProfileSectionsAction` com resumo de impacto, categorias públicas em tabela própria (`public_categories`, PD2-12), opção "Exibir endereço" em `establishments`. **Síncrona, sem fila.**
- **Contratos afetados.** `config/permissions.php` (inclui `account.institutional.publish`), convenção institucional, `establishments` (opção de exibir endereço, desligada por padrão).
- **Implementação prevista.** Classes de seção com schema, classe (editorial ou operacional) e padrão, Data de validação, regra de contraste, Query única de leitura, teste estático impedindo leitura fora do resolvedor.
- **Evidências.** Testes da seção 09.6 nos estados `draft` e `published`; editar rascunho não altera o público; publicar não toca unidades com `override` e atinge as que herdam; seção operacional vale ao salvar; contato pessoal não herdado; endereço doméstico não exposto.
- **Dependências.** S02. Pode ocorrer em paralelo às S04 e S05, sem tocar as mesmas tabelas. Nenhuma decisão pendente.
- **Encerramento.** Cenário 7 e a parte institucional dos cenários 6 e 16.

### S07: Horários e exceções

- **Objetivo.** Horários semanais com turnos, fuso e exceções por data, com herança.
- **Escopo.** Seção `hours_weekly`, `hour_exceptions` (com `applies_to_overrides`, PD2-08), coluna `establishments.timezone` IANA com padrão `America/Sao_Paulo` e cálculo de "aberto agora", que **nunca é gravado em HTML em cache** (seção 09.5). **Síncrona.**
- **Contratos afetados.** `establishments` (coluna aditiva), resolvedor.
- **Implementação prevista.** Value objects de turno, resolução por data e fuso, telas, ajuda e tour.
- **Evidências.** Testes por fuso e por mudança de horário de verão, exceção da unidade A sem afetar B, feriado corporativo, fechamento temporário, exceção corporativa que não alcança unidade com `override` e que a alcança com `applies_to_overrides`, migration do fuso sem mudar nenhuma vigência.
- **Dependências.** S06 e S05 (o fuso passa a ser lido pelo cálculo de "hoje" da lotação). Nenhuma decisão pendente.
- **Encerramento.** Parte de horário dos cenários 6 e 15.

### S08: Biblioteca de mídia

- **Objetivo.** Upload seguro, derivados, texto alternativo e reuso por referência.
- **Escopo.** `media_assets`, `media_variants`, regras de upload, retenção e exclusão conservadora por evento. Sem uso público ainda. **Execução síncrona como proposta**, sujeita ao orçamento e aos critérios de segurança da seção 13.1 (barreiras antes de decodificar, estimativa de memória, duração, atomicidade, concorrência). Se a medição reprovar, a própria S08 incorpora fila e worker (DA2-21). Sem agendador.
- **Contratos afetados.** Disco privado e público, GD existente (PD2-05), `config/filesystems.php`. **Não** altera `Dockerfile` sem prova (PD2-13).
- **Implementação prevista.** Validação por conteúdo, reprocessamento sem metadados, limites, nomes opacos, Query com escopo, interface de armazenamento isolada.
- **Evidências.** Testes de arquivo disfarçado, SVG recusado, EXIF removido, limite de tamanho e de megapixels, isolamento de mídia entre tenants, medição de memória e tempo **na requisição** com `memory_limit` real. **Prova de PD2-13:** fotos nas orientações EXIF 1 a 8, comparando rotação, remoção de metadados e segurança, com a conclusão registrada (aceitar sem rotação, leitura mínima de orientação em PHP ou `exif`, esta última com aprovação para alterar o `Dockerfile`). Se o orçamento medido não couber na requisição, a S08 **incorpora o fluxo assíncrono e o laboratório de worker no próprio escopo** e só encerra com ele validado (seção 19.1). Evidências adicionais: arquivo acima do orçamento recusado sem erro fatal, 48 MP recusado, envio abortado por tempo sem resíduos, envios paralelos sem crescimento de memória.
- **Dependências.** S01 (H1 e H3), S02. PD2-05 em sua parte de **armazenamento e CDN** (o disco local atende o laboratório; produção depende do responsável). Worker apenas se a medição o exigir, e então como parte da própria S08.
- **Encerramento.** Cenário 21 (mídia de outro tenant) e testes de upload.

### S09: Perfil público do colaborador

- **Objetivo.** Configurar a exposição pública da equipe com consentimento.
- **Escopo.** `employee_public_profiles`, foto profissional via biblioteca, contatos lidos da lotação, prévia do que será exibido, registro e revogação de consentimento, elegibilidade, permissões `edit-profile`, `publish-profile` e `consent` (DA2-20), mais a permissão `select-team` e `SaveUnitTeamSelectionAction` sobre a seção `team_display` (PD2-16), com os testes de escopo da seção 20.5 (item 2a). **Síncrona**, inclusive a revogação.
- **Contratos afetados.** Mídia, auditoria (eventos explícitos), LGPD.
- **Implementação prevista.** `EmployeePublicProfile`, `PublicEmployeeResource` com allowlist, `EmployeeEligibilityQuery`, Actions de consentimento.
- **Evidências.** Teste de payload sem campos proibidos, revogação some a foto e os derivados, colaborador inativo ou sem lotação inelegível, contato pessoal nunca copiado.
- **Dependências.** S05, S08, PD2-07 (texto e base legal do consentimento). PD2-16 está aprovada.
- **Encerramento.** Cenários 10 e 12 completos e a parte de elegibilidade do cenário 13 (a exibição e o cache fecham na S11).

### S10: Sites, páginas, blocos e revisões (somente retaguarda)

- **Objetivo.** Criar o modelo editorial e o editor, ainda sem superfície pública.
- **Escopo.** `sites`, `site_pages`, `site_page_revisions`, `site_slug_redirects`, catálogo de blocos, rascunho, revisões, reversão, prévia **somente autenticada** (PD2-09), formatação restrita de texto (PD2-15), sanitização. **Síncrona, sem fila.**
- **Contratos afetados.** Permissões, auditoria, frontend (editor), sanitizador compartilhável com a Ajuda.
- **Implementação prevista.** `BlockType` e classes por bloco, renderizador único usado pela prévia e pelo público, editor com os componentes Bold, tour da tela (sem instanciar `GuidedTour` em modal).
- **Evidências.** Testes de bloco inválido, referência fora de escopo, XSS por bloco e por texto, imutabilidade de revisão publicada, reversão cria nova revisão, editor de A não acessa B, verificação visual do editor em desktop e mobile.
- **Dependências.** S06, **S07** (o bloco `hours` e a prévia leem horários, que a revisão 2 omitia), S08, S09, H7 verificada e PD2-15 aprovada.
- **Encerramento.** Parte administrativa do cenário 17 (negar sem alterar nada, seção 12.1) e os testes de sanitização.

### S11: Publicação em subdomínio gerenciado

- **Objetivo.** Primeira superfície pública, em domínio gerenciado.
- **Escopo.** `public_hosts` (`subject = site`, gerenciados), `routes/public.php`, `PublicContext`, renderização, cache por versão com `valid_until`, endpoint de horários com TTL curto, SEO básico, `sitemap.xml`, `robots.txt`, `noindex`, 301 de slug, CSP, rate limit, chave de configuração para liberar. **Síncrona: sem job de limpeza de cache e sem agendador** (DA2-14 e DA2-21).
- **Contratos afetados.** `docker/Caddyfile`, infraestrutura de DNS e TLS do domínio gerenciado (PD2-02), cache (store decidido pela medição, PD2-10). Não altera Actions do plano 001: a retratação por inativação é conferida na leitura.
- **Implementação prevista.** Middleware de resolução, publicação e despublicação, `content_version`, invalidadores, conferência de `active` e vigência antes de servir, `SyncDirectoryEntryAction` ainda inativo.
- **Evidências.** 404 neutro (inclusive despublicado, nunca 410), site em rascunho não indexa, prévia autenticada, isolamento de rascunho entre tenants, página com equipe que muda de vigência à meia-noite sem evento, "aberto agora" ausente do HTML em cache, unidade inativada sai do ar com cache quente, invalidação por revogação de foto, medição com worker FrankenPHP real de latência, queries por página e memória, sem vazamento de `PublicSiteContext` entre requisições.
- **Dependências.** S10, S06 (seções publicadas), S07 (horários), S08 e S09 (derivados e equipe públicos, por transitividade da S10), **PD2-02 (bloqueante, depende do responsável)**. PD2-10 só se a medição reprovar o cache de banco.
- **Encerramento.** Cenários 1, 13, 17, 18, 19 e 21 (parte pública).

### S12: Site de unidade, modo híbrido e localizador

- **Objetivo.** Páginas de unidade em site corporativo, site próprio de estabelecimento e modo híbrido sem duplicar dados.
- **Escopo.** `site_pages.kind = unit`, `units_locator`, `LocalBusiness`, regras de conteúdo mínimo e `noindex`, equipe e horário por unidade.
- **Contratos afetados.** Resolvedor (site herda unidade e empresa), permissões por nó de unidade.
- **Implementação prevista.** Bloco de unidade, criação guiada, validação de NAP coerente com a ficha, `canonical`.
- **Evidências.** Cenários 2, 3 (sem domínio próprio), 6, 15, 16 e 19 da seção 20; filial muda endereço sem alterar outras; unidade sem conteúdo mínimo permanece `noindex`.
- **Dependências.** S11, S07, S09 (e S05 por ela). Sem decisão pendente. **Síncrona.** Exercita na unidade a seleção de equipe (`select-team`, PD2-16) e repete os testes de escopo do item 2a da seção 20.5 nas páginas e sites de unidade.
- **Encerramento.** Cenários 2, 3 (modo gerenciado), 6, 15, 16 e 19.

### S13: Diretório e busca

- **Objetivo.** Lista agrupada por empresa, com busca, deep link para unidade e ficha pública sem site (modo A).
- **Escopo.** `directory_listings` com publicar e despublicar, `directory_entries` e `SyncDirectoryEntryAction`, **host público do diretório da conta** (`public_hosts`, `subject = directory`, `account.directory.configure`, DA2-19), busca, ficha de empresa e de unidade, categorias e tags, sitemap do diretório (DA2-17 e DA2-23). **Síncrona:** a reconstrução de uma empresa com muitas unidades é medida; se não couber na requisição, a S13 incorpora o fluxo assíncrono e o laboratório de worker no próprio escopo (seção 19.1).
- **Contratos afetados.** Publicação de seções e de sites (chamam a sincronização), `SaveCompanyAction` e `SaveEstablishmentAction` do plano 001 (uma chamada aditiva de sincronização na inativação), PostgreSQL (extensões só se medidas), PD2-04.
- **Implementação prevista.** Query pública única sobre a projeção, normalização em PHP, índices somente com plano medido, tela da ficha e de visibilidade.
- **Evidências.** Simulação da seção 14.4; ficha publicada e despublicada **sem site**; inativar empresa ou unidade remove a entrada; ficha de unidade invisível com a empresa despublicada; empresas homônimas distintas não se fundem; regressão das Actions do plano 001; ficha de estabelecimento sem endereço público não publica; empresa sem unidade com endereço público é achada por nome e não por cidade; host do diretório de uma conta não serve ficha de outra; medição em PostgreSQL isolado com volume.
- **Dependências.** S06 (seções publicadas e categorias), S11 (infraestrutura pública: hosts, contexto e cache) e S12 (somente para o deep link de unidade com site; o modo A e a busca não dependem dela). PD2-04 aprovada. PD2-02 para o host gerenciado do diretório.
- **Encerramento.** Cenários 4 e 5.

### S14: Domínios próprios

- **Objetivo.** Permitir domínio próprio com prova de posse, TLS e revogação.
- **Escopo.** Fluxo de verificação DNS por TXT, estados do domínio, TLS sob demanda com `ask`, revalidação, revogação e desvinculação.
- **Contratos afetados.** `docker/Caddyfile`, configuração de DNS, `public_hosts`, permissões `account.domains.*`.
- **Implementação prevista.** Validação de host contra SSRF, verificação sob demanda (síncrona, tempo limite curto) e **job de verificação e revalidação agendado** com timeout, endpoint `ask` autenticado por rede interna, redirecionamento canônico apex e `www`, suspensão por divergência de DNS. **Primeira etapa que exige worker e agendador** (seção 19.1).
- **Evidências.** Domínio não verificado não ativa, host de outro tenant recusado de forma neutra, host `localhost` e IP rejeitados, `ask` recusa host desconhecido, revogação para de servir, ensaio com domínio real de teste.
- **Dependências.** S11 (técnica). Ordem após a S13 por decisão do plano. H5, PD2-02, **PD2-14 (aprovação dos serviços `queue` e `schedule` no laboratório, com os critérios da seção 19.1)** e PD2-11 (limites de domínios).
- **Encerramento.** Cenários 3 (com domínio próprio) e 20.

### S15: Fechamento técnico integrado

- **Objetivo.** Provar o conjunto em laboratório multiempresa e multiestabelecimento e fechar tecnicamente o plano.
- **Escopo.** Os cenários da seção 20 (exceto os registrados como fora do ciclo), regressão do plano 001, segurança, cache, performance, falhas simuladas, publicação e despublicação, fotos de teste autorizadas e seeder de laboratório. **Fora do escopo:** homologação funcional com usuários, que é atividade posterior e separada.
- **Contratos afetados.** Todos.
- **Implementação prevista.** Relatório de fechamento técnico em `docs/plano-de-acoes/`, evidências em `docs/evidencias/`, atualização das convenções e do quadro.
- **Evidências.** Suíte completa, `audit:check`, `permissions:sync`, `organization:check`, `institutional:check`, medições em PostgreSQL isolado e com worker FrankenPHP real.
- **Dependências.** S01 a S14.
- **Encerramento.** Plano marcado **concluído tecnicamente**, separado da homologação funcional e da autorização de implantação.

### Reservada: catálogo público de produtos e serviços

Não é etapa do ciclo. Entra em plano próprio quando o módulo de Produtos existir (seção 15).

### 19.1 Execução síncrona, assíncrona e periódica (DA2-21)

**Fato [C] (H3).** O Compose tem `app`, `postgres` e `mailpit`. Não há worker de fila nem agendador, e o código não despacha nenhum job. Fila, cache e sessão estão em banco (`QUEUE_CONNECTION=database`, tabela `jobs` sem consumidor).

**Conceitos.** *Síncrono* roda na requisição, dentro da transação e do `AuditLog::transaction()`. *Assíncrono* é um job na fila, que exige worker. *Periódico* é uma tarefa disparada por agendador, que exige o processo do agendador e, se despacha jobs, também o worker.

| Etapa | Síncrono | Assíncrono (fila) | Periódico (agendador) | Observação |
| --- | --- | --- | --- | --- |
| S03 a S07 | tudo | nada | nada | Vigência que expira é cálculo na leitura, não tarefa |
| S08 | envio, validação, derivados GD, remoção de derivados e de órfãos por evento | nada. **Gatilho** abaixo | nada. Limpeza de resíduos por comando manual `media:purge` | A revisão 2 previa variantes "na fila"; contradizia a S01 (25 ms, 30 MB com 5 MB e 20 MP) |
| S09 | consentimento, revogação, remoção de derivados | nada | nada | Revogação nunca espera worker |
| S10 | salvar, prévia, reverter | nada | nada | |
| S11 | publicar, despublicar, `content_version`, conferência de `active` e vigência, sitemap sob demanda com cache | nada. **O job de limpeza de cache foi removido** | nada. O TTL até meia-noite é calculado na resposta | A revisão 2 previa job de limpeza; a troca de versão já invalida (DA2-14) |
| S12 | tudo | nada | nada | |
| S13 | `SyncDirectoryEntryAction`, publicar e despublicar ficha | nada. **Gatilho** abaixo | nada | Reconstrução limitada ao nó de origem e às unidades dele |
| **S14** | verificação sob demanda ("Verificar agora", tempo limite curto), `ask`, revogação | **verificação de DNS por domínio, com timeout e tentativas** | **reteste de domínios `pending` e revalidação dos `active`** | **Primeira etapa que exige worker e agendador** |
| S15 | tudo o que existir | idem S14 | idem S14 | Medições com os serviços do laboratório |

**Conclusão.** Antes da S14 nenhuma etapa precisa de worker nem de agendador **por contrato**, salvo se a medição da S08 ou da S13 reprovar o síncrono (então a própria etapa o incorpora). A S14 precisa dos dois: o reteste de DNS é periódico porque a propagação leva minutos ou horas, e cada verificação é um job porque a consulta de DNS tem latência imprevisível e não deve prender a requisição nem o agendador.

**Antecipação (S08 e S13).** As metas numéricas são fixadas depois da linha de base medida ([Performance](../convencoes/performance.md)). Se a medição da etapa mostrar que a operação síncrona **não cabe** no orçamento (S08: critérios 1 a 5 da seção 13.1; S13: reconstrução de uma empresa com muitas unidades), **a etapa incorpora worker (e agendador, se houver tarefa periódica) no seu próprio escopo**, com os critérios abaixo, e entrega o fluxo assíncrono completo. A alteração do Compose segue a autorização da própria etapa; ela não é adiada para a S14. Enquanto o worker não estiver validado em laboratório, o fluxo não é liberado, e a recusa limpa dos arquivos fora do orçamento continua valendo.

**Critérios para implantar worker e agendador em laboratório (condição para a S14, ou para a antecipação).** Nada disto é feito na S02.

1. **Autorização** do responsável para alterar o Compose (PD2-14), dada com a autorização da etapa que o exige (S14, ou S08 ou S13 se a medição o impuser).
2. **Serviços separados** `queue` e `schedule`, com a mesma imagem e sem Octane: `php artisan queue:work` com `--tries`, `--timeout` menor que o tempo de retenção e `--max-time` para reciclar o processo, e `php artisan schedule:work` (ou equivalente) em **uma única instância**.
3. **Reinício na implantação** (`queue:restart`), para que o worker não rode código antigo.
4. **Contexto** em todo job: `account_id`, `company_id`, `establishment_id`, `membership_id` e `actor_id`, com **reautorização** ao executar. Tarefa do agendador usa autoria de sistema.
5. **Idempotência** e trava por domínio: dois workers não processam o mesmo domínio ao mesmo tempo, e repetir o job não duplica evento de auditoria.
6. **Falhas visíveis:** `failed_jobs` e log só com identificadores. Falha de job nunca desfaz uma publicação já concluída.
7. **Medição em laboratório:** memória estável em mil jobs, latência de DNS com o resolvedor configurado, contenção da tabela `jobs` em PostgreSQL isolado, `audit:check` verde. Redis não entra sem medição (PD2-10, aprovada).
8. **Teste de contrato:** job de uma conta não lê dados de outra, e job com vínculo suspenso é recusado.

---

## 20. Testes

Os testes usam SQLite em memória (`tests/bootstrap.php`) e nunca o banco da aplicação. Concorrência, planos e medição de performance ficam fora da suíte, em PostgreSQL isolado, no padrão de `tests/Performance`.

### 20.1 Catálogo mínimo por categoria

| Categoria | Casos |
| --- | --- |
| Positivos | Cadastrar colaborador de pessoa existente, lotar em duas unidades, publicar site corporativo e de unidade, herdar e sobrescrever seção, publicar e reverter revisão, ativar domínio verificado |
| Isolamento | Tenant A lê rascunho, mídia, colaborador, seção ou site do tenant B: 404. Empresa A acessa recurso de B. Estabelecimento A edita B. ID de outro escopo em todas as Queries novas |
| Autorização | Cada ação exige a permissão própria. Editar sem publicar. Atribuição de estabelecimento não alcança o site corporativo. Antiescalada ao conceder permissões. Inspeção bloqueia escritas |
| Privacidade | Payload público varrido: nenhuma chave proibida. Contato pessoal não vira público. Endereço doméstico não publicado. Login não exposto |
| Herança | Seção 09.6 completa |
| Concorrência (fora da suíte) | Duas lotações sobrepostas em paralelo, duas publicações simultâneas da mesma página, dois pedidos do mesmo slug |
| Segurança | XSS em blocos e textos, upload disfarçado, SVG, SSRF em domínio, host desconhecido, enumeração por mensagem |
| Cache | Cada invalidador renderiza antes e depois. Revogação de foto remove derivado |
| Regressão do plano 001 | Contexto por URL, RBAC, Clientes, Fornecedores, Auditoria, Cargos, Pessoas e a landing da raiz permanecem inalterados |
| Auditoria | Eventos com `company_id` e `establishment_id` corretos, sem evento em atualização sem mudança, reversão sem histórico concluído, sem imagens ou tokens, `audit:check` |

### 20.2 Os 22 cenários do briefing

Cada cenário abaixo é um teste de aceite da etapa indicada.

| # | Cenário | Etapa |
| --- | --- | --- |
| 1 | Uma conta, uma empresa, um estabelecimento: configuração mínima e site simples | S11 |
| 2 | Empresa com matriz e três filiais: site corporativo e páginas locais | S12 |
| 3 | Filial com site próprio e domínio personalizado, mantendo o corporativo | S12 (gerenciado), S14 (próprio) |
| 4 | Empresa sem site, só ficha no diretório, no host do diretório da conta | S13 |
| 5 | Duas marcas e PJs diferentes: não presumir agrupamento | S13 |
| 6 | Filial muda endereço e horário sem alterar outras unidades | S07, S12 |
| 7 | Mudança de cor padrão com override de uma filial | S06 |
| 8 | Pessoa é cliente e colaborador, sem duplicar CPF | S04 |
| 9 | Colaborador sem usuário e usuário sem colaborador | S04 |
| 10 | Colaborador em mais de uma unidade, com funções e contatos públicos diferentes | S05, S09 |
| 11 | Departamento global e local, filtro sem duplicar Pessoa | S05 |
| 12 | Funcionário deixa a empresa: publicação cessa, histórico privado permanece | S05, S09 |
| 13 | Consentimento de foto revogado: site e cache deixam de exibir | S09, S11 |
| 14 | Serviço global disponível só na filial B | Reservada (seção 15) |
| 15 | Loja A fecha temporariamente e B continua | S07, S12 |
| 16 | Endereço doméstico não é publicado por padrão | S06, S12 |
| 17 | Editor da filial A tenta editar B: negar (404 ou 403) sem alterar nada. **Não há evento de negócio para tentativa negada** (seção 12.1) | S10, S11 |
| 18 | Site em rascunho não indexa e a prévia é autenticada (PD2-09) | S11 |
| 19 | Renomear slug preserva redirecionamento e evita colisões | S11, S12 |
| 20 | Domínio personalizado não verificado não é ativado | S14 |
| 21 | Tenant A não lê rascunho nem mídia do tenant B | S08, S11 |
| 22 | Duplicidade e importação de base legada tratadas com idempotência | Fora do ciclo; só com a etapa de importação (seção 18) |

O cenário 14 depende do catálogo e o 22 de uma etapa de importação. **Ambos ficam explicitamente fora do encerramento deste plano** e registrados como pendências, para não os declarar cobertos sem estarem.

### 20.3 Cenários derivados da auditoria

1. Colaborador em empresas diferentes da mesma pessoa tem duas relações independentes e auditorias separadas.
2. Reativar colaborador não reabre lotações antigas.
3. Alterar `hours_weekly` da empresa propaga só a quem herda.
4. Exceção de horário corporativa não alcança unidade com override próprio, salvo declaração (PD2-08).
5. Revisão publicada é imutável: editar cria nova linha.
6. Referência de mídia removida não quebra a página: o bloco é ignorado e o `institutional:check` aponta.
7. `PublicContext` não vaza entre requisições no Octane (dois sites e um diretório em sequência no mesmo worker).
8. Host com maiúsculas, porta e IDN resolve de forma consistente.
9. Rotas públicas rejeitam POST e qualquer parâmetro de tenant vindo do cliente.
10. Despublicar remove o site do diretório e do sitemap.
11. Site de unidade cujo estabelecimento é inativado sai do ar.
12. Slug reservado e slug ocupado recebem a mesma mensagem neutra.
13. Excluir mídia em uso por revisão publicada é recusado.
14. Uma conta com um estabelecimento não vê seletor nem passo extra para criar o site.

### 20.4 Testes adicionais da revisão 2

1. Vigência: períodos adjacentes sem lacuna nem duplicidade; troca no mesmo dia do início vira correção; `starts_on = ends_on` válido; retroativo sobreposto recusado; lotação futura inerte até a data.
2. Principal: encerrar a principal com outra lotação vigente exige nova principal; sem outras, o colaborador fica sem principal.
3. Dados por unidade: colaborador em A e B com funções e contatos diferentes; o bloco da unidade A mostra A e o da B mostra B; o bloco geral mostra a principal.
4. Perfil público não guarda contato; nenhum valor de `people` aparece no payload; campos profissionais não são pré-preenchidos de Pessoa.
5. Rascunho: editar `draft_settings`, valor de trabalho de seção e revisão `draft` não altera o público.
6. Publicar seção da empresa: unidade em `inherit` recebe, unidade com `override` não, e o resumo de impacto bate com o resultado.
7. Seção operacional vale ao salvar e a tela avisa antes; retratação vale na hora.
8. Página com bloco de equipe expira na próxima 00:00 do fuso do estabelecimento: colaborador cuja lotação terminou ontem some no dia seguinte sem evento, e a resposta não ultrapassa `valid_until`.
9. Ficha do diretório: publicar e despublicar sem site; ficha de unidade invisível com a empresa despublicada; inativar empresa ou unidade remove a entrada; publicar site não cria ficha.
10. Permissão `list` com alvo `establishment` não alcança dados centrais nem outra unidade; `update` central exige alvo `company`.

### 20.5 Testes adicionais da revisão 3

1. **Função e Cargo:** nenhum texto, rótulo, rota ou permissão novos usam "cargo" para a atividade profissional; o módulo de permissões se chama "Funções".
2. **Perfil público:** `edit-profile` edita só enquanto não publicado; editar publicado exige `publish-profile`; despublicar e revogar valem na hora; o gestor de unidade não edita nem publica o perfil, não liga `show_*` e não registra consentimento.
2a. **Seleção por unidade (PD2-16, S09 e S12):** com `select-team` na unidade A, o gestor escolhe e ordena só colaboradores com lotação vigente em A e perfil publicado pela empresa; colaborador de B, sem lotação em A ou sem perfil publicado é recusado (422 ou 404); a seleção não torna público quem não é; `account.institutional.update` sozinho não edita `team_display`; a unidade exibe função e contato da **lotação** e só com `show_work_*` ligado pela empresa; nenhum payload da unidade contém `people.*`; atribuição de A não seleciona na empresa nem em B; a seleção é rascunho até `account.institutional.publish`.
3. **Indicador de acesso:** quem não tem `account.users.list` não vê "Possui acesso à conta" nem o filtro.
4. **Host do diretório:** a ficha de uma conta não responde no host de outra; host de site não serve rota de diretório; `hostname` único entre sites e diretórios; CHECK de `subject`.
5. **Exceção corporativa:** unidade com `override` só a recebe com `applies_to_overrides`; exceção da unidade sempre vale para ela.
6. **"Aberto agora"** não aparece no HTML em cache; o endpoint de horários respeita o TTL curto.
7. **Retratação por leitura:** inativar a unidade ou a empresa tira o site e a ficha do ar com cache quente, sem ter chamado a sincronização.
8. **Despublicação neutra:** página e site despublicados respondem o mesmo 404 de um site inexistente; despublicar a `home` despublica o site.
9. **Ponteiros de revisão:** `published_revision_id` de uma página não aceita revisão de outra página (FK composta); a imutabilidade cobre `blocks` e `seo`, não `state`.
10. **Execução:** nenhuma Action das etapas S03 a S13 despacha job sem que a medição da etapa o tenha exigido (DA2-21); a revogação de consentimento e o envio de mídia terminam na mesma requisição.
11. **Frescor (seção 09.7, item 6):** o teste de virada de data com relógio congelado, cache quente, fusos distintos, mudança de horário de verão e revalidação condicional; foto revogada responde 404 na revalidação; nenhum cabeçalho `stale-*`.
12. **Auditoria:** tentativa negada não grava evento; publicar seção da empresa grava um evento no nó da empresa com contagem de afetados; `content_version` não gera evento.

---

## 21. Ajuda e tours

Toda tela de negócio nova tem artigo de ajuda e tour próprio. Um artigo pode cobrir várias telas do mesmo fluxo. Até que o suporte de registro esteja disponível, o conteúdo é versionado em `docs/ajuda/{slug}.html` e `docs/tours/{slug}.md`, com a integração pendente registrada na entrega, conforme o AGENTS.md. Artigos em HTML com a tipografia do Ubold. Tours reutilizam `PageHeaderTour` e `BusinessListTour`, e demonstrações de menus, abas e colunas restauram o estado ao sair. O tour pertence à tela, nunca ao modal.

| Etapa | Artigos de ajuda | Tours |
| --- | --- | --- |
| S03 | `departamentos-funcoes` | `departamentos`, `funcoes` |
| S04 e S05 | `colaboradores`, `lotacoes` | `colaboradores`, `colaborador-ficha` |
| S06 e S07 | `perfil-institucional`, `horarios-funcionamento` | `institucional-empresa`, `institucional-estabelecimento` |
| S08 | `midia` | `midia` |
| S09 | `perfil-publico-colaborador` (inclui consentimento e revogação) | `perfil-publico` |
| S10 | `sites-editor`, `blocos` | `sites`, `editor-de-paginas` |
| S11 e S12 | `publicar-site`, `sites-de-unidade` | `publicacao` |
| S13 | `diretorio` | `diretorio` |
| S14 | `dominios-proprios` | `dominios` |

Cada artigo documenta quem pode executar, pré-requisitos, etapas, resultado e bloqueios, e os tours destacam a posição de controles novos. Alterações de layout em telas existentes (Empresas, Estabelecimentos, Etiquetas, Cargos, Pessoas) revisam o artigo e o tour correspondentes na mesma etapa. A verificação visual no navegador, em desktop e mobile, é parte de cada entrega.

---

## 22. Riscos, alternativas e reversão

| Risco | Efeito | Mitigação |
| --- | --- | --- |
| Vazamento entre tenants na renderização pública | Grave | Contexto por host, Queries com `account_id`, Resources com allowlist, teste de payload e de leitura cruzada, projeção do diretório |
| Dado privado publicado por acidente | Grave (LGPD) | Opt-in por campo, elegibilidade na leitura, publicação padrão desabilitada, prévia do que será exibido |
| XSS em conteúdo de cliente | Alto | Blocos tipados, allowlist, CSP, domínio registrável separado, sem HTML livre |
| Domínio próprio tomado ou mal configurado | Alto | TXT, `ask` do TLS, revalidação, etapa final |
| Custo de mídia e armazenamento | Médio | Limites por conta, derivados limitados, exclusão conservadora, PD2-05 e PD2-11 |
| Desempenho do público (cache de banco) | Médio | Cache por versão, medição com worker real. Redis só se a medição o exigir (PD2-10, aprovada) |
| Busca insuficiente sem extensões | Médio | Normalização em PHP, medição, avaliar `pg_trgm` só se justificado |
| Conflito de nomenclatura Cargo e Função | Médio | Resolvido: **Função** (PD2-01, aprovada) e teste de texto (seção 20.5) |
| Crescimento de escopo do editor | Médio | Catálogo fechado de blocos, sem HTML livre, sem builder genérico |
| Reuso indevido do GNAI2 | Jurídico | PD2-03 antes de portar qualquer trecho |
| Regressão do plano 001 | Alto | Testes de regressão por etapa, `audit:check`, etapas pequenas |
| Manutenção de muitos domínios | Médio | Revalidação automática (exige agendador e worker, S14), painel de situação, limites |
| Funcionalidade entregue com lacuna por falta de worker | Médio | DA2-21: se a medição reprovar o síncrono, a própria etapa incorpora o assíncrono e o laboratório (seção 19.1) |
| Diretório sem host próprio | Alto | `public_hosts` com sujeito `directory` (DA2-19) |
| Indexação de páginas vazias ou duplicadas | Médio | `noindex` por padrão, conteúdo mínimo por página de unidade |

**Alternativas descartadas.**

- Instalar WordPress ou CMS externo por cliente: ignora o ERP como fonte e multiplica operação.
- Tabela polimórfica de dono do site: sem FK, sem integridade composta.
- HTML livre com editor WYSIWYG: superfície de XSS desproporcional.
- Entidade Marca agora: contraria a decisão e altera a árvore do plano 001.
- Sistema próprio de autenticação ou de permissões para sites: o público é só leitura sem sessão, e a administração usa o RBAC existente.
- Motor de busca externo agora: custo e operação sem medição que o justifique.
- Herança por campo: resolução difícil de verificar.

**Reversão.** Cada etapa é aditiva e liberada por chave de configuração a partir da S10. Dados criados depois do uso real tornam a `down()` não reversível sem perda, e a recuperação passa a ser por backup ou correção para frente, como no plano 001. Nenhum dado existente é alterado por este plano, exceto a coluna aditiva de fuso (S07).

---

## 23. Decisões de produto e infraestrutura

Estados: **Aprovada** (decidida pelo responsável em 24/11/2026, já refletida nas seções normativas), **Pendente, depende do responsável** (falta informação ou decisão externa), **Pendente, com evidência** (a S01 trouxe evidência e falta aprovação de infraestrutura) e **Proposta** (introduzida pela S02, aguarda ratificação). Uma etapa só adota o que está aprovado. A coluna Bloqueia diz o que **não pode começar** sem a decisão.

### 23.1 Aprovadas

| Código | Decisão aprovada | Onde está refletida |
| --- | --- | --- |
| PD2-01 | Nome da atividade profissional na interface: **Função** (menu "Funções"). Cargo continua reservado a papéis de acesso e permissões | DA2-03, seções 05.1, 10.2, 12.1, 16.1 e 20.5 |
| PD2-04 | Diretório **por conta** no primeiro ciclo | DA2-17, DA2-19, seção 14.1 |
| PD2-06 | Edição e publicação com permissões distintas, sem aprovação obrigatória em duas etapas | DA2-16, DA2-20, seções 11.3 e 12.1 |
| PD2-08 | Fuso IANA por estabelecimento, inicialmente `America/Sao_Paulo`, configurável. Exceção corporativa sobre unidade com `override` somente por contrato explícito (`applies_to_overrides`) | Seções 09.5, 10.3.1 e S07 |
| PD2-09 | Prévia somente autenticada no primeiro ciclo | DA2-13, seção 12.5 |
| PD2-10 | Redis não é dependência automática; medir antes de adotar | DA2-14, S11 |
| PD2-12 | Categorias públicas em tabela própria (`public_categories`) | Seção 05.4 e S06 |
| PD2-13 | Não adicionar a extensão `exif` somente por ausência; a necessidade é comprovada na S08 | DA2-10 e S08 |
| PD2-15 | Editor de blocos tipados com formatação restrita, sem HTML arbitrário nem WYSIWYG completo inicialmente | DA2-07 e seção 11.2 |
| PD2-16 | O gestor de unidade **não** altera o perfil público corporativo compartilhado. Com a permissão específica `account.employees.select-team`, controla a seleção e a ordem dos colaboradores apresentados na sua unidade e usa os dados profissionais da respectiva lotação. Não altera dados centrais nem divulga contatos privados | DA2-20, seção 12.1, testes em S09 e S12 (seção 20.5) |

### 23.2 Pendentes

| Código | Decisão | Estado | Evidência ou padrão proposto | Bloqueia |
| --- | --- | --- | --- | --- |
| PD2-02 | Domínio registrável dos sites gerenciados, hospedagem e wildcard TLS | **Pendente, depende do responsável** | O repositório só tem Compose local e a imagem Caddy **não tem módulo de DNS** (H5), então wildcard por DNS-01 exige build customizada. O domínio deve ser registrável distinto do app (DA2-08) | **S11**, e o host do diretório na S13 |
| PD2-03 | Titularidade e condições do código e dos textos do GNAI2 | **Pendente, depende do responsável** | `composer.json` declara MIT, mas não há arquivo `LICENSE`, e o histórico tem dois autores. Nenhum código foi portado. Sem confirmação, só se reaproveitam ideias | Reuso de código (contraste, S10) |
| PD2-05 | Armazenamento e CDN da mídia | **Pendente (parte)** | A biblioteca está decidida: GD, sem dependência nova, limites de 5 MB e 20 MP. Falta armazenamento e CDN de produção; o disco local atende o laboratório | Produção da S08 |
| PD2-07 | Texto, base legal e retenção do consentimento de foto e contato | **Pendente, depende do responsável** | Nada no repositório define isso. O plano só prevê o registro técnico; o texto exige revisão jurídica | **S09** |
| PD2-11 | Limites por conta (sites, domínios, mídia) | **Pendente, depende do responsável** | Plano comercial. Padrão conservador até lá | S11 e S14 |
| PD2-14 | Serviços `queue` e `schedule` no Compose | **Pendente, com evidência (H3)** | Etapa fixada: **S14** (seção 19.1). Antes dela só por antecipação decidida pelo responsável. Os critérios de laboratório estão na seção 19.1. A S02 não instala nem inicia nada | **S14** |

### 23.3 Decisões arquiteturais introduzidas pela S02, a ratificar

DA2-19 (host público do diretório), DA2-20 (perfil público), DA2-21 (síncrono, fila e agendador), DA2-22 (revisões e despublicação) e DA2-23 (endereço, contatos e ficha da empresa). Todas corrigem contradições do próprio plano e **não alteram** a árvore, o RBAC nem o plano 001. Nenhuma bloqueia a S03 nem a S04.

---

## 24. Esforço, dependências e evolução sem quebrar o plano 001

### 24.1 Esforço qualitativo

Sem estimativa de prazo. Escala relativa: **P** (pequeno), **M** (médio), **G** (grande), **GG** (muito grande, com risco de infraestrutura).

| Etapa | Esforço | Natureza do risco principal |
| --- | --- | --- |
| S01 | P | Verificações e decisões |
| S02 | P | Redação e emendas sem contradição |
| S03 | P | Padrão já conhecido (cadastro simples) |
| S04 | M | Terceira etiqueta derivada, reuso de busca |
| S05 | M | Vigência sem sobreposição e escopo por lotação |
| S06 | G | Resolvedor de herança e telas |
| S07 | M | Fuso e exceções |
| S08 | G | Dependência de imagem, armazenamento, fila |
| S09 | M | LGPD e elegibilidade |
| S10 | GG | Editor de blocos, sanitização, revisões |
| S11 | GG | Superfície pública, cache, TLS e infraestrutura |
| S12 | G | Modo híbrido e SEO local |
| S13 | G | Busca e medição |
| S14 | G | DNS, TLS, segurança de domínio |
| S15 | G | Laboratório e fechamento técnico |

### 24.2 Dependências entre etapas

```text
S01 → S02 ─┬→ S03 ──────────┐
           ├→ S04 ──────────┴→ S05 ──→ (S07 lê o fuso na lotação)
           ├→ S06 → S07
           └→ S08 ─────────→ S09 (também depende de S05)
S06, S07, S08, S09 → S10 → S11 → S12 (também S07 e S09)
S06, S11 → S13 (S12 só para o deep link de unidade com site) → S14 → S15
S14: dependência técnica S11; ordem após S13 por decisão do plano
```

Dependências de **infraestrutura**, separadas das de código (seção 19.1):

```text
S03 a S13: sem worker e sem agendador (DA2-21)
S11: PD2-02 (domínio gerenciado) bloqueia
S13: PD2-02 para o host gerenciado do diretório
S14: worker e agendador (PD2-14), H5 e PD2-02, PD2-11
```

### 24.3 Dependências do plano 001

| Contrato do plano 001 | Uso nesta frente | Etapa |
| --- | --- | --- |
| E04: `companies`, `establishments`, FKs compostas, `TaxDocument` | Donos de colaborador, seção, site e mídia | S03 em diante |
| E05: `account_memberships`, `role_assignments`, antiescalada | Login opcional do colaborador, permissões por nó | S03, S04 |
| E06: `OrganizationContext`, grupos `empresa` e `estabelecimento`, `todos` | Rotas e listagens consolidadas | S03 em diante |
| E07: padrão de relação por empresa (`customers`, `suppliers`), derivação de etiqueta | Molde de `employees` | S04 |
| E08: auditoria contextual, `operation_id`, visibilidade em SQL | Todos os eventos | todas |
| E09: `/contas/{conta}/...` e identidade em vários tenants | URLs administrativas | todas |
| E10: Caddyfile com redação de logs, medição com worker FrankenPHP | Rotas com segredo, medição pública | S11, S14, S15 |
| Plano 001 seção 27.3: "White label, proprietário Tenant, alvo `account`" | Esta frente **refina** para empresa e estabelecimento (sites, mídia, seções, fichas e projeção do diretório) e deixa no tenant só a configuração do diretório (host, categorias) | Refinamento registrado na S02 (seção 27) e na convenção institucional |
| `SaveCompanyAction` e `SaveEstablishmentAction` (E04) | Chamada aditiva de `SyncDirectoryEntryAction` na inativação; a retratação pública não depende dela (seção 09.7) | S13, com teste de regressão |
| `RegisterAccountMembershipAction` e `Tag` (E07) | Criação da etiqueta especial `employee`, aditiva | S04 |
| `establishments` (E04) | Colunas aditivas `timezone` (S07) e "Exibir endereço" (S06) | S06, S07 |

**Implantação.** A implantação das etapas deste plano em produção só ocorre depois da implantação do plano 001, que continua pendente de autorização própria.

### 24.4 Evolução sem quebrar o plano 001

- Nenhuma etapa remove ou altera coluna, rota, permissão ou contrato do plano 001. As únicas alterações em tabela existente são as colunas aditivas de fuso (S07) e de exibir endereço público (S06), ambas em `establishments`, e a chamada aditiva da S13 nas Actions de inativação.
- Novos módulos entram no catálogo de permissões sem renomear existentes. `permissions:sync` não usa `--prune` nesta frente.
- As convenções do plano 001 são **emendadas por adição** na S02 (Colaborador como etiqueta especial, contexto público, cache público). Se a emenda contradisser texto vigente, o conflito é apresentado ao responsável antes de alterar.
- A etiqueta `employee` e os módulos novos seguem o contrato para módulos futuros (seção 27 do plano 001) e o [checklist de módulos](../convencoes/checklist-modulos.md).
- Marca (futura) e catálogo de produtos (futuro) se ligam por tabelas novas, sem reescrever o que esta frente cria (DA2-12 e seção 15).
- A landing do Prospera na raiz permanece intocada. Os sites dos clientes vivem em hosts distintos e nunca no caminho do app.

---

## 25. Quadro de acompanhamento

**Estado geral (atualizado na seção 33): S01 a S06 concluídas e homologadas tecnicamente.** O texto original deste quadro registrava S01 e S02 concluídas e as etapas S03 a S15 ainda não iniciadas, e o histórico segue nas seções 26 a 32. As etapas S07 a S15 não foram autorizadas nem iniciadas. A S01 foi uma verificação e a S02 é exclusivamente documental: nenhum código, migration, rota, tela, seed, asset, dependência, permissão real, Docker ou dado da aplicação foi alterado. Os artefatos versionáveis alterados estão listados na seção 27.1.

| Etapa | Nome | Estado | Referência |
| --- | --- | --- | --- |
| S01 | Verificação das hipóteses e fechamento das decisões | **Concluída em 24/11/2026**, com impedimentos registrados (H4 depende do site público; H5 depende de infraestrutura do responsável) | Seção 26 |
| S02 | Contrato institucional | **Concluída em 24/11/2026**, com DA2-19 a DA2-23 a ratificar | Seção 27 e [`institucional.md`](../convencoes/institucional.md) |
| S03 | Departamentos e funções | **Concluída e homologada tecnicamente** (aprovação do responsável registrada na seção 33). Implementação registrada em 24/11/2026; commit `cb2d765` | Seções 28 e 33 |
| S04 | Colaborador como papel de Pessoa (por empresa, sem lotação) | **Concluída e homologada tecnicamente** (aprovação do responsável registrada na seção 33). Fechamento técnico em 25/11/2026; commits `8fad837` e `46009bc`; ficha reformulada em `b4cb9fb` | Seções 29 e 33 |
| S05 | Lotações e histórico (com o complemento de contatos por lotação) | **Concluída e homologada tecnicamente, inclusive o complemento** (aprovação do responsável registrada na seção 33). Implementação registrada em 26/11/2026; commits `c8a272e` e `b0d14b5` | Seções 30, 31 e 33 |
| S06 | Perfil institucional e herança | **Concluída e homologada tecnicamente** (registrada em 26/11/2026). Commit `458d147` | Seções 32 e 33 |
| S07 | Horários e exceções | Pendente | |
| S08 | Biblioteca de mídia | Pendente | |
| S09 | Perfil público do colaborador | Pendente | |
| S10 | Sites, páginas, blocos e revisões (somente retaguarda) | Pendente | |
| S11 | Publicação em subdomínio gerenciado (primeira superfície pública) | Pendente | |
| S12 | Site de unidade, modo híbrido e localizador | Pendente | |
| S13 | Diretório e busca | Pendente | |
| S14 | Domínios próprios | Pendente | |
| S15 | Fechamento técnico integrado | Pendente | |

### Registro de revisões

- **Revisão 4 (24/11/2026):** revisão final dirigida antes de versionar a S02. Detalhes na seção 27.8.
- **Revisão 3 (24/11/2026):** execução da S02 (seção 27). Decisões aprovadas PD2-01, 04, 06, 08, 09, 10, 12, 13 e 15 (seção 23.1). Separação entre execução síncrona, fila e agendador por etapa, com a S14 como primeira que exige worker e agendador (DA2-21, seção 19.1). Host público do diretório (DA2-19, `public_hosts` no lugar de `site_domains`), perfil público do colaborador (DA2-20), revisões e despublicação neutra (DA2-22), endereço e ficha da empresa (DA2-23). `directory_entries` reclassificada para empresa ou estabelecimento. Alvos de FK composta fechados (seção 06.1). Cenário 17 sem auditoria de negação. Dependência S10 de S07. Fence solta da seção 24.2 corrigida.
- **Revisão 1 (24/11/2026):** criação do plano a partir do briefing institucional e da auditoria aprovada na mesma data.
- **Revisão 2 (24/11/2026):** execução da S01 (seção 26) e correção de inconsistências. Resumo: S04 passa a listar por empresa e a filtragem por unidade vai para a S05 (A); semântica de vigência das lotações (B, seção 10.3.1); dados profissionais por lotação e perfil público sem terceira cópia de contato (C, seção 10.6); distinção entre conteúdo editorial, dado operacional e retratação, com valor de trabalho e valor publicado (D, DA2-16 e seção 09.7); ficha de diretório com autoridade própria (E, DA2-17); S10, S11 e S15 uniformizadas (F). Novos DA2-16 a DA2-18 e PD2-13 a PD2-15. Padrões propostos registrados em PD2-01, PD2-04, PD2-06, PD2-09, PD2-10 e PD2-12, **sem presumir decisão**.

---

## 26. Registro da S01 e evidências

> Registro histórico da revisão 2. Onde a seção 27 ou a convenção institucional divergirem (por exemplo, `site_domains` virou `public_hosts` e a fila das S08 e S11 foi removida), vale a seção 27. As evidências medidas continuam válidas.

Execução em 24/11/2026 sobre o commit `33fd8df`, com a árvore de trabalho limpa. Ambiente: Docker local do projeto (`prospera-app-1`, PHP 8.5.13 ZTS com FrankenPHP; `postgres:18`). Regras seguidas: nenhum arquivo da aplicação alterado, nenhuma extensão criada no banco da aplicação, nenhuma dependência instalada, nenhum domínio registrado, nenhum certificado emitido e nenhuma alteração no Caddy. Os laboratórios usaram `/tmp` do contêiner (apagado ao final) e contêineres `postgres:18` descartáveis (`docker run --rm`), parados ao fim.

### 26.1 Resultado por hipótese

| Hipótese | Resultado | Situação |
| --- | --- | --- |
| H1 Imagens | GD com JPEG, PNG, WebP, AVIF e FreeType. **Sem Imagick e sem `exif`**, e sem biblioteca de imagem no Composer. `memory_limit` 128M, `upload_max_filesize` 5M, `post_max_size` 6M. Um JPEG com segmento APP1 Exif falso (marcador de GPS) recodificado pelo GD para JPEG, PNG e WebP **não preservou o metadado** nas três saídas. Reduzir 1600x1200 para 800 e gravar WebP levou 25 ms, com pico de 30 MB. Decodificar JPEG com 128M: 12 MP usou 48 MB e 24 MP usou 96 MB; **48 MP não coube** (alocação de 192 MB falhou). Sem `exif` o GD não lê a orientação | **Confirmada, com limites.** Limites propostos: 5 MB e 20 MP, lidos por `getimagesize` antes de decodificar. Variantes síncronas são viáveis. Rotação por orientação depende de PD2-13 |
| H2 PostgreSQL | `pg_trgm` 1.6 e `unaccent` 1.1 em `postgres:18` (18.7), ambos `trusted`. Um papel **sem superusuário** (dono do banco) criou as duas extensões. `unaccent` é `STABLE`, portanto não serve em índice de expressão: grava-se `search_text` normalizado (já previsto). Laboratório com 200.000 linhas de **uma única conta** (pior caso): `ILIKE` com dois termos, 148 ms sem índice e **4,6 ms com GIN trigram**; o operador de similaridade `%` com termo comum a todas as linhas levou 658 ms | **Confirmada.** O índice GIN só entra na S13 se o volume e o plano medido justificarem. Similaridade fica fora do primeiro corte. A busca usa `ILIKE` por termo sobre `search_text` |
| H3 Fila | O Compose tem `app`, `postgres` e `mailpit`: **não há worker de fila nem agendador**. O código **não despacha nenhum job** (`ShouldQueue`, `dispatch`, `Queueable` ausentes em `app`). `QUEUE_CONNECTION=database` e a tabela `jobs` existe, mas nada a consome | **Refutada** a premissa de worker em execução. Consequência: S08 e S11 síncronas; a S14 precisa de fila e agendador (PD2-14). O contrato do plano 001 (job leva contexto e reautoriza) continua valendo e não tem exemplo existente |
| H4 Cache | Cache, sessão e fila em banco. **Sem Redis**: nenhum serviço, extensão `redis` ausente (`REDIS_CLIENT=phpredis` só aparece em `.env.example`). O driver `octane` de cache não foi testado | **Parcialmente verificada.** A infraestrutura está confirmada. A adequação a páginas públicas **só pode ser medida com o site público**, na S11, junto com cache HTTP por `content_version` (`ETag` e `Cache-Control`). Redis só se a medição o exigir (PD2-10) |
| H5 DNS e TLS | A imagem FrankenPHP/Caddy tem `tls.issuance.acme`, `tls.issuance.zerossl`, `tls.permission.http` (necessário ao `ask` do TLS sob demanda) e `tls.ech.publishers.dns`. **Não há módulo `dns.providers.*`**, então DNS-01 e wildcard não existem na imagem atual. O repositório não tem configuração de produção (só Compose local; o nome do servidor vem do Octane). `dns_get_record` executa no contêiner sem erro, mas isso não prova o resolvedor de produção. O GNAI2 tem `docker-compose.tunnel.yml` (Cloudflare Tunnel), citado só como indício e não analisado | **Parcialmente verificada, com impedimento concreto.** Domínio gerenciado, provedor de DNS e hospedagem dependem do responsável (PD2-02). Nada foi emitido ou alterado |
| H6 Etiquetas especiais | Mapa completo na seção 26.2 | **Confirmada com correção:** a criação é preguiçosa nas contas novas, não só por migration |
| H7 Editor | Mapa na seção 26.3 | **Confirmada:** o editor é viável sem segunda biblioteca visual, mas com componentes ainda não integrados |

### 26.2 H6: pontos de adaptação para a etiqueta Colaborador

O mecanismo não se limita ao enum `CommercialRole`. Para uma terceira etiqueta derivada, a S04 precisa tocar:

| Ponto | Arquivo | O que muda |
| --- | --- | --- |
| Fábricas e criação preguiçosa | `Tag::supplierTag`, `customerTag`, `specialTag` (`app/Domains/Etiquetas/Domain/Models/Tag.php`) | Nova fábrica `employeeTag`; a regra de sufixo para nome colidente é reaproveitada |
| Provisionamento em contas novas | `RegisterAccountMembershipAction` (linhas 57 e 58) | Criar também a etiqueta de colaborador |
| Contas existentes | migrations `2026_11_18_000007` e `000010` (modelo) | Nova migration idempotente por `system_key` |
| Módulos comerciais de etiqueta | `Tag::COMMERCIAL_MODULES`, `SaveTagData` | Colaborador **não** é módulo comercial: não entra nessa lista |
| Interface de Etiquetas | `tagModules.ts`, `TagEditorModal.tsx`, `tags.tsx`, `TagSelect.tsx`, `TagLabel.tsx` | A etiqueta especial já usa `system_key`; conferir filtro, estrela (somente na listagem de Etiquetas) e leitura somente |
| Derivação | `PeopleDirectoryQuery` (flag `commercial`), `PersonCommercialRelationsQuery` (união de `customers` e `suppliers`) | Generalizar para um registro único de papéis derivados, incluindo `employees` |
| Edição de etiquetas convencionais | `SaveCommercialTagsAction`, `CommercialRelationController` (linhas 295 e 364), `DirectoryController` (linha 88) | Garantir que a etiqueta especial de colaborador não seja alterada pelas rotas de etiqueta convencional |
| Papel oposto | `CommercialRelationController` (linha 364) | Lógica de Cliente e Fornecedor opostos não se aplica a colaborador |
| Modelo | `Person` (relações `customers()` e `suppliers()`) | Acrescentar `employees()` |
| Autoridade por papel | `CommercialRole::companies()` | Não ampliar o enum comercial: criar `EmployeeAuthority` |
| Testes e documentação | `CustomerDirectoryTest`, `ContextualAccessTest`, ajuda `etiquetas.html`, tour `etiquetas.md`, convenção `pessoas-e-contatos.md` | Revisar na mesma etapa |

### 26.3 H7: editor e componentes

- **Integrados ao app** (usados em `resources/js/app`): `ColorPicker`, `NestableRow`, `PageTabs`, `Tabs` e `ProfileAvatar`, além dos de negócio (`BusinessForm`, `BusinessList`, `Modal`, `SelectControl`, `PreviewSection`).
- **Existem como componentes Bold, mas só em páginas de demonstração**: `FileDropzone`, `ImageCropper`, `TimePicker`, `SummernoteEditor`, `TinyMceEditor`, `GoogleMap` e `FormWizard`. A S07 (horários) e a S08 (mídia) precisam integrá-los ao padrão de formulário de negócio, sem criar componente paralelo.
- `NestableRow` é apenas um contêiner estilizado, **sem arrastar e soltar**. Reordenar blocos exige botões de mover ou um contrato novo na S10. `package.json` não traz biblioteca de arrastar e soltar.
- `TinyMceEditor` carrega o TinyMCE da série 4 de `public/assets/plugins/tinymce` (licença LGPL 2.1). Emite HTML, portanto exigiria sanitização no servidor, e é uma série antiga. PD2-15 propõe marcação restrita.
- **GNAI2:** `WebsiteBuilderController` tem só `edit` e `update` de uma linha de configuração, em React com Inertia. Não existe editor de blocos nem lista de páginas para reaproveitar. O que há são as classes de aparência e de contraste.
- Conclusão: o editor é viável com `BusinessForm`, `Modal`, `Tabs` e `PreviewSection`, mais componentes a integrar na S07, S08 e S10. A prova prática do editor (reordenar, editar bloco, prévia) fica como primeira tarefa da S10.

### 26.4 Inconsistências do plano corrigidas na revisão 2

| Correção | Onde | Justificativa |
| --- | --- | --- |
| S04 não promete filtro nem coluna por estabelecimento | Seções 07, 10.4, 12.1, 16.1 e S04 | Sem `employee_assignments` não há autoridade. A listagem é por empresa; a filtragem entra na S05 |
| Vigência definida (inclusive, adjacente, mesmo dia, retroativo, principal) | Seção 10.3.1 e S05 | O texto anterior só dizia "sem sobreposição" |
| Perfil público sem `public_email` e `public_phone` | Seções 05.1, 10.2, 10.6 e 12.4 | Eram uma terceira cópia de contato, contra a convenção de Pessoas. O valor é lido da lotação |
| Contatos profissionais por lotação (`contact_mode`) | Seções 10.2 e 10.6 | O texto exigia contatos diferentes por unidade sem onde guardá-los |
| Seções com valor de trabalho e valor publicado; `draft_settings` e `published_settings` no site | Seções 05.2, 09.7, 11.1 e DA2-16 | Editar uma seção ou o tema alteraria sites publicados sem publicação |
| TTL limitado ao próximo meia-noite local | Seções 09.7 e 11.5 | A vigência de uma lotação muda sem evento e o cache por versão não perceberia |
| Ficha do diretório com autoridade própria e `SyncDirectoryEntryAction` | Seção 14.2.1, DA2-17 e S13 | O modo A não tem página e dependia de `PublishPageAction` |
| `PublishPageAction` não atualiza mais o diretório diretamente | Seção 11.3 | Passou a chamar a sincronização, que é a única que escreve a projeção |
| Alvo `establishment` para `list`, `view` e `assign` só a partir da S05 | Seção 12.1 e DA2-18 | Havia conflito com a regra de alvo igual ao nó do recurso. O precedente é `account.audit.*` |
| Alvo de FK composta `(id, company_id)` em `sites` | Seção 11.1 | As FKs compostas de páginas e domínios precisavam desse alvo |
| Premissa de etiquetas "criadas por migration" corrigida | Seções 02.2, 03 e 26.2 | A criação nas contas novas é preguiçosa |
| S04 não depende da S03 | Seção 19 e diagrama 24.2 | A ficha do colaborador sem lotação não usa departamento nem função |
| S11 depende de S06 e S07; S13 depende de S06; S14 depende tecnicamente só da S11 | Seções 19 e 24.2 | O público lê seções publicadas e horários |
| "A primeira superfície pública é a S10" | Seção 01 | Era a S11. A S10 é só retaguarda |
| S15 passa a "Fechamento técnico integrado" | Seções 19, 24 e 25 | A homologação funcional com usuários é atividade posterior e separada |
| Quadros de encerramento alinhados aos 22 cenários | Seção 19 (revisão 1) | Os números divergiam da tabela da seção 20.2 |

### 26.5 Pendências e limpeza

- **Dependem do responsável:** PD2-02 (domínio gerenciado e hospedagem), PD2-03 (titularidade do GNAI2), PD2-07 (consentimento), PD2-11 (limites por conta) e a aprovação de mudanças de infraestrutura (PD2-05, PD2-13 e PD2-14).
- **Padrões propostos sem decisão:** PD2-01, PD2-04, PD2-06, PD2-08, PD2-09, PD2-10 e PD2-12.
- **Riscos novos:** limite de memória do GD (20 MP), ausência de worker e agendador, ausência de DNS-01 na imagem, TinyMCE 4 como componente não integrado.
- **Limpeza:** arquivos temporários do laboratório de imagens removidos do contêiner e do host; contêineres `postgres:18` descartáveis parados e removidos; nenhum volume, banco ou extensão da aplicação alterado.

---

## 27. Registro da S02, conflitos e condições de início

Executada em 24/11/2026 sobre o commit `33fd8df`, com a árvore sem alterações de outras sessões (apenas este plano, ainda não rastreado). **Exclusivamente documental.** Nenhum comando de aplicação, migration, instalação, Docker ou serviço foi executado.

### 27.1 Arquivos documentais alterados

| Arquivo | Alteração |
| --- | --- |
| `docs/convencoes/institucional.md` | **Novo.** Convenção normativa da frente |
| `docs/plano-de-acoes/002-institucional-equipe-sites-white-label.md` | Revisão 3: decisões, DA2-19 a DA2-23, seção 06.1, seção 19.1, seção 23 reescrita, seção 27 |
| `AGENTS.md` | Linha na tabela de convenções por tarefa e seção curta "Institucional, equipe e sites" |
| `docs/convencoes/pessoas-e-contatos.md` | Colaborador como terceiro papel derivado; etiqueta especial; classe "Dados do colaborador" |
| `docs/convencoes/contexto-organizacional.md` | Seção "Contexto público" e grupo Institucional na sidebar |
| `docs/convencoes/permissoes.md` | Seção "Frente Institucional" (alvo por lotação, editar e publicar distintos, negação sem evento) |
| `docs/convencoes/auditoria.md` | Seção "Frente Institucional" (nó do evento, vigência sem evento) |
| `docs/convencoes/performance.md` | Seção "Conteúdo público" |
| `docs/convencoes/checklist-modulos.md` | Linhas dos módulos da frente |

As emendas às convenções existentes são **por adição**: linhas acrescentadas e uma linha estendida (em Pessoas e contatos, "Contatos específicos da relação", cujo "nenhum" passou a "nenhum em Clientes e Fornecedores" mais os contatos de Colaborador). Nada foi apagado. A única reinterpretação é explícita: a frase de Pessoas sobre o filtro e o cadastro de Etiquetas ofereceram "somente Clientes e Fornecedores" passa a valer para etiquetas **convencionais**, e a etiqueta Colaborador entra como especial derivada somente leitura. O plano já previa essa emenda (seção 24.4) e a sua autorização a cobre.

### 27.2 Decisões formalizadas

**Aprovadas** (seção 23.1): PD2-01, PD2-04, PD2-06, PD2-08, PD2-09, PD2-10, PD2-12, PD2-13 e PD2-15.

**Propostas pela S02 para ratificação** (seção 23.3): DA2-19 a DA2-23. Cada uma corrige uma contradição do próprio plano.

### 27.3 Conflitos detectados e tratamento

| # | Conflito | Tratamento |
| --- | --- | --- |
| 1 | S08 previa variantes "na fila", S11 previa "job de limpeza de cache", S14 previa job de DNS, e o plano dizia que só a S14 precisava de assíncrono. A S01 confirmou que não há worker nem agendador | Seção 19.1 e DA2-21. S08 e S11 são síncronas por contrato (25 ms e 30 MB medidos; invalidação por versão). O job de limpeza foi removido. A S14 é a primeira a exigir worker **e** agendador |
| 2 | Contexto público por host resolvia só `site_id`, mas o diretório sem site (modo A) não tem site nem host | DA2-19: `public_hosts` com `subject` `site` ou `directory`, no lugar de `site_domains`; `PublicContext` no lugar de `PublicSiteContext`; `account.directory.configure` no alvo `tenant` |
| 3 | `directory_entries` era "tenant" mas carregava `company_id` e `establishment_id`, contra o checklist (colunas da classe) | Reclassificada para empresa ou estabelecimento, leitura pública por conta (seções 05.3, 07 e 14.2) |
| 4 | Ficha de empresa exigia "cidade de endereço publicado", mas `companies` não tem endereço | DA2-23: ficha de empresa sem cidade; ficha de unidade exige endereço público |
| 5 | Seção `contacts` podia duplicar `establishments.phone`, `whatsapp` e `email` | DA2-23: contatos públicos são digitados, nunca copiados nem pré-preenchidos |
| 6 | Vigência que expira sem evento: TTL "do dono" não definia o fuso quando há várias unidades, e "aberto agora" mudaria sem evento | Seção 09.7, item 6, e 09.5: `valid_until` na próxima 00:00 do menor fuso envolvido; "aberto agora" fora do HTML em cache, via endpoint com TTL curto |
| 7 | Retratação por inativação de empresa ou unidade dependeria de gancho nas Actions do plano 001 (que a S11 não toca) | Seção 09.7, item 9: leitura pública confere `active` e vigência antes de servir cache; o gancho aditivo da S13 só limpa a projeção |
| 8 | `publish-profile` cobria ligar, desligar e editar, contra PD2-06; o perfil não tinha classe em 09.7; a matriz dava ao gestor de unidade um acesso que o alvo `company` não permitia | DA2-20: `edit-profile`, `publish-profile` e `consent`, todas `company`; classe definida; matriz corrigida; a seleção da equipe pela unidade é a PD2-16, aprovada, com `select-team` |
| 9 | Cenário 17 pedia "auditar a tentativa negada", mas a convenção de Auditoria diz que negação não gera evento e que não há política de segurança aprovada | Seção 12.1 e cenário 17: negar sem alterar nada, sem evento; registrar tentativas fica como decisão futura da plataforma |
| 10 | Despublicar com "404 ou 410" contradizia o 404 neutro da seção 11.4 | DA2-22: sempre 404 neutro; `home` despublicada despublica o site |
| 11 | Revisão `published` "imutável" mas com transição para `archived`; ponteiro e `state` com duas autoridades | DA2-22: imutável é o conteúdo; `published_revision_id` é a autoridade; `state` derivado e conferido |
| 12 | Alvos de FK composta implícitos (`employees`, `media_assets`, `site_pages`, revisões); tabelas de apoio sem colunas da classe; ponteiros circulares sem solução compatível com SQLite | Seção 06.1 com a tabela de alvos, ponteiros anuláveis `MATCH SIMPLE`, colunas organizacionais nas tabelas de apoio |
| 13 | S10 usa o bloco `hours` e a prévia de horários, mas dependia só de S06, S08 e S09 | S10 passa a depender também da S07 (seção 19 e diagrama 24.2) |
| 14 | S13 dependia de S12 para o modo A, que não precisa dela | S12 só para o deep link de unidade com site; infraestrutura vem da S11 |
| 15 | "Possui acesso" na ficha e no filtro podia revelar vínculos a quem não vê Usuários | Mostrado somente a quem tem `account.users.list` em algum escopo, sem cargo nem escopo |
| 16 | Publicar seção da empresa "audita unidades afetadas" contra "um evento por nó escrito" | Evento no nó escrito, com contagem; unidades que herdam são afetadas por leitura (seção 09.7, item 7) |
| 17 | Prévia "autenticada ou assinada" e PD2-10 e PD2-13 ainda como padrão proposto | Textos alinhados às decisões aprovadas (DA2-13, DA2-14, DA2-10) |
| 18 | Chave de cache sem tenant e nó, contra Performance | DA2-14 inclui `account_id` e o nó |
| 19 | Fence de código sem fechamento na seção 24.2 engolia a 24.3 | Corrigida |
| 20 | Remoção de mídia "marcada para remoção" sem dizer quem remove, sem agendador | Remoção por evento, resíduos por comando manual até a S14 (seção 13.1) |

### 27.4 Confronto com as convenções e com o plano 001

| Referência | Resultado |
| --- | --- |
| AGENTS.md | Compatível. Colaborador sem duplicar Pessoa, rótulos, ajuda e tour, permissão por ação, auditoria obrigatória, Octane e estrela de etiqueta mantidos. Emenda por adição |
| Pessoas e contatos | Uma reinterpretação explícita (item da etiqueta, 27.1). Dados centrais, contatos da relação e "sem sincronização" preservados, inclusive para contatos públicos e do colaborador |
| Contexto organizacional | Árvore intacta. Contexto da URL preservado na área autenticada. Contexto público é separado e não usa sessão. Sem entidade Marca |
| Permissões e alvos | Alvos declarados. `establishment` por lotação segue o precedente `account.audit.*`. Nenhuma permissão genérica. Sem novo sistema de permissões |
| Auditoria | Trilha única. Nó do evento corrigido (itens 9 e 16). Sem evento para negação. `unauditedFields()` justificado para `content_version` |
| Performance | Chaves com tenant e nó, `scoped`, sem estado estático, sem N+1, índices só medidos, Redis só medido. Medições adiadas às etapas |
| Checklist de módulos | Ficha da seção 07 do plano e linhas novas na tabela de módulos. Cada etapa repete a ficha |
| Plano 001 | Nada alterado em silêncio. Pontos de contato listados na seção 24.3: refinamento de 27.3, gancho aditivo em `SaveCompanyAction` e `SaveEstablishmentAction` (S13), etiqueta `employee` (S04) e duas colunas aditivas em `establishments` (S06 e S07). A implantação do 001 em produção continua pendente |

### 27.5 Critérios de conclusão

| # | Critério | Situação |
| --- | --- | --- |
| 1 | Convenções coerentes entre si | Atendido. `institucional.md` remete às demais e as emendas só acrescentam |
| 2 | Decisões aprovadas identificadas | Atendido (seção 23.1) |
| 3 | Decisões pendentes explícitas | Atendido (seção 23.2 e 23.3) |
| 4 | Sem contradição entre as seções normativas do plano | Atendido para os 20 conflitos acima. Uma varredura textual por `site_domains`, `PublicSiteContext`, "fila" e "410" não encontrou resíduo normativo. **Limitação:** a verificação é de leitura e busca textual, sem validador automático |
| 5 | Contratos claros para a S03 e a S04 | Atendido (seção 27.7) |
| 6 | Plano 001 sem alteração silenciosa | Atendido (seção 27.4) |
| 7 | Fila e agendamento posicionados | Atendido (seção 19.1 e DA2-21) |

### 27.6 Pendências de produto e infraestrutura

PD2-02 (domínio gerenciado, bloqueia a S11), PD2-03 (GNAI2), PD2-05 (armazenamento e CDN), PD2-07 (consentimento, bloqueia a S09), PD2-11 (limites por conta), e PD2-14 (worker e agendador, bloqueia a S14). Mais a ratificação de DA2-19 a DA2-23.

### 27.7 Condições para iniciar a S03 e a S04

**S03 (Departamentos e Funções).** Pode começar com autorização explícita. Pré-requisitos atendidos: nome **Função** (PD2-01), alvos `company` (seção 12.1), alvo de FK `(id, company_id)` (seção 06.1), classe empresa, auditoria com `company_id`, grupo de rota `empresa/{empresa}`, sem job, sem tela de permissões. Pede: `permissions:sync`, `audit:check`, ajuda e tour, validação visual em 1440, 390 e 320 px. Não depende de decisão pendente.

**S04 (Colaborador como papel de Pessoa).** Pode começar com autorização explícita, em paralelo ou depois da S03 (não depende dela). Pré-requisitos atendidos: `employees` com alvo `(id, company_id)`, FKs `(company_id, account_id)` e `(person_id, account_id)`, etiqueta `employee` derivada e somente leitura, registro único de papéis derivados sem ampliar `CommercialRole`, listagem **por empresa** sem filtros de unidade, indicador de acesso condicionado a `account.users.list`, `account.employees.*` só `company`, sem job. Os pontos de adaptação estão na seção 26.2. Não depende de decisão pendente.

**Não bloqueiam S03 nem S04:** PD2-02, 03, 05, 07, 11 e 14 e a ratificação de DA2-19 a DA2-23.

### 27.8 Revisão final dirigida (revisão 4)

Feita antes do commit da S02, sem reabrir o planejamento. Corrigiu apenas o que a revisão demonstrou.

| Tema | Resultado |
| --- | --- |
| PD2-16 | **Aprovada.** O gestor de unidade não altera o perfil público corporativo. Com `account.employees.select-team` (`company` e `establishment`, pela lotação), escolhe e ordena quem aparece na equipe da unidade, só entre elegíveis, usando função e contato **da lotação**. Não altera dados centrais, não liga flags, não divulga contato privado. A seleção mora em `team_display` (referências, editorial) e a lista manual de pessoas saiu do bloco `team`. Testes em S09 e S12 (seção 20.5, item 2a) |
| `public_hosts` | Conferido contra os quatro modos (tabela em DA2-19). **Defeito demonstrado:** duas autoridades de host, `sites.slug` global (vira subdomínio) e `public_hosts.hostname`. Corrigido: o slug é por conta e editorial, e só `public_hosts` resolve. Também fechados: estados por tipo de host, quarentena de host revogado, host canônico por site, host do app reservado, confiança em cabeçalhos de proxy, destino do diretório sem copiar host. **Segundo defeito:** a S11 criava a tabela e a S14 "adicionava campos" por migration, com CHECK que o SQLite não altera; a tabela nasce completa na S11 |
| Mídia | Critérios de segurança e desempenho explícitos (seção 13.1): barreiras antes de decodificar, estimativa de memória, duração, atomicidade, concorrência, corpus de medição e recusa de arquivo que excede o ambiente sem erro fatal. **Mudança de regra:** se o síncrono reprovar, a própria S08 (ou S13) incorpora o fluxo assíncrono e o laboratório de worker, em vez de parar e adiar |
| Cache e vigência | **Defeitos demonstrados:** TTL pela "próxima meia-noite" não dizia como tratar horário de verão nem conferia a validade na leitura, e cabeçalhos HTTP poderiam manter cópias vencidas em navegador e borda. Corrigido em 09.7, item 6: `valid_until` no início do próximo dia civil do fuso, conferido na leitura, sem `stale-*`, HTML com `no-cache` e `ETag`, foto de pessoa sem `immutable`, teste de virada de data com relógio congelado |
| Plano 001 e convenções | Reconferidos: `people (id, account_id)`, `establishments (id, company_id)` e `account.users.list` existem; `account.audit.*` admite os três nós. Nenhuma contradição nova com o plano 001. A ampliação de Pessoas e contatos para Colaborador foi **aprovada** pelo responsável |

## 28. Registro da S03: Departamentos e Funções

Executada em 24/11/2026 sobre a branch `feat/pagina-inicial` (HEAD `b5f2849`), sem commit, merge, push ou deploy. A S04 e a S05 não foram iniciadas.

### 28.1 Decisão aplicada: DA2-24, uma tela por módulo e a empresa vem do acesso

A primeira versão da S03 abria cada tela dentro de uma empresa (`/empresa/{empresa}/departamentos`) e pedia a escolha da empresa antes de listar. A revisão do produto corrigiu: **a tela é a mesma para todas as empresas e mostra o que o acesso alcança.**

| Ponto | Contrato aplicado |
| --- | --- |
| Rota | `/contas/{conta}/departamentos` e `/contas/{conta}/funcoes`, sem empresa na URL e sem tela de escolha. Removidos a entrada `/empresa`, `CompanyContextController` e `CompanyDestinationData` |
| Listagem | O descritor da permissão `list` (`AuthorizedNodes`) restringe a consulta no SQL: acesso à conta lista todas as empresas; cargo numa empresa lista só ela. Coluna e filtro **Empresa** só aparecem com mais de uma empresa (padrão de Clientes). Filtro `company` por código, resolvido por subconsulta na conta |
| Cadastro | Seleção de **Empresas** (caixas de marcação) com as empresas em que `create` se aplica. Um registro por empresa marcada, numa única transação. Com uma só empresa autorizada, o seletor não aparece e o servidor a assume. Empresa fora do descritor ou inativa responde **404** e nada é gravado |
| Edição | A empresa do registro é fixa (somente leitura). O corpo não move registro entre empresas |
| Autorização | Controller obtém o descritor e responde 403 sem empresa alcançada (`AuthorizedNodes::reachesCompanies`, atribuição só de estabelecimento não abre o módulo). Ficha e edição buscam o registro dentro do descritor: fora dele, 404. A Action confere `create` ou `update` e `activate` ou `deactivate` sobre o nó de **cada** empresa |
| Unicidade | `(company_id, normalized_name)` no banco. A validação cobre todas as empresas marcadas e a mensagem cita as empresas em conflito. Recusa o cadastro inteiro |

O plano da S04 prevê `/empresa/{empresa}/colaboradores`. **Recomendação, a ratificar antes da S04:** adotar o mesmo contrato (tela única, empresa pelo acesso e no cadastro). Nada da S04 foi alterado.

### 28.2 Arquivos

- **Migration:** `database/migrations/2026_11_24_000001_create_departments_and_job_titles.php` (aditiva, `down()` remove as duas tabelas; FKs `(company_id, account_id)` para `companies`, `account_id` para `clients`, únicos `(id, company_id)` e `(company_id, normalized_name)`).
- **Domínio `app/Domains/Colaboradores`:** Models `Department` e `JobTitle` (`Auditable`, `register` e `revise`, relação `company`), `CatalogItemDTO`, Data (`SaveDepartmentData`, `SaveJobTitleData`, `CatalogFiltersData`, `CatalogCompaniesData`), Queries (`DepartmentDirectoryQuery`, `JobTitleDirectoryQuery`), Actions (`SaveDepartmentAction`, `SaveJobTitleAction`), Controllers.
- **Suporte e contratos:** `App\Support\NormalizedName`, `AuthorizedNodes::reachesCompanies`, `OrganizationContextQuery::companies`, `config/permissions.php` (12 ações, alvo `company`), `config/audit.php`, `EffectivePermissions` (visibilidade), `HelpCatalog`, `routes/account.php`.
- **Frontend:** `departments.tsx`, `job-titles.tsx`, `CompanyCatalog.tsx`, `CatalogEditorModal.tsx`, `CatalogPreview.tsx`, `CompanyCatalog.types.ts`, `catalogTours.ts`, `accountNavigation.ts` (grupo **Institucional**), `SaasLayout.tsx`, `ApiApplication.tsx`, `guidance.ts`.
- **Ajuda e tour:** `docs/ajuda/departamentos-funcoes.html`, `docs/tours/departamentos-funcoes.md`.
- **Testes:** `tests/Feature/CompanyCatalogTest.php`.

### 28.3 Contratos preservados

Função independente de Cargo e do RBAC (teste de papéis, atribuições e concessões intactos); sem exclusão física (DELETE responde 405); sem job; `normalized_name` fora da auditoria por derivação; eventos gravam `company_id` e `account_id`; inspeção SaaS somente leitura bloqueia escritas e, em edição, preserva o autor real e o representado; o descritor do representado vale (a outra empresa continua fechada).

### 28.4 Testes e resultados

| Verificação | Resultado |
| --- | --- |
| `CompanyCatalogTest` (20 cenários, 16 deles nos dois cadastros, 36 casos) | 36 aprovados, 397 asserções |
| Suíte completa (SQLite em memória, imagem da aplicação com o código montado) | 325 aprovados, 4362 asserções |
| Pint, PHPStan (`--memory-limit=1G`), `tsc --noEmit`, testes de frontend (20), `check-architecture` | Verdes |
| `permissions:sync`, `audit:check`, `organization:check` | 94 permissões descobertas, 15 modelos auditados, nenhuma violação |
| Migration em PostgreSQL 18 descartável (`prospera_s03_tmp`, removido ao fim) | `migrate:fresh`, `rollback --step=1` (tabelas removidas) e novo `up`: sem erro; restrições conferidas em `pg_constraint`; banco oficial intocado |

Cobertura dos 12 itens obrigatórios: 1 a 3 (criação, normalização, mesmo nome em empresas diferentes, cadastro múltiplo), 4 (404 entre empresas e contas), 5 e 6 (ação a ação, ativar e inativar), 7 (auditoria contextual e leitor de outra empresa), 8 (inspeção), 9 (Cargos intactos), 10 (PostgreSQL), 11 (suíte completa), 12 (ajuda e tour, abaixo).

### 28.5 Evidências visuais

Laboratório isolado (projeto `prospera-s03`, porta 8010, banco próprio com o cenário Grupo Horizonte), Chromium real, removido ao fim. Usuários: `ana.ribeiro` (conta inteira) e `bruno.carvalho` (empresa Supermercados, com permissões de departamentos concedidas só no laboratório).

| Largura | Verificado |
| --- | --- |
| 1440 px | Lista vazia, cadastro com Empresas, erro de empresa obrigatória, cadastro em duas empresas (toast "Departamentos salvos."), nome duplicado citando a empresa, busca sem resultado, edição com empresa fixa, ficha, tour completo (16 passos), sem rolagem horizontal |
| 390 px | Mesmo fluxo, lista compacta, Funções vazia com filtros, tour (13 passos), sem rolagem horizontal |
| 320 px | Mesmo fluxo, mensagens de validação no modal, tour em folha inferior, sem rolagem horizontal |
| Acesso restrito (1440) | Bruno vê só os registros da empresa dele, sem filtro nem coluna de empresa, sem seletor no cadastro, grava na empresa dele; Funções oculta no menu |

Tour: abertura, passos do cadastro com a janela aberta pela demonstração, encerramento sem janela residual (0 diálogos) e sem gravar dados. Console sem erros além dos 422 esperados das validações.

### 28.6 Defeitos encontrados e correções

| Defeito | Correção |
| --- | --- |
| Modelo inicial com empresa na URL e tela de escolha, contra a intenção do produto | DA2-24: tela única, listagem pelo acesso e seleção de empresas no cadastro (seção 28.1) |
| Atribuição só de estabelecimento abria a tela (200 vazio) | `AuthorizedNodes::reachesCompanies` e 403 |
| Coluna Empresa truncada e Situação cortada em 390 px | Coluna fora do modo compacto e só com mais de uma empresa, como em Clientes |
| Toast "Função salvo." (concordância) | "Função salva." |

### 28.7 Pendências reais e observações

- **Integração de ajuda e tour:** o conteúdo está versionado em `docs/ajuda/` e `docs/tours/` e integrado à Central de Ajuda e a `guidance.ts` existentes. Nenhuma integração externa pendente.
- **Mensagem de 403:** uma pessoa sem a permissão que abre a URL direta recebe "Conta indisponível", texto genérico já existente para qualquer 403 dentro da conta. Não alterado nesta etapa.
- **Passo "Empresas" do tour:** o popover cobre parte da lista de empresas no destaque. A informação permanece legível, igual aos demais passos do cadastro.
- **Seed:** os cargos do cenário de desenvolvimento não recebem as novas permissões (só o Administrador integral). Concedê-las é decisão do operador pela tela de Cargos.
- **Execução dos testes:** o PHP do host não tem o driver SQLite; usou-se a imagem da aplicação com o código montado.

### 28.8 Classificação

**S03: implementada, verificada e versionada (`cb2d765`).** Classificação original: em revisão. **Homologada tecnicamente pelo responsável** (registro na seção 33). Estar versionada não substitui a homologação, e esta foi dada separadamente.

## 29. Registro da S04: Colaborador como papel de Pessoa

Executada em 25/11/2026 sobre a branch `feat/pagina-inicial` (base `9e01157`), versionada em commit local, sem merge, push ou deploy. A S05 não foi iniciada.

### 29.1 Contrato aplicado (DA2-24 estendida)

| Ponto | Contrato |
| --- | --- |
| Rotas | `/contas/{conta}/colaboradores` (lista), `/cadastrar`, `/{pessoa}/visualizar`, `/{pessoa}/editar`, `POST /pessoa` (busca por documento), `PATCH /{pessoa}`, `POST /{pessoa}/vinculos`, `PATCH /{pessoa}/vinculos/{vinculo}`. Sem empresa na URL e sem tela de escolha. Substitui `/empresa/{empresa}/colaboradores` do plano original |
| Listagem | Uma linha por Pessoa, sobre `people` com `whereIn` nos vínculos do descritor de `account.employees.list`. Busca, filtros (situação do vínculo, empresa, acesso), contagem e paginação no SQL. Os vínculos exibidos vêm de uma segunda consulta por página, também restrita ao descritor. Vínculos fora do acesso não entram em filtro, contagem nem resposta |
| Cadastro | Pessoa existente (`person_id`, descoberta por CPF ou CNPJ completo na conta) ou nova (`CreatePersonAction`), mais empresas escolhidas. Uma só empresa autorizada dispensa a escolha. Um vínculo por empresa numa única transação com a conta travada; empresa fora do descritor ou inativa responde 404; duplicidade em qualquer empresa recusa tudo (422), inclusive a pessoa nova |
| Ficha | Dados centrais (Pessoas, mesma autoridade de `UpdatePersonAction`), etiquetas derivadas, um formulário por vínculo (situação e contatos profissionais, independentes), vínculo a outras empresas e indicador de acesso somente leitura |
| Modelo | `employees (id, account_id, company_id, person_id, active, work_email, work_phone, work_whatsapp)`, único `(company_id, person_id)` e `(id, company_id)`, FKs compostas `(company_id, account_id)` e `(person_id, account_id)`. Sem CPF, nome, endereço, e-mail pessoal ou login |
| Etiqueta | `employee` em `DerivedRole` (registro único de papéis derivados, sem ampliar `CommercialRole`). `Tag::employeeTag`, provisionada em contas novas (`RegisterAccountMembershipAction`) e nas existentes pela migration (idempotente por `system_key`). `PersonCommercialRelationsQuery` inclui `employees`. `ListDerivedPersonTagsQuery` só devolve o papel se houver relação ativa em empresa coberta por `account.employees.view` |
| Usuário | Indicador `has_access` via `account_memberships.person_id`, presente na resposta (chave, filtro e contador) somente com `account.users.list`. Sem a permissão a chave não existe e o filtro é ignorado. Cadastrar, editar e inativar nunca criam nem alteram login ou permissões |
| Permissões | `account.employees.{list,view,create,update,activate,deactivate}`, alvo `company`. Atribuição só de estabelecimento recebe 403 |
| Auditoria | `Employee` auditável (tipo `employee`), um evento por empresa com `company_id`, sem documento. Inspeção SaaS somente leitura bloqueia escritas |

### 29.2 Arquivos

- **Backend:** migration `2026_11_25_000001_create_employees.php`; `Employee`; Data (`CreateEmployeeData`, `NewEmployeeLink`, `UpdateEmployeeData`, `ListEmployeesData`); DTOs; Actions (`CreateEmployeeAction`, `UpdateEmployeeAction`, `UpdateEmployeePersonAction`); Queries (`ListEmployeesQuery`, `ListEmployeeLinksQuery`, `ShowEmployeePersonQuery`, `ShowEmployeeLinkQuery`, `FindEmployeePersonQuery`, `PersonHasAccountAccessQuery`); `EmployeeController`; `DerivedRole`; `ListDerivedPersonTagsQuery`; ajustes em `Tag`, `Person`, `RegisterAccountMembershipAction`, `EffectivePermissions`, `HelpCatalog`, `EnforceAccountInspection`, `PersonCommercialRelationsQuery`, `config/permissions.php`, `config/audit.php`, `routes/account.php`.
- **Frontend:** `employees.tsx`, `employee-form.tsx`, `Employees.css`, `ApiApplication.tsx`, `SaasLayout.tsx`, `accountNavigation.ts` (grupo Institucional), `guidance.ts` (tours da lista, do cadastro e da ficha), `PageHeader.css` (título de palavras longas em telas estreitas).
- **Ajuda e tour:** `docs/ajuda/colaboradores.html`, `docs/tours/colaboradores.md`, `docs/ajuda/etiquetas.html`, `docs/tours/etiquetas.md`.
- **Testes:** `tests/Feature/EmployeeDirectoryTest.php` (19 cenários); `PeopleDirectoryTest` ajustado (uma etiqueta especial a mais no catálogo).

### 29.3 Verificações

| Verificação | Resultado |
| --- | --- |
| Suíte completa (SQLite em memória, imagem da aplicação) | 344 testes, verdes |
| Pint, PHPStan, `tsc --noEmit`, testes de frontend (20), `check:architecture` | Verdes |
| `permissions:sync`, `audit:check`, `organization:check` | 100 permissões, 16 modelos auditados, nenhuma violação |
| PostgreSQL 18 descartável | `migrate:fresh`, `rollback --step=1`, nova aplicação e restrições em `pg_constraint`; etiqueta criada para conta existente, removida na `down()` e recriada; 4 processos concorrentes cadastrando a mesma pessoa novamente: 1 pessoa, 1 vínculo, 3 recusas de validação |
| Navegador (Chromium, laboratório isolado) | 1440, 390 e 320 px: administrador, operador de uma empresa (Roupas), leitor sem pessoas e usuários, outra conta. Lista com uma linha por pessoa, sem rolagem horizontal, cadastro em duas empresas, reaproveitamento por CPF, inativação de um vínculo sem afetar o outro, tours (lista 14 passos, cadastro 5, ficha 7) restaurando o estado |

### 29.4 Defeitos encontrados e correções

| Defeito | Correção |
| --- | --- |
| Busca por documento chamava `@/colaboradores/pessoa` sem resolver o prefixo (rota inexistente) | `useContextUrl` |
| Situação do vínculo não mudava (campo controlado sem estado) | Componente `LinkForm` com estado |
| Notas de seção em vermelho e título "COLABORADORE S" em 320 px | `FormHelp` e ajuste do `PageHeader` (título prioritário e caminho abaixo em até 360 px) |
| Empresas truncadas na lista compacta | Empresas sob o nome no modo compacto |

### 29.4.1 Fechamento técnico

| Verificação | Resultado |
| --- | --- |
| Permissões sobre Pessoas | Dados centrais exigem `account.people.update` pela autoridade de `UpdatePersonAction`, além de `account.employees.update` na ficha. Teste de envio forjado: só colaboradores recebe 403 sem alterar nome, CPF nem contato; só pessoas também recebe 403; as duas juntas passam. Vínculo editável sem tocar em Pessoa; formulário sem edição central habilitada quando falta permissão |
| Etiqueta derivada | Dois vínculos ativos, inativar um (permanece), inativar o último (some, sem `person_tag`), reativar (volta), vínculo em empresa sem acesso (não revelado). Clientes e Fornecedores intactos |
| `organization:check` | A advertência "Conta #1: sem empresa" vinha de uma conta criada só pelo ensaio da migration. Com massa consistente: nenhuma violação. Comando não alterado |
| Efeito colateral intencional | `PersonCommercialRelationsQuery` agora inclui `employees` nas empresas relacionadas, logo o vínculo de colaborador também conta na regra de cobertura da edição de identificação de Cliente e Fornecedor (plano 26.2) |

### 29.5 Pendências reais

- Filtros por estabelecimento, departamento e função, coluna Unidades, `todos` e lotações permanecem na S05.
- O seed de desenvolvimento não concede as novas permissões a cargos existentes (só ao Administrador integral).
- Concorrência medida só no PostgreSQL do laboratório, fora da suíte, conforme a convenção.

### 29.6 Classificação

**S04: implementada, verificada e versionada (`8fad837` e `46009bc`).** Classificação original: em revisão. O fechamento técnico foi aprovado em 25/11/2026 e a **homologação técnica foi dada pelo responsável** (seção 33).

## 30. Registro da S05: Lotações e histórico

Executada em 26/11/2026 sobre a branch `feat/pagina-inicial` (HEAD `46009bc`), sem commit, merge, push ou deploy. A S06 não foi iniciada.

### 30.1 Contrato aplicado

| Ponto | Contrato |
| --- | --- |
| Tabela | `employee_assignments` conforme a seção 10.2, **sem** `contact_mode` e `work_contacts` (contatos por lotação ficam para a S06 e a S09, aditivos). FKs compostas de colaborador, unidade, departamento e função na mesma empresa; CHECK de período; índice parcial único da principal sem fim; índices `(employee_id, establishment_id)` e `(establishment_id, ends_on)` |
| Vigência | Datas `Y-m-d` inclusivas; `ends_on` nulo é sem data final e não significa que já começou (futura, vigente e encerrada por cálculo, sem job). "Hoje" no fuso de negócio configurável |
| Regras | Sem sobreposição no mesmo colaborador e unidade (inclusive futuras); retroativo aceito se não sobrepõe; troca em D encerra em D - 1 e abre em D, e no mesmo dia do início vira correção; encerrar a principal exige a nova principal; inativar encerra em `hoje` as já iniciadas e vigentes, mantém as futuras (inertes) e o histórico; reativar não reabre e a nova lotação é explícita; criar lotação exige vínculo ativo; unidade, departamento ou função fora da empresa ou inativos são recusados |
| Concorrência | `AuditLog::transaction()` com trava da empresa e do colaborador antes de verificar sobreposição e principal; `institutional:check` cobre o que depende de várias linhas |
| Permissões | `account.employees.assign` (alvos empresa e estabelecimento). `list` e `view` também admitem estabelecimento (DA2-18). Atribuição só de estabelecimento abre o módulo apenas para vínculos com lotação nela; criar, editar e inativar seguem só na empresa |
| Escopo | `EmployeeScope` em todas as leituras (lista, ficha, vínculo, etiqueta derivada). Opções dos filtros e de lotação limitadas ao escopo, sem revelar outras unidades |
| Auditoria | `EmployeeAssignment` auditável (tipo `employee-assignment`), eventos com `company_id` e `establishment_id`, mesma operação lógica na troca e na inativação |
| Interface | Listagem: coluna Unidades (letreiro), filtros de estabelecimento, departamento, função e situação da lotação, mesma lotação nos filtros combinados. Ficha: seção Lotações por vínculo (vigentes, futuras e histórico), modais de adicionar, corrigir, alterar função ou departamento, encerrar e tornar principal. Gestor de unidade vê só a própria unidade |

### 30.2 Arquivos

- **Backend:** migration `2026_11_26_000001_create_employee_assignments`; `EmployeeAssignment`, DTOs, Data (`CreateEmployeeAssignmentData`, `UpdateEmployeeAssignmentData`, `ChangeEmployeeAssignmentData`, `EndEmployeeAssignmentData`, `AssignmentOptions`); Actions de lotação; Queries (`AssignmentOverlapExistsQuery`, `OverlappingPrimaryIdsQuery`, `ListRemainingAssignmentsQuery`, `ListOpenAssignmentsQuery`, `ListAssignmentOptionsQuery`, `ListEmployeeAssignmentsQuery`, `ShowEmployeeAssignmentQuery`, `ListEmployeeFilterOptionsQuery`, `EmployeeScope`, `ListInstitutionalViolationsQuery`); `EmployeeAssignmentController`; `CheckInstitutionalCommand`; `BusinessDate`; ajustes em `EmployeeController`, `ListEmployeesData`, `ListEmployeesQuery`, `UpdateEmployeeAction`, `DerivedRole`, `ListDerivedPersonTagsQuery`, `EffectivePermissions`, `config/permissions.php`, `config/audit.php`, `config/prospera.php` e `routes/account.php`.
- **Frontend:** `EmployeeAssignments.tsx`, `employees.tsx`, `employee-form.tsx`, `Employees.css`, `guidance.ts`.
- **Ajuda e tour:** `docs/ajuda/colaboradores.html`, `docs/tours/colaboradores.md`, `docs/convencoes/institucional.md`.
- **Testes e laboratório:** `tests/Feature/EmployeeAssignmentTest.php` (15 cenários), ajuste de `EmployeeDirectoryTest` e `tests/Performance/s05-race.php` e `.sh`.

### 30.3 Verificações

| Verificação | Resultado |
| --- | --- |
| Suíte completa (SQLite em memória) | 360 testes, 4882 asserções, verdes |
| Pint, PHPStan, `tsc`, frontend (20), `check:architecture` | Verdes |
| `permissions:sync`, `audit:check`, `organization:check`, `institutional:check` | 101 permissões, 17 modelos, nenhuma violação |
| PostgreSQL 18 descartável | `migrate:fresh`, `rollback --step=1` e reaplicação; FK composta e CHECK recusam, em SQL direto, unidade de outra empresa e período invertido |
| Concorrência (4 processos reais, `s05-race.sh`) | Mesma unidade com períodos sobrepostos: 1 gravada e 3 recusadas. Unidades diferentes, todas principais: 1 gravada. Períodos adjacentes: 4 gravadas |
| Plano da listagem (5004 vínculos, 6667 lotações) | Filtro de unidade e vigência com 3,8 ms de execução. O índice `(establishment_id, ends_on)` é usado, o que o justifica |
| Navegador (Chromium, laboratório isolado) | 1440, 390 e 320 px: administrador, gestora da empresa, gestor da unidade Guanambi; lista, filtros combinados, ficha, modais, validações de sobreposição e de datas, troca de função com histórico, inativação e reativação, tours (ficha com 10 passos) restaurando o estado e sem rolagem horizontal |

### 30.4 Defeitos encontrados e correções

| Defeito | Correção |
| --- | --- |
| `ALTER TABLE ADD CONSTRAINT` não existe no SQLite | CHECK no SQL de criação da tabela, como em `establishments` |
| Gestor de unidade não conseguia encerrar a principal porque a regra exigia indicar a principal de uma unidade que ele não vê | Candidatas à nova principal limitadas às lotações que o operador pode gerenciar |
| Passos "Adicionar lotação" e "Histórico" do tour ausentes (o botão não repassa `data-tour`) | Botões envolvidos em elementos com `data-tour` |
| Nota da seção centralizada e lista sem ordem útil | Nota alinhada e ordem vigentes, futuras e encerradas |
| `EmployeeDirectoryTest` afirmava 403 para atribuição de unidade | Contrato novo: abre o módulo só para quem tem lotação na unidade |

### 30.5 Pendências reais

- Perfil público e publicação dos contatos: S09 (os contatos por lotação foram concluídos no complemento da seção 31).
- Filtro de perfil público: S09. Auditoria exibe identificadores de unidade, departamento e função como números, como já ocorre com `person_id`.
- O seed de desenvolvimento não concede `account.employees.assign` a cargos existentes (só ao Administrador integral).
- Concorrência medida em PostgreSQL de laboratório, fora da suíte, conforme a convenção.

### 30.6 Classificação

**S05: implementada, verificada e versionada (`c8a272e`).** Classificação original: em revisão. **Homologada tecnicamente pelo responsável** (seção 33).

## 31. Complemento da S05: contatos profissionais por lotação e autoridade da principal

Executado em 26/11/2026 sobre a branch `feat/pagina-inicial` (HEAD `c8a272e`), sem commit, merge, push ou deploy. A S06 não foi iniciada.

### 31.1 Auditoria do contrato

O contrato (seções 10.2 e 10.6, DA2-18 e convenção) previa `contact_mode` e `work_contacts` na lotação, com a semântica de vazio intencional da seção 09.2, e a S05 os havia adiado. O modelo existente (`EmployeeAssignment`) não tinha os campos, então a migration aditiva era necessária. Conferido contra DA2-24 (tela única) e DA2-18 (gestor de unidade): o contato efetivo da lotação aparece ao gestor da unidade, os campos da relação não.

### 31.2 Contrato aplicado

| Ponto | Contrato |
| --- | --- |
| Persistência | Migration `2026_11_26_000002`: `contact_mode` (padrão `inherit`) e `work_contacts` JSON. CHECK de coerência no PostgreSQL. Lotações existentes passam a `inherit`, sem perda |
| Herança | `inherit` usa `employees.work_*` e acompanha a mudança. `override` guarda o objeto, inclusive vazio (ausência intencional). `effectiveContacts()` informa a origem (`inherit`, `override`, `none`) e nunca lê Pessoa |
| Validação | `AssignmentContactsData` reutiliza `ContactInput::normalize` e as mesmas regras e mensagens dos contatos do vínculo |
| Edição | Criar, corrigir e (por correção) alterar contatos exigem `account.employees.assign` sobre a unidade. Corrigir sem `contact_mode` preserva a personalização. A troca de função leva os contatos ao período novo. Mudar o contato padrão do vínculo não toca lotações personalizadas |
| Principal | Transferir a marca só ocorre se todas as principais sobrepostas estão no alcance (422 antes de qualquer escrita). Encerrar a principal só indica herdeira entre as lotações gerenciáveis, e é recusado, com explicação, quando as demais estão fora do alcance |
| Interface | Cada lotação mostra "herdados do vínculo", "personalizados" ou "sem contato nesta unidade (intencional)". Os modais de adicionar e corrigir escolhem entre herdar, personalizar e sem contato. Os erros de principal aparecem no modal. Sem controle de publicação |

### 31.3 Verificações

| Verificação | Resultado |
| --- | --- |
| Suíte completa (SQLite) | 366 testes, 5010 asserções, verdes |
| Pint, PHPStan, `tsc`, frontend (20), `check:architecture` | Verdes |
| `permissions:sync`, `audit:check`, `organization:check`, `institutional:check` | Sem violações (o `institutional:check` agora detecta modo de contato incoerente) |
| PostgreSQL 18 descartável | Migration sobre lotações existentes (viraram `inherit`), rollback e reaplicação; o CHECK recusou `override` sem valor e `inherit` com valor |
| Concorrência (4 processos, `s05-race-contacts.sh`) | 4 edições simultâneas de contato: estado final coerente (`override` com um valor). 4 transferências simultâneas da principal: exatamente 1 principal. As corridas da S05 foram repetidas, com o mesmo resultado |
| Navegador | 1440, 390 e 320 px: herdar, personalizar, sem contato, mudança do contato padrão sem sobrescrever a personalização, gestor de unidade com o contato efetivo e a recusa explicada ao encerrar a principal, tour restaurado, sem rolagem horizontal |

### 31.4 Defeitos encontrados e correções

| Defeito | Correção |
| --- | --- |
| Gestor de unidade podia encerrar a principal enquanto outra lotação seguia vigente em unidade oculta, deixando o colaborador sem principal sem aviso (introduzido na S05) | Recusa com explicação quando só há lotações fora do alcance |
| Tornar principal falhava com 403 genérico ao esbarrar numa principal oculta e a verificação ocorria durante as escritas | Verificação completa antes de escrever e 422 explicativo |
| O erro de herdeira e de principal não aparecia no modal quando o campo não era renderizado | Mensagem exibida no modal |
| Corrigir datas sem contatos apagaria a personalização se o modo viesse por padrão | `contact_mode` ausente mantém os contatos |

### 31.5 Relação com a S09 e pendências

A S09 lerá o contato efetivo da lotação aplicável (seção 10.6), com `show_work_email` e `show_work_phone`, sem copiar valor para o perfil. Pendências: perfil e publicação (S09); a auditoria continua mostrando unidade, departamento e função como identificadores numéricos.

### 31.6 Classificação

**Complemento da S05: implementado, verificado e versionado (`b0d14b5`).** Classificação original: em revisão. **Homologado tecnicamente pelo responsável** (seção 33).

## 32. Registro da S06: Perfil institucional e herança

Executada em 26/11/2026 na branch local `feat/s06-perfil-institucional`, criada a partir de `fix/ficha-sem-acesso` (HEAD `b4cb9fb`, que já contém a S05 e o complemento). Sem commit, merge, push ou deploy. S03, S04 e S05 seguem com o estado registrado nas seções 28 a 31: este registro não as homologa. A S07 não foi iniciada.

### 32.1 Estado inicial

Suíte de 366 testes verde antes da S06. `main` em `b0d14b5` (S05 e complemento), a branch `fix/ficha-sem-acesso` à frente com a ficha do colaborador. As migrations da S06 não alteram migrations históricas. O complemento da S05 (`contact_mode`, `work_contacts`, contato efetivo e autoridade da principal) permaneceu intacto, com os testes correspondentes verdes.

### 32.2 Contrato entregue

| Ponto | Contrato |
| --- | --- |
| Persistência | Migration `2026_11_27_000001`: `profile_sections` (pares de trabalho e publicado, CHECKs, únicos parciais por dono, FKs compostas), `public_categories` e `establishments.show_public_address` |
| Resolvedor | `ProfileResolver` `scoped`, estados `draft` e `published`, uma consulta por dono, padrão tipado da plataforma. Teste arquitetural contra leituras fora dele |
| Seções | Editoriais `palette`, `typography`, `about`, `seo_defaults`; operacionais `contacts` e `social_links`; declaradas e indisponíveis `logo`, `banners`, `gallery` (S08) e `team_display` (S09). Contraste verificado no servidor, fontes de catálogo fechado, texto sem HTML, redes só com HTTPS e domínio da rede |
| Herança | Por seção inteira; vazio intencional não herda; desativar só onde o catálogo permite; galeria local; publicar a empresa não toca unidades com `override` nem o rascunho delas |
| Publicação | `PublishProfileSectionsAction` com impacto recalculado e confirmação obrigatória; evento `published` no nó escrito |
| Permissões | `account.institutional.view`, `update` e `publish` (empresa e estabelecimento); editar não publica; gestor de unidade só alcança a própria unidade |
| Privacidade | Contatos públicos digitados, nunca de Pessoas, colaborador ou contatos operacionais; endereço só com "Exibir endereço" (desligada por padrão) |
| Interface | Lista de donos, perfil da empresa e do estabelecimento com origem, rascunho, publicado, Voltar a herdar, Desativar, publicação com resumo e opção de endereço; ajuda e tour |
| Categorias | `public_categories` por conta com Actions auditadas (sem tela nem permissão até a S13) |

### 32.3 Verificações

| Verificação | Resultado |
| --- | --- |
| Suíte completa (SQLite) | 391 testes, verdes (366 anteriores mais 25 da S06), ver 32.7 |
| `InstitutionalProfileTest` | 23 cenários que cobrem os 38 itens obrigatórios aplicáveis a esta etapa (herança em rascunho e publicado, overrides, vazio intencional, 422, PATCH parcial, isolamento entre contas, empresas e unidades, permissões, inspeção, privacidade, impacto, rollback, auditoria e consultas) |
| PostgreSQL 18 descartável | Rollback e reaplicação sobre dados existentes; CHECKs, únicos parciais, FK composta e únicos de categoria recusam as violações em SQL direto |
| Concorrência (4 processos, `s06-race.sh`) | Criação simultânea da mesma seção: 1 linha. Publicação simultânea: 1 evento e estado coerente. Salvar e publicar juntos: publicado sempre coerente. Unidades diferentes não interferem |
| Performance | 60 unidades, 91 linhas: 1 consulta por dono (1,8 ms para draft e published; mediana 0,27 ms e p95 0,68 ms por unidade). Em 60,6 mil linhas, o plano usa BitmapOr dos dois índices parciais em 0,054 ms |
| FrankenPHP (workers persistentes, `tests/Browser/s06-octane.mjs`) | Com 1 e com 2 workers: 540 verificações alternando contas, usuários, permissões, rascunho e publicado, com escritas intercaladas, sem vazamento |
| Navegador | 1440, 1024, 768, 390 e 320 px: lista, perfil da empresa e da unidade, edição, contraste inválido, publicação com resumo, voltar a herdar, desativar e reativar, endereço, gestor de unidade isolado, editor sem publicar e tour; sem rolagem horizontal e sem erros de JS |

### 32.4 Defeitos encontrados e correções

| Defeito | Correção |
| --- | --- |
| Botões das seções sem o estilo do padrão de adicionar | Importação de `ContactFields.css` |
| Verificação de impacto do teste confirmava 0 e 0 | O teste passou a confirmar os números calculados pelo servidor, como a tela faz |
| Falha simulada na publicação não disparava com `saveQuietly` | O teste força a falha na segunda gravação via `DB::listen` |
| PHPStan: leituras `object` do query builder e coleções sem tipo | `\stdClass` e listas explícitas |

### 32.5 Limitações e pendências

- Logotipo, banners, galeria (S08) e equipe exibida (S09) ficam declarados e indisponíveis. `hours_weekly` e o fuso por estabelecimento são da S07.
- Sem sites não há contagem de sites no impacto nem invalidação por `content_version` (S10 e S11).
- Categorias públicas: as Actions têm **segurança de contexto** (só dentro da própria conta, dados de outra conta recusados, nenhuma rota ou tela as alcança), mas a **interface e o controle administrativo de permissões serão concluídos na S13**, com a permissão de tenant do diretório.
- O conteúdo das seções aparece como JSON na tela de Auditoria. A formatação legível é uma evolução.
- A leitura em lote de vários donos pelo diretório (S13) deve ganhar um carregamento conjunto no resolvedor, medido nessa etapa.

### 32.6 Classificação

**S06: Concluída e homologada tecnicamente em 26/11/2026**, após a revisão dirigida da seção 32.7. O versionamento local fica para a decisão do responsável.

### 32.7 Revisão dirigida e homologação técnica

**1. Actions de `public_categories` (isolamento e autorização).** Não existe rota, controller, tela nem outro domínio que as chame (teste arquitetural). Enquanto a permissão de tenant da S13 não existe, as Actions recusam por construção: `AuthorizationException` quando o usuário não está dentro da conta informada (ou não há conta aberta), `ResourceNotAvailable` quando os dados validados são de outra conta, e categoria de outra conta responde como inexistente (`ModelNotFoundException`). Os `Data` carregam `accountId`. Testes: cadastro e edição na própria conta, nome e slug únicos por conta, iguais em outra conta, conta divergente do contexto, sem conta aberta, dados mistos e categoria alheia, sem alterar nada.

**2. Consistência transacional.** Toda escrita do perfil (salvar, publicar, endereço) trava a empresa antes de qualquer linha, na mesma ordem, o que serializa salvar e publicar do mesmo perfil e as escritas de unidades da mesma empresa, sem inversão de ordem de travas. O impacto é recalculado depois da trava. Prova em PostgreSQL (`s06-race.sh`, 4 a 8 processos): criação simultânea gera uma linha; publicações simultâneas geram um evento; 8 salvamentos simultâneos deixam a cadeia de auditoria contínua (o "antes" de cada evento é o "depois" do anterior, 0 quebras) e o valor final é o do último evento; salvar e publicar em paralelo deixam o publicado igual a um rascunho já auditado; unidades diferentes não interferem. Mais o teste de rollback (falha na segunda gravação não deixa publicação nem evento parcial).

**3. Situação documental de S03, S04 e S05, sem alteração de estado.** Conferido, sem editar nenhum estado ou registro anterior:

| Etapa | Estado registrado | Observação documental |
| --- | --- | --- |
| S03 | "Implementada, em revisão (sem commit)" no quadro e na seção 28 | O código foi versionado depois (commit `cb2d765`); o texto "sem commit" ficou desatualizado |
| S04 | "Tecnicamente concluída" no quadro; a seção 29.6 diz "em revisão, sem commit" | Há divergência interna entre o quadro e a seção 29.6; o commit existe (`8fad837` e `46009bc`) |
| S05 e complemento | "Em revisão (sem commit)" no quadro e nas seções 30 e 31 | Versionados (`c8a272e` e `b0d14b5`) e já na `main`; o texto "sem commit" ficou desatualizado |

As implementações permanecem intactas e verdes na suíte. A atualização dos estados e a harmonização desses textos dependem da decisão do responsável.

**Resultado.** Sem defeitos novos além do endurecimento das Actions de categorias (acrescentado nesta revisão). **S06: Concluída e homologada tecnicamente.**

## 33. Regularização documental e homologação técnica de S03 a S06

Esta seção só organiza o registro. Nenhuma implementação foi alterada e as evidências são as já registradas nas seções 28 a 32. Os históricos de implementação e de revisão permanecem como foram escritos: a classificação original de cada etapa ("em revisão") continua nas seções 28 a 31, e a homologação é um registro posterior e separado.

**Três datas diferentes.** (a) *Implementação*: a data registrada na abertura de cada seção (24/11/2026 para a S03, 25/11 para a S04 e 26/11 para a S05, o complemento e a S06). (b) *Commit*: a data do Git, de 24/11/2026 para a S03 e 25/11/2026 para todos os demais. (c) *Homologação técnica*: o ato do responsável, registrado em 25/11/2026 (relógio do ambiente) para S03, S04, S05 e o complemento, e em 26/11/2026 para a S06, como já constava na seção 32. As datas "26/11" das seções 30 a 32 divergem da data dos commits (25/11) e foram mantidas por serem o registro histórico original, sem que isso altere o que o Git prova.

**Princípio.** Estar versionada (existir commit, inclusive na `main`) não equivale a estar homologada. A homologação técnica é um ato do responsável e consta abaixo porque foi dada.

### 33.1 Situação por etapa

| Etapa | Commits (data do Git) | Evidências | Estado do registro | Na `main` |
| --- | --- | --- | --- | --- |
| S03 | `cb2d765` (24/11/2026), com os refatoramentos de padrão `afa85bd`, `7959bc2`, `3d2bb8e` e `9e01157` | Seção 28: `CompanyCatalogTest` (36 casos), suíte, PostgreSQL 18 descartável, navegador em 1440, 390 e 320 px, `audit:check` e `organization:check` | **Concluída e homologada tecnicamente** | Sim |
| S04 | `8fad837` e `46009bc` (25/11/2026); ficha reformulada em `b4cb9fb` (25/11/2026) | Seção 29: `EmployeeDirectoryTest`, concorrência em PostgreSQL, navegador, fechamento técnico | **Concluída e homologada tecnicamente** | `8fad837` e `46009bc` sim; `b4cb9fb` ainda não |
| S05 | `c8a272e` (25/11/2026) | Seção 30: `EmployeeAssignmentTest`, concorrência em PostgreSQL, plano de consulta medido, navegador, `institutional:check` | **Concluída e homologada tecnicamente** | Sim |
| Complemento da S05 | `b0d14b5` (25/11/2026) | Seção 31: contatos por lotação, autoridade da principal, provas em PostgreSQL e navegador | **Concluído e homologado tecnicamente** | Sim |
| S06 | `458d147` (25/11/2026) | Seção 32: 391 testes, PostgreSQL, concorrência, FrankenPHP, navegador | **Concluída e homologada tecnicamente** | Ainda não |

### 33.2 Estado do Git e da integração

`main` e `origin/main` estão em `b0d14b5` e contêm S03, S04, S05 e o complemento. Fora da `main` há exatamente dois commits, em linha reta sobre `b0d14b5`: `b4cb9fb` (ficha reformulada do colaborador, tabela de vínculos e lotações em modal) e `458d147` (S06). A integração não foi feita e depende de autorização do responsável; a proposta, o diff e as verificações estão registrados na entrega correspondente.

### 33.3 Registro mantido

As Actions de `public_categories` têm segurança de contexto, mas a interface e o controle administrativo de permissões serão concluídos na S13 (seção 32.5).

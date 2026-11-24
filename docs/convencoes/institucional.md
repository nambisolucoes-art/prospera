# Institucional, equipe e sites

Contrato normativo da frente Institucional, Equipe e Sites White Label. A justificativa, as alternativas e o plano de etapas estão no [plano 002](../plano-de-acoes/002-institucional-equipe-sites-white-label.md) (DA2-01 a DA2-23 e PD2-01 a PD2-16). Em caso de divergência, vale esta convenção e o plano é corrigido.

Nada aqui está implementado ainda. Cada etapa (S03 a S15) implanta a parte indicada, e até lá nenhum código novo pode contrariar este contrato. Contexto geral em [Contexto organizacional](contexto-organizacional.md), [Pessoas e contatos](pessoas-e-contatos.md), [Cargos e permissões](permissoes.md), [Auditoria](auditoria.md), [Performance](performance.md) e [Checklist de módulos](checklist-modulos.md).

Esta frente **não** altera a árvore Conta, Empresa e Estabelecimento, **não** cria a entidade Marca e **não** cria outro sistema de autenticação ou de permissões. Empresas juridicamente distintas nunca são agrupadas por semelhança de nome.

## Conceitos

| Conceito | Definição | Não é |
| --- | --- | --- |
| Colaborador | Papel de Pessoa numa empresa (`employees`) | Usuário, nem cópia da Pessoa |
| Função | Atividade profissional exibível (`job_titles`), por empresa | Cargo. Cargo é papel de acesso (`roles`) |
| Departamento | Organização interna da empresa (`departments`) | Filial ou estabelecimento |
| Lotação | Período em que o colaborador atua num estabelecimento, com função e departamento (`employee_assignments`) | Vínculo de acesso |
| Perfil público | Autorização e apresentação opt-in do colaborador (`employee_public_profiles`) | Cadastro da pessoa |
| Seção institucional | Unidade de herança do perfil da empresa ou da unidade (`profile_sections`) | Campo avulso |
| Site | Entidade editorial de uma empresa, corporativo ou de uma unidade (`sites`) | Instalação por cliente |
| Ficha do diretório | Autoridade de publicação da empresa ou da unidade no diretório (`directory_listings`) | Site |
| Host público | Nome de host que identifica um site ou o diretório da conta (`public_hosts`) | Sessão ou tenant do app |

Na interface, a atividade profissional é **Função** (menu "Funções"). O termo Cargo fica reservado a papéis de acesso e permissões.

## Colaborador

- É um papel de Pessoa, no molde de Cliente e Fornecedor: uma relação por empresa, única em `(company_id, person_id)`, sem repetir identidade, documento, endereço ou contato central.
- Cadastrar reutiliza a Pessoa pelo documento completo, pela mesma busca de Clientes e Fornecedores. A resposta nunca revela dados de outra conta.
- Contatos profissionais da empresa ficam na relação (`work_email`, `work_phone`, `work_whatsapp`), com os campos de `ContactFields`. Nada é pré-preenchido nem sincronizado a partir da Pessoa.
- Inativar encerra as lotações abertas na data da operação e torna o perfil público inelegível. Reativar não reabre lotações antigas. Não há exclusão física.
- Salário, regime trabalhista e dados de RH ficam fora desta frente.

### Etiqueta especial derivada

- `tags.system_key = 'employee'`, módulo `people`, como as de Cliente e Fornecedor. É **derivada** da relação ativa: nunca é atribuída nem removida à mão.
- Aparece para quem tem `account.employees.view` cobrindo a empresa da relação. A partir da S05, também por lotação numa unidade coberta. Nunca revela relação com empresa não autorizada.
- Não é módulo comercial: não entra em `Tag::COMMERCIAL_MODULES` nem no cadastro de etiquetas convencionais, e `CommercialRole` não a inclui. Um registro único de papéis derivados substitui a derivação espalhada, sem segunda trilha.
- A estrela de etiqueta especial aparece somente na listagem de Etiquetas.

### Colaborador e usuário

- São independentes. Colaborador sem usuário e usuário sem colaborador são situações normais. Ter um não concede nem cria o outro.
- O vínculo é derivado de `account_memberships.person_id`, sem coluna nova. A ficha e o filtro mostram só **"Possui acesso à conta"**, sem cargo, escopo nem situação, e **somente a quem tem `account.users.list` em algum escopo**.
- Nenhum fluxo desta frente altera foto, e-mail de acesso, senha, segundo fator ou situação da identidade ([Identidade e vínculos](identidade-e-vinculos.md)). Conceder acesso continua sendo feito em Usuários.

## Lotações, vigência e histórico

1. `starts_on` é o primeiro dia e `ends_on` o último, ambos inclusivos, em `date`. Vigente em D: `starts_on <= D` e (`ends_on` nulo ou `D <= ends_on`). Uma lotação de um dia é válida.
2. "Hoje" é calculado no fuso IANA do estabelecimento da lotação (PD2-08). Até a S07 criar `establishments.timezone`, a S05 usa `America/Sao_Paulo` por configuração, o mesmo padrão da coluna.
3. Troca de função ou departamento em D encerra o período antigo em `D - 1` e abre o novo em D. Se o período aberto começou em D, a operação é **correção** do período, auditada, e não uma lotação nova.
4. Sobreposição é proibida para o mesmo colaborador e estabelecimento, inclusive com períodos futuros. Estabelecimentos distintos podem coexistir. No máximo uma lotação principal vigente.
5. Lotação retroativa exige `account.employees.assign` e fica auditada. Lotação futura só vale a partir de `starts_on` e fica inerte se o colaborador estiver inativo.
6. A Action abre `AuditLog::transaction()` e trava a linha de `employees` (`lockForUpdate()`) antes de verificar sobreposição e principal. `institutional:check` detecta o que depende de várias linhas.
7. **Expirar não é evento.** Quando `ends_on` passa, a lotação deixa de ser vigente por cálculo: sem escrita, sem auditoria, sem job. A elegibilidade é calculada na leitura e a resposta tem validade limitada (seção Cache).

## Função e departamento

- `departments` e `job_titles` pertencem à empresa, com nome único por empresa, ativos ou inativos, sem exclusão. Inativos não são escolhidos em lotações novas e permanecem nas antigas.
- Função e departamento acompanham o **período** da lotação, não o colaborador. O departamento nunca é filial.
- Contatos profissionais próprios da unidade ficam na lotação (`contact_mode` `inherit` ou `override`, com a mesma semântica de vazio intencional da herança). O contato efetivo é o da lotação aplicável e, se herdar, o da relação.

## Visão por empresa e estabelecimento

- **S04:** listagem **por empresa**, com filtros de situação, presença de acesso e busca por nome ou documento. Sem filtro por unidade, departamento ou função, sem coluna Unidades, sem `todos` e sem acesso de gestor de unidade, porque ainda não existe lotação.
- **S05:** coluna Unidades agregada sem N+1, consolidado `todos` (somente leitura) e filtros por unidade, departamento, função, vigência e perfil público. A pessoa com duas lotações aparece **uma vez**.
- **Gestor de unidade (alvo `establishment` para `list`, `view`, `assign` e `select-team`, DA2-18 e PD2-16):** o recurso avaliado é a lotação. Ele vê o nome, as lotações **da própria unidade** (vigentes e históricas) e o contato efetivo dela. Não vê lotações de outras unidades, dados da relação que a lotação não herde, perfil público nem dados centrais além do que Pessoas já concede.
- `create`, `update`, `activate`, `deactivate`, `edit-profile`, `publish-profile` e `consent` admitem só `company`.
- **Equipe da unidade (PD2-16).** O perfil público é corporativo e compartilhado, e o gestor de unidade não o altera. Com `account.employees.select-team` ele escolhe e ordena, na seção `team_display` da unidade, quem aparece na equipe da unidade, **só entre colaboradores já elegíveis** (perfil publicado pela empresa, lotação vigente na unidade, consentimento válido para foto). Usa a função e o contato profissional **da respectiva lotação**, e o contato só aparece se a empresa ligou `show_work_*`. Não altera dados centrais, não liga flags, não publica perfil nem registra consentimento, e nenhuma tela da unidade lê `people.*`. `team_display` guarda referências, não é editável por `account.institutional.update` e é editorial (rascunho e publicação).

## Herança e resolução

- A seção é a unidade de herança. Modos: `inherit` (valor sempre nulo), `override` (objeto válido, inclusive vazio intencional) e `disabled` (valor nulo, só em tipos que o permitem). A empresa só tem `override`; sem linha vale o padrão da plataforma.
- `null` em valor é rejeitado com 422. Campo ausente no PATCH significa "não alterar". Voltar a herdar grava `inherit`. Não há herança por campo: uma seção que precise dela é dividida em duas.
- O `ProfileResolver` (`scoped`) é a **única** porta de leitura de `profile_sections`. Devolve valor, origem e modo, no estado `draft` (editor e prévia) ou `published` (público e diretório). Em `draft`, a unidade em `inherit` vê o valor de trabalho da empresa. Em `published`, o publicado.
- **Contato pessoal nunca é herdado como empresarial.** Contatos públicos (`contacts`) são digitados como conteúdo próprio, nunca copiados de `establishments`, de Pessoas ou do cadastro de usuário.
- Endereço, situação e fuso da unidade ficam em `establishments`, lidos por Query pública com lista de campos permitidos. O endereço só é público com "Exibir endereço" ligada (padrão desligada).
- Horários: `hours_weekly` mais `hour_exceptions` por dono. Exceção de uma unidade nunca afeta outra. Exceção da empresa vale a quem herda `hours_weekly`; quem tem `override` só a recebe se ela declarar `applies_to_overrides`, com confirmação do impacto.

## Classes de dado e publicação

| Classe | Exemplos | Quando o público vê |
| --- | --- | --- |
| Editorial versionado | blocos, textos e SEO de página, tema e navegação, seções `about`, `logo`, `palette`, `typography`, `banners`, `gallery`, `seo_defaults`, `team_display` | Só após publicação explícita. Tem valor de trabalho e valor publicado |
| Operacional dinâmico | `contacts`, `social_links`, `hours_weekly`, `hour_exceptions`, endereço e situação da unidade, perfil público já publicado | Ao salvar. A tela avisa antes: "Esta alteração aparece nos sites publicados" |
| Retratação | revogar consentimento, inativar colaborador, encerrar lotação, inativar unidade, remover mídia, despublicar | Na hora, com prioridade |
| Exposição nova | ligar perfil público, publicar ficha, página ou site | Somente depois do ato, com permissão própria |

- Editar rascunho **nunca** altera a publicação atual. Nada novo é exposto sem ato explícito. Itens sem estado publicável não aparecem.
- **PD2-06:** editar e publicar são permissões distintas, e não há aprovação obrigatória em duas etapas.
- Publicar seção da empresa atinge as unidades em `inherit` e não toca as com `override`. O resumo de impacto mostra quantos sites e unidades são afetados e quantos `override` não são.
- Revisão de página: o conteúdo (`blocks`, `seo`, `schema_version`, `number`, `note`) é imutável depois de publicado. `site_pages.published_revision_id` é a autoridade. Reverter copia uma revisão antiga para um novo rascunho, e publicar é outro ato.
- **Despublicar** responde 404 neutro, nunca 410. Despublicar a `home` despublica o site. Slug renomeado responde 301.
- A retratação não depende de gancho: a leitura pública confere `active` de empresa e estabelecimento e a vigência antes de servir qualquer resposta, inclusive em cache.

### Perfil público do colaborador

- **Opt-in** por colaborador e por campo. O padrão é nada público. O perfil guarda autorização e apresentação, **não guarda contato**: e-mail e telefone exibidos vêm da lotação aplicável, com `show_work_email` e `show_work_phone`. Nunca de `people.*`, `additional_*` nem do e-mail de login.
- `edit-profile` cria e edita enquanto não publicado. `publish-profile` publica, despublica e edita o publicado. `consent` registra e revoga consentimento. Todas são `company`.
- Foto exige consentimento (data, versão do texto, quem registrou). Revogar é síncrono: apaga derivados, troca a versão e remove do diretório.
- Função exibida: `public_title`, se preenchido (vale em qualquer escopo), senão a função da lotação aplicável. Departamento só com `show_department`.
- **Lotação aplicável:** em site de unidade, a vigente na unidade; em bloco de uma unidade, a vigente nela; em bloco geral, a principal vigente, senão a vigente de menor `starts_on`, desempate por `id`. O colaborador aparece uma vez por bloco.
- Elegibilidade na leitura: colaborador ativo, lotação vigente no escopo, perfil publicado e, para foto, consentimento válido.

## Sites corporativos, locais e híbridos

- Site é de **empresa**, com `establishment_id` nulo (corporativo) ou preenchido (de unidade, da mesma empresa por FK composta). Uma empresa pode ter vários sites. Não há tabela polimórfica.
- Modos: **A** ficha no diretório sem site; **B** site corporativo com páginas de unidade (`kind = unit`); **C** site de unidade; **D** híbrido (B e C). Os quatro usam as mesmas tabelas e **nunca duplicam** dado de unidade: a página lê as seções e as colunas da unidade pelo resolvedor.
- Página de unidade só existe em site corporativo. Em site de unidade, `establishment_id` da página é nulo ou igual ao do site.
- A permissão avalia o nó do site: unidade avalia `establishment`, corporativo avalia `company`. Atribuição de unidade não alcança site corporativo nem outra unidade.
- Site nasce `draft`, com publicação desabilitada por padrão. Só fica `published` com a `home` publicada. Site de unidade cuja unidade é inativada sai do ar.
- Páginas de unidade não são geradas em massa, e página sem conteúdo mínimo fica `noindex`. `LocalBusiness` só com nome, endereço e telefone completos e coerentes com a ficha.
- Uma conta com uma empresa e um estabelecimento cria o site corporativo sem seletor nem passo extra.

## Diretório

- **Por conta** (PD2-04), com host próprio. Nada reúne contas. O diretório público é projeção opt-in de cada empresa, não compartilhamento entre empresas.
- `directory_listings` é a autoridade (`draft`, `published`, `unpublished`), uma por empresa e uma por estabelecimento, com `PublishDirectoryListingAction` e `UnpublishDirectoryListingAction`, `account.directory.publish` e `unpublish` no nó. **Não depende de página nem de site.**
- Nada é listado por padrão. Criar empresa ou unidade não lista ninguém. Publicar ou despublicar **site** não cria nem remove ficha: só preenche ou limpa o destino.
- Ficha de empresa: empresa ativa, nome e ao menos um contato publicado (a empresa não tem endereço). Ficha de unidade: unidade ativa, ficha da empresa publicada, endereço público e ao menos um contato publicado. Despublicar a empresa oculta as fichas das unidades.
- `directory_entries` é derivada, de propriedade do nó de origem, escrita só por `SyncDirectoryEntryAction`, a partir da ficha, das seções **publicadas** e do melhor destino publicado. É a única fonte de leitura pública do diretório. Não guarda documento, e-mail, telefone pessoal nem dados de pessoas.
- Agrupamento sempre por `company_id`. Busca insensível a acento e caixa sobre `search_text` normalizado em PHP. Índices só com plano medido.
- Categorias públicas em tabela própria `public_categories` (PD2-12).

## Contexto público e hosts

- Rotas públicas ficam em `routes/public.php`, **sem sessão, cookie nem CSRF**, só GET e HEAD, com limite de taxa. O domínio registrável dos sites gerenciados é **diferente** do app (PD2-02).
- `public_hosts` é a **única autoridade** de resolução de host: guarda o host de um site (`subject = site`) ou do diretório da conta (`subject = directory`), com `hostname` único global, inclusive revogados (quarentena). `sites.slug` é identificador editorial por conta e nunca resolve host. Uma só Query resolve; nenhum outro caminho existe.
- Host gerenciado: `active`, `suspended` ou `revoked`. Host próprio: `pending`, `verified`, `active`, `suspended` ou `revoked`. Só `active` é servido e autorizado pelo `ask` do TLS. Um site tem um host canônico; os demais respondem 301. A tabela nasce completa na S11; a S14 só habilita `kind = custom`.
- Host válido e conteúdo publicado são camadas distintas: sem as duas, o mesmo **404 neutro** vale para host desconhecido, suspenso, revogado, pendente, de outro tenant ou despublicado. O host do app e hosts reservados nunca existem na tabela, e o destino de uma entrada do diretório guarda site e página, nunca o host.
- `PublicContext` (`scoped`) é preenchido só pelo middleware de host e só é lido pelas Queries públicas. Para site, leva `account_id`, `company_id`, `site_id` e `establishment_id`. Para diretório, só `account_id`. Nenhuma Query pública aceita esses valores vindos da requisição. O contexto não concede permissão e **não substitui** `OrganizationContext`.
- Rotas com segredo no caminho entram no prefixo do `docker/Caddyfile` na mesma etapa (README). A prévia é somente autenticada no primeiro ciclo (PD2-09), dentro de `contas/{conta}/...`, com `noindex` e sem cache.
- Domínio próprio: prova de posse por TXT, validação contra SSRF, TLS sob demanda com `ask` restrito a host ativo, revalidação periódica. É a S14.

## Projeções públicas e allowlist

- O público nunca lê tabela privada nem serializa um Model. Cada resposta é um Resource com lista explícita de chaves e `schema_version`. Um teste varre os payloads e falha com chave proibida.
- **Nunca saem:** documento de pessoa, endereço e contatos pessoais, e-mail de login, IDs de usuário, vínculo, cargos de acesso, salário, situação trabalhista, observações internas, tokens, caminhos privados de arquivo. Estoque, preço e custo só por opt-in futuro.
- Blocos tipados com formatação restrita (PD2-15): sem HTML arbitrário, sem `iframe` de usuário, sem script, sem SVG. Referências a mídia, colaborador e unidade são validadas no escopo ao salvar. `products` e `services` são reservados e rejeitados.

## Privacidade e LGPD

- Exposição é opt-in. Colaborador inativo, sem lotação vigente no escopo ou sem consentimento perde a publicação **por política**, na leitura.
- Histórico privado é mantido. O texto e a base legal do consentimento exigem revisão jurídica (PD2-07). A revogação pela própria pessoa (`self.*`) não faz parte deste ciclo.
- Respostas sobre documento ou e-mail nunca revelam dados de outra conta.

## Permissões e alvos

Cada ação tem permissão própria com identificador literal. Os alvos admitidos ficam em `config/permissions.php`, e `permissions:sync` falha sem eles.

| Módulo | Ações | Alvos |
| --- | --- | --- |
| `account.departments`, `account.job-titles` | `list`, `view`, `create`, `update`, `activate`, `deactivate` | `company` |
| `account.employees` | `list`, `view`, `assign` | `company`; `establishment` a partir da S05 (`select-team` a partir da S09) |
| `account.employees` | `create`, `update`, `activate`, `deactivate`, `edit-profile`, `publish-profile`, `consent` | `company` |
| `account.employees` | `select-team` (equipe pública da unidade) | `company`; `establishment` pela lotação |
| `account.institutional` | `view`, `update`, `publish` | `company`, `establishment` |
| `account.media` | `list`, `create`, `update`, `delete` | `company`, `establishment` |
| `account.sites` | `list`, `view`, `create`, `update`, `preview`, `publish`, `unpublish`, `revert` | `company`, `establishment` |
| `account.domains` | `list`, `create`, `verify`, `delete` | `company`, `establishment` |
| `account.directory` | `view`, `update`, `publish`, `unpublish` | `company`, `establishment` |
| `account.directory` | `configure` (host e nome público do diretório) | `tenant` |

- O administrador integral da conta recebe as novas permissões `account`. Nenhum cargo de tenant recebe `platform.*`.
- Editor corporativo, gestor de unidade e editor de conteúdo **não** são cargos fixos: são combinações sugeridas, em cargos configuráveis.
- **Tentativa negada** responde 404 (ID de outro escopo) ou 403 e **não altera nada**. Não gera evento de negócio (convenção de Auditoria). Registrar tentativas depende de decisão futura da plataforma.
- A inspeção SaaS é somente leitura e bloqueia todas as escritas desta frente.

## Auditoria

- Models persistentes implementam `Auditable`, usam `AuditsChanges` e declaram `auditedFields()`. `content_version` é contador derivado e fica em `unauditedFields()` com essa justificativa. Tokens, imagens e o texto integral de blocos nunca entram no histórico: registra-se o fato e o identificador da revisão.
- O evento fica no **nó escrito**: empresa para empresa, estabelecimento para lotação, ficha e página de unidade. Uma operação que escreve vários nós usa o mesmo `operation_id` e um evento por nó. Publicar uma seção da empresa grava só a empresa; as unidades que herdam são afetadas por leitura, sem evento próprio, e o evento da empresa leva a contagem de afetados.
- Eventos explícitos sem Model: publicar, despublicar, reverter, verificar, ativar e revogar domínio, registrar e revogar consentimento, mudar seção para `inherit`, `override` ou `disabled`, publicar seções, publicar ficha. Rótulos em `config/audit.php`.
- Leitura pública, cache, prévia e **vigência que expira sem edição** não são operações auditáveis. Tarefas do agendador (S14) usam autoria de sistema.
- Não existe trilha paralela ao `AuditLog`. `php artisan audit:check` permanece verde a cada etapa.

## Cache e performance

- Chave de cache pública inclui `account_id`, o nó do site, `site_id`, `content_version` e `published_revision_id` (toda chave leva tenant e nó). Sem varredura: trocar a versão invalida. **Não há job de limpeza de cache.**
- **Frescor.** Resposta que depende de data tem `valid_until` igual ao **início do próximo dia civil no fuso** do estabelecimento envolvido (o menor, quando houver vários; calculado no fuso, não por 24 horas). A leitura **descarta** a entrada vencida independentemente do TTL do armazenamento. Sem `stale-while-revalidate` nem `stale-if-error`.
- **HTTP.** Páginas HTML usam `public, no-cache` com `ETag`: navegador e borda revalidam a cada acesso, e a revalidação confere `active` e vigência. Foto de pessoa usa `no-cache` com `ETag`, nunca `immutable`; revogada, responde 404. Só mídia imutável que não seja foto de pessoa tem cache longo.
- Revogação, inativação, encerramento por operação, remoção de mídia e despublicação trocam `content_version` na própria transação. A virada de data sem operação é coberta por `valid_until`.
- "Aberto agora" **nunca** vai no HTML em cache. Vem de `/api/publico/horarios`, com TTL de no máximo 60 s, como melhoria progressiva.
- `PublicContext` e `ProfileResolver` são `scoped`, sem estado estático e sem guardar `Request` ou `User`.
- Redis não é dependência automática (PD2-10): só se a medição com worker FrankenPHP real reprovar cache de banco, cache HTTP por versão e TTL.
- Sem N+1, com filtros, contagem e agregação no banco e Query com tenant e nó. Medição em PostgreSQL isolado e, para o público, com worker real. Índice só com consulta e plano medidos.

## Execução síncrona, assíncrona e periódica

- **S03 a S13 são síncronas por padrão e sem agendador.** Envio de mídia, derivados GD, revogação de consentimento, publicação, troca de versão, sincronização do diretório e remoção por evento acontecem na requisição, dentro da transação.
- **Mídia (S08):** o síncrono só vale com as barreiras antes de decodificar (bytes, tipo pelo conteúdo, megapixels, estimativa de memória), orçamento de tempo, atomicidade e concorrência medida. Arquivo acima do orçamento é recusado antes de decodificar, sem erro fatal.
- **A S14 é a primeira etapa que exige worker e agendador** (verificação de DNS por job e reteste periódico). Antes dela, nenhuma Action despacha job sem decisão registrada.
- Derivados de mídia nunca são gerados numa requisição pública. Limpeza de resíduos de mídia é comando manual até existir agendador.
- Se a medição reprovar o síncrono (S08 ou S13), **a própria etapa incorpora o fluxo assíncrono e o laboratório de worker** e só encerra com ele validado. Não se entrega funcionalidade com lacuna esperando a S14. Critérios de laboratório: plano 002, seção 19.1.
- Todo job leva `account_id`, `company_id`, `establishment_id`, `membership_id` e `actor_id` e reautoriza ao executar.

## Integridade

- Toda tabela carrega as colunas da sua classe, com FKs compostas `(company_id, account_id)` e `(establishment_id, company_id)`. Os alvos únicos novos estão no plano 002, seção 06.1. Tabelas de apoio (`media_variants`, `site_slug_redirects`, pivôs) também carregam `account_id` e `company_id`.
- Ponteiros circulares entre página e revisão são colunas anuláveis com FK composta, sem restrição adiável.
- Regras que dependem de várias linhas (sobreposição de lotações, uma principal vigente, coerência de `published_revision_id`, referências dentro de blocos) ficam em transação com trava e em `institutional:check`, somente leitura.

## Testes obrigatórios

Os do [Checklist de módulos](checklist-modulos.md), mais: leitura cruzada de rascunho e mídia entre tenants; payload público sem chave proibida; herança nos estados `draft` e `published`; vigência (adjacentes, mesmo dia, retroativa, futura, expiração sem evento); retratação com cache quente; host de um diretório que não serve outra conta; 404 idêntico para site inexistente, despublicado e de outro tenant; tentativa negada sem evento e sem alteração; nenhum job nas etapas S03 a S13.

## Decisões em aberto

PD2-02 (domínio gerenciado), PD2-03 (titularidade do GNAI2), PD2-05 (armazenamento e CDN), PD2-07 (consentimento), PD2-11 (limites por conta) e PD2-14 (worker e agendador). Nenhuma etapa decide sozinha uma delas.

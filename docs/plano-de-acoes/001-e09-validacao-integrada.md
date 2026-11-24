# E09: fechamento técnico e validação integrada

Estado: **Concluída, homologada formalmente em 23/11/2026** (seção 26 do plano oficial). Este relatório
registrou a validação Em validação e é preservado integralmente como evidência; a homologação foi
acrescentada ao final, sem substituir resultados anteriores. Data da validação: 23/11/2026.
Não foi iniciada a E10. Não houve commit, push, implantação ou uso de banco compartilhado.

## Retomada após a interrupção da sessão (23/11/2026, noite)

A sessão anterior foi interrompida depois de uma rodada de correções feita entre 16:44 e 17:10, posterior
às últimas evidências (16:38). Essa rodada vinculou o convite à identidade destinatária (`recipient_id`,
com FK e `check (accepted_by is null or accepted_by = recipient_id)`), endureceu a proteção do último
administrador e ajustou Actions, migration de convites e testes. Nada disso tinha sido revalidado.
Também foram encontrados: relatório citando 267 testes enquanto o último log salvo tinha 265, referência
a uma evidência inexistente (`admin-final-race`, o arquivo real é `admin-race`) e dois laboratórios ainda
em execução com código anterior à mudança. Os laboratórios antigos foram removidos e toda a validação
abaixo foi refeita sobre a árvore final. As evidências desta retomada estão em
[retomada](../evidencias/e09-2026-11-23/retomada/); as anteriores foram preservadas.

Lacunas encontradas e corrigidas na retomada:

- **Cargo sem tela inicial recebia 403 vazio.** Quem só tinha acesso à Central de Ajuda (inclusive logo
  após aceitar um convite com esse cargo) era levado a `/contas/{conta}` e recebia 403 sem mensagem.
  `ClientController::accountDashboard` passa a usar a Ajuda da conta como última tela alternativa.
- **Limite de leitura de convites em inglês.** Ao atingir o limitador (20 leituras por minuto por
  identidade; cada abertura direta conta duas), a pessoa via a página padrão "Too Many Requests".
  `bootstrap/app.php` passa a responder 429 com a mensagem "Muitas tentativas em pouco tempo. Aguarde um
  minuto e tente novamente.", mantendo `Retry-After`. Na abertura direta, a aplicação é entregue e
  mostra a mensagem no painel de erro existente. Vale para todos os limitadores HTTP, sem alterar limites.
- **Cobertura automatizada.** `AccountIsolationTest` (6 testes) cobre o que antes só tinha prova de
  laboratório: mesma identidade administradora em A e operadora em B na mesma sessão, com permissões,
  etiquetas, 404 entre contas e autoria da auditoria em cada conta; estados de vínculo e conta (ativo,
  suspenso, inativo, inexistente, alheio, nenhum utilizável); inspeção presa à conta inspecionada mesmo
  quando o representado tem outra conta; emissão neutra pela tela de Usuários, token só como SHA-256,
  e-mail entregue ao destinatário, substituição do pendente e flag desligada; revogação restrita à conta
  da URL; e o limite de leitura em português.
- **Laboratório.** `e09-lab.sh` aceita `E09_METRICS=1` para injetar `OctaneMetrics` só na cópia do
  bootstrap dentro do container. `e09-ui-fixtures.php` cria uma identidade com cargo só de Ajuda.
  `tests/Browser/e09-limits.mjs` verifica esse cargo e o limite de convites nas cinco larguras.
  `e09-invite.mjs` registra tela e URL quando falha e confere que o aviso de aceite assentado cabe em
  320 px antes de fotografar o menu do avatar.

Ocorrências na retomada, não tratadas como sucesso: a primeira execução de `e09-invite.mjs` falhou por
429 (o limitador funcionando, mas com a página em inglês, corrigida acima); a captura do avatar em 320 px
mostrou o aviso cortado, e a medição provou que era a animação de entrada (x=177 no início, x=12 a 308
depois de assentado), sem defeito do componente; o ensaio de dois workers observou três workers na
terceira rodada porque o worker que atendeu a maior parte das 20.656 requisições atingiu
`--max-requests=20000` e foi reciclado, sem erro no log; comandos executados pelo zsh com o comando em
variável não rodaram e foram repetidos com bash antes de qualquer conclusão.

## Diagnóstico e Git

Branch inicial e final: `feat/filtros-calendario-titulos`.
HEAD inicial e final: `48e2191fbae65df07cd0302eef431bbb290ee4ef`.
A árvore já continha a implementação da E09 sem commit. Foram preservados seus arquivos e as demais
alterações existentes. O inventário inicial está em
[git-initial.txt](../evidencias/e09-2026-11-23/git-initial.txt), e o final acompanha as evidências.
Os resultados do checkpoint anterior eram uma linha de base, não uma homologação.

Reaproveitados: vínculos e RBAC da E05, Organização e preferências da E06, relações comerciais da E07,
AuditLog e inspeção da E08, resolução da conta pela URL, código público, migrations, seleção, convites,
Fortify, componentes UBold e infraestrutura de navegação já presentes.

Lacunas corrigidas nesta validação:

- Bloqueio de operação no tenant enquanto a senha inicial estiver pendente.
- Revalidação da identidade sob trava e do nó ativo no aceite de convite.
- Reautorização de criação de identidade, emissão e revogação depois da trava da conta.
- Proteção do último administrador considerando identidade habilitada, verificação e senha definida.
- Filtro de convites inteiramente no SQL, antes do limite.
- Seleção das contas em uma consulta, incluindo os nomes dos cargos.
- Códigos com sufixo numeroso respeitando 40 caracteres, inclusive após 999 colisões.
- Erro de colisão concorrente de código identificado no campo correto.
- Limitadores independentes para leitura e aceite de convite.
- Registro explícito de envio no AuditLog, sem token nem conteúdo de e-mail.
- Tutorial restabelecido nas URLs com código de conta, com ajuda contextual e artigo de acesso global.
- Listagem compacta de contas com Abrir conta inteiro em 320 px.

## PostgreSQL 18 e migrations

Laboratório: PostgreSQL 18.7 em tmpfs, PHP 8.5.13, Laravel Octane com FrankenPHP, imagem local
`prospera-app:latest`, código copiado de uma montagem somente leitura. Chave, senha, dados e e-mails
são sintéticos. Nenhum comando foi executado dentro dos containers oficiais.

A instalação limpa aplicou todas as migrations, inclusive as três da E09. A evolução foi executada
sobre o esquema completo anterior, com identidade, conta, pessoa, vínculo, atribuição e evento de
auditoria existentes. Os IDs e as relações foram preservados. Contas com nomes duplicados e acentos
receberam códigos distintos. O teste adicional compara hashes dos vínculos, atribuições e histórico
antes e depois do preenchimento dos códigos.

**`users.account_id` está ausente nos esquemas finais descartáveis. A identidade global não depende
dessa coluna no código de aplicação. O banco oficial não foi migrado nem alterado.** Referências em
migrations históricas e relatórios são necessárias à evolução e não representam dependência de execução.

Constraints testadas diretamente: imutabilidade e formato (23514), unicidade (23505), palavras
reservadas (23514), cargo de outro tenant (23503). As FKs compostas de cargo, pessoa, empresa e unidade,
o índice único do hash e o índice único parcial de convite pendente foram inspecionados no PostgreSQL.
Não se depende só de validações preventivas em PHP.

Reversão: com um vínculo, a migration reconstruiu a coluna e sua referência correta, depois reaplicou
a contração. Com vários vínculos, abortou antes de modificar o esquema. Nenhuma conta foi escolhida
arbitrariamente. Também foi feito dump do esquema anterior e restauração em outro banco descartável,
seguida de reaplicação da E09, preservando os registros sintéticos. Depois de múltiplos vínculos, a
recuperação preferencial é correção para frente. Restauração anterior exige aprovação explícita sobre
a perda das alterações posteriores. O ensaio sintético não substitui o backup da futura implantação.

Retomada, sobre a árvore final: o código do HEAD `48e2191` (pré-E09) criou esquema e dados em PostgreSQL
18 descartável (contas com nomes duplicados e acentuados, conta chamada `todos`, conta inativa,
identidades com `users.account_id`, vínculos, atribuições e 42 eventos de auditoria). Depois, o código
atual aplicou as três migrations. Vínculos, atribuições, auditoria, pessoas, cargos, `permission_role`
e identidades (id, e-mail e senha) ficaram idênticos por SHA-256 antes, depois, após reversão e após
reaplicação. A reversão com vínculos únicos recriou `users_account_id_foreign` e os mesmos valores
originais. Com uma identidade em duas contas, a reversão saiu com código 1 e a coluna continuou ausente.
O dump do esquema anterior, restaurado em outro banco, evoluiu com os mesmos códigos
(`arvore-acao-matriz`, `arvore-acao`, `todos-conta`). Constraints refeitas: imutável, formato e
reservado (23514), único (23505), destinatário (23514) e cargo de outra conta (23503).
[Evolução](../evidencias/e09-2026-11-23/retomada/), arquivos `evolucao-*` e `postgres-esquema-final.txt`.

Evidências da primeira rodada: [instalação limpa](../evidencias/e09-2026-11-23/migrations-clean-final.log),
[evolução](../evidencias/e09-2026-11-23/previous-schema.log),
[hashes preservados](../evidencias/e09-2026-11-23/migration-evolution.json),
[constraints](../evidencias/e09-2026-11-23/constraints.json),
[backup e recuperação](../evidencias/e09-2026-11-23/backup-recovery.log).

## Matriz de requisitos e isolamento

| Requisito | Cobertura automatizada e ensaio | Evidência |
| --- | --- | --- |
| Uma conta, duas e cinco contas | MultipleAccountsTest, login real e Chromium | integration-browser.json, login-performance.json |
| Sem vínculo ativo, suspenso, revogado, conta inativa ou inexistente | AccountIsolationTest, OrganizationContextTest, AccountInspectionTest e Chromium | states-browser.json e imagens state |
| Administrador em A e operador em B | AccountIsolationTest, alternância HTTP e duas abas no mesmo contexto Chromium | octane-final-2.json, tabs-ui.json, retomada/browser-main.json |
| Conta explícita na URL e troca sem novo login | Matriz de seleção e menu do avatar | selection-final.json, invite-browser-final.json |
| Legado GET/HEAD seguro e escrita recusada | Teste explícito com URL absoluta, sem conversão do helper de testes | MultipleAccountsTest |
| Conta, permissões e Pessoas isoladas | Testes existentes de acesso, diretório e relações comerciais, alternância HTTP e abas | suíte PHP, integration-browser.json |
| Filtros, paginação, ficha, formulário e modal | API com search/page, paginação real da lista, visualização e gravação pelo modal em A; B sem cadastrar | integration-browser.json, tabs-ui.json |
| Recarregamento e histórico | Duas páginas no mesmo contexto de navegador, reload e goBack | tabs-ui.json |
| Área de Trabalho e preferência por usuário e conta | Seleção de duas unidades em A e padrão independente de B | workspace-browser-settled.json |
| Preferência preservada durante inspeção | Escrita da preferência recusada por HTTP e testes E06 existentes | octane-audit.json, CommercialRelationWorkspaceTest |
| Convite pendente sem acesso e destinatário correto | MultipleAccountsTest, emissão em transporte em memória e Chromium | email-final.json, invite-browser-final.json |
| Expiração, revogação e reutilização | Testes específicos e Chromium com o destinatário real do convite expirado | expired-browser-final.json, invite-browser-final.json |
| Aceites e emissões concorrentes | Processos PHP independentes e PostgreSQL real | accept-race e issue-race |
| Revogação concorrente com aceite | Revogador segura a trava; aceite aguarda e recebe 410 | revoke-race.json, revoke-accept-race.json |
| Códigos concorrentes | Duas Actions independentes com mesmo nome e documentos distintos | code-final-race e code-final-state.txt |
| Revalidação de nova concessão | Permissão retirada depois da memoização não autoriza criar identidade ou vínculo sob a trava | MultipleAccountsTest |
| Permissões simultâneas | Duas alterações do mesmo cargo via SaveRoleAction, serializadas; estado final completo, sem união indevida | permission-race |
| Último administrador | Duas suspensões concorrentes e teste de substituto pendente ou não verificado, preservando administrador utilizável | admin-race, retomada/corrida-admin e InitialInvitationSecurityTest |
| Suspensão durante escrita | Escrita autorizada em voo termina; suspensão é concluída; requisição seguinte recebe 403 | inflight-write.json, inflight-suspend.json |
| Fortify e identidade nova | Expiração, destinatário incorreto, uso único e suspensão preservada depois do reset | InitialInvitationSecurityTest |
| Flag ligada e desligada | Matriz de 1/2/5 contas, aceite bloqueado e HTTP real com worker iniciado com flag false | MultipleAccountsTest, flag-off-http-final.json |
| Estado persistente e revogação seguinte | Alternância sustentada, mudança de permissão sem reinício e sessões distintas | octane-endurance-1.json, octane-final-2.json |
| Autoria normal e inspeção | Escrita HTTP, consulta independente do AuditLog e checagem de autor, representado, vínculo e inspection_id | octane-audit.json |
| Troca indevida de tenant na inspeção | AccountIsolationTest e HTTP: inspeção em A recebe 404 ao trocar a URL para B, inclusive quando o representado também participa de B; sessão normal segue válida | octane-audit.json, retomada/octane-*.json |
| Emissão neutra, hash do token, e-mail e substituição | AccountIsolationTest pela tela de Usuários e corrida de emissão | retomada/corrida-issue-*.json |
| Destinatário preso à identidade | MultipleAccountsTest, constraint `recipient-match` (23514) e prova CLI | retomada/corrida-recipient.json, retomada/corrida-constraints.json |
| Cargo só com Ajuda e limite de convites | AccountIsolationTest e Chromium nas cinco larguras | retomada/browser-limits.json, imagens help-only e rate-limit |
| Ajuda, tours e larguras | Chromium, abertura e Escape, artigo global e links contextuais | workspace-browser-settled.json, imagens tour |

Os arquivos citados por nome estão em [evidências](../evidencias/e09-2026-11-23/).
As provas de concorrência usam banco e processos independentes, fora do PHPUnit. O PHPUnit continua
obrigatoriamente em SQLite em memória, inclusive herdando variáveis PostgreSQL.

## Convites e Fortify

Identidade existente: emissão com resposta neutra; token aleatório de 64 caracteres, persistido só
como SHA-256; validade padrão de sete dias; uso único; revogação e substituição do pendente anterior.
A conta e a identidade são revalidadas sob trava, e a empresa/unidade precisam continuar ativas.
Um vínculo existente, inclusive suspenso, impede nova concessão por convite. A pessoa interna é criada
no aceite se não houver pessoa previamente indicada da mesma conta. Não se duplicam Pessoas entre
empresas e não se expõem vínculos de outra conta.

No aceite concorrente, houve exatamente um vínculo e um aceite; a outra tentativa recebeu 410. Na
emissão concorrente, restaram um pendente e um revogado. Quando a revogação obteve a trava primeiro,
o aceite não concedeu vínculo. A alteração de identidade durante a espera é observada pelo registro
travado. Criação, envio, aceite, revogação, vínculo e atribuição usam o AuditLog contextual.

Identidade nova: Fortify continua sendo a única autenticação e o único broker de senha. A identidade
provisionada com convite tem senha aleatória e `password_setup_pending`; o vínculo não é utilizável
até a conclusão segura da definição de senha. O reset tem validade de 60 minutos, exige destinatário
e token correspondentes e não permite reutilização. Senha definida não reativa vínculo suspenso,
conta inativa ou identidade desabilitada. Os testes também verificam o encerramento da sessão antiga.
Convite organizacional não é substituído por recuperação de identidade existente.

E-mails foram entregues exclusivamente ao ArrayTransport em memória e aos fakes dos testes, sem SMTP
nem destinatários reais. Esse transporte é o mecanismo equivalente ao Mailpit neste laboratório.

## Flag e implantação

`config/prospera.php` tem padrão false. O exemplo e o Compose de desenvolvimento ligam a chave; a suíte
também a liga. Na implantação, defina explicitamente `PROSPERA_MULTIPLE_ACCOUNTS=false` até liberar novas
associações. Resolução, seleção, autorização e vínculos existentes não dependem da liberação. Desligar
não elimina vínculos nem permissões, não elege uma conta para escrita e bloqueia novos aceites.

A implantação futura exige backup verificado, janela de migrations e reinício controlado dos workers,
com novo login das sessões anteriores à contração. Nenhuma implantação foi feita nesta tarefa.

## Chromium e experiência visual

Larguras: **1440, 1024, 768, 390 e 320 px**. Foram verificados login e redirecionamento, seleção,
conta sem acesso, convite válido, expirado, revogado e reutilizado, aceite concluído, avatar, troca de
conta, menu da Área de Trabalho, ajuda, tour e estados de erro. Também houve paginação, ficha, modal,
gravação autorizada, recusa de escrita em B, reload e histórico em duas abas autenticadas pela mesma
identidade. Não houve erro JavaScript não tratado nem rolagem horizontal da página nos cenários medidos.

A conferência de acessibilidade básica usou nomes acessíveis, navegação dos controles e Escape para
fechar menus, modais e tour. Não substitui uma auditoria completa com leitores de tela. As capturas dos
menus finais aguardam a animação antes de fotografar. As duas contas preservaram permissões e padrões
independentes. A lista compacta foi corrigida sem alterar preferências já salvas ou criar outro componente.

Exemplos: [seleção 320 px](../evidencias/e09-2026-11-23/selection-final-320.png),
[convite 320 px](../evidencias/e09-2026-11-23/invite-valid-320.png),
[Área de Trabalho 320 px](../evidencias/e09-2026-11-23/workspace-320.png),
[tour 320 px](../evidencias/e09-2026-11-23/tour-320.png).

## Performance e limites das medições

Não foram comparados endpoints funcionalmente diferentes como equivalentes. Antes da otimização,
a seleção executava duas consultas próprias; depois, uma, comprovada no teste e por uma query agregada
no PostgreSQL. No mesmo endpoint de seleção com dois vínculos, o total HTTP medido passou de 8/9 para
7/8 queries, incluindo sessão e montagem da resposta. Latências são indicativas: o aquecimento, a duração
e a carga do host variam. Não se atribui à otimização uma garantia de ganho em milissegundos.

Ensaio refeito na retomada com **6.000 requisições amostradas por configuração**, um worker e dois
workers persistentes, mais requisições de controle, autenticação, negações, revogação e inspeção. Zero
respostas inesperadas, `e09=passed` e exit code zero nas três rodadas de dois workers. Nos seis fluxos
de negócio não houve assinatura SQL repetida. Memória no início da requisição: um worker, 8,4 MB a
9,7 MB. Dois workers: subiu a 16,6 MB e voltou a 9,7 MB dentro da mesma rodada (ciclo do coletor), e o
worker reciclado por `--max-requests` recomeçou em 2,7 MB. Não há crescimento contínuo.

Dois workers, retomada (código final):

| Fluxo | Amostras | Mediana ms | P95 ms | Queries totais | Duplicatas máximas |
| --- | --- | --- | --- | --- | --- |
| `/contas/laboratorio-conta-1/etiquetas` | 1000 | 12.10 | 14.19 | 11, 12 | 0 |
| `/contas/laboratorio-conta-2/etiquetas?search=E09&page=2` | 1000 | 10.01 | 11.77 | 9, 10 | 0 |
| `/contas` | 1000 | 7.68 | 9.07 | 7, 8 | 0 |
| `/contas/laboratorio-conta-1/area-de-trabalho/padrao` | 1000 | 8.16 | 9.85 | 8, 9 | 0 |
| `/contas/laboratorio-conta-2/area-de-trabalho/padrao` | 1000 | 7.59 | 9.03 | 7, 8 | 0 |
| `/contas/laboratorio-conta-1/usuarios` | 1000 | 14.85 | 17.64 | 17, 18 | 0 |

Usuários passou de 15/16 para 17/18 queries porque o laboratório da retomada tem um convite pendente na
conta 1: a listagem de convites (uma consulta) e seus cargos (uma consulta em lote). O custo depende de
haver convites, não do número deles; não é N+1. As consultas foram listadas com `DB::listen` no
laboratório. Etiquetas e Área de Trabalho da conta 1 aparecem com uma consulta a mais que na tabela da primeira
rodada; os mesmos valores (11/12 e 8/9) já constavam em `flag-off-http-final.json`, com outro estado de
dados. Não há duplicatas, e a origem exata dessa consulta não foi isolada nesta retomada.

Login real, retomada: dez identidades sintéticas por perfil (uma, duas e cinco contas), sem desativar
throttling. Medianas do POST: 14,00, 13,53 e 13,36 ms; P95: 30,68, 14,30 e 16,91 ms (o P95 de uma conta
inclui a primeira amostra fria do laboratório). Queries do POST: 11, 10/11 e 10. O fluxo completo teve quatro
passos com conta única e três com múltiplas contas, portanto suas latências não são equivalentes.
A consulta de identidade repetida uma vez vem do pipeline Fortify, que verifica autenticação/2FA e
então autentica. O custo não cresce com o número de vínculos. Não foi acrescentado cache de autorização.

Convite: dez leituras, mediana 15,71 ms, P95 17,45 ms e nove queries totais (primeira rodada). Na
retomada, com o destinatário preso à identidade, dez aceites HTTP por dez identidades existentes
distintas tiveram mediana 25,50 ms, P95 44,10 ms e 34 queries, pico incremental máximo de 819.632 bytes
e zero respostas inesperadas. Foram confirmados
os dez vínculos corretos e suas pessoas próprias no PostgreSQL. A emissão foi medida separadamente
na fronteira da Action em CLI, com PostgreSQL e transporte em memória: dez amostras, mediana 10,72 ms,
P95 24,27 ms e 16/17 queries (retomada). Esse custo não é comparado ao HTTP, que inclui sessão, CSRF e navegação.

As duas assinaturas repetidas no aceite são a consulta antes/depois das etiquetas e a consulta de
consolidação de eventos no AuditLog, que observam estados diferentes e preservam a E08. Não são N+1.
A concorrência usa processos independentes; as dez amostras de latência não são teste de capacidade
de um provedor de e-mail.

Inspeção (retomada, dois workers): dez ciclos, medianas de início/leitura/encerramento
10,19/12,58/10,44 ms, P95 22,67/14,62/12,76 ms e 13/14/14 queries. O encerramento contém uma assinatura repetida de revalidação.
As atribuições e os dados do vínculo são reconsultados a cada requisição, sem cache entre workers.
Planos EXPLAIN ANALYZE BUFFERS estão versionados; com poucos tenants sintéticos, varredura sequencial
é esperada e não motivou índice especulativo. Os índices únicos novos existem para integridade.

Os números usam PostgreSQL isolado, HTTP local e bcrypt com custo 4. Não são SLA de produção, ensaio
comercial, teste de rede externa, auditoria completa de acessibilidade nem comparação com outro hardware.
Não foram acrescentados Redis, Swoole, cache persistente ou outra infraestrutura de aplicação.

## Checks finais e evidências

- PHPUnit completo: **274 testes, 3.748 asserções**, sem falhas, em container sem rede herdando
  `DB_CONNECTION=pgsql` (o bootstrap impõe SQLite em memória).
- Específicos da E09 (`MultipleAccountsTest`, `InitialInvitationSecurityTest`, `AccountIsolationTest`):
  **18 testes, 390 asserções**, sem falhas.
- Frontend: **20 testes**, sem falhas.
- Arquitetura, TypeScript, build, PHPStan, Pint e `git diff --check`: aprovados.
- Logs finais: [backend](../evidencias/e09-2026-11-23/retomada/final-backend.log) e
  [frontend](../evidencias/e09-2026-11-23/retomada/final-frontend.log).
- `permissions:sync`: 82 permissões descobertas, sem remoção de vínculos ou permissões.
- `audit:check`: 13 modelos de negócio cobertos.
- `organization:check`: nenhuma violação estrutural. Avisos fiscais dizem respeito exclusivamente
  às empresas sintéticas criadas com unidade sem inscrição fiscal no ensaio de colisão.
- Instalação limpa, evolução, constraints, rollback condicionado e restauração PostgreSQL: aprovados.
- Ensaios de concorrência, HTTP persistente e Chromium: resultados e capturas versionados. Na retomada,
  todos foram repetidos com o código final: aceite duplo (um aceito, outro 410), emissão dupla (um
  pendente, um revogado), códigos (`colisao-concorrente` e `-2`), revogação com trava antes do aceite
  (410), cargo concorrente (conjunto completo de uma gravação), último administrador (um suspenso, um
  protegido), concessão após revogação de permissão (negada, identidade não criada) e suspensão em voo
  (escrita autorizada concluída, requisição seguinte 403). No banco: zero pendentes duplicados, zero
  vínculos duplicados e zero convites aceitos sem vínculo.
- Flag desligada sob HTTP real: dois vínculos abrem as duas contas, seleção sem escolha automática,
  aceite 403 com convite ainda pendente e emissão sem novo convite. Controle com a flag ligada para a
  mesma identidade: exatamente um convite emitido.
- Chromium na retomada: `e09.mjs` (20 medições de largura, nenhuma rolagem horizontal), Área de
  Trabalho, duas abas, convite válido/aceite/reutilização/troca pelo avatar e `e09-limits.mjs`, nas
  cinco larguras e sem erro JavaScript.

Os scripts `tests/Performance/e09-*` e `tests/Browser/e09*` mantêm a preparação e as verificações
reutilizáveis. O README de Performance documenta dependências, execução e limitações de repetição.

Ocorrências durante a preparação não foram consideradas sucesso: PHP do host sem SQLite, ensaio
inicial sem APP_KEY e suíte indevidamente instrumentada foram substituídos por containers de teste
limpos, sem rede, com SQLite em memória e chave sintética. O `migrate:fresh` sobre um banco descartável
já usado encontrou funções de trigger da E08 remanescentes; instalação/evolução foram repetidas em
bancos efetivamente novos, sem alterar a E08 ou qualquer base oficial. A automação de navegador teve
seletores corrigidos (avatar é um link), e as falhas reais de limite, tutorial e botão foram corrigidas
e repetidas. A elevação inicial de memória motivou o ensaio prolongado, que comprovou retorno por coleta.

## Entrega e operação

Arquivos alterados ou criados na retomada: `app/Domains/Plataforma/UI/Controllers/ClientController.php`
(Ajuda como tela alternativa), `bootstrap/app.php` (429 em português), `tests/Feature/AccountIsolationTest.php`
(novo), `tests/Performance/e09-lab.sh` (`E09_METRICS`), `tests/Performance/e09-ui-fixtures.php`
(identidade só com Ajuda), `tests/Browser/e09-limits.mjs` (novo), `tests/Browser/e09-invite.mjs`
(diagnóstico de falha e aviso assentado), `tests/Performance/README.md`, `docs/ajuda/acesso-contas.md`,
este relatório, a linha da E09 no plano oficial e `docs/evidencias/e09-2026-11-23/retomada/`. As
alterações da rodada interrompida (16:44 a 17:10) foram preservadas e validadas, sem reimplementação.

E09 permanece **Em validação** para homologação formal. A árvore de trabalho continua sem commit.
O inventário final discrimina arquivos existentes e novos; não houve staging, push ou limpeza de dados
reais. Os laboratórios foram preparados com nomes exclusivos, sem Compose oficial, sem volumes
compartilhados e sem SMTP. A ajuda e os tours estão integrados, sem integração pendente dessas telas.

Restam a homologação do usuário e os passos operacionais da implantação: backup da base que será
implantada, janela, política de liberação da flag, credenciais reais do provedor de e-mail e reinício
controlado. Esses passos não foram executados. O ensaio de laboratório não autoriza executá-los.

## Homologação formal (23/11/2026)

O usuário autorizou a conferência de homologação. Antes do encerramento, foi identificada a consulta
adicional de Etiquetas e Área de Trabalho da conta 1: é a leitura do padrão pessoal da Área de Trabalho
(`account_memberships.workspace_establishments`) por `OrganizationContext::defaultCodes`, código da E06
sem alteração pela E09. Ela só existe quando a pessoa tem mais de uma unidade navegável. A linha de base
de 16:00 foi medida antes de os fixtures ganharem a "Unidade adicional" na conta 1. Com essa unidade
inativa no laboratório, as telas caem de 10 e 7 para 9 e 6 consultas; reativada, voltam a 10 e 7. É uma
alteração legítima do fluxo, dependente do dado, não redundante e não introduzida pela E09; nada foi
otimizado. Evidências em [homologação](../evidencias/e09-2026-11-23/homologacao/).

Testes repetidos na conferência: PHPUnit completo 274 testes e 3.748 asserções, E09 18 testes e 390
asserções, contexto organizacional, Área de Trabalho e Pessoas 40 testes e 629 asserções, frontend 20
testes, build, tipos, PHPStan e Pint aprovados. A separação entre homologação técnica e implantação, as
limitações aceitas e as pendências operacionais estão na seção 26 do plano oficial.

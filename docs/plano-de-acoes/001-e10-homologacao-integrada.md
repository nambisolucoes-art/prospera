# E10: homologação integrada final da arquitetura organizacional

Estado: **E10 Concluída e homologada tecnicamente em 24/11/2026**, incluindo a correção dos logs de acesso (seção O).
Os estados anteriores (Em validação, aguardando homologação formal; pronta para homologação formal) são checkpoints
históricos, preservados nas seções abaixo. Data da execução: 24/11/2026. A homologação ocorreu em laboratório e não é
implantação (seção P).
Não houve commit, push, implantação, alteração de containers oficiais ou uso de banco compartilhado.
As evidências estão em [evidencias/e10-2026-11-24](../evidencias/e10-2026-11-24/).

## A. Estado inicial

- Branch `feat/filtros-calendario-titulos`, sincronizada com `origin` (0 commits à frente ou atrás), árvore limpa,
  nada em staging nem stash.
- HEAD de referência: `af96f3e` (feat: adiciona seeders para o sistema), posterior a `cd4305f`, que contém a E09
  homologada. O `af96f3e` acrescentou os seeders de desenvolvimento, `DevelopmentSeedTest`, o uso de
  `useContextUrl` em `PersonPreview.tsx` e `create or replace` nas funções de gatilho de duas migrations
  (`protect_committed_audit_history` e `add_code_to_clients`). Essas mudanças foram exercitadas pelo Cenário B.
- 39 migrations; E01 a E09 concluídas e homologadas (seção 26 do plano).
- Linha de base, antes de qualquer alteração: PHPUnit **277 testes e 3.777 asserções** (os 274 da E09 mais os 3 de
  `DevelopmentSeedTest`), frontend 20 testes, arquitetura e tipos, PHPStan e Pint aprovados
  ([linha de base](../evidencias/e10-2026-11-24/baseline-phpunit.log)).
- Laboratórios e scripts reaproveitados: `e09-lab.sh` (modelo do laboratório), `OctaneMetrics`,
  `organization-http.php` (modelo do cliente HTTP), `e08-volume.sql`, `e08-plans.sql`, `e09-route-queries.php`
  (modelo da listagem de SQL por requisição) e os roteiros de navegador da E09 (seletores).

## B. Laboratório

| Item | Versão ou recurso |
| --- | --- |
| PHP | 8.5.13 (ZTS), imagem local `prospera-app:latest` |
| FrankenPHP | 1.14.0, Laravel Octane, 1 e 2 workers, `--max-requests=20000` |
| PostgreSQL | 18 (imagem `postgres:18`), tmpfs |
| E-mail | Mailpit v1.27 em rede interna, sem relay, só no laboratório |
| Navegador | Chromium (Chrome for Testing 148) com Playwright 1.65 instalado em `/tmp`, fora do projeto |
| PHPUnit | 12.5, container sem rede, SQLite em memória imposto por `tests/bootstrap.php` |

Isolamento: prefixo `prospera-e10-lab-*`, rótulo `prospera.lab=e10`, rede interna para banco e e-mail, porta só em
`127.0.0.1:18110`, código copiado de montagem somente leitura, métricas injetadas apenas na cópia do bootstrap
dentro do container. Nenhum `docker compose`, volume ou container oficial foi usado. Ao final, `e10-lab.sh down`
removeu apenas os recursos com o rótulo `e10`; os containers `prospera-*` continuaram em execução e intocados.

A massa não vem de fixtures: o único fixture cria dois administradores SaaS. Contas, empresas, estabelecimentos,
cargos, usuários, convites, clientes, fornecedores, etiquetas e auditoria nascem pelas jornadas HTTP, com login,
CSRF, validação, autorização e auditoria reais. Os e-mails de convite e de definição de senha são lidos do Mailpit.

### Massa sintética resultante

| Conta | Estrutura | Pessoas e acesso |
| --- | --- | --- |
| A, Grupo Gartan (contratante PJ, cadastro manual) | Empresa A1 (raiz numérica): matriz, filiais Guanambi e Brumado, filial Guanambi promovida a principal. Empresa A2 (raiz alfanumérica `G7RT4N2B`): filial principal de ordem 0001 sem matriz, filial adicional | Ana (administradora integral), Henrique (segundo administrador), Carlos (Vendas A na empresa A1 e Gerente de unidade em Brumado), Consulta (somente leitura na conta), Unidade (gerente só de Brumado), Logística (cargo restrito à A2), Suspenso, Sem vínculo ativo, Somente Ajuda |
| B, Outra Organização (contratante PJ, automático, matriz) | Empresa própria com matriz e filial; empresa homônima com a mesma raiz da A2 | Bruno (administrador por convite e Fortify), Olga (operadora de uma unidade), Ana (operadora por convite), identidade nova por convite |
| C, Filial Única (automático, filial) | Uma empresa cuja única unidade é filial e principal, sem matriz | Carla (administradora), Ana (administradora por convite) |
| D, Diana Ferraz (contratante PF, automático) | Empresa individual pelo CPF, sem classificação fiscal | Diana |

Plataforma: `platform@e10.test` e um segundo inspetor usado na perda de autoridade. A Ana termina com três vínculos
(administradora na A e na C, operadora na B); há identidades com uma conta, duas e três contas, acesso restrito a uma
unidade, vínculo suspenso, nenhum vínculo ativo, convites pendentes, revogados, expirados e aceitos.

### Reprodução

```sh
npm run build
export E10_PREFIX=prospera-e10-lab-main
bash tests/Performance/e10-journeys.sh          # recria o laboratório e executa J1 a J10, concorrência, chave,
                                                # Octane (1 e 2 workers) e desempenho; saída em /tmp/prospera-e10
docker cp "$E10_PREFIX-app:/tmp/e10-state.json" /tmp/prospera-e10/state.json
E10_STATE=/tmp/prospera-e10/state.json E10_CHROMIUM=/caminho/chrome E10_URL=http://127.0.0.1:18110 \
    E10_MAIL=http://<ip do Mailpit na rede $E10_PREFIX-browser>:8025 node tests/Browser/e10.mjs
docker cp tests/Performance/e08-volume.sql "$E10_PREFIX-db:/tmp/"
docker exec "$E10_PREFIX-db" psql -U postgres -d e10 -v target=100000 -f /tmp/e08-volume.sql
E10_REQUESTS=100 E10_LABEL=volume-100k bash tests/Performance/e10-run.sh 14-desempenho
bash tests/Performance/e10-evolution.sh /tmp/arvore-pre-e09   # git archive 48e2191 + seeders atuais
bash tests/Performance/e10-phpunit.sh                         # suíte completa em container sem rede
bash tests/Performance/e10-lab.sh down
```

O README de `tests/Performance` detalha os pré-requisitos. A senha sintética das identidades fica apenas nos scripts
do laboratório, como na E09, e nenhum token de convite ou de redefinição foi versionado.

## C. Migrations

**Cenário A, instalação limpa.** As 39 migrations rodaram em banco vazio; `permissions:sync` (82 permissões),
`audit:check` (13 modelos) e `organization:check` (nenhuma violação) passaram. O esquema
([inspeção](../evidencias/e10-2026-11-24/migrations/cenario-a-esquema.txt)) confirma: `users.account_id` ausente;
`clients.code` com CHECK de formato e palavras reservadas, unicidade e gatilho de imutabilidade; FKs compostas de
pessoas, relações, cargos, atribuições, disponibilidade, convites e auditoria; CHECKs de escopo das atribuições e
convites, de convite aceito e de destinatário; principal sempre ativa; índices parciais de principal, matriz,
documentos e convite pendente; e os gatilhos de proteção do histórico.

**Cenário B, evolução.** O código de `48e2191` (pré-E09) criou o esquema e uma massa representativa com os seeders
atuais (única adaptação: a linha `enter()`, inexistente antes da E09) mais duas contas homônimas acentuadas e uma
preferência de Área de Trabalho: 3 contas, 12 identidades com `users.account_id`, 12 vínculos (1 suspenso),
20 atribuições, 13 clientes, 11 fornecedores, 31 pessoas, 17 etiquetas e 154 eventos. O código atual aplicou as três
migrations da E09 e os comandos de integridade.

| Tabela | Linhas | Depois | Revertido | Reaplicado | Restaurado |
| --- | --- | --- | --- | --- | --- |
| users (sem `account_id`), clients (sem `code`), companies, establishments, people, customers, suppliers, tags, person_tag, roles, role_availability, account_memberships, role_assignments | 13 a 31 por tabela | idêntico | idêntico | idêntico | idêntico |
| permission_role e audit_records pré-existentes | 399 e 154 | idêntico | idêntico | idêntico | idêntico |

Comparação por SHA-256 do conteúdo ([antes](../evidencias/e10-2026-11-24/migrations/evolucao-2-antes.json),
[depois](../evidencias/e10-2026-11-24/migrations/evolucao-3-depois.json)). Os únicos acréscimos são a permissão nova
da E09 (`account.users.revoke-invitation`), sua concessão ao Administrador e o evento da sincronização. Códigos:
`grupo-horizonte`, `arvore-acao-comercio` e `arvore-acao-comercio-2`. Preferências preservadas.

**Reversão.** Com vínculos únicos, `migrate:rollback --step=1` recriou `users.account_id` com os mesmos valores
originais e a reaplicação voltou ao estado anterior. Depois que uma identidade passou a ter vínculos em duas contas, a
reversão saiu com código 1 ("Reversão interrompida: 1 identidade(s) com vínculos em mais de uma conta") e a coluna
continuou ausente, sem alterar dados. O backup do esquema anterior (`pg_dump`, 27 tabelas com dados) foi restaurado
noutro banco e evoluiu com os mesmos códigos. Limite registrado: após o primeiro vínculo múltiplo, a recuperação é
correção para frente; voltar ao modelo anterior exige o backup anterior à liberação, com perda das alterações
posteriores e aprovação explícita.

## D. Jornadas integradas

Total do roteiro HTTP final: **494 verificações, zero falhas**, num laboratório recriado do zero
([resumo](../evidencias/e10-2026-11-24/jornadas/resumo.log)).

| Jornada | Verificações | Resultado e destaques |
| --- | --- | --- |
| 1. Provisionamento SaaS | 35 | PJ manual (empresa com raiz diferente do contratante, sem reutilizar o CNPJ), PJ automático matriz e filial, PF automático (empresa individual sem classificação). Classificação exigida e não inferida pelo sufixo 0001. Falha transacional (administrador com e-mail existente) e primeira empresa inválida sem nenhuma linha criada (contas, empresas, unidades, identidades, vínculos e auditoria contados). Administrador recebe todas as permissões `account` e `self`, nenhuma `platform`. Eventos de conta, empresa, unidade, vínculo e atribuição com autoria e origem da plataforma, sem senha, hash ou documento |
| 2. Empresas, matriz e filiais | 36 | Filiais com a mesma raiz; segunda matriz, raiz incompatível, CNPJ repetido e raiz repetida recusados sem gravação; CNPJ alfanumérico; filial principal sem matriz; mesma raiz aceita em outra conta; promoção de principal sem alterar classificação; inativar principal e promover inativa recusados; inativação e reativação de unidade e empresa; código imutável; índice parcial impede segunda matriz no banco (23505); eventos com empresa e unidade corretas |
| 3. Identidade global | 37 | Seleção com três contas sem escolha automática; vínculo único entra direto; permissões distintas por URL; autoridade da A recusada na B por URL e por ID, sem efeito colateral; conta inexistente e sem vínculo com resposta idêntica (404); `/conta` sem eleger conta e escrita recusada; conta inativa pela plataforma afeta só ela; suspensão na B não afeta A e C; a B não revela dados da A |
| 4. Convites | 38 e 11 | Respostas idênticas para identidade nova e existente; identidade nova pelo Fortify com senha pendente; token só como SHA-256; pendente sem acesso; outra identidade não vê nem aceita; aceite cria um vínculo e a pessoa da conta; reutilizado, substituído, revogado e expirado respondem 410; vínculo suspenso não é reativado; token inexistente sem dados; cargo só de Ajuda entra na Ajuda; eventos sem token. Chave desligada (instância própria): vínculos existentes funcionam, aceite 403 com convite pendente, emissão sem convite novo; religada, o mesmo convite é aceito |
| 5. Cargos, permissões e vínculos | 92 | Cargos por conta, empresa, unidade e restrito à A2; permissão `platform` recusada; antiescalada do gerente de unidade (cargo superior, outra unidade, nível de empresa, retirada superior, suspensão, criação de cargo) sem alteração; concessão local com nó e autor corretos e visibilidade só do próprio escopo; matriz de 33 operações por ID (listar, visualizar, cadastrar, editar, ativar, inativar, vincular, desvincular, administrar) sem mudança de dados nem auditoria; revogação e concessão de permissão na requisição seguinte; suspensão e reativação; última atribuição retirada sem suspender; último administrador protegido |
| 6. Área de Trabalho | 32 | Uma, várias, todas da empresa e de empresas distintas; unidade de outra conta 404; padrão gravado em `account_memberships.workspace_establishments` só do vínculo da A; códigos da B recusados; unidade inativa fora do padrão efetivo; revogação prevalece sobre a preferência sem reescrevê-la; uma unidade entra direto; escritas exigem empresa explícita (`todos` e empresa de outra conta 404) |
| 7. Clientes e Fornecedores | 42 | Uma Pessoa como Cliente e Fornecedor e em duas empresas; busca por CPF por POST; etiquetas por relação sem vazar entre empresas; contato central alterável por quem cobre uma empresa e identificação negada (403) sem alteração; inativação só da relação; auditoria da Pessoa no nó da conta e da relação com `company_id`; outra conta não descobre nem vincula a Pessoa e cria a própria; sem tela de Pessoas; etiqueta nova de Pessoas recusada; etiquetas de sistema preservadas |
| 8. Auditoria contextual | 35 | Totais, filtros (empresa, nível, entidade) e paginação iguais ao banco; detalhe com antes e depois, autor e nó; CPF ausente do histórico; auditor de unidade vê só a unidade, sem detalhe de conta, empresa ou outra conta e sem dados técnicos; filtro forjado não amplia; plataforma restrita a PD-02; sem mudança efetiva sem evento; 422 sem evento; UPDATE no histórico recusado |
| 9. Inspeção SaaS | 34 | Representáveis só da conta; somente leitura sem escrita nem evento; troca de conta pela URL 404 mesmo com vínculo do representado; preferência intocada; sessão do representado independente; edição com autoria real, representado, vínculo e `inspection_id` coerentes e autoridade só do representado; perda de autoridade encerra a inspeção e a sessão (401) com evento; nenhum vínculo criado |
| 10. Duas abas | Chromium | Ver J. Interface |

## E. Segurança

Matriz de isolamento ([10-seguranca](../evidencias/e10-2026-11-24/jornadas/10-seguranca.json), 54 verificações): o
administrador da B tentou 33 rotas com IDs da A (empresa, unidade, cargo, vínculo, convite, etiqueta, cliente,
fornecedor, auditoria, contexto de unidade, consolidado e URL da A) e 10 formulários válidos da B com referências da A
(cargo, empresa, unidade, pessoa, disponibilidade, etiqueta, empresa adicional e concessão). Todos responderam 403,
404 ou 422 sem citar dados da A, e o SHA-256 de 15 tabelas ficou idêntico. Filtro e busca da B não contam dados da A.
Anônimo recebe 401, POST sem CSRF 419 sem gravação, login limitado (429 em português) com erro idêntico para e-mail
existente e inexistente, recuperação de acesso neutra e cookie de sessão HttpOnly com SameSite.

Logs: sem documento, senha, hash, cookie ou caminho de redefinição. Achado: o log de acesso do console do Octane (o
mesmo comando do `Dockerfile` oficial) imprime o caminho `/convites/{token}` truncado em 46 dos 64 caracteres.
**Corrigido na revisão final de segurança dos logs (seção O):** o truncamento era só visual e o registro do Caddy
guardava a URI inteira; o `Dockerfile` agora inicia o Octane com `docker/Caddyfile`, que redige o segredo.

## F. Concorrência

Processos independentes (fork) liberados no mesmo instante contra FrankenPHP com dois workers e PostgreSQL 18,
conferindo o estado final ([11-concorrencia](../evidencias/e10-2026-11-24/jornadas/11-concorrencia.json)):

| Cenário | Respostas | Estado final |
| --- | --- | --- |
| Contas com o mesmo nome | 200 e 200 | `colisao-concorrente-e10` e `-2`, uma empresa e uma principal cada |
| Aceite simultâneo | 200 e 410 | um vínculo, convite aceito, um evento |
| Revogação durante aceite | 200 e 422 | aceito com vínculo, nunca revogado com vínculo |
| Dois administradores suspendendo um ao outro | 200 e 403 | um administrador ativo, depois restaurado |
| Mesmo CPF na mesma empresa | 204 e 422 | uma Pessoa, um Cliente, um evento |
| Pessoa nova em duas empresas | 204 e 422 | uma Pessoa, sem 500 |
| Dez alterações da mesma Pessoa | 10 x 204 | dez eventos encadeados, valor final igual ao último depois, autoria correta |
| Inativação da empresa durante cadastro (5 rodadas) | 404 e 200 | nenhum fornecedor após a inativação |
| Edição de unidade durante cadastro (5 rodadas) | 204 e 200 | ambas concluídas |
| Atribuição igual simultânea | 200 e 422 | uma atribuição |
| Suspensão durante oito escritas | 204, 204, 403 x 6 | dois clientes e dois eventos, nenhuma sobra das negadas |

O log da instância de dois workers terminou sem nenhum `local.ERROR`. Antes da correção D1, a inativação concorrente
produziu 500 em 5 de 5 rodadas e a edição de unidade em 3 de 5
([antes da correção](../evidencias/e10-2026-11-24/concorrencia-antes-da-correcao.json)).

## G. FrankenPHP

Fluxos integrados alternados em 12 passos por iteração (administradora na A, a mesma identidade operadora na B,
operadora de uma unidade, consolidado da empresa A1, auditoria local, usuários da B, consulta com 200 e 403, seleção,
conta inexistente, padrões da Área de Trabalho, escrita comercial com conferência da autoria, 422, inspeção com
leitura, troca de conta, escrita e encerramento, e listagens da plataforma). Cada resposta foi conferida contra o
próprio contexto: base da conta, permissões do perfil, ausência de nomes da outra conta e total da auditoria local.

| Configuração | Requisições | Inesperadas | Memória inicial por bloco de 500 (MB) |
| --- | --- | --- | --- |
| 1 worker | 4.202 | 0 | 14,6 a 15,0, último bloco 13,6 (mínimo 11,5, máximo 18,2) |
| 2 workers | 4.202 | 0 | 12,3 a 13,4, último bloco 11,3 (mínimo 9,9, máximo 16,5) |

Sem crescimento contínuo de memória. A cada escrita a autoria foi lida do banco: nenhum evento herdou autor,
representado ou inspeção da requisição anterior. Revogação e concessão de permissão valeram na requisição seguinte,
sem reiniciar o worker. Como o cliente é sequencial, o segundo worker atendeu poucas requisições nesse ensaio; os dois
workers foram exercitados em paralelo pelas corridas de F.

## H. Auditoria

Autor real, representado, conta, empresa, unidade, entidade, evento, data e antes e depois conferidos nos eventos das
jornadas. O contexto é o do recurso: Pessoa no nó da conta, relação comercial com `company_id` mesmo navegando por
unidade, unidade com empresa e unidade, atribuição com o nó do escopo. Nenhum evento em alteração sem mudança, em
resposta 4xx (inclusive 422 após validação), em tentativa negada ou em inspeção somente leitura. Escritas concorrentes
geram cadeia coerente. A busca na auditoria e os detalhes não mostram CPF; tokens, senhas e hashes nunca aparecem. O
histórico antigo não foi reescrito: os eventos pré-E09 continuaram com hash idêntico após a evolução.

## I. Performance

Medição por tela com o `OctaneMetrics` (100 amostras, 1 worker), antes e depois de completar `audit_records` com o
script de volume da E08 ([base](../evidencias/e10-2026-11-24/jornadas/desempenho-base.json),
[100 mil](../evidencias/e10-2026-11-24/jornadas/desempenho-volume-100k.json)).

| Tela | Consultas | Mediana ms (1,2 mil eventos) | P95 | Mediana ms (101 mil) | P95 |
| --- | --- | --- | --- | --- | --- |
| Dashboard da conta | 12, 13 | 16,08 | 20,20 | 16,00 | 17,61 |
| Seleção de contas | 7, 8 | 10,67 | 12,18 | 10,80 | 12,33 |
| Resolução da conta (padrão) | 8, 9 | 11,41 | 15,52 | 11,29 | 12,78 |
| Área de Trabalho consolidada | 8, 9 | 12,62 | 14,19 | 13,32 | 14,99 |
| Empresas | 11, 12 | 15,00 | 16,78 | 15,12 | 17,34 |
| Estabelecimentos | 13, 14 | 16,52 | 19,00 | 16,84 | 18,97 |
| Clientes (consolidado) | 11, 12 | 17,61 | 19,55 | 17,83 | 20,06 |
| Fornecedores (consolidado) | 11, 12 | 16,73 | 18,35 | 15,83 | 18,64 |
| Usuários | 18, 19 | 23,39 | 26,36 | 22,99 | 26,57 |
| Cargos | 11, 12 | 17,66 | 19,48 | 17,27 | 18,58 |
| Etiquetas | 11, 12 | 17,44 | 19,45 | 17,59 | 19,66 |
| Auditoria, conta inteira | 14, 15 | 27,06 | 28,74 | 34,57 | 37,09 |
| Auditoria, busca textual | 13 a 16 | 22,83 | 24,92 | 83,67 | 86,35 |
| Auditoria, unidade | 15, 16 | 22,76 | 24,66 | 39,73 | 41,79 |
| Plataforma, contas | 6, 7 | 8,74 | 9,90 | 8,70 | 10,38 |
| Inspeção início, leitura, encerramento (Octane, 1 worker) | 13, 14 / 14 / 14 | 13,49 / 16,95 / 13,28 | 16,98 / 18,89 / 15,66 | | |

Custos estruturais: uma consulta de atribuições e uma de permissões por requisição de conta (duas de permissão na
inspeção, que avalia o autor e o representado), sem variar com o número de checagens. Listagens sem N+1: o mesmo número
de consultas com 5 e 50 linhas por página em Clientes, Fornecedores, Usuários, Etiquetas, Auditoria, Empresas e
Estabelecimentos. Nenhuma assinatura SQL repetida nas leituras. A variação de uma consulta (por exemplo 11 e 12) é a
gravação periódica da sessão. A leitura de `workspace_establishments` por `OrganizationContext::defaultCodes` aparece
quando há mais de uma unidade e é legítima (E06), como registrado na E09.

Repetições nas escritas, constantes e explicadas: a escrita comercial repete a trava de `clients` e de `people` porque
`SavePersonAction` (sub-Action da E07) trava de novo as linhas que a Action comercial já travou; o encerramento da
inspeção repete a revalidação do autor SaaS (já registrado na E09). Não há crescimento com dados nem laço por linha.

Auditoria em volume: a consulta não mudou desde `7cd9b9d` (E08), então a referência de 1 milhão de eventos (mediana
≈533 ms e p95 ≈561 ms na busca textual ampla) continua válida e não foi repetida. Na amostra proporcional, com 42,7 mil
eventos na conta A, a busca textual ficou em 83,67 ms de mediana (65,8 ms no banco), coerente com a escala linear da
varredura medida na E08. Os planos com 101 mil eventos
([auditoria](../evidencias/e10-2026-11-24/planos-auditoria-100k.txt)) usam os índices da E08 (página inicial 0,10 ms,
contagem da conta 7,0 ms, empresa e unidade por índice próprio). Os planos reais das telas, capturados com
`auto_explain` ([telas](../evidencias/e10-2026-11-24/planos-telas.txt), parâmetros removidos), mostram a conta resolvida
por `clients_code_unique` e varreduras sequenciais sub-milissegundo nas tabelas pequenas de vínculos e atribuições.
Nenhum índice, cache ou infraestrutura foi acrescentado.

Comparações com a E09: as telas equivalentes mantêm o mesmo número de consultas (seleção 7 e 8; Etiquetas de conta com
várias unidades 11 e 12; padrão da Área de Trabalho 8 e 9). As latências não são comparadas: a massa (mais etiquetas,
pessoas, eventos e unidades), o laboratório (Mailpit e instâncias adicionais) e a carga do host são diferentes. A
correção D1 acrescenta uma consulta (`select ... for update` em `clients`) por escrita de empresa ou estabelecimento;
a correção D2 não acrescenta consulta (usa as atribuições já carregadas).

## J. Interface

Chromium com login real ([resultado](../evidencias/e10-2026-11-24/navegador/resultado.json), 301 verificações, zero
falhas, 116 medições de largura). Larguras 1440, 1024, 768, 390 e 320 px em: login, seleção de conta, início da conta,
Empresas, Estabelecimentos e cadastro, Organização, Clientes e Fornecedores consolidados, Usuários, Cargos e cadastro,
Etiquetas, Auditoria (administradora e gerente de unidade), Ajuda, operadora de uma unidade, plataforma (contas e
auditoria), inspeção, convite válido e reutilizado. Interações em 1440, 390 e 320 px: menu do avatar com Trocar de
conta, seletor da Área de Trabalho com Selecionar todas e Abrir área de trabalho, tour com Escape, modal com erro de
validação, Cancelar e Salvar com toast, estado vazio e foco por teclado. Tour de Etiquetas percorrido até Concluir pela
operadora somente consulta (12 passos em 1440 e 10 em 320, sem abrir o cadastro). Convite entregue ao Mailpit, aceito
pelo botão Salvar e reutilizado com a mensagem de indisponível. Nenhum erro JavaScript, nenhuma resposta 5xx e nenhuma
rolagem horizontal.

Duas abas na mesma sessão (J10): aba A na Conta A como administradora com cinco unidades e aba B na Conta B como
operadora de uma unidade. Listagens, filtros, modal, gravação na A com recarga da B, histórico do navegador e troca de
unidade na A sem afetar a B. Com o vínculo da B suspenso, a aba B mostrou "Seu acesso a esta conta está suspenso" com
Escolher outra conta, sem troca silenciosa para a A, enquanto a aba A seguiu operando
([captura](../evidencias/e10-2026-11-24/navegador/aba-b-revogada-1440.png)).

Ocorrência não tratada como falha: a 11ª abertura direta do mesmo convite em um minuto caiu no limitador de leitura
(20 por minuto, cada abertura direta conta duas), com a mensagem em português da E09; o roteiro passou a aguardar a
janela. Não houve redesenho de tela.

## K. Testes

- PHPUnit completo: **278 testes e 3.792 asserções**, sem falhas ([log](../evidencias/e10-2026-11-24/phpunit-final.log)).
  Novo `IntegratedArchitectureTest` (1 teste, 15 asserções), comprovadamente falho no HEAD `af96f3e` (403 em vez de 200).
- Frontend 20 testes, arquitetura (822 arquivos), tipos e build aprovados; PHPStan sem erros; Pint em 344 arquivos;
  `git diff --check` limpo.
- `permissions:sync` 82 descobertas sem mudança, `audit:check` 13 modelos, `organization:check` sem violações.
- Roteiros novos: `tests/Performance/e10-*.sh`, `tests/Performance/e10/*.php` e `tests/Browser/e10.mjs`.

### Matriz de rastreabilidade

| Etapa | Contrato homologado | Funcionalidade | Jornada E10 | Testes automatizados | Evidência PostgreSQL ou navegador | Resultado | Defeitos | Correção | Risco residual |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| E01 | Árvore Plataforma, Conta, Empresa, Estabelecimento; PD-01 a PD-07 | Arquitetura e decisões | Todas; massa A, B, C, D | Suíte completa | Esquema limpo e evolução | Aprovado | Nenhum | | Nenhum técnico |
| E02 | Convenções, nomes, URLs e textos | Contrato organizacional | J2, J3, J6, rotas por código | `AccountContextArchitectureTest`, `check-architecture` | Navegador (rótulos Salvar, textos) | Aprovado | Nenhum | | |
| E03 | Contexto por requisição e RBAC sem cache | `AccountContext`, `EffectivePermissions` scoped | G (Octane), J5 revogação | `AccountRequestsTest`, `ContextualAccessTest` | 8.404 requisições sem vazamento; 1 consulta de permissão | Aprovado | Nenhum | | Latência sintética |
| E04 | Empresas e estabelecimentos, 6.6 e 6.7, `TaxDocument` | Matriz, filial, principal, raiz | J1, J2, F (inativação) | `OrganizationTest`, `TaxDocumentTest` | Índices parciais (23505), CNPJ alfanumérico | Aprovado após D1 | D1 deadlock | Trava conta antes da empresa | Nenhum conhecido |
| E05 | Vínculos, cargos, atribuições, antiescalada, último administrador | RBAC contextual | J5, F (corridas) | `AccessControlTest`, `ContextualAccessTest`, `RoleBasedRegistrationTest` | Matriz de 33 operações e corridas | Aprovado após D2 | D2 Etiquetas por atribuição local | Leitura por qualquer atribuição | Nenhum conhecido |
| E06 | Área de Trabalho, padrão por vínculo, URL como autoridade | Seletor e consolidado | J6, J10 | `OrganizationContextTest`, `CommercialRelationWorkspaceTest`, frontend | Navegador e `workspace_establishments` | Aprovado | Nenhum | | |
| E07 | Pessoas internas, relações por empresa, etiquetas comerciais | Clientes e Fornecedores | J7, F (documento e pessoa) | `CustomerDirectoryTest`, `SupplierDirectoryTest`, `CommercialRelation*Test`, `PeopleDirectoryTest` | Corridas e planos `auto_explain` | Aprovado | Nenhum | | Retrava constante na escrita |
| E08 | Auditoria contextual, PD-02, imutabilidade | Histórico e consulta | J8, J9, F, I | `ContextualAuditTest`, `AuditAndGuidanceTest` | Volume 100 mil, planos, UPDATE recusado | Aprovado | Nenhum | | Busca textual cresce com o volume |
| E09 | Identidade global, convites, chave, URL de conta | Várias contas e convites | J1, J3, J4, J9, J10, F | `MultipleAccountsTest`, `InitialInvitationSecurityTest`, `AccountIsolationTest` | Evolução, reversão, chave desligada, Mailpit, Chromium | Aprovado | Log de acesso com token truncado | Redação no Caddy e `Referrer-Policy` (seção O) | Imagem oficial precisa ser reconstruída |

Nenhuma etapa foi considerada validada só porque seus testes antigos continuaram passando: cada linha tem a jornada
integrada e a evidência correspondentes.

## Defeitos encontrados e corrigidos

**D1. Deadlock entre escrita organizacional e cadastro comercial (E04 e E07).** Reprodução: inativar a empresa A2
enquanto outro processo cadastra fornecedor nela; 500 (`SQLSTATE 40P01`) em 5 de 5 rodadas, e editar um
estabelecimento durante um cadastro de cliente na mesma empresa, em 3 de 5. Causa: `RegisterCommercialRelationAction`
trava `clients` e depois `companies`; `SaveCompanyAction`, `SaveEstablishmentAction` e
`PromotePrimaryEstablishmentAction` travavam `companies` e, ao gravar a auditoria, a FK `audit_records.account_id`
pedia `FOR KEY SHARE` na linha de `clients`, em ordem inversa. Contrato violado: a concorrência não pode produzir erro interno
nem inconsistência (critério 15 desta etapa e convenção de Performance para escritas concorrentes). Correção na responsabilidade apropriada: as três Actions
passam a travar a conta antes da empresa, a mesma ordem já usada por `CreateCompanyAction` e pelas Actions comerciais e
de acesso. Regressão: cinco rodadas de cada corrida no PostgreSQL com dois workers, sem 500 e com estado coerente; a
suíte SQLite não reproduz travas concorrentes, por isso a prova fica no laboratório (`11-concorrencia.php`).

**D2. Etiquetas negadas a atribuições de empresa ou unidade (E05).** Reprodução: identidade com cargo de unidade que
contém `account.tags.list` recebia 403 em Etiquetas e não via o menu nem o artigo de ajuda. Os seeders já davam essa
permissão a cargos de loja. Causa: a rota usava `can:account.tags.list` sem nó, que o Gate reserva a atribuições de
conta, e `visibleSlugsFor` não incluía Etiquetas. Contrato violado: seção 8.2.1, "`account.tags.list/view`: qualquer
atribuição (leitura do catálogo de etiquetas)". Correção: listar e visualizar conferem `nodesFor` no controller
(qualquer atribuição com a permissão) e as duas permissões entram em `visibleSlugsFor`; cadastrar, editar, ativar,
inativar e excluir continuam exigindo escopo de conta. O artigo de ajuda de Etiquetas foi atualizado; o tour já
condicionava o cadastro à permissão (`availableWith`) e foi percorrido pela operadora somente consulta. Regressão:
`IntegratedArchitectureTest`, J4 (identidade nova operando Etiquetas na unidade) e o navegador.

## L. Git

Branch e HEAD inalterados (`feat/filtros-calendario-titulos`, `af96f3e`). Sem commit, push, staging, stash ou reset.

Alterados: `app/Domains/Organizacao/Application/Actions/SaveCompanyAction.php`,
`SaveEstablishmentAction.php`, `PromotePrimaryEstablishmentAction.php` (D1);
`app/Domains/Pessoas/UI/Controllers/DirectoryController.php`, `routes/account.php`,
`app/Domains/Acessos/Application/Context/EffectivePermissions.php` (D2); `docs/ajuda/etiquetas.md`;
`tests/Performance/OctaneMetrics.php` (campo `duplicate_sql`, só laboratório); `tests/Performance/README.md`; o plano
oficial.

Novos: `tests/Feature/IntegratedArchitectureTest.php`; `tests/Performance/e10-lab.sh`, `e10-reset.sh`, `e10-run.sh`,
`e10-journeys.sh`, `e10-evolution.sh`, `e10-phpunit.sh`; `tests/Performance/e10/` (núcleo HTTP, fixture, 15 roteiros,
fotografia e massa da evolução, listagem de SQL); `tests/Browser/e10.mjs`; este relatório e
`docs/evidencias/e10-2026-11-24/`.

## M. Pendências

Riscos técnicos e limitações:

1. Latências e memória são linha de base sintética em host compartilhado, com bcrypt de custo 4; não são SLA.
2. A busca textual ampla da auditoria cresce com o volume da conta (referência da E08 preservada).
3. Resolvida na seção O: o log de acesso do Octane não registra mais tokens. Resta definir retenção e acesso aos
   logs de acesso como higiene operacional, sem dado sensível de convite ou de redefinição neles.
4. A acessibilidade foi verificada em nível básico (nomes, foco, Escape), sem leitor de tela.
5. O e-mail foi validado só com Mailpit isolado; nenhum provedor real.
6. A retrava de conta e pessoa nas escritas comerciais é constante e não foi otimizada (comportamento da E07).

Condições de implantação, preservadas da E09 e não executadas: backup recuperável e verificado da base a migrar;
janela de migrations, com a contração de `users.account_id` como ponto sem reversão simples após o primeiro vínculo
múltiplo; política de ativação de `PROSPERA_MULTIPLE_ACCOUNTS` (padrão desligado); configuração e validação do
provedor de e-mail; reinício controlado dos workers; novo login das sessões anteriores à contração; monitoramento
inicial de erros, latência, memória e consultas; política de retenção dos logs de acesso (item 3) e a imagem
oficial reconstruída para que o `CMD` com `--caddyfile` entre em vigor (seção O.7).

## N. Estado da E10

Os 23 critérios de aceite da autorização foram atendidos no laboratório: instalação limpa e evolução, provisionamento,
contrato fiscal, identidade global, permissões isoladas, convites, último administrador, Área de Trabalho, duas abas,
Pessoas compartilhadas só dentro da conta, auditoria, inspeção, concorrência (após D1), workers sem vazamento,
desempenho sem regressão estrutural, cinco larguras, testes, matriz de rastreabilidade, defeitos corrigidos e
revalidados, pendências separadas e nenhuma etapa reconstruída.

**E10 Em validação, pronta para homologação formal** (checkpoint anterior à homologação definitiva, seção Q).

## O. Revisão final de segurança dos logs de acesso

Intervenção limitada, autorizada depois da execução principal. Não reabriu a arquitetura, não alterou convites,
autenticação, Pessoas nem as correções D1 e D2.

### O.1 Diagnóstico

Estado do Git: a árvore estava limpa em `0a0502c` (registros de homologação já versionados), sem staging nem stash.

Fluxo real do log de acesso:

1. O FrankenPHP (Caddy 2.11) grava um registro JSON por requisição (`handled request`) com a **URI completa** e todos os
   cabeçalhos da requisição (menos Cookie, Set-Cookie e Authorization, que o Caddy já mascara). O nível é INFO quando
   `APP_ENV=local`, como no `compose.yaml`, e WARN fora disso (sem registro de acesso).
2. O `octane:frankenphp` lê esse JSON do stderr do Caddy e o reimprime em `StartFrankenPhpCommand::writeServerOutput`
   e `InteractsWithIO::requestInfo`. Só ali ocorre o corte em `largura do terminal - 15` caracteres seguido de `...`.
   Sem TTY a largura é 80, daí 46 dos 64 caracteres. O corte é visual e **não torna o registro seguro**: 46
   caracteres de um segredo ainda são parte do segredo, e com `--log-level` o Octane repassa o JSON bruto, com a URI
   inteira.
3. Não há outra camada que armazene a URL: o Laravel não loga a rota (`storage/logs` vazio nos ensaios), o Octane não
   grava arquivo e o Docker guarda só o que o processo imprime.

Credenciais temporárias encontradas nos caminhos e queries:

| Rota | Credencial |
| --- | --- |
| `GET/POST /convites/{token}` | token de convite (64 caracteres) |
| `GET /reset-password/{token}?email=` | token de redefinição e e-mail |
| `GET /email/verify/{id}/{hash}?expires&signature` | hash e assinatura |
| `GET /settings/profile/email/confirm?...signature` | assinatura |

Cabeçalhos: `Referer` (carrega o caminho da página anterior, inclusive em requisições do mesmo site) e
`X-XSRF-TOKEN` ou `X-CSRF-TOKEN` (o Caddy só mascara Cookie, Set-Cookie e Authorization). O app não definia
`Referrer-Policy`, então o navegador usava o padrão `strict-origin-when-cross-origin`: sem vazamento da URL para
outro site, mas com a URL completa no `Referer` das requisições internas, que iam para o log.

### O.2 Causa e camada responsável

Camada: configuração do Caddy do servidor de aplicação. O Caddyfile padrão do Octane só redige o parâmetro
`authorization` (Mercure) e registra o resto. A causa é a ausência de redação para as rotas do Prospera.

### O.3 Solução

- `docker/Caddyfile`: cópia do stub do Octane com filtros de log. `request>uri` vira `/convites/[REDACTED]` (idem para
  `/reset-password`, `/email/verify` e `/settings/profile/email/confirm`; a query inteira é removida). `Referer` e
  `Location` da resposta recebem a mesma regra. `X-Xsrf-Token` e `X-Csrf-Token` são removidos. Caminhos com `%`
  (por exemplo `/%63onvites/...`, que o Laravel decodifica e roteia) são redigidos por inteiro.
- `Dockerfile` (`CMD`) e `docker/dev.sh`: `--caddyfile=/app/docker/Caddyfile` e `--caddyfile=docker/Caddyfile`.
- `App\Http\Middleware\SetReferrerPolicy` (global, para cobrir também redirecionamentos, 403, 404, 419 e 429) e
  `<meta name="referrer" content="no-referrer">` em `app.blade.php`: o navegador não envia `Referer` a lugar nenhum.
- Sem código de convite, autenticação ou rota alterado. Sem infraestrutura externa.

Preservado no log: método, status, duração, tamanho, IP do cliente, prefixo da rota, `X-Ratelimit-*`, tipo de conteúdo
e `Referrer-Policy`. Cookies continuam `REDACTED`.

### O.4 Resultado real dos logs

Laboratório descartável por modo (PostgreSQL 18 em tmpfs, FrankenPHP com um worker, Mailpit, `APP_ENV=local`, mesmas
dez jornadas HTTP e o roteiro `16-logs-limite`, 459 requisições de acesso registradas). Verificação por janela
deslizante de 12 caracteres sobre 17 segredos sintéticos: tokens de convite e de redefinição extraídos dos e-mails do
Mailpit, tokens inexistentes, hash e assinaturas sintéticos, valor sintético de `X-XSRF-TOKEN` e caminho codificado.

| Modo | Comando | Segredos inteiros | Segredos com trecho ≥ 12 | Maior trecho |
| --- | --- | --- | --- | --- |
| baseline | Octane sem `--caddyfile` (antes da correção) | 3 | 11 | 45 |
| oficial | `--caddyfile` lido do `CMD` do Dockerfile, saída padrão | 0 | 0 | 0 |
| oficial-bruto | igual, com `--log-level=INFO` (JSON bruto do Caddy) | 0 | 0 | 0 |

`storage/logs` e o stderr do Laravel também ficaram sem segredos. Nas duas execuções corrigidas os logs mostram, por
exemplo, `200 GET /convites/[REDACTED]`, `429 GET /convites/[REDACTED]`, `410 POST /convites/[REDACTED]`,
`302 GET /email/verify/[REDACTED]` e `200 GET /reset-password/[REDACTED]`; nenhum cabeçalho `X-Xsrf-Token` ou
`X-Csrf-Token` e `Referer` redigido (`http://externo.test/convites/[REDACTED]`). Contagens em
[amostra-console-oficial.txt](../evidencias/e10-2026-11-24/logs/amostra-console-oficial.txt), registros brutos em
[amostra-json-bruto.json](../evidencias/e10-2026-11-24/logs/amostra-json-bruto.json) e resultados em
`docs/evidencias/e10-2026-11-24/logs/resultado-*.txt`. Nenhum token, senha ou credencial foi gravado nas evidências;
as de baseline trazem só contagens.

### O.5 Testes executados

- Jornadas HTTP 01 a 10 nos três modos, todas sem falhas (35, 36, 92, 38, 37, 32, 42, 35, 34 e 54 verificações),
  incluindo abertura, aceite, convite inválido, expirado, reutilizado, substituído, revogado, recuperação de senha e
  login limitado. `16-logs-limite` (sessão real): 20 respostas 200 e depois 429 na abertura de convites.
- Requisições extras com `Referer` contendo o caminho sensível, `X-XSRF-TOKEN` sintético, links de verificação de
  e-mail e de confirmação de e-mail assinados, redefinição com query e caminho codificado.
- Cabeçalho `Referrer-Policy: no-referrer` conferido ao vivo em 200, 302 (login), 404 e `/up`, e a meta no HTML.
- `AccessLogRedactionTest` (4 testes, 21 asserções): cabeçalho em respostas normais e de exceção, meta, `CMD` do
  Dockerfile e `dev.sh` com `--caddyfile`, regras do Caddyfile.
- Uma execução de `php artisan test` no PHP do host terminou com 259 falhas, 1 teste ignorado e 22 aprovados. A causa
  foi confirmada na saída (seção Q.1): ambiente, sem regressão.
- PHPUnit completo em container sem rede: **282 testes e 3.813 asserções**, sem falhas (antes 278 e 3.792). PHPStan
  sem erros e Pint aprovado. `node scripts/check-architecture.mjs` aprovado. `migrate`, `permissions:sync`,
  `audit:check` e `organization:check` rodam na subida de cada laboratório e passaram.
- Frontend (testes e build) não reexecutados: nenhum arquivo TypeScript, CSS ou componente mudou; só o HTML do Blade
  ganhou uma meta.
- A sintaxe do Caddyfile foi validada com `frankenphp validate` na imagem oficial, e o servidor real subiu com ele nos
  laboratórios. Os laboratórios trocam só o comando de inicialização (o `CMD` da imagem local é anterior à correção),
  mas o argumento `--caddyfile` é lido do `CMD` do `Dockerfile` do repositório e o script falha se ele não existir.

Preservado da validação anterior, sem repetir: concorrência (11 cenários), 4.202 requisições de ensaio, Chromium
(301 verificações), evolução e reversão de migrations, planos de consulta. Justificativa técnica: a mudança não toca
código de domínio, banco, travas, autorização ou frontend; o diff de `app/` é só o middleware de cabeçalho. D1
(ordem de travas Conta, Empresa) e D2 (leitura de Etiquetas por qualquer atribuição, sem ampliar entre contas) seguem
cobertos pelos mesmos testes PHPUnit, todos aprovados, e as jornadas 03 e 05 passaram intactas.

### O.6 Impacto operacional

- Logs de acesso continuam úteis, só sem o segredo. Para diagnosticar um convite, use o horário, o status, o IP e o
  identificador técnico na auditoria. O prefixo `/convites/[REDACTED]` não distingue um token de outro.
- `POST /reset-password` aparece como `/reset-password/[REDACTED]`, e caminhos com `%` aparecem como `//[REDACTED]`
  (perda de detalhe aceita, por segurança).
- `Referrer-Policy: no-referrer` não afeta CSRF (o Laravel confere token e `Origin`) nem o fluxo funcional.
- O `docker/Caddyfile` copia o stub do Octane. Ao atualizar o Octane, compare com
  `vendor/laravel/octane/src/Commands/stubs/Caddyfile`.

### O.7 Riscos residuais e pendências de implantação

1. A correção só vale na imagem oficial depois de reconstruir (o `CMD` mudou). Os containers oficiais em execução
   não foram reconstruídos nem reiniciados e continuam com o comportamento anterior até a implantação.
2. Se alguém iniciar o Octane sem `--caddyfile` (comando manual ou outro orquestrador), o problema volta. O teste
   `AccessLogRedactionTest` cobre `Dockerfile` e `dev.sh`, não comandos externos.
3. Proxy ou balanceador à frente do app tem o próprio log de acesso e precisa de regra equivalente (não existe no
   repositório).
4. Tokens de convite já gravados em logs antigos permanecem lá; são de uso único e expiram, mas a retenção desses
   logs deve ser curta.
5. Rotas novas com credencial temporária na URL exigem acrescentar o prefixo ao `docker/Caddyfile`.

### O.8 Arquivos e estado do Git

Modificados: `Dockerfile`, `docker/dev.sh`, `bootstrap/app.php`, `resources/views/app.blade.php`,
`tests/Performance/e10-lab.sh` (copia `docker/` e aceita `E10_OCTANE_ARGS`), `tests/Performance/e10-phpunit.sh`
(copia `docker/` e `Dockerfile`), este relatório, o plano 001, o README e o README de Performance.
Novos: `docker/Caddyfile`, `app/Http/Middleware/SetReferrerPolicy.php`, `tests/Feature/AccessLogRedactionTest.php`,
`tests/Performance/e10-logs.sh`, `tests/Performance/e10-logs-check.py`, `tests/Performance/e10/16-logs-limite.php` e
`docs/evidencias/e10-2026-11-24/logs/`. Sem commit, push, staging ou stash; HEAD `0a0502c`, branch
`feat/filtros-calendario-titulos`. Containers e bancos oficiais não foram tocados, e os três laboratórios foram
removidos.

## P. Pendências operacionais de implantação

Checklist para uma implantação futura, **sem relação com etapas de desenvolvimento pendentes da arquitetura**. Nada
abaixo foi executado, e a implantação exige autorização separada.

| # | Item | Condição |
| --- | --- | --- |
| 1 | Backup | Backup recuperável e verificado (restauração testada) da base a migrar |
| 2 | Migrations | Janela própria; a contração de `users.account_id` não tem reversão simples após o primeiro vínculo múltiplo |
| 3 | Imagem oficial | Reconstruir a imagem para que o `CMD` com `--caddyfile=/app/docker/Caddyfile` entre em vigor; o container em execução mantém o comportamento anterior |
| 4 | Proxy ou balanceador externo | Regra de redação equivalente à do `docker/Caddyfile` em todo componente que registre requisições com credenciais temporárias; não existe no repositório |
| 5 | E-mail | Configurar e validar o provedor real (só o Mailpit isolado foi usado) |
| 6 | Feature flag | Definir a política de `PROSPERA_MULTIPLE_ACCOUNTS` (padrão desligado) |
| 7 | Workers | Reinício controlado dos workers do Octane |
| 8 | Sessões | Novo login das sessões anteriores à contração |
| 9 | Política de logs | Retenção curta e acesso restrito; logs históricos anteriores à correção podem conter tokens de convite (uso único) e devem ser tratados por política operacional |
| 10 | Monitoramento inicial | Erros, latência, memória e consultas após a implantação |
| 11 | Manutenção | Novas rotas com token ou assinatura na URL entram no `docker/Caddyfile`; ao atualizar o Octane, comparar o Caddyfile com `vendor/laravel/octane/src/Commands/stubs/Caddyfile` |

## Q. Homologação técnica definitiva (24/11/2026)

**E10 Concluída e homologada tecnicamente em 24/11/2026.** Aprovada pelo usuário após a revisão final de segurança.

### Q.1 Origem das 259 falhas

Conferida na saída, sem presumir. `php artisan test` no PHP do host (que tem `PDO`, mas não `pdo_sqlite`) produziu
259 falhas, 1 ignorado e 22 aprovados, total de 282 testes. Em todas as 259, a mensagem é `QueryException: could not
find driver (Connection: sqlite, Database: :memory:)`, e nenhuma outra exceção apareceu. O `tests/bootstrap.php`
impõe SQLite em memória, então sem o driver nenhum teste com banco consegue rodar. Foi falha **exclusivamente de
ambiente**. O mesmo código passou no container correto (`e10-phpunit.sh`): **282 testes e 3.813 asserções, sem
falhas**, referência final preservada. Não há regressão pendente, e a suíte não foi repetida.

### Q.2 Aprovado tecnicamente

`docker/Caddyfile`; redação das rotas com credenciais temporárias; proteção dos cabeçalhos sensíveis (`Referer`,
`X-Xsrf-Token`, `X-Csrf-Token`, `Location`); caminhos codificados; `Referrer-Policy: no-referrer`; comandos de
inicialização com `--caddyfile`; os quatro testes de regressão; os ensaios com logs reais de FrankenPHP. Convites,
Fortify e demais autenticações permaneceram intactos (jornadas 01 a 10 sem falhas). Nenhum mecanismo novo de logging
ou autenticação foi criado.

### Q.3 Limitações aceitas

A homologação foi feita em laboratório. O ambiente oficial precisa receber a nova imagem. Proxies e balanceadores
externos precisam de proteção equivalente. Logs históricos podem conter segredos anteriores à correção. Novas rotas
com token na URL entram nas regras de sanitização. Atualizações do Octane exigem conferir o Caddyfile. Não bloqueiam a
homologação técnica, mas são condições obrigatórias antes de uma implantação segura (seção P).

### Q.4 Conjunto do checkpoint final e verificação de credenciais

Git: branch `feat/filtros-calendario-titulos`, HEAD `0a0502c`, 10 arquivos modificados e 7 entradas novas, sem
staging nem stash. Compõem o checkpoint: código e infraestrutura (`Dockerfile`, `docker/dev.sh`, `docker/Caddyfile`,
`bootstrap/app.php`, `SetReferrerPolicy.php`, `app.blade.php`); teste (`AccessLogRedactionTest.php`); scripts
(`e10-lab.sh`, `e10-phpunit.sh`, `e10-logs.sh`, `e10-logs-check.py`, `e10/16-logs-limite.php`); documentação (este
relatório, o plano 001, `README.md` e `tests/Performance/README.md`); evidências (`docs/evidencias/e10-2026-11-24/logs/`).
Varredura dos arquivos versionáveis por sequências longas, chaves e senhas: só aparecem os marcadores sintéticos
dos roteiros e a chave e senha sintéticas de laboratório já existentes; nenhum token, e-mail real ou credencial real.
Sem commit nem push.

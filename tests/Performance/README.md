# Ensaios de auditoria contextual

Use uma cópia de código com as dependências da imagem `prospera-app:e08-base` e a imagem `postgres:18` disponíveis. Antes de executar, confira as rotas IPv4 e as sub-redes Docker. Passe em `E08_SUBNET` uma faixa livre, sem sobreposição com interfaces físicas, VPN ou redes Docker. Use uma faixa distinta por execução simultânea. Os harnesses usam rede interna, PostgreSQL em tmpfs, nomes próprios por processo e removem seus recursos ao sair.

- `bash tests/Performance/e08-check.sh /copia/e08 after` mede página, contagem, busca, filtros, planos, escrita e memória. Checa os totais por perfil, intercala quatro contas e uma inspeção, e valida dez escritas concorrentes com dois workers. `E08_VOLUMES='200000 1000000'` recorta os volumes. `E08_ENDURANCE=0` omite os oito lotes de 450 requisições. Preserve a saída de cada execução ao comparar revisões.
- `bash tests/Performance/e08-migrations.sh /copia/e07 /copia/e08` evolui a E07 com dados sintéticos, testa SQL incompatível, UPDATE, DELETE e TRUNCATE, consolidação com savepoint, exportação e restauração da origem antiga, rollback, reaplicação e instalação limpa. Compara também o custo de escrita em lote com e sem a proteção, mantendo os índices da E08.
- A suíte PHP usa SQLite em memória. Os testes dos gatilhos e da concorrência pertencem a esses harnesses. Para validação visual, use uma cópia isolada do ambiente `e04-stack.sh` com `E06_CONTEXT=1`, uma sub-rede explícita e usuários sintéticos com as permissões de auditoria pertinentes. Confira 1440, 1024, 768, 390 e 320 px.

Na versão sem a tela Pessoas, as escritas de prova usam o cadastro de Cliente e a edição dos dados compartilhados. A medição de escrita dessa versão inclui o vínculo comercial.

## Estabilização complementar de 23/11/2026

`bash tests/Performance/run-octane.sh "$PWD" 1` e a mesma chamada com `2` usam PostgreSQL em tmpfs e FrankenPHP descartáveis, rede interna e e-mail em log. Não apontar para bases existentes. A execução remove seus containers e rede ao sair.

O cliente deixou de acessar `/conta/pessoas` e `/conta/pontos`: lista e formulário são de Clientes na unidade da URL; escritas usam Clientes na empresa da URL. Confere payload, HTTP 403/404/422/204, total após escrita, revogação e autoria real/representada. A fixture exige também o escopo empresarial na auditoria. Listagem e escrita comerciais constituem nova linha de base, sem comparação direta com Pessoas ou Pontos. As demais telas preservam a responsabilidade original, mas só são comparáveis com ambiente, carga e versão controlados.

`vendor/bin/phpunit tests/Performance/AccountRequestsTest.php` continua usando SQLite em memória imposto pelo bootstrap. Verifica orçamento estrutural, ausência de SQL repetido e payload de 25 Clientes sintéticos; os tempos desse teste não são métricas de PostgreSQL. Medições HTTP e limites estão no registro complementar do plano 001. Nenhum harness E08 precisa ser executado para essa estabilização.

## Retomada da E08

A retomada usa a implementação de `7cd9b9d`, sem recriar a auditoria. Informe `E08_IMAGE=prospera-app:latest` quando a imagem `prospera-app:e08-base` não estiver disponível. Use uma cópia imutável da fonte e do diretório de harnesses por execução, para não misturar revisões durante uma medição.

O runner exige o marcador final `verificado` do cliente, além dos códigos HTTP e das asserções de payload. Uma exceção renderizada pelo bootstrap não é aceita como conclusão. A medição de cadastro atual inclui Pessoa e relação de Cliente: ambos os eventos são conferidos, com conta, autoria e nó próprios. Os nomes sintéticos são únicos entre repetições. A corrida exige dez eventos, cadeia coerente e autor correto, e informa o worker de cada resposta.

A alternância entre contas e inspeção é repetida na instância com dois workers. Requisições sequenciais podem ser atendidas por apenas um deles; os IDs observados são informados. As escritas concorrentes exercitam os dois. As instâncias descartáveis usam limite de reciclagem de 20 mil requisições para separar reciclagem programada de vazamento de contexto. Isso não modifica a configuração oficial.

O harness de migrations seleciona por nome as quatro migrations da E08, em vez de presumir que sejam as últimas migrations do projeto. A instalação limpa continua executando o esquema atual completo. A cópia anterior deve ser um checkpoint pré-E08, como `e0d3cce`, com os dados sintéticos e tabelas de origem esperados. Exportação/restauração das tabelas antigas e testes destrutivos ocorrem somente no PostgreSQL temporário do próprio runner.

## E09: laboratório, concorrência e navegador

Relatório e resultados: `docs/plano-de-acoes/001-e09-validacao-integrada.md` e
`docs/evidencias/e09-2026-11-23/`. Os cenários E09 usam código atual, PostgreSQL 18 e FrankenPHP,
sem adaptar a suíte PHPUnit para PostgreSQL. Não execute os scripts em bases oficiais.

Preparação (usa a imagem local `prospera-app:latest`, com dependências PHP e FrankenPHP):

```sh
npm run build
export E09_PREFIX=prospera-e09-lab-revisao
export E09_PORT=18099
bash tests/Performance/e09-lab.sh up
# Aguarde GET http://127.0.0.1:18099/up responder 200 e confira docker logs "$E09_PREFIX-app".
```

O script copia o código de uma montagem somente leitura para o container descartável. Não executa
Compose nem monta volumes da aplicação. PostgreSQL fica em rede interna e tmpfs. Só o app recebe uma
segunda rede e publica a porta em 127.0.0.1. O transporte de e-mail é `array`, sem SMTP. Os usuários
sintéticos usam `password`. A finalização exige o mesmo prefixo e só remove containers com label E09:

```sh
bash tests/Performance/e09-lab.sh down
```

Os fixtures precisam de banco novo. Os ensaios alteram o estado sintético: execute a seleção de cinco
contas antes da suspensão de administradores, os aceites antes de reutilizar seus tokens e os convites
antes de outro vínculo do mesmo destinatário. Para repetir desde o início, descarte o laboratório e
prepare outro prefixo. Não apague linhas de histórico para reaproveitar os fixtures.

Chromium e Playwright ficam fora das dependências do projeto:

```sh
npm install --prefix /tmp/prospera-e09-browser playwright
/tmp/prospera-e09-browser/node_modules/.bin/playwright install chromium
mkdir -p /tmp/prospera-e09-evidence
node tests/Browser/e09.mjs
node tests/Browser/e09-workspace.mjs
```

`E09_PLAYWRIGHT` pode indicar outro `playwright/index.mjs`, e `E09_URL` troca a origem dos scripts.
As capturas ficam em `/tmp/prospera-e09-evidence`. Os testes usam login real e cookies do navegador,
sem endpoint que injete autenticação. Para aceite visual, execute primeiro a corrida de aceite da conta
A (para a identidade já ter um vínculo), emita outro convite com `e09-invitations.php`, copie somente
o token sintético de `/tmp/e09-browser-invite.txt` do container para
`/tmp/prospera-e09-browser/invite.txt` e execute `tests/Browser/e09-invite.mjs`. `E09_TOKEN_FILE` altera
esse caminho, e `E09_ACCOUNT` identifica a conta esperada, com padrão `laboratorio-conta-2`.

Provas PostgreSQL independentes do PHPUnit:

```sh
docker exec "$E09_PREFIX-app" php tests/Performance/e09-race.php setup
# Dois processos reais, não duas chamadas sequenciais:
for process in 1 2; do
  docker exec "$E09_PREFIX-app" php tests/Performance/e09-race.php accept &
done
wait
for process in 1 2; do
  docker exec "$E09_PREFIX-app" php tests/Performance/e09-race.php issue &
done
wait
for process in 1 2; do
  docker exec "$E09_PREFIX-app" php tests/Performance/e09-race.php code "$process" &
done
wait
docker exec "$E09_PREFIX-app" php tests/Performance/e09-constraints.php
```

Confira explicitamente os marcadores de resultado: uma exceção renderizada pelo bootstrap não garante
exit code diferente de zero. O aceite deve produzir `accepted` e `denied` com 410. A emissão deixa um
pendente e um revogado; a criação deixa dois códigos distintos. `permission-setup` seguido de dois
processos `permission 1` e `permission 2` verifica o cargo concorrente. `admin-setup` seguido de
`suspend-admin 1` e `suspend-admin 2` protege o último administrador. O script `e09-revoke-race.php`
prepara outro convite e permite manter a trava enquanto outro processo tenta aceitar. A prova de
suspensão usa `e09-suspension.php` e um trigger temporário de atraso exclusivamente no laboratório;
seus resultados e a ordem de execução estão registrados nas evidências.

`e09-migrations.php` verifica rollback ambíguo, preenchimento de códigos e hashes de preservação no
banco sintético. Execute essa prova em laboratório separado da validação visual, porque ela acrescenta
contas sem estrutura comercial. A instalação e evolução completas, rollback de vínculo único e
backup/restauração estão no relatório, com SQL e logs do banco anterior isolado.

Para medir HTTP, injete `Tests\Performance\OctaneMetrics` somente na cópia do bootstrap descartável,
como os runners das E03/E08 fazem. Não altere o bootstrap oficial. No laboratório E09, suba com
`E09_METRICS=1 bash tests/Performance/e09-lab.sh up`: o script injeta o middleware apenas na cópia do
container e falha se a injeção não ocorrer. O cliente E09 usa login e CSRF reais:

```sh
docker exec -e E09_REQUESTS=6000 "$E09_PREFIX-app" php tests/Performance/e09-client.php
# Exija o marcador JSON e09=passed, além de conferir o exit code.
```

Para dois workers, suba uma segunda instância no mesmo container descartável, com
`OCTANE_STATE_FILE=/tmp/e09-two.json`, `--port=8001 --admin-port=2020 --workers=2` e
`--max-requests=20000`. Execute o cliente com `HARNESS_URL=http://127.0.0.1:8001`. Para conferir a flag,
inicie outra instância com `PROSPERA_MULTIPLE_ACCOUNTS=false`, state file e portas exclusivos. Não mude
variáveis ou workers dos containers oficiais. Os endpoints equivalentes e a quantidade de amostras
estão discriminados no JSON, incluindo queries, duplicatas, mediana, P95 e memória. As provas de
latência não usam SQLite e não substituem uma medição no hardware de implantação.

`e09-ui-fixtures.php` acrescenta conta inativa, identidade revogada e registros para paginação real.
Execute uma vez, antes de `tests/Browser/e09-tabs.mjs`. `e09-login-fixtures.php` cria dez identidades
por perfil de 1/2/5 contas para `tests/Browser/e09-login-performance.mjs`, respeitando o throttling real.
`e09-route-queries.php <caminho> [e-mail]` lista o SQL de uma requisição GET como numa requisição nova
do worker (instâncias scoped descartadas), para comparar consultas entre estados de dados no laboratório.
`e09-ui-fixtures.php` também cria `help-only@e09.test`, com cargo só de Ajuda, usado por
`tests/Browser/e09-limits.mjs`, que verifica a entrada direta na Ajuda e o limite de leitura de convites
em português nas cinco larguras. Execute esse script com pelo menos um minuto de distância de outro
ensaio de convite da mesma identidade, porque o limitador real continua ativo.
Esses fixtures são exclusivamente sintéticos e devem rodar depois dos cenários de último administrador,
pois acrescentam administradores. Os scripts não são seeders operacionais da aplicação.

`e09-invitation-samples.php` prepara dez identidades existentes e emite convites com entrega somente em
memória, medindo a fronteira da Action. Copie `/tmp/e09-accept-samples.json` do container para
`/tmp/prospera-e09-browser/accept-samples.json` (tokens exclusivamente sintéticos, não versione esse
arquivo), injete as métricas somente no laboratório e execute `tests/Browser/e09-invitation-performance.mjs`.
O script recebe `E09_URL` e `E09_SAMPLES_FILE`, usa login e CSRF reais e mede dez aceites HTTP válidos.
Ele não compara o tempo da Action CLI com o tempo de requisição HTTP. Use um laboratório novo para repetir.

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

# Performance

Contrato obrigatório para código novo e alterado. O custo real é medido: número de queries é uma das medidas, não o custo inteiro. Detalhes de método e linha de base nas seções 14 e 15 do [plano 001](../plano-de-acoes/001-arquitetura-organizacional-prospera.md).

## Contexto por requisição no Octane e FrankenPHP

O Prospera roda em workers persistentes do FrankenPHP (`octane:frankenphp`). Um objeto que sobrevive à requisição pode levar dados de uma conta para outra.

- **Escopo por requisição:** `AccountContext`, `EffectivePermissions`, `OrganizationContext` e `AuditContext` usam binding `scoped`. Serviços com estado de conta, vínculo, contexto ou autorização nunca são `singleton`.
- **Sem estado estático:** é proibido estado estático mutável em Actions, Queries, Models, Providers e middleware. Constantes e tabelas fixas são permitidas.
- **Sem objetos de requisição guardados:** serviços longevos não guardam `Request`, `User`, `Session` nem o contexto em propriedades. Recebem esses valores por parâmetro.
- **Memoização:** dentro da requisição, pelo próprio serviço `scoped`. `once()` só para valores derivados do próprio objeto de modelo.
- **Sem cache persistente de autorização:** permissões não ficam em cache entre requisições nem na sessão. A revogação vale na requisição seguinte. Um cache futuro exige medição, aprovação e versão por vínculo.
- **Cache e jobs:** toda chave de cache inclui o tenant e o nó. Jobs levam o contexto explícito e reautorizam ao executar.
- **Recursos do Swoole:** `Octane::concurrently`, ticks, `Octane::table` e o cache `octane` não estão disponíveis no FrankenPHP e não são usados.

## Conteúdo público (frente Institucional)

- `PublicContext` e `ProfileResolver` usam binding `scoped`. A chave de cache de página inclui tenant e nó, `site_id`, `content_version` e a revisão publicada. Invalidar é trocar a versão, sem varredura nem job.
- Resposta que depende da data (lotação, exceção de horário) tem `valid_until` no início do próximo dia civil do fuso do estabelecimento, conferido na leitura, com revalidação a cada acesso (`no-cache` com `ETag`) e sem servir conteúdo vencido. O estado "aberto agora" não vai no HTML em cache.
- Processamento síncrono só com orçamento medido de memória, tempo e concorrência; se a medição reprovar, a própria etapa incorpora a fila. Nenhuma etapa usa fila ou agendador sem essa medição, e a S14 é a primeira que os exige por contrato. Redis só após medição com worker FrankenPHP real. Detalhes em [Institucional, equipe e sites](institucional.md).

## Autorização

- As permissões efetivas são resolvidas uma vez por requisição, na primeira checagem, em no máximo 2 queries (atribuições do vínculo e permissões dos cargos distintos). Esse número não cresce com o número de checagens, atribuições ou estabelecimentos.
- As linhas retornadas são atribuições mais as permissões dos cargos distintos, nunca o produto das duas.
- O resultado de cada checagem é memorizado na requisição.
- O seletor organizacional custa 1 consulta nas telas da conta, que enumera as unidades navegáveis numa junção de `establishments` com `companies`, sem laço por unidade (E06). A resolução do código da URL custa mais 1 consulta, só nos grupos com contexto.
- Consultas consolidadas usam o descritor autorizado (`tenant inteiro` ou `company_id in (...) or establishment_id in (...)`), derivado das atribuições já carregadas, sem query extra e sem laço sobre os estabelecimentos.

## Consultas

- **Escopo explícito:** toda consulta passa por uma Query com o tenant e, conforme o nó, a empresa, a unidade ou o descritor autorizado. Escopos globais do Eloquent não são usados para isolamento.
- **Sem N+1:** o número de queries de uma listagem não cresce com o número de linhas. Use eager loading com colunas selecionadas, `withCount` e `withExists`. `Model::preventLazyLoading()` fica ativo nos testes a partir da E03.
- **Banco faz o trabalho:** filtros, ordenação, paginação, contagem e agregação acontecem no SQL, antes de paginar. É proibido carregar registros para filtrar ou agregar em PHP.
- **Colunas necessárias:** listagens selecionam só as colunas exibidas.
- **Paginação:** `PageSize` e `ApiPage`, como nas telas atuais. Exportações futuras usam a mesma Query, em lotes.

## Índices

- Cada índice novo é justificado por uma consulta existente ou planejada, com o impacto na escrita registrado no relatório da etapa.
- Índices compostos começam pela coluna organizacional do filtro quando o filtro for seletivo nos cenários medidos.
- Usar índice **não** é obrigatório. Varredura sequencial, bitmap ou outra estratégia são aceitas quando o plano medido for melhor. O critério é tempo e buffers medidos, não a presença de índice.
- Índices que o planejador não usa nos cenários realistas são removidos antes de concluir a etapa.

## Medição

Toda etapa ou módulo com consulta nova ou alterada registra, antes e depois:

| Medida | Como |
| --- | --- |
| Quantidade de queries e queries repetidas | `DB::listen` no harness `tests/Performance` (E03) |
| Latência | Tempo de banco e tempo total por requisição; por HTTP com 1 e 2 workers FrankenPHP reais no ambiente isolado |
| Linhas retornadas | Por query crítica e pelo resolvedor de permissões |
| Memória | Pico por requisição e memória por worker durante a carga, sem crescimento contínuo |
| Plano de execução | `EXPLAIN (ANALYZE, BUFFERS)` em PostgreSQL nos cenários grandes |

- **Metas:** são fixadas depois da linha de base medida de cada cenário. As únicas metas fixadas antes são as estruturais: queries de autorização constantes e listagens sem N+1.
- **Onde medir:** tempo, planos, seletividade e concorrência só valem em PostgreSQL, num ambiente isolado com banco próprio e `ANALYZE` após a carga. Nunca no banco da aplicação.
- **Suíte padrão:** continua em SQLite em memória (`tests/bootstrap.php`), sem remover esse isolamento. O harness de performance fica fora dela, no grupo `performance`.
- **Workers reais:** mudanças em contexto, autorização ou auditoria são verificadas com FrankenPHP e `--workers=1`, alternando centenas de requisições entre contas, inclusive com respostas 403, 404 e 422. Cada resposta contém só dados do próprio contexto, e o `AuditContext` de uma escrita não herda o autor da anterior.

## Cenários de dados

1 empresa e 1 estabelecimento; 1 e 3; 3 empresas com 2 a 5 unidades; 10 empresas; 50 estabelecimentos; usuários com 1, 5 e 30 atribuições; cargos com 10, 40 e todas as permissões; auditoria com 10 mil e 200 mil registros, concentrada e espalhada.

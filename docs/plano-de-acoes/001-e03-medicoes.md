# E03: Medições de contexto da conta e RBAC

Execução em 20/11/2026. Referência anterior `c9719a1`. Ambiente privado com PostgreSQL 18.7, PHP 8.5.13, Laravel 13.43.0, Octane 2.25.0 e FrankenPHP 1.13.6. O runner cria uma rede Docker interna sem portas publicadas e um banco descartável em tmpfs. A imagem local fornece o runtime e as dependências. A aplicação de cada revisão é copiada para o container. A instrumentação e a rota de sessão sintética são carregadas somente nessa cópia descartável.

## HTTP com um worker

Dez amostras sequenciais por rota e perfil. Medianas por requisição, incluindo sessão em banco, carregamento do usuário e escrita da sessão. Pico PHP mede bytes acima do início da requisição. As permissões do perfil administrador somam 56 slugs de conta e pessoais. A Central de Ajuda reproduziu também a referência de 26 consultas totais, 13 consultas SQL ao catálogo e 24 duplicadas em SQLite com `actingAs`. Após a mudança, o mesmo teste registrou 2, 1 e 0.

| Rota e perfil | SQL total | SQL de slugs | Duplicadas | HTTP (ms) | Banco (ms) | Pico PHP (bytes) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| `/conta` | 11 → 6 | 3 → 1 | 4 → 0 | 5,46 → 3,40 | 2,46 → 1,25 | 98432 → 93840 |
| `/conta/pessoas` | 14 → 12 | 2 → 1 | 2 → 0 | 10,51 → 9,14 | 3,21 → 2,56 | 373360 → 382576 |
| `/conta/pessoas/cadastrar` | 11 → 7 | 3 → 1 | 4 → 0 | 6,31 → 4,29 | 2,67 → 1,54 | 108600 → 103376 |
| `/conta/etiquetas` | 11 → 7 | 3 → 1 | 4 → 0 | 6,25 → 4,28 | 2,71 → 1,57 | 107048 → 98696 |
| `/conta/cargos` | 9 → 7 | 2 → 1 | 2 → 0 | 5,08 → 4,70 | 2,17 → 1,68 | 99544 → 97896 |
| `/conta/cargos/cadastrar` | 8 → 6 | 2 → 1 | 2 → 0 | 9,21 → 8,96 | 2,04 → 1,54 | 269528 → 273000 |
| `/conta/auditoria` | 12 → 10 | 2 → 1 | 2 → 0 | 6,55 → 5,91 | 2,64 → 2,19 | 154824 → 164088 |
| `/conta/usuarios` | 11 → 8 | 2 → 1 | 2 → 0 | 5,89 → 4,72 | 2,44 → 1,69 | 142680 → 148776 |
| `/ajuda` | 29 → 5 | 13 → 1 | 24 → 0 | 14,84 → 4,70 | 7,57 → 1,10 | 298048 → 255016 |
| `/ajuda` (member) | 29 → 5 | 13 → 1 | 24 → 0 | 15,05 → 3,96 | 7,49 → 1,08 | 246256 → 205376 |
| `/ajuda` (medium) | 29 → 5 | 13 → 1 | 24 → 0 | 14,66 → 4,65 | 7,30 → 1,11 | 293024 → 241872 |

`member` tem 10 permissões e `medium` tem 40. O pico PHP subiu em Pessoas, no formulário de Cargos e em Auditoria nesta amostra. A maior diferença foi inferior a 10 KiB. A Central de Ajuda economizou cerca de 43 KiB para o administrador. Os tempos são medições locais em dados sintéticos.

## HTTP com dois workers configurados

| Rota e perfil | SQL total | SQL de slugs | Duplicadas | HTTP (ms) | Banco (ms) | Pico PHP (bytes) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| `/conta` | 11 → 6 | 3 → 1 | 4 → 0 | 6,21 → 3,50 | 2,59 → 1,29 | 98416 → 93840 |
| `/conta/pessoas` | 14 → 12 | 2 → 1 | 2 → 0 | 11,47 → 9,36 | 3,39 → 2,65 | 373376 → 381184 |
| `/conta/pessoas/cadastrar` | 11 → 7 | 3 → 1 | 4 → 0 | 7,21 → 4,35 | 2,91 → 1,56 | 108584 → 103376 |
| `/conta/etiquetas` | 11 → 7 | 3 → 1 | 4 → 0 | 6,67 → 4,68 | 2,88 → 1,62 | 107032 → 98696 |
| `/conta/cargos` | 9 → 7 | 2 → 1 | 2 → 0 | 6,05 → 4,85 | 2,40 → 1,67 | 99528 → 97896 |
| `/conta/cargos/cadastrar` | 8 → 6 | 2 → 1 | 2 → 0 | 8,91 → 8,00 | 2,07 → 1,43 | 269496 → 273000 |
| `/conta/auditoria` | 12 → 10 | 2 → 1 | 2 → 0 | 7,06 → 5,51 | 2,80 → 2,08 | 154896 → 164032 |
| `/conta/usuarios` | 11 → 8 | 2 → 1 | 2 → 0 | 6,33 → 5,07 | 2,47 → 1,73 | 142696 → 147384 |
| `/ajuda` | 29 → 5 | 13 → 1 | 24 → 0 | 16,62 → 5,18 | 7,81 → 1,15 | 298064 → 255016 |
| `/ajuda` (member) | 29 → 5 | 13 → 1 | 24 → 0 | 14,66 → 4,26 | 7,51 → 1,12 | 246360 → 205384 |
| `/ajuda` (medium) | 29 → 5 | 13 → 1 | 24 → 0 | 15,81 → 4,52 | 7,49 → 1,10 | 293136 → 241856 |

As amostras sequenciais alcançaram um worker na linha de base de dois workers e dois workers em pelo menos uma rodada com o código atualizado. O teste de isolamento exige o mesmo worker usando a configuração de um worker. A medição cobre latência sequencial e pico PHP por requisição. Capacidade sob carga concorrente e RSS por thread ficam fora desta medição.

## Custo isolado do RBAC em PostgreSQL

Medianas de dez repetições com 12 checagens `can()` e uma leitura da lista para o JSON por repetição. O contador de linhas de slugs usa a cardinalidade do resultado vezes o número de consultas idênticas. A consulta de conta retorna uma linha para todos os perfis deste cenário. O tempo PHP é a diferença aproximada entre o tempo total da checagem e a soma dos tempos de consultas informados pelo Laravel.

| Slugs efetivos | SQL de autorização | Linhas de slugs | Linhas de conta | Banco (ms) | Total (ms) | PHP (ms) | Pico PHP (bytes) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 10 | 26 → 2 | 130 → 10 | 13 → 1 | 6,07 → 0,48 | 9,95 → 1,00 | 3,91 → 0,52 | 39000 → 27544 |
| 40 | 26 → 2 | 520 → 40 | 13 → 1 | 6,30 → 0,48 | 9,87 → 1,02 | 3,51 → 0,54 | 54152 → 42696 |
| 56 | 26 → 2 | 728 → 56 | 13 → 1 | 6,59 → 0,52 | 10,80 → 1,07 | 4,06 → 0,55 | 61760 → 50304 |

## Índices

`EXPLAIN (ANALYZE, BUFFERS)` com 300 mil vínculos cargo/permissão, 1,2 milhão de vínculos pessoa/etiqueta e 400 mil usuários. As tabelas temporárias reproduzem as chaves usadas pelas consultas de interesse. A coluna de tempo registra uma execução antes e depois de criar os índices e rodar `ANALYZE`.

| Índice | Consulta avaliada | Linhas encontradas | Plano e tempo |
| --- | --- | ---: | --- |
| `permission_role(permission_id)` | Busca reversa para exclusão de uma permissão e sua FK em cascata | 4.690 de 300.000 | Varredura sequencial → bitmap. 14,278 → 3,148 ms |
| `person_tag(tag_id)` | Contagem e busca de pessoas por etiqueta | 120 de 1.200.000 | Varredura sequencial → bitmap. 47,833 → 0,353 ms |
| `users(role_id)` | Busca reversa da FK antes de remover um cargo | 13 elegíveis de 400.000, `LIMIT 1` | Varredura sequencial → index scan. 16,158 → 0,007 ms |

A contagem do último administrador já usava o índice de conta e ficou próxima de 0,03 ms. A busca direta pelo cargo continuou atendida pela chave primária de `permission_role`. No conjunto pequeno da aplicação, o SQL real do RBAC devolveu 40 slugs com o mesmo plano e quatro buffers compartilhados com e sem o índice reverso. Os índices acrescentaram aproximadamente 2,0 MiB, 7,9 MiB e 3,3 MiB nas três tabelas de prova. Cada índice acrescenta manutenção às escritas da respectiva tabela. A latência de escrita sob concorrência e o tempo de bloqueio no volume real de implantação seguem sem medição. A migration foi aplicada, revertida e reaplicada no PostgreSQL isolado.

## Revalidação em 20/11/2026

O runner repetiu as nove rotas e os três perfis da Central de Ajuda contra a referência `c9719a1` e a E03 com um worker. Com dois workers configurados na E03, as amostras atingiram os dois workers. Na Central de Ajuda, o administrador passou novamente de 29 para 5 consultas totais, de 13 para 1 consulta de slugs e de 24 para zero duplicatas. A mediana HTTP caiu de 17,08 para 4,80 ms com um worker. O tempo de banco caiu de 7,92 para 1,14 ms e o pico PHP por requisição passou de 298.032 para 255.008 bytes. Com dois workers, a mediana HTTP da E03 foi 4,92 ms.

O teste ampliado executou 300 requisições sequenciais em um único worker, alternando as duas contas e verificando o contexto e as permissões após respostas 403, 404 e 422. A memória PHP no início da requisição da segunda conta foi de 9.321.744 bytes nas cinco amostras da E03, distribuídas entre a primeira e a última rodada. A revogação continuou efetiva com a sessão ativa. A gravação durante a inspeção registrou o administrador SaaS como autor e o usuário da conta como representado. A gravação normal seguinte registrou somente seu próprio autor. A referência também passou nessa sequência.

O rollback e a reaplicação da migration passaram novamente. Os três planos de busca reversa voltaram a usar os novos índices, com tempos de 14,386 para 3,532 ms em `permission_role`, de 45,299 para 0,347 ms em `person_tag` e de 15,854 para 0,006 ms em `users`.

## Reprodução

- `php -d extension=pdo_sqlite -d extension=iconv vendor/bin/phpunit` executa a suíte funcional protegida por `tests/bootstrap.php`.
- `php -d extension=pdo_sqlite vendor/bin/phpunit tests/Performance/AccountRequestsTest.php` registra métricas SQLite e aplica os orçamentos. Para medir a referência, copie o mesmo teste para a revisão anterior e execute com `E03_BASELINE=1`.
- `bash tests/Performance/run-octane.sh /caminho/da/revisao 1` executa a comparação PostgreSQL e o teste de isolamento real. Repita com `2` para medir latência com dois workers configurados.
- Execute o runner com `E03_INDEX_PLANS=1 E03_CHECK_MIGRATION=1` na revisão com a migration para coletar os planos e testar o rollback. Os logs do runner registram uma linha JSON por amostra.

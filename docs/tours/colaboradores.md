# Tour de Colaboradores

Artigo: [Colaboradores](../ajuda/colaboradores.html). Telas: `/conta/colaboradores`, `/conta/colaboradores/cadastrar` e `/conta/colaboradores/{id}/editar` (S04). Executor na barra superior, configurado em `resources/js/app/guidance.ts`. Nenhum executor ou atalho de tutorial dentro de modal.

A tela é única para a conta: uma linha por Pessoa, com os vínculos das empresas que o acesso alcança.

## Listagem

| Ordem | Alvo | Título | Orientação |
| --- | --- | --- | --- |
| 1 | `PageHeaderTour` | Moldura | Passos compartilhados da moldura, sem duplicar textos. |
| 2 | `employees-register` | Cadastrar | Busca por CPF ou CNPJ para reaproveitar a pessoa e escolha das empresas. |
| 3 em diante | `BusinessListTour` | Buscar, filtrar, colunas e paginação | Busca, filtros de situação do vínculo, empresa (só com mais de uma) e acesso à conta (só para quem lista usuários). Cada linha é uma pessoa. Colaborador não é usuário. |

## Cadastro e ficha

| Tela | Alvo | Título | Orientação |
| --- | --- | --- | --- |
| Cadastro | `employee-companies` | Empresas | Cada empresa marcada recebe um vínculo independente, numa única operação. Passo ausente com uma só empresa. |
| Cadastro | `person-identity` | Pessoa | Busca pelo documento completo para reaproveitar a pessoa sem duplicar. |
| Cadastro | `employee-tags` | Etiquetas | Etiqueta Colaborador fixa, derivada do vínculo, mais as etiquetas convencionais do módulo. |
| Cadastro | `employee-work-contacts` | Contatos profissionais | Próprios de cada empresa, sem alterar os contatos centrais. |
| Ficha | `person-identity` | Dados centrais da pessoa | Valem para todas as empresas e papéis. |
| Ficha | `employee-tags` | Etiquetas | Derivadas, somente leitura. |
| Ficha | `employee-links` | Vínculos empresariais | Tabela com uma linha por empresa; o lápis abre a janela de edição do vínculo. |
| Ficha | `employee-assignments` | Lotações | Cabeçalho da coluna Lotações da tabela de vínculos; o botão de cada linha abre a janela das lotações (o tour não a abre). O que é lotação, diferença entre estabelecimento, departamento e função, principal, vigência (na data local do fuso da unidade) e a origem dos contatos profissionais (herdados, personalizados ou sem contato local). |
| Ficha | `employee-add-link` | Vincular a outra empresa | Cria vínculos novos sem alterar os existentes. |

Na listagem, os filtros de estabelecimento, departamento, função e situação da lotação ficam depois do filtro de empresa. O tour não abre os modais de lotação nem grava dados.

Os passos cujo alvo não existe na tela (por permissão ou por haver uma só empresa) são ignorados. O tour não grava dados e não abre modais. Revisar alvos e ordem quando a posição dos controles mudar.

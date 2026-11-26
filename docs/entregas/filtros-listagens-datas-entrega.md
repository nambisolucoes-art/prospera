# Filtros das listagens e seletor de data

A barra de filtros pertence ao `BusinessList`. Busca, seletores e datas ficam na mesma linha: a busca ocupa a maior parte e os demais controles dividem o restante. Abaixo de 560 px cada controle ocupa a linha inteira, e as datas ficam lado a lado. As telas não criam mais contêineres próprios de filtro (`saas-search` e `audit-filters` foram removidos).

- `BusinessListSearch`: campo de busca com a lupa clicável à direita. Enter e a lupa executam a busca imediatamente.
- `useBusinessListFilters`: busca com atraso de 350 ms, filtros imediatos, preserva os demais parâmetros (inclusive `per_page`) e volta à primeira página.
- `DatePicker`: Bootstrap Datepicker do tema, o mesmo usado pelo tema e outros sistemas, com máscara DD/MM/AAAA, idioma pt-BR, botão **Limpar** e limites `min` e `max`. Recebe e emite `AAAA-MM-DD`; datas incompletas ficam apenas no campo, e datas inexistentes são indicadas abaixo dele sem serem aplicadas. O botão do calendário usa o cinza neutro do `InputGroup`, como na tema, e o interior segue o tema original: células arredondadas, destaque amarelo para hoje e verde-água para a data selecionada.

Telas atualizadas: Pessoas, Etiquetas (busca, módulo e situação), Cargos (busca e origem), Auditoria (busca, seletores e datas) e Contas da plataforma. No servidor, `TagFiltersData` e `RoleFiltersData` sanitizam os filtros, e as Queries filtram dentro da conta com `whereLike`, sem diferenciar maiúsculas e minúsculas, com os curingas escapados. As consultas não alteram dados e não geram eventos de auditoria. As rotas mantêm as permissões de listar existentes.

Ajuda e tours atualizados: `docs/ajuda/etiquetas.md`, `docs/ajuda/cargos-permissoes.md`, `docs/ajuda/auditoria.md`, `docs/tours/etiquetas.md`, `docs/tours/cargos.md`, `docs/tours/auditoria.md`, `docs/tours/componentes-compartilhados.md` e os textos de filtros em `resources/js/app/guidance.ts`. Os alvos `business-filters`, `people-search` e `people-suppliers` foram preservados.

## Validação

Chromium em 1440, 390 e 320 px, com uma conta de teste em ambiente isolado. Pessoas, Etiquetas, Cargos e Auditoria conferidas sem rolagem horizontal. Busca digitada e por Enter, filtro de situação preservando a busca, filtro de origem, seleção de dia pelo calendário, digitação de data completa, data inexistente com aviso e calendário aberto no celular. Sem erros no console. Arquitetura, tipos, build e suíte PHP aprovados, incluindo testes de busca, filtros e escopo por conta para Etiquetas e Cargos.

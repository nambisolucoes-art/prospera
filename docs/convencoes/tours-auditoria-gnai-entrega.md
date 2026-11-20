# Tours e detalhe de auditoria no padrão do GNAI

Referência conferida no GNAI rodando em `http://localhost/auditoria`, com acesso autenticado, e nos componentes `TableList/tableListTour.ts`, `UI/GuidedTour/`, `Audit/auditTours.ts`, `Audit/Details/AuditRecordModal.tsx` e seu CSS. A referência foi consultada sem alterar seu código.

## Alterações

- Passos comuns de título, trilha e ações extraídos para `PageHeaderTour`. Listagens de Contas, Usuários da plataforma, Pessoas, Etiquetas, Cargos e Auditoria reutilizam `BusinessListTour`.
- O tour apresenta filtros, cadastro quando permitido, Colunas, menu de opções, tabela, separador de largura, demonstração de largura no desktop, ações, quantidade e paginação.
- Menus abertos para demonstração são fechados ao sair do passo. Larguras temporárias ficam separadas das preferências persistidas. O tour não clica em Salvar, nem muda página ou quantidade de registros.
- Destaque com área iluminada e pulso, transições, grupos, progresso, mensagens explicativas das demonstrações e lista Ver todos os passos. Esc, retorno do foco e movimento reduzido preservados.
- Modal da auditoria com abas Formatado e Log técnico, marcadores de ação e tipo, data e fechamento no rodapé. Alterações mostram valores antigos riscados e novos destacados; cadastros e exclusões usam Valor registrado e Valor ao excluir. Listas mostram itens adicionados e removidos.
- O tour abre o primeiro detalhe autorizado, apresenta suas partes, demonstra Log técnico quando permitido e fecha somente a janela aberta pela demonstração. O destaque acompanha conteúdo assíncrono e a animação do modal. O foco do modal não compete com o foco do tour.

## Verificação

Arquitetura, TypeScript e build aprovados. Chrome em 1440, 390 e 320 px: percurso dos passos de Pessoas e Auditoria, menus de colunas e quantidade, tabela, ações e paginação; no desktop, demonstração com alteração visível de largura e restauração exata, inclusive do localStorage. Abas do modal verificadas por clique e setas do teclado. Cadastro de Contas e detalhe da auditoria da plataforma conferidos nas três larguras.

Verificados saltos por Ver todos os passos, Esc durante demonstração de largura, fechamento dos menus, fechamento do modal, retorno do foco e movimento reduzido. Perfil sem permissão técnica não recebe a aba nem o passo técnico. Sem rolagem horizontal da página; a área de código tem sua própria rolagem.

Capturas temporárias em `/tmp/tour-*.png`, `/tmp/audit-formatted-new-*.png`, `/tmp/audit-technical-new-*.png` e `/tmp/platform-*-tour-*.png`. Dados temporários da validação removidos ao concluir, preservando os demais usuários e contas.

## Padronização da listagem de auditoria

O botão de informação passou a usar `business-list__actions`, com a mesma cor azul, tamanho, espaçamento e foco das ações de Pessoas, Etiquetas e Cargos. A largura fixa de Ações passou para 78 px no desktop e 64 px no modo compacto, exibindo o título inteiro. Registro recebeu maior proporção de espaço, com ajuste das larguras relativas de Ação, Quem fez e Quando.

Chrome em 1440, 390 e 320 px, na auditoria da conta e da plataforma: título Ações sem reticências, botão com dimensões e estilos iguais aos de Pessoas, abertura e fechamento do modal e ausência de rolagem horizontal da página. Build, arquitetura e TypeScript aprovados. Capturas em `/tmp/audit-standard-*.png`. Roteiro e artigo revisados; alvos e ordem do tour preservados.

## Miniabas inverse

Formatado e Log técnico reutilizam as classes `button--inverse` e `button--sm` do tema, no padrão consultado em `GNAI2/SegmentedControl` e `site-visdom-estagio-2026/css/components.css`: fundo grafite `#4c5667`, texto branco, botões unidos e sombra interna no selecionado. Foco por teclado branco dentro do botão. Alvos e comportamento do tour preservados.

Build, arquitetura e TypeScript aprovados. Chrome em 1440, 390 e 320 px: cor dos dois botões, hover, sombra selecionada, clique, setas do teclado, foco, fechamento do modal e ausência de rolagem horizontal da página. Capturas temporárias em `/tmp/inverse-tabs-*.png`. Dados temporários removidos ao concluir.

## Ondas nas miniabas

As duas miniabas inverse passaram a usar `waves-effect waves-light`, integrando o mesmo executor compartilhado de ondas dos botões do sistema. Artigo de ajuda e roteiro revisados; alvos, ordem e comportamento funcional preservados.

Chrome em 1440, 390 e 320 px: onda clara visível nas duas miniabas, seleção por clique e toque, remoção do elemento da onda ao terminar e animação desativada com movimento reduzido. Build, arquitetura e TypeScript aprovados. Capturas em `/tmp/audit-waves-*.png`. Dados temporários removidos ao concluir.

## Cantos quadrados do inverse

O estilo compartilhado inverse usa `--bold-radius`, cujo padrão é zero. Removidos os arredondamentos locais da primeira e última miniaba da auditoria. Regra registrada em AGENTS.md. Artigo e roteiro revisados; alvos e navegação do tour preservados. Ondas e sombra de seleção mantidas.

Chrome em 1440, 390 e 320 px: quatro cantos com raio zero nas duas miniabas, cor inverse, hover, seleção e navegação por teclado, foco e fechamento do modal. Build, arquitetura e TypeScript aprovados. Capturas em `/tmp/inverse-square-*.png`. Dados temporários removidos ao concluir.

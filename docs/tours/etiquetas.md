# Tours de Etiquetas

Artigo `/ajuda/etiquetas`. Listagem em `/conta/etiquetas`. Os endereços antigos de cadastro e edição compõem essa mesma tela com o modal aberto.

1. `PageHeaderTour`: título, trilha e ação Cadastrar etiqueta, conforme permissão.
2. `BusinessListTour`: busca por nome, módulo e situação no passo de filtros, colunas, tabela, largura, ações, quantidade e paginação. Alvos dos filtros: `tags-search`, `tags-module` e `tags-status`, dentro de `business-filters`.
3. `tag-fields`: abre o modal de cadastro por demonstração e explica nome, módulo e cor. Não grava nem abre o seletor automaticamente.
4. `tag-modal-actions`: explica Salvar e Cancelar. Fecha somente a janela aberta pelo tutorial ao sair desta parte ou encerrar o tour.

`tagTours.ts` fornece os passos do formulário exclusivamente ao tour da tela. O modal contém somente os campos e as ações do cadastro, sem botão nem instância de tutorial. A demonstração da listagem usa `availableWith` no botão de cadastro; não aparece sem essa permissão. O modal é montado dentro do executor de tela e a autorização permanece no servidor.

Etiquetas especiais não podem ser alteradas. Na inspeção somente leitura, ações de escrita são ocultadas. Conferir validação no modal, Colorpicker aberto, Esc fechando primeiro a paleta e depois o modal, retorno do foco, cadastro e edição com sucesso, além de 1440, 390 e 320 px.

O marcador Especial com estrela é exclusivo da listagem de Etiquetas. Os modais e demais telas mantêm nome e cor, sem estrela.

O formulário apresenta Situação. A listagem mostra Ativa ou Inativa e a ação Excluir etiqueta com ícone de borracha, desabilitada quando existem vínculos. O tour não confirma exclusões nem altera a situação.

A paleta sobrepõe os campos sem aumentar o modal. Cancelar é branco e Salvar é verde. A confirmação de exclusão usa modal pequeno com botão Excluir sem ícone; a borracha permanece na tabela.

A visualização da etiqueta usa o tamanho padrão do Modal (600 px no desktop), com adaptação automática ao celular. A revisão mantém os alvos e a ordem do tour: o ajuste reduz somente a largura da janela de leitura, sem alterar os controles do cadastro.

Validação do ajuste de largura: Chromium em 1440, 390 e 320 px, visualização carregada com nome, badge e situação, fechamento por Esc e botão e retorno de foco à ação da tabela. Sem rolagem horizontal. Visualização de Cargos preservada em 900 px. Arquitetura e TypeScript aprovados; Docker reconstruído e saudável.

O filtro e o seletor **Módulo** incluem Pessoas, Clientes e Fornecedores. Etiquetas comerciais convencionais são oferecidas no formulário do próprio papel.

# Tour guiado

Cada tela de negócio deve oferecer uma orientação sob demanda na barra superior. O tour apresenta os controles no contexto da página e aponta para o artigo de [ajuda](ajuda.md) do fluxo. Telas de autenticação, erro e demonstração do tema ficam fora dessa regra. Uma página de demonstração que se torne uma tela de negócio passa a seguir a convenção.

## Passos e comportamento

- Descreva os passos na ordem da tela. Use identificadores estáveis para passos e alvos. Na implementação React, mantenha a configuração junto ao recurso e marque os alvos com `data-tour`.
- Compartilhe passos de componentes reutilizados, como trilha de navegação, filtros, tabela e ações do formulário. Acrescente à tela os passos próprios do fluxo.
- Pule os passos cujo alvo estiver ausente para o perfil, estado ou tamanho de tela atual. Quando um passo depender da abertura de um menu, aba ou diálogo, a demonstração deve restaurar somente o estado que ela própria alterou. Preserve dados digitados, preferências e registros do usuário.
- Permita avançar, voltar, fechar com **Esc** e retomar o foco no controle que abriu o tour. Respeite a preferência por movimento reduzido. No mobile, mantenha o cartão e o alvo visíveis, inclusive quando houver rodapé fixo.
- Ao alterar uma funcionalidade, confira textos, ordem, disponibilidade e posição dos alvos. No navegador, percorra os passos afetados no desktop e no mobile.

O primeiro conjunto pode aproveitar `SaasLayout`, `BusinessList` e `BusinessForm` para cobrir a listagem de contas, o cadastro, a edição e o modal de visualização. O modal pode entrar como passo da listagem, já que faz parte da mesma tela.

Referência consultada no outro sistema em `AGENTS.md` e `docs/ai-conventions/frontend/guided-tours.md`.

O executor React `GuidedTour` é aberto por **Tutorial desta tela** na topbar. O catálogo `resources/js/app/guidance.ts` associa cada rota de negócio a seus passos e artigo. Alvos ausentes ou ocultos são pulados. O tour não abre formulários nem grava dados.

## Componentes compartilhados e demonstrações

Use `PageHeaderTour` para título, trilha e ações, e `BusinessListTour` para todas as listagens. Não reescreva os passos comuns em cada tela. Os alvos de `BusinessList` cobrem filtros, Colunas, menu de colunas, tabela, separadores de largura, ações das linhas, quantidade e paginação. Os controles ausentes, incluindo separadores no celular, são pulados.

O executor oferece grupos, progresso, lista de todos os passos, animação do destaque e mensagens das demonstrações. Um passo pode usar vários alvos para destacar também um menu aberto. `availableWith` permite incluir alvos que serão revelados por uma demonstração, como o modal carregado pela API.

Demonstrações usam `onEnter` e retornam `undo` e uma explicação opcional. Ao trocar de passo, sair por Esc, concluir ou desmontar a tela, restaure somente o estado aberto pela demonstração. O redimensionamento de `BusinessList` usa pesos temporários separados das preferências; não grava no localStorage. Nunca demonstre cadastro, edição, exclusão ou navegação de paginação com um clique real.

O tour de auditoria abre o detalhe da primeira operação autorizada e mantém a janela entre os passos de resumo, campos, abas e fechamento. Mostra Log técnico apenas com a permissão específica. Ao sair, restaura a aba anterior e fecha a janela que o tutorial abriu. O foco do modal não deve competir com o diálogo do tour.

O executor GuidedTour e seu acionador ficam exclusivamente na tela. Modais não devem importar nem instanciar o executor e não devem conter botões de tutorial. Seus alvos são explicados pelo tour da tela que os abre; a demonstração identifica a ação disponível, abre a janela, mantém os passos necessários e fecha somente o modal que ela própria abriu.

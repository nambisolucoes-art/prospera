# Componentes compartilhados dos tours

`PageHeaderTour.ts`: título, trilha e ações autorizadas da tela, incluindo botão de cadastro.

`BusinessListTour.ts`: filtros, Colunas, abertura das opções, tabela, linha de largura, demonstração da largura, ações de cada registro, quantidade, abertura das opções e paginação. Passos organizados em Filtros, Tabela e Páginas. Telas passam somente os textos específicos de filtros e registros.

Destaque com transição e pulso, indicador de progresso, explicação da demonstração e lista recolhível de todos os passos. Movimento reduzido desliga as animações. Separadores ausentes no mobile e botões sem permissão são pulados.

Revisar em 1440, 390 e 320 px, inclusive saltos na lista de passos, voltar, Esc durante uma demonstração e conclusão. Verificar restauração exata de larguras e do localStorage, fechamento dos menus e retorno do foco ao botão Tutorial desta tela.

Anterior, Próximo e Concluir usam o componente Button com as mesmas dimensões dos botões dos formulários de cadastro. O tour não define altura, padding ou arredondamento próprios para essas ações.

Validação dos botões no navegador em 1440, 390 e 320 px: Anterior e Próximo com 34 px de altura, igual a Salvar. Primeiro passo com Anterior desabilitado, avanço, retorno e saída por Esc conferidos. Arquitetura, TypeScript e build aprovados.

Cores das ações do tour: Anterior usa Button branco e Próximo ou Concluir usa Button verde (success), mantendo as dimensões do cadastro.

Formulários de negócio usam Cancelar branco e Salvar verde no BusinessFormActions. A paleta de cores abre sobre o conteúdo, preferindo o espaço acima quando o modal não comporta a abertura abaixo, sem alterar a altura do formulário.

Badges e etiquetas seguem a dimensão medida na tabela de ordens de serviço da referência Visdom: fonte 10,5 px, entrelinha 14 px, padding 3 px por 6 px, peso 600 e espaçamento 0,05 em. Tokens compartilhados mantêm altura aproximada de 20 px nas tabelas, modais e seletores. Cores semânticas e cantos do tema são preservados.

Os seletores compartilham animação de abertura do menu de 250 ms, com opacidade e leve deslocamento e escala vertical, seguindo o SelectField do GNAI. O padrão abrange SelectControl, TagSelect e o menu Colunas (SelectChecklist). A preferência por movimento reduzido desliga a animação. Alvos e ordem dos tours preservados.

O fechamento executa a animação inversa por 250 ms antes de desmontar o menu, inclusive ao selecionar uma opção, clicar novamente no campo, sair por Esc ou clicar fora. Durante a saída, o menu fica inerte e oculto para tecnologias assistivas. Reabrir cancela a saída pendente. Colunas e Etiquetas preservam a seleção múltipla e fecham ao sair do menu.

AdditionalContacts é compartilhado por Conta e Pessoas, com prefixos de campos e erros próprios de cada integração. Mantém botões, máscaras, ícones e limite de 20 linhas por tipo. Os tours explicam os controles sem modificar as linhas.

# Componentes compartilhados dos tours

O preloader é global e acompanha a navegação real, incluindo entrada inicial, links e histórico do navegador. Só aparece após 300 ms de espera e fecha quando a tela está pronta, sem espera mínima artificial. Filtros e paginação na mesma tela não acionam a sobreposição. O favicon fica no centro de oito pontos nas cores da marca, com fundo translúcido e desfoque. Movimento reduzido desliga a rotação. Os alvos e a ordem dos tours permanecem os mesmos; a sinalização de conclusão usa o identificador da navegação para ignorar conclusões antigas após cancelamentos.

A prévia temporária está desativada com `appLoaderPreviewEnabled = false` em `resources/js/utils/appPageTransition.ts`. Enquanto estiver `true`, o indicador aparece imediatamente em toda navegação, inclusive filtros e paginação, e permanece visível por pelo menos 500 ms antes da saída. Definir `false` restaura o comportamento normal acima, sem remover sua implementação.

Prévia conferida em 1440, 390 e 320 px, na entrada inicial e entre Contas, Usuários e Auditoria. O indicador apareceu imediatamente e iniciou a saída após cerca de 500 ms. Paginação também exibiu a prévia, e uma resposta simulada de 1000 ms manteve o indicador até a conclusão. Build, arquitetura e TypeScript aprovados após o pull, com alterações locais reaplicadas sem conflitos.

Validação do preloader no Chrome em 1440, 390 e 320 px: entrada inicial de Contas e login, navegação entre Contas, Usuários e Auditoria, módulos já carregados sem sobreposição, respostas atrasadas com conteúdo anterior visível, paginação lenta sem preloader, voltar e avançar, cancelamento seguido de outra navegação, erro de rede e movimento reduzido. Com atraso de 1200 ms, o indicador fechou cerca de 180 ms após a tela ficar pronta. Verificados favicon, oito pontos nas cores da marca, centralização, desfoque e liberação da rolagem.

As cores auxiliares de badges, labels, botões, painéis e barras de progresso usam o GNAI2 como referência (`resources/css/tokens.css` e `resources/css/variables.css`), com os tons mais suaves atualizados: sucesso `#16a34a`, perigo `#ef4444`, aviso `#f59e0b`, informação `#0284c7`, inverse `#475569`, roxo `#7c3aed` e rosa `#db2777`. As ações das listagens usam o azul auxiliar `#2e5cb8`. A marca Prospera, primary, sidebar, dimensões, textos e alvos dos tours permanecem preservados.

Validação da paleta em 1440, 390 e 320 px: Auditoria (listagem, detalhe e tour de resumo), Contas, cadastro de usuário e demonstrações de botões e badges. Conferidos os tons calculados no navegador, a consistência entre listagem e detalhe, os estados de foco, hover e pressionado dos botões e a preservação dos tokens da marca, primary e navegação. Os destaques do tour mantêm os mesmos alvos e a saída fecha o detalhe aberto pela demonstração.

Os formulários com senha reutilizam o controle de visibilidade de TextInput. O passo `password-visibility` explica Mostrar senha e Ocultar senha, sem clicar no olho nem revelar valores durante a demonstração. Senha e confirmação mantêm estados independentes. Login e redefinição de senha usam o mesmo controle, sem executor de tour nas telas de autenticação.

O olho usa o botão lateral de InputGroup, separado do campo por uma borda, com fundo cinza e cantos quadrados, seguindo o ícone de e-mail. O alvo e o texto do tour permanecem adequados ao controle.

Esse ajuste visual foi conferido no login e no cadastro de usuário em 1440, 390 e 320 px: botão de 36 px, alinhamento e fundo iguais ao ícone de e-mail, foco, clique, teclado e tour preservados, sem sobreposição com o texto nem rolagem horizontal indevida.

Validação do controle de senha em 1440, 390 e 320 px: login, redefinição, cadastro de administrador da plataforma e cadastro de conta. Conferidos clique, ativação pelo teclado, preservação do valor e do envio por FormData, controles independentes e ausência de envio ao clicar no olho. O tour do cadastro de usuário destacou o campo sem revelar a senha. O login com erro de validação manteve a mensagem ao alternar a visibilidade.

`PageHeaderTour.ts`: título, trilha e ações autorizadas da tela, incluindo botão de cadastro.

`BusinessListTour.ts`: filtros, Colunas, abertura das opções, tabela, linha de largura, demonstração da largura, ações de cada registro, quantidade, abertura das opções e paginação. Passos organizados em Filtros, Tabela e Páginas. Telas passam somente os textos específicos de filtros e registros.

A coluna Ações fica sempre no final da tabela e do menu Colunas. A primeira e a última coluna visíveis têm largura fixa. Somente separadores entre colunas intermediárias permitem ajustar a largura com o mouse ou as setas do teclado. O tour explica essa regra e demonstra apenas os separadores disponíveis, restaurando as larguras ao sair.

Validação desta mudança em Contas, Usuários da plataforma e Auditoria: 1440, 390 e 320 px, com registros existentes e sem rolagem horizontal indevida. Em desktop, o ajuste pelo teclado preservou a primeira e a última largura. Em Contas, foram conferidos o menu Colunas, a ocultação e restauração da primeira coluna, os passos de largura e ações e a saída por Esc. A demonstração restaurou as larguras e preservou o localStorage. Em mobile, os passos de redimensionamento foram pulados e o destaque das ações permaneceu na última coluna.

A barra de filtros é montada pelo próprio `BusinessList`: busca (`BusinessListSearch`), seletores e datas (`DatePicker`) ficam na mesma linha, com a busca ocupando a maior parte, e quebram para uma coluna no celular. `useBusinessListFilters` aplica a busca com atraso, mantém os demais parâmetros e volta à primeira página. Os passos do tour continuam apontando para `business-filters`.

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

## Sidebar e dicas dos formulários

A sidebar recolhida apresenta o nome real de cada item em um tooltip com seta, ao passar o mouse ou focar pelo teclado. Os submenus continuam exibindo seus nomes no painel lateral. Expandir remove as dicas dos ícones. Sidebar, topo e conteúdo acompanham a mesma transição de 220 ms; movimento reduzido remove a transição.

FormField, Field e Select aceitam `info` opcional. O botão de informação fica ao lado do label, fora do elemento label, e abre por hover, foco ou toque. Esc e clique fora fecham a dica sem gravar dados. Tooltip usa portal e preserva descrições acessíveis existentes.

Revisados os alvos e a ordem dos tours de Contas, Usuários, Pessoas, Organização, Etiquetas, Cargos e Segurança. As dicas não deslocam as seções nem alteram a disponibilidade dos controles. Nenhum executor de tour foi adicionado aos modais. A ajuda já está integrada à Central de Ajuda; não há integração pendente.

### Referências da implementação de tooltips

- Controle Bar: `/home/djavan/controle-bar/resources/js/components/ui/sidebar.tsx` (`SidebarMenuButton`) e `ui/tooltip.tsx`. Usa Radix, portal, seta, posicionamento lateral e exibição condicionada ao estado recolhido.
- GNAI2: `/home/djavan/woquiton/GNAI2/resources/js/components/UI/InfoTooltip/InfoTooltip.tsx`, `UI/Tooltip/Tooltip.tsx` e `Forms/FormField/FormField.tsx`. Usa botão de informação, tooltip compartilhado e descrição acessível preservada.
- Os projetos de referência foram apenas lidos. No Prospera, o Tooltip existente foi ampliado sem adicionar bibliotecas. InfoTooltip reutiliza essa base.

### Arquivos desta melhoria

Na raiz `resources/js/`:

- `components/Tooltip/Tooltip.tsx` e `Tooltip.css`: portal, seta, limites da viewport, posicionamento lateral, preservação de aria-describedby, entrada e saída de 120 ms, foco, toque, clique fora e Esc. Esc fecha primeiro a dica e preserva o modal.
- `components/InfoTooltip/InfoTooltip.tsx` e `InfoTooltip.css`: botão de informação reutilizável.
- `components/FormField/FormField.tsx` e `FormField.css`: propriedade opcional info e composição do label.
- `components/BusinessForm/BusinessFormFields.tsx` e `BusinessForm.css`: propagação de info por Field e Select e alinhamento horizontal.
- `components/SidebarPrimary/SidebarMenuItem.tsx`, `SidebarPrimary.tsx` e `SidebarPrimary.css`: tooltip comum aos itens, remoção da projeção antiga do nome e transição da largura e dos textos.
- `components/TopBarPrimary/TopBarPrimary.css`, `app/App.css` e `styles/tokens.css`: transição sincronizada de 220 ms e preferência por movimento reduzido.
- `app/saas/ClientForm.tsx`: documento do contratante e consulta cadastral.
- `app/saas/PersonIdentityFields.tsx`: documento opcional e único na conta.
- `app/saas/company-form.tsx`: raiz, documento completo e consulta cadastral.
- `app/saas/establishment-form.tsx`: código do estabelecimento e CNPJ com a raiz da empresa.
- `app/saas/TagEditorModal.tsx`: módulo e situação da etiqueta.
- `app/saas/UserFields.tsx`: forma de acesso e confirmação da senha inicial.
- `app/saas/RoleAvailabilityFields.tsx` e `role-form.tsx`: disponibilidade do cargo e permissões por ação.
- `app/account/AccountForm.tsx`: suporte opcional a info na definição dos campos.
- `app/account/auth/reset-password.tsx` e `app/account/settings/security.tsx`: confirmação da nova senha e senha atual.

Documentação revisada e atualizada: `docs/ajuda/contas.md`, `empresas.md`, `estabelecimentos.md`, `pessoas.md`, `etiquetas.md`, `usuarios.md`, `cargos-permissoes.md`, `seguranca.md` e este roteiro compartilhado. Alterações locais anteriores em outros arquivos foram preservadas.

### Validação dos tooltips e da sidebar

Chrome local com viewport emulada:

- Cadastro de usuário da plataforma e modal de Etiquetas: 1440, 390 e 320 px. Hover, foco, clique e posição dentro da viewport; no modal, dica acima da camada e Esc sem fechar o formulário.
- Cadastro de Contas: 1440 px, dicas de documento e acesso.
- Cadastros de Pessoas, Empresas, Estabelecimentos e Cargos: 1440 e 390 px. Conteúdo específico, labels alinhados e ausência de rolagem horizontal indevida.
- Segurança: 1440, 390 e 320 px, dicas de senha atual e confirmação. A sessão temporária de teste recebeu confirmação recente somente para acessar essa tela.
- Pessoas no celular: eventos reais de toque emulados no Chrome e fechamento ao tocar fora.
- Cadastro de usuário: resposta de validação com campos vazios, erros preservados e dica funcionando. Tour aberto, passos percorridos e saída por Esc em 1440 e 390 px.
- Sidebar: todos os quatro ícones da plataforma, expandida e recolhida, saída das dicas, submenu do exemplo Bold, foco no submenu e abertura e fechamento da navegação mobile.
- Animação medida: largura inicial 240 px, intermediária aproximadamente 189 px e final 70 px; o conteúdo manteve a mesma coordenada da borda da sidebar. Nove alternâncias rápidas terminaram no estado esperado. Movimento reduzido resultou em transição de 0 s.

Não há alternância de tema escuro implementada na tela Aparência atual. A verificação utilizou o tema disponível, com sidebar azul e superfícies claras. Dispositivos móveis foram emulados, sem teste em aparelho físico.

Verificações: npm run check (arquitetura e TypeScript), build de produção no Docker, git diff --check e php artisan audit:check aprovados. Auditoria cobriu 12 modelos. A suíte Laravel, com SQLite em memória imposto por tests/bootstrap.php, teve 173 testes aprovados e 1 falha (1719 asserções): ProfileUpdateTest, teste email_change_requires_confirmation_and_revokes_other_sessions, linha 70, esperava um e-mail e recebeu zero. A falha foi reproduzida isoladamente; backend e testes permaneceram inalterados nesta tarefa. Não foi criado evento de auditoria porque as mudanças são de apresentação, sem nova operação de negócio.

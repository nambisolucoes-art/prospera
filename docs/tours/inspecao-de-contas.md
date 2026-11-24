# Tour de inspeção de contas

Artigo de referência em [Inspecionar uma conta](../ajuda/inspecao-de-contas.html).

| Passo | Alvo | Orientação |
| --- | --- | --- |
| Inspecionar conta | Ações da listagem de Contas | Use o ícone de entrada para abrir o modal em uma conta ativa. |
| Escolher usuário | Campo Usuário no modal | Busque pelo nome ou e-mail. Confira o cargo abaixo do nome e escolha o perfil. |
| Escolher modo | Campo Modo de inspeção | Escolha entre consultar e editar conforme as permissões do perfil. A demonstração termina antes de trocar a sessão. |
| Voltar ao SaaS | Faixa acima da barra superior (`data-tour="account-inspection-banner"`) | Confira o texto Acessando como, o usuário e o modo na faixa amarela, que ocupa toda a largura da janela e permanece visível durante a rolagem. No celular, o modo fica abaixo do usuário, com o botão ao lado. Use o ícone vermelho de saída identificado como “Voltar ao SaaS”. Encerre a inspeção e retorne à plataforma. |

Executor disponível pelo botão **Tutorial desta tela** na barra superior. Configuração em `resources/js/app/guidance.ts`. Revisar alvos em desktop e mobile a cada alteração.

Voltar ao SaaS aparece apenas como ícone vermelho, sem fundo ou borda. Mantém foco por teclado, nome acessível e área de toque no celular. O alvo da faixa e a ordem do tour permanecem os mesmos.

Validação em 1440, 390 e 320 px, em inspeção Somente leitura: faixa acima da barra superior em toda a largura, usuário e modo, destaque do tour, rolagem, menu lateral aberto no celular e retorno à plataforma. O cabeçalho, o menu lateral e o conteúdo acompanham a altura da faixa, inclusive quando o nome quebra em mais linhas. Ao encerrar a inspeção, a barra superior retorna ao topo sem espaço vazio.

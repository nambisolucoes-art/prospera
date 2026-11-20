# Tour de inspeção de contas

Artigo de referência em [Inspecionar uma conta](../ajuda/inspecao-de-contas.md).

| Passo | Alvo | Orientação |
| --- | --- | --- |
| Inspecionar conta | Ações da listagem de Contas | Abra o modal em uma conta ativa. |
| Escolher usuário | Campo Usuário no modal | Busque pelo nome ou e-mail. Confira o cargo abaixo do nome e escolha o perfil. |
| Escolher modo | Campo Modo de inspeção | Escolha entre consultar e editar conforme as permissões do perfil. A demonstração termina antes de trocar a sessão. |
| Voltar ao SaaS | Faixa abaixo da barra superior (`data-tour="account-inspection-banner"`) | Confira o usuário e o modo na faixa amarela. No celular, o modo fica abaixo do usuário, com o botão ao lado. Use o ícone vermelho de saída que mostra somente o ícone de saída, identificado como “Voltar ao SaaS”. Encerre a inspeção e retorne à plataforma. |

Executor disponível pelo botão **Tutorial desta tela** na barra superior. Configuração em `resources/js/app/guidance.ts`. Revisar alvos em desktop e mobile a cada alteração.

Voltar ao SaaS aparece apenas como ícone vermelho, sem fundo ou borda. Mantém foco por teclado, nome acessível e área de toque no celular. O alvo da faixa e a ordem do tour permanecem os mesmos.

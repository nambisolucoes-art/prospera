# Validação visual no navegador

Toda tela nova ou alterada visualmente deve ser conferida na aplicação rodando. Considere desktop e mobile desde a composição da tela e use os componentes e tokens que o Prospera já oferece.

1. Abra o fluxo com o perfil que usa a tela e com dados representativos. Use o ambiente local e dados de teste para operações de escrita.
2. Confira em uma largura de desktop e uma de celular. Use cerca de 1440 px e 390 px como referência. Confira também perto de 320 px quando a mudança envolver tabela, select, modal ou barra de ações no mobile.
3. Interaja com o que mudou. Conforme o fluxo, abra a navegação, menus de select e modais, percorra tabela e formulário, provoque um erro de validação e confira o sucesso. Em componentes compartilhados, verifique telas consumidoras representativas.
4. Durante a rolagem, verifique recortes, sobreposição, rolagem horizontal indevida, área de toque e foco. Abra os menus para conferir suas opções e veja se os botões do formulário ficam inteiros quando o rodapé de ações acompanha a rolagem. O rodapé geral continua no fim da página.
5. Na entrega, registre as telas, as larguras e as interações verificadas. Quando o ambiente impedir uma etapa, informe o que ficou pendente e por quê.

`npm run check` verifica arquitetura e tipos. A inspeção no navegador cobre a apresentação e as interações. Uma alteração restrita à documentação segue revisão de texto e links.

No Prospera, a conferência é responsabilidade de quem entrega a mudança.

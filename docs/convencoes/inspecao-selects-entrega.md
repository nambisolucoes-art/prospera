# Ícone de saída e animação dos seletores

Voltar ao SaaS usa somente ícone vermelho, sem fundo e sem borda. O controle continua sendo um botão semântico, com nome acessível, foco visível e bloqueio durante o envio. A área de toque é de 44 px no celular; o desktop mantém 30 px. Ajuda e roteiro de inspeção atualizados.

SelectMenu compartilha a presença do menu durante a animação entre SelectControl, SelectChecklist (Colunas) e TagSelect. A abertura usa opacidade e translateY de -6 px com scaleY de 0,96 até 1. O fechamento executa a sequência inversa antes de desmontar o menu. Ambos duram 250 ms com ease, seguindo a referência GNAI/resources/react/components/Forms/SelectField/SelectField.css.

O fechamento ocorre após selecionar uma opção simples, clicar novamente, sair por Esc ou clicar fora. Os seletores múltiplos mantêm sua seleção sem fechar a cada marcação. Na saída, o menu fica inerte e oculto para tecnologias assistivas. Reabrir cancela o temporizador de saída. Movimento reduzido desativa ambas as animações. Roteiro compartilhado revisado, com os mesmos alvos e ordem.

## Validação

Navegador em 1440, 390 e 320 px: inspeção Somente leitura aberta com um administrador SaaS de teste. Ícone sem fundo ou borda e vermelho, foco e saída via Enter com retorno à plataforma conferidos.

Seletores de Papel na conta, Etiquetas e Colunas conferidos nas três larguras. Amostragem da animação confirmou opacidade intermediária na abertura e no fechamento. Verificados seleção simples com saída animada, Esc, clique no campo, clique fora, reabertura durante saída, movimento reduzido e marcações de Colunas restauradas. Menus sem recorte horizontal. Arquitetura, tipos e build aprovados. Integrações de ajuda e tour existentes utilizadas.

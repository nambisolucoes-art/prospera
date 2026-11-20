# Situação e exclusão de etiquetas

O formulário compartilha Select de Situação, Ativa ou Inativa. A tabela e a visualização mostram a situação. No celular, Módulo fica disponível em Colunas para preservar espaço para nome, situação e ações.

Etiquetas inativas mantêm os vínculos existentes, aparecem na edição das pessoas que já as possuem e não podem ser selecionadas em novas pessoas. A validação e a Action verificam essa regra. Ativar e inativar exigem permissões próprias além de editar. Cadastrar uma definição já inativa exige inativar. Etiquetas especiais permanecem protegidas.

Excluir exige account.tags.delete. A tabela oferece a borracha, com confirmação na tela. A ação fica desabilitada quando há pessoas vinculadas, com orientação para editar e inativar. O servidor verifica vínculos e conta dentro da transação. O lock da conta e das definições protege a disputa entre vínculo, inativação e exclusão.

Auditoria: active integra os campos auditados de Tag. Alterações de situação e exclusões geram eventos centrais com autor e conta; tentativas rejeitadas não deixam alterações ou eventos concluídos. Não há remoção automática dos relacionamentos ao excluir.

Ajuda, README e tour de Etiquetas atualizados. Integrações existentes utilizadas, sem pendências de integração.

## Validação

86 testes e 671 asserções aprovados em SQLite em memória. Pint nos 13 arquivos PHP do escopo, PHPStan, arquitetura, TypeScript e build aprovados. audit:check aprovou os seis modelos; permissions:sync reconheceu as 58 ações.

Navegador em 1440, 390 e 320 px: cadastro com erro de nome obrigatório e sucesso, mudança para Inativa, reabertura com valor persistido, reativação, cancelamento e confirmação da exclusão com borracha, bloqueio visual da exclusão vinculada e abertura e fechamento do tour. Modais sem rolagem horizontal indevida. Capturas temporárias em /tmp/prospera-tag-edit-*.png e /tmp/prospera-tag-delete-*.png.

## Paleta e ações dos formulários

Removido o padding de 190 px que aumentava o modal ao abrir a paleta. ColorPicker escolhe a abertura acima quando não há espaço abaixo dentro do modal, sem alterar a altura do conteúdo. A navegação por Esc continua fechando primeiro a paleta. BusinessFormActions usa Salvar verde por padrão e Cancelar branco, incluindo os formulários de negócio que passam por AccountForm. Formulários de autenticação preservam seu padrão.

A confirmação de exclusão usa o tamanho small compartilhado (300 px no desktop). O botão Excluir permanece vermelho e não contém ícone. A borracha continua na tabela.

Ajuda e roteiros revisados. Apenas o roteiro visual foi complementado, pois campos, regras e ordem não mudaram.

Navegador em 1440, 390 e 320 px: abrir a paleta manteve a altura do modal em 410,5 px no desktop e 507,25 px no celular. Paleta visível no viewport, escolha de cor e fechamento com Esc conferidos. Cancelar branco e Salvar verde verificados no modal e em Pessoas como consumidor do componente compartilhado. Confirmação de exclusão small e botão sem ícone conferidos nas três larguras. Arquitetura, TypeScript e build aprovados.

Texto do botão de confirmação reduzido para Excluir, conferido no modal em 1440, 390 e 320 px. Build, arquitetura e tipos aprovados. Título do modal e ação da tabela preservam a identificação da etiqueta.

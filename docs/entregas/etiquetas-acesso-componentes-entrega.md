# Etiquetas, acesso e componentes

A retirada de Usuário no formulário de Pessoas revoga o acesso ao salvar. A operação exige `account.users.revoke`, preserva a pessoa, o usuário e a autoria histórica, e registra a mudança de `access_enabled` na transação da pessoa e das etiquetas. A reativação exige cadastrar usuário e atribuir cargo. O último administrador habilitado permanece protegido. Login por senha rejeita usuários revogados; sessões existentes e outros meios de autenticação são bloqueados na próxima requisição pelo middleware compartilhado. Inspeções novas também excluem usuários revogados.

## Revisão dos componentes

Pessoas, Etiquetas, AccessList e PersonPreview usam TagLabel. TagSelect compartilha a função tagColors com TagLabel para marcadores selecionados e opções. BusinessList e BusinessForm seguem como componentes das listagens e formulários de negócio. O detalhe de auditoria tinha tabela e badges próprias: agora usa Table e Label do Bold, mantendo apenas os estilos específicos de comparação e quebra de conteúdo. PreviewSection já é compartilhado por contas, cargos e auditoria. As páginas de demonstração do Bold mantêm suas composições próprias.

O critério de luminância das etiquetas passa a favorecer letras brancas em cores fortes como rosa e letras escuras em fundos claros. A estrela continua restrita à listagem de Etiquetas.

Ajuda de Pessoas, README, roteiro e configuração do tour revisados na mesma tarefa. A integração existente da ajuda e do tour permanece utilizada.

## Verificação

84 testes e 633 asserções aprovados exclusivamente em SQLite em memória. PHPStan, arquitetura, TypeScript e build aprovados. `audit:check` aprovou os seis modelos e `permissions:sync` reconheceu 55 ações, incluindo a revogação.

Navegador em 1440, 390 e 320 px: formulário de Pessoas com seletor aberto, rosa forte com texto branco, remoção de Usuário exibindo o aviso antes de salvar, listagem de Etiquetas e detalhe de Auditoria. Sem rolagem horizontal da página. Abas Formatado e Log técnico e abertura e fechamento dos tours conferidos. A revogação gravada, a reativação, as falhas de permissão e a proteção do último administrador foram verificadas pelos testes de integração.

## Texto auxiliar da revogação

O aviso antes de salvar reutiliza FormSectionDescription, também usado nas descrições de subseções do tema: fonte de 13 px, cor discreta e entrelinha de 1,6. A mensagem e o comportamento da revogação foram preservados. Ajuda e tour revisados, sem necessidade de alterar os passos ou alvos.

Aviso conferido no formulário de Pessoas em 1440, 390 e 320 px após retirar Usuário: 13 px e cor da descrição de subseção, com quebra de linha sem rolagem horizontal indevida. Arquitetura, TypeScript e build aprovados.

## Foco dos campos e cores do tour

TagSelect passa a usar os mesmos tokens de borda normal, foco e cantos dos campos TextInput. Papel na conta declara required no Select compartilhado, que apresenta asterisco e semântica de obrigatório. Anterior usa Button branco e Próximo ou Concluir usa Button success verde. Ajuda de Pessoas revisada: já orienta a seleção de cargo. Roteiros revisados sem mudança na ordem ou nos alvos.

Validação no navegador em 1440, 390 e 320 px: seletor de etiquetas aberto e em foco com borda #aaa, asterisco em Papel na conta, Próximo verde, Anterior branco, avanço, retorno e saída por Esc. Botões mantêm 34 px de altura. Arquitetura, TypeScript e build aprovados.

O aviso de revogação usa o alinhamento compartilhado business-form__control-start e a cor --bold-validation-message-color, preservando o tamanho de 13 px e o papel de status. Ajuda e alvos do tour revisados, sem mudança de conteúdo ou ordem.

Aviso alinhado conferido no navegador em 1440, 390 e 320 px: início do texto coincide com o campo Etiquetas nas três larguras, cor de validação #f6504d, fonte 13 px e sem rolagem horizontal. Arquitetura, TypeScript e build aprovados.

## Tabela e badges do detalhe de auditoria

Table oferece headerTone primary compartilhado pela BusinessList e pelo detalhe de Auditoria. O modal usa cabeçalho preenchido com a cor das listagens, moldura com borda do tema e linhas sem listras, mantendo hover. A definição das cores do cabeçalho saiu do CSS exclusivo da listagem para evitar divergências.

Label oferece size comfortable para os resumos: fonte de 12 px, padding de 4 px por 8 px e entrelinha de 1,4. O detalhe usa essa variante para ação e tipo. As demais etiquetas mantêm suas dimensões. Ajuda e tour revisados; alvos e conteúdo operacional preservados.

Navegador em 1440, 390 e 320 px: cabeçalho do detalhe com a mesma cor da listagem, badges com aproximadamente 25 px de altura, tabela legível, abas Formatado e Log técnico, fechamento e abertura do tour conferidos. Sem rolagem horizontal da página. Arquitetura, TypeScript e build aprovados.

## Cores por ação de auditoria

Referência consultada: sistema de referência/resources/js/components/Audit/Audit.utils.ts, com criação success, exclusão danger, restauração info e alterações warning. AuditEventLabel centraliza esse mapeamento para listagem e modal. Entrada usa info, saída inverse, início de inspeção purple, fim de inspeção inverse e sincronização warning. Eventos desconhecidos usam warning. O tipo do registro continua neutro.

A API inclui event_key, o identificador estável do evento, junto do rótulo traduzido. As cores e a comparação de antes e depois usam o identificador em vez do texto traduzido. Mantidos tamanho confortável e nome visível da ação.

Ajuda e tour atualizados. Oito testes de auditoria e orientação, com 100 asserções, e Pint aprovados. Arquitetura, TypeScript e build aprovados.

Navegador em 1440, 390 e 320 px: cadastro verde, alteração amarela, exclusão vermelha e entrada ciano conferidos na listagem e no detalhe, com a mesma cor por evento e sem rolagem horizontal indevida. Capturas temporárias em /tmp/prospera-audit-{evento}-{largura}.png.

## Formatação dos valores de auditoria

AuditValueFormatter centraliza a apresentação por entidade e campo. Modo de inspeção utiliza AccountInspectionMode::label(), incluindo edit como Leitura e edição e read-only como Somente leitura. Módulos, tipo de pessoa, etiquetas especiais, cargos iniciais e booleanos conhecidos também usam rótulos em português. Conteúdo livre e dados protegidos não são reinterpretados. Nenhum registro histórico é reescrito.

Nove testes de auditoria e orientação, com 121 asserções, e PHPStan aprovados. Teste de regressão confirma a apresentação de registros antigos, antes/depois, campos vazios, textos livres edit/read-only e integridade dos valores armazenados. Navegador em 1440, 390 e 320 px: ambos os modos conferidos na tabela e no log técnico, sem rolagem horizontal da página. Ajuda, roteiro e convenção de auditoria atualizados.

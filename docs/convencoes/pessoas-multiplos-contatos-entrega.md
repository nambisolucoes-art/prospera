# Múltiplos contatos em Pessoas

Pessoas reutiliza AdditionalContacts, o mesmo componente do formulário da conta. Prefixos de nomes e erros permitem manter os campos details[additional_phones] e details[additional_emails] da conta e usar additional_phones e additional_emails na pessoa, sem duplicar a composição dos controles.

São permitidos até 20 telefones e 20 e-mails adicionais. Os botões de adicionar e remover, máscaras e ícones são os mesmos da conta. Identificadores estáveis preservam os valores das outras linhas ao remover uma delas. Linhas vazias são ignoradas e remover todas envia uma lista vazia para limpar o cadastro. Cancelar não grava as alterações.

A migração adiciona listas JSON à pessoa, inicializadas vazias para os cadastros existentes. Identidade, telefone, WhatsApp, e-mail principal, papéis e acesso não são duplicados. Contatos adicionais são comuns a todos os papéis da pessoa e não criam nem alteram login.

SavePersonDetailsData normaliza telefones e e-mails, valida cada item e limita as listas. Campos ausentes em uma atualização preservam os dados existentes. Person declara casts e campos auditados. A consulta e a visualização retornam as listas no escopo da pessoa autorizada. As permissões de Pessoas continuam sendo aplicadas no servidor; alterar contatos adicionais não exige editar usuário quando a identidade de acesso permanece a mesma.

Auditoria registra inclusões e remoções com autor e conta, na transação da pessoa. Coleções vazias e valores ausentes não geram mudanças vazias no histórico. O formatador mostra números brasileiros com a máscara de telefone, mantendo os valores normalizados originais armazenados. Inspeção preserva o administrador SaaS como autor real e bloqueia gravações em modo Somente leitura.

Ajuda, roteiro de Pessoas, roteiro dos componentes e README atualizados. O tour explica os controles sem adicionar ou remover linhas. As integrações existentes de ajuda, tour e auditoria foram utilizadas.

## Validação

Aplicação reconstruída com Docker e serviços saudáveis. TypeScript e PHPStan aprovados. Audit:check aprovado com sete modelos cobertos. Suíte completa com SQLite em memória: 103 testes e 885 asserções aprovados.

Navegador Chromium em 1440, 390 e 320 px: adicionar telefones e e-mails, máscara, erro de e-mail, remover uma linha preservando as seguintes, ignorar linhas vazias, salvar, reabrir, visualizar, limpar todas as linhas e cancelar. Tutorial revisado e fechamento preserva os valores do formulário. Sem rolagem horizontal nessas larguras. Formulário da conta verificado com os mesmos controles e nomes de campos existentes. Contatos principais e login preservados. Conta e usuários temporários de validação desativados ao concluir.

# Pessoas, usuários e etiquetas da conta

Operações auditáveis: `person.created`, `person.updated`, `user.created`, `tag.created`, `tag.updated`. Escopo: conta autenticada, administrador autor, entidade e identificador, instante e UUID de operação. Histórico na mesma transação, em `directory_audit_events`; sem senhas, tokens ou valores de contatos. Para pessoas, registra campos alterados e antes/depois dos vínculos de etiquetas e usuário. Para etiquetas, nome, módulo e cor. A consulta central de auditoria ainda depende de integração.

A definição da etiqueta especial Usuário é reservada ao sistema; sua seleção no formulário solicita a criação do acesso. A migração associa pessoas e etiquetas dos usuários existentes à conta; novos acessos no formulário exigem a seleção explícita dessa etiqueta. Etiquetas convencionais não concedem acesso. A primeira versão permite cadastrar e editar pessoas e etiquetas; não inclui exclusão de pessoas, remoção de acesso nem alteração de papel de um acesso existente.

Ajuda e tours estão versionados em `docs/ajuda/` e `docs/tours/`. Central de Ajuda e executor dos tours estão integrados ao menu e à barra superior.

## Verificação após revisão do fluxo

- Build Docker concluído, com arquitetura e TypeScript aprovados.
- Suíte Laravel em SQLite: 62 testes, 360 asserções. Inclui acesso determinado pela seleção da etiqueta, rejeição de etiquetas de outra conta, exigência dos dados de acesso, proteção dos vínculos existentes, ausência de acesso quando há apenas etiquetas convencionais e reversão transacional.
- Chrome em 1440, 390 e 320 px: menu sem Usuários, lista com busca automática, ações de visualizar e editar, modal no padrão de Contas, formulário com seletor múltiplo e oito opções de teste. Seleção e remoção de Usuário antes de salvar mostram e ocultam a seção; validação, cadastro com convite, busca automática do novo registro e edição conferidos nas três larguras. Sem rolagem horizontal indevida.
- Modal conferido após sua animação em desktop e mobile; fechamento por Esc e retorno à lista. Capturas temporárias em `/tmp/people-*.png`. Na entrega inicial, os tours estavam apenas documentados. A integração e execução foram verificadas na tarefa de auditoria.

## Revisão dos padrões visuais

Busca de Pessoas com lupa clicável à direita e envio por Enter, preservando a busca automática. Marcadores de etiquetas quadrados e na cor cadastrada, com estrela amarela discreta e tooltip Especial. O mesmo componente é usado nas listas de Pessoas e Etiquetas e no modal da pessoa. Filtro de módulos sem rótulo visível, mantendo o nome acessível. Campo Cor integra o plugin mjolnic-bootstrap-colorpicker do Ubold, com hexadecimal e amostra à direita; a paleta abre abaixo do campo.

Arquitetura, TypeScript e build Docker aprovados. Chrome em 1440, 390 e 320 px: clique da busca, raio zero dos marcadores, filtro aberto, hover da estrela, abertura da paleta, alteração de saturação e gravação da cor conferidos. Capturas em `/tmp/padroes-*.png`. Artigos e passos afetados revisados; executor dos tours integrado na tarefa de auditoria.

# Auditoria, ajuda e tour

## Integração

Auditoria central em `audit_records`, contrato `Auditable`, trait `AuditsChanges`, observer de campos permitidos e `AuditLog::transaction()`. Contas, pontos, pessoas, usuários, cargos e etiquetas têm cobertura. Pivôs, catálogo de permissões e eventos de acesso têm registros explícitos. Inspeções preservam o administrador real e o usuário representado. Históricos anteriores são importados com os dados disponíveis. O detalhe não reconstrói valores que não foram guardados anteriormente.

Consulta somente leitura em `/conta/auditoria` e `/saas/auditoria`, com permissões separadas para listar, visualizar e consultar detalhes técnicos. Escopo de conta aplicado no servidor também nos filtros. Senhas, hashes, tokens, segredos e fotos nunca entram nas duas visões. Documento recebe marcador de alteração. Campos sem mudança e operações revertidas não geram histórico concluído.

Central de Ajuda disponível no menu em `/ajuda`, com busca por título, palavras-chave e conteúdo. Resultados podem apontar para a seção correspondente. Artigos e links internos respeitam contexto e permissão; acesso direto não autorizado retorna 404. Markdown remove HTML bruto e bloqueia links inseguros.

Tutorial desta tela disponível na topbar, inclusive no celular. `resources/js/app/guidance.ts` associa rotas a passos e artigos. O executor pula alvos ocultos, preserva os dados digitados, respeita movimento reduzido, permite voltar, avançar e fechar por Esc, prende o foco no diálogo e o devolve ao botão de origem.

## Verificação

- Laravel: 80 testes, 587 asserções. Casos novos cobrem autoria, antes e depois, ausência de mudanças, pivôs, reversão transacional e resposta 422, isolamento entre contas, detalhes técnicos, proteção de segredos, inspeção, contexto entre requisições, catálogo, busca e conteúdo seguro da ajuda.
- `audit:check`: seis modelos de negócio cobertos. Verificação obrigatória também na inicialização Docker; novos modelos e colunas exigem cobertura ou exclusão justificada.
- PHPStan, Pint, arquitetura e TypeScript aprovados.
- Chrome em 1440, 390 e 320 px: histórico da conta e da plataforma, filtro de conta, modal com Antes e Depois e detalhes técnicos, pesquisa da ajuda, abertura de artigo, navegação lateral e tutorial. Sem rolagem horizontal indevida.
- Percorridos os passos de Minha conta, edição do estabelecimento, Pessoas, Etiquetas, Cargos, cadastros, Perfil, Aparência, Segurança, confirmação de senha, Auditoria e Ajuda. Na plataforma: Contas, Usuários, seus cadastros, Auditoria e Ajuda. Cadastro de cargo pela interface seguido da consulta do evento nas três larguras. Formulário de pessoa preservado; seção Acesso de usuário ausente sem etiqueta e presente com Usuário. Esc e retorno do foco conferidos. Movimento reduzido conferido no percurso dos tours.
- Capturas temporárias em `/tmp/audit-*.png`, `/tmp/help-*.png`, `/tmp/platform-audit-*.png`. Dados de validação removidos ao concluir.

## Incidente no ambiente local

A primeira execução dos testes herdou o PostgreSQL do Docker e a preparação da suíte recriou o banco local. Os dados anteriores foram apagados. O incidente foi informado durante o trabalho; o administrador criado depois foi preservado. Não havia backup disponível para restaurar os registros anteriores.

`tests/bootstrap.php` agora impõe SQLite em memória em `getenv`, `$_ENV` e `$_SERVER` antes de carregar o Laravel. Isso impede que a suíte use o banco da aplicação mesmo herdando as variáveis PostgreSQL do Docker. Um teste também confere driver e banco em memória. As execuções posteriores preservaram os registros locais.

# Recuperação de arquivos da aplicação e estrela de etiquetas

## Causa e tratamento

A captura mostrava `Unable to preload CSS` e os logs locais confirmaram 404 em arquivos com hashes de uma versão anterior, incluindo o CSS de Etiquetas e módulos JavaScript de listagem. Uma aba aberta antes da reconstrução podia tentar carregar arquivos removidos na atualização.

`pageAssetRecovery.ts` reconhece falhas de carregamento de CSS e módulos de páginas. `ApiApplication` faz uma única navegação GET para o destino solicitado, preservando caminho, consulta e fragmento. O destino é validado como mesma origem. A tentativa é limitada por destino em sessionStorage durante 60 segundos, evitando loops; a guarda é removida após uma carga bem-sucedida. Se o armazenamento estiver indisponível ou a nova tentativa falhar, aparece mensagem em português e a ação Recarregar tela. Erros de API e de execução da aplicação mantêm seu tratamento normal.

O HTML servido por JsonNavigation recebe `Cache-Control: private, no-store`. O tratamento segue o cenário documentado pelo [Vite para erros após uma nova publicação](https://vite.dev/guide/build.html#load-error-handling). A recuperação não reenvia a operação de formulário: somente a tela de destino é carregada por GET.

## Estrela da etiqueta

A propriedade `special` de TagLabel é passada somente na listagem de Etiquetas. Pessoas, opções do seletor, formulário e modais de visualização exibem nome e cor sem estrela. A cor cadastrada e as regras funcionais da etiqueta Usuário permanecem preservadas. Regra registrada em AGENTS.md; ajuda e roteiros revisados.

## Verificação

- Build, arquitetura, TypeScript, PHPStan e Pint dos arquivos PHP alterados aprovados.
- Laravel: 82 testes, 617 asserções. Teste de HTML atualizado para conferir o cabeçalho sem armazenamento em cache.
- Chrome com falhas de rede simuladas: CSS 404 uma vez, módulo JavaScript 404 uma vez, CSS 404 persistente e nova tentativa manual. Verificados uma recuperação automática por destino, ausência de loop e mensagem em português.
- CSS bloqueado após salvar uma pessoa: exatamente um POST e um evento de cadastro na auditoria; destino recuperado sem duplicar a escrita. Cabeçalho do HTML conferido no navegador.
- Chrome em 1440, 390 e 320 px: estrela presente no catálogo de etiquetas e ausente na lista de pessoas, formulário, opções do seletor, modal da pessoa e modal da etiqueta. Cor da etiqueta preservada. Sem rolagem horizontal da página.
- Capturas temporárias em `/tmp/tag-star-*.png` e `/tmp/assets-recovery-fallback.png`. Dados temporários removidos ao concluir.

Uma aba que já estava aberta com JavaScript anterior a esta correção precisa ser atualizada uma vez para receber o novo tratamento.

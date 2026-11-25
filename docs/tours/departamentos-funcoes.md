# Tour de Departamentos e Funções

Artigo: [Departamentos e Funções](../ajuda/departamentos-funcoes.html). Telas: `/conta/departamentos` e `/conta/funcoes` (S03). Executor na barra superior, configurado em `resources/js/app/guidance.ts`, com os passos do cadastro em `resources/js/app/saas/catalogTours.ts`. Nenhum executor ou atalho de tutorial no modal.

As duas telas são únicas para a conta: a lista traz as empresas que o acesso alcança, sem escolha prévia de empresa.

| Ordem | Alvo | Título | Orientação |
| --- | --- | --- | --- |
| 1 | PageHeaderTour | Moldura | Passos compartilhados da moldura, sem duplicar textos. |
| 2 em diante | BusinessListTour | Buscar, filtrar, colunas e paginação | Busca por nome sem acentos nem caixa; filtros Empresa (só com mais de uma empresa) e Situação. Cada linha mostra a empresa na coluna Empresa. Visualizar cadastro abre a ficha, Editar abre a janela. Não há exclusão: inative o que não usa mais. |
| Cadastro 1 | `{tela}-companies` | Empresas | Marque as empresas em que o registro vale; cada uma recebe o seu. Só aparecem as empresas em que o cargo permite cadastrar. Passo pulado quando há uma única empresa ou na edição. |
| Cadastro 2 | `{tela}-fields` | Dados do departamento ou da função | Nome único por empresa, sem diferenciar caixa nem acentos; Situação só oferece o que o cargo permite aplicar. Na edição a empresa aparece só para consulta. |
| Cadastro 3 | `{tela}-modal-actions` | Salvar ou cancelar | Salvar valida e atualiza a lista; Cancelar fecha sem gravar. O tutorial não salva dados. |

## Janela de cadastro

O executor pertence à tela. Os passos do cadastro abrem a janela pelo botão **Cadastrar** da própria tela e, ao terminar ou sair, fecham somente a janela que a demonstração abriu (evento `catalog-editor:close-demo`). Se o usuário já estava com a janela aberta, ela é preservada. Nada é gravado.

Revisar alvos e ordem quando a posição de **Cadastrar**, dos filtros ou das ações mudar.

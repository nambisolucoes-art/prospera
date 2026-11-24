# Tours de Cargos

Artigo: [Cargos e permissões](../ajuda/cargos-permissoes.html). Executor disponível na barra superior.

| Tela / passo | Alvo | Orientação |
| --- | --- | --- |
| Filtros | business-filters | Busque pelo nome e filtre pela origem (Cargo inicial ou Personalizado). Alvos internos: `roles-search` e `roles-origin`. |
| Listagem | roles-list | Consulte cargos e use Visualizar para abrir o modal ou Editar para alterar um cargo autorizado. |
| Cadastro e edição | role-fields | Informe o nome do cargo desta conta. |
| Permissões | role-permissions | Selecione as ações específicas disponibilizadas automaticamente por cada módulo. As permissões não são cadastradas manualmente e não têm tela própria. Permissões não selecionadas ficam bloqueadas. |

Finalize em Salvar. Pule ações não autorizadas, cargos protegidos e gravações em inspeção somente leitura. Preserve seleções ao demonstrar o formulário. Fechar o modal devolve o foco ao botão de origem.

No modal, confira primeiro Dados do cargo e depois Permissões por módulo, com uma etiqueta por ação e o nome do módulo na linha do grupo. Revise o alinhamento no desktop e no mobile.

Executor disponível pelo botão **Tutorial desta tela** na barra superior. Configuração em `resources/js/app/guidance.ts`. Revisar alvos em desktop e mobile a cada alteração.

O passo **Abrangência do cargo** explica o local de disponibilidade e a revisão do impacto antes de salvar.

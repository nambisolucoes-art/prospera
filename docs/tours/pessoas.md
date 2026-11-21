# Tours de Pessoas

Artigo: [Pessoas e acesso](../ajuda/pessoas.md). Integração do executor disponível na barra superior.

## Listagem

| ID | Alvo | Orientação |
| --- | --- | --- |
| people-search | people-search | Digite nome ou e-mail. A busca é automática e pode ser executada pela lupa clicável à direita ou por Enter. |
| people-suppliers | people-suppliers | Clientes ou Fornecedores: pessoas com relação ativa nas empresas em que o leitor pode listar e visualizar o papel. |
| people-company | people-company | Com um papel escolhido e mais de uma empresa autorizada, restringe a uma empresa. Passo omitido quando o filtro não aparece. |
| people-list | people-list | Consulte os cadastros e use os ícones Visualizar e Editar na coluna Ações. |
| person-preview | person-preview | Confira os dados no modal; feche para retornar à lista e ao botão de origem. A seção Relações comerciais (`person-preview-relations`) mostra Empresa, Cliente e Fornecedor só com o que o leitor pode ver. |

## Cadastro e edição

| ID | Alvo | Orientação |
| --- | --- | --- |
| person-identity | person-identity | Informe nome, e-mail e telefone. |
| person-tags | person-tags | Abra o seletor, busque e escolha múltiplas etiquetas. Selecionar Usuário exibe a seção de acesso. Retirar Usuário e salvar revoga o acesso com a permissão específica. Cliente e Fornecedor não são selecionáveis (E07). |
| person-relations | person-relations | Cliente e Fornecedor por empresa, com situação independente. Desmarcar e salvar inativa só a relação da linha. Passo omitido quando o operador não tem permissão comercial em nenhuma empresa. |
| person-access | person-access | Escolha um cargo cadastrado na conta e a forma de acesso. Para um vínculo existente, use **Editar atribuições** para gerir os cargos por escopo. No modal, o acordeão **Permissões efetivas da atribuição** mostra as ações agrupadas por módulo. Este passo aparece quando **Usuário** está selecionado. |

Finalize em Salvar. Preserve dados digitados ao demonstrar o seletor. Pule a seção de acesso quando ausente e as ações indisponíveis na inspeção somente leitura. Ao fechar o modal ou o tour, retorne o foco ao controle de origem.

Executor disponível pelo botão **Tutorial desta tela** na barra superior. Configuração em `resources/js/app/guidance.ts`. Revisar alvos em desktop e mobile a cada alteração.

Os marcadores selecionados e as opções do seletor usam a cor de cada etiqueta, compartilhando o cálculo de contraste de TagLabel. Alvos e ordem preservados. Conferir cores claras e escuras sem alterar os dados ao executar o tour.

Revisão dos marcadores: nenhuma estrela deve aparecer na lista, no seletor ou no modal de pessoa. A estrela permanece somente na listagem de Etiquetas. Alvos e ordem preservados.

Papel na conta exibe o indicador de campo obrigatório. O foco do seletor de etiquetas segue a borda dos demais campos. Alvos e ordem dos passos preservados.

| person-address | person-address | Informe o endereço principal da pessoa, reutilizado por todos os seus papéis. |
| person-contact | person-contact | Telefone e WhatsApp seguem máscaras e ícones do cadastro da conta. Informe os contatos uma única vez. |

Inscrições estadual e municipal ficam disponíveis para PF e PJ. O passo person-contact inclui os botões de adicionar Telefone e E-mail e o X das linhas adicionais. O tour preserva os contatos digitados.

O passo **Consultar por papel** apresenta o filtro Tipo de cadastro. Os papéis aparecem conforme as permissões de listar e visualizar na mesma empresa. Para manter Cliente ou Fornecedor, use a seção **Relações comerciais**. A sequência e os componentes BusinessListTour e PageHeaderTour são preservados.

A seção **Acesso de usuário** explica a primeira atribuição e identifica o e-mail de acesso de um vínculo existente.

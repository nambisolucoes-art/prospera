# Tours de Pessoas

Artigo: [Pessoas e acesso](../ajuda/pessoas.md). Integração do executor disponível na barra superior.

## Listagem

| ID | Alvo | Orientação |
| --- | --- | --- |
| people-search | people-search | Digite nome ou e-mail. A busca é automática e pode ser executada pela lupa clicável à direita ou por Enter. |
| people-list | people-list | Consulte os cadastros e use os ícones Visualizar e Editar na coluna Ações. |
| person-preview | person-preview | Confira os dados no modal; feche para retornar à lista e ao botão de origem. |

## Cadastro e edição

| ID | Alvo | Orientação |
| --- | --- | --- |
| person-identity | person-identity | Informe nome, e-mail e telefone. |
| person-tags | person-tags | Abra o seletor, busque e escolha múltiplas etiquetas. Selecionar Usuário exibe a seção de acesso. Retirar Usuário e salvar revoga o acesso com a permissão específica. |
| person-access | person-access | Escolha um cargo cadastrado na conta e a forma de acesso. Em usuários existentes, a troca de cargo depende da permissão de atribuir cargo. Este passo só aparece quando Usuário está selecionado. |

Finalize em Salvar. Preserve dados digitados ao demonstrar o seletor. Pule a seção de acesso quando ausente e as ações indisponíveis na inspeção somente leitura. Ao fechar o modal ou o tour, retorne o foco ao controle de origem.

Executor disponível pelo botão **Tutorial desta tela** na barra superior. Configuração em `resources/js/app/guidance.ts`. Revisar alvos em desktop e mobile a cada alteração.

Os marcadores selecionados e as opções do seletor usam a cor de cada etiqueta, compartilhando o cálculo de contraste de TagLabel. Alvos e ordem preservados. Conferir cores claras e escuras sem alterar os dados ao executar o tour.

Revisão dos marcadores: nenhuma estrela deve aparecer na lista, no seletor ou no modal de pessoa. A estrela permanece somente na listagem de Etiquetas. Alvos e ordem preservados.

Papel na conta exibe o indicador de campo obrigatório. O foco do seletor de etiquetas segue a borda dos demais campos. Alvos e ordem dos passos preservados.

| people-suppliers | people-suppliers | Filtre Fornecedores para consultar somente os ativos, preservando a busca. Só aparece com a permissão de listar fornecedores. |
| person-address | person-address | Informe o endereço principal da pessoa, reutilizado por todos os seus papéis. |
| person-contact | person-contact | Telefone e WhatsApp seguem máscaras e ícones do cadastro da conta. Informe os contatos uma única vez. |

Identificação, endereço e contatos aparecem sem depender da etiqueta Fornecedor. O tour não seleciona etiquetas nem grava dados. A estrela especial permanece exclusiva da listagem de Etiquetas.

Inscrição estadual e municipal ficam disponíveis para PF e PJ. Avisos de retirada de etiquetas especiais aparecem imediatamente abaixo do campo, alinhados com o controle. O texto explicativo repetido sobre Fornecedor foi removido; alvos e ordem do tour permanecem os mesmos.

O passo person-contact inclui os botões de adicionar Telefone e E-mail e o X das linhas adicionais. O tour não adiciona nem remove linhas: preserva os contatos digitados e salvos. O e-mail principal continua sendo o acesso de usuário.

## Etiqueta especial Cliente

O passo person-tags explica que Cliente e Fornecedor podem coexistir e compartilham a identidade, endereço e contatos da pessoa. Selecionar Cliente não cria acesso de usuário. Retirar e salvar inativa somente esse papel, com aviso imediatamente abaixo do seletor.

O alvo people-suppliers mantém o identificador existente e agora corresponde ao filtro Tipo de cadastro, com Clientes e Fornecedores conforme as permissões de listar cada papel. O passo se chama Consultar por papel. A sequência e os componentes BusinessListTour e PageHeaderTour são preservados. O tour explica os controles sem selecionar etiquetas, trocar filtros ou gravar dados.

A seção **Acesso de usuário** explica a primeira atribuição e identifica o e-mail de acesso de um vínculo existente.

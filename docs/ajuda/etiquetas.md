---
titulo: Etiquetas
resumo: Organize os cadastros com etiquetas por módulo.
categoria: Cadastros
palavras-chave: etiquetas, tags, módulo, pessoas, usuário, especial, cor, cliente
---

## Consultar etiquetas

Usuários de contas ativas com a permissão de listar etiquetas podem abrir **Etiquetas**. Cadastrar, visualizar e editar exigem suas próprias permissões. A lista reúne as etiquetas da conta. No topo, busque pelo nome e filtre por módulo e por situação (**Ativas** ou **Inativas**). A lista se atualiza automaticamente ao digitar; a lupa à direita e Enter também executam a busca. Trocar um filtro preserva a busca e os demais filtros. Cada etiqueta aparece em um marcador quadrado na sua cor; uma estrela amarela discreta indica **Especial** ao passar o mouse ou receber foco. A estrela aparece somente nesta listagem, sem ser repetida em seletores, pessoas ou modais. Inicialmente está disponível apenas **Pessoas**.

## Cadastrar e editar

Clique em **Cadastrar etiqueta** para abrir o modal sobre a listagem. Informe nome, módulo e cor e use **Salvar**. A lista é atualizada ao salvar. **Cancelar**, o botão de fechar ou Esc encerram o modal sem salvar. Erros de validação aparecem no próprio formulário e mantêm os dados preenchidos. O campo **Cor** usa o Colorpicker do Ubold: digite o hexadecimal ou clique na amostra à direita para abrir o seletor de tonalidade e saturação. O nome deve ser único no módulo da conta. Use a ação **Editar cadastro** de uma etiqueta convencional para abrir o mesmo modal com seus dados; a ação **Visualizar cadastro** abre o modal. Selecione-a no campo múltiplo Etiquetas do formulário de pessoa. Os marcadores selecionados e as opções do seletor usam a cor de cada etiqueta, assim como a listagem e a visualização da pessoa. O texto recebe contraste claro ou escuro conforme a cor. Por exemplo, crie **Contato comercial** para organizar pessoas por uma categoria própria. Etiquetas convencionais não criam os papéis das etiquetas especiais.

A visualização de uma etiqueta abre uma janela compacta com nome, cor e situação. Use o botão de fechar ou Esc para voltar à listagem.

## Etiqueta especial Usuário

A etiqueta **Usuário** pertence ao módulo Pessoas e é gerenciada pelo sistema. Selecione-a no cadastro de pessoa para abrir a seção Acesso de usuário e escolher um cargo cadastrado na conta e preencher a forma de acesso. Somente salvar esses dados válidos cria o acesso. Retirar Usuário no formulário de pessoa e salvar revoga o acesso com a permissão específica. O cadastro da definição especial e seu nome são gerenciados pelo sistema; etiquetas convencionais não concedem permissões.

## Orientação na tela

Use **Tutorial desta tela** na barra superior. O tutorial apresenta o cadastro quando permitido, os filtros, as colunas, as ações e a paginação. Ele abre opções e demonstra a largura das colunas no desktop, restaurando as preferências ao sair. **Ver todos os passos** permite escolher um assunto. Esc fecha o tutorial.

O tutorial é iniciado exclusivamente por **Tutorial desta tela**, na barra superior da listagem. Quando necessário, ele abre o modal, explica os campos e as ações e fecha apenas a janela que ele próprio abriu. Não há botão de tutorial dentro do modal. Acessos diretos aos endereços antigos de cadastro e edição abrem o formulário no modal da listagem. Sem permissão de listar, o formulário não revela os demais registros.

## Se a tela não abrir

Após uma atualização, o sistema tenta reabrir a tela automaticamente uma vez. Se aparecer a mensagem de que não foi possível carregar a tela, use **Recarregar tela** para tentar novamente. Essa ação abre a tela sem reenviar o cadastro.

## Ativar, inativar ou excluir

No formulário, escolha Situação: Ativa ou Inativa e salve. Alterar a situação exige, além de editar, a permissão de Ativar ou Inativar etiquetas. Para cadastrar uma etiqueta já inativa, é necessária a permissão de Inativar. Etiquetas inativas permanecem nos registros já vinculados, mas não podem ser adicionadas a outras pessoas. Reativar disponibiliza novamente a etiqueta para seleção.

A listagem e a visualização mostram a situação. A ação Excluir etiqueta usa o ícone de borracha e exige a permissão de Excluir etiquetas. Confirme a exclusão na janela. Uma etiqueta com registros vinculados não pode ser excluída: use Editar cadastro para inativar. O servidor verifica novamente os vínculos ao excluir. Etiquetas especiais não podem ser editadas, inativadas ou excluídas.

## Etiqueta especial Fornecedor

Fornecedor identifica um papel da mesma pessoa, sem repetir CPF/CNPJ, contatos ou endereço e sem conceder acesso ao sistema. Sua definição é protegida, assim como Usuário. Atribuir ou retirar Fornecedor depende das permissões específicas do módulo; retirar e salvar inativa o cadastro complementar, preservando dados e histórico. Uma etiqueta comum com nome semelhante não é convertida automaticamente. Consulte [Fornecedores](fornecedores.md).

## Etiqueta especial Cliente

Cliente também é uma etiqueta especial protegida. Selecionar essa etiqueta em Pessoas cria ou reativa o papel de cliente; retirar e salvar inativa esse papel sem apagar a pessoa. A estrela permanece exclusiva da listagem de Etiquetas. Consulte [Clientes](clientes.md).

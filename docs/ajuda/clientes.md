---
titulo: Clientes
resumo: Consulte e mantenha a relação de cliente de cada empresa, sem duplicar o cadastro da pessoa.
categoria: Cadastros
palavras-chave: cliente, clientes, relação comercial, empresa, área de trabalho, CPF, CNPJ, vincular, ativar, inativar, pessoa
---

## Pessoa, cliente e fornecedor

A **pessoa** é o cadastro central da conta: tipo, nome ou razão social, CPF ou CNPJ, inscrições, contatos e endereço são informados uma única vez. **Cliente** é uma relação comercial entre essa pessoa e uma **empresa** da conta. A mesma pessoa pode ser cliente de uma empresa, fornecedora de outra, as duas coisas na mesma empresa ou não ter relação nenhuma. A relação não concede acesso ao sistema, e um usuário não é cliente só por ter acesso.

Exemplo: João da Silva tem um único cadastro no Grupo Gartan. Na Gartan Roupas, ele é cliente ativo e fornecedor ativo; na Gartan Express, cliente ativo; na Gartan 3, não tem relação. Cada uma dessas relações tem situação e histórico próprios.

## Por que não repetir a pessoa por filial

A relação pertence à empresa, e não ao estabelecimento. As filiais Guanambi, Janaúba e Jequié da Gartan Roupas compartilham a mesma relação de João com a Gartan Roupas. Uma futura venda feita em Guanambi será uma operação daquela unidade, ligada à empresa; o cadastro do cliente continua único.

## Consultar na área de trabalho

Abra **Clientes** no menu. A tela considera as empresas das unidades marcadas no seletor da barra superior. Com Guanambi e Janaúba (Gartan Roupas) e Jequié (Gartan Express), a lista mostra as relações das duas empresas; duas unidades da mesma empresa não repetem a mesma relação. Com mais de uma empresa, a coluna e o filtro **Empresa** aparecem. Busque por nome, e-mail, CPF ou CNPJ e filtre pela situação.


A lista considera as empresas em que seu cargo permite **Listar clientes**. Recarregar a página mantém a seleção de unidades desta aba.

## Cadastrar

Na lista, clique em **Cadastrar** para abrir a página de cadastro. Quando houver mais de uma empresa disponível, escolha a empresa responsável. Para uma Pessoa nova, informe o tipo, a identificação e os dados de endereço e contato que você tiver. Você pode selecionar as etiquetas do módulo **Clientes**. Elas ficam no cadastro comercial da empresa. A etiqueta **Cliente** aparece marcada e permanece fixa. Use **Salvar** para criar a Pessoa e registrar o cadastro de cliente na empresa. Criar os dados compartilhados exige também a permissão **Cadastrar pessoas**.

Se a Pessoa já estiver cadastrada, selecione **Pessoa física** para buscar por CPF ou **Pessoa jurídica** para buscar por CNPJ. Ao preencher o documento completo, aguarde a busca automática ou clique na lupa para buscar na hora. Se houver resultado, confira o aviso junto ao campo do documento. Se a busca não encontrar uma ficha e você tiver permissão para cadastrar Pessoas, continue preenchendo o formulário. A ficha preenche os dados centrais que você pode visualizar e indica o outro papel existente quando você tem acesso a ele na empresa. **Salvar** reutiliza a Pessoa e cadastra o papel na empresa escolhida. Você pode completar ou corrigir os dados centrais conforme suas permissões de Pessoas. O ícone de informação ao lado de **Deseja utilizar outro cadastro?** indica que essas alterações valem para toda a conta. O CPF ou CNPJ usado para localizar o cadastro permanece fixo. Para pesquisar outro documento, use **Descartar**. Essa ação limpa a ficha selecionada no formulário sem excluir o cadastro. Se o papel estiver inativo, use **Ativar** na listagem.

## Editar ou reativar

Na coluna **Ações**, escolha **Editar cliente** para abrir a ficha na empresa da linha. Você pode atualizar as etiquetas comerciais e, com permissão em Pessoas, alterar os dados compartilhados liberados no formulário. Use **Salvar** para aplicar ou **Cancelar** para voltar à lista. A situação do cadastro permanece a mesma ao editar.

Use **Inativar** ou **Ativar** na coluna **Ações** para mudar a situação do vínculo na empresa da linha, conforme seu cargo. A mudança vale somente para esse papel nessa empresa.

## Permissões

Cada ação tem permissão própria: **Listar**, **Visualizar**, **Cadastrar**, **Editar**, **Ativar** e **Inativar clientes**. Elas valem por empresa: um cargo atribuído na Gartan Roupas não alcança a Gartan Express, e um cargo atribuído somente num estabelecimento não alcança a relação comercial da empresa. Os dados centrais seguem as permissões de Pessoas: quem atende pelo menos uma empresa da pessoa altera contatos e endereço; nome, CPF ou CNPJ e inscrições exigem atender todas as empresas da pessoa ou a administração da conta.

## Histórico

Cadastro, edição, ativação e inativação são registrados na Auditoria como **Cliente**, com a empresa da relação, o autor e, na inspeção SaaS, o usuário representado. Salvar sem mudança não gera registro. CPF ou CNPJ completo não entra no histórico.

Cada ficha abre pelo botão **Visualizar cadastro** da própria listagem e apresenta a etiqueta **Cliente**. Para usar o mesmo CPF ou CNPJ também como **Fornecedor**, faça a busca pelo documento no cadastro do outro módulo e vincule o registro encontrado. A situação de cada vínculo é independente.

---
titulo: Pessoas e acesso de usuário
resumo: Cadastre pessoas, selecione etiquetas e conceda acesso à conta pela etiqueta Usuário.
categoria: Cadastros
palavras-chave: pessoas, contatos, usuários, acesso, convite, senha, etiquetas, cliente, visualizar
---

## Consultar e visualizar

Usuários de contas ativas com permissão de listar ou cadastrar pessoas encontram **Pessoas** no menu da conta. Digite nome ou e-mail na busca: a lista atualiza automaticamente. A lupa à direita também executa a pesquisa ao clicar, assim como Enter. Na coluna **Ações**, use **Visualizar cadastro** para abrir o modal com os dados da pessoa ou **Editar cadastro** para abrir o formulário. O nome não é um link.

## Cadastrar uma pessoa

Abra **Cadastrar pessoa**. Informe nome, e-mail opcional e telefone opcional. As etiquetas aparecem em marcadores quadrados com suas próprias cores. A estrela de etiqueta especial aparece somente na listagem de Etiquetas, não em Pessoas ou no seletor. No seletor **Etiquetas**, selecione uma ou várias etiquetas disponíveis no módulo Pessoas da conta. Elas aparecem dentro do campo com sua própria cor cadastrada, também exibida nas opções do menu; busque pelo nome e use o × para retirar uma seleção. Salve em **Salvar**. Pessoas sem a etiqueta Usuário não recebem acesso.

## Conceder acesso pela etiqueta Usuário

Selecione **Usuário** no campo Etiquetas para exibir **Acesso de usuário**. Informe e-mail, cargo cadastrado na conta e a forma de acesso: convite por e-mail ou senha inicial com confirmação. Retirar a etiqueta antes da criação oculta a seção e cancela a criação do acesso. O cadastro é gravado junto com o acesso. A confirmação é a mesma para uma solicitação com endereço já usado. Nesse caso, o vínculo novo fica indisponível.

Para conceder acesso a uma pessoa existente, use **Editar cadastro** e selecione **Usuário**. Não existe item Usuários no menu da conta.

## Editar uma pessoa com acesso

Para revogar o acesso, retire a etiqueta Usuário e salve. É necessária a permissão Revogar acesso de Usuários. O cadastro da pessoa e o histórico são preservados. Sessões existentes são bloqueadas na próxima requisição. O último administrador com acesso não pode ser revogado. Selecionar novamente a etiqueta reativa o mesmo usuário, com as permissões de cadastrar usuário e atribuir cargo. O formulário mostra o cargo atual e permite alterá-lo com a permissão específica de atribuir cargo. Consulte [Cargos e permissões](cargos-permissoes.md). Nome e e-mail são sincronizados com o usuário. Mudanças no e-mail exigem nova verificação.

Na inspeção somente leitura, a visualização continua disponível e as ações de gravação ficam indisponíveis.

## Orientação na tela

Use **Tutorial desta tela** na barra superior. O tutorial apresenta o cadastro quando permitido, os filtros, as colunas, as ações e a paginação. Ele abre opções e demonstra a largura das colunas no desktop, restaurando as preferências ao sair. **Ver todos os passos** permite escolher um assunto. Esc fecha o tutorial.

## Identificação, endereço e contatos compartilhados

Tipo de pessoa, Nome ou Razão social, CPF ou CNPJ, Nome fantasia, inscrições, endereço e contatos pertencem à pessoa. Eles aparecem independentemente das etiquetas escolhidas. CPF/CNPJ é opcional e, quando preenchido, passa por dígitos verificadores e unicidade dentro da conta. CNPJ numérico e alfanumérico são aceitos. O tipo padrão é Pessoa física. Ao escolher Pessoa jurídica, o formulário mostra os campos próprios da empresa. Inscrição estadual e Inscrição municipal são opcionais tanto para Pessoa física quanto para Pessoa jurídica e são preservadas ao trocar o tipo. Ao voltar para pessoa física e salvar, apenas Nome fantasia é limpo.

Telefone e WhatsApp usam máscaras e os mesmos ícones e grupos de campo do formulário da conta. E-mail é obrigatório quando houver acesso de usuário. Site deve começar por http:// ou https://. O endereço principal usa a mesma consulta de CEP e os seletores de estado e município já utilizados na conta. Revise os dados antes de Salvar.

## Fornecedores

A etiqueta especial Fornecedor identifica um papel da mesma pessoa. Não há CPF, contatos ou endereço repetidos em uma seção de fornecedor. Na listagem, Tipo de cadastro permite consultar os fornecedores ativos conforme a permissão. Retirar a etiqueta e salvar inativa o vínculo, preservando a pessoa e seus dados comuns. Consulte [Fornecedores](fornecedores.md).

## Telefones e e-mails adicionais

Na seção Informações de contato, use os botões Telefone e E-mail com o ícone de adicionar, como no cadastro da conta. As novas linhas aparecem como Telefone 2, E-mail 2 e assim por diante. Preencha até 20 contatos adicionais de cada tipo. O botão X vermelho remove apenas a linha correspondente. As alterações são gravadas ao clicar em Salvar; Cancelar não grava inclusões ou remoções. Linhas vazias são ignoradas.

O telefone e o e-mail principais permanecem nos seus campos. E-mails adicionais são contatos e não criam login, não alteram a senha e não substituem o e-mail principal do usuário. Telefones usam a mesma máscara e os mesmos ícones da conta. A visualização da pessoa mostra os contatos adicionais. Esses dados pertencem à pessoa e são compartilhados por suas etiquetas e papéis. Todas as alterações são auditadas no escopo da conta.

## Papel de cliente

Selecione a etiqueta especial Cliente para identificar um cliente da conta. Os dados já informados pertencem à pessoa e são compartilhados com Fornecedor e outros papéis. Retirar Cliente e salvar inativa somente esse vínculo; selecionar novamente reativa o mesmo registro, conforme as permissões específicas de Clientes. O filtro Tipo de cadastro oferece Clientes aos usuários autorizados. Consulte [Clientes](clientes.md).
\n## Editar atribuições\n\nNo cadastro de uma pessoa com vínculo, use **Editar atribuições** para gerir os cargos nos locais que você administra. **E-mail de acesso** é somente leitura. Alterações no nome, no e-mail de contato e nos outros dados de Pessoas ficam no cadastro da conta.\n
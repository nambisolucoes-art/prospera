---
titulo: Usuários
resumo: Consulte os usuários na plataforma ou na sua conta.
categoria: Configurações
palavras-chave: usuários, equipe, administradores, foto, avatar, iniciais
---

## Consultar usuários

Abra **Usuários** no menu lateral. Na coluna **Nome**, a foto aparece ao lado da pessoa. Se ela ainda não adicionou uma foto ao [perfil](perfil.md), a tabela mostra as iniciais.

## Acesso de usuários da conta

Nos cadastros com senha inicial, use o ícone de olho para **Mostrar senha** e clique novamente para **Ocultar senha**. A senha e a confirmação têm controles independentes.

Em **Usuários**, pesquise por nome ou e-mail de acesso. A coluna **Atribuições** lista cada cargo e o local em que ele vale, conforme o seu acesso. Use o ícone de informação para abrir **Visualizar usuário** e conferir os dados de acesso. Use **Editar atribuições** na coluna **Ações** para ajustar os cargos conforme sua autorização. Quem administra o acesso à conta também pode suspender ou reativar o vínculo pela ficha. O cadastro de um vínculo aplica automaticamente a etiqueta **Usuário**.

Em **Tipo de cadastro**, a opção inicial é **Pessoa ainda não cadastrada**. Para conceder acesso a alguém que já tem dados na conta, escolha **Pessoa já cadastrada**, procure pelo nome ou e-mail e selecione o cadastro encontrado. O nome vem da ficha e o e-mail de contato é sugerido como e-mail de acesso. Você pode informar outro e-mail de acesso. A forma de acesso e a primeira atribuição continuam obrigatórias. Essa opção exige autorização para visualizar os dados compartilhados da pessoa e só oferece cadastros sem acesso nesta conta.

Ao cadastrar um usuário, escolha o local e o cargo da primeira atribuição. O sistema apresenta a mesma confirmação quando a solicitação usa um e-mail já existente. Até a seleção de múltiplas contas ser disponibilizada, um endereço existente em outra conta não recebe um vínculo novo.

## Orientação na tela

Use **Tutorial desta tela** na barra superior. O tutorial apresenta o cadastro quando permitido, os filtros, as colunas, as ações e a paginação. Ele abre opções e demonstra a largura das colunas no desktop, restaurando as preferências ao sair. **Ver todos os passos** permite escolher um assunto. Esc fecha o tutorial.

## Orientações nos campos

As dicas de Forma de acesso e Confirmar senha explicam o envio do convite e a necessidade de repetir exatamente a senha inicial. Passe o mouse, use Tab para focar o ícone ou toque nele no celular. Esc fecha a dica sem alterar os dados do formulário.


## Selecionar conta e aceitar convites

A mesma identidade pode ter vínculos independentes com várias contas. Após entrar, uma única conta
ativa abre automaticamente. Com várias contas disponíveis, escolha em Selecionar conta. O endereço
sempre contém `/contas/{codigo}`. Trocar de conta afeta somente a aba atual. Cargos, unidades e
preferências de área de trabalho pertencem ao vínculo em cada conta. Vínculos suspensos e contas
inativas não permitem entrada.

Para uma identidade já existente, o cadastro de acesso solicita um convite com resposta neutra.
O convite pendente não concede acesso. Entre com o e-mail destinatário, abra o link recebido,
confira conta, cargo e validade e use Salvar para aceitar. O link tem uso único, expira e pode ser
revogado por um operador com permissão. Um novo envio revoga o convite pendente anterior.
Cancelar não concede acesso. Nenhuma conta pode alterar sua identidade de login.

A configuração `prospera.multiple_accounts` controla novas associações por convite. Desligá-la
bloqueia esses aceites, preservando os vínculos e a seleção explícita de contas já existentes.


## Preservar a administração da conta

Antes de suspender ou retirar o acesso de um administrador, mantenha outro administrador utilizável
na mesma conta. O substituto precisa ter acesso ativo, identidade habilitada, e-mail verificado e senha
inicial definida. Um convite pendente não substitui o administrador atual. Suspender um vínculo não
altera a identidade de login nem os acessos da pessoa em outras contas.

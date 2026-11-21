---
titulo: Cargos e permissões
resumo: Defina as ações permitidas para cada cargo e atribua um cargo ao acesso da pessoa.
categoria: Acesso e segurança
palavras-chave: cargos, papéis, permissões, catálogo, acesso, equipe, usuário, administrador, visualizar, cadastrar, editar
---

## Consultar cargos

O item **Cargos** aparece no menu conforme as permissões do usuário. Quando o usuário pode apenas cadastrar, o item abre o formulário; após salvar, permanece numa tela autorizada. Listar, visualizar, cadastrar e editar são autorizações separadas. No topo da lista, busque pelo nome do cargo e filtre pela origem: **Cargo inicial** ou **Personalizado**. A lista se atualiza automaticamente ao digitar; a lupa à direita e Enter também executam a busca. Use a ação de informação para abrir o modal do cargo, com nome, origem, quantidade de permissões e ações atribuídas agrupadas por módulo.

As permissões são disponibilizadas automaticamente pelos módulos do sistema. Não existe uma tela ou cadastro de permissões. A seleção das ações autorizadas acontece no formulário de cadastro e edição do cargo.

## Cadastrar um cargo

Em **Cargos > Cadastrar**, informe o nome e marque cada ação permitida, agrupada por módulo. Por exemplo, **Listar: Pessoas** permite consultar a lista; **Visualizar: Pessoas** permite abrir o modal; **Cadastrar: Pessoas** permite criar pessoas; **Editar: Pessoas** permite alterar o cadastro. Salve em **Salvar**.

O cargo vale somente na conta atual. Permissões não marcadas ficam bloqueadas. Você só pode conceder permissões que já possui. O administrador inicial da conta é protegido para preservar o acesso administrativo. O cargo inicial Usuário oferece acesso básico e pode ser editado, assim como cargos personalizados.

## Atribuir um cargo à pessoa

Em **Pessoas**, cadastre ou edite uma pessoa e selecione a etiqueta **Usuário**. Em **Papel na conta**, escolha um dos cargos cadastrados na conta. Defina a forma de acesso e salve. Criar acesso e atribuir cargo exigem suas próprias permissões, além da permissão de cadastrar ou editar a pessoa.

Para um usuário existente, o campo mostra o cargo atual e permite trocá-lo quando autorizado. O último administrador deve permanecer com o cargo de administração. Só é possível atribuir cargos cujas permissões você possui. As alterações passam a valer nas próximas requisições, sem precisar sair e entrar novamente.

## Bloqueios

A permissão de editar uma pessoa não concede automaticamente permissão para editar seus dados de usuário ou atribuir cargos. A conta não pode usar cargos de outra conta nem permissões da plataforma SaaS. A inspeção somente leitura permite as consultas autorizadas e bloqueia gravações.

## Orientação na tela

Use **Tutorial desta tela** na barra superior. O tutorial apresenta o cadastro quando permitido, os filtros, as colunas, as ações e a paginação. Ele abre opções e demonstra a largura das colunas no desktop, restaurando as preferências ao sair. **Ver todos os passos** permite escolher um assunto. Esc fecha o tutorial.

## Definir a abrangência de um cargo

Em **Abrangência**, escolha **Toda a conta** ou **Restrita a empresas ou estabelecimentos selecionados**. A disponibilidade indica onde o cargo pode ser usado. A atribuição em **Usuários** concede o cargo a um vínculo no local escolhido. As permissões do cargo são avaliadas sobre o recurso da ação e seguem o alcance do local da atribuição.

Ao restringir um cargo existente, selecione os locais e escolha **Verificar impacto**. Revise a lista e confirme a remoção das atribuições que ficarem fora da abrangência antes de escolher **Salvar**. Se a lista mudar desde a conferência, faça a verificação novamente. Cargos de sistema abrangem toda a conta.

## Orientações nos campos

As dicas de Disponibilidade e dos grupos de permissões explicam a abrangência do cargo e a seleção independente de cada ação. Só é possível conceder permissões que o operador possui. Passe o mouse, use Tab para focar o ícone ou toque nele no celular. Esc fecha a dica sem alterar os dados do formulário.

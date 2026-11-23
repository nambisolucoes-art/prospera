---
titulo: Auditoria
resumo: Consulte quem mudou um registro, quando e quais valores foram alterados.
categoria: Sistema
palavras-chave: auditoria, ajuda, tutorial, histórico, consultar
---

## O que é a auditoria

A auditoria é o histórico das mudanças feitas no Prospera: quem fez, quando, o que mudou e qual conta, empresa ou estabelecimento foi afetado. Ela registra cadastros, alterações, exclusões, acessos e inspeções. Consultas, pesquisas e navegação não aparecem como mudanças.

## Consultar o histórico

Abra **Auditoria** no menu. É necessária a permissão de listar auditoria. Busque pelo nome do registro ou do autor para atualizar a lista automaticamente. A lupa à direita e Enter também executam a busca. Nas datas, digite no formato DD/MM/AAAA ou use o botão de calendário à direita para escolher o dia; **Limpar** remove a data. Uma data inexistente é indicada abaixo do campo e não é aplicada.

Cada linha mostra o registro, a ação, o tipo, **Onde** a mudança aconteceu, **Quem fez** e **Quando**. Em **Onde** aparece o estabelecimento com a empresa abaixo, a empresa, ou Conta quando a mudança é da conta inteira, como a criação de cargos, mudanças de usuários, etiquetas e os dados centrais compartilhados usados por Clientes, Fornecedores e Usuários. A inclusão ou remoção de uma restrição de disponibilidade de cargo aparece na empresa ou no estabelecimento da restrição.

## Filtrar

- **Tipo**, **Ação** e **Autor** mostram apenas opções que existem nos eventos que você pode ver.
- **Origem** separa operações feitas na conta, pela plataforma, durante uma inspeção ou pelo sistema.
- **Empresa**, **Estabelecimento** e **Nível** aparecem quando o seu acesso alcança mais de uma opção. Ao escolher uma empresa, Estabelecimento lista só as unidades dela. Nível separa eventos da conta, das empresas e dos estabelecimentos.
- A plataforma filtra por **Conta**.

Os filtros só restringem o que você já pode ver. O estabelecimento escolhido na Área de Trabalho não muda a auditoria.

## Por que alguns eventos não aparecem

A auditoria mostra os eventos dos lugares onde você tem a permissão de auditoria:

- Com a permissão na conta inteira, você vê todos os eventos da conta.
- Com a permissão numa empresa, vê os eventos daquela empresa e das unidades dela, mas não os da conta nem os de outras empresas.
- Com a permissão num estabelecimento, vê só os eventos daquela unidade.
- Com várias atribuições, vê a soma dos lugares autorizados.

As mudanças no cadastro central de uma pessoa (nome, documento, contatos e endereço) valem para todas as empresas e são eventos da conta. Quem audita uma empresa vê o cadastro e a inativação do cliente ou fornecedor naquela empresa, mas não o histórico do cadastro central. As contagens e as opções dos filtros consideram só os eventos que você pode ver. Um registro fora do seu acesso não é encontrado.

## Visualizar uma operação

Com a permissão de visualizar auditoria, use o botão azul **Visualizar registro**, identificado por um ícone de informação, na coluna **Ações**. A ficha organiza o evento em seções:

- **Operação:** registro, tipo, ação, data e hora.
- **Autoria:** quem fez e a origem. Numa inspeção, também o usuário representado e o modo.
- **Contexto organizacional:** nível, empresa e estabelecimento afetados. **Não se aplica** indica um evento da conta.
- **Campos:** em alterações, compare **Antes**, com o valor antigo riscado, e **Depois**, com o novo valor destacado. Em cadastros aparece **Valor registrado**; em exclusões, **Valor ao excluir**. Nas listas, o sinal de mais mostra os itens adicionados e o de menos, os removidos.

As miniabas usam botões inverse quadrados em cinza grafite. A aba selecionada aparece pressionada. **Formatado** abre primeiro. A aba **Log técnico** exige permissão própria e mostra os identificadores da operação. Use as setas do teclado para alternar as abas quando estiverem em foco. **Fechar** retorna à listagem. Cada novo registro abre em Formatado.

## Inspeção administrativa

O suporte da plataforma pode inspecionar uma conta usando o acesso de um usuário dela, em modo **Somente leitura** ou **Leitura e edição**. Na auditoria, uma operação feita durante a inspeção tem a marca **Inspeção**: **Quem fez** é o administrador da plataforma, e o usuário representado aparece separado, com o nome que tinha na data da operação. O representado nunca é registrado como autor. O histórico registra o início e o encerramento solicitado, a saída da sessão e a perda de autorização detectada durante o uso. Uma sessão expirada impede continuar a inspeção. O histórico pode mostrar apenas o início quando a sessão expira sem uma ação de encerramento. Em registros antigos, o nome do representado e o modo podem aparecer como não registrados.

## Histórico protegido

O histórico é somente leitura. Não há cadastro, edição ou exclusão de eventos. O nome do autor e o do registro ficam como eram na data da operação, mesmo que sejam alterados depois. Alterações de senha, segundo fator e foto mostram apenas que o dado foi alterado, e documentos de pessoas aparecem como Documento informado. Os valores protegidos nunca aparecem. Atualizações sem mudança e operações revertidas não aparecem como alterações concluídas.

## Cobertura

Contas, empresas, estabelecimentos, pessoas, clientes, fornecedores, usuários, vínculos, etiquetas, cargos, permissões dos cargos e atribuições têm histórico. Entrada, saída, chaves de acesso, envio de link de redefinição e de convite, início e encerramento de inspeção e sincronização do catálogo também são registrados. Eventos anteriores à central foram importados com os dados disponíveis; valores que não foram guardados no histórico anterior não podem ser reconstruídos. Eventos antigos sem empresa ou estabelecimento continuam como eventos da conta.

## Tutorial

Use **Tutorial desta tela** na barra superior para conhecer filtros, colunas, largura da tabela, ações e paginação. **Ver todos os passos** permite ir direto a um assunto. O tutorial abre menus, demonstra a largura e retorna às suas preferências ao sair do passo. Não altera a página nem a quantidade selecionada.

Se houver uma operação visível com permissão de visualizar, os passos finais abrem seu detalhe e apresentam resumo, autoria e organização, campos, abas e fechamento. A janela aberta pelo tour fecha ao concluir essa parte. O Log técnico é demonstrado apenas para quem possui acesso. Feche com Esc ou Concluir.

## Cores das ações

As badges identificam a ação na listagem e no detalhe: cadastro em verde, alteração em âmbar e exclusão em vermelho. Entrada usa azul, saída usa cinza, inspeção iniciada usa roxo e inspeção encerrada usa cinza. Sincronizações usam âmbar. O nome da ação continua visível para que a identificação não dependa somente da cor.

Os tons seguem a paleta auxiliar compartilhada do sistema, também usada em situações e botões. A identidade de cores da marca permanece a mesma. A marca roxa **Inspeção** ao lado do autor identifica operações feitas durante uma inspeção.

## Valores apresentados

O detalhe traduz valores de opções para os nomes usados nos formulários, inclusive em registros antigos: Modo de inspeção mostra Somente leitura ou Leitura e edição, módulos de etiquetas mostram Clientes ou Fornecedores, e classificações anteriores mostram Cadastro anterior e tipos de pessoa mostram Pessoa física ou Pessoa jurídica. Situações mostram Ativa ou Inativa, e acesso mostra Habilitado ou Revogado. Os valores históricos armazenados são preservados; a formatação acontece na consulta. Textos livres não são traduzidos.

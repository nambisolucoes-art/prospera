---
titulo: Auditoria
resumo: Consulte quem mudou um registro, quando e quais valores foram alterados.
categoria: Sistema
palavras-chave: auditoria, ajuda, tutorial, histórico, consultar
---

## Consultar o histórico

Abra **Auditoria** no menu. É necessária a permissão de listar auditoria. Busque pelo nome do registro ou do autor para atualizar a lista automaticamente. A lupa à direita e Enter também executam a busca. Filtre por tipo, ação, autor e datas inicial e final. Nas datas, digite no formato DD/MM/AAAA ou use o botão de calendário à direita para escolher o dia; **Limpar** remove a data. Uma data inexistente é indicada abaixo do campo e não é aplicada. A plataforma também pode filtrar por conta. Dentro da conta, somente os eventos daquela conta ficam disponíveis, inclusive nas opções dos filtros.

## Visualizar uma operação

Com a permissão de visualizar auditoria, use o botão azul **Visualizar registro**, identificado por um ícone de informação, na coluna **Ações**. O modal informa o registro, autor, data, ação e contexto. Em uma inspeção, o autor é o administrador SaaS e o usuário representado é identificado separadamente. As miniabas usam botões inverse quadrados em cinza grafite. A aba selecionada aparece pressionada. **Formatado** abre primeiro. Em alterações, compare **Antes**, com o valor antigo riscado, e **Depois**, com o novo valor destacado. Em cadastros aparece **Valor registrado**; em exclusões, **Valor ao excluir**. Nas listas, o sinal de mais mostra os itens adicionados e o de menos, os removidos.

A aba **Log técnico** exige permissão própria e mostra os identificadores e valores seguros da operação. Use as setas do teclado para alternar as abas quando estiverem em foco. **Fechar** retorna à listagem. Cada novo registro abre em Formatado.

O histórico é somente leitura. Não há cadastro, edição ou exclusão de eventos na interface. Alterações de senha, segundo fator e foto mostram apenas que o dado foi alterado. Os valores protegidos nunca aparecem. Atualizações sem mudança e operações revertidas não aparecem como alterações concluídas.

## Cobertura

Contas, pontos, pessoas, usuários, etiquetas, cargos e suas permissões têm histórico. Entrada, saída, chaves de acesso, início e encerramento de inspeção e sincronização do catálogo também são registrados. Leituras, pesquisas, cache e sessões não criam eventos de mudança. Eventos anteriores à central são importados com os dados disponíveis; valores que não foram guardados no histórico anterior não podem ser reconstruídos.

## Tutorial

Use **Tutorial desta tela** na barra superior para conhecer filtros, colunas, largura da tabela, ações e paginação. **Ver todos os passos** permite ir direto a um assunto. O tutorial abre menus, demonstra a largura e retorna às suas preferências ao sair do passo. Não altera a página nem a quantidade selecionada.

Se houver uma operação visível com permissão de visualizar, os passos finais abrem seu detalhe e apresentam resumo, campos, abas e fechamento. A janela aberta pelo tour fecha ao concluir essa parte. O Log técnico é demonstrado apenas para quem possui acesso. Feche com Esc ou Concluir.

## Cores das ações

As badges identificam a ação na listagem e no detalhe: cadastro em verde, alteração em amarelo e exclusão em vermelho. Entrada usa ciano, saída usa cinza, inspeção iniciada usa roxo e inspeção encerrada usa cinza. Sincronizações usam amarelo. O nome da ação continua visível para que a identificação não dependa somente da cor.

## Valores apresentados

O detalhe traduz valores de opções para os nomes usados nos formulários, inclusive em registros antigos: Modo de inspeção mostra Somente leitura ou Leitura e edição, módulo people mostra Pessoas e tipos de pessoa mostram Pessoa física ou Pessoa jurídica. Situações mostram Ativa ou Inativa, e acesso mostra Habilitado ou Revogado. Os valores históricos armazenados são preservados; a formatação acontece na consulta. Textos livres não são traduzidos.

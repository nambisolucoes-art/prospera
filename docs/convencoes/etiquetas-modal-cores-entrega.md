# Etiquetas em modal e cores nos cadastros

Cadastro e edição de etiquetas passaram a usar `TagEditorModal` na própria listagem. Removida a tela React separada `tag-form.tsx`. Endereços antigos continuam autorizados por ação e compõem a mesma listagem com o modal aberto. Quem possui cadastro ou edição sem listar recebe somente o formulário e uma listagem vazia no servidor; os demais registros não são enviados.

O modal reutiliza BusinessForm, os campos horizontais e o Colorpicker do Ubold. Erros permanecem no formulário, Cancelar não grava, salvar atualiza a listagem. Esc fecha primeiro a paleta e depois o modal. O tour da listagem demonstra os campos sem salvar; O tutorial é iniciado na listagem e abre e fecha o modal durante a explicação, sem instância ou botão de tour dentro dele. Artigos de Etiquetas e Pessoas e seus roteiros foram atualizados.

`TagLabel.colors.ts` compartilha a cor e o contraste entre TagLabel e TagSelect. A cor cadastrada aparece nos marcadores selecionados e opções de Pessoas, nas listagens, na visualização da pessoa e na visualização da etiqueta. A etiqueta especial Usuário preserva sua própria cor e suas regras de acesso. Remover um marcador mantém o foco no seletor, permitindo fechá-lo por Esc.

As gravações continuam em SaveTagAction e SavePersonAction, na mesma transação da auditoria. Cadastro e edição registram autor, conta e valores anteriores e novos, incluindo a cor. As permissões de listar, visualizar, cadastrar e editar continuam independentes e são verificadas no servidor. Nenhuma permissão nova ou operação de negócio adicional foi criada.

## Verificação

- Laravel: 82 testes, 615 asserções. Casos novos conferem o payload dos modais, acesso sem listar, isolamento entre contas, cores nos dados da pessoa e auditoria da mudança de cor.
- PHPStan, Pint dos arquivos PHP alterados, arquitetura, TypeScript e build aprovados.
- Chrome em 1440, 390 e 320 px: abrir cadastro sem navegar, validação no modal, paleta de cor aberta, Esc, cancelar, cadastrar e editar com sucesso. Cores escuras e claras, contraste e gravação verificados.
- No formulário de pessoa: cores nas opções e marcadores, seleção e remoção de Usuário com exibição condicional do acesso, cadastro da pessoa e mesma cor na listagem e no modal de visualização. Sem rolagem horizontal da página.
- URL antiga de cadastro abre o modal. O tour da listagem preserva os dados e abre e fecha somente sua demonstração. Perfil com cadastrar sem listar salva pelo modal sem receber a lista de etiquetas.
- Capturas temporárias em `/tmp/tag-modal-palette-*.png`, `/tmp/tag-list-colored-*.png` e `/tmp/person-tag-colors-*.png`. Dados temporários removidos ao concluir, preservando as demais contas e usuários.

## Tour exclusivo da tela

Removidos o botão de tutorial e a instância de GuidedTour do modal. O executor fica exclusivamente na tela, aberto pela barra superior. `tagListFormTourSteps` identifica a ação de cadastro, abre o modal, mantém a janela durante os passos de campos e ações e fecha somente a demonstração ao sair. Os endereços antigos usam a mesma composição de passos da tela. Regra registrada em AGENTS.md e na convenção de tours.

Build, arquitetura e TypeScript aprovados. Chrome em 1440, 390 e 320 px: modal manual sem botão de tour, abertura pelo tour da listagem, avançar e voltar sem duplicar janelas, fechamento por Concluir e Esc, retorno do foco e contagem de etiquetas inalterada. Capturas em `/tmp/screen-tour-tag-modal-*.png`. Dados temporários removidos ao concluir.

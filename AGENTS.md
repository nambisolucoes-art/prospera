# Prospera

O Prospera usa Laravel, PostgreSQL, Octane e FrankenPHP, com React e TypeScript. A aplicação React troca dados com o Laravel pela API JSON. Consulte o [README](README.md) para rodar o projeto e localizar os padrões de código.

## Convenções por tarefa

| Ao trabalhar em | Consulte |
| --- | --- |
| Tela de negócio nova ou alteração de fluxo, campos, regras ou permissões | [Central de Ajuda](docs/convencoes/ajuda.md) e [tour guiado](docs/convencoes/tour-guiado.md) |
| Operação que muda dados de negócio, acesso ou permissões | [Auditoria](docs/convencoes/auditoria.md) |
| Qualquer alteração visível, inclusive em componentes compartilhados | [Validação visual](docs/convencoes/validacao-visual.md) |

## Responsabilidade pela entrega

- Toda tela de negócio nova deve estar coberta por um artigo de ajuda e ter um tour próprio. Um artigo pode cobrir várias telas do mesmo fluxo.
- Ao alterar uma funcionalidade, atualize na mesma tarefa os artigos e passos afetados. Alterações de layout também exigem revisar os destaques e a ordem do tour quando mudarem a posição ou a disponibilidade dos controles. Ajustes puramente visuais exigem revisão, com edição do conteúdo quando necessário.
- Quem implementa uma alteração visível verifica a aplicação rodando no navegador em desktop e mobile. Na entrega, informe as telas, as larguras, os estados verificados e qualquer limitação que tenha impedido um teste.
- Ajuda, tour e auditoria têm suas convenções registradas para orientar a implementação. Até que o suporte esteja disponível, versione o conteúdo de ajuda e os passos das telas de negócio novas ou alteradas em `docs/ajuda/` e `docs/tours/`, respectivamente. Registre a integração pendente na entrega. Para operações auditáveis, registre o evento e seu escopo no trabalho correspondente.

## Padrões do código

- No backend, siga `app/Domains/{Dominio}`. Controllers coletam a entrada e o contexto. `Application/Data` sanitiza, normaliza e valida. Actions executam os casos de uso, Queries cuidam das consultas e DTOs de domínio representam valores estruturados. A validação fica nas classes Data, sem criar FormRequests.
- CPF/CNPJ, tipo de pessoa, nome ou razão social, nome fantasia, inscrições, endereço principal e contatos pertencem a Pessoas. Etiquetas e papéis como Fornecedor não duplicam esses dados; guardam apenas o vínculo e informações realmente específicas do papel. Campos de telefone, WhatsApp e endereço devem reutilizar as máscaras, ícones e grupos de campos do cadastro da conta.
- No frontend, reutilize os componentes Bold já integrados, como `BusinessForm`, `BusinessList` e `SelectControl`. Mantenha a composição das telas nas pastas de integração e estilos compartilhados nos tokens de `resources/js/styles/`.
- Preserve o escopo de acesso entre plataforma SaaS e conta. A autorização pertence ao servidor, inclusive quando a interface oculta uma ação.

## Cargos e permissões

- As permissões são definidas no código e sincronizadas automaticamente. Não crie telas, rotas ou formulários de cadastro de permissões; a seleção acontece no formulário de cadastro e edição de Cargos.
- Todo módulo de negócio deve entrar no catálogo de permissões. Cada ação possui uma permissão própria: listar, visualizar, cadastrar, editar e quaisquer outras operações existentes. Não use uma permissão genérica de gerenciar para liberar todas as ações.
- Siga [Cargos e permissões](docs/convencoes/permissoes.md). Declare as verificações com identificadores literais `account.modulo.acao`, `platform.modulo.acao` ou `self.modulo.acao`. O comando `php artisan permissions:sync` descobre esses usos no backend e nas rotas e sincroniza o catálogo; execute-o ao acrescentar ou mudar ações.
- Autorize no servidor cada rota e ação sensível, inclusive operações de acesso dentro de outro formulário. A interface consome `auth.permissions`; o cargo do usuário vem do banco e pertence à conta. Não use `is_account_admin` como atalho de autorização nem liste cargos fixos no formulário.
- Teste a separação entre ações, contas e plataforma, a atribuição de cargos e as tentativas de conceder permissões que o operador não possui.

## Rótulos dos botões

Nos formulários e modais de negócio, a ação de persistir usa somente **Salvar** e a confirmação de exclusão usa somente **Excluir**, sem acrescentar o nome do recurso. Cancelar permanece branco, Salvar verde e Excluir vermelho. Títulos, perguntas de confirmação, tooltips e nomes acessíveis podem identificar o recurso. Ações que apenas abrem um cadastro mantêm o contexto, como Cadastrar pessoa. Use os componentes compartilhados e seus rótulos padrão.

## Textos da aplicação

- Não use travessão nem meia-risca nos textos da interface, nomes gerados, mensagens, ajuda ou documentação. Use frases diretas, dois-pontos, vírgulas ou parênteses, conforme o contexto. Para valores ausentes, escreva “Não informado”; para intervalos numéricos, use “a”. Esta regra vale para todos os módulos novos e existentes.

## Auditoria obrigatória

Todo módulo novo deve auditar suas operações de negócio e de acesso. Models persistentes implementam `Auditable`, usam `AuditsChanges` e declaram os campos permitidos em `auditedFields()`. Exclusões precisam de justificativa em `unauditedFields()` ou `config/audit.php`. Senhas, segredos, tokens e imagens nunca entram no histórico, nem técnico. Registre apenas o fato de alteração desses campos.

Actions com várias escritas usam `AuditLog::transaction()`. Alterações em relacionamentos e escritas em lote exigem registro explícito dentro da mesma transação. Preserve autoria real em inspeções e o escopo da conta. Execute `php artisan audit:check` e testes de autoria, escopo, dados protegidos e reversão. O comando também roda na inicialização Docker e deve falhar se um modelo ou campo novo ficar sem cobertura. Leituras, cache, sessões e a própria tabela de auditoria não são operações de negócio auditáveis.

Os testes usam exclusivamente SQLite em memória, imposto por `tests/bootstrap.php`, mesmo quando o processo herda variáveis PostgreSQL do Docker. Nunca remova esse isolamento para rodar a suíte no banco da aplicação.

## Tours por componente

As listagens devem reutilizar `BusinessListTour` e a moldura deve reutilizar `PageHeaderTour`. Passos específicos complementam os componentes, sem duplicar seus textos e comportamentos. Demonstrações de menus, abas e largura de colunas devem restaurar o estado ao sair e preservar preferências e dados. Siga a implementação e as convenções em `docs/convencoes/tour-guiado.md`.

Botões inverse seguem o padrão de cantos quadrados do tema (`--bold-radius`), inclusive em grupos e miniabas. Não aplique arredondamentos locais nesses controles de negócio.

O executor e o botão do tour pertencem à tela, nunca ao modal. Não instancie GuidedTour nem adicione atalhos de tutorial dentro de modais. Quando necessário, o tour da tela deve identificar a ação, abrir o modal, explicar seus controles e fechar somente a janela aberta pela demonstração, sem gravar dados.

A estrela de etiqueta especial aparece somente na listagem de Etiquetas. Pessoas, seletores, formulários e modais exibem a cor e o nome da etiqueta sem estrela.

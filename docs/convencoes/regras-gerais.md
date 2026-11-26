# Regras gerais do Prospera

Regras de código, isolamento, permissões, auditoria e interface que valem para qualquer módulo. Movidas do `AGENTS.md` sem alteração de conteúdo, para que as instruções carregadas em toda sessão fiquem curtas. Leia só a seção da tarefa.

## Padrões do código

- No backend, siga `app/Domains/{Dominio}`. Controllers coletam a entrada e o contexto. `Application/Data` sanitiza, normaliza e valida. Actions executam os casos de uso, Queries cuidam das consultas e DTOs de domínio representam valores estruturados. A validação fica nas classes Data, sem criar FormRequests.
- CPF/CNPJ, tipo de pessoa, nome ou razão social, nome fantasia, inscrições, endereço principal e contatos pertencem a Pessoas. Etiquetas e papéis como Fornecedor não duplicam esses dados; guardam apenas o vínculo e informações realmente específicas do papel. Campos de telefone, WhatsApp e endereço devem reutilizar as máscaras, ícones e grupos de campos do cadastro da conta.
- Exceção aprovada (PD-01): empresas e estabelecimentos têm identificação fiscal própria (razão social, raiz ou CNPJ completo, inscrições), validada por `App\Support\TaxDocument` e com os mesmos componentes de campo. Os dados fiscais da conta (`clients`) são do contratante. Nenhum outro módulo cria colunas fiscais fora de Pessoas.
- Dados centrais da pessoa valem para todas as empresas; dados e contatos que interessam a uma empresa só ficam na relação comercial dela. Nada é sincronizado entre as duas camadas nem entre empresas.
- No frontend, reutilize os componentes Bold já integrados, como `BusinessForm`, `BusinessList`, `BusinessListSearch`, `SelectControl`, `Modal` e `PreviewSection`. Não crie componentes paralelos. Mantenha a composição das telas nas pastas de integração e estilos compartilhados nos tokens de `resources/js/styles/`.
- Preserve o escopo de acesso entre plataforma SaaS e conta. A autorização pertence ao servidor, inclusive quando a interface oculta uma ação.

## Legibilidade do código

Priorize o código que uma pessoa entende ao bater o olho, mesmo com mais linhas, em PHP e TypeScript. Siga [Código do backend](docs/convencoes/codigo-backend.md): linhas de até 120 colunas, uma instrução por linha, blocos separados por linha em branco, Actions sem funções privadas, DTO montado numa variável e entregue ao Model (`register` e `revise`), regras e relacionamentos no Model e nenhuma compatibilidade com estruturas antigas.

## Organização e isolamento

- A árvore é Plataforma SaaS, Conta (tenant, `clients`, `account_id`), Empresas (`company_id`) e Estabelecimentos (`establishment_id`). Toda empresa tem um estabelecimento principal ativo. Código novo nunca trata a conta como estabelecimento; o legado de Minha conta e Pontos é corrigido na E04.
- Todo dado declara sua classe de propriedade (tenant, empresa ou estabelecimento) e carrega as colunas organizacionais dela, com FKs compostas. Operações de conta não recebem empresa nem estabelecimento fictícios.
- Toda leitura passa por Query com o tenant e o nó ou o descritor autorizado, antes de paginar, contar ou agregar. Não use `Model::find` sem escopo nem escopos globais do Eloquent para isolamento. ID ou código de outro escopo responde 404.
- O contexto de empresa e estabelecimento vem da URL e é validado no servidor; a sessão nunca é autoridade de contexto. Siga os nomes, prefixos e identificadores fechados em [Contexto organizacional](docs/convencoes/contexto-organizacional.md).
- Uma conta com uma empresa e um estabelecimento funciona sem seletor nem passo extra.
- Nenhum administrador de conta, empresa ou estabelecimento altera a identidade de login de outra pessoa (foto, e-mail de acesso, senha, segundo fator, situação); reativar um vínculo não reativa a identidade. Respostas sobre e-mail e documento nunca revelam dados de outra conta.

## Performance

- No Octane com FrankenPHP, serviços com estado de conta, contexto, permissões ou auditoria usam binding `scoped`. Não use estado estático mutável nem guarde `Request`, `User` ou `Session` em objetos longevos. Permissões não ficam em cache entre requisições.
- Listagens não têm N+1; filtros, paginação, contagem e agregação acontecem no banco. Cada índice novo é justificado por uma consulta e por plano medido; usar índice não é obrigatório.
- Meça queries, latência, linhas e memória antes e depois de mudanças em consultas críticas, em PostgreSQL isolado e, quando envolver contexto ou autorização, com worker FrankenPHP real. Siga [Performance](docs/convencoes/performance.md).

## Institucional, equipe e sites

- Colaborador é papel de Pessoa por empresa, com etiqueta especial derivada. Não duplica identidade, documento nem contatos centrais e não se confunde com usuário. A atividade profissional chama-se **Função** na interface; **Cargo** é só papel de acesso.
- Dado público é opt-in e vem de projeção com lista de campos permitidos. Rotas públicas não usam sessão nem `OrganizationContext`: o contexto vem do host (`PublicContext`). Editar rascunho nunca altera o que está publicado.
- Antes da S14, nenhuma etapa despacha job nem exige agendador, salvo se a medição da própria etapa reprovar o processamento síncrono; nesse caso a etapa incorpora a fila e entrega o fluxo completo. Siga [Institucional, equipe e sites](docs/convencoes/institucional.md).

## Cargos e permissões

- As permissões são definidas no código e sincronizadas automaticamente. Não crie telas, rotas ou formulários de cadastro de permissões; a seleção acontece no formulário de cadastro e edição de Cargos.
- Todo módulo de negócio deve entrar no catálogo de permissões. Cada ação possui uma permissão própria: listar, visualizar, cadastrar, editar e quaisquer outras operações existentes. Não use uma permissão genérica de gerenciar para liberar todas as ações.
- Siga [Cargos e permissões](docs/convencoes/permissoes.md). Declare as verificações com identificadores literais `account.modulo.acao`, `platform.modulo.acao` ou `self.modulo.acao`. O comando `php artisan permissions:sync` descobre esses usos no backend e nas rotas e sincroniza o catálogo; execute-o ao acrescentar ou mudar ações.
- A autorização avalia a operação sobre o nó do recurso afetado (conta, empresa, estabelecimento ou pessoa), não um nível fixo por slug. Cada permissão declara seus alvos admitidos em `config/permissions.php` (obrigatório a partir da E05). Atribuições de empresa ou estabelecimento nunca alcançam outra empresa, a conta ou outra unidade.
- O Administrador integral da conta recebe automaticamente as permissões `account` e `self`, nunca `platform`. Administradores locais gerenciam só as atribuições do próprio escopo e não alteram cargos, vínculos nem identidades.
- Autorize no servidor cada rota e ação sensível, inclusive operações de acesso dentro de outro formulário. A interface consome `auth.permissions`; o cargo do usuário vem do banco e pertence à conta. Não use `is_account_admin` como atalho de autorização nem liste cargos fixos no formulário.
- Teste a separação entre ações, contas e plataforma, a atribuição de cargos e as tentativas de conceder permissões que o operador não possui.

## Rótulos dos botões

Nos formulários e modais de negócio, a ação de persistir usa somente **Salvar** e a confirmação de exclusão usa somente **Excluir**, sem acrescentar o nome do recurso. Cancelar permanece branco, Salvar verde e Excluir vermelho. Títulos, perguntas de confirmação, tooltips e nomes acessíveis podem identificar o recurso. Ações que apenas abrem um cadastro mantêm o contexto, como Cadastrar pessoa. Use os componentes compartilhados e seus rótulos padrão.

## Textos da aplicação

- Não use travessão nem meia-risca nos textos da interface, nomes gerados, mensagens, ajuda ou documentação. Use frases diretas, dois-pontos, vírgulas ou parênteses, conforme o contexto. Para valores ausentes, escreva “Não informado”; para intervalos numéricos, use “a”. Esta regra vale para todos os módulos novos e existentes.

## Auditoria obrigatória

Todo módulo novo deve auditar suas operações de negócio e de acesso. Models persistentes implementam `Auditable`, usam `AuditsChanges` e declaram os campos permitidos em `auditedFields()`. Exclusões precisam de justificativa em `unauditedFields()` ou `config/audit.php`. Senhas, segredos, tokens e imagens nunca entram no histórico, nem técnico. Registre apenas o fato de alteração desses campos.

A partir da E04, eventos de empresa e de estabelecimento gravam `company_id` e `establishment_id`, e a leitura da auditoria é filtrada no banco pelo escopo autorizado ([Auditoria](docs/convencoes/auditoria.md)). Não crie trilhas paralelas ao `AuditLog`.

Actions com várias escritas usam `AuditLog::transaction()`. Alterações em relacionamentos e escritas em lote exigem registro explícito dentro da mesma transação. Preserve autoria real em inspeções e o escopo da conta. Execute `php artisan audit:check` e testes de autoria, escopo, dados protegidos e reversão. O comando também roda na inicialização Docker e deve falhar se um modelo ou campo novo ficar sem cobertura. Leituras, cache, sessões e a própria tabela de auditoria não são operações de negócio auditáveis.

Os testes usam exclusivamente SQLite em memória, imposto por `tests/bootstrap.php`, mesmo quando o processo herda variáveis PostgreSQL do Docker. Nunca remova esse isolamento para rodar a suíte no banco da aplicação. Medições de performance e testes de concorrência ficam fora da suíte, num ambiente isolado com PostgreSQL próprio ([Performance](docs/convencoes/performance.md)).

## Tours por componente

As listagens devem reutilizar `BusinessListTour` e a moldura deve reutilizar `PageHeaderTour`. Passos específicos complementam os componentes, sem duplicar seus textos e comportamentos. Demonstrações de menus, abas e largura de colunas devem restaurar o estado ao sair e preservar preferências e dados. Siga a implementação e as convenções em `docs/convencoes/tour-guiado.md`.

Botões inverse seguem o padrão de cantos quadrados do tema (`--bold-radius`), inclusive em grupos e miniabas. Não aplique arredondamentos locais nesses controles de negócio.

O executor e o botão do tour pertencem à tela, nunca ao modal. Não instancie GuidedTour nem adicione atalhos de tutorial dentro de modais. Quando necessário, o tour da tela deve identificar a ação, abrir o modal, explicar seus controles e fechar somente a janela aberta pela demonstração, sem gravar dados.

A estrela de etiqueta especial aparece somente na listagem de Etiquetas. Pessoas, seletores, formulários e modais exibem a cor e o nome da etiqueta sem estrela.

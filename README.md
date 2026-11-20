# Prospera

Aplicação Laravel 13 com PHP 8.5, Octane e FrankenPHP. O frontend **Bold**, migrado de `clean-web-design`, usa React, TypeScript e Vite. O Laravel entrega a estrutura HTML inicial e o React consulta e envia os dados da aplicação pela API JSON.

As [orientações do projeto](AGENTS.md) indicam quando documentar ajuda, tours e auditoria e como verificar as telas no navegador.

## Rodar com Docker

```sh
make up
# Equivale a docker compose up --build --wait
```

`make help` lista os atalhos: `make migrate`, `make logs`, `make test`, `make update` (git pull e make up), `make fresh` (apaga o banco local, com confirmação) e outros. A imagem só recompila os assets quando o código muda.

Abra [http://localhost:8000](http://localhost:8000). O Laravel aguarda o PostgreSQL 18 ficar pronto e executa as migrações na inicialização. Os dados do banco ficam no volume `postgres-data`, e a chave da aplicação fica no volume `app-data`.

### Desenvolvimento com Vite

```sh
make dev
# Equivale a docker compose -f compose.yaml -f compose.dev.yaml up --build --wait
```

No `make dev`, o código do host é montado no container e não há rebuild a cada alteração:

- o serviço `vite` roda `npm run dev` em [http://localhost:5174](http://localhost:5174). Alterações em `resources/js` e nos estilos chegam ao navegador na hora, por HMR, e mudanças em Blade ou rotas recarregam a página;
- o Octane roda com `--watch`, e o FrankenPHP reinicia os workers quando muda um arquivo listado em `watch` de `config/octane.php`;
- o `.env` da raiz é usado pelo container, e `vendor` e `node_modules` ficam em volumes próprios, reinstalados quando `composer.lock` ou `package-lock.json` mudam. Os arquivos gerados pertencem ao seu usuário;
- `VITE_PORT` altera a porta do Vite. Em sistemas de arquivos sem eventos (por exemplo, WSL com o código no Windows), use `VITE_USE_POLLING=true`.

`make dev-logs` acompanha o app e o Vite. O `make up` volta à imagem de produção, que roda `npm run build` e ignora o `public/hot` do desenvolvimento.

O banco é acessível pela rede interna do Compose pelo nome `postgres`. Para definir o nome do banco e as credenciais antes da primeira inicialização, use `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no `.env` da raiz. Defina `APP_PORT` para alterar a porta do frontend.

A raiz encaminha para o login ou para o painel correspondente ao usuário. O cadastro de contas é restrito à administração SaaS. Os exemplos do Bold ficam disponíveis às contas administrativas da plataforma em suas URLs `.html`.

## Estrutura do frontend

- `resources/js/app/` contém o layout, o roteamento visual e a integração de conta com o Laravel. Os módulos das telas são carregados sob demanda.
- `resources/js/pages/` organiza as telas do Bold por recurso. Os componentes reutilizáveis ficam em `resources/js/components/`, com seus estilos locais.
- Formulários e modais usam **Salvar** para gravar e **Excluir** para confirmar exclusões, sem acrescentar o nome do recurso. Os componentes de formulário oferecem esses textos como padrão.
- Badges de situação usam `StatusLabel`: Ativa em verde e Inativa em vermelho.
- Badges e etiquetas usam os tokens `--bold-label-*`, medidos na tabela de ordens de serviço da referência Visdom: fonte de 10,5 px e altura aproximada de 20 px em uma linha, inclusive em modais e seletores.
- `resources/js/styles/` mantém os estilos e tokens compartilhados. Imagens, fontes de ícones e plugins do tema ficam em `public/assets/`.
- Telas de empresa e de estabelecimento usam o código na URL (`/conta/estabelecimento/{codigo}`). Na barra superior, a área de trabalho permite selecionar uma ou várias unidades navegáveis e salvar um padrão pessoal por conta. Aplicar a seleção muda apenas a aba atual. Endereços `~/` em `ApiLink`, `ApiForm` e `useContextUrl` são resolvidos no contexto da página. Veja [Contexto organizacional](docs/convencoes/contexto-organizacional.md).
- `npm run check` valida a arquitetura e os tipos. `npm run build` também gera a distribuição do frontend.
- As rotas de tela retornam HTML no acesso direto pelo navegador e dados quando a aplicação solicita `Accept: application/json`. As requisições de escrita usam a sessão e a proteção CSRF do Laravel. Erros de validação retornam HTTP 422 com mensagens por campo.
- O catálogo em `resources/data/municipios-ibge.json` guarda códigos, nomes e UFs do snapshot de novembro de 2026 da API de Localidades do IBGE. O seletor consulta esse arquivo pelo endpoint interno `/api/localidades/municipios`. [Fonte do catálogo](https://servicodados.ibge.gov.br/api/docs/localidades)

No `make up`, use `docker compose up --build --wait` após editar a aplicação para reconstruir e substituir o container. No `make dev`, as alterações são aplicadas sem rebuild.

```sh
docker compose logs -f app
docker compose exec app php artisan test
```

`make down` para o ambiente, inclusive o serviço `vite`, e preserva os volumes.

## Administrar a plataforma SaaS

Crie o primeiro administrador em um terminal interativo. O comando pede o nome, o e-mail e a senha sem exibir a senha. Use um e-mail exclusivo para a conta administrativa.

```sh
make create-user
# Ou execute diretamente
docker compose exec app php artisan auth:create-user --role=platform-admin
```

Entre em [localhost:8000/login](http://localhost:8000/login). No painel **Contas**, use **Cadastrar conta** para informar CPF ou CNPJ, plano, contatos e endereço. Defina uma senha inicial para o administrador da conta ou envie um link para ele escolher a senha. A tabela permite buscar, visualizar e editar contas.

O comando é genérico. Para criar um usuário vinculado a uma conta no terminal, informe `--role=account-admin` ou `--role=account-member` com `--account=ID`. Na plataforma, use **Usuários**; dentro da conta, use **Pessoas** e selecione a etiqueta **Usuário** para conceder acesso.

O administrador da conta entra na mesma tela de login e é encaminhado para **Início**. No menu **Organização**, o administrador pode editar os dados do contratante, cadastrar empresas e gerenciar seus estabelecimentos. Cada usuário possui uma pessoa vinculada, incluindo os usuários que já existiam antes da migração.

### Pessoas e etiquetas da conta

Usuários autorizados da conta encontram **Pessoas** e **Etiquetas** no menu, conforme as permissões de seu cargo. Em Pessoas, cadastre os contatos e selecione etiquetas. Selecionar **Usuário** abre a seção de acesso por convite ou senha inicial. Para uma pessoa existente, use a edição para conceder acesso sem duplicar o cadastro.

O cadastro e a edição de etiquetas abrem em modais na listagem, com situação Ativa ou Inativa. Inativar preserva os vínculos existentes e impede novas seleções. A ação de exclusão usa uma borracha e só permite excluir etiquetas sem vínculos; etiquetas especiais são protegidas. Cada etiqueta mantém sua cor nas listas, visualizações e no seletor de pessoas. Etiquetas pertencem à conta e ao módulo; inicialmente está disponível Pessoas. A etiqueta especial **Usuário** cria acesso. Retirá-la na edição revoga o acesso ao salvar, com a permissão Revogar acesso de Usuários. Consulte [Pessoas](docs/ajuda/pessoas.md) e [Etiquetas](docs/ajuda/etiquetas.md).

### Pessoas e seus papéis

Identificação (tipo de pessoa, nome ou razão social, CPF/CNPJ, nome fantasia e inscrições), endereço principal e contatos pertencem a Pessoas e são preenchidos uma única vez, mesmo quando a pessoa recebe várias etiquetas. Telefone e WhatsApp reutilizam máscaras e ícones do formulário da conta. Pessoas também permite até 20 telefones e 20 e-mails adicionais pelos mesmos botões de adicionar e remover da conta, sem alterar o e-mail principal de acesso. Documento é opcional, validado quando informado e único por conta; CNPJ alfanumérico é aceito.

A etiqueta especial **Fornecedor** identifica um papel da mesma pessoa e não repete os campos comuns. O filtro Fornecedores mostra os ativos. Retirar a etiqueta e salvar inativa o vínculo, preservando pessoa, dados e histórico. Não é criado acesso de usuário. Cadastrar, visualizar, listar, ativar e inativar fornecedores têm permissões próprias; os dados comuns são editados pela permissão de Pessoas. Consulte [Fornecedores](docs/ajuda/fornecedores.md) e o [levantamento dos campos](docs/pesquisas/cadastro-fornecedores.md).

A etiqueta especial **Cliente** também representa um papel da pessoa, podendo coexistir com Fornecedor e Usuário. O filtro Clientes mostra os vínculos ativos. Retirar a etiqueta e salvar inativa o vínculo; selecionar novamente reativa o mesmo registro. Não repete identidade nem contatos e não cria acesso. Clientes têm permissões próprias de cadastrar, visualizar, listar, ativar e inativar. Consulte [Clientes](docs/ajuda/clientes.md).

### Recuperação de senha

No Docker local, os links ficam na caixa [Mailpit](http://localhost:8025). Para entregar e-mail externo, configure no `.env` um SMTP válido com `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD` e `MAIL_FROM_ADDRESS`. Recrie o serviço `app` após a alteração.

### Banco em arquivo

`make prepare-sqlite` cria e migra um banco independente em `storage/app/prospera.sqlite`. No Docker o arquivo fica no volume `app-data`. O painel SaaS usa o PostgreSQL. Para executar um comando Artisan nesse arquivo separado, use `docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=/app/storage/app/prospera.sqlite app php artisan <comando>`.

## Padrão de entrada e domínio

O código de negócio se organiza em `app/Domains/{Dominio}`.

- A camada `UI` coleta o payload HTTP e o contexto autenticado.
- As classes `Application/Data` normalizam valores, aplicam as regras e retornam dados validados.
- A `Action` coordena o caso de uso e a persistência. Para operações com vários valores de domínio, ela monta um DTO imutável em `Domain/DTOs` e entrega ao model.
- As consultas de listagem ficam em `Application/Queries` e recebem os filtros normalizados.

O cadastro de estabelecimentos é uma referência. O controller cria `SaveEstablishmentData`, `SaveEstablishmentAction` monta `EstablishmentDTO` e o model `Establishment` aplica o estado.

## Cargos e catálogo de permissões

O acesso da conta é definido por cargos persistidos e permissões por ação. O item **Cargos** aparece conforme o acesso; as permissões são selecionadas em seu formulário, sem tela ou cadastro próprio; em Pessoas, **Papel na conta** busca os cargos cadastrados. As migrações preservam os usuários existentes, atribuindo os cargos iniciais conforme o acesso anterior.

```sh
docker compose exec app php artisan permissions:sync
```

O comando descobre as verificações no PHP e sincroniza o catálogo preservando os vínculos dos cargos. As permissões são definidas pelos módulos no código; não existe cadastro manual de permissões. Também roda na inicialização Docker. Todo módulo novo deve declarar e verificar as permissões específicas de suas ações; veja a [convenção de permissões](docs/convencoes/permissoes.md) e a [ajuda de cargos](docs/ajuda/cargos-permissoes.md).

## Auditoria, ajuda e tutorial

**Auditoria** aparece no menu da plataforma e da conta conforme a permissão de listar. A consulta tem filtros por autor, tipo, ação e período; na plataforma há filtro por conta. O detalhe mostra os valores anteriores e novos. A permissão de detalhes técnicos é independente. O servidor limita todos os dados e opções à conta autorizada.

Toda alteração de negócio é auditada pelo contrato `Auditable` e pelo observer compartilhado. Relacionamentos e eventos de acesso são registrados explicitamente. Operações revertidas não geram histórico concluído. Dados protegidos são substituídos por marcadores, sem senhas, segredos ou imagens. `docker compose exec app php artisan audit:check` verifica a cobertura e também roda na inicialização. Consulte a [convenção de auditoria](docs/convencoes/auditoria.md).

A **Central de Ajuda** está no menu e usa os artigos de `docs/ajuda/`, filtrados no servidor por contexto e permissão. O botão **Tutorial desta tela**, com ícone de informação na barra superior, inicia os passos da página, inclusive no celular. O tour respeita os controles disponíveis, não modifica os dados digitados e pode ser fechado com Esc. As configurações estão em `resources/js/app/guidance.ts` e seus roteiros em `docs/tours/`.

A suíte impõe SQLite em memória em `tests/bootstrap.php`, antes de carregar o Laravel, para impedir que as variáveis do Docker direcionem os testes ao PostgreSQL da aplicação.

Após uma atualização, a aplicação recupera uma falha de carregamento dos arquivos de tela com uma única navegação GET, sem reenviar formulários. O HTML não fica armazenado em cache. Falhas persistentes oferecem Recarregar tela; a tentativa automática é limitada para evitar loops. Consulte a [verificação de recuperação](docs/convencoes/recuperacao-assets-etiquetas-entrega.md).

O preloader aparece somente quando uma troca de tela ultrapassa 300 ms e sai assim que o conteúdo fica pronto, sem duração mínima de exibição. Navegações rápidas e atualizações de filtros ou paginação na mesma tela não mostram a sobreposição. O indicador usa o favicon Prospera com oito pontos verdes e azuis sobre um desfoque discreto, respeitando a preferência por movimento reduzido. Os dados da API continuam sendo consultados no servidor a cada navegação.

O modo normal está ativo com `appLoaderPreviewEnabled = false` em `resources/js/utils/appPageTransition.ts`. Para conferir a animação temporariamente, altere a chave para `true` e execute `make up`: toda navegação mostrará o indicador imediatamente por pelo menos 500 ms, além da transição de saída. Retorne a chave para `false` e execute `make up` para restaurar o modo normal.

A listagem da Central de Ajuda segue a busca do Ubold e do GNAI: campo centralizado, abas com contadores por categoria e resultados em lista com título, caminho e resumo. SearchInput, SearchResults, SearchResultItem, Tabs e Badge são reutilizados; categorias e contagens usam somente os artigos autorizados retornados pelo servidor.

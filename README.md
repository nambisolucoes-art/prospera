# Prospera

Aplicação Laravel 13 com PHP 8.5, Octane e FrankenPHP. O frontend **Bold**, migrado de `clean-web-design`, usa React, TypeScript e Vite. O Laravel entrega a estrutura HTML inicial e o React consulta e envia os dados da aplicação pela API JSON.

As [orientações do projeto](AGENTS.md) indicam quando documentar ajuda, tours e auditoria e como verificar as telas no navegador.

## Rodar com Docker

```sh
docker compose up --build --wait
```

Abra [http://localhost:8000](http://localhost:8000). O Laravel aguarda o PostgreSQL 18 ficar pronto e executa as migrações na inicialização. Os dados do banco ficam no volume `postgres-data`, e a chave da aplicação fica no volume `app-data`.

O banco é acessível pela rede interna do Compose pelo nome `postgres`. Para definir o nome do banco e as credenciais antes da primeira inicialização, use `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no `.env` da raiz. Defina `APP_PORT` para alterar a porta do frontend.

A raiz encaminha para o login ou para o painel correspondente ao usuário. O cadastro de contas é restrito à administração SaaS. Os exemplos do Bold ficam disponíveis às contas administrativas da plataforma em suas URLs `.html`.

## Estrutura do frontend

- `resources/js/app/` contém o layout, o roteamento visual e a integração de conta com o Laravel. Os módulos das telas são carregados sob demanda.
- `resources/js/pages/` organiza as telas do Bold por recurso. Os componentes reutilizáveis ficam em `resources/js/components/`, com seus estilos locais.
- `resources/js/styles/` mantém os estilos e tokens compartilhados. Imagens, fontes de ícones e plugins do tema ficam em `public/assets/`.
- `npm run check` valida a arquitetura e os tipos. `npm run build` também gera a distribuição do frontend.
- As rotas de tela retornam HTML no acesso direto pelo navegador e dados quando a aplicação solicita `Accept: application/json`. As requisições de escrita usam a sessão e a proteção CSRF do Laravel. Erros de validação retornam HTTP 422 com mensagens por campo.
- O catálogo em `resources/data/municipios-ibge.json` guarda códigos, nomes e UFs do snapshot de novembro de 2026 da API de Localidades do IBGE. O seletor consulta esse arquivo pelo endpoint interno `/api/localidades/municipios`. [Fonte do catálogo](https://servicodados.ibge.gov.br/api/docs/localidades)

Após editar a aplicação, use `docker compose up --build --wait` para reconstruir e substituir o container.

```sh
docker compose logs -f app
docker compose exec -e APP_ENV=testing -e DB_CONNECTION=sqlite app php artisan test
```

`docker compose down` para o ambiente e preserva os volumes.

## Administrar a plataforma SaaS

Crie o primeiro administrador em um terminal interativo. O comando pede o nome, o e-mail e a senha sem exibir a senha. Use um e-mail exclusivo para a conta administrativa.

```sh
make create-user
# Ou execute diretamente
docker compose exec app php artisan auth:create-user --role=platform-admin
```

Entre em [localhost:8000/login](http://localhost:8000/login). No painel **Contas**, use **Cadastrar conta** para informar CPF ou CNPJ, plano, contatos e endereço. Defina uma senha inicial para o administrador da conta ou envie um link para ele escolher a senha. A tabela permite buscar, visualizar e editar contas.

O comando é genérico. Para criar um usuário vinculado a uma conta no terminal, informe `--role=account-admin` ou `--role=account-member` com `--account=ID`. Nas telas, o cadastro **Usuários** assume o contexto da plataforma ou da conta autenticada.

O administrador da conta entra na mesma tela de login e é encaminhado para **Minha conta**. A própria conta representa o estabelecimento. Nesse painel, o administrador pode editar os dados do estabelecimento e gerenciar seus pontos. Cada usuário possui uma pessoa vinculada, incluindo os usuários que já existiam antes da migração.

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

O cadastro de pontos é uma referência curta. O controller cria `SavePointData`, `SavePointAction` monta `PointConfigurationDTO` e o model `Point` aplica o estado.

# Integração contínua (GitHub Actions)

O workflow `.github/workflows/ci.yml` roda as verificações essenciais em todo Pull Request para a `main`, em todo push na `main` e sob demanda (`workflow_dispatch`, aba Actions). Não é uma etapa funcional do plano 002.

## Jobs (checks separados no PR)

| Check | O que roda | Equivale localmente a |
| --- | --- | --- |
| **Backend / PHP** | `composer install` pelo `composer.lock`, `npm ci` e `vite build` (as views exigem o manifesto), Pint, PHPStan, `php artisan test` (SQLite em memória) | `composer test` (mesmas três etapas, separadas para mostrar qual falhou) |
| **Frontend / TypeScript e build** | `npm ci`, `npm run check`, `npm run test:frontend`, `vite build` | `npm run check && npm run test:frontend && npm run build` |
| **Banco / PostgreSQL 18 e verificações** | migrations em banco limpo, `permissions:sync`, `audit:check`, `organization:check`, `institutional:check`, rollback da última migration e nova aplicação, nenhuma migration pendente | os mesmos comandos num PostgreSQL 18 descartável |

O rollback completo não é possível por projeto: `2026_11_20_000008_remove_single_role_from_users` é irreversível e exige cópia anterior. Os três jobs rodam em paralelo. O frontend roda `vite build` direto porque `npm run build` repetiria o `npm run check` da etapa anterior.

## Ambiente

- **PHP:** estágio `dev` do `Dockerfile` (FrankenPHP, PHP 8.5, `gd`, `pdo_sqlite`, `pdo_pgsql`), com Composer 2 e Node 24. O Dockerfile de produção não foi alterado. A imagem usa cache `type=gha`.
- **Execução:** `.github/ci/php.sh <comando>` roda o comando na imagem, com o usuário do runner e só variáveis descartáveis: `APP_KEY` gerada na hora, `MAIL_MAILER=array` e, no job de banco, o PostgreSQL do próprio runner. Nenhum `.env` entra.
- **Node:** 24, com cache do npm pelo `package-lock.json`. O cache do Composer usa o `composer.lock`.
- **Banco da suíte PHP:** SQLite em memória (`tests/bootstrap.php`). O PostgreSQL 18 do job de banco é um serviço do runner (porta só no localhost do runner), destruído ao fim do job.

## Segurança

- `permissions: contents: read`. O workflow não escreve no repositório, não cria commit, não faz push, não faz merge e não faz deploy.
- Sem `pull_request_target`, sem secrets e sem acesso a recurso de produção. A senha `ci` do PostgreSQL é descartável e só existe no runner.
- Actions fixadas por SHA de commit (versão no comentário). Para atualizar, troque o SHA e o comentário juntos.
- `persist-credentials: false` no checkout. Dependências só pelos lockfiles.
- Sem `continue-on-error`: qualquer etapa vermelha reprova o job. Os jobs de PHP e frontend ainda confirmam, ao final, que a execução não alterou arquivos versionados (`git diff --exit-code`).
- `concurrency`: um commit novo no mesmo PR cancela a execução anterior desse PR. A `main` nunca é cancelada.

## Como ler uma falha

Abra o PR, aba **Checks** (ou o ✗ ao lado do commit), escolha o job vermelho e abra a etapa em vermelho. O nome da etapa indica o comando (por exemplo "PHPStan (composer types:check)"), o log traz a mensagem e, nos testes, o nome do teste. O commit testado aparece no topo da execução. Para repetir localmente, use a coluna "Equivale localmente a".

## Reproduzir localmente

```bash
composer test                       # Pint, PHPStan e testes PHP (use o contêiner `app`, que tem SQLite)
npm run check && npm run test:frontend && npm run build
# Banco: num PostgreSQL descartável, nunca no banco da aplicação
php artisan migrate --force && php artisan permissions:sync && php artisan audit:check \
  && php artisan organization:check && php artisan institutional:check
```

## Adicionar um check

1. Se o comando é de PHP, acrescente uma etapa chamando `.github/ci/php.sh <comando>` no job que faz sentido. Se precisa de banco, vai no job de banco.
2. Se é novo job, repita `permissions` mínimas (herdadas), `timeout-minutes` e o `name` descritivo, que vira o nome do check exigível.
3. Atualize a tabela acima.

## O que continua só no laboratório

Concorrência real com processos PHP independentes, FrankenPHP com um e dois workers, medições de latência e memória e as jornadas no Chromium (larguras 1440 a 320 px). O CI **não** os cobre e não substitui as provas de cada etapa. Os testes do Chromium (`tests/Browser`) exigem laboratório com dados sintéticos e não rodam no CI.

## Tornar os checks obrigatórios na `main`

Criar o workflow **não impede** o merge de um PR com teste falhando: isso depende das regras do repositório. Em **Settings > Branches > Branch protection rules** (ou **Rulesets**) para `main`:

1. Marque **Require a pull request before merging**.
2. Marque **Require status checks to pass before merging** e **Require branches to be up to date before merging**.
3. Selecione os checks `Backend / PHP`, `Frontend / TypeScript e build` e `Banco / PostgreSQL 18 e verificações` (aparecem depois da primeira execução).
4. Opcional: **Do not allow bypassing the above settings** e bloqueio de force-push.

Esta configuração é do administrador do repositório e não foi alterada automaticamente.

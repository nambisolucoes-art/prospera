# Cargos e permissões

Todos os módulos registram cada ação no catálogo e verificam a autorização no servidor. Não há uma permissão genérica de gerenciar que substitua as ações.

## Interface

A conta possui o item **Cargos** no menu. As permissões aparecem para seleção no formulário de cadastro e edição de cargos, agrupadas por módulo. O modal de um cargo mostra somente as permissões já atribuídas. Não existe tela, rota, menu, formulário ou cadastro manual de permissões.

## Descoberta e sincronização

`PermissionRegistry` descobre verificações de autorização com identificadores literais no PHP de `app/` e `routes/`, como `account.people.create` e middleware `can:account.people.create`. Os escopos são `account` (negócio da conta), `platform` (administração SaaS) e `self` (operações sobre o próprio perfil e segurança). Declare novas operações com identificadores literais, sem concatenação. Os nomes em português vêm de `config/permissions.php`, com fallback para módulos e ações novos e dois-pontos entre ação e módulo.

Execute `docker compose exec app php artisan permissions:sync` após alterar ações. A inicialização Docker executa esse comando após as migrações. A sincronização é idempotente e preserva vínculos dos cargos. A opção `--prune` remove definições do sistema que saíram do código. A descoberta ocorre no comando; a autorização consulta o banco sem cache global de usuário no Octane.

Use middleware da própria ação em cada rota. Ações compostas também usam `Gate::authorize`: salvar uma pessoa não basta para cadastrar acesso, editar dados de usuário ou atribuir cargo. Listagem e visualização são independentes. Autenticação, recuperação de acesso e encerramento de inspeção preservam seus mecanismos próprios.

## Cargos e escopo

Cargos pertencem a uma conta. Cada usuário da conta recebe um cargo persistido. Os cargos iniciais são Administrador da conta, com as permissões de conta e pessoais do catálogo, e Usuário, com Minha conta, listagem de pontos e operações pessoais. O administrador inicial é protegido; os demais cargos podem ser personalizados.

`is_account_admin` permanece apenas para compatibilidade e provisionamento. A autorização usa cargo e vínculos com definições do sistema. Cargos da conta nunca recebem permissões da plataforma ou de outra conta. Registros legados de permissões manuais não são exibidos, atribuídos nem considerados na autorização; seu histórico permanece preservado.

Atribuir cargo exige `account.users.assign-role`. Só é possível atribuir cargos ou conceder permissões que o operador possui. O último administrador não pode receber um cargo diferente. A inspeção somente leitura bloqueia todas as gravações.

## Auditoria

`directory_audit_events` registra `role.created`, `role.updated` e `user.role-updated`, com autor, conta, entidade, UUID e valores autorizados antes/depois na mesma transação. Alterações sem mudanças não geram evento. `access_catalog_events` registra sincronizações globais e a retirada das antigas permissões de manutenção do catálogo, com autoria de sistema, escopo plataforma e contas afetadas. A consulta central continua pendente.

## Verificação

Cubra descoberta e idempotência, seleção por ação no cargo, inexistência de endpoints de manutenção de permissões, isolamento de conta, rejeição de permissões SaaS, escalada de privilégios, troca de cargo, último administrador, auditoria e inspeção somente leitura. Confira cadastro, edição e visualização de Cargos no desktop e mobile.

O modal compartilha `PreviewSection` com Contas. A regra de não usar travessão nos textos da aplicação está no AGENTS.md. Ajuda e tour de Cargos estão versionados; a integração da Central de Ajuda, do executor dos tours e da consulta central de auditoria permanece pendente.

## Validação da retirada da tela de permissões

72 testes e 480 asserções aprovados, incluindo inexistência dos antigos endpoints, seleção de permissões do catálogo no cargo, rejeição de registros manuais legados e migração das antigas autorizações da tela retirada. PHPStan, Pint, arquitetura, TypeScript e build aprovados.

Chrome em 1440, 390 e 320 px: menu sem Permissões, cadastro de cargo com ações por módulo, gravação e reabertura das seleções, edição de ações atribuídas e modal do cargo. Menus mobile abertos em 390 e 320 px, sem item de Permissões. Controles do formulário visíveis ao rolar e sem rolagem horizontal indevida. Capturas temporárias em `/tmp/inline-*.png`. Ajuda e tour atualizados; o executor continua pendente.

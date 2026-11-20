# Cargos e permissões

Todos os módulos registram cada ação no catálogo e verificam a autorização no servidor. Não há uma permissão genérica de gerenciar que substitua as ações.

O RBAC atual é preservado e evolui para autorização contextual nas etapas E03 a E05 do [plano 001](../plano-de-acoes/001-arquitetura-organizacional-prospera.md). As seções marcadas com a etapa descrevem o contrato que a etapa implanta; até lá, vale o comportamento descrito em "Modelo atual". Contexto geral em [Contexto organizacional](contexto-organizacional.md).

## Interface

A conta possui o item **Cargos** no menu. As permissões aparecem para seleção no formulário de cadastro e edição de cargos, agrupadas por módulo. O modal de um cargo mostra somente as permissões já atribuídas. Não existe tela, rota, menu, formulário ou cadastro manual de permissões. O resumo de acesso, somente leitura, fica em Usuários e Cargos (PD-06).

## Catálogo, descoberta e sincronização

`PermissionRegistry` descobre verificações de autorização com identificadores literais no PHP de `app/` e `routes/`, como `account.people.create` e middleware `can:account.people.create`. Os escopos do catálogo são `account` (negócio da conta), `platform` (administração SaaS) e `self` (operações sobre o próprio perfil e segurança). Declare novas operações com identificadores literais, sem concatenação. Os nomes em português vêm de `config/permissions.php`, com fallback para módulos e ações novos e dois-pontos entre ação e módulo.

Execute `docker compose exec app php artisan permissions:sync` após alterar ações. A inicialização Docker executa esse comando após as migrações. A sincronização é idempotente e preserva vínculos dos cargos. A opção `--prune` remove definições do sistema que saíram do código. A descoberta ocorre no comando; a autorização consulta o banco sem cache global de usuário no Octane.

Use middleware da própria ação em cada rota. Ações compostas também usam `Gate::authorize`: salvar uma pessoa não basta para cadastrar acesso, editar dados de usuário ou atribuir cargo. Listagem e visualização são independentes. Autenticação, recuperação de acesso e encerramento de inspeção preservam seus mecanismos próprios.

O escopo do catálogo (`account`, `platform`, `self`) diz a quem a permissão pode ser concedida. Ele não é o nível organizacional da checagem: esse é dado pelo recurso-alvo (E05). Não volte ao modelo de um nível fixo por slug.

## Modelo atual

Cargos pertencem a uma conta. Cada usuário da conta recebe um cargo persistido (`users.role_id`), de alcance da conta inteira. Os cargos iniciais são Administrador da conta, com as permissões de conta e pessoais do catálogo, e Usuário, com Minha conta, listagem de pontos e operações pessoais. O administrador inicial é protegido; os demais cargos podem ser personalizados.

`is_account_admin` permanece apenas para compatibilidade e provisionamento. A autorização usa cargo e vínculos com definições do sistema. Cargos da conta nunca recebem permissões da plataforma ou de outra conta. Registros legados de permissões manuais não são exibidos, atribuídos nem considerados na autorização; seu histórico permanece preservado.

Atribuir cargo exige `account.users.assign-role`. Só é possível atribuir cargos ou conceder permissões que o operador possui. O último administrador não pode receber um cargo diferente. A inspeção somente leitura bloqueia todas as gravações.

## Modelo contextual

### Cargos, disponibilidade e atribuições (E05)

- **Cargo:** conjunto de permissões, catálogo do tenant. Cargos só são criados e editados no escopo tenant (`account.roles.create` e `account.roles.update` com alvo `tenant`).
- **Disponibilidade (Abrangência):** onde o cargo pode ser atribuído: toda a conta (padrão, inclui empresas e estabelecimentos futuros) ou restrita a empresas e estabelecimentos (`role_availability`). Disponibilidade nunca concede acesso.
- **Atribuição:** cargo concedido a um vínculo num escopo (`role_assignments`). Um vínculo pode ter várias atribuições, com cargos e escopos diferentes. A atribuição só é válida se o escopo estiver coberto pela disponibilidade do cargo.
- Ao restringir a disponibilidade, as atribuições que ficarem descobertas são listadas e removidas na mesma operação, com confirmação. Nunca ficam atribuições inválidas.

### Escopos e herança

| Escopo da atribuição | Alcance |
| --- | --- |
| Tenant | A conta, todas as empresas e todos os estabelecimentos, atuais e futuros |
| Empresa | A empresa e todos os seus estabelecimentos, atuais e futuros |
| Estabelecimento | Somente aquela unidade |

A herança é só descendente: uma atribuição de estabelecimento nunca alcança a empresa, a conta ou outra unidade; uma de empresa nunca alcança outra empresa.

### Recurso-alvo (E05)

Toda autorização avalia uma operação sobre um recurso. Cada recurso tem um nó: `tenant`, `company:{id}`, `establishment:{id}` ou, para Pessoas, o conjunto das empresas com as quais a pessoa tem relação.

Cada permissão declara em `config/permissions.php` os alvos admitidos (`targets`: `tenant`, `company`, `establishment`, `person`). O módulo pode declarar um padrão, e a ação pode sobrescrevê-lo. Os slugs não mudam. A sincronização falha se um slug descoberto não tiver alvos declarados.

A atribuição A concede a permissão P sobre o nó N se, e somente se:

1. P pertence ao cargo de A;
2. o vínculo e a conta estão ativos;
3. o tipo de N está entre os alvos admitidos de P;
4. o escopo de A é ancestral ou igual a N.

Para o alvo `person`, a regra depende da classe do dado ([Pessoas e contatos](pessoas-e-contatos.md)): contatos e endereço exigem cobrir **uma** empresa relacionada; identificação exige cobrir **todas**. Cobrir a empresa C significa ter atribuição de tenant, da empresa C ou de qualquer estabelecimento de C.

A classificação de cada permissão atual e prevista está na seção 8.2.1 do plano. Os módulos novos seguem o [checklist de módulos](checklist-modulos.md).

### Administradores

| Tipo | Como existe | Alcance |
| --- | --- | --- |
| Administrador SaaS | `users.is_saas_admin` e permissões `platform.*` | Plataforma, contas e inspeção auditada. Nenhuma permissão `account.*`; dados de conta só em inspeção, com a autoridade do vínculo representado |
| Administrador integral da conta | Cargo de sistema Administrador, disponibilidade e atribuição de escopo tenant | Recebe automaticamente todas as permissões `account` e `self` do catálogo a cada sincronização, nunca `platform` |
| Administrador de empresa | Cargo personalizado atribuído no escopo da empresa | A empresa e as unidades dela: edição, unidades, usuários novos com a primeira atribuição na empresa, atribuições locais e auditoria da empresa |
| Administrador de estabelecimento | Cargo personalizado atribuído no escopo da unidade | Dados da própria unidade, usuários novos com a primeira atribuição nela, atribuições locais e auditoria da unidade |

Administradores de empresa e de estabelecimento não criam nem alteram cargos, não criam nem inativam empresas, não veem eventos de tenant ou de outro escopo e não alteram a identidade global nem o vínculo com a conta. Administração local nunca concede acesso a outras empresas.

### Prevenção de escalada (E05)

1. **Permissões do cargo:** conceder permissões a um cargo, ou alterar sua disponibilidade, exige `account.roles.update` sobre o tenant e que o operador possua cada permissão concedida por atribuição de escopo tenant.
2. **Atribuir ou retirar cargo no nó N:** o operador precisa ter `account.users.assign-role` sobre N, o cargo precisa estar disponível em N, e o operador precisa ter cada permissão do cargo por atribuições que cubram N.
3. **Outras atribuições da pessoa** não entram na decisão. O administrador da Empresa A gerencia a atribuição de Ana na Empresa A mesmo que Ana trabalhe na Empresa B, sem ver nem alterar a de lá.
4. **Busca horizontal:** todo identificador recebido (empresa, estabelecimento, cargo, vínculo, pessoa) é buscado no tenant do contexto.
5. **Último administrador:** precisa existir pelo menos um vínculo ativo com o cargo Administrador em escopo tenant.
6. **Inspeção:** o administrador SaaS em inspeção tem exatamente a autoridade do vínculo representado.
7. **Interface não é barreira:** ocultar um controle não substitui a verificação no servidor.

### Revogação

- Permissões são resolvidas por requisição, sem cache persistente nem sessão. Retirar uma atribuição, alterar um cargo, suspender um vínculo ou inativar a conta vale na requisição seguinte.
- Retirar uma atribuição exige as mesmas condições de concedê-la sobre o nó dela. Ninguém retira atribuição de fora do próprio escopo ou com permissões maiores que as próprias naquele nó.
- Retirar a última atribuição local não suspende o vínculo. Suspender o vínculo é de escopo tenant ([Identidade e vínculos](identidade-e-vinculos.md)).

### Administração de atribuições locais

- A tela de acesso da pessoa mostra e edita só as atribuições dentro do escopo do operador. Atribuições de outros escopos não são listadas, porque revelariam onde a pessoa trabalha. Apenas operadores de escopo tenant veem todas.
- As opções de cargo e de "Onde" são filtradas pela disponibilidade do cargo e pela autoridade do operador.
- `account.users.create` com escopo local cria uma identidade nova, o vínculo e a primeira atribuição dentro do escopo do operador. Não altera identidade nem vínculo existentes.
- A auditoria local só pode ser atribuída fora do escopo tenant quando a consulta contextual de auditoria estiver ativa (seção 11.1.1 do plano).

### Resolução e custo (E03 e E05)

`EffectivePermissions` (`scoped`) carrega as permissões na primeira checagem da requisição, em no máximo 2 queries, e memoriza cada resultado. Detalhes em [Performance](performance.md).

## Nomes fechados na E02

| Permissão | Situação |
| --- | --- |
| `account.account-data.update` | Substitui `account.establishment.update` na E04 (Dados da conta), com migração do pivô |
| `account.companies.list/view/create/update/activate/deactivate` | E04 |
| `account.establishments.list/view/create/update/activate/deactivate/promote` | E04; `promote` promove o estabelecimento principal |
| `account.users.reactivate`, `account.users.send-password-reset`, `account.users.invite` | E05 ([Identidade e vínculos](identidade-e-vinculos.md)) |
| `account.points.*` | Removidas na E04 (PD-05) |

## Auditoria

Cargos e atribuições são auditados apenas em `audit_records`: o cargo pelo próprio Model, as permissões do cargo por `recordAuditFieldChange('permission_ids', ...)` e a troca de cargo do usuário como alteração de `role_id`. Alterações sem mudanças não geram evento. `access_catalog_events` registra sincronizações globais e a retirada das antigas permissões de manutenção do catálogo, com autoria de sistema, escopo plataforma e contas afetadas. Ela é consolidada em `audit_records` na E08. Atribuições gravam a empresa e o estabelecimento do escopo ([Auditoria](auditoria.md)).

## Verificação

Cubra descoberta e idempotência, seleção por ação no cargo, inexistência de endpoints de manutenção de permissões, isolamento de conta, rejeição de permissões SaaS, escalada de privilégios, troca de cargo, último administrador, auditoria e inspeção somente leitura. A partir da E05, cubra também alvos admitidos, herança, atribuições em vários escopos, administração local legítima e negada, revogação e atribuição de cargo indisponível. Confira cadastro, edição e visualização de Cargos no desktop e mobile.

O modal compartilha `PreviewSection` com Contas. A regra de não usar travessão nos textos da aplicação está no AGENTS.md. Ajuda e tour de Cargos estão versionados; a integração da Central de Ajuda, do executor dos tours e da consulta central de auditoria permanece pendente.

## Validação da retirada da tela de permissões

72 testes e 480 asserções aprovados, incluindo inexistência dos antigos endpoints, seleção de permissões do catálogo no cargo, rejeição de registros manuais legados e migração das antigas autorizações da tela retirada. PHPStan, Pint, arquitetura, TypeScript e build aprovados.

Chrome em 1440, 390 e 320 px: menu sem Permissões, cadastro de cargo com ações por módulo, gravação e reabertura das seleções, edição de ações atribuídas e modal do cargo. Menus mobile abertos em 390 e 320 px, sem item de Permissões. Controles do formulário visíveis ao rolar e sem rolagem horizontal indevida. Capturas temporárias em `/tmp/inline-*.png`. Ajuda e tour atualizados; o executor continua pendente.

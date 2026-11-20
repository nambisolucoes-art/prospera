# Auditoria

A auditoria deve permitir que um usuário autorizado descubra quem fez uma mudança de negócio, quando, em qual conta e o que mudou.

## Registro de operações

- Ao criar ou alterar uma Action de escrita, identifique as mudanças que precisam de histórico. Comece por cadastro e edição de contas, ativação e inativação, alterações de plano, cadastro de usuários, mudanças de papel e dados da conta.
- Registre autor, data, evento, identificador da operação, tipo e identificador do registro e, quando aplicável, a conta afetada. Diferencie uma operação da plataforma que afeta uma conta de uma operação executada dentro dela. Registre operações automáticas com autoria de sistema.
- A atualização da foto do perfil deve registrar o próprio usuário como autor, com o contexto de plataforma ou conta da sessão. A imagem fica fora do histórico.
- Na inspeção de contas, o log da aplicação registra início, encerramento e requisições de escrita em modo de edição, com os identificadores do administrador SaaS, da conta e do usuário representado. O histórico central preserva o administrador SaaS como autor e o usuário representado separadamente.
- Em mudanças de campos, armazene os valores anteriores e novos apenas para campos autorizados. Preserve uma identificação compreensível do registro para consulta histórica. Senhas, hashes, tokens, segredos e dados pessoais desnecessários devem ficar fora do histórico, inclusive da visão técnica.
- Grave o histórico na mesma transação da mudança de negócio. Uma operação revertida não deve aparecer como concluída. Agrupe mudanças do mesmo registro na mesma operação e descarte atualizações sem alteração efetiva.
- Se for usado um observer do Eloquent, a Action deve registrar explicitamente as escritas que não disparam seus eventos, como atualizações em lote e alterações diretas em tabelas de relacionamento. No Octane, o contexto de auditoria deve durar apenas a requisição ou a operação que o abriu.

## Consulta e verificação

- Mantenha a consulta centralizada, paginada e somente para leitura. Use filtros por período, conta quando autorizada, autor, entidade e evento. Mostre o resumo em português e os valores antes e depois no detalhe. A visão técnica deve obedecer à mesma autorização e às mesmas regras de ocultação.
- Aplique o escopo no servidor em listagens, detalhes e filtros. Um usuário de uma conta só pode consultar os eventos daquela conta que seu perfil permite. Operações da plataforma exigem autorização própria.
- Use os testes das operações auditáveis para conferir autoria, conta, valores registrados e reversão por transação. Ao implementar a consulta, verifique também a tentativa de acessar o registro de outra conta e a visualização do detalhe no mobile.

Referências consultadas no Controle Bar em `docs/specs/0003-auditoria/` e no GenAI em `Docs/audit.md`. A prioridade acima segue os fluxos atuais do Prospera.

A implementação usa `Auditable`, `AuditsChanges`, `AuditObserver` e `AuditLog::transaction()`. Declare campos permitidos e exclusões justificadas. Para pivôs, use `AuditLog::relationship()` dentro da transação da Action. A consulta `/conta/auditoria` ou `/saas/auditoria` é somente leitura e separa listar, visualizar e detalhes técnicos. `audit:check` verifica todos os modelos de domínio e as colunas persistidas; é obrigatório também para módulos novos.

## Contexto organizacional

O mecanismo central (`audit_records`, `Auditable`, `AuditsChanges`, `AuditLog`, `AuditContext`) é preservado. Ele é a única trilha: `DirectoryAudit` foi removido e `directory_audit_events` não recebe mais gravações. Mudanças de relacionamento são registradas pelo próprio Model com `recordAuditFieldChange()`. `access_catalog_events` ainda recebe a sincronização do catálogo e é consolidada na E08. Estrutura organizacional em [Contexto organizacional](contexto-organizacional.md).

### Gravação (E04)

- `audit_records` recebe `company_id` e `establishment_id`, ambos opcionais, na E04.
- `Auditable` ganha `auditCompanyId()` e `auditEstablishmentId()`, com implementação padrão em `AuditsChanges` lendo os atributos homônimos, como `auditAccountId()`. `AuditLog::record` e `AuditLog::relationship` gravam esses valores.
- A partir da E04, `audit:check` exige que todo model com `company_id` ou `establishment_id` informe esses ids.
- O histórico anterior à E04 fica sem empresa e sem unidade, como evento de tenant, sem preenchimento retroativo por suposição.

### Níveis de evento

| Nível | Exemplos | `account_id` | `company_id` | `establishment_id` |
| --- | --- | --- | --- | --- |
| Plataforma | catálogo de permissões, cadastro e situação de contas, inspeção | conta afetada, quando houver | nulo | nulo |
| Tenant | dados da conta, cargos e disponibilidade, vínculos e acesso, cadastro de empresas como estrutura | a conta | nulo | nulo |
| Empresa | empresa criada, alterada, ativada ou inativada; relação comercial; recursos de empresa | a conta | a empresa | nulo |
| Estabelecimento | estabelecimento criado, alterado, promovido ou inativado; operações da unidade | a conta | a empresa | a unidade |
| Compartilhado | dados centrais de Pessoas e etiquetas | a conta | nulo | nulo |
| Atribuição | cargo concedido ou retirado | a conta | empresa do escopo, quando houver | unidade do escopo, quando houver |
| Identidade | troca de e-mail de acesso, senha, segundo fator, `access_enabled` | conta da sessão, quando houver | nulo | nulo |

- **Sem unidade fictícia:** eventos administrativos ficam sem empresa e sem estabelecimento.
- **Eventos sem model** (login, sincronização do catálogo, inspeção) ficam sem empresa e sem estabelecimento.
- **Várias unidades:** uma operação que afeta várias unidades gera um evento por unidade, com o mesmo `operation_id`. Não há pivô de unidades por evento. O provisionamento da empresa com a unidade principal grava os dois eventos na mesma operação.
- **Compartilhado:** a alteração dos dados centrais de uma pessoa é evento de tenant; a da relação comercial, evento da empresa. Um auditor restrito a uma empresa vê a relação, mas não os dados centrais. Essa limitação é aceita porque os dados centrais são compartilhados.

### Autorização de leitura (E05 e E08)

Um evento da conta aparece para o usuário quando o nó do evento está coberto por uma atribuição com `account.audit.list`:

```text
(establishment_id is not null and (establishment_id in E or company_id in C or T))
or (establishment_id is null and company_id is not null and (company_id in C or T))
or (company_id is null and T)
```

T é a permissão concedida em escopo tenant; C, as empresas com a permissão em escopo de empresa; E, os estabelecimentos com a permissão em escopo de unidade.

- A condição é aplicada no SQL, antes de paginar e contar, a partir do descritor de `EffectivePermissions`.
- Listagem, contagem, opções de filtro, detalhe (`findOrFail` sobre o escopo), dados técnicos e exportação usam a mesma condição. `account.audit.view` e `account.audit.technical` são avaliadas sobre o nó do evento.
- Ter acesso a unidades não concede auditoria: `account.audit.list` é sempre exigida.
- Eventos de tenant, compartilhados e administrativos só aparecem com T.
- A E05 entrega essa condição na tela atual, sem filtros novos. Nenhuma atribuição local de auditoria é concedida sem ela.

### Visão consolidada (E08)

- A visão consolidada é a própria condição de leitura: a união dos nós autorizados.
- Filtros de empresa e de estabelecimento só restringem dentro do conjunto autorizado e ficam ocultos quando não houver mais de uma opção.
- Nenhuma consulta é feita por estabelecimento: página e contagem são uma query cada, mais as opções de filtro.

### Autoria

- O autor é sempre a identidade autenticada. Operações automáticas usam autoria de sistema.
- O vínculo usado na operação acompanha o evento a partir da E05.
- Na inspeção, o autor é o administrador SaaS, e o usuário representado fica registrado separadamente.
- O `AuditContext` dura só a requisição ou a operação que o abriu e nunca é herdado por outra requisição no mesmo worker.

### Plataforma e inspeções (PD-02, E08)

- `/saas/auditoria` mostra os eventos de plataforma e os eventos administrativos das contas (provisionamento, situação, plano, inspeções). A lista exata é fechada na E08.
- Eventos de negócio de uma conta ficam visíveis para a plataforma apenas durante uma inspeção autorizada.
- Início, encerramento e escritas de inspeção continuam registrados, com o administrador SaaS e o usuário representado.

## Formatação dos valores

A consulta usa AuditValueFormatter para apresentar opções com os mesmos rótulos dos formulários. Declare a formatação por entidade e campo ao adicionar enums, módulos ou situações. Nunca traduza valores por substituição global: nome, e-mail e outros textos livres podem coincidir com um código. Preserve os valores originais do histórico e formate antes/depois na leitura, cobrindo registros antigos. Teste o rótulo exibido, a preservação do valor armazenado, valores ausentes e textos livres iguais a códigos.

Modo de inspeção usa AccountInspectionMode::label(): Somente leitura ou Leitura e edição. Módulo people usa Pessoas. Situações, acesso e tipos de pessoa possuem seus próprios rótulos. Marcadores de campos protegidos continuam sem conteúdo sensível.

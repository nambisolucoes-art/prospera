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

A prioridade acima segue os fluxos atuais do Prospera.

A implementação usa `Auditable`, `AuditsChanges`, `AuditObserver` e `AuditLog::transaction()`. Declare campos permitidos e exclusões justificadas. Para pivôs, use `recordAuditFieldChange()` dentro da transação da Action. A consulta `/conta/auditoria` ou `/saas/auditoria` é somente leitura e separa listar, visualizar e detalhes técnicos. `audit:check` verifica todos os modelos de domínio, as colunas persistidas e a informação de `company_id` e `establishment_id`; é obrigatório também para módulos novos.

## Contrato da E08 para módulos

O histórico central (`audit_records`, `Auditable`, `AuditsChanges`, `AuditObserver`, `AuditLog`, `AuditContext`) é a única trilha. A migration `2026_11_21_000006` retira do esquema ativo as antigas tabelas de diretório e de catálogo após a conciliação da E08. A consulta e o registro de operações usam apenas a tabela central. A classificação organizacional segue [Contexto organizacional](contexto-organizacional.md).

### Como registrar uma operação

1. **Model persistente:** implemente `Auditable`, use `AuditsChanges` e declare `auditType()`, `auditedFields()` e, com justificativa, `unauditedFields()`. Criação, alteração e exclusão pelo Eloquent já geram o evento, com antes e depois só dos campos alterados.
2. **Relacionamento ou escrita em lote:** registre no método do Model com `recordAuditFieldChange()`, dentro da transação da Action.
3. **Evento sem Model** (envio de link, inspeção, sincronização): `AuditLog::record($tipo, $id, $conta, $evento, $nome, $alteracoes, $empresa, $unidade)`. Declare o rótulo do evento em `config/audit.php` (`events`) e o do tipo em `entities`.
4. **Transação:** Actions com mais de uma escrita usam `AuditLog::transaction()`. Requisições de escrita já rodam numa transação aberta por `EnforceAudit`, e qualquer resposta 4xx ou 5xx desfaz o histórico junto com a operação.
5. **Teste:** confira o evento produzido (tipo, conta, empresa, unidade, autor, representado e campos) e a ausência de evento em atualização sem mudança, em resposta de erro e em reversão.

O módulo não resolve conta, empresa, unidade, autor nem inspeção por conta própria: o Model informa o nó, e o `AuditContext` informa a autoria.

### Recurso afetado e contexto

O contexto do evento é o nó do recurso afetado, nunca a unidade selecionada na Área de Trabalho nem a rota usada.

- `auditAccountId()`, `auditCompanyId()` e `auditEstablishmentId()` leem `account_id`, `company_id` e `establishment_id` do Model. Empresa e estabelecimento sobrescrevem para informar o próprio id.
- Unidade exige empresa, e empresa exige conta. `AuditLog::record` recusa o evento incoerente. No banco, FKs compostas para `companies (id, account_id)` e `establishments (id, company_id, account_id)` e CHECKs no PostgreSQL garantem que a empresa e a unidade pertencem à conta informada.
- As FKs usam `restrict`: conta, empresa ou unidade com histórico não são apagadas fisicamente. Inativar não afeta o histórico.

| Nível | Exemplos | `account_id` | `company_id` | `establishment_id` |
| --- | --- | --- | --- | --- |
| Plataforma | catálogo de permissões, identidade de administrador SaaS | nulo | nulo | nulo |
| Administrativo da conta | cadastro, situação e plano da conta, inspeção iniciada e encerrada | a conta | nulo | nulo |
| Conta | dados da conta, cargos, abrangência geral, vínculos e acesso | a conta | nulo | nulo |
| Disponibilidade restrita | inclusão ou remoção de uma restrição de cargo | a conta | empresa do nó | unidade do nó, quando houver |
| Compartilhado | dados centrais de Pessoas e etiquetas | a conta | nulo | nulo |
| Empresa | empresa criada, alterada, ativada ou inativada; cliente; fornecedor; inativação e reativação da relação | a conta | a empresa | nulo |
| Estabelecimento | estabelecimento criado, alterado, promovido ou inativado; operações futuras da unidade (caixa, venda, estoque) | a conta | a empresa | a unidade |
| Atribuição | cargo concedido ou retirado | a conta | empresa do escopo, quando houver | unidade do escopo, quando houver |
| Identidade | troca de e-mail de acesso, senha, segundo fator, `access_enabled`, entrada e saída | conta da sessão, quando houver | nulo | nulo |

- **Sem unidade fictícia:** eventos da conta ficam sem empresa e sem estabelecimento.
- **Vários nós:** uma operação que afeta vários nós gera um evento por nó afetado, com o mesmo `operation_id`. A inclusão e remoção de restrições de cargo seguem a mesma classificação das atribuições.
- **Compartilhado:** a alteração dos dados centrais de uma pessoa é um único evento da conta, sem cópia por empresa relacionada. A relação comercial é evento da empresa, mesmo quando o operador navega por uma filial.
- **Histórico anterior:** eventos sem empresa ou unidade determinável continuam assim. Nada é preenchido por suposição.

### Frente Institucional

| Evento | Nó do evento |
| --- | --- |
| Colaborador, departamento, função, seção da empresa, site, host de site, ficha da empresa | empresa |
| Lotação, seção da unidade, exceção de horário da unidade, página de unidade, ficha da unidade | estabelecimento, com a empresa |
| Configuração do host do diretório | conta, sem empresa nem estabelecimento |

Publicar uma seção da empresa grava só a linha da empresa: as unidades que herdam são afetadas por leitura, sem evento próprio, e o evento da empresa leva a contagem de afetados. Cada linha efetivamente escrita gera evento no próprio nó, com o mesmo `operation_id`. Contadores derivados (`content_version`) ficam em `unauditedFields()`. A vigência que expira com a passagem do tempo não é operação e não gera evento. Operações do agendador usam autoria de sistema. Detalhes em [Institucional, equipe e sites](institucional.md).

### Autoria e inspeção

| Coluna | Conteúdo |
| --- | --- |
| `actor_id`, `actor_name` | Autor real: a identidade autenticada ou, na inspeção, o administrador SaaS. Operações automáticas usam autoria de sistema (`actor_id` nulo, `Sistema`) |
| `membership_id` | Vínculo cuja autoridade foi usada (o do representado, na inspeção) |
| `represented_user_id`, `represented_user_name` | Usuário representado e o nome dele no momento da operação |
| `inspection_id` | Referência da inspeção, a mesma no início, nas escritas e no encerramento |
| `context` | Origem: `account` (Conta), `platform` (Plataforma SaaS), `platform-account` (Inspeção da plataforma), `system` (Sistema) |

- O nome do autor, o nome do representado e o nome do registro (`entity_name`) são gravados no momento da operação. Renomear, suspender ou remover a identidade não altera o histórico. Empresa e unidade são exibidas com o nome atual, pelo id gravado.
- O representado nunca aparece como autor. O modo da inspeção é lido do evento de início da mesma `inspection_id`.
- O encerramento solicitado, a saída explícita da sessão e a perda de autoridade detectada numa requisição geram `inspection-ended` com o motivo em Encerramento. O início grava seu evento obrigatório antes de trocar a sessão. A expiração passiva do armazenamento de sessões impede continuar a inspeção. Como não executa uma requisição de encerramento, ela pode deixar uma inspeção com apenas o evento de início e as operações realizadas.
- O `AuditContext` usa binding `scoped`, é aberto por `EnforceAudit` e limpo ao fim de cada requisição. Não há estado estático.

### Alterações registradas

- Só campos alterados entram, com antes e depois. Nulo e lista vazia são o mesmo valor ausente. Atualização sem mudança não grava evento, e criação e edição do mesmo registro e nó na mesma operação viram um evento.
- Relacionamentos registram os nomes dos itens antes e depois, e a ficha mostra o que foi adicionado e removido.
- Tentativas negadas não são alterações. Não há política aprovada de eventos de segurança para negações; se for criada, será um evento próprio, distinto das alterações cadastrais.

### Dados protegidos

- Senhas, hashes, tokens (sessão, recuperação, convite, CSRF, API), segredos e códigos do segundo fator, credenciais e imagens nunca entram no histórico. Campos com esses nomes gravam apenas Configurado ou Atualizado. Foto grava Foto atualizada. O documento de Pessoas e da conta (`document`) e o CPF de empresa individual (`individual_document`) gravam Documento anterior e Documento informado. A raiz do CNPJ da empresa e o CNPJ do estabelecimento são identificação empresarial e entram como valor.
- E-mail, telefone e endereço da pessoa entram como valores, porque o histórico precisa mostrar o que mudou. A visibilidade é a do nó do evento: dados centrais só aparecem para quem tem auditoria no escopo da conta.
- A descoberta por CPF ou CNPJ é consulta por POST, sem documento na URL, e não gera evento. Logs da inspeção levam só identificadores. Corpos de requisição não são registrados.

### Imutabilidade

O Model `AuditRecord` restringe alteração e exclusão à operação que criou o evento. A aplicação não expõe rota, Action ou permissão para editar ou excluir o histórico. No PostgreSQL, os gatilhos de `audit_records` aceitam UPDATE ou DELETE somente para linhas inseridas na mesma transação de banco. Isso permite consolidar mudanças e cancelar uma alteração líquida zero, inclusive com savepoints. UPDATE, DELETE e TRUNCATE sobre o histórico confirmado são recusados também em SQL direto e escritas em lote. SQLite exercita as regras da aplicação, e o harness de migrations cobre os gatilhos no PostgreSQL.

O papel de execução em instalações operacionais deve ter só os privilégios de dados necessários, sem propriedade do esquema ou permissão para desabilitar gatilhos. A execução das migrations pertence a um papel administrativo separado. O Compose de desenvolvimento usa a mesma credencial para ambos. Um proprietário do esquema ou superusuário pode alterar a proteção por DDL, portanto ela não prova resistência contra administradores do banco.

O módulo deve deixar o vínculo entre escrita de negócio e auditoria dentro da mesma transação. É proibido gravar diretamente em `audit_records` por SQL de aplicação, atualizar o histórico após o commit, duplicar eventos por atribuição ou fazer consultas de auditoria sem a política de escopo. Eventos explícitos sem Model também devem enviar apenas campos seguros.

### Implantação e recuperação

Antes da evolução de um banco pré-E08 com dados a preservar, faça backup verificado, exporte as duas tabelas antigas e reconcilie contagens e amostras dos eventos centralizados. A importação histórica usa uma janela temporal e campos permitidos. A janela não prova equivalência linha a linha e alguns atributos originais só podem ser recuperados do backup. A reversão da remoção recria as tabelas vazias. Para reverter também a importação e reaplicá-la, restaure os dados de origem antes de reverter a migration de consolidação. Execute a evolução primeiro em cópia isolada e programe a atualização dos workers no ambiente de destino.

### Evoluções independentes

Tentativas negadas pertencem à segurança operacional. Uma decisão própria deve definir eventos de autenticação suspeita, escopo indevido, elevação de privilégios e falhas de autorização. Respostas 403 e 404 não geram eventos de negócio por padrão. A política de retenção deve tratar prazo, crescimento, arquivamento, privacidade, exportação, exclusão controlada, preservação histórica e recuperação. Nenhum prazo ou descarte automático é definido aqui.

## Consulta e autorização

Um evento da conta aparece para o usuário quando o nó do evento está coberto por uma atribuição com `account.audit.list`:

```text
(establishment_id is not null and (establishment_id in E or company_id in C or T))
or (establishment_id is null and company_id is not null and (company_id in C or T))
or (company_id is null and T)
```

T é a permissão concedida em escopo tenant; C, as empresas com a permissão em escopo de empresa; E, os estabelecimentos com a permissão em escopo de unidade.

- A condição vem do descritor de `EffectivePermissions` e é aplicada no SQL por `AuditQuery::scoped`, antes de paginar, contar e montar opções.
- Listagem, contagem, paginação, busca, opções de filtro e detalhe usam a mesma consulta. O detalhe aplica também o descritor de `account.audit.view` e responde 404 fora do escopo. Os dados técnicos exigem `account.audit.technical` sobre o nó do evento.
- Com várias atribuições, o usuário vê a união dos nós autorizados. Não há herança além da regra acima: auditoria de unidade não vê eventos da empresa nem da conta, e auditoria de empresa não vê eventos da conta nem de outra empresa.
- Ter acesso a unidades não concede auditoria: `account.audit.list` é sempre exigida.
- A revogação de uma atribuição ou a suspensão do vínculo valem na requisição seguinte.

### Filtros (E08)

- Período (intervalo semiaberto sobre `created_at`), autor, tipo, ação, origem e busca por registro ou autor.
- Empresa, Estabelecimento e Nível (Conta, Empresa, Estabelecimento) só restringem o conjunto autorizado. As opções vêm dos nós autorizados, e os filtros ficam ocultos quando não há mais de uma opção. Com uma empresa escolhida, Estabelecimento lista só as unidades dela.
- A unidade selecionada na Área de Trabalho e a preferência salva não alteram a auditoria.
- Autores, tipos e ações vêm da consulta com escopo. No escopo da conta inteira, são calculados por varredura solta dos índices `(account_id, actor_id, created_at)` e `(account_id, entity_type, event)`.

### Plataforma (PD-02)

`/saas/auditoria` mostra apenas eventos com `account_id` nulo, eventos do tipo Conta (cadastro, situação, plano, dados da conta, inspeção iniciada e encerrada) e eventos de origem Plataforma SaaS (provisionamento da conta). Eventos de negócio das contas, inclusive as escritas feitas durante uma inspeção, ficam visíveis só pela auditoria da conta, para quem tem a permissão ou durante uma inspeção autorizada. Os filtros de empresa, estabelecimento e nível não existem na plataforma.

## Testes obrigatórios de um módulo novo

- Evento de criação, alteração e exclusão com o nó correto (conta, empresa, unidade).
- Autor real, representado e `inspection_id` em inspeção com edição; nenhuma escrita em somente leitura.
- Atualização sem mudança, resposta de erro e reversão sem evento.
- Auditor de outra empresa, de outra unidade e de outra conta sem acesso ao evento (lista, contagem, opções e detalhe 404).
- Ausência de segredos no histórico e nos dados técnicos.
- `php artisan audit:check` verde.

## Formatação dos valores

A consulta usa AuditValueFormatter para apresentar opções com os mesmos rótulos dos formulários. Declare a formatação por entidade e campo ao adicionar enums, módulos ou situações. Nunca traduza valores por substituição global: nome, e-mail e outros textos livres podem coincidir com um código. Preserve os valores originais do histórico e formate antes/depois na leitura, cobrindo registros antigos. Teste o rótulo exibido, a preservação do valor armazenado, valores ausentes e textos livres iguais a códigos.

Modo de inspeção usa AccountInspectionMode::label(): Somente leitura ou Leitura e edição. Módulos de etiquetas usam Clientes e Fornecedores; o valor histórico people aparece como Cadastro anterior, sem oferecer esse módulo para novos cadastros. Situações, acesso e tipos de pessoa possuem seus próprios rótulos. Marcadores de campos protegidos continuam sem conteúdo sensível.

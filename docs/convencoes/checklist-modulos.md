# Checklist de módulos

Critério de aceite de qualquer módulo novo (CRM, estoque, vendas, financeiro, ordens de serviço e outros) e de alterações relevantes em módulos existentes. O documento do módulo responde à ficha antes da implementação, e a entrega comprova cada item da lista. Um item que não se aplica é marcado com a justificativa.

## Ficha do módulo

Responder antes de implementar:

1. Quem é o proprietário de cada dado (plataforma, tenant, empresa, estabelecimento ou habilitação)?
2. Qual é o nó de cada recurso e quais alvos cada permissão admite?
3. O dado é compartilhado? Por qual mecanismo (cadastro central do tenant ou habilitação por unidade)?
4. Quais estabelecimentos podem utilizá-lo?
5. Quem pode visualizá-lo (permissão e escopo)?
6. Quem pode modificá-lo (permissão e escopo)?
7. Como é auditado (`company_id`, `establishment_id`, eventos entre unidades)?
8. Como aparece na visão consolidada (descritor autorizado)?
9. Como o contexto chega ao backend (grupo de rota e parâmetros)?
10. Como o acesso indevido é impedido (Query com escopo, FK composta e testes)?

## Verificações

### Propriedade e escopo organizacional

- [ ] Cada tabela tem as colunas da sua classe: `account_id`; mais `company_id` em dados de empresa; mais `establishment_id` em dados de unidade.
- [ ] FKs compostas garantem que unidade, empresa e tenant são coerentes.
- [ ] Rotas de empresa ou de estabelecimento usam o grupo de contexto correto; operações de tenant usam rotas administrativas sem contexto fictício.
- [ ] Tenant com uma empresa e uma unidade funciona sem seletor nem passo extra.

### Permissões e autorização

- [ ] Cada ação tem permissão própria, com identificador literal `account.modulo.acao`, sem permissão genérica de gerenciar.
- [ ] Cada permissão declara os alvos admitidos em `config/permissions.php`, e a verificação usa o nó do recurso afetado.
- [ ] Rotas e Actions verificam no servidor, inclusive ações compostas dentro de outro formulário.
- [ ] Concessões e atribuições respeitam a antiescalada; o administrador integral recebe as permissões novas e nenhum cargo de tenant recebe `platform.*`.
- [ ] `php artisan permissions:sync` executado.

### Compartilhamento e dados de pessoas

- [ ] Nenhum dado de empresa é visível a outra empresa sem regra explícita.
- [ ] Dados ligados a uma pessoa seguem [Pessoas e contatos](pessoas-e-contatos.md): dados da relação ficam na relação, sem cópia para os dados centrais.
- [ ] Nenhum fluxo altera a identidade global ([Identidade e vínculos](identidade-e-vinculos.md)).

### Integridade

- [ ] Invariantes do módulo garantidos na camada adequada (FK, índice parcial, CHECK, transação com trava, regra de domínio).
- [ ] Escritas múltiplas em `AuditLog::transaction()`; falha no meio não deixa sobras.
- [ ] Migrations classificadas quanto à reversibilidade (R, RP ou B), com `down()` testada.

### Auditoria

- [ ] Models implementam `Auditable`, usam `AuditsChanges` e declaram `auditedFields()`; exclusões justificadas.
- [ ] Eventos gravam `company_id` e `establishment_id` conforme o nó do recurso afetado, nunca pela unidade selecionada na Área de Trabalho; unidade sempre com a empresa.
- [ ] Eventos sem Model usam `AuditLog::record` com rótulos em `config/audit.php`; nenhuma resolução própria de autor, conta ou inspeção.
- [ ] Relacionamentos e escritas em lote registrados explicitamente na mesma transação. Uma operação que afeta vários nós usa o mesmo `operation_id` e registra cada nó, sem duplicar por atribuição.
- [ ] Segredos, tokens, senhas e imagens fora do histórico; documentos pessoais mascarados.
- [ ] Testes do evento produzido: nó, autor, representado em inspeção, sem evento em atualização sem mudança, erro e reversão, e sem acesso por auditor de outra empresa ou unidade ([Auditoria](auditoria.md)).
- [ ] `php artisan audit:check` verde. A leitura reutiliza a consulta central e o módulo não modifica eventos confirmados.

### Consultas contextualizadas

- [ ] Toda leitura passa por Query com tenant e nó ou descritor autorizado; nunca `Model::find` sem escopo.
- [ ] Listagem, contagem, opções de filtro, detalhe, busca e exportação usam a mesma condição de escopo.
- [ ] Visão consolidada só em telas que a declaram, restrita ao conjunto autorizado.

### Segurança

- [ ] ID de outro tenant, de outra empresa e de outra unidade responde 404, na leitura e na escrita.
- [ ] Código de outra empresa ou de outro tenant na URL é recusado.
- [ ] Mensagens de erro e unicidade não revelam dados de outra conta ou empresa.
- [ ] Jobs levam contexto explícito e reautorizam; chaves de cache têm tenant e nó.
- [ ] Inspeção somente leitura bloqueia as escritas do módulo.

### Performance

- [ ] Contrato de [Performance](performance.md) seguido: sem estado mutável entre requisições, sem N+1, paginação e agregação no banco.
- [ ] Queries por requisição medidas e registradas; índices justificados por consulta e por plano medido.
- [ ] Medição em PostgreSQL nos cenários grandes quando houver consulta crítica.

### Componentes reutilizáveis

- [ ] Telas usam `PageHeader`, `BusinessList`, `BusinessListSearch`, `useBusinessListFilters`, `BusinessForm`, `SelectControl`, `Modal` e `PreviewSection`; campos de documento, telefone, WhatsApp e endereço reutilizam os de Pessoas.
- [ ] Rótulos Salvar, Cancelar e Excluir; textos sem travessão nem meia-risca; "Não informado" para valores ausentes.
- [ ] Artigo de ajuda e tour com `BusinessListTour` e `PageHeaderTour`; validação visual em 1440, 390 e 320 px.

### Testes

- [ ] Outro tenant, outra empresa e outra unidade.
- [ ] Manipulação de ID e de URL.
- [ ] Separação entre ações e revogação na requisição seguinte.
- [ ] Consolidado restrito ao conjunto autorizado.
- [ ] Orçamento de queries da listagem.
- [ ] Autoria, escopo e reversão da auditoria.
- [ ] Suíte em SQLite em memória verde; concorrência e medições em PostgreSQL isolado quando aplicável.

## Aplicação aos módulos previstos

| Módulo | Proprietário | Alvo típico | Observação |
| --- | --- | --- | --- |
| CRM, leads, oportunidades | Empresa | `company` | Pessoa do tenant como contato |
| Produtos e serviços | Empresa | `company` (cadastro), `establishment` (preço local) | Habilitação por unidade |
| Estoque e depósitos | Estabelecimento | `establishment` | Depósito pertence à unidade |
| Compras | Empresa (documento), unidade (recebimento) | `company` e `establishment` | |
| Vendas e PDV | Estabelecimento (operação), empresa (responsável) | `establishment` | Ponto de venda é entidade do módulo |
| Financeiro | Empresa | `company`; caixa em `establishment` | Entre empresas é operação comercial |
| Ordens de serviço | Estabelecimento | `establishment` | |
| Relatórios | Conforme a fonte | Conforme a fonte | Sempre pelo descritor autorizado |

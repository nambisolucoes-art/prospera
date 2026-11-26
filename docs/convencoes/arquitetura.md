# Arquitetura do backend

Mapa curto do padrão que o Prospera segue em **todo** o backend. É o padrão adotado para todo o projeto, adaptado ao multi-tenant. O detalhe de cada camada, com exemplos, está em [Código do backend](codigo-backend.md); aqui está o que importa para decidir onde cada coisa vai e o que nunca fazer.

## Fluxo de uma operação

```text
Controller  →  Data (valida)  →  Action (orquestra)  →  DTO  →  Model (register/revise: regras)  →  save
   │                                  │
   └─ Query (leitura)                 └─ sub-Action, Query, AuditLog::transaction
```

| Camada | Pasta | Faz | Não faz |
| --- | --- | --- | --- |
| Controller | `UI/Controllers` | Coleta entrada e contexto, autoriza a rota, chama Data e Action, responde | Regra, consulta, transação |
| Data | `Application/Data/{Recurso}` | Formato e sanitização da entrada **de uma operação**: `rules()`, `messages()`, `fromInput()` | Consultar o registro atual, decidir regra de negócio |
| Action | `Application/Actions/{Recurso}` | **Um** caso de uso: transação, trava, `Gate::authorize`, DTO, persistência, auditoria | Funções privadas, validar formato, `array` solto |
| DTO | `Domain/DTOs/{Recurso}` | Valores tipados para o Model | Regra |
| Model | `Domain/Models` | `register(DTO)`, `revise(DTO)`, invariantes (lança exceção de domínio), relacionamentos | `save()` (é da Action) |
| Query | `Application/Queries/{Recurso}` | Uma leitura, método `execute()`, escopo no SQL | Escrever, várias leituras |
| Value Object | `Domain/ValueObjects` | Valor com invariante: construtor privado e construtores nomeados | Estado mutável |
| Exceção | `Domain/Exceptions` | Regra violada, mensagem para a pessoa | Resposta HTTP |

## Regras que não mudam

1. **Create e Update separados**, em Action, Data e DTO. Nunca `SaveXAction`, `SaveXData`, `SaveXDTO`. Repetir regras entre as duas Datas é aceito.
2. **A Data valida só formato.** Regra que depende do registro, do dono ou de outras linhas é invariante do Model ou de um Value Object (exceção de domínio) ou de uma sub-Action (`AssertXAction`).
3. **Edição substitui os valores** (como o formulário envia). O valor atual vem do Model travado, na Action, nunca de um array na Data. Exceção declarada: seções do perfil institucional, que herdam por seção inteira e aceitam PATCH parcial.
4. **A Action tem `execute(Data, ...)`, é `final readonly` e não tem função privada.** Passo com nome vira sub-Action, Query ou método do Model. O DTO é montado numa variável antes de ir ao Model.
5. **Autorização no servidor**, com slug literal em `Gate::authorize('account.modulo.acao', $no)` dentro da Action. Trava na ordem empresa, unidade, registro.
6. **Exceção de domínio** para regra violada. Erro de campo estende `ValidationException` (422). `abort` só no Controller e no middleware.
7. **Escrita auditada**: Model `Auditable` com `AuditsChanges`, e `AuditLog::transaction()` quando há várias escritas. Sem mudança, sem evento.
8. **Leitura com escopo no SQL** (conta, empresa, estabelecimento) antes de paginar, contar ou agregar. ID de outro escopo responde 404.
9. **Sem compatibilidade com estrutura antiga:** ao substituir, remova a antiga na mesma tarefa.

## Receita de um recurso novo

1. Migration aditiva (FKs compostas, CHECKs) e Model (`register`, `revise`, `auditedFields`).
2. `CreateXDTO`, `UpdateXDTO`, `CreateXData`, `UpdateXData` (`rules()` e `messages()` próprias).
3. `CreateXAction`, `UpdateXAction` (e `DeleteX` se houver exclusão), com `Gate::authorize` e `AuditLog::transaction`.
4. Queries de leitura (`ListX`, `ShowX`, `XExists`) e Controller fino, rotas com o contexto na URL.
5. Permissões literais e `permissions:sync`; `audit:check`; ajuda e tour; testes.

## Implementações de referência

- Cadastro simples: `Colaboradores/Departments` (Create, Update, Data, DTO, Queries).
- Recurso por dono com período e invariantes no Model: `Institucional/Hours` (`HourException`, `Shifts`, `LockProfileOwnerAction`, `AssertHourExceptionPeriodFreeAction`).
- Valor por seção com herança: `Institucional/Profile` (`CreateProfileSectionAction`, `UpdateProfileSectionAction`, `ProfileResolver`).

## Economia de execução

O custo de uma tarefa vem mais de leitura repetida e de prova excessiva do que de código. Regras:

- **Leia o mínimo.** Só a linha da tabela do `AGENTS.md` que corresponde à tarefa e, no plano, só a seção da etapa. Nunca o plano inteiro. Use `grep -n` para achar a seção e leia o intervalo.
- **Não releia** arquivo que acabou de editar nem repita comando cujo resultado já está na conversa.
- **Teste proporcional:** `--filter` do que mudou durante o trabalho; suíte completa, Pint e PHPStan **uma vez**, antes da entrega. Laboratório PostgreSQL, concorrência, FrankenPHP e Chromium só quando a mudança toca banco, trava, contexto ou tela, e nunca repetidos sem causa.
- **Saída curta:** comandos com `tail`, `grep` ou `head`; capturas de tela só dos estados que provam a mudança.
- **Entrega enxuta:** o que mudou, o que foi provado e o que falta, sem repetir o que o diff mostra.

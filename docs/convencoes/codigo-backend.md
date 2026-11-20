# Código do backend

Padrão de escrita do backend, alinhado ao GNAI2 (módulo de Itens). O objetivo é um código que uma pessoa entende ao bater o olho, mesmo com mais linhas.

## Camadas

| Camada | Responsabilidade | Exemplo |
| --- | --- | --- |
| Controller (`UI/Controllers`) | Coleta entrada e contexto, chama a Data e a Action, responde | `DirectoryController::savePerson` |
| Data (`Application/Data`) | Sanitiza, normaliza e valida a entrada. Campos ausentes numa edição recebem o valor atual | `SavePersonData`, `SaveAccountData` |
| DTO (`Domain/DTOs`) | Valores tipados que o domínio recebe | `PersonDTO`, `TagDTO`, `AccountDTO` |
| Model (`Domain/Models`) | Preenche os próprios atributos, aplica suas regras e sincroniza seus relacionamentos | `Person::register()`, `$person->revise()`, `$person->assignTags()` |
| Action (`Application/Actions`) | Orquestra o caso de uso: transação, travas, autorização composta, DTO, persistência e sub-Actions | `SavePersonAction` |
| Query (`Application/Queries`) | Leitura. Verificações de existência usam uma Query de propósito único | `PersonDocumentExistsQuery`, `UserEmailExistsQuery` |

A validação fica nas classes Data, sem FormRequests.

## Actions

- A Action não tem funções privadas. Um passo que merece nome próprio vira método do Model, Query ou sub-Action injetada no construtor (`SavePersonAccessAction`, `SaveCustomerAction`).
- Dependências entram pelo construtor com `private readonly` (ou classe `final readonly`). Evite `app(...)` dentro da Action. A exceção são serviços de contexto com binding `scoped`, como `AuditContext`, resolvidos no momento do uso para nunca carregar o estado de outra requisição no Octane.
- O DTO é montado numa variável com argumentos nomeados e só depois entregue ao Model:

```php
$personDTO = new PersonDTO(
    name: $data->values['name'],
    email: $data->values['email'] ?? null,
    document: $data->values['document'] ?? null,
);

if ($lockedPerson === null) {
    $lockedPerson = Person::register($personDTO, $account->id);
} else {
    $lockedPerson->revise($personDTO);
}

$lockedPerson->saveOrFail();
```

- Condições compostas ficam escritas por extenso dentro do `if`, uma condição por linha, ou numa variável com nome que diga a regra.
- Use `$locked{Recurso}` para o registro travado com `lockForUpdate()`, sem reatribuir o parâmetro recebido.
- Valores simples (um ID, um booleano) dispensam DTO.

## Models

- `register(DTO)` cria a instância e `revise(DTO)` altera a existente. Os dois preenchem os atributos a partir do DTO; a persistência fica na Action.
- Regras do próprio registro e consultas ao estado dele ficam no Model (`$user->isLastAdministratorWithAccess()`).
- Relacionamentos são sincronizados por um método do Model, que registra a mudança no histórico com `recordAuditFieldChange()`:

```php
$before = $this->tags()->pluck('tags.id')->all();
$this->tags()->sync($tagIds);
$this->recordAuditFieldChange('tag_ids', $before, $tagIds, 'Etiquetas');
```

## Auditoria

- O histórico central (`audit_records`) é a única trilha. Não crie tabelas, classes ou gravações paralelas.
- Campos são auditados pelo `AuditsChanges`. Relacionamentos, pelo método do Model com `recordAuditFieldChange()`.
- Regras completas em [Auditoria](auditoria.md).

## Permissões

O `permissions:sync` só descobre identificadores literais escritos direto na chamada (`Gate::authorize('account.tags.activate')`, `can('...')`, `can:...`). Não monte o slug com ternário, variável ou concatenação; use `if`/`else` com uma chamada literal em cada ramo.

## Formatação

- Linhas de até 120 colunas. O Pint (`preset laravel`) não quebra linhas; a quebra é responsabilidade de quem escreve.
- Uma instrução por linha. Separe com uma linha em branco os blocos lógicos: autorização, busca, regra, DTO, persistência, auditoria e retorno.
- Cadeias de consulta com mais de duas chamadas ficam com uma chamada por linha.
- Arrays, assinaturas e chamadas que passem de 120 colunas, ou com mais de três itens, ficam com um item por linha e vírgula final.
- Rotas com mais de uma chamada encadeada ficam com uma chamada por linha, separadas por linha em branco.
- Comentários curtos em português explicam o porquê de uma regra, não o que a linha faz.

## Compatibilidade

Não mantenha compatibilidade com trilhas, rotas, classes ou estruturas antigas. Ao substituir algo, remova o anterior na mesma tarefa e ajuste os testes. Dados históricos só são apagados com aprovação.

## Verificação

`make test`, `./vendor/bin/pint --test` e `./vendor/bin/phpstan analyse` sem erros. Testes seguem a mesma formatação.

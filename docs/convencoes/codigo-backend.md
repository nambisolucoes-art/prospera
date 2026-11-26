# Código do backend

Mapa curto e receita: [Arquitetura do backend](arquitetura.md). Padrão de escrita do backend. O objetivo é um código que uma pessoa entende ao bater o olho, mesmo com mais linhas e com repetição entre arquivos. **Toda classe nova segue este documento. Código que não o segue é refatorado na mesma tarefa em que é tocado.**

## Práticas do padrão e como o Prospera as aplica

| Prática | Aplicação no Prospera |
| --- | --- |
| `declare(strict_types=1);` em todo arquivo PHP | Obrigatório em todo arquivo de `app/` |
| Uma Action por caso de uso (`CreateDepartmentAction`, `UpdateDepartmentAction`), `final readonly`, método único `execute()` | Nada de `SaveXAction` que cria e edita. Ativar e inativar seguem como campo de situação do Create e do Update, com a permissão própria conferida na Action |
| A Action recebe o objeto Data tipado, monta o DTO numa variável com argumentos nomeados e o entrega ao Model | A Action nunca lê `$data->values['campo']` nem recebe `array` |
| Uma Data por operação (`CreateXData`, `UpdateXData`, `ListXData`), cada uma com **as próprias regras e mensagens**, mesmo repetidas | Sem Data compartilhada entre criar e editar. A repetição é aceita: cada arquivo se explica sozinho |
| Data tipada: propriedades públicas `readonly` com tipo, enums, objetos de valor | `final readonly class` com construtor tipado. Sem `array $values` |
| Validação dentro da Data (`rules()`, `messages()`), sem FormRequest | O Prospera valida em `fromInput(array $input, ...contexto)`, que chama `rules()` e `messages()` da própria classe. Não há biblioteca de Data: a validação precisa da conta e do contexto da requisição (unicidade por empresa, descritor de acesso), que a biblioteca não alcança |
| DTO por operação (`CreateXDTO`, `UpdateXDTO`), `final readonly`, valores tipados e sem regra | DTO só existe para entrada de escrita no Model |
| Model: `register(CreateXDTO)` e `revise(UpdateXDTO)` preenchem os atributos, normalizam e aplicam as regras do registro | Persistência (`save`) fica na Action |
| Query de propósito único, `final readonly`, método `execute()` (`ListDepartmentsQuery`, `ShowDepartmentQuery`, `DepartmentNameExistsQuery`) | Vale para código novo. Consultas antigas com vários métodos são divididas quando tocadas |
| `ValueObjects` (`OrganizationCode`, `Shift`): `final readonly`, construtor privado, construtores nomeados, invariantes que lançam exceção de domínio | Valores com regra própria vão para `Domain/ValueObjects` (`OrganizationCode`, `AccountCode`, `AccessNode`, `AuthorizedNodes`) |
| `Enums` com `label()` e `options()` | Vale |
| Exceções de domínio tipadas (`InvalidHourException`, `CompanyNotAvailable`) e `@throws` na Action | Vale para regra de negócio violada. `abort_if` e `abort` ficam só no Controller e no middleware |
| Controller recebe a Data e a Action por injeção e só redireciona (`store(CreateXData $data, CreateXAction $action)`) | O Prospera monta a Data com `XData::fromInput($request->all(), ...contexto)` |
| Pastas por recurso dentro de cada camada (`Actions/Departments`, `Data/Departments`, `Queries/Departments`, `DTOs/Departments`) | Vale quando o domínio tem mais de um recurso |

## Camadas

| Camada | Responsabilidade | Exemplo |
| --- | --- | --- |
| Controller (`UI/Controllers`) | Coleta entrada e contexto, autoriza a rota, monta a Data, chama a Action, responde | `DepartmentController::store` |
| Data (`Application/Data/{Recurso}`) | Sanitiza, normaliza e valida a entrada de **uma** operação e entrega valores tipados | `CreateDepartmentData`, `UpdateDepartmentData`, `ListDepartmentsData` |
| DTO (`Domain/DTOs/{Recurso}`) | Valores tipados que o Model recebe | `CreateDepartmentDTO`, `UpdateDepartmentDTO` |
| Model (`Domain/Models`) | Preenche os próprios atributos, normaliza, aplica suas regras e sincroniza seus relacionamentos | `Department::register()`, `$department->revise()` |
| Action (`Application/Actions/{Recurso}`) | Orquestra **um** caso de uso: transação, travas, autorização composta, DTO, persistência, auditoria e sub-Actions | `CreateDepartmentAction` |
| Query (`Application/Queries/{Recurso}`) | Leitura de propósito único | `ListDepartmentsQuery`, `ShowDepartmentQuery`, `DepartmentNameExistsQuery` |
| Value Object (`Domain/ValueObjects`) | Valor com regra própria e imutável | `OrganizationCode`, `AccessNode` |
| Enum (`Domain/Enums`) | Conjunto fechado de valores, com rótulo | `CommercialRole` |
| Exceção (`Domain/Exceptions`) | Regra de negócio violada, com mensagem para a pessoa | `LastAdministratorCannotBeRemoved` |

A validação fica nas classes Data, sem FormRequests. Os testes seguem em `tests/`.

## Data

```php
<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Departments;

use App\Support\NormalizedName;
use Illuminate\Support\Facades\Validator;

final readonly class CreateDepartmentData
{
    public function __construct(
        public string $name,
        public bool $active,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     * @param  list<int>  $companyIds
     */
    public static function fromInput(array $input, array $companyIds): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = NormalizedName::display($input['name']);
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        return new self(
            name: $values['name'],
            active: (bool) ($values['active'] ?? true),
        );
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do departamento.',
            'name.max' => 'O nome deve ter até 100 caracteres.',
        ];
    }
}
```

- Uma Data por operação. A Data de edição repete as regras e as mensagens que precisar. Não existe `SaveXData`.
- Campos opcionais ausentes viram `null` ou o valor padrão declarado na Data. Numa edição, o valor atual vem do Model, na Action, e nunca de `$input` em um array solto.
- Dependências de contexto (conta, empresa, ID ignorado na unicidade) entram como parâmetros de `fromInput`, nunca por `app()`.
- Filtros de listagem também são Data (`ListXData`), com valores já saneados e tipados.

## DTO

- `final readonly`, argumentos nomeados, sem regra e sem consulta.
- Um DTO para criar e outro para editar, mesmo quando iguais hoje. O de edição só traz o que a edição pode mudar.
- Dado de empresa, estabelecimento ou conta que o Model precisa para nascer (`accountId`, `companyId`) entra como argumento de `register`, não no DTO.

## Actions

- Uma Action por caso de uso, `final readonly class`, método `execute()`. Para Actions de criação e edição, o primeiro argumento é a Data da operação.
- A Action não tem funções privadas. Um passo que merece nome próprio vira método do Model, Query ou sub-Action injetada no construtor.
- Dependências entram pelo construtor com `private readonly`. Evite `app(...)` dentro da Action. A exceção são serviços de contexto com binding `scoped`, como `AuditContext`, resolvidos no momento do uso para nunca carregar o estado de outra requisição no Octane.
- O DTO é montado numa variável com argumentos nomeados e só depois entregue ao Model:

```php
$departmentDTO = new CreateDepartmentDTO(
    name: $data->name,
    active: $data->active,
);

$department = Department::register($departmentDTO, $accountId, $companyId);
$department->saveOrFail();
```

- Condições compostas ficam escritas por extenso dentro do `if`, uma condição por linha, ou numa variável com nome que diga a regra.
- Use `$locked{Recurso}` para o registro travado com `lockForUpdate()`, sem reatribuir o parâmetro recebido.
- Valores simples (um ID, um booleano) dispensam DTO.
- A Action declara `@throws` das exceções de domínio que lança.

## Models

- `register(CreateXDTO)` cria a instância e `revise(UpdateXDTO)` altera a existente. Os dois preenchem os atributos a partir do DTO e normalizam (`trim`, texto vazio vira `null`); a persistência fica na Action.
- Regras do próprio registro e consultas ao estado dele ficam no Model (`$user->isLastAdministratorWithAccess()`).
- Relacionamentos são sincronizados por um método do Model, que registra a mudança no histórico com `recordAuditFieldChange()`:

```php
$before = $this->tags()->pluck('tags.id')->all();
$this->tags()->sync($tagIds);
$this->recordAuditFieldChange('tag_ids', $before, $tagIds, 'Etiquetas');
```

## Queries

- Uma Query, um propósito, `final readonly`, método `execute()`. Consulta de existência tem Query própria (`PersonDocumentExistsQuery`).
- Escopo de conta, empresa e estabelecimento no SQL, antes de paginar, contar ou agregar (ver [Contexto organizacional](contexto-organizacional.md)).

## Value Objects, Enums e exceções

- Value Object: `final readonly`, construtor privado, construtores nomeados (`from`, `restore`), invariantes validadas no construtor com exceção de domínio.
- Enum com `label()` e `options()` quando alimenta um select.
- Exceção de domínio estende uma base do domínio e carrega mensagem em português para a pessoa. O Controller ou o tratador de exceções a converte em resposta HTTP.

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

## Estado da adoção

Registro vivo da migração do código existente para este padrão. Atualize ao concluir cada ponto. Código novo já nasce no padrão.

| Ponto | Estado |
| --- | --- |
| `declare(strict_types=1)` em todos os arquivos de `app/` | **Adotado** |
| Create e Update separados (Action, Data, DTO) | **Adotado** em Colaboradores (referência), Etiquetas, Organização (empresas e estabelecimentos), Plataforma (contas), Pessoas, Acessos (cargos). `SaveRoleAssignmentsAction` (conceder e revogar atribuições) e as Actions de Usuários (perfil, senha, foto, e-mail de acesso) já são de propósito único e seguem como estão |
| Actions `final readonly`, sem funções privadas | **Adotado** |
| Data tipada, sem `array $values` | **Adotado**. As Datas ainda sem prefixo de operação (`PasswordData`, `ProfilePhotoData`, `TwoFactorCodeData`, `ChangeAccessEmailData`) já são de uma operação só |
| Value Objects em `Domain/ValueObjects` | **Adotado** (`AccessNode`, `AuthorizedNodes`, `NavigableCompany`, `NavigableEstablishment`, `OrganizationCode`, `AccountCode`, `PersonEditAuthority`, `AccountInspection`) |
| Exceções de domínio no lugar de `abort` em Actions e Queries | **Adotado** (`App\Support\Exceptions`: `OperationNotAllowed` 403, `ResourceNotAvailable` 404, `ResourceGone` 410, `ConflictingChange` 409, e as de cada domínio). `AccountContext`, `OrganizationContext`, `WorkspaceDestinationData` e `WorkspaceSelectionData` mantêm o `abort`, por serem serviços de contexto de requisição |
| Seeders e fixtures de desempenho nas novas Actions | **Adotado** |
| Queries de propósito único (`execute`) | **Adotado**: cada Query tem um método `execute` e fica numa pasta por recurso (`Companies`, `Establishments`, `Context`, `CommercialRelations`, `Memberships`, `Roles`, `People`, `Accounts`, `Users` e outras). Exceções por não serem consultas de um registro: `AuditQuery` (pipeline de Builder: escopo, filtro e paginação do histórico, com a formatação de cada evento) e `HelpCatalog` (catálogo e renderização de artigos, sem banco) |
| `execute(Data, ...)` com a Data como primeiro argumento | **Adotado** em todas as Actions que recebem uma Data. As Actions comerciais recebem `RegisterCommercialRelationData` e `UpdateCommercialRelationData`, e os dados centrais da pessoa ficam em `CommercialPersonChangesData`, que só vira `UpdatePersonData` depois que a Action trava a pessoa. A troca do e-mail de acesso virou `RequestAccessEmailChangeAction` e `ConfirmAccessEmailChangeAction`, e `SaveRoleAssignmentsAction` virou `ChangeRoleAssignmentsAction` com `AssertRoleAssignableAction` à parte |
| Controller só com injeção e redirecionamento | **Adotado** em Relações comerciais (cadastro, edição, ativar e inativar chamam a Action com a Data pronta e a autorização mora na Action). **Exceção declarada:** `EstablishmentController` mantém no controller a autorização de cadastrar, editar e promover, porque `CreateEstablishmentAction`, `UpdateEstablishmentAction` e `PromotePrimaryEstablishmentAction` também rodam em seeders, migrations e fixtures sem operador autenticado. Mover a verificação para a Action exige separar esse uso interno |
| Institucional (S06 e S07) | **Adotado**: seções do perfil (`CreateProfileSectionAction`, `UpdateProfileSectionAction`), exceções de horário (`Create`, `Update` e `DeleteHourExceptionAction`) e fuso, com Data e DTO por operação, invariantes em `ProfileSection`, `HourException`, `Establishment::useTimezone()` e nos Value Objects `Shift`, `Shifts` e `DaySchedule`, e sub-Actions no lugar de funções privadas |

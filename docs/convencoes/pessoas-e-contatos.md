# Pessoas e contatos

Política oficial da DA-25. Pessoas é o cadastro central do tenant. Cada empresa mantém, na relação comercial, os dados e contatos que só interessam a ela. Uma alteração local nunca modifica, em silêncio, dados de outra empresa. Contexto geral em [Contexto organizacional](contexto-organizacional.md).

Esta política classifica os campos existentes e reserva o lugar dos campos futuros. Ela não move campos nem tabelas: os campos atuais de `people` continuam onde estão, e qualquer mudança de classe exige decisão própria, registrada no plano.

## Princípios

- **Duas camadas:** dados centrais (tabela `people`, nó tenant ou alvo `person`) e dados da relação comercial (`customers` e `suppliers`, nó `company`).
- **Sem sincronização:** nenhuma rotina copia, sincroniza ou propaga dados entre os dados centrais e as relações, nem entre relações de empresas diferentes.
- **Alteração explícita:** quem altera um dado central vê que ele vale para todas as empresas. Quem altera um dado da relação altera só a própria empresa.
- **Contato de uma empresa só:** um contato que interessa a uma única empresa (por exemplo, o e-mail de cobrança da Empresa A) é gravado na relação daquela empresa, nunca no cadastro central.
- **Uma permissão por ação:** o formulário usa `account.people.update`. O servidor compara cada campo alterado com a classe dele e aplica a regra da classe. Um envio forjado que altere campo não autorizado é rejeitado com 403.

## Classes

### Identificação central

- **Campos atuais (`people`):** `name` (nome ou razão social), `person_type`, `document` (CPF ou CNPJ), `trade_name`, `state_registration`, `municipal_registration`.
- **Proprietário:** o tenant.
- **Visualização:** `account.people.view` com qualquer atribuição ativa na conta.
- **Edição:** `account.people.update` cobrindo **todas** as empresas com as quais a pessoa tem relação ativa. Sem relação, qualquer atribuição com a permissão. Fora disso, só escopo tenant.
- **Efeitos:** muda a identificação usada por todas as empresas, inclusive em documentos e históricos futuros. Não altera a identidade de login.
- **Auditoria:** evento do cadastro central, nó tenant (sem `company_id`).
- **Várias empresas:** quem não cobre todas vê os campos somente para leitura, com a explicação "Alterável por quem atende todas as empresas desta pessoa ou pela administração da conta".

### Contatos centrais

- **Campos atuais (`people`):** `email`, `phone`, `whatsapp`, `additional_phones`, `additional_emails`, `website`, `contact_name` (pessoa de contato). Também as etiquetas convencionais (`person_tag`, exceto Cliente, Fornecedor e Usuário).
- **Proprietário:** o tenant.
- **Visualização:** `account.people.view` com qualquer atribuição ativa na conta.
- **Edição:** `account.people.update` cobrindo **pelo menos uma** empresa relacionada. Sem relação, qualquer atribuição com a permissão.
- **Efeitos:** vale para todas as empresas. Não altera contatos das relações nem a identidade de login (o `email` da pessoa não é o e-mail de acesso).
- **Auditoria:** evento do cadastro central, nó tenant.
- **Várias empresas:** quando a pessoa tem relação com mais de uma empresa, o formulário mostra junto da seção o aviso "Esta alteração vale para todas as empresas que atendem esta pessoa".

`contact_name` continua central. Se a prática mostrar que a pessoa de contato varia por empresa, o campo equivalente será criado na relação, sem mover o atual (pendência registrada no plano).

### Endereços

- **Campos atuais (`people.address`):** endereço principal (`postal_code`, `street`, `number`, `complement`, `neighborhood`, `city`, `state`).
- **Regra:** mesma regra dos contatos centrais.
- **Endereços específicos:** endereços de entrega e de cobrança usados por uma empresa são dados da relação. Eles não existem hoje e, quando forem criados, ficam na relação.
- **Componentes:** todo campo de endereço reutiliza máscaras, ícones e grupos do cadastro da conta.

### Dados comerciais por empresa

- **Campos atuais:** `customers.active` e `suppliers.active` (situação da relação). Cada tabela admite uma relação por combinação de empresa e pessoa. A situação de cada tipo é independente.
- **Campos futuros:** condições de pagamento, limite de crédito, tabela de preço, observações comerciais e afins.
- **Proprietário:** a empresa da relação.
- **Visualização:** `account.customers.view` ou `account.suppliers.view` sobre **aquela** empresa.
- **Edição:** a permissão da ação disponível (`create`, `activate`, `deactivate`) sobre aquela empresa. Campos comerciais futuros terão sua própria permissão de edição.
- **Efeitos:** só a relação daquela empresa. Outras empresas não veem nem herdam o dado.
- **Auditoria:** evento com `company_id` da relação.
- **Várias empresas:** cada empresa tem a própria linha, com histórico separado.

### Contatos específicos da relação

- **Campos atuais:** nenhum.
- **Campos futuros:** contato comercial, e-mail e telefone de cobrança ou de pedidos, endereço de entrega ou cobrança.
- **Regra:** mesmas regras dos dados comerciais por empresa.
- **Componentes:** os campos reutilizam `ContactFields` e os grupos de endereço de Pessoas. Não há duplicação dos dados centrais: a relação guarda só o que é específico.

### Dados operacionais restritos

- **Campos atuais:** nenhum ligado à pessoa.
- **Futuros:** histórico de vendas, títulos financeiros, ordens de serviço, créditos.
- **Proprietário:** a empresa ou o estabelecimento do registro.
- **Visualização e edição:** permissão do módulo sobre o nó do registro.
- **Leitura do cadastro:** quem lê o cadastro de Pessoas não vê esses dados. A busca de Pessoas não procura neles.
- **Auditoria:** com `company_id` e, quando houver, `establishment_id`.

### Acesso de usuário

A etiqueta Usuário, o vínculo e as atribuições seguem [Identidade e vínculos](identidade-e-vinculos.md). A etiqueta Usuário aparece para quem tem `account.users.list` em algum escopo. Cargos e escopos só aparecem nas telas de acesso.

## Etiquetas comerciais

- A partir da E07, Cliente e Fornecedor são derivadas das relações e calculadas para quem está vendo. Cliente aparece somente se houver relação ativa numa empresa onde o leitor tem `account.customers.view`. Fornecedor segue a mesma regra.
- Os filtros Clientes e Fornecedores da listagem de Pessoas consideram relações ativas nas empresas em que o leitor possui as permissões de listar e visualizar o papel.
- `person_tag` guarda etiquetas convencionais e Usuário. Cliente e Fornecedor continuam no catálogo como etiquetas especiais, com vínculo calculado a partir das relações ativas e visíveis.

Etiquetas convencionais de Clientes e Fornecedores pertencem à relação da empresa. O catálogo pertence à conta, e o módulo da etiqueta determina em qual tipo de relação ela pode ser selecionada. As etiquetas especiais Cliente e Fornecedor continuam derivadas e aparecem fixas no formulário comercial. A seleção específica de uma relação permanece nela, sem passar para a Pessoa ou para a relação de outro papel.

## Consultas e vínculo

- A Área de Trabalho limita as listagens comerciais às empresas distintas das unidades selecionadas com permissão para a ação. As consultas de Pessoas continuam administrativas.
- Uma escrita comercial identifica uma empresa. Em Clientes ou Fornecedores, a URL da escrita contém o código da empresa. No formulário de Pessoas, as escolhas são indexadas pela empresa.
- Quem pode cadastrar uma relação pode buscar um documento completo na empresa escolhida. O tipo de pessoa é obrigatório no corpo da requisição e determina a validação e a busca por CPF ou CNPJ. A resposta identifica a Pessoa central e informa a situação da relação daquele tipo somente na empresa escolhida.
- Para vincular uma Pessoa já presente em outra empresa, reutilize o identificador retornado pela busca. A situação de cada relação é independente. Empresas inativas preservam as relações e bloqueiam alterações comerciais.

## Formulário

Ao editar dados centrais de contato ou endereço, a tela sempre informa que eles pertencem ao cadastro compartilhado da conta, com o texto "Estes dados pertencem ao cadastro compartilhado da conta." Isso vale também para contas com uma empresa só. Dados comerciais específicos continuam na relação comercial da empresa correspondente, e nenhuma rotina sincroniza dados entre empresas.

Com mais de uma empresa ativa na conta, o formulário de pessoa separa as seções:

| Seção | Título | Texto de apoio |
| --- | --- | --- |
| Identificação, contatos e endereço centrais | **Dados centrais** | "Estes dados pertencem ao cadastro compartilhado da conta e valem para todas as empresas." |
| Relação comercial | **Dados desta empresa: {nome fantasia da empresa}** | "Valem somente para esta empresa. As outras empresas não veem estes dados." |

- A relação de cada empresa aparece apenas para quem pode visualizá-la naquela empresa.
- O usuário escolhe em quais empresas autorizadas a relação existe.
- Com uma empresa só, o formulário mantém as seções atuais, sem esses títulos, mas com o aviso de cadastro compartilhado nos contatos e no endereço. A escolha de empresa é implícita.
- Os rótulos de ação seguem o AGENTS.md: **Salvar** e **Cancelar**.

## Testes obrigatórios

Cenários 21, 42 e 43 do plano: pessoa cliente em duas empresas com dados separados; contato central editável e identificação negada (403 com envio forjado) quando há relação com empresa não coberta; etiquetas, filtros e visualização sem revelar relação com empresa não autorizada. Toda edição confere que nenhum dado de outra relação mudou.

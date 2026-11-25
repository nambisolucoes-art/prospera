# Pessoas e contatos

Política oficial da DA-25. A tabela `people` guarda a identificação compartilhada do tenant. Clientes, Fornecedores e Usuários são as entradas do produto para seus respectivos cadastros. A busca pelo CPF ou CNPJ completo nos módulos comerciais pode reaproveitar o mesmo registro e registrar um segundo papel. A etiqueta especial deriva do papel da relação, cujo escopo é a empresa. Cada empresa mantém, na relação comercial, os dados e contatos que só interessam a ela. Uma alteração local nunca modifica, em silêncio, dados de outra empresa. Contexto geral em [Contexto organizacional](contexto-organizacional.md).

Esta política classifica os campos existentes e reserva o lugar dos campos futuros. Ela não move campos nem tabelas: os campos atuais de `people` continuam onde estão, e qualquer mudança de classe exige decisão própria, registrada no plano.

## Princípios

- **Duas camadas:** dados centrais (tabela `people`, nó tenant ou alvo `person`) e dados da relação comercial (`customers` e `suppliers`, nó `company`).
- **Sem sincronização:** nenhuma rotina copia, sincroniza ou propaga dados entre os dados centrais e as relações, nem entre relações de empresas diferentes.
- **Alteração explícita:** quem altera um dado central vê que ele vale para todas as empresas. Quem altera um dado da relação altera só a própria empresa.
- **Contato de uma empresa só:** um contato que interessa a uma única empresa (por exemplo, o e-mail de cobrança da Empresa A) é gravado na relação daquela empresa, nunca no cadastro central.
- **Uma permissão por ação:** os dados centrais são alterados pela ficha de Clientes ou Fornecedores, que exige a permissão de editar do papel na empresa (`account.customers.update` ou `account.suppliers.update`) e aplica `account.people.update` aos campos centrais. O servidor compara cada campo alterado com a classe dele e aplica a regra da classe. Um envio forjado que altere campo não autorizado é rejeitado com 403. Não existe tela nem rota própria de Pessoas.

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

- **Campos atuais:** nenhum em Clientes e Fornecedores. Em Colaboradores, `work_email`, `work_phone` e `work_whatsapp` na relação e `work_contacts` por lotação (contatos profissionais, nunca copiados de Pessoas nem publicados a partir dela).
- **Campos futuros:** contato comercial, e-mail e telefone de cobrança ou de pedidos, endereço de entrega ou cobrança.
- **Regra:** mesmas regras dos dados comerciais por empresa.
- **Componentes:** os campos reutilizam `ContactFields` e os grupos de endereço de Pessoas. Não há duplicação dos dados centrais: a relação guarda só o que é específico.

### Dados operacionais restritos

- **Campos atuais:** nenhum ligado à pessoa.
- **Futuros:** histórico de vendas, títulos financeiros, ordens de serviço, créditos.
- **Proprietário:** a empresa ou o estabelecimento do registro.
- **Visualização e edição:** permissão do módulo sobre o nó do registro.
- **Leitura do cadastro:** quem lê os dados centrais pela ficha comercial não vê esses dados. As buscas de Clientes e Fornecedores não procuram neles.
- **Auditoria:** com `company_id` e, quando houver, `establishment_id`.

### Dados do colaborador

- **Campos:** `employees` (situação e contatos profissionais), `employee_assignments` (função, departamento, período e contatos da unidade) e `employee_public_profiles` (autorização e apresentação públicas, sem contato).
- **Proprietário:** a empresa. Edição pelas permissões `account.employees.*` sobre o nó. Dados centrais seguem a classe de identificação e contatos centrais acima.
- **Efeitos:** só a relação daquela empresa. Nada é sincronizado entre empresas nem com a Pessoa.
- **Auditoria:** evento com `company_id` e, na lotação, `establishment_id`.

### Acesso de usuário

A etiqueta Usuário, o vínculo e as atribuições seguem [Identidade e vínculos](identidade-e-vinculos.md). A etiqueta Usuário aparece para quem tem `account.users.list` em algum escopo. Cargos e escopos só aparecem nas telas de acesso.

## Etiquetas comerciais

- A partir da E07, Cliente e Fornecedor são derivadas das relações e calculadas para quem está vendo. Cliente aparece somente se houver relação ativa numa empresa onde o leitor tem `account.customers.view`. Fornecedor segue a mesma regra.
- As listagens de Clientes e Fornecedores mostram as relações das empresas em que o leitor pode listar o papel; abrir a ficha exige visualizar o papel na mesma empresa.
- `person_tag` guarda a etiqueta Usuário e etiquetas convencionais anteriores do módulo Pessoas. Pessoas é somente uma estrutura central interna: não tem cadastro avulso, menu ou etiquetas próprias no produto. O filtro e o cadastro de Etiquetas oferecem somente Clientes e Fornecedores. Vínculos convencionais anteriores permanecem preservados, sem permitir novos cadastros nessa classificação. Cliente e Fornecedor continuam no catálogo como etiquetas especiais, com vínculo calculado a partir das relações ativas e visíveis.

**Colaborador** é um terceiro papel derivado, com a mesma lógica: relação por empresa (`employees`), etiqueta especial `employee` calculada da relação ativa e visível a quem tem `account.employees.view` sobre a empresa. A etiqueta especial não é atribuída à mão. O módulo **Colaboradores** tem etiquetas convencionais próprias (`employees_tags`), escolhidas por vínculo, como em Clientes e Fornecedores. A afirmação acima de que o filtro e o cadastro de Etiquetas oferecem somente Clientes e Fornecedores vale para etiquetas **convencionais**. A etiqueta de Colaborador aparece na listagem de Etiquetas como especial e somente leitura, sem estrela fora dela. Pessoas continua sem cadastro avulso, menu ou etiquetas próprias. Contexto em [Institucional, equipe e sites](institucional.md).

Etiquetas convencionais de Clientes e Fornecedores pertencem à relação da empresa. O catálogo pertence à conta, e o módulo da etiqueta determina em qual tipo de relação ela pode ser selecionada. As etiquetas especiais Cliente e Fornecedor continuam derivadas e aparecem fixas no formulário comercial. A seleção específica de uma relação permanece nela, sem passar para a Pessoa ou para a relação de outro papel.

## Consultas e vínculo

- A Área de Trabalho limita as listagens comerciais às empresas distintas das unidades selecionadas com permissão para a ação.
- Uma escrita comercial identifica uma empresa: a URL da escrita contém o código da empresa. Empresas adicionais do mesmo cadastro são indicadas pelo código em `add_companies` e `remove_companies`, e cada uma exige a permissão correspondente.
- Quem pode cadastrar uma relação pode buscar um documento completo na empresa escolhida. O tipo de pessoa é obrigatório no corpo da requisição e determina a validação e a busca por CPF ou CNPJ. A resposta identifica a Pessoa central e informa a situação da relação daquele tipo somente na empresa escolhida.
- Para vincular uma Pessoa já presente em outra empresa, reutilize o identificador retornado pela busca. A situação de cada relação é independente. Empresas inativas preservam as relações e bloqueiam alterações comerciais.

## Formulário

As fichas de Clientes e Fornecedores são as entradas da Pessoa. Ao editar dados centrais de identificação, contato ou endereço, a ficha informa que as alterações valem para o cadastro compartilhado da conta. Dados comerciais específicos continuam na relação comercial da empresa correspondente, e nenhuma rotina sincroniza dados entre empresas.

- A ficha abre na empresa da linha e mostra os dados centrais somente a quem tem `account.people.view`.
- Identificação e contatos centrais seguem a autoridade das classes acima; os campos sem autoridade ficam somente para leitura.
- Em **Empresas do cadastro**, o usuário marca ou desmarca as empresas autorizadas em que a relação existe. Com uma empresa só, a escolha é implícita.
- A situação da relação é alterada na ficha ou pelas ações Ativar e Inativar da listagem, sempre com a permissão própria.
- O acesso de usuário é criado e suspenso em Usuários, nunca pela ficha comercial.
- Os rótulos de ação seguem o AGENTS.md: **Salvar** e **Cancelar**.

## Testes obrigatórios

Cenários 21, 42 e 43 do plano: pessoa cliente em duas empresas com dados separados; contato central editável e identificação negada (403 com envio forjado) quando há relação com empresa não coberta; etiquetas, filtros e visualização sem revelar relação com empresa não autorizada. Toda edição confere que nenhum dado de outra relação mudou.

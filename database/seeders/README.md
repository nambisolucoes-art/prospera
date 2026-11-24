# Cenário de desenvolvimento (make seed)

`make seed` apaga o banco local, roda as migrations e cria o cenário abaixo. Pede confirmação; para
automatizar, use `make seed CONFIRM=sim`. O seed se recusa a rodar com `APP_ENV=production`.

No `make up`, o código fica dentro da imagem: depois de alterar seeders ou migrations, rode `make up`
antes de `make seed`, senão o container executa a versão anterior. No `make dev`, o código é montado e
não precisa de rebuild.

Todos os usuários usam a senha **Prospera@2026**. Os dados são sintéticos: CPFs e CNPJs têm dígitos
verificadores válidos, mas não pertencem a pessoas ou empresas reais conhecidas, e os e-mails usam
o domínio reservado `.test`. Nenhum e-mail é enviado.

## Organização

Conta **Grupo Horizonte** (`Grupo Horizonte Comércio Ltda`).

| Empresa | Documento | Estabelecimentos |
| --- | --- | --- |
| Horizonte Supermercados | CNPJ, raiz 27184390 | Supermercado Centro (matriz), Loja Brumado (filial), Loja Guanambi (filial), Centro de Distribuição (sem inscrição) |
| Horizonte Farma | CNPJ, raiz 31975264 | Farmácia Centro (matriz), Farmácia Shopping (filial), Farmácia Candeias (filial) |
| Horizonte Atacado | CNPJ, raiz 18642093 | Atacado Matriz (matriz), Depósito Anexo (sem inscrição) |
| Mercearia Dona Clara | CPF (empresa individual) | Unidade principal sem inscrição |

## Usuários

| E-mail | Perfil |
| --- | --- |
| admin@prospera.test | Administrador da plataforma SaaS, sem conta |
| ana.ribeiro@horizonte.test | Administradora integral da conta |
| henrique.alves@horizonte.test | Segundo administrador integral |
| bruno.carvalho@horizonte.test | Gerente de empresa no Horizonte Supermercados |
| carla.souza@horizonte.test | Gerente de loja em três estabelecimentos: Loja Brumado, Loja Guanambi e Centro de Distribuição |
| diego.lima@horizonte.test | Operador de caixa em estabelecimentos de empresas diferentes: Loja Brumado e Farmácia Shopping |
| elisa.martins@horizonte.test | Comprador no Supermercados e no Atacado; Atendimento ao cliente na Farma |
| felipe.rocha@horizonte.test | Auditor interno em toda a conta |
| gabriela.nunes@horizonte.test | Coordenador farmacêutico (cargo restrito à Farma) e Gerente de loja na Farmácia Candeias |
| joana.teixeira@horizonte.test | Atendimento ao cliente no Supermercados e no Atacado; Operador de caixa na Loja Guanambi |
| igor.pereira@horizonte.test | Operador de caixa na Farmácia Candeias, com vínculo **suspenso** (não entra na conta) |

## Cargos, etiquetas e relações comerciais

- Cargos com várias permissões cada: Gerente de empresa, Gerente de loja, Operador de caixa, Comprador,
  Atendimento ao cliente, Auditor interno e Coordenador farmacêutico (disponível só na Horizonte Farma),
  além dos cargos de sistema.
- Etiquetas comerciais de Clientes (Cliente VIP, Fidelidade, Compra no atacado, Inadimplente) e de
  Fornecedores (Fornecedor homologado, Entrega rápida, Importado, Crítico para o estoque).
- 13 relações de cliente e 11 de fornecedor, em todas as empresas. Uma cliente atende o Supermercados e a
  Farma com a mesma Pessoa; o Restaurante Sabor da Serra é cliente do Supermercados e do Atacado e também
  fornecedor do Atacado, sem duplicar o cadastro central.

## Como os seeders funcionam

Cada domínio tem seu seeder em `database/seeders/{Dominio}`. `DatabaseSeeder` só define a ordem. Os
seeders não recebem objetos uns dos outros: encontram o que precisam no banco por chaves naturais de
`Support/SeedCatalog.php` (documento, raiz do CNPJ, código da unidade, nome do cargo, e-mail).

Os dados passam pelas Actions e classes Data da aplicação, com a mesma validação, autorização e
auditoria da interface. A conta é criada pelo administrador da plataforma; o restante, pela
administradora da conta, que aparece como autora no histórico. O trait `Support/SeedsAccountScenario.php`
assume o operador e entra no contexto da conta. Resolva cada Action com `app()` depois de assumir o
operador, nunca por injeção no `run()`, porque várias guardam serviços por requisição no construtor.

Para acrescentar um módulo: crie o seeder na pasta do domínio, use as Actions do módulo, registre as
chaves naturais novas no catálogo e inclua a classe em `DatabaseSeeder` depois dos módulos de que ela
depende. Um seeder isolado pode ser executado com `make art ARGS="db:seed --class=..."` quando os
anteriores já tiverem rodado. `tests/Feature/DevelopmentSeedTest.php` verifica o cenário na suíte.

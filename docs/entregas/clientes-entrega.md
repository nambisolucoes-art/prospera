# Etiqueta especial Cliente

Cliente é um papel da pessoa na conta. O domínio Clientes guarda somente account_id, person_id e active, sem duplicar nome, CPF/CNPJ, inscrições, endereço, telefones ou e-mails. Cliente e Fornecedor coexistem, com ou sem acesso de Usuário.

Tag::customerTag cria a definição protegida em novas contas pelo fluxo de criação de usuário já utilizado pelas outras etiquetas especiais. A migração cria a definição nas contas existentes. O gerador de etiquetas especiais de Cliente e Fornecedor é compartilhado, com tratamento de conflito de nome. Etiquetas convencionais existentes são preservadas, inclusive seus vínculos; uma definição especial recebe um nome distinto se necessário. Não há conversão automática de categorias antigas.

Pessoas reutiliza TagSelect, TagLabel, BusinessForm, BusinessList, SelectControl, StatusLabel e PreviewSection. O filtro Tipo de cadastro oferece Clientes e Fornecedores conforme as permissões; busca e paginação preservam o papel selecionado. A estrela especial permanece exclusiva da listagem de Etiquetas. Retirar Cliente mostra o aviso abaixo do seletor, salva a inativação e preserva pessoa, fornecedor, acesso e histórico. Selecionar novamente reativa o mesmo vínculo.

## Permissões e escopo

As ações account.customers.create, account.customers.list, account.customers.view, account.customers.activate e account.customers.deactivate são independentes. Cadastro e edição dos dados comuns continuam autorizados por Pessoas. As operações do papel são autorizadas no servidor dentro do formulário. Não há campos próprios que exijam uma ação adicional de edição do perfil de Cliente.

O catálogo foi sincronizado por permissions:sync. Consultas, validação das etiquetas e alterações limitam o acesso à conta. A inspeção Somente leitura bloqueia gravações. A interface oculta ou trava as ações indisponíveis conforme auth.permissions.

## Auditoria

Customer implementa Auditable e AuditsChanges. Cadastro e alterações de active geram eventos customer.created e customer.updated com autor real e conta. Pessoa, vínculos de etiquetas e cliente são gravados na mesma AuditLog::transaction. Mudanças sem efeito não geram histórico adicional e falhas de autorização revertem todas as escritas. Inspeções preservam administrador SaaS e usuário representado separadamente. O formato de tag.system_key inclui Cliente na leitura, preservando o código customer no histórico original. CPF/CNPJ e credenciais não são copiados para Cliente nem para seus eventos.

## Ajuda, tour e validação

O artigo Clientes está integrado à Central de Ajuda por permissão. Pessoas, Etiquetas e README foram revisados. Os roteiros Clientes e Pessoas documentam o filtro e a etiqueta. O tour das telas de Pessoas explica os papéis e mantém BusinessListTour e PageHeaderTour, sem executor no modal ou alteração de dados na demonstração.

TypeScript, arquitetura, PHPStan e Pint dos arquivos PHP alterados aprovados. Suíte completa em SQLite na memória: 108 testes, 962 asserções aprovados. Audit:check aprovado com oito modelos. Docker reconstruído com os serviços saudáveis e migração aplicada.

Chromium em 1440, 390 e 320 px: cadastro simultâneo Cliente e Fornecedor, ausência de acesso de usuário e campos duplicados, filtro Clientes preservando a busca, visualização de ambos os papéis, aviso de retirada, cancelar preservando vínculo, inativar, reabrir e reativar, tour de Etiquetas e fechamento por Esc preservando formulário. Sem rolagem horizontal nessas larguras. Listagem de Etiquetas e artigo Clientes acessíveis. A conta e os usuários temporários usados na validação foram desativados ao concluir.

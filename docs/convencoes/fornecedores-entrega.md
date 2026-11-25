# Pessoas e o papel de fornecedor

Identificação, endereço principal e contatos pertencem à pessoa, independentemente de etiquetas. Tipo de pessoa, nome ou razão social, CPF/CNPJ, nome fantasia, inscrições, endereço, telefone, WhatsApp, e-mail, contato e site são preenchidos uma única vez. A etiqueta especial Fornecedor representa apenas um papel ativo ou inativo. Não há campos exclusivos de fornecedor no cadastro básico atual.

## Estrutura e migração

Person contém os dados comuns. CreatePersonData e UpdatePersonData normalizam e validam CPF/CNPJ (inclusive alfanumérico), unicidade por conta, tipo, inscrições, site e endereço. CreatePersonAction e UpdatePersonAction aplicam CreatePersonDTO e UpdatePersonDTO e executam as gravações na mesma transação da classificação e das etiquetas.

Supplier guarda somente account_id, person_id e active. Não possui cópia de documento, contato ou endereço. ChangeSupplierRelationAction verifica cadastrar, ativar e inativar o papel. Listar e visualizar a classificação têm permissões específicas; editar a identidade usa Pessoas. A permissão experimental de editar dados exclusivos de fornecedor foi removida do catálogo com permissions:sync --prune, após confirmar que era a única definição obsoleta.

A migração 000008 copia os dados existentes da primeira estrutura de fornecedor para sua pessoa e remove as colunas duplicadas. A migração não reescreve eventos históricos. Nome semelhante em etiqueta comum não é convertido automaticamente em papel de fornecedor.

## Interface e padrões

PersonIdentityFields, PersonContactFields e AddressFields compõem o formulário seguindo a ordem usada no cadastro da conta: identificação, endereço, contatos, etiquetas e acesso quando aplicável. Telefone e WhatsApp usam MaskedField com os mesmos grupos de campo e ícones fa-phone e fa-whatsapp da conta; e-mail usa o mesmo ícone de envelope. CPF/CNPJ, telefone e CEP são apresentados com formatação.

Adicionar ou retirar Fornecedor não cria nem esconde campos comuns. Outras etiquetas reutilizam os mesmos dados. O modal apresenta CPF/CNPJ e endereço uma única vez, e a situação do fornecedor separadamente. A estrela especial permanece apenas na listagem de Etiquetas. Salvar, Cancelar, badges e seletores seguem os componentes compartilhados.

## Segurança, auditoria e orientação

Pessoa e fornecedor permanecem limitados à mesma conta. Toda escrita é autorizada no servidor, inclusive a atribuição do papel dentro de Pessoas. Inativar fornecedor preserva os dados comuns e as demais etiquetas. Reativar reutiliza o mesmo vínculo. Nenhuma classificação de fornecedor concede acesso de usuário.

Os dados comuns são auditados em Pessoa; a situação do papel é auditada em Fornecedor; o pivô de etiquetas é registrado explicitamente. Todos compartilham a transação e a autoria real, inclusive em inspeção. CPF/CNPJ completo nunca entra no histórico técnico ou formatado.

Ajuda de Pessoas, Fornecedores e Etiquetas, pesquisa de campos, README, AGENTS.md e roteiros foram revisados. Os passos de identificação, endereço e contatos são gerais, sem dependência da etiqueta. O filtro de fornecedores aparece no grupo Filtros. Não há executor ou atalho de tutorial em modal. Integrações existentes utilizadas, sem pendência de integração.

## Validação final

96 testes e 812 asserções aprovados exclusivamente em SQLite em memória. A suíte cobre pessoa com CPF sem fornecedor, múltiplas etiquetas com uma única identidade, unicidade entre pessoas da mesma conta, escopo entre contas, CPF/CNPJ alfanumérico e inválido, inativação/reativação sem perda de dados comuns, permissões, autoria real em inspeção, dados protegidos e rollback. Teste da migração reconstrói a estrutura anterior e confirma a transferência de documento, tipo, nome fantasia, WhatsApp e endereço sem reescrever o histórico.

PHPStan, Pint nos 13 arquivos PHP do ajuste, arquitetura, tipos, build e audit:check aprovados. Catálogo sincronizado com 63 ações e retirada somente da permissão obsoleta account.suppliers.update.

Navegador em 1440, 390 e 320 px: pessoa comum com CPF e endereço sem etiqueta, erro de CPF inválido, gravação, reabertura, Cliente e Fornecedor simultâneos com um único CPF/endereço, modal, tour de contatos, retirada de Fornecedor preservando Cliente e todos os dados, telefone e WhatsApp com ícones e máscaras do padrão da conta. CPF, telefone, WhatsApp e CEP formatados na visualização. Dados cadastrados antes da migração conferidos na aplicação. Formulário da conta preservou seus nomes e campos de endereço. Sem rolagem horizontal indevida. Capturas temporárias em /tmp/prospera-person-contact-*.png e /tmp/prospera-person-common-final-*.png.

## Inscrições e avisos do campo Etiquetas

Inscrição estadual e municipal são opcionais para PF e PJ, inclusive na visualização, e não são limpas ao trocar o tipo. A regra que limpava inscrição estadual de pessoa física foi removida. O teste de regressão percorre PF, PJ e PF novamente, conferindo persistência e retorno da API.

TagSelect oferece o slot notice dentro da própria área do campo, com aria-describedby e status acessível. Os avisos de retirada de Usuário ou Fornecedor usam essa área, a 4 px do controle, alinhados com sua borda e com a cor de validação. Foi removido o texto explicativo repetido de Fornecedor. Ajuda e tour revisados.

Dez testes de fornecedores e identidade, com 133 asserções em SQLite em memória, PHPStan e audit:check aprovados. Navegador em 1440, 390 e 320 px: inscrições disponíveis e persistidas para ambos os tipos; aviso medido a 4 px, alinhado e vermelho; ausência do texto removido; sem rolagem horizontal da página. Tipos, arquitetura e build aprovados.

# Levantamento do cadastro de fornecedores

## Base consultada

O [cadastro de fornecedores do Compras.gov.br](https://www.comprasnet.gov.br/servicos_fornecedores.htm) identifica CNPJ, razão social, endereço, telefone e e-mail. O [tutorial oficial do SICAF](https://tutoriais.comprasgovernamentais.gov.br/sicaf/html/demo_8.html) distingue pessoa física de jurídica e CPF de CNPJ. O [tutorial do Serpro](https://demonstra.serpro.gov.br/tutoriais/sicaf_fornecedor/html/demo_11.html) organiza dados complementares, contato e endereço. Essas fontes ajudam a identificar campos usuais; as exigências de contratação pública não foram transferidas para o cadastro privado do Prospera.

O [manual de dígitos verificadores da Receita Federal](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf) mantém módulo 11 e usa ASCII menos 48 para caracteres do CNPJ alfanumérico. A implementação aceita os formatos numérico e alfanumérico e mantém os dois dígitos finais numéricos.

## Decisão para o Prospera

Os campos cadastrais encontrados na pesquisa pertencem à pessoa, não ao papel de fornecedor. O mesmo CPF/CNPJ, nome, endereço e contato podem ser usados quando essa pessoa também é cliente ou usuário. A implementação mantém uma única identidade em Pessoas e um vínculo de fornecedor separado, com sua situação.

| Dado | Responsável | Critério |
| --- | --- | --- |
| Tipo de pessoa, nome ou razão social, CPF/CNPJ | Pessoa | Tipo e nome obrigatórios; documento opcional, válido e único por conta quando preenchido. |
| Nome fantasia | Pessoa jurídica | Opcional e não repetido por papel. |
| Inscrição estadual e inscrição municipal | Pessoa física ou jurídica | Opcionais, preservadas ao trocar o tipo, sem validação fiscal externa. |
| Pessoa de contato, telefone, WhatsApp, e-mail e site | Pessoa | Contatos compartilhados. |
| Endereço principal | Pessoa | Opcional e compartilhado, sem cópia em fornecedor. |
| Papel e situação de fornecedor | Fornecedor | Vínculo ativo ou inativo com a mesma pessoa e conta. |

Não foram encontrados campos exclusivos que precisem ser obrigatórios em um cadastro básico de fornecedor no escopo atual. Informações de compras, condições comerciais e pagamentos pertencem aos respectivos fluxos quando existirem. Dados bancários, Pix, certidões e anexos não foram incluídos. Um endereço específico futuro deverá ter um tipo (entrega, cobrança ou atendimento) vinculado à pessoa, sem duplicar a identidade.

As exigências de contratação pública não foram transferidas para o cadastro privado do Prospera. A classificação dos campos e sua obrigatoriedade são decisões deste produto. CPF e CNPJ são conferidos por dígitos verificadores, sem consulta de existência à Receita Federal.

## Consistência e proteção

Uma pessoa é cadastrada uma única vez e recebe seus papéis por etiquetas. Fornecedor é uma definição especial provisionada por conta; etiqueta comum com nome semelhante não ativa o vínculo. Inativação e reativação reutilizam o mesmo registro, preservam os dados comuns e não criam usuário de acesso.

Pessoas autoriza e audita os campos comuns. Fornecedores autoriza e audita a atribuição e situação do vínculo. Todas as escritas são atômicas. CPF/CNPJ completo permanece fora do histórico. A migração move os dados cadastrados na primeira versão de fornecedor para a pessoa correspondente e remove as colunas duplicadas.

# Visual da Central de Ajuda

Referências: extra-search-result.html do Ubold local e componentes Help/List do GNAI2. A Central de Ajuda usa busca centralizada, título com termo e quantidade de artigos, abas Todos e categorias com contadores, abas ligadas diretamente ao painel branco com borda, formando um único contorno no padrão Visdom. A busca fica acima desse painel e os resultados são separados por linhas. A aba ativa usa o azul primário do tema. Cada resultado apresenta título com link, caminho verde e resumo. O artigo aberto mantém a composição de leitura existente.

SearchInput recebeu suporte opcional a valor controlado, rótulo acessível e estado de busca. SearchResults recebeu busca, cabeçalho, resumo e abas por propriedades, preservando a demonstração original quando essas opções não são fornecidas. SearchResultItem aceita título renderizado para permitir navegação pela API ou título sem link conforme a permissão. Tabs e Badge são reutilizados. A variante compartilhada framed de Tabs conecta o contorno da aba ativa à borda do painel; SearchResults recebe essa variante por propriedade, preservando a demonstração original. Não foram duplicados controles de busca, badges ou abas. Os estilos compartilhados pertencem aos componentes e usam os tokens do Bold.

As categorias e contadores derivam apenas dos artigos autorizados retornados pelo servidor. Não há categorias fixas com contagens fictícias. Uma nova pesquisa retorna à aba Todos. Categorias e contadores ficam visíveis no celular, com rolagem horizontal restrita à faixa de abas e sem rolagem horizontal da página. A busca continua enviando o termo ao servidor, preservando a pesquisa em títulos, resumos, palavras-chave e conteúdo e os links diretos às seções.

O novo artigo Central de Ajuda explica os controles e está integrado ao HelpCatalog com a permissão existente self.help.list. Visualizar continua exigindo self.help.view na rota. Os demais artigos mantêm a separação entre plataforma e conta e suas permissões. Não há alteração de dados de negócio, acesso ou catálogo de permissões neste ajuste de apresentação e leitura. Não há nova operação auditável ou integração pendente.

O tour usa os alvos help-search, help-categories e help-results, após PageHeaderTour. Explica busca, abas e resultados sem trocar a categoria ou submeter a busca. Preserva os valores digitados e a aba ao sair por Esc. Roteiro e artigo atualizados.

## Validação

Arquitetura e TypeScript aprovados. Pint aprovado nos arquivos PHP alterados. Os nove testes de AuditAndGuidanceTest passaram com 123 asserções em SQLite em memória, incluindo contexto, permissões, sanitização do HTML e links de seção. Docker reconstruído com serviços saudáveis.

Chromium em 1440, 390 e 320 px, nos contextos de conta e plataforma: visualização inicial, contagem total, troca de categoria, preservação de termo digitado e aba ao abrir e fechar o tour, busca por Enter, retorno à aba Todos, abertura de artigo com âncora, volta à pesquisa, busca sem resultados e limpeza do termo. Sem rolagem horizontal nessas larguras. Demonstração compartilhada extra-search-result.html conferida com resultados e aba Usuários preservados. Usuários e conta temporários de validação são desativados ao concluir.

A revisão do contêiner mantém a busca fora do painel e une aba e conteúdo em uma única borda. Não há verde escuro no texto das abas da Ajuda.

Variante framed conferida no Chromium em 1440, 390 e 320 px, nos contextos de conta e plataforma. A aba ativa sobrepõe em um pixel o topo do painel, unindo suas bordas; o painel mantém borda lateral e inferior. Confirmados texto azul, busca acima do contêiner, seleção das categorias e rolagem da faixa até a última aba no celular, sem rolagem horizontal da página. Busca, contadores, tour, âncoras e estado vazio aprovados. Demonstração original do Bold preservada. Arquitetura, TypeScript e reconstrução Docker aprovados.

Botão Buscar da Ajuda alterado para o azul primário, com texto e ícone brancos, pelo parâmetro opcional buttonTone de SearchInput. O padrão dos demais consumidores foi preservado. Revisão do tour sem mudança de alvos ou sequência. Verificação no Chromium em 1440, 390 e 320 px: cores computadas, texto legível, busca pelo botão e ausência de rolagem horizontal. Arquitetura, TypeScript e reconstrução Docker aprovados.

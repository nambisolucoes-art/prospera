# Títulos de página no padrão do tema

O `PageHeader` exibe o título em maiúsculas, com peso 700 e uma listra de 4 px à esquerda na cor `--prospera-title-accent` (`#5d9bec`). A transformação é apenas visual: o texto continua com a grafia original para leitores de tela e para o tour.

Abaixo de 480 px o título usa 17 px e recuo de 10 px. Nas telas com trilha à direita, a quebra ocorre somente entre palavras. O alvo `page-title` do `PageHeaderTour` permanece no mesmo elemento e na mesma posição, por isso os passos e os artigos de ajuda não mudaram.

## Validação

Chromium em 1440, 390 e 320 px: Etiquetas, Cadastrar pessoa e Minha conta, com uma conta de teste em ambiente isolado. Sem rolagem horizontal; em 320 px, Cadastrar pessoa quebra em duas linhas inteiras ao lado da trilha. Arquitetura, tipos e build aprovados.

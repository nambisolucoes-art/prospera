# Central de Ajuda

A Central de Ajuda explica como operar o Prospera. O [tour guiado](tour-guiado.md) mostra onde ficam os controles e oferece um caminho para a explicação completa.

## Conteúdo

- Organize os artigos em Markdown, um por fluxo, com nome curto em minúsculas, sem acentos e separado por hífens. Listagem, cadastro e edição podem compartilhar um artigo ao tratar do mesmo fluxo.
- Registre título, resumo, categoria e palavras-chave. Inclua sinônimos, siglas e frases que alguém usaria para procurar a resposta. Use títulos de seção por tarefa, com âncoras para abrir o trecho correspondente.
- Escreva em português do Brasil e use os nomes que aparecem na tela. Documente quem pode executar cada ação, seus pré-requisitos, etapas, resultado e bloqueios relevantes. Consulte a tela, as rotas, as classes Data e as Actions.
- Revise o artigo e as palavras-chave ao mudar uma regra, validação, permissão, etapa, menu, botão ou campo citado. Faça a revisão no mesmo trabalho da mudança.

## Integração

A busca deve encontrar artigos por título, palavras-chave e conteúdo, com links para a seção relevante. A listagem, a busca e o acesso direto devem respeitar as permissões do usuário e o contexto de plataforma ou conta.

Os primeiros fluxos do Prospera são **Contas** no painel SaaS, **Minha conta** para o estabelecimento e **Usuários** nos dois contextos. A ajuda de Contas deve cobrir o acesso inicial do administrador, incluindo quando a senha é definida no cadastro e quando o usuário recebe um convite.

Referências consultadas no Controle Bar em `CLAUDE.md` e `docs/sessoes/2026-10-27-central-de-ajuda-completa.md`, e no GenAI em `docs/ai-conventions/content/help-articles.md`.

A integração está disponível em `/ajuda`, no menu de ambos os contextos. `HelpCatalog` filtra artigos por contexto e permissão antes da busca e do acesso direto. Markdown é renderizado com HTML bruto removido e links inseguros bloqueados.

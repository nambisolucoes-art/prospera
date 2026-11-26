# Central de Ajuda

A Central de Ajuda explica como operar o Prospera. O [tour guiado](tour-guiado.md) mostra onde ficam os controles e oferece um caminho para a explicação completa.

## Conteúdo

- Escreva os artigos em HTML (`docs/ajuda/{slug}.html`), um por fluxo, com nome curto em minúsculas, sem acentos e separado por hífens. Listagem, cadastro e edição podem compartilhar um artigo ao tratar do mesmo fluxo.
- Registre título, resumo, categoria e palavras-chave num comentário HTML no topo do arquivo (`titulo:`, `resumo:`, `categoria:` e `palavras-chave:`, um por linha, entre `<!--` e `-->`). Inclua sinônimos, siglas e frases que alguém usaria para procurar a resposta. Use títulos de seção por tarefa, com `id` explícito em cada `<h2>`, que serve de âncora para abrir o trecho correspondente.
- Escreva em português do Brasil e use os nomes que aparecem na tela. Documente quem pode executar cada ação, seus pré-requisitos, etapas, resultado e bloqueios relevantes. Consulte a tela, as rotas, as classes Data e as Actions.
- Revise o artigo e as palavras-chave ao mudar uma regra, validação, permissão, etapa, menu, botão ou campo citado. Faça a revisão no mesmo trabalho da mudança.

## Integração

A busca deve encontrar artigos por título, palavras-chave e conteúdo, com links para a seção relevante. A listagem, a busca e o acesso direto devem respeitar as permissões do usuário e o contexto de plataforma ou conta.

Os primeiros fluxos do Prospera são **Contas** no painel SaaS, **Dados da conta**, **Empresas** e **Estabelecimentos** no menu **Organização** e **Usuários** nos dois contextos. A ajuda de Contas deve cobrir o acesso inicial do administrador, incluindo quando a senha é definida no cadastro e quando o usuário recebe um convite.


A integração está disponível em `/ajuda`, no menu de ambos os contextos. `HelpCatalog` filtra artigos por contexto e permissão antes da busca e do acesso direto. O HTML do artigo é entregue como está, depois de remover `script`, `style`, `iframe`, `form`, atributos `on*` e endereços `javascript:` ou `data:`. Links para outros artigos usam `/ajuda/{slug}` ou `/ajuda/{slug}#ancora`.

## Tipografia dos artigos

Os artigos usam a tipografia do tema (UI Kit, Tipografia), com os estilos em `.help-article` (`resources/js/app/saas/Guidance.css`):

- **Letra capitular:** o primeiro parágrafo do artigo começa com `<span class="dropcap text-primary">A</span>` seguido do restante do texto.
- **Descrição horizontal:** `<dl class="dl-horizontal">` com `<dt>` e `<dd>` para pares termo e explicação, como os campos de um formulário ou as seções de uma ficha. Em telas estreitas, termo e descrição empilham.
- **Lista sem estilo:** `<ul class="list-unstyled">` para enumerações, com marcador quadrado na cor da marca. Use `<ul class="list-inline">` para itens curtos em uma linha e `<ol>` para passos em ordem.
- **Citações em bloco como avisos:** `<blockquote class="help-note help-note--info">` para permissão, `--warning` para atenção e `--success` para dica, com o rótulo em `<strong class="text-info">Permissão:</strong>`. Sem `<footer>`, que acrescentaria travessão.
- **Cores contextuais:** `text-success`, `text-danger`, `text-warning`, `text-info`, `text-muted` e as demais do tema. Siga o significado já usado na interface: **Salvar** e Ativa em verde, **Excluir** e Inativa em vermelho, **Cancelar** em cinza, suspenso em âmbar e pendente em azul.
- **Marca, sublinhado e riscado:** `<mark>` para o ponto crítico de uma regra, `<ins>` para sublinhar um termo-chave ou o valor novo e `<del>` para o valor antigo. `<abbr title="...">` na primeira ocorrência de siglas como CNPJ e CPF.
- Use `<strong>` para os nomes que aparecem na tela e `<h2 id="...">` por tarefa. Não use travessão nem meia-risca.

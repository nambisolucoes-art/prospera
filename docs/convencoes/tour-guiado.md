# Tour guiado

Cada tela de negócio deve oferecer uma orientação sob demanda na barra superior. O tour apresenta os controles no contexto da página e aponta para o artigo de [ajuda](ajuda.md) do fluxo. Telas de autenticação, erro e demonstração do tema ficam fora dessa regra. Uma página de demonstração que se torne uma tela de negócio passa a seguir a convenção.

## Passos e comportamento

- Descreva os passos na ordem da tela. Use identificadores estáveis para passos e alvos. Na implementação React, mantenha a configuração junto ao recurso e marque os alvos com `data-tour`.
- Compartilhe passos de componentes reutilizados, como trilha de navegação, filtros, tabela e ações do formulário. Acrescente à tela os passos próprios do fluxo.
- Pule os passos cujo alvo estiver ausente para o perfil, estado ou tamanho de tela atual. Quando um passo depender da abertura de um menu, aba ou diálogo, a demonstração deve restaurar somente o estado que ela própria alterou. Preserve dados digitados, preferências e registros do usuário.
- Permita avançar, voltar, fechar com **Esc** e retomar o foco no controle que abriu o tour. Respeite a preferência por movimento reduzido. No mobile, mantenha o cartão e o alvo visíveis, inclusive quando houver rodapé fixo.
- Ao alterar uma funcionalidade, confira textos, ordem, disponibilidade e posição dos alvos. No navegador, percorra os passos afetados no desktop e no mobile.

O primeiro conjunto pode aproveitar `SaasLayout`, `BusinessList` e `BusinessForm` para cobrir a listagem de contas, o cadastro, a edição e o modal de visualização. O modal pode entrar como passo da listagem, já que faz parte da mesma tela.

Referência consultada no GenAI em `AGENTS.md` e `docs/ai-conventions/frontend/guided-tours.md`.

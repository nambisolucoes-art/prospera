# Auditoria

Rotas: `/conta/auditoria` e `/saas/auditoria`. Ajuda: `/ajuda/auditoria`.

1. `PageHeaderTour`: título e trilha. Sem passo de cadastro, pois a auditoria é somente leitura.
2. `BusinessListTour`: filtros (busca, seletores e as datas com o `DatePicker` compartilhado), Colunas, menu aberto, tabela, largura, demonstração temporária, ações, quantidade, opções e paginação.
3. `audit-modal-summary`: abre a primeira operação autorizada e explica ação, tipo e data.
4. `audit-modal-content`: campos em Formatado, comparações Antes e Depois ou valores de cadastro e exclusão.
5. `audit-modal-views`: demonstra Log técnico e restaura Formatado ao sair. Exige permissão técnica.
6. `audit-modal-footer`: explica Fechar e encerra a janela aberta pelo tutorial.

Os passos do modal compartilham uma sessão de demonstração. Alvos assíncronos usam `availableWith` sobre o botão de visualização. Ausência de registros ou permissão pula a parte indisponível. Esc e Concluir limpam menus, largura temporária e modal. Preferências de colunas, filtros e dados não são gravados pelo tour.

Na listagem, Visualizar registro usa o mesmo botão compacto das demais telas. A coluna Ações mantém largura fixa de 78 px no desktop e 64 px no modo compacto, com título inteiro. A distribuição dá mais espaço ao nome do registro.

Miniabas Formatado e Log técnico: botões inverse compactos, unidos e com cantos quadrados, com sombra interna no estado selecionado e foco branco interno. Revisão visual sem mudança de alvos ou ordem dos passos.

As miniabas inverse usam o efeito de ondas claro dos botões do sistema. Com movimento reduzido, a animação fica desativada. Alvos e navegação do tour preservados.

O detalhe usa o mesmo cabeçalho de tabela das listagens e badges com as dimensões compartilhadas do tema para ação e tipo. Os alvos e a ordem dos passos permanecem os mesmos.

Ação usa a mesma badge na listagem e no detalhe: cadastro verde, alteração amarela e exclusão vermelha. Eventos de acesso e inspeção também possuem cores próprias. O nome acompanha a cor.

Os campos do detalhe mostram os rótulos das opções em português, inclusive para registros antigos. Modo de inspeção apresenta Somente leitura ou Leitura e edição. Alvos e ordem preservados.

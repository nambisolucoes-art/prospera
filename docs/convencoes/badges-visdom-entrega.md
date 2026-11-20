# Dimensões compartilhadas das badges

A referência foi inspecionada no navegador em http://localhost:8080/adm/suporte/os, após login e seleção de Todos. As badges Finalizado, Normal, Contrato e técnico usam fonte Noto Sans de 10,5 px, peso 600, espaçamento de 0,525 px entre letras, padding de 3,15 px por 6,3 px e altura medida de 20,28 px. A inspeção foi somente leitura.

O Prospera passou a usar os tokens --bold-label-* para Label, TableLabel, TagLabel, marcadores selecionados de TagSelect e badges comuns. Fonte 10,5 px, peso 600, espaçamento 0,05 em, entrelinha 14 px e padding 3 px por 6 px resultam em 20 px de altura em uma linha. Etiquetas longas podem ocupar mais linhas no celular. Contadores explicitamente pequenos e círculos conservam suas variantes próprias. Cantos seguem o tema do Prospera.

A variante local de auditoria com aproximadamente 25 px foi removida. Listagem e modal consomem o mesmo padrão global. Cores por evento, contraste das etiquetas, estrela restrita a Etiquetas e permissões foram preservados.

Ajuda e roteiros revisados: não houve mudança de campos, regras, alvos ou ordem. README e roteiro de componentes compartilhados registram as medidas para alterações futuras.

Validação no Prospera em 1440, 390 e 320 px: Etiquetas (nome e situação), Pessoas (marcadores selecionados e opções do seletor) e Auditoria (ação na tabela, ação e tipo no modal). Em uma linha, os componentes mediram 20 px, fonte 10,5 px e espaçamento 0,525 px. Textos longos quebram em múltiplos de 14 px, sem reduzir a fonte. Sem rolagem horizontal indevida no modal. Arquitetura, TypeScript e build aprovados.

Badges de situação usam StatusLabel: Ativa em verde e Inativa em vermelho. Contas e Etiquetas compartilham o componente para impedir divergência entre módulos. Cinza não representa inatividade. Dimensões e cores próprias de eventos de auditoria foram preservadas. Ajuda e tours revisados, sem mudança de controles ou alvos.

Situação Inativa em vermelho e Ativa em verde verificadas na tabela de Etiquetas em 1440, 390 e 320 px, sem rolagem horizontal da página. Busca no frontend não encontrou outra badge de situação inativa com variante inverse. Arquitetura, tipos e build aprovados.

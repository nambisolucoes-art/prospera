# Prospera

O Prospera usa Laravel, PostgreSQL, Octane e FrankenPHP, com React e TypeScript. A aplicação React troca dados com o Laravel pela API JSON. Consulte o [README](README.md) para rodar o projeto e localizar os padrões de código.

## Convenções por tarefa

| Ao trabalhar em | Consulte |
| --- | --- |
| Tela de negócio nova ou alteração de fluxo, campos, regras ou permissões | [Central de Ajuda](docs/convencoes/ajuda.md) e [tour guiado](docs/convencoes/tour-guiado.md) |
| Operação que muda dados de negócio, acesso ou permissões | [Auditoria](docs/convencoes/auditoria.md) |
| Qualquer alteração visível, inclusive em componentes compartilhados | [Validação visual](docs/convencoes/validacao-visual.md) |

## Responsabilidade pela entrega

- Toda tela de negócio nova deve estar coberta por um artigo de ajuda e ter um tour próprio. Um artigo pode cobrir várias telas do mesmo fluxo.
- Ao alterar uma funcionalidade, atualize na mesma tarefa os artigos e passos afetados. Alterações de layout também exigem revisar os destaques e a ordem do tour quando mudarem a posição ou a disponibilidade dos controles. Ajustes puramente visuais exigem revisão, com edição do conteúdo quando necessário.
- Quem implementa uma alteração visível verifica a aplicação rodando no navegador em desktop e mobile. Na entrega, informe as telas, as larguras, os estados verificados e qualquer limitação que tenha impedido um teste.
- Ajuda, tour e auditoria têm suas convenções registradas para orientar a implementação. Até que o suporte esteja disponível, versione o conteúdo de ajuda e os passos das telas de negócio novas ou alteradas em `docs/ajuda/` e `docs/tours/`, respectivamente. Registre a integração pendente na entrega. Para operações auditáveis, registre o evento e seu escopo no trabalho correspondente.

## Padrões do código

- No backend, siga `app/Domains/{Dominio}`. Controllers coletam a entrada e o contexto. `Application/Data` sanitiza, normaliza e valida. Actions executam os casos de uso, Queries cuidam das consultas e DTOs de domínio representam valores estruturados. A validação fica nas classes Data, sem criar FormRequests.
- No frontend, reutilize os componentes Bold já integrados, como `BusinessForm`, `BusinessList` e `SelectControl`. Mantenha a composição das telas nas pastas de integração e estilos compartilhados nos tokens de `resources/js/styles/`.
- Preserve o escopo de acesso entre plataforma SaaS e conta. A autorização pertence ao servidor, inclusive quando a interface oculta uma ação.

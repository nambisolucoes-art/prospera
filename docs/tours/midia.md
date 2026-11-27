# Tour de Mídia

Artigo: [Mídia](../ajuda/midia.html). Telas: `/conta/midia`, `/conta/empresa/{codigo}/midia` e `/conta/estabelecimento/{codigo}/midia` (S08). Executor na barra superior, configurado em `resources/js/app/guidance.ts`. Nenhum executor ou atalho de tutorial dentro de modal.

| Tela | Alvo | Título | Orientação |
| --- | --- | --- | --- |
| Entrada | `media-owners` | Empresas e estabelecimentos | O que o acesso alcança, sem escolha prévia. |
| Biblioteca | `media-upload` | Enviar imagem | Abre o envio: tipos, limites e otimização automática; enviar não publica. |
| Biblioteca | `media-filters` | Filtros | Uso, biblioteca (corporativa ou local) e busca no texto alternativo. |
| Biblioteca | `media-grid` | Imagens | Miniaturas autorizadas; a unidade vê a corporativa só para consulta; aviso de texto alternativo ausente. |
| Biblioteca | `media-empty` | Biblioteca vazia | Aparece sem imagens; indica como enviar a primeira. |

O tour não abre os modais de envio e de detalhes e não grava dados. Os controles que ficam dentro deles (`media-dropzone`, `media-variants`) são explicados no artigo. Passos cujo alvo não existe na tela (por permissão, por exemplo) são ignorados. Revisar alvos e ordem quando a posição dos controles mudar.

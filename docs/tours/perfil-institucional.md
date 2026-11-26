# Tour de Perfil institucional

Artigo: [Perfil institucional](../ajuda/perfil-institucional.html). Telas: `/conta/institucional`, `/conta/empresa/{codigo}/institucional` e `/conta/estabelecimento/{codigo}/institucional` (S06). Executor na barra superior, configurado em `resources/js/app/guidance.ts`. Nenhum executor ou atalho de tutorial dentro de modal.

| Tela | Alvo | Título | Orientação |
| --- | --- | --- | --- |
| Lista | `institutional-owners` | Empresas e estabelecimentos | O que o acesso alcança, sem escolha prévia. |
| Perfil | `institutional-sections` | Seções do perfil | Valor efetivo de cada seção e a herança entre empresa e unidade. |
| Perfil | `institutional-origin` | Origem do conteúdo | Própria, herdada ou padrão da plataforma; editorial é rascunho e operacional vale ao salvar. |
| Perfil | `institutional-publish` | Publicar alterações | Resumo de impacto recalculado pelo servidor. |
| Estabelecimento | `institutional-address` | Exibir endereço | Opção desligada por padrão; o endereço não é copiado. |

O tour não abre os modais de edição e de publicação e não grava dados. Passos cujo alvo não existe na tela (por permissão, por exemplo) são ignorados. Revisar alvos e ordem quando a posição dos controles mudar.

# Tour de Perfil institucional

Artigo: [Perfil institucional](../ajuda/perfil-institucional.html). Telas: `/conta/institucional`, `/conta/empresa/{codigo}/institucional` e `/conta/estabelecimento/{codigo}/institucional` (S06). Executor na barra superior, configurado em `resources/js/app/guidance.ts`. Nenhum executor ou atalho de tutorial dentro de modal.

| Tela | Alvo | Título | Orientação |
| --- | --- | --- | --- |
| Lista | `institutional-owners` | Empresas e estabelecimentos | O que o acesso alcança, sem escolha prévia. |
| Perfil | `institutional-sections` | Seções do perfil | Valor efetivo de cada seção e a herança entre empresa e unidade. |
| Perfil | `institutional-origin` | Origem do conteúdo | Própria, herdada ou padrão da plataforma; editorial é rascunho e operacional vale ao salvar. |
| Perfil | `institutional-publish` | Publicar alterações | Resumo de impacto recalculado pelo servidor. |
| Perfil | `hours-panel` | Situação e exceções de horário | Operacional: vale ao salvar e aparece nos sites publicados (S07). |
| Perfil | `hours-now` | Aberto agora | Calculado na hora no fuso da unidade, nunca gravado. |
| Estabelecimento | `hours-timezone` | Fuso horário | Fuso IANA da unidade; não altera datas gravadas. |
| Perfil | `hours-exceptions` | Exceções de horário | Feriado, fechamento temporário e horário especial; herdadas da empresa aparecem separadas. |
| Perfil | `hours-add-exception` | Cadastrar exceção | Na empresa, resumo de alcance e confirmação para unidades com horário próprio. |
| Estabelecimento | `institutional-address` | Exibir endereço | Opção desligada por padrão; o endereço não é copiado. |
| Empresa | `hours-units` | Unidades agora | Situação de cada unidade e origem do horário. |

O tour não abre os modais de edição, de publicação e de exceção e não grava dados. Os controles do editor de horário (`hours-editor`, `hours-copy`) e o resumo de alcance (`hours-impact`) ficam dentro de modais e são explicados no artigo. Passos cujo alvo não existe na tela (por permissão, por exemplo) são ignorados. Revisar alvos e ordem quando a posição dos controles mudar.

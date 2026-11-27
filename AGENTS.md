# Prospera

O Prospera usa Laravel, PostgreSQL, Octane e FrankenPHP, com React e TypeScript. A aplicação React troca dados com o Laravel pela API JSON. Consulte o [README](README.md) para rodar o projeto.

## Convenções por tarefa

Leia só a linha que corresponde à tarefa. Do plano 001 ou 002, só a seção da etapa (`grep -n` para achar).

| Ao trabalhar em | Consulte |
| --- | --- |
| Qualquer código PHP (Actions, Models, DTOs, Data, Queries, rotas, testes) | [Arquitetura do backend](docs/convencoes/arquitetura.md) e, para o detalhe, [Código do backend](docs/convencoes/codigo-backend.md) |
| Regras de isolamento, permissões, auditoria, textos, botões e tours em qualquer módulo | [Regras gerais](docs/convencoes/regras-gerais.md) |
| Tela de negócio nova ou alteração de fluxo, campos, regras ou permissões | [Central de Ajuda](docs/convencoes/ajuda.md) e [tour guiado](docs/convencoes/tour-guiado.md) |
| Operação que muda dados de negócio, acesso ou permissões | [Auditoria](docs/convencoes/auditoria.md) |
| Qualquer alteração visível, inclusive em componentes compartilhados | [Validação visual](docs/convencoes/validacao-visual.md) |
| Módulo novo, dado de negócio, rota, consulta ou tela que dependa de conta, empresa ou estabelecimento | [Contexto organizacional](docs/convencoes/contexto-organizacional.md) e [checklist de módulos](docs/convencoes/checklist-modulos.md) |
| Cargos, permissões, atribuições e administração de acesso | [Cargos e permissões](docs/convencoes/permissoes.md) e [identidade e vínculos](docs/convencoes/identidade-e-vinculos.md) |
| Pessoas, contatos, endereços, clientes e fornecedores | [Pessoas e contatos](docs/convencoes/pessoas-e-contatos.md) |
| Colaboradores, departamentos, funções, lotações, perfil institucional, horários, mídia, sites, diretório e domínios | [Institucional, equipe e sites](docs/convencoes/institucional.md) e, se a tarefa for de uma etapa, o [plano 002](docs/plano-de-acoes/002-institucional-equipe-sites-white-label.md) |
| Consultas, serviços com estado, cache, jobs e índices | [Performance](docs/convencoes/performance.md) |
| Workflow do GitHub Actions, checks de Pull Request e proteção da `main` | [Integração contínua](docs/convencoes/ci.md) |

Registros históricos de entregas ficam em `docs/entregas/`: não são convenção e só se leem quando a tarefa pedir.

## Padrão do backend (vale para tudo)

Padrão de backend do projeto, detalhado em [Arquitetura do backend](docs/convencoes/arquitetura.md). Em resumo:

- **Create e Update separados** em Action, Data e DTO. Nunca `SaveX*`. Controllers só coletam entrada e contexto.
- **Data valida só formato** (`rules()`, `messages()`, sem FormRequest). Regras do registro vivem no **Model** (`register`/`revise`) e em Value Objects, com exceção de domínio.
- **Actions** `final readonly`, `execute(Data, ...)`, sem função privada, DTO montado numa variável, `Gate::authorize` literal e `AuditLog::transaction()`. Passo com nome vira sub-Action ou Query.
- **Queries** de propósito único (`execute`), com escopo no SQL. Linhas de até 120 colunas, uma instrução por linha, nenhuma compatibilidade com estrutura antiga.
- Código novo nasce assim e código antigo é refatorado quando tocado.

## Regras que valem sempre

Detalhe em [Regras gerais](docs/convencoes/regras-gerais.md).

- **Escopo:** árvore Plataforma SaaS, Conta (`account_id`), Empresa (`company_id`), Estabelecimento (`establishment_id`). Leitura por Query com tenant e nó antes de paginar. Contexto vem da URL, nunca da sessão. ID de outro escopo responde 404. Autorização sempre no servidor.
- **Pessoas:** CPF/CNPJ, nome, inscrições, endereço e contatos pertencem a Pessoas; outros módulos guardam só o vínculo. Exceção PD-01: empresas e estabelecimentos têm identificação fiscal própria (`TaxDocument`).
- **Permissões:** definidas no código, uma por ação (`account.modulo.acao`, literal), sincronizadas por `permissions:sync`. Sem permissão genérica de gerenciar nem `is_account_admin` como atalho.
- **Auditoria:** todo Model persistente é `Auditable` com `auditedFields()`; sem trilha paralela ao `AuditLog`; segredos e imagens nunca entram no histórico; rodar `audit:check`.
- **Octane:** serviços com estado de conta, contexto ou permissões usam binding `scoped`; sem estado estático mutável.
- **Interface:** reutilizar os componentes Bold e `BusinessForm`; botões **Salvar** (verde), **Excluir** (vermelho) e Cancelar (branco), sem o nome do recurso; sem travessão nem meia-risca em nenhum texto; tour e botão do tour pertencem à tela, nunca ao modal.
- **Testes** usam só SQLite em memória (`tests/bootstrap.php`); nunca no banco da aplicação. Medições de performance e concorrência ficam num PostgreSQL isolado.

## Responsabilidade pela entrega

- Tela de negócio nova tem artigo de ajuda e tour; ao alterar funcionalidade, atualize os artigos e passos afetados. Versione o conteúdo em `docs/ajuda/` e `docs/tours/`.
- Alteração visível é verificada no navegador em desktop e mobile; informe telas, larguras, estados e limitações.
- Operações auditáveis registram o evento e seu escopo.

## Eficiência de execução

- Leia o mínimo (só a linha da tabela e a seção da etapa) e não releia o que acabou de editar.
- Durante o trabalho rode só `--filter` do que mudou. Suíte completa, Pint e PHPStan **uma vez**, antes de entregar.
- Laboratório PostgreSQL, concorrência, FrankenPHP e Chromium só quando a mudança toca banco, trava, contexto ou tela, sem repetir sem causa.
- Saída de comandos com `tail`, `grep` ou `head`. Relatório curto: o que mudou, o que foi provado, o que falta.

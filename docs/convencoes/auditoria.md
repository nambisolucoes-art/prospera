# Auditoria

A auditoria deve permitir que um usuário autorizado descubra quem fez uma mudança de negócio, quando, em qual conta e o que mudou.

## Registro de operações

- Ao criar ou alterar uma Action de escrita, identifique as mudanças que precisam de histórico. Comece por cadastro e edição de contas, ativação e inativação, alterações de plano, cadastro de usuários, mudanças de papel e dados do estabelecimento.
- Registre autor, data, evento, identificador da operação, tipo e identificador do registro e, quando aplicável, a conta afetada. Diferencie uma operação da plataforma que afeta uma conta de uma operação executada dentro dela. Registre operações automáticas com autoria de sistema.
- A atualização da foto do perfil deve registrar o próprio usuário como autor, com o contexto de plataforma ou conta da sessão. A imagem fica fora do histórico.
- Na inspeção de contas, o log da aplicação registra início, encerramento e requisições de escrita em modo de edição, com os identificadores do administrador SaaS, da conta e do usuário representado. O histórico central preserva o administrador SaaS como autor e o usuário representado separadamente.
- Em mudanças de campos, armazene os valores anteriores e novos apenas para campos autorizados. Preserve uma identificação compreensível do registro para consulta histórica. Senhas, hashes, tokens, segredos e dados pessoais desnecessários devem ficar fora do histórico, inclusive da visão técnica.
- Grave o histórico na mesma transação da mudança de negócio. Uma operação revertida não deve aparecer como concluída. Agrupe mudanças do mesmo registro na mesma operação e descarte atualizações sem alteração efetiva.
- Se for usado um observer do Eloquent, a Action deve registrar explicitamente as escritas que não disparam seus eventos, como atualizações em lote e alterações diretas em tabelas de relacionamento. No Octane, o contexto de auditoria deve durar apenas a requisição ou a operação que o abriu.

## Consulta e verificação

- Mantenha a consulta centralizada, paginada e somente para leitura. Use filtros por período, conta quando autorizada, autor, entidade e evento. Mostre o resumo em português e os valores antes e depois no detalhe. A visão técnica deve obedecer à mesma autorização e às mesmas regras de ocultação.
- Aplique o escopo no servidor em listagens, detalhes e filtros. Um usuário de uma conta só pode consultar os eventos daquela conta que seu perfil permite. Operações da plataforma exigem autorização própria.
- Use os testes das operações auditáveis para conferir autoria, conta, valores registrados e reversão por transação. Ao implementar a consulta, verifique também a tentativa de acessar o registro de outra conta e a visualização do detalhe no mobile.

Referências consultadas no Controle Bar em `docs/specs/0003-auditoria/` e no GenAI em `Docs/audit.md`. A prioridade acima segue os fluxos atuais do Prospera.

A implementação usa `Auditable`, `AuditsChanges`, `AuditObserver` e `AuditLog::transaction()`. Declare campos permitidos e exclusões justificadas. Para pivôs, use `AuditLog::relationship()` dentro da transação da Action. A consulta `/conta/auditoria` ou `/saas/auditoria` é somente leitura e separa listar, visualizar e detalhes técnicos. `audit:check` verifica todos os modelos de domínio e as colunas persistidas; é obrigatório também para módulos novos.

## Formatação dos valores

A consulta usa AuditValueFormatter para apresentar opções com os mesmos rótulos dos formulários. Declare a formatação por entidade e campo ao adicionar enums, módulos ou situações. Nunca traduza valores por substituição global: nome, e-mail e outros textos livres podem coincidir com um código. Preserve os valores originais do histórico e formate antes/depois na leitura, cobrindo registros antigos. Teste o rótulo exibido, a preservação do valor armazenado, valores ausentes e textos livres iguais a códigos.

Modo de inspeção usa AccountInspectionMode::label(): Somente leitura ou Leitura e edição. Módulo people usa Pessoas. Situações, acesso e tipos de pessoa possuem seus próprios rótulos. Marcadores de campos protegidos continuam sem conteúdo sensível.

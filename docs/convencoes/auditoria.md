# Auditoria

A auditoria deve permitir que um usuário autorizado descubra quem fez uma mudança de negócio, quando, em qual conta e o que mudou.

## Registro de operações

- Ao criar ou alterar uma Action de escrita, identifique as mudanças que precisam de histórico. Comece por cadastro e edição de contas, ativação e inativação, alterações de plano, cadastro de usuários, mudanças de papel e dados do estabelecimento.
- Registre autor, data, evento, identificador da operação, tipo e identificador do registro e, quando aplicável, a conta afetada. Diferencie uma operação da plataforma que afeta uma conta de uma operação executada dentro dela. Registre operações automáticas com autoria de sistema.
- A atualização da foto do perfil deve registrar o próprio usuário como autor, com o contexto de plataforma ou conta da sessão. A imagem fica fora do histórico.
- Na inspeção de contas, o log da aplicação registra início, encerramento e requisições de escrita em modo de edição, com os identificadores do administrador SaaS, da conta e do usuário representado. A integração com a consulta central de auditoria permanece prevista.
- Em mudanças de campos, armazene os valores anteriores e novos apenas para campos autorizados. Preserve uma identificação compreensível do registro para consulta histórica. Senhas, hashes, tokens, segredos e dados pessoais desnecessários devem ficar fora do histórico, inclusive da visão técnica.
- Grave o histórico na mesma transação da mudança de negócio. Uma operação revertida não deve aparecer como concluída. Agrupe mudanças do mesmo registro na mesma operação e descarte atualizações sem alteração efetiva.
- Se for usado um observer do Eloquent, a Action deve registrar explicitamente as escritas que não disparam seus eventos, como atualizações em lote e alterações diretas em tabelas de relacionamento. No Octane, o contexto de auditoria deve durar apenas a requisição ou a operação que o abriu.

## Consulta e verificação

- Mantenha a consulta centralizada, paginada e somente para leitura. Use filtros por período, conta quando autorizada, autor, entidade e evento. Mostre o resumo em português e os valores antes e depois no detalhe. A visão técnica deve obedecer à mesma autorização e às mesmas regras de ocultação.
- Aplique o escopo no servidor em listagens, detalhes e filtros. Um usuário de uma conta só pode consultar os eventos daquela conta que seu perfil permite. Operações da plataforma exigem autorização própria.
- Use os testes das operações auditáveis para conferir autoria, conta, valores registrados e reversão por transação. Ao implementar a consulta, verifique também a tentativa de acessar o registro de outra conta e a visualização do detalhe no mobile.

Referências consultadas no Controle Bar em `docs/specs/0003-auditoria/` e no GenAI em `Docs/audit.md`. A prioridade acima segue os fluxos atuais do Prospera.

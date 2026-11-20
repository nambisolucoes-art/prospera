# Rótulos das ações de formulários

A gravação dos formulários de negócio usa Salvar. Confirmações de exclusão usam Excluir. Os textos não acrescentam o nome da entidade. BusinessFormActions oferece Salvar por padrão; AccountForm usa Excluir para DELETE e Salvar para as demais gravações, mantendo ações explícitas de autenticação.

Aplicado em Pessoas, Etiquetas, Cargos, Contas, Estabelecimento, Pontos, Usuários, Perfil, alteração de senha, confirmação da configuração de segundo fator e cadastro de chaves. A remoção de chave e a exclusão do perfil usam Excluir. Cores continuam Salvar verde, Cancelar branco e Excluir vermelho.

Títulos, tooltips, nomes acessíveis das ações da tabela e perguntas de confirmação identificam o recurso. Ações que abrem o cadastro, como Cadastrar pessoa, mantêm seu contexto. Ações específicas de autenticação, ativação e geração de códigos preservam seus verbos.

Regra registrada no AGENTS.md e README. Artigos de ajuda e roteiros afetados revisados na mesma tarefa, sem alteração de alvos ou ordem. Integrações existentes preservadas.

Validação no navegador em 1440, 390 e 320 px: Salvar em cadastro e edição de Pessoas, cadastro de Cargos, edição da conta e Perfil. Excluir em Perfil e confirmação de Etiquetas. Modal de Etiquetas com validação de nome obrigatório e cancelamento conferidos, sem gravar ou excluir registros. Cor verde dos botões Salvar e ausência de rolagem horizontal verificadas. Arquitetura, tipos e build aprovados.

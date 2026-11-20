# Identidade e vínculos

Política oficial da DA-24. Separa quem a pessoa é para o login, quem ela é no cadastro de cada conta, se ela tem acesso a uma conta e o que pode fazer em cada parte dela. Contexto geral em [Contexto organizacional](contexto-organizacional.md); autorização em [Cargos e permissões](permissoes.md).

## Camadas

| Camada | Tabela | Conteúdo | Pertence a | Quem altera |
| --- | --- | --- | --- | --- |
| Identidade global | `users` | E-mail de acesso, senha, segundo fator, chaves de acesso, nome da identidade, foto, `access_enabled`, `is_saas_admin` | À própria pessoa | Somente a própria pessoa, pelos fluxos seguros (`self.*`), e a plataforma, em suporte auditado |
| Pessoa cadastral | `people` | Identificação, contatos e endereço da pessoa naquela conta | À conta | Regras de [Pessoas e contatos](pessoas-e-contatos.md) |
| Vínculo com o tenant | `account_memberships` (E05) | Identidade, conta, pessoa da conta e situação (`active`, `suspended`) | À conta | Somente operadores de escopo tenant |
| Atribuições | `role_assignments` (E05) | Cargo num escopo (conta, empresa ou estabelecimento) | Ao nó da atribuição | Operador com `account.users.assign-role` sobre o nó, com antiescalada |

Regras gerais:

- **Sem cópia automática:** nenhuma camada altera outra implicitamente. Editar o nome ou o e-mail da pessoa não altera a identidade (corrige A20 na E05). Na criação do vínculo, a pessoa da conta recebe nome e e-mail iniciais da identidade; depois disso, cada camada segue seu próprio fluxo.
- **Pessoa por conta:** a mesma identidade tem uma pessoa diferente em cada conta. Dados cadastrais não atravessam contas.
- **Sem exceção de tenant:** nenhum administrador de tenant, empresa ou estabelecimento altera diretamente o e-mail de acesso, a senha, o segundo fator, as chaves de acesso ou a situação da identidade de outra pessoa, nem quando ela tem um único vínculo.
- **Identidade somente leitura:** na interface da conta, o e-mail de acesso aparece apenas para leitura, com o rótulo "E-mail de acesso".

## Fluxos

### Alteração de e-mail de acesso

1. A própria pessoa pede a troca em Perfil, com confirmação de senha recente.
2. O sistema envia um link de confirmação ao novo endereço. O e-mail de acesso só muda quando o link é confirmado, e o link expira.
3. Até a confirmação, o login continua com o endereço atual.
4. Após a confirmação, o endereço anterior recebe um aviso da troca, sem link que desfaça a operação sem autenticação. As demais sessões da identidade são encerradas.
5. Se o novo endereço já pertencer a outra identidade, a resposta é a mesma de um pedido aceito ("Enviamos um link de confirmação para o novo e-mail"). Nenhum e-mail é enviado a esse endereço com dados da conta.
6. A troca é auditada como evento da identidade, sem o token.

A plataforma pode iniciar a troca em suporte, com autoria registrada. A conclusão também exige a confirmação no novo endereço.

### Recuperação de acesso

- Usa a redefinição de senha do Fortify, sempre enviada ao e-mail da própria identidade.
- A tela pública de esqueci a senha responde da mesma forma para e-mail existente ou inexistente.
- Um operador de escopo tenant pode **disparar** o envio do link pela ação "Enviar link de redefinição de senha" (`account.users.reset-password`). Ele não vê, não define e não recebe a senha nem o link.
- O disparo é auditado como evento de vínculo, com o operador como autor.

### Reenvio de convite

- O convite é o link de definição de senha enviado à identidade criada sem senha (mecanismo atual de `SendUserInvitationAction`).
- Reenviar exige `account.users.invite` sobre o tenant e só se aplica a vínculos cuja identidade ainda não definiu a senha. O envio é auditado.
- Para uma identidade que já existe em outra conta, o convite cria somente o vínculo e a pessoa nesta conta, depois que a pessoa aceita (E09). Até a E09, essa criação é recusada com resposta neutra.

### Suspensão e reativação do vínculo

- Suspender o acesso à conta (`account.users.revoke`) e reativá-lo (`account.users.reactivate`) são operações de escopo tenant.
- A suspensão bloqueia todas as permissões daquela conta na requisição seguinte e não afeta outras contas, a identidade nem as atribuições gravadas.
- Administradores de empresa e de estabelecimento não suspendem vínculos. Eles retiram o acesso local removendo as atribuições que gerenciam.
- `access_enabled = false` bloqueia a identidade inteira e é exclusivo da plataforma.
- O último vínculo ativo com o cargo Administrador em escopo tenant não pode ser suspenso.

### Revogação de atribuições

- Retirar uma atribuição exige `account.users.assign-role` sobre o nó dela e todas as permissões do cargo naquele nó (antiescalada em [Cargos e permissões](permissoes.md)).
- Ninguém retira atribuição de fora do próprio escopo ou de nível superior.
- Retirar a última atribuição local não suspende o vínculo nem altera a identidade. O vínculo sem atribuições não tem permissões na conta até receber outra atribuição ou ser suspenso por um operador de escopo tenant.
- A revogação vale na requisição seguinte, porque as permissões não ficam em cache entre requisições.

## Usuários em vários tenants

- Uma identidade pode ter um vínculo por conta (E09, liberado por chave de configuração).
- Cada vínculo tem pessoa, situação e atribuições independentes. Revogar um vínculo afeta só aquela conta.
- Um operador de uma conta não vê nem infere os outros vínculos da identidade: listagens, mensagens de erro e auditoria da conta mostram apenas dados da própria conta.
- Com mais de um vínculo, a conta vem da URL (`/contas/{conta}/...`, PD-04) e a pessoa escolhe a conta após o login.
- O administrador SaaS não tem vínculo com contas. Ele acessa dados de uma conta apenas por inspeção auditada, com exatamente a autoridade do vínculo representado.

## Respostas neutras

Nenhuma resposta a usuário de tenant, nem a visitante anônimo, revela se um e-mail pertence a uma identidade ou a outra conta.

| Situação | Resposta |
| --- | --- |
| Criar usuário com e-mail de identidade existente | "Convite enviado" depois da E09. Até lá, recusa com mensagem que não confirma a existência |
| Editar e-mail da pessoa | Validação limitada às pessoas da própria conta; nunca consulta `users` de outras contas |
| Esqueci a senha | Mesma mensagem para e-mail existente ou inexistente |
| Alteração do próprio e-mail para endereço já usado | Mesma mensagem de pedido aceito |
| Login inválido | Mensagem genérica de credenciais |

Validações de unicidade de documento seguem a mesma regra: são sempre limitadas ao tenant.

## Permissões

| Permissão | Alvo | Ação |
| --- | --- | --- |
| `account.users.list`, `account.users.view` | tenant, company, establishment | Lista vínculos com atribuição no escopo do operador, mostrando só as atribuições desse escopo |
| `account.users.create` | tenant, company, establishment | Cria identidade nova, vínculo e a primeira atribuição no escopo do operador. Nunca altera identidade existente |
| `account.users.update` | tenant | Dados do vínculo na conta. Não inclui a identidade |
| `account.users.revoke`, `account.users.reactivate` | tenant | Suspender e reativar o acesso à conta |
| `account.users.reset-password` | tenant | Disparar o link de redefinição de senha |
| `account.users.invite` | tenant | Reenviar o convite |
| `account.users.assign-role` | tenant, company, establishment | Conceder e retirar atribuições no nó |
| `self.profile.*`, `self.security.*` | identidade | Fluxos da própria pessoa |

`account.users.reactivate`, `account.users.reset-password` e `account.users.invite` são nomes fechados na E02 e entram no catálogo na E05.

## Auditoria

- Eventos de identidade (troca de e-mail, senha, segundo fator, `access_enabled`) registram a própria pessoa ou a plataforma como autor, sem valores sensíveis.
- Eventos de vínculo e de disparo de link são de nó tenant.
- Eventos de atribuição gravam a empresa e o estabelecimento do escopo.
- Na inspeção, a autoria preserva o administrador SaaS e o usuário representado.

## Testes obrigatórios

Cenários 38, 39, 50 a 53 do plano: tentativa de alterar e-mail, senha ou situação da identidade por administradores de qualquer nível; retirada de atribuição fora do escopo; última atribuição local; edição de pessoa sem efeito na identidade; respostas neutras.

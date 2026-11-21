# Tour de Clientes

Artigo: [Clientes](../ajuda/clientes.md). Tela: `/conta/estabelecimento/{codigo|todos}/clientes` (E07). Executor na barra superior, configurado em `resources/js/app/guidance.ts`. Nenhum executor ou atalho de tutorial no modal.

| Ordem | Alvo | Título | Orientação |
| --- | --- | --- | --- |
| 1 | PageHeaderTour | Moldura | Passos compartilhados da moldura, sem duplicar textos. |
| 2 | organization-switcher | Empresas da área de trabalho | A lista considera as empresas das unidades marcadas; duas unidades da mesma empresa mostram a relação uma vez. |
| 3 | commercial-register | Cadastrar | Informe os dados da Pessoa ou busque um documento já cadastrado. O documento fica fixo e os campos autorizados podem ser atualizados. A etiqueta Cliente vem marcada. Selecione a empresa quando houver mais de uma opção e use Salvar. |
| 4 em diante | BusinessListTour | Buscar, filtrar, colunas e paginação | Busca por nome, e-mail, CPF ou CNPJ; filtros Situação e Empresa; Use Visualizar cadastro para abrir a ficha. Editar abre a edição. Inativar e Ativar mudam a situação na empresa da linha. |

## Página de cadastro

O botão **Cadastrar** abre uma página própria com tutorial na barra superior. O tour destaca a empresa quando houver escolha, os dados da pessoa, o endereço, os contatos, as etiquetas e as ações **Salvar** e **Cancelar**. A lupa localiza uma ficha já existente. Use **Descartar** para retirar essa seleção e pesquisar outro documento.

Revisar alvos e ordem quando a posição de Cadastrar, dos filtros ou das ações mudar. Conferido em 1440, 1024, 768, 390 e 320 px no registro da E07.

## Página de edição

A ação **Editar** abre a ficha da empresa da linha. O tutorial destaca a empresa, os campos disponíveis, as etiquetas e as ações **Salvar** e **Cancelar**.

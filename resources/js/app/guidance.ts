import { screenPath } from '@/utils/organizationContext';
import { tagListFormTourSteps } from '@/app/saas/tagTours';
import { catalogListFormTourSteps } from '@/app/saas/catalogTours';
import { departmentsConfig, jobTitlesConfig } from '@/app/saas/CompanyCatalog.types';
import { pageHeaderTourSteps } from '@/components/PageHeader/PageHeaderTour';
import { businessListTourSteps } from '@/components/BusinessList/BusinessListTour';
import { auditDetailTourSteps } from '@/app/saas/auditTours';
import type {
  TourDefinition,
  TourStep,
} from '@/components/GuidedTour/GuidedTour';
const at = (target: string, title: string, text: string): TourStep => ({
  target: '[data-tour="' + target + '"]',
  title,
  text,
});
export function tourFor(
  path: string,
  permissions: string[],
): TourDefinition | undefined {
  const routePath = screenPath(path);
  const inAccount = /^\/conta(?:\/|$)/.test(routePath);
  path = routePath.replace(/^\/conta\/ajuda/, '/ajuda');

  let title = '',
    help = '',
    steps: TourStep[] = [];
  const list = (name: string, _search: string, _table: string) =>
    businessListTourSteps({
      filters: `Encontre ${name} pela busca e pelos filtros. A lista atualiza automaticamente. A lupa à direita e Enter também executam a pesquisa.`,
    });
  const form = (targets: TourStep[]) => [
    ...targets,
    at(
      'password-visibility',
      'Conferir a senha',
      'Use o ícone de olho para mostrar a senha deste campo. Clique novamente para ocultar. O tutorial não altera a visibilidade.',
    ),
    at(
      'form-actions',
      'Salvar alterações',
      'Revise os dados e salve. Campos obrigatórios e mensagens de validação orientam o preenchimento.',
    ),
  ];
  if (path === '/contas') {
    title = 'Selecionar conta';
    help = 'acesso-contas';
    steps = [
      ...businessListTourSteps({ filters: 'Confira as contas vinculadas à sua identidade.' }),
      at('account-selection', 'Abrir uma conta',
        'Confira a situação e os cargos. Abrir conta muda somente esta aba. Vínculos suspensos não permitem entrada.'),
    ];
  } else if (/^\/convites\/[A-Za-z0-9]{64}$/.test(path)) {
    title = 'Convite para conta';
    help = 'acesso-contas';
    steps = [at('account-invitation', 'Conferir e aceitar',
      'Confira a conta, o cargo e a validade. Salvar confirma o aceite. O tutorial não aceita o convite.')];
  } else if (path === '/user/confirm-password') {
    title = 'Confirmar acesso';
    help = 'seguranca';
    steps = [
      at(
        'security-confirm',
        'Confirmar sua identidade',
        'Informe sua senha atual para abrir as configurações de segurança. Use o ícone de olho para mostrar ou ocultar a senha.',
      ),
    ];
  } else if (path === '/dashboard' || path === '/saas/contas') {
    title = 'Contas';
    help = 'contas';
    steps = list('contas', 'business-filters', 'business-list');
  } else if (/^\/saas\/contas\/(cadastrar|\d+\/editar)$/.test(path)) {
    title = 'Cadastro de conta';
    help = 'contas';
    steps = form([
      at(
        'business-form',
        'Dados da conta',
        'Preencha a identificação, o endereço e os contatos do contratante.',
      ),
      ...(path.endsWith('/cadastrar') ? [
        at(
          'account-first-company',
          'Primeira empresa',
          'Use Sim para criar a empresa do contratante e seu estabelecimento principal. Com CNPJ, informe se ele é da sede ou de uma filial. Use Não para editar os dados sugeridos antes de salvar.',
        ),
      ] : []),
    ]);
  } else if (path === '/conta') {
    title = 'Início';
    help = 'dados-da-conta';
    steps = [
      at(
        'home-pending',
        'Pendências da organização',
        'Empresas sem CNPJ ou CPF aparecem aqui. Abra a empresa para completar a identificação fiscal.',
      ),
      at(
        'home-organization',
        'Conta e organização',
        'Os cartões levam à Organização. Abra a conta, as empresas ou seus estabelecimentos conforme suas permissões.',
      ),
      at(
        'organization-switcher',
        'Configurar a área de trabalho',
        'Marque uma ou várias unidades. A caixa no fim da linha marca toda a empresa. Clique fora da lista para aplicar a seleção nesta aba e salvá-la como padrão. Abrir área de trabalho, no fim da lista, leva às unidades marcadas.',
      ),
    ];
  } else if (/^\/conta\/estabelecimento\/[a-z0-9][a-z0-9-]{1,39}$/.test(path)) {
    title = path.endsWith('/todos') ? 'Área de trabalho' : 'Início do estabelecimento';
    help = 'estabelecimento-atual';
    steps = [
      at(
        'organization-switcher',
        'Configurar a área de trabalho',
        'Marque uma ou várias unidades. A caixa no fim da linha marca toda a empresa. Clique fora da lista para aplicar a seleção nesta aba e salvá-la como padrão.',
      ),
      at(
        'establishment-home-summary',
        'Estabelecimentos da área de trabalho',
        'Confira as unidades desta aba. Quando a tela pedida funciona com uma unidade por vez, escolha aqui o estabelecimento. Ao abrir uma unidade, Ver cadastro e Editar aparecem conforme as permissões do seu cargo.',
      ),
      at(
        'establishment-home-tabs',
        'Contexto desta aba',
        'O endereço mantém a seleção ao atualizar a página. Para salvar um registro operacional, abra o estabelecimento de destino.',
      ),
    ];
  } else if (/^\/conta\/estabelecimento\/[a-z0-9][a-z0-9-]{1,39}\/(clientes|fornecedores)\/(cadastrar|[a-z0-9][a-z0-9-]{1,39}\/\d+\/editar)$/.test(path)) {
    const customers = path.includes('/clientes/');
    const editing = path.endsWith('/editar');
    title = `${editing ? 'Editar' : 'Cadastrar'} ${customers ? 'cliente' : 'fornecedor'}`;
    help = customers ? 'clientes' : 'fornecedores';
    steps = form([
      at('commercial-registration-company', 'Empresa responsável', editing
        ? 'Confira a empresa desta ficha. Marque empresas para criar ou reativar vínculos e desmarque para inativá-los, conforme suas permissões.'
        : 'Marque uma ou mais empresas para cadastrar os vínculos em um único salvamento.'),
      at('person-identity', 'Identificação', editing
        ? 'Confira a ficha e altere os campos autorizados. Corrigir o tipo de pessoa ou o CPF ou CNPJ vale para todos os vínculos.'
        : 'Selecione Pessoa física para buscar por CPF ou Pessoa jurídica para buscar por CNPJ. Ao preencher o documento completo, aguarde a busca automática ou use a lupa para buscar na hora.'),
      at('person-address', 'Endereço', 'Complete o endereço do cadastro compartilhado conforme suas permissões.'),
      at('person-contact', 'Contato', 'Informe os dados gerais de contato da pessoa.'),
      at('person-tags', 'Etiquetas', 'A etiqueta do papel vem marcada. Selecione as etiquetas adicionais do módulo.'),
    ]);
  } else if (/^\/conta\/estabelecimento\/[a-z0-9][a-z0-9-]{1,39}\/(clientes|fornecedores)$/.test(path)) {
    const customers = path.endsWith('/clientes');
    title = customers ? 'Clientes' : 'Fornecedores';
    help = customers ? 'clientes' : 'fornecedores';
    steps = [
      at(
        'organization-switcher',
        'Empresas da área de trabalho',
        'A lista considera as empresas das unidades marcadas. Duas unidades da mesma empresa mostram a mesma relação uma única vez.',
      ),
      at(
        'commercial-register',
        'Cadastrar',
        'Informe os dados da pessoa ou busque um CPF ou CNPJ já cadastrado. A etiqueta do papel vem marcada e Salvar registra o cadastro na empresa escolhida.',
      ),
      ...businessListTourSteps({
        filters: 'Busque por nome, e-mail, CPF ou CNPJ e filtre pela situação e, com várias empresas, pela empresa.',
        table: 'Cada linha é a relação de uma pessoa com uma empresa. Use Visualizar cadastro para abrir a ficha, Editar para alterar os dados e Ativar ou Inativar para mudar a situação da relação, conforme seu cargo.',
      }),
    ];
  } else if (path === '/conta/colaboradores') {
    title = 'Colaboradores';
    help = 'colaboradores';
    steps = [
      at(
        'employees-register',
        'Cadastrar',
        'Busque a pessoa pelo CPF ou CNPJ completo para reaproveitar o cadastro, ou informe uma pessoa nova. Marque as empresas em que ela é colaboradora.',
      ),
      ...businessListTourSteps({
        filters: 'Busque por nome, e-mail, CPF ou CNPJ e filtre pela situação do vínculo, pela empresa (quando você alcança mais de uma) e, se o seu cargo lista usuários, pelo acesso à conta.',
        table: 'Cada linha é uma pessoa, com todas as empresas em que ela é colaboradora e que o seu acesso alcança. Colaborador não é usuário: ser colaborador não concede acesso nem permissões. Use Visualizar cadastro para consultar e Editar para abrir a ficha e administrar cada vínculo.',
      }),
    ];
  } else if (/^\/conta\/colaboradores\/(cadastrar|\d+\/editar)$/.test(path)) {
    const editing = path.endsWith('/editar');
    title = editing ? 'Ficha do colaborador' : 'Cadastro de colaborador';
    help = 'colaboradores';
    steps = form(editing
      ? [
        at('person-identity', 'Dados centrais da pessoa', 'Identidade, documento e contatos valem para todas as empresas e para os demais papéis da pessoa, como cliente e fornecedor. Os campos aparecem conforme suas permissões.'),
        at('employee-tags', 'Etiquetas', 'As etiquetas são derivadas dos vínculos e dos outros papéis da pessoa. Não podem ser atribuídas nem removidas aqui.'),
        at('employee-links', 'Vínculos empresariais', 'Cada empresa tem o próprio vínculo, com situação e contatos profissionais independentes. Salvar um vínculo não altera os outros.'),
        at('employee-add-link', 'Vincular a outra empresa', 'Marque as empresas em que você pode cadastrar e que ainda não têm vínculo da pessoa.'),
        at('employee-access', 'Acesso ao Prospera', 'Indicador somente leitura, visível a quem lista usuários. Colaborador e usuário são conceitos distintos.'),
      ]
      : [
        at('employee-companies', 'Empresas', 'Marque uma ou mais empresas. Cada uma recebe um vínculo de colaborador independente, gravado numa única operação. Com uma só empresa autorizada, este passo não aparece.'),
        at('person-identity', 'Pessoa', 'Informe o CPF ou CNPJ completo e aguarde a busca (ou use a lupa). Se a pessoa já existe na conta, ela é reaproveitada sem duplicar o documento.'),
        at('employee-work-contacts', 'Contatos profissionais', 'Opcionais e próprios de cada empresa. Não alteram os contatos centrais da pessoa.'),
      ]);
  } else if (/^\/conta\/(departamentos|funcoes)$/.test(path)) {
    const departments = path.endsWith('/departamentos');
    const config = departments ? departmentsConfig : jobTitlesConfig;
    title = config.title;
    help = 'departamentos-funcoes';
    steps = [
      ...businessListTourSteps({
        filters: departments
          ? 'Busque pelo nome do departamento, sem se preocupar com acentos ou maiúsculas, e filtre pela empresa (quando você alcança mais de uma) e pela situação. A lista se atualiza automaticamente.'
          : 'Busque pelo nome da função, sem se preocupar com acentos ou maiúsculas, e filtre pela empresa (quando você alcança mais de uma) e pela situação. A lista se atualiza automaticamente.',
        table: departments
          ? 'Cada linha é um departamento de uma empresa, mostrada na coluna Empresa. A lista traz as empresas que o seu acesso alcança. Use Visualizar cadastro para consultar e Editar para alterar o nome ou a situação, conforme o seu cargo. Departamentos não são excluídos: inative os que não usa mais.'
          : 'Cada linha é uma função de uma empresa, mostrada na coluna Empresa. A lista traz as empresas que o seu acesso alcança. Função é a atividade profissional, diferente do Cargo, que é o papel de acesso. Use Visualizar cadastro para consultar e Editar para alterar o nome ou a situação. Funções não são excluídas: inative as que não usa mais.',
      }),
      ...catalogListFormTourSteps(config),
    ];
  } else if (path === '/conta/organizacao') {
    title = 'Organização';
    help = 'organizacao';
    steps = [
      at(
        'organization-account',
        'Conta contratante',
        'O selo Conta contratante identifica o primeiro nível. O lápis abre seus dados e o botão de adição cadastra uma empresa.',
      ),
      at(
        'organization-companies',
        'Empresas',
        'O selo Empresa identifica o segundo nível. Use o sinal de mais à esquerda do nome para abrir as unidades. O botão azul de adição à direita cadastra um estabelecimento.',
      ),
      at(
        'organization-establishments',
        'Estabelecimentos',
        'Cada unidade tem o selo Estabelecimento. A linha também mostra a situação, a classificação fiscal e o selo Principal quando aplicável.',
      ),
    ];
  } else if (path === '/conta/editar') {
    title = 'Dados da conta';
    help = 'dados-da-conta';
    steps = form([
      at(
        'business-form',
        'Dados do contratante',
        'Atualize nome, contatos e endereço do contratante. O documento e o plano são mantidos pela plataforma. Os dados fiscais das empresas ficam em Empresas.',
      ),
    ]);
  } else if (path === '/conta/empresas') {
    title = 'Empresas';
    help = 'empresas';
    steps = businessListTourSteps({
      filters: 'Busque por razão social, nome fantasia, código, CNPJ ou CPF e filtre pela situação. A lista se atualiza automaticamente.',
      table: 'Cada linha é uma empresa da conta, com a quantidade de estabelecimentos. Visualizar mostra os dados da empresa. Para abrir as unidades, expanda a empresa em Organização.',
    });
  } else if (/^\/conta\/empresas\/(cadastrar|\d+\/editar)$/.test(path)) {
    title = 'Cadastro de empresa';
    help = 'empresas';
    steps = form([
      at(
        'company-identity',
        'Identificação da empresa',
        'Escolha Pessoa jurídica ou Pessoa física. Em CNPJ, a lupa consulta os nomes oficiais. Confira o resultado e escolha se quer preencher os campos em branco. O cadastro também aceita raiz e CNPJ alfanumérico com preenchimento manual.',
      ),
      at('company-fiscal', 'Dados fiscais', 'Regime tributário e CNAE principal valem para a empresa inteira. A situação aparece na edição conforme suas permissões.'),
      at(
        'company-primary',
        'Identificação do estabelecimento',
        'Nome, endereço e contatos ficam no mesmo grupo. A empresa nasce com o estabelecimento principal, criado junto no mesmo salvamento. Com o CNPJ completo, responda: A unidade deste CNPJ é a sede ou uma filial? Escolha Matriz para a sede ou Filial. Ao informar somente a raiz, a unidade é cadastrada sem CNPJ próprio.',
      ),
    ]);
  } else if (path === '/conta/estabelecimentos') {
    title = 'Estabelecimentos';
    help = 'estabelecimentos';
    steps = businessListTourSteps({
      filters: 'Busque por nome, código ou CNPJ e filtre por situação. O filtro de empresa aparece quando a conta tem mais de uma empresa.',
      table: 'O selo Principal indica a unidade padrão da empresa. Matriz, Filial ou Sem CNPJ próprio é a classificação jurídica informada. A bandeira torna uma unidade a principal.',
    });
  } else if (/^\/conta\/estabelecimentos\/(cadastrar|\d+\/editar)$/.test(path)) {
    title = 'Cadastro de estabelecimento';
    help = 'estabelecimentos';
    steps = form([
      at(
        'establishment-identity',
        'Identificação da unidade',
        'Escolha a empresa no cadastro. Na edição a empresa fica fixa, para que registros não mudem de pessoa jurídica. Tornar principal fica no topo da tela.',
      ),
      at(
        'establishment-fiscal',
        'Identificação fiscal',
        'Matriz e Filial são a classificação fiscal que você informa, sem dedução pela ordem do CNPJ. Filiais podem ser cadastradas sem a matriz; quando ela existe e você pode visualizá-la, aparece como referência. Informe o CNPJ completo, com a mesma raiz da empresa. Para um CNPJ numérico, a lupa pode preencher os nomes vazios.',
      ),
      at('establishment-address', 'Endereço', 'Endereço da unidade, com consulta opcional de CEP.'),
      at('establishment-contact', 'Contatos', 'Telefone, WhatsApp e e-mails da unidade, no mesmo padrão do cadastro da conta.'),
    ]);
  } else if (
    /^\/conta\/etiquetas(?:\/(?:cadastrar|\d+\/editar))?$/.test(path)
  ) {
    title = 'Etiquetas';
    help = 'etiquetas';
    steps = [
      ...businessListTourSteps({
        filters: 'Busque pelo nome e filtre por módulo e situação (Ativas ou Inativas). A lista se atualiza automaticamente e preserva os demais filtros.',
        table:
          'Os marcadores usam a cor da etiqueta. Uma estrela identifica as etiquetas especiais.',
      }),
      ...tagListFormTourSteps(),
    ];
  } else if (path === '/conta/cargos') {
    title = 'Cargos';
    help = 'cargos-permissoes';
    steps = businessListTourSteps({
      filters:
        'Busque pelo nome do cargo e filtre pela origem: Cargo inicial ou Personalizado. A lista se atualiza automaticamente.',
      table:
        'Consulte os cargos e suas permissões. Use as ações de visualizar e editar conforme seu acesso.',
    });
  } else if (/^\/conta\/cargos\/(cadastrar|\d+\/editar)$/.test(path)) {
    title = 'Cadastro de cargo';
    help = 'cargos-permissoes';
    steps = form([
      at(
        'role-fields',
        'Nome do cargo',
        'Defina o nome que aparecerá no acesso de usuário.',
      ),
      at(
        'role-availability',
        'Abrangência do cargo',
        'Escolha onde o cargo pode ser atribuído. Ao restringir um cargo existente, verifique e confirme as atribuições que serão removidas.',
      ),
      at(
        'role-permissions',
        'Permissões por ação',
        'Selecione cada ação permitida. O catálogo é sincronizado pelo sistema, sem cadastro manual de permissões.',
      ),
    ]);
  } else if (path === '/conta/usuarios') {
    title = 'Usuários da conta';
    help = 'usuarios';
    steps = [
      ...businessListTourSteps({ filters: 'Busque pelo nome da pessoa ou pelo e-mail de acesso.' }),
      at(
        'user-access-actions',
        'Consultar acesso e editar atribuições',
        'Use o ícone de informação para consultar os dados de acesso e os cargos. Use o ícone de atribuições para ajustar os cargos quando tiver permissão. O detalhe também oferece suspensão ou reativação do acesso autorizado.',
      ),
    ];
  } else if (path === '/conta/usuarios/cadastrar') {
    title = 'Cadastrar usuário da conta';
    help = 'usuarios';
    steps = form([
      at('user-registration', 'Tipo de cadastro', 'Escolha Pessoa já cadastrada para buscar e reaproveitar o cadastro. Confira o e-mail de acesso e escolha a forma de acesso.'),
      at('user-assignment', 'Primeira atribuição', 'Escolha Onde e Cargo para conceder acesso no local selecionado.'),
      at('user-tags', 'Etiqueta automática', 'A etiqueta Usuário é aplicada ao cadastrar o acesso na conta.'),
    ]);
  } else if (path === '/saas/usuarios') {
    title = 'Usuários da plataforma';
    help = 'usuarios';
    steps = list('usuários', 'business-filters', 'business-list');
  } else if (path === '/saas/usuarios/cadastrar') {
    title = 'Cadastrar usuário';
    help = 'usuarios';
    steps = form([
      at(
        'business-form',
        'Acesso à plataforma',
        'Preencha a identificação e a forma de acesso do usuário SaaS.',
      ),
    ]);
  } else if (path === '/settings/profile') {
    title = 'Perfil';
    help = 'perfil';
    steps = form([
      at(
        'profile-details',
        'Seu perfil',
        'Atualize sua identificação e foto de perfil.',
      ),
      at(
        'profile-access-email',
        'E-mail de acesso',
        'Informe o novo endereço e sua senha atual. A troca ocorre depois que você abre o link no novo e-mail.',
      ),
    ]);
  } else if (path === '/settings/security') {
    title = 'Segurança';
    help = 'seguranca';
    steps = [
      at('security-password', 'Senha', 'Defina uma senha segura. Use o ícone de olho de cada campo para mostrar ou ocultar a senha.'),
      at(
        'security-passkeys',
        'Chaves de acesso',
        'Cadastre uma chave para entrar com o seu dispositivo.',
      ),
      at(
        'security-two-factor',
        'Segundo fator',
        'Adicione uma confirmação extra ao acesso e guarde os códigos de recuperação.',
      ),
    ];
  } else if (path === '/settings/appearance') {
    title = 'Aparência';
    help = 'aparencia';
    steps = [
      at(
        'settings-tabs',
        'Preferências',
        'Escolha a aparência disponível nas configurações.',
      ),
    ];
  } else if (path.endsWith('/auditoria')) {
    title = 'Auditoria';
    help = 'auditoria';
    steps = [
      ...businessListTourSteps({
        filters:
          'Filtre por tipo, ação, autor, origem e período. Empresa, Estabelecimento e Nível aparecem quando há mais de uma opção no seu acesso e só restringem o que você já pode ver. Na plataforma, filtre por conta.',
        table:
          'Cada linha é uma operação. Onde mostra a conta, a empresa ou o estabelecimento afetado; Quem fez destaca as inspeções e o usuário representado. O histórico é somente leitura.',
      }),
      ...auditDetailTourSteps(
        permissions.includes(
          (path.startsWith('/saas') ? 'platform' : 'account') +
            '.audit.technical',
        ),
      ),
    ];
  } else if (path === '/ajuda') {
    title = 'Central de Ajuda';
    steps = [
      at(
        'help-search',
        'Buscar ajuda',
        'Digite uma tarefa, assunto ou palavra usada no sistema e use Buscar ou Enter. A busca consulta somente os artigos permitidos para o seu acesso.',
      ),
      at('help-categories', 'Filtrar por categoria', 'Use Todos para consultar todos os resultados ou selecione uma categoria. Os contadores mostram quantos artigos foram encontrados em cada grupo.'),
      at(
        'help-results',
        'Artigos disponíveis',
        'Os resultados mostram título, caminho e resumo. Abra um título para consultar as instruções, inclusive a seção encontrada pela busca.',
      ),
    ];
  } else if (path.startsWith('/ajuda/')) {
    title = 'Artigo de ajuda';
    steps = [
      at(
        'help-article',
        'Como executar a tarefa',
        'Leia as instruções e use os links para navegar entre os assuntos disponíveis.',
      ),
    ];
  }
  if (!title) return;
  return {
    id: path,
    title,
    steps: [
      at(
        'account-inspection-banner',
        'Acessando como outro usuário',
        'Acima da barra superior, confira o usuário e o modo da inspeção. Use Voltar ao SaaS para encerrar e retornar à plataforma.',
      ),
      ...pageHeaderTourSteps(title),
      ...steps,
    ],
    help:
      help && permissions.includes('self.help.view')
        ? (inAccount ? '@/ajuda/' : '/ajuda/') + help
        : undefined,
  };
}

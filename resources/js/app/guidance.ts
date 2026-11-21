import { tagListFormTourSteps } from '@/app/saas/tagTours';
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
  if (path === '/user/confirm-password') {
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
          'Use Sim para criar a empresa do contratante e seu estabelecimento principal. Use Não para editar os dados sugeridos antes de salvar.',
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
        'Escolha Pessoa jurídica ou Pessoa física. Em CNPJ, use a lupa com um número completo para preencher os nomes que estiverem em branco. O cadastro também aceita raiz e CNPJ alfanumérico com preenchimento manual.',
      ),
      at('company-fiscal', 'Dados fiscais', 'Regime tributário e CNAE principal valem para a empresa inteira. A situação aparece na edição conforme suas permissões.'),
      at(
        'company-primary',
        'Estabelecimento principal',
        'A empresa nasce com o estabelecimento principal, criado junto no mesmo salvamento. Com o CNPJ completo, responda: A unidade deste CNPJ é a sede ou uma filial? Escolha Matriz para a sede ou Filial. Ao informar somente a raiz, a unidade é cadastrada sem CNPJ próprio.',
      ),
      at('company-primary-address', 'Endereço da unidade', 'O endereço e os contatos pertencem ao estabelecimento principal. A consulta de CEP é opcional.'),
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
        'Matriz e Filial são unidades da empresa selecionada. Informe o CNPJ completo da unidade, com a mesma raiz da empresa. Para um CNPJ numérico, a lupa pode preencher os nomes vazios.',
      ),
      at('establishment-address', 'Endereço', 'Endereço da unidade, com consulta opcional de CEP.'),
      at('establishment-contact', 'Contatos', 'Telefone, WhatsApp e e-mails da unidade, no mesmo padrão do cadastro da conta.'),
    ]);
  } else if (path === '/conta/pessoas') {
    title = 'Pessoas';
    help = 'pessoas';
    steps = list('pessoas', 'people-search', 'people-list');
    steps.splice(1, 0, { ...at('people-suppliers', 'Consultar por papel', 'Escolha Clientes ou Fornecedores para listar as pessoas com esse papel ativo na conta. As opções dependem das suas permissões.'), group: 'Filtros' });
  } else if (/^\/conta\/pessoas\/(cadastrar|\d+\/editar)$/.test(path)) {
    title = 'Cadastro de pessoa';
    help = 'pessoas';
    steps = form([
      at(
        'person-identity',
        'Dados pessoais',
        'Escolha Pessoa física ou Pessoa jurídica e informe o nome ou razão social. CPF ou CNPJ, nome fantasia e inscrições pertencem à pessoa e não são repetidos por etiqueta.',
      ),
      at('person-address', 'Endereço da pessoa', 'O endereço principal pertence à pessoa e é compartilhado por seus papéis. A consulta de CEP é opcional; confira os dados antes de salvar.'),
      at('person-contact', 'Contatos da pessoa', 'Telefone e WhatsApp usam máscaras e ícones do padrão da conta. Use os botões de adicionar Telefone e E-mail para contatos adicionais; o X remove a linha. O e-mail principal continua sendo o acesso do usuário.'),
      at(
        'person-tags',
        'Etiquetas',
        'As etiquetas representam os papéis da mesma pessoa. Cliente e Fornecedor compartilham CPF/CNPJ, contatos e endereço. A mesma pessoa pode ter os dois papéis. Usuário revela a seção de acesso. Retirar uma etiqueta especial e salvar desativa sua função, conforme suas permissões.',
      ),
      at(
        'person-access',
        'Acesso de usuário',
        'Escolha um cargo da conta e a forma de acesso. Esta seção aparece apenas com a etiqueta Usuário.',
      ),
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
        'Visualizar usuário e editar pessoa',
        'Use o ícone de informação para consultar os dados de acesso e os cargos. Use o lápis para abrir o cadastro em Pessoas, conforme suas permissões.',
      ),
    ];
  } else if (path === '/conta/usuarios/cadastrar') {
    title = 'Cadastrar usuário da conta';
    help = 'usuarios';
    steps = form([
      at('user-assignment', 'Primeira atribuição', 'Escolha Onde e Cargo para conceder acesso no local selecionado.'),
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
          'Filtre por autor, tipo, ação e período. Na plataforma também é possível filtrar por conta.',
        table:
          'Cada linha é uma operação. O histórico é somente leitura. Use a ação Visualizar registro para consultar o que mudou.',
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
        ? '/ajuda/' + help
        : undefined,
  };
}

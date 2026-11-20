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
        'Informe sua senha atual para abrir as configurações de segurança.',
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
        'Preencha os dados do contratante, endereço, contatos e o acesso inicial do administrador. Na seção Primeira empresa, confirme a identidade com o contratante somente quando estiver comprovada.',
      ),
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
      table: 'Cada linha é uma pessoa jurídica da conta, com a quantidade de estabelecimentos. Visualizar mostra os dados e as unidades da empresa.',
    });
  } else if (/^\/conta\/empresas\/(cadastrar|\d+\/editar)$/.test(path)) {
    title = 'Cadastro de empresa';
    help = 'empresas';
    steps = form([
      at(
        'company-identity',
        'Identificação da empresa',
        'Escolha Pessoa jurídica ou Pessoa física e informe a razão social ou o nome. O CNPJ aceita o número completo ou a raiz com 8 caracteres, inclusive alfanumérico. O código é gerado pelo nome fantasia e não muda depois do cadastro.',
      ),
      at('company-fiscal', 'Dados fiscais', 'Regime tributário e CNAE principal valem para a empresa inteira. A situação aparece na edição conforme suas permissões.'),
      at(
        'company-primary',
        'Estabelecimento principal',
        'A empresa nasce com o estabelecimento principal, criado junto no mesmo salvamento. Com o CNPJ completo, escolha se ele é a matriz ou uma filial.',
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
        'Matriz e Filial exigem CNPJ com a mesma raiz da empresa. A empresa tem no máximo uma matriz. O principal fica sempre ativo.',
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
        'role-permissions',
        'Permissões por ação',
        'Selecione cada ação permitida. O catálogo é sincronizado pelo sistema, sem cadastro manual de permissões.',
      ),
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
    ]);
  } else if (path === '/settings/security') {
    title = 'Segurança';
    help = 'seguranca';
    steps = [
      at('security-password', 'Senha', 'Defina uma senha segura.'),
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
    steps: [...pageHeaderTourSteps(title), ...steps],
    help:
      help && permissions.includes('self.help.view')
        ? '/ajuda/' + help
        : undefined,
  };
}

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
        'Preencha os dados do estabelecimento, contato, endereço e acesso inicial do administrador.',
      ),
    ]);
  } else if (path === '/conta') {
    title = 'Minha conta';
    help = 'minha-conta';
    steps = [
      at(
        'page-title',
        'Minha conta',
        'Consulte os dados do estabelecimento. As ações disponíveis dependem das suas permissões.',
      ),
      at(
        'tenant-points',
        'Pontos',
        'Consulte e cadastre os pontos do estabelecimento conforme sua permissão.',
      ),
    ];
  } else if (path === '/conta/editar') {
    title = 'Editar estabelecimento';
    help = 'minha-conta';
    steps = form([
      at(
        'business-form',
        'Dados do estabelecimento',
        'Atualize identificação, contatos e endereço da sua conta.',
      ),
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

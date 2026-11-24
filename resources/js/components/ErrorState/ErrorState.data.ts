import type { ErrorCode, ErrorDescription } from './ErrorState.types';

export const errorDescriptions: Record<ErrorCode, ErrorDescription> = {
  '400': {
    title: 'Solicitação inválida',
    paragraphs: ['Não foi possível processar a solicitação. Confira os dados e tente novamente.'],
  },
  '403': {
    title: 'Acesso restrito',
    paragraphs: [
      'Você não tem as permissões necessárias para acessar este recurso.',
      'Consulte o administrador caso acredite que isso é um erro.',
    ],
  },
  '404': {
    title: 'Página não encontrada',
    paragraphs: [
      'O endereço acessado pode estar incorreto, ou a página não está disponível para o seu acesso.',
    ],
  },
  '419': {
    title: 'Sessão expirada',
    paragraphs: ['Sua sessão expirou. Atualize a página e entre novamente para continuar.'],
  },
  '429': {
    title: 'Muitas solicitações',
    paragraphs: ['O limite de solicitações foi atingido. Aguarde alguns instantes antes de tentar novamente.'],
  },
  '500': {
    title: 'Erro interno no servidor',
    paragraphs: ['Ocorreu um problema ao carregar esta página. Tente novamente em alguns instantes.'],
  },
  '503': {
    title: 'Serviço indisponível',
    paragraphs: ['O sistema está temporariamente indisponível. Volte em breve para tentar novamente.'],
  },
};

export const errorStateCopy = { home: 'Voltar ao início', label: 'Erro' };

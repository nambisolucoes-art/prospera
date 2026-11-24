export function organizationFormNavigation(url: string, companyId?: number) {
  const params = new URLSearchParams(url.split('?')[1]);
  const fromTree = params.get('origem') === 'organizacao';

  return {
    suffix: fromTree ? '?origem=organizacao' : '',
    back: fromTree ? `@/organizacao?${companyId ? `empresa=${companyId}` : 'nivel=conta'}` : undefined,
  };
}

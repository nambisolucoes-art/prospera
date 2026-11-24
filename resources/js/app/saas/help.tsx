import { useEffect, useState } from 'react';
import SaasLayout from './SaasLayout';
import CardBox from '@/components/CardBox/CardBox';
import SearchInput from '@/components/SearchInput/SearchInput';
import SearchResults from '@/components/SearchResults/SearchResults';
import SearchResultItem from '@/components/SearchResultItem/SearchResultItem';
import Badge from '@/components/Badge/Badge';
import type { TabItem } from '@/components/Tabs/Tabs';
import Link from '@/components/ApiLink/ApiLink';
import { useApiPage } from '@/hooks/useApiPage';
import { accountBase } from '@/utils/organizationContext';
import type { SessionProps } from '@/types/Session.types';
import './Guidance.css';
type Article = {
  slug: string;
  title: string;
  summary: string;
  category: string;
  anchor?: string;
  html?: string;
};
export default function Help({
  articles = [],
  article,
  search = '',
}: {
  articles?: Article[];
  article?: Article;
  search?: string;
}) {
  const { props, navigate, url } = useApiPage<SessionProps>();
  // Na conta, a ajuda mostra os artigos permitidos nela; fora dela, os da identidade (E09).
  const helpBase = `${accountBase(url) ?? ''}/ajuda`;
  const [query, setQuery] = useState(search);
  const [busy, setBusy] = useState(false);
  const canView = props.auth.permissions.includes('self.help.view');
  function resultList(results: Article[]) {
    return results.length ? results.map((result) => {
      const href = helpBase + '/' + result.slug + (result.anchor ? '#' + result.anchor : '');
      return <SearchResultItem key={result.slug}
        result={{ title: result.title, url: ['Ajuda', result.category, result.title].join(' › '), description: result.summary }}
        title={canView ? <Link href={href}>{result.title}</Link> : result.title} />;
    }) : <p className="help-empty" role="status">{search ? 'Nenhum artigo encontrado. Tente outras palavras.' : 'Nenhum artigo disponível para o seu acesso.'}</p>;
  }
  const categories = [...new Set(articles.map((result) => result.category))];
  const resultTabs: TabItem[] = [
    { id: 'all', accessibleLabel: `Todos os resultados, ${articles.length} artigos`,
      label: <><b>Todos</b><Badge variant="primary" ariaHidden>{articles.length}</Badge></>, content: resultList(articles) },
    ...categories.map((category, index) => {
      const results = articles.filter((result) => result.category === category);
      return { id: category, accessibleLabel: `${category}, ${results.length} artigos`,
        label: <><b>{category}</b><Badge variant={index % 2 ? 'info' : 'pink'} ariaHidden>{results.length}</Badge></>,
        content: resultList(results) };
    }),
  ];
  useEffect(() => setQuery(search), [search]);
  useEffect(() => {
    if (!article) return;
    const frame = requestAnimationFrame(() => {
      const id = decodeURIComponent(location.hash.slice(1));
      if (id) document.getElementById(id)?.scrollIntoView({ block: 'start' });
    });
    return () => cancelAnimationFrame(frame);
  }, [article]);
  return (
    <SaasLayout
      title={article?.title ?? 'Central de Ajuda'}
      tenant={!props.auth.user?.is_saas_admin}
      breadcrumb={[
        { label: 'Central de Ajuda', href: helpBase },
        ...(article ? [{ label: article.title }] : []),
      ]}
    >
      {article ? (
        <CardBox>
          <article className="help-article" data-tour="help-article">
            <p className="help-summary">{article.summary}</p>
            <div
              dangerouslySetInnerHTML={{
                __html: article.html ?? '',
              }}
            />
            <Link href={helpBase}>Voltar à Central de Ajuda</Link>
          </article>
        </CardBox>
      ) : (
        <div className="help-results" data-tour="help-results">
          <SearchResults
            key={search}
            search={<div data-tour="help-search">
              <SearchInput
                placeholder="Como podemos ajudar?"
                label="Buscar na ajuda"
                value={query}
                onChange={setQuery}
                busy={busy}
                buttonTone="primary"
                onSearch={(value) => {
                  setBusy(true);
                  void navigate(helpBase + (value.trim() ? '?search=' + encodeURIComponent(value.trim()) : ''), { preserveScroll: true })
                    .finally(() => setBusy(false));
                }}
              />
            </div>}
            heading={search ? `Resultados da busca por “${search}”` : 'Todos os artigos da Central de Ajuda'}
            summary={`${articles.length} ${articles.length === 1 ? 'artigo encontrado' : 'artigos encontrados'}.`}
            items={resultTabs}
            tabsTourTarget="help-categories"
            tabsVariant="framed"
          />
        </div>
      )}
    </SaasLayout>
  );
}

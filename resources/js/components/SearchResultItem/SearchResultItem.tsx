import type { ReactNode } from 'react';
import "./SearchResultItem.css";
import type { SearchResult } from "./SearchResultItem.types";
export default function SearchResultItem({
  result,
  title,
}: {
  result: SearchResult;
  title?: ReactNode;
}): JSX.Element {
  return (
    <article className="searchResultItem">
      <h3>
        {title ?? <a href="#">{result.title}</a>}
      </h3>
      <div className="searchResultItem__url">{result.url}</div>
      <p>{result.description}</p>
    </article>
  );
}

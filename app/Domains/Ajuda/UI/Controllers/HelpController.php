<?php

declare(strict_types=1);

namespace App\Domains\Ajuda\UI\Controllers;

use App\Domains\Ajuda\Application\Data\HelpSearchData;
use App\Domains\Ajuda\Application\Queries\HelpCatalog;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class HelpController
{
    public function index(Request $r, HelpCatalog $catalog): JsonResponse|View
    {
        $search = HelpSearchData::fromInput($r->query());
        $terms = preg_split('/\s+/', Str::lower(Str::ascii($search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $articles = array_values(array_filter($catalog->articles($r->user()), function ($a) use ($terms) {
            $text = $this->searchable(implode(' ', [...Arr::except($a, ['body']), $a['body']]));
            foreach ($terms as $term) {
                if (! str_contains($text, $term)) {
                    return false;
                }
            }

            return true;
        }));

        $results = array_map(function ($article) use ($terms) {
            $result = array_intersect_key($article, array_flip(['slug', 'title', 'summary', 'category']));
            $result['anchor'] = '';
            if ($terms) {
                preg_match_all('/<h2([^>]*)>(.*?)<\/h2>(.*?)(?=<h2|\z)/s', $article['body'], $sections, PREG_SET_ORDER);
                foreach ($sections as $section) {
                    $text = $this->searchable($section[2].' '.$section[3]);
                    if (count(array_filter($terms, fn ($term) => str_contains($text, $term))) === count($terms)) {
                        $found = preg_match('/\bid="([^"]+)"/', $section[1], $id);
                        $result['anchor'] = $found ? $id[1] : Str::slug(strip_tags($section[2]));
                        break;
                    }
                }
            }

            return $result;
        }, $articles);

        return ApiPage::respond(['articles' => $results, 'search' => $search]);
    }

    public function show(Request $r, HelpCatalog $catalog, string $slug): JsonResponse|View
    {
        $article = collect($catalog->articles($r->user()))->firstWhere('slug', $slug);
        abort_unless($article !== null, 404);

        return ApiPage::respond(['article' => $catalog->render($article, $r->user())]);
    }

    /** Texto em minúsculas e sem acentos nem marcação, para a busca. */
    private function searchable(string $html): string
    {
        return Str::lower(Str::ascii(html_entity_decode(strip_tags($html))));
    }
}

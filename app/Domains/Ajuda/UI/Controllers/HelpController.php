<?php

namespace App\Domains\Ajuda\UI\Controllers;

use App\Domains\Ajuda\Application\Data\HelpSearchData;
use App\Domains\Ajuda\Application\Queries\HelpCatalog;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class HelpController
{
    public function index(Request $r, HelpCatalog $catalog): JsonResponse|View
    {
        $search = HelpSearchData::fromInput($r->query());
        $terms = preg_split('/\s+/', Str::lower(Str::ascii($search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $articles = array_values(array_filter($catalog->articles($r->user()), function ($a) use ($terms) {
            $text = Str::lower(Str::ascii(implode(' ', $a)));
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
                preg_match_all('/^## ([^\r\n]+)\R(.*?)(?=^## |\z)/ms', $article['body'], $sections, PREG_SET_ORDER);
                foreach ($sections as $section) {
                    $text = Str::lower(Str::ascii($section[0]));
                    if (count(array_filter($terms, fn ($term) => str_contains($text, $term))) === count($terms)) {
                        $result['anchor'] = Str::slug($section[1]);
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
}

<?php

declare(strict_types=1);

namespace App\Domains\Ajuda\Application\Queries;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class HelpCatalog
{
    /** @return list<array<string,mixed>> */
    public function articles(User $user): array
    {
        $result = [];
        foreach (File::glob(base_path('docs/ajuda/*.html')) as $path) {
            $slug = basename($path, '.html');
            if (! $this->allowed($user, $slug)) {
                continue;
            }

            $source = (string) file_get_contents($path);

            // Cada artigo é um HTML com a tipografia do Ubold e um comentário de metadados no topo.
            if (! preg_match('/\A<!--\R(.*?)\R-->\R(.*)\z/s', $source, $m)) {
                continue;
            }

            $meta = [];

            foreach (explode("\n", $m[1]) as $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $meta[trim($parts[0])] = trim($parts[1]);
                }
            }
            $body = $m[2];

            $result[] = [
                'slug' => $slug,
                'title' => $meta['titulo'] ?? $slug,
                'summary' => $meta['resumo'] ?? '',
                'category' => $meta['categoria'] ?? 'Sistema',
                'keywords' => $meta['palavras-chave'] ?? '',
                'body' => $body,
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $result;
    }

    private function allowed(User $user, string $slug): bool
    {
        // O artigo aparece quando a pessoa tem alguma permissão do módulo.
        $can = fn (string $module): bool => count(array_filter(
            app(EffectivePermissions::class)->visibleSlugsFor($user),
            fn ($permission) => str_starts_with($permission, $module.'.'),
        )) > 0;

        return match ($slug) {
            'acesso-contas' => ! $user->is_saas_admin && $user->can('self.help.view'),
            'central-de-ajuda' => $user->can('self.help.list'),
            'contas' => $user->is_saas_admin && $can('platform.accounts'),
            'usuarios' => $user->is_saas_admin ? $can('platform.users') : $can('account.users'),
            'inspecao-de-contas' => $user->is_saas_admin && $user->can('platform.inspection.start'),
            'dados-da-conta' => ! $user->is_saas_admin
                && ($can('account.dashboard') || $can('account.account-data')),
            'organizacao' => ! $user->is_saas_admin
                && ($user->can('account.account-data.update') || $can('account.companies')
                    || $user->can('account.companies.create') || $can('account.establishments')),
            'empresas' => ! $user->is_saas_admin && $can('account.companies'),
            'estabelecimentos' => ! $user->is_saas_admin && $can('account.establishments'),
            // Quem tem algum cargo em uma unidade navega por contexto, mesmo sem cadastrar estabelecimentos.
            'estabelecimento-atual' => ! $user->is_saas_admin
                && ! app(EffectivePermissions::class)->navigableNodes($user)->isEmpty(),
            'clientes' => ! $user->is_saas_admin && $can('account.customers'),
            'fornecedores' => ! $user->is_saas_admin && $can('account.suppliers'),
            'etiquetas' => ! $user->is_saas_admin && $can('account.tags'),
            'perfil-institucional' => ! $user->is_saas_admin && $can('account.institutional'),
            'colaboradores' => ! $user->is_saas_admin && $can('account.employees'),
            'departamentos-funcoes' => ! $user->is_saas_admin
                && ($can('account.departments') || $can('account.job-titles')),
            'cargos-permissoes' => ! $user->is_saas_admin && $can('account.roles'),
            'perfil' => $user->can('self.profile.view'),
            'seguranca' => $user->can('self.security.view'),
            'aparencia' => $user->can('self.appearance.view'),
            'auditoria' => $user->is_saas_admin ? $user->can('platform.audit.list') : $can('account.audit'),
            default => false
        };
    }

    /**
     * @param  array<string,mixed>  $article
     * @return array<string,mixed>
     */
    public function render(array $article, User $user): array
    {
        unset($article['keywords']);
        $article['html'] = $this->sanitized($article['body']);
        $ids = [];
        $article['html'] = preg_replace_callback('/<h([1-6])([^>]*)>(.*?)<\/h\1>/s', function ($match) use (&$ids) {
            if (preg_match('/\bid="([^"]+)"/', $match[2], $existing)) {
                $ids[$existing[1]] = 1;

                return $match[0];
            }

            $base = Str::slug(html_entity_decode(strip_tags($match[3])));
            $count = $ids[$base] = ($ids[$base] ?? 0) + 1;
            $id = $base.($count > 1 ? '-'.$count : '');

            return '<h'.$match[1].' id="'.e($id).'"'.$match[2].'>'.$match[3].'</h'.$match[1].'>';
        }, $article['html']);
        // Links para artigos que a pessoa não pode abrir viram texto simples.
        $article['html'] = preg_replace_callback(
            '/<a href="\/ajuda\/([a-z0-9-]+)(?:#[^"]*)?">(.*?)<\/a>/s',
            fn ($match) => $user->can('self.help.view') && $this->allowed($user, $match[1]) ? $match[0] : $match[2],
            $article['html'],
        );

        // Dentro de uma conta, os links continuam na ajuda dessa conta (E09).
        if (app(AccountContext::class)->linkedId($user) !== null) {
            $base = app(AccountContext::class)->path('ajuda');
            $article['html'] = str_replace('<a href="/ajuda/', '<a href="'.$base.'/', $article['html']);
        }

        unset($article['body']);

        return $article;
    }

    /** O HTML dos artigos é versionado, mas nunca executa script, carrega objeto externo ou usa endereço inseguro. */
    private function sanitized(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b.*?</\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*>#i', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

        return preg_replace('/\s(href|src)\s*=\s*(["\'])\s*(?:javascript|data|vbscript):[^"\']*\2/i', '', $html) ?? '';
    }
}

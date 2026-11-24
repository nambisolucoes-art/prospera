<?php

namespace Tests\Performance;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class OctaneMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        // Identifica a instância PHP do worker, inclusive quando threads compartilham o PID.
        static $worker = null;
        $worker ??= bin2hex(random_bytes(8));

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        memory_reset_peak_usage();
        $memory = memory_get_usage();
        $start = hrtime(true);

        try {
            $response = $next($request);
            $queries = $connection->getQueryLog();
            $signatures = array_map(fn ($query) => serialize([$query['query'], $query['bindings']]), $queries);
            $permissionQueries = array_filter(
                $queries,
                fn ($query) => str_starts_with($query['query'], 'select "slug" from "permissions"')
                    || str_contains($query['query'], 'from "permission_role" as "pr"'),
            );
            $body = $response->getContent();
            $data = is_string($body) ? json_decode($body, true) : null;

            $metrics = [
                'worker' => $worker,
                'queries' => count($queries),
                'permission_queries' => count($permissionQueries),
                'assignment_queries' => count(array_filter(
                    $queries,
                    fn ($query) => str_contains($query['query'], 'from "role_assignments"')
                        && str_contains($query['query'], 'join "clients" as "c"'),
                )),
                'duplicates' => count($queries) - count(array_unique($signatures)),
                // Só o texto SQL das assinaturas repetidas, sem parâmetros, para explicar a duplicação.
                'duplicate_sql' => array_values(array_unique(array_map(
                    fn (string $signature): string => mb_substr((string) unserialize($signature)[0], 0, 160),
                    array_diff_assoc($signatures, array_unique($signatures)),
                ))),
                'db_ms' => array_sum(array_column($queries, 'time')),
                'php_ms' => (hrtime(true) - $start) / 1e6,
                'start_bytes' => $memory,
                'peak_delta_bytes' => memory_get_peak_usage() - $memory,
                'permission_slugs' => count($data['auth']['permissions'] ?? []),
            ];
            $response->headers->set('X-E03-Metrics', json_encode($metrics, JSON_THROW_ON_ERROR));

            return $response;
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }
}

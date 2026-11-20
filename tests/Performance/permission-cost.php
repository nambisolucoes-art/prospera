<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$queries = [];
DB::listen(function (QueryExecuted $query) use (&$queries): void {
    $queries[] = $query;
});

foreach ([1, 41, 42] as $userId) {
    $user = User::findOrFail($userId);

    for ($sample = 0; $sample < 10; $sample++) {
        $app->forgetScopedInstances();
        $queries = [];
        memory_reset_peak_usage();
        $memory = memory_get_usage();
        $start = hrtime(true);

        for ($check = 0; $check < 12; $check++) {
            $user->can('account.dashboard.view');
        }
        $slugs = $user->permissionSlugs();
        $time = (hrtime(true) - $start) / 1e6;
        $peak = memory_get_peak_usage() - $memory;
        $permissionQueries = array_filter(
            $queries,
            fn (QueryExecuted $query) => str_contains($query->sql, 'from "permissions"'),
        );
        $db = array_sum(array_column($queries, 'time'));

        echo json_encode([
            'probe_slugs' => count($slugs),
            'checks_and_payload' => 13,
            'auth_queries' => count($queries),
            'permission_rows' => count($permissionQueries) * count($slugs),
            'account_rows' => count($queries) - count($permissionQueries),
            'db_ms' => $db,
            'check_ms' => $time,
            'check_minus_db_ms' => $time - $db,
            'peak_delta_bytes' => $peak,
        ], JSON_THROW_ON_ERROR).PHP_EOL;
    }
}

if (getenv('E03_INDEX_PLANS') === '1') {
    $query = reset($permissionQueries);

    DB::beginTransaction();

    try {
        foreach (['with index', 'without index'] as $mode) {
            if ($mode === 'without index') {
                DB::statement('DROP INDEX permission_role_permission_id_index');
            }

            echo 'Effective permissions: '.$mode.PHP_EOL;
            $plan = DB::select('EXPLAIN (ANALYZE, BUFFERS) '.$query->sql, $query->bindings);

            foreach ($plan as $line) {
                echo $line->{'QUERY PLAN'}.PHP_EOL;
            }
        }
    } finally {
        DB::rollBack();
    }
}

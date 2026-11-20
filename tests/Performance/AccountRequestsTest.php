<?php

namespace Tests\Performance;

use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('performance')]
final class AccountRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_pages(): void
    {
        $account = Client::create(['name' => 'Conta sintética', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $role = Role::where('account_id', $account->id)->where('system_key', 'member')->sole();
        User::factory()->count(39)->forAccount($account->id, $role)->create();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query;
        });

        foreach ([
            '/conta',
            '/conta/pessoas',
            '/conta/pessoas/cadastrar',
            '/conta/etiquetas',
            '/conta/cargos',
            '/conta/cargos/cadastrar',
            '/conta/auditoria',
            '/conta/usuarios',
            '/ajuda',
        ] as $path) {
            $queries = [];
            memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            $response = $this->actingAs($owner)->getJson($path);
            $elapsed = (hrtime(true) - $start) / 1e6;
            $response->assertOk();
            $signatures = array_map(fn (QueryExecuted $query) => serialize([$query->sql, $query->bindings]), $queries);
            $permissionQueries = count(array_filter(
                $queries,
                fn (QueryExecuted $query) => str_starts_with($query->sql, 'select "slug" from "permissions"')
                    || str_contains($query->sql, 'from "permission_role" as "pr"'),
            ));

            if (! getenv('E03_BASELINE')) {
                self::assertSame(1, $permissionQueries, $path);
                self::assertCount(count(array_unique($signatures)), $queries, $path);
                // As telas da conta somam 1 consulta do seletor de estabelecimento (E06, meta 15.3.1).
                self::assertLessThanOrEqual($path === '/ajuda' ? 2 : 10, count($queries), $path);
                self::assertLessThanOrEqual(1, count(array_filter(
                    $queries,
                    fn (QueryExecuted $query) => str_contains($query->sql, 'from "establishments" as "e"'),
                )), $path);
            }

            fwrite(STDERR, json_encode([
                'path' => $path,
                'queries' => count($queries),
                'permission_queries' => $permissionQueries,
                'duplicates' => count($queries) - count(array_unique($signatures)),
                'db_ms' => array_sum(array_column($queries, 'time')),
                'request_ms' => $elapsed,
                'peak_delta_bytes' => memory_get_peak_usage() - $memory,
                'permission_slugs' => count($response->json('auth.permissions')),
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }
}

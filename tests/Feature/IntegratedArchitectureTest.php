<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Application\Actions\Companies\CreateCompanyAction;
use App\Domains\Organizacao\Application\Data\Companies\CreateCompanyData;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regressões encontradas na homologação integrada da E10, entre módulos já homologados. */
final class IntegratedArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_catalog_is_readable_by_any_assignment_with_the_permission(): void
    {
        $account = Client::create(['name' => 'Conta de etiquetas', 'active' => true]);
        $other = Client::create(['name' => 'Outra conta', 'active' => true]);
        $company = $this->company($account, 'Empresa A', '11222333');
        $unit = $company->primaryEstablishment()->sole();
        $tag = Tag::create(['account_id' => $account->id, 'name' => 'Cliente VIP', 'module' => 'customers', 'color' => '#123456']);
        $foreign = Tag::create(['account_id' => $other->id, 'name' => 'Alheia', 'module' => 'customers', 'color' => '#654321']);
        $cashier = $this->role($account, 'Caixa', ['account.tags.list', 'account.tags.view']);
        $operator = User::factory()->forAccount($account->id)->create();
        $membership = $this->membership($operator);
        $membership->assignments()->delete();
        RoleAssignment::grant($membership, $cashier, AccessNode::establishment($account->id, $company->id, $unit->id), null)
            ->saveOrFail();

        $listing = $this->actingAs($operator)->getJson('/conta/etiquetas')->assertOk();
        $this->assertContains('Cliente VIP', collect($listing->json('tags.data'))->pluck('name')->all());
        $this->assertNotContains('Alheia', collect($listing->json('tags.data'))->pluck('name')->all());
        $this->assertContains('account.tags.list', $listing->json('auth.permissions'));
        $this->assertContains('account.tags.view', $listing->json('auth.permissions'));
        $this->assertNotContains('account.tags.create', $listing->json('auth.permissions'));
        $this->getJson('/conta/etiquetas/'.$tag->id.'/visualizar')->assertOk()->assertJsonPath('name', 'Cliente VIP');
        $this->getJson('/conta/etiquetas/'.$foreign->id.'/visualizar')->assertNotFound();

        // Leitura não concede manutenção do catálogo, que continua sendo da conta.
        $this->postJson('/conta/etiquetas', ['name' => 'Nova', 'module' => 'customers', 'color' => '#000000'])
            ->assertForbidden();
        $this->patchJson('/conta/etiquetas/'.$tag->id, ['name' => 'Alterada', 'module' => 'customers', 'color' => '#000000'])
            ->assertForbidden();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertForbidden();
        $this->assertSame('Cliente VIP', $tag->fresh()->name);

        // Sem a permissão em nenhum nó, a listagem e a visualização continuam negadas.
        $viewer = User::factory()->forAccount($account->id)->create();
        $viewerMembership = $this->membership($viewer);
        $viewerMembership->assignments()->delete();
        RoleAssignment::grant(
            $viewerMembership,
            $this->role($account, 'Unidades', ['account.establishments.list']),
            AccessNode::establishment($account->id, $company->id, $unit->id),
            null,
        )->saveOrFail();
        $this->actingAs($viewer)->getJson('/conta/etiquetas')->assertForbidden();
        $this->getJson('/conta/etiquetas/'.$tag->id.'/visualizar')->assertForbidden();
    }

    /** @param list<string> $slugs */
    private function role(Client $account, string $name, array $slugs): Role
    {
        $role = Role::create(['name' => $name, 'account_id' => $account->id]);
        $role->permissions()->attach(Permission::whereIn('slug', $slugs)->pluck('id'));

        return $role;
    }

    private function company(Client $account, string $name, string $root): Company
    {
        $data = CreateCompanyData::fromInput([
            'name' => $name,
            'person_type' => 'company',
            'document' => $root,
        ], $account->id);

        return app(CreateCompanyAction::class)->execute($data, $account->id);
    }
}

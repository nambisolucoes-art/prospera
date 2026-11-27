<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Institucional\Application\Actions\Categories\CreatePublicCategoryAction;
use App\Domains\Institucional\Application\Actions\Categories\UpdatePublicCategoryAction;
use App\Domains\Institucional\Application\Context\ProfileResolver;
use App\Domains\Institucional\Application\Data\Categories\CreatePublicCategoryData;
use App\Domains\Institucional\Application\Data\Categories\UpdatePublicCategoryData;
use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\Enums\ProfileState;
use App\Domains\Institucional\Domain\Models\ProfileSection;
use App\Domains\Institucional\Domain\Models\PublicCategory;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * S06 do plano 002: perfil institucional com herança por seção, rascunho e publicado.
 */
class InstitutionalProfileTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private Establishment $sede;

    private Establishment $guanambi;

    private Establishment $caetite;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->sede = $this->roupas->primaryEstablishment()->sole();
        $this->guanambi = $this->unit($this->roupas, 'Guanambi');
        $this->caetite = $this->unit($this->roupas, 'Caetité');
        $this->owner = $this->administrator($this->gartan);

        $this->actingAs($this->owner);
    }

    private function companyUrl(Company|string|null $company = null, string $suffix = ''): string
    {
        $company = $company instanceof Company ? $company : $this->roupas;

        return '/conta/empresa/'.$company->code.'/institucional'.$suffix;
    }

    private function unitUrl(Establishment $unit, string $suffix = ''): string
    {
        return '/conta/estabelecimento/'.$unit->code.'/institucional'.$suffix;
    }

    private function resolver(): ProfileResolver
    {
        $this->app->forgetScopedInstances();

        return app(ProfileResolver::class);
    }

    private function companyOwner(?Company $company = null): ProfileOwner
    {
        return ProfileOwner::company($this->gartan->id, ($company ?? $this->roupas)->id);
    }

    private function unitOwner(Establishment $unit): ProfileOwner
    {
        return ProfileOwner::establishment($this->gartan->id, $unit->company_id, $unit->id);
    }

    /** @param array<string,mixed> $value */
    private function saveCompany(string $section, array $value, ?Company $company = null): void
    {
        $this->patchJson($this->companyUrl($company, '/secoes/'.$section), ['mode' => 'override', 'value' => $value])
            ->assertNoContent();
    }

    /** @param array<string,mixed>|null $value */
    private function saveUnit(Establishment $unit, string $section, string $mode, ?array $value = null)
    {
        return $this->patchJson($this->unitUrl($unit, '/secoes/'.$section), array_filter(
            ['mode' => $mode, 'value' => $value],
            fn ($item) => $item !== null,
        ));
    }

    /** Sem números, confirma exatamente o resumo que o servidor calcula, como a tela faz. */
    private function publish(string $url, array $sections, ?int $inheriting = null, ?int $overriding = null)
    {
        if ($inheriting === null || $overriding === null) {
            $query = implode('&', array_map(fn (string $section) => 'secoes[]='.$section, $sections));
            $impact = $this->getJson(str_replace('/publicar', '/impacto', $url).'?'.$query)->json();
            $inheriting = $impact['inheriting'] ?? 0;
            $overriding = $impact['overriding'] ?? 0;
        }

        return $this->postJson($url, ['sections' => $sections, 'inheriting' => $inheriting, 'overriding' => $overriding]);
    }

    /** @param list<string> $slugs */
    private function managerOf(Company|Establishment $node, array $slugs): User
    {
        static $sequence = 0;

        $role = $this->role($this->gartan, 'Gestor institucional '.++$sequence, $slugs);
        $access = $node instanceof Company
            ? AccessNode::company($this->gartan->id, $node->id)
            : AccessNode::establishment($this->gartan->id, $node->company_id, $node->id);

        return $this->operator($this->gartan, [[$role, $access]]);
    }

    public function test_the_company_edit_is_a_draft_until_it_is_published(): void
    {
        $default = ProfileSectionType::About->defaultValue();
        $this->assertSame($default, $this->resolver()->resolve($this->companyOwner(), ProfileSectionType::About, ProfileState::Draft)->value);

        $this->saveCompany('about', ['title' => 'Gartan Roupas', 'description' => 'Moda para a família.']);

        $draft = $this->resolver()->resolve($this->companyOwner(), ProfileSectionType::About, ProfileState::Draft);
        $published = $this->resolver()->resolve($this->companyOwner(), ProfileSectionType::About, ProfileState::Published);

        $this->assertSame('Gartan Roupas', $draft->value['title']);
        $this->assertSame('company', $draft->source);
        $this->assertSame($default, $published->value);
        $this->assertSame('platform', $published->source);

        $page = $this->getJson($this->companyUrl())->assertOk();
        $about = collect($page->json('sections'))->firstWhere('key', 'about');
        $this->assertTrue($about['pending']);

        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertNoContent();

        $published = $this->resolver()->resolve($this->companyOwner(), ProfileSectionType::About, ProfileState::Published);
        $this->assertSame('Gartan Roupas', $published->value['title']);
        $this->assertSame('company', $published->source);

        // Editar depois não altera o que já está publicado.
        $this->saveCompany('about', ['title' => 'Novo título']);
        $this->assertSame('Gartan Roupas', $this->resolver()
            ->resolve($this->companyOwner(), ProfileSectionType::About, ProfileState::Published)->value['title']);
        $this->assertSame('Novo título', $this->resolver()
            ->resolve($this->companyOwner(), ProfileSectionType::About, ProfileState::Draft)->value['title']);
    }

    public function test_several_sections_publish_together_with_the_same_operation(): void
    {
        $this->saveCompany('about', ['title' => 'Sobre nós']);
        $this->saveCompany('seo_defaults', ['title' => 'Gartan', 'description' => 'Loja de roupas.']);
        $this->saveCompany('typography', ['heading' => 'source-sans-pro']);

        $this->publish($this->companyUrl('', '/publicar'), ['about', 'seo_defaults', 'typography'])->assertNoContent();

        foreach (['about', 'seo_defaults', 'typography'] as $section) {
            $row = ProfileSection::where('company_id', $this->roupas->id)->where('section', $section)->sole();
            $this->assertSame('override', $row->published_mode);
            $this->assertNotNull($row->published_at);
            $this->assertSame($this->owner->id, $row->published_by);
        }

        $events = AuditRecord::where('entity_type', 'profile-section')->where('event', 'published')->get();
        $this->assertCount(3, $events);
        $this->assertSame(1, $events->pluck('operation_id')->unique()->count());
        $this->assertSame([$this->roupas->id], $events->pluck('company_id')->unique()->values()->all());
        $this->assertNull($events->first()->establishment_id);
    }

    public function test_inheritance_follows_draft_and_published_and_publishing_does_not_touch_overrides(): void
    {
        $this->saveCompany('about', ['title' => 'Versão 1']);
        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertNoContent();

        // Unidade B personaliza e publica; a unidade A herda.
        $this->saveUnit($this->caetite, 'about', 'override', ['title' => 'Caetité própria'])->assertNoContent();
        $this->publish($this->unitUrl($this->caetite, '/publicar'), ['about'])->assertNoContent();

        // A empresa altera o rascunho: a prévia de A acompanha, o publicado de A não.
        $this->saveCompany('about', ['title' => 'Versão 2']);

        $a = $this->unitOwner($this->guanambi);
        $this->assertSame('Versão 2', $this->resolver()->resolve($a, ProfileSectionType::About, ProfileState::Draft)->value['title']);
        $this->assertSame('Versão 1', $this->resolver()->resolve($a, ProfileSectionType::About, ProfileState::Published)->value['title']);
        $this->assertSame('inherit', $this->resolver()->resolve($a, ProfileSectionType::About, ProfileState::Draft)->mode);
        $this->assertSame('company', $this->resolver()->resolve($a, ProfileSectionType::About, ProfileState::Draft)->inheritedFrom);

        // A empresa publica: A herda o novo publicado, B mantém o override em rascunho e publicado.
        $this->publish($this->companyUrl('', '/publicar'), ['about'], 2, 1)->assertNoContent();

        $b = $this->unitOwner($this->caetite);
        $this->assertSame('Versão 2', $this->resolver()->resolve($a, ProfileSectionType::About, ProfileState::Published)->value['title']);
        $this->assertSame('Caetité própria', $this->resolver()->resolve($b, ProfileSectionType::About, ProfileState::Published)->value['title']);
        $this->assertSame('Caetité própria', $this->resolver()->resolve($b, ProfileSectionType::About, ProfileState::Draft)->value['title']);
        $this->assertSame('establishment', $this->resolver()->resolve($b, ProfileSectionType::About, ProfileState::Draft)->source);

        // A publicação da empresa não grava nem audita linha de unidade: só a publicação da unidade B tem evento.
        $this->assertSame(1, AuditRecord::where('entity_type', 'profile-section')->where('event', 'published')
            ->where('establishment_id', $this->caetite->id)->count());
        $this->assertSame(0, AuditRecord::where('entity_type', 'profile-section')->where('event', 'published')
            ->where('establishment_id', $this->guanambi->id)->count());
    }

    public function test_going_back_to_inherit_is_a_draft_change_until_published(): void
    {
        $this->saveCompany('about', ['title' => 'Empresa']);
        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertNoContent();
        $this->saveUnit($this->caetite, 'about', 'override', ['title' => 'Local'])->assertNoContent();
        $this->publish($this->unitUrl($this->caetite, '/publicar'), ['about'])->assertNoContent();

        $b = $this->unitOwner($this->caetite);
        $this->saveUnit($this->caetite, 'about', 'inherit')->assertNoContent();

        $this->assertSame('Empresa', $this->resolver()->resolve($b, ProfileSectionType::About, ProfileState::Draft)->value['title']);
        $this->assertSame('Local', $this->resolver()->resolve($b, ProfileSectionType::About, ProfileState::Published)->value['title']);

        // Publicar o modo inherit passa a acompanhar o publicado atual da empresa.
        $this->publish($this->unitUrl($this->caetite, '/publicar'), ['about'])->assertNoContent();
        $this->assertSame('Empresa', $this->resolver()->resolve($b, ProfileSectionType::About, ProfileState::Published)->value['title']);

        // Mudança posterior e publicada da empresa chega à unidade que voltou a herdar.
        $this->saveCompany('about', ['title' => 'Empresa 2']);
        $this->publish($this->companyUrl('', '/publicar'), ['about'], 3, 0)->assertNoContent();
        $this->assertSame('Empresa 2', $this->resolver()->resolve($b, ProfileSectionType::About, ProfileState::Published)->value['title']);
    }

    public function test_an_intentional_empty_override_stays_empty_and_disabled_only_where_allowed(): void
    {
        $this->saveCompany('about', ['title' => 'Empresa', 'description' => 'Texto']);
        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertNoContent();

        $empty = ['title' => '', 'description' => '', 'mission' => '', 'values' => [], 'differentials' => []];
        $this->saveUnit($this->guanambi, 'about', 'override', $empty)->assertNoContent();

        $resolved = $this->resolver()->resolve($this->unitOwner($this->guanambi), ProfileSectionType::About, ProfileState::Draft);
        $this->assertSame('override', $resolved->mode);
        $this->assertNull($resolved->value['title']);
        $this->assertSame([], $resolved->value['values']);

        // Desativar vale só onde o catálogo permite.
        $this->saveUnit($this->caetite, 'about', 'disabled')->assertNoContent();
        $disabled = $this->resolver()->resolve($this->unitOwner($this->caetite), ProfileSectionType::About, ProfileState::Draft);
        $this->assertSame('disabled', $disabled->mode);
        $this->assertNull($disabled->value);

        $this->saveUnit($this->caetite, 'palette', 'disabled')->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->saveUnit($this->caetite, 'contacts', 'disabled')->assertUnprocessable();
        $this->patchJson($this->companyUrl('', '/secoes/about'), ['mode' => 'inherit'])
            ->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->patchJson($this->companyUrl('', '/secoes/about'), ['mode' => 'disabled'])->assertUnprocessable();
    }

    public function test_invalid_values_are_refused_and_nothing_is_written(): void
    {
        $this->patchJson($this->companyUrl('', '/secoes/about'), ['mode' => 'override'])
            ->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->patchJson($this->companyUrl('', '/secoes/about'), ['mode' => 'override', 'value' => 'texto'])
            ->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->patchJson($this->companyUrl('', '/secoes/palette'), ['mode' => 'override', 'value' => ['primary' => 'azul']])
            ->assertUnprocessable()->assertJsonValidationErrors('value.primary');
        // Contraste insuficiente entre texto e fundo.
        $this->patchJson($this->companyUrl('', '/secoes/palette'), [
            'mode' => 'override', 'value' => ['text' => '#cccccc', 'background' => '#ffffff'],
        ])->assertUnprocessable()->assertJsonValidationErrors('value.text');
        $this->patchJson($this->companyUrl('', '/secoes/typography'), ['mode' => 'override', 'value' => ['heading' => 'comic-sans']])
            ->assertUnprocessable()->assertJsonValidationErrors('value.heading');
        $this->patchJson($this->companyUrl('', '/secoes/social_links'), ['mode' => 'override', 'value' => [
            'items' => [['network' => 'instagram', 'url' => 'https://exemplo.com/gartan']],
        ]])->assertUnprocessable()->assertJsonValidationErrors('value.items.0.url');
        $this->patchJson($this->companyUrl('', '/secoes/social_links'), ['mode' => 'override', 'value' => [
            'items' => [['network' => 'instagram', 'url' => 'http://instagram.com/gartan']],
        ]])->assertUnprocessable();
        $this->patchJson($this->companyUrl('', '/secoes/contacts'), ['mode' => 'override', 'value' => ['emails' => ['sem-arroba']]])
            ->assertUnprocessable()->assertJsonValidationErrors('value.emails.0');
        $this->patchJson($this->companyUrl('', '/secoes/seo_defaults'), ['mode' => 'override', 'value' => ['title' => str_repeat('a', 71)]])
            ->assertUnprocessable();
        $this->patchJson($this->companyUrl('', '/secoes/inexistente'), ['mode' => 'override', 'value' => []])->assertNotFound();

        $this->assertSame(0, ProfileSection::count());
    }

    public function test_text_is_plain_and_fields_missing_from_a_patch_are_kept(): void
    {
        $this->saveCompany('about', [
            'title' => '<b>Gartan</b>', 'description' => 'Moda <script>alert(1)</script>para todos',
            'mission' => 'Vestir bem', 'values' => ['Ética', '<i>Respeito</i>'],
        ]);

        $value = ProfileSection::sole()->value;
        $this->assertSame('Gartan', $value['title']);
        $this->assertSame('Moda alert(1)para todos', $value['description']);
        $this->assertStringNotContainsString('<', json_encode($value));
        $this->assertSame(['Ética', 'Respeito'], $value['values']);

        // PATCH só com a descrição mantém título, missão e valores.
        $this->saveCompany('about', ['description' => 'Nova descrição']);
        $value = ProfileSection::sole()->value;
        $this->assertSame('Nova descrição', $value['description']);
        $this->assertSame('Gartan', $value['title']);
        $this->assertSame('Vestir bem', $value['mission']);
        $this->assertSame(['Ética', 'Respeito'], $value['values']);

        // Limpar é enviar o campo vazio de propósito.
        $this->saveCompany('about', ['mission' => '']);
        $this->assertNull(ProfileSection::sole()->value['mission']);

        // Contatos são normalizados e as redes só aceitam o domínio da própria rede.
        $this->saveCompany('contacts', ['phones' => ['(77) 3333-1111'], 'emails' => ['Contato@Gartan.TEST']]);
        $contacts = ProfileSection::where('section', 'contacts')->sole()->value;
        $this->assertSame(['7733331111'], $contacts['phones']);
        $this->assertSame(['contato@gartan.test'], $contacts['emails']);

        $this->saveCompany('social_links', ['items' => [['network' => 'instagram', 'url' => 'https://www.instagram.com/gartan']]]);
        $this->assertSame('instagram', ProfileSection::where('section', 'social_links')->sole()->value['items'][0]['network']);
    }

    public function test_operational_sections_take_effect_on_save_in_both_states(): void
    {
        $this->saveCompany('contacts', ['phones' => ['7733331111']]);

        $company = $this->companyOwner();
        $draft = $this->resolver()->resolve($company, ProfileSectionType::Contacts, ProfileState::Draft);
        $published = $this->resolver()->resolve($company, ProfileSectionType::Contacts, ProfileState::Published);
        $this->assertSame(['7733331111'], $draft->value['phones']);
        $this->assertSame($draft->value, $published->value);

        // A unidade que herda vê o contato publicado na hora.
        $a = $this->resolver()->resolve($this->unitOwner($this->guanambi), ProfileSectionType::Contacts, ProfileState::Published);
        $this->assertSame(['7733331111'], $a->value['phones']);
        $this->assertSame('company', $a->inheritedFrom);

        // Publicar uma seção operacional não faz sentido.
        $this->publish($this->companyUrl('', '/publicar'), ['contacts'])->assertUnprocessable()->assertJsonValidationErrors('sections');

        // O vazio intencional na unidade não herda o contato da empresa.
        $this->saveUnit($this->caetite, 'contacts', 'override', ['phones' => [], 'whatsapps' => [], 'emails' => []])->assertNoContent();
        $b = $this->resolver()->resolve($this->unitOwner($this->caetite), ProfileSectionType::Contacts, ProfileState::Published);
        $this->assertSame([], $b->value['phones']);
        $this->assertSame('establishment', $b->source);
    }

    public function test_sections_that_depend_on_future_steps_are_declared_but_unavailable(): void
    {
        foreach (['logo', 'banners', 'gallery', 'team_display'] as $section) {
            $this->saveUnit($this->guanambi, $section, 'override', ['items' => []])->assertUnprocessable();
        }

        $this->patchJson($this->companyUrl('', '/secoes/gallery'), ['mode' => 'override', 'value' => []])->assertUnprocessable();
        $this->assertSame(0, ProfileSection::count());

        $page = $this->getJson($this->companyUrl())->assertOk();
        $keys = collect($page->json('sections'))->pluck('key')->all();
        $this->assertContains('logo', $keys);
        $this->assertNotContains('gallery', $keys);
        $this->assertFalse(collect($page->json('sections'))->firstWhere('key', 'logo')['available']);

        $unit = collect($this->getJson($this->unitUrl($this->guanambi))->assertOk()->json('sections'));
        $this->assertContains('gallery', $unit->pluck('key')->all());
        $this->assertStringContainsString('S08', $unit->firstWhere('key', 'banners')['unavailable_reason']);
    }

    public function test_update_and_publish_are_separate_permissions_on_the_owner_node(): void
    {
        $viewer = $this->managerOf($this->roupas, ['account.institutional.view']);
        $editor = $this->managerOf($this->roupas, ['account.institutional.view', 'account.institutional.update']);
        $publisher = $this->managerOf($this->roupas, [
            'account.institutional.view', 'account.institutional.update', 'account.institutional.publish',
        ]);

        $this->actingAs($viewer);
        $this->getJson($this->companyUrl())->assertOk()->assertJsonPath('canUpdate', false)->assertJsonPath('canPublish', false);
        $this->patchJson($this->companyUrl('', '/secoes/about'), ['mode' => 'override', 'value' => ['title' => 'X']])->assertForbidden();
        $this->assertSame(0, ProfileSection::count());

        $this->actingAs($editor);
        $this->saveCompany('about', ['title' => 'Editado']);
        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertForbidden();
        $this->assertNull(ProfileSection::sole()->published_mode);

        $this->actingAs($publisher);
        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertNoContent();
        $this->assertSame('override', ProfileSection::sole()->published_mode);

        // Sem a permissão de ver nem a tela abre, e a atribuição na Roupas não alcança a Express.
        $blind = $this->managerOf($this->roupas, ['account.institutional.update']);
        $this->actingAs($blind)->getJson($this->companyUrl())->assertForbidden();
        $this->actingAs($publisher)->getJson($this->companyUrl($this->express))->assertNotFound();
        $this->patchJson($this->companyUrl($this->express, '/secoes/about'), ['mode' => 'override', 'value' => ['title' => 'X']])
            ->assertNotFound();
        $this->assertSame(0, ProfileSection::where('company_id', $this->express->id)->count());
    }

    public function test_a_unit_manager_only_reaches_the_own_unit(): void
    {
        $slugs = ['account.institutional.view', 'account.institutional.update', 'account.institutional.publish'];
        $manager = $this->managerOf($this->guanambi, $slugs);

        $this->actingAs($manager);
        $this->saveUnit($this->guanambi, 'about', 'override', ['title' => 'Guanambi'])->assertNoContent();
        $this->publish($this->unitUrl($this->guanambi, '/publicar'), ['about'])->assertNoContent();

        // A outra unidade, a empresa e a Express não existem para ele, e nada é gravado nelas.
        $this->saveUnit($this->caetite, 'about', 'override', ['title' => 'Invasão'])->assertNotFound();
        $this->getJson($this->unitUrl($this->caetite))->assertNotFound();
        $this->getJson($this->companyUrl())->assertNotFound();
        $this->patchJson($this->companyUrl('', '/secoes/about'), ['mode' => 'override', 'value' => ['title' => 'X']])->assertNotFound();
        $this->assertSame(1, ProfileSection::count());
        $this->assertSame($this->guanambi->id, ProfileSection::sole()->establishment_id);

        // A lista mostra só o que ele alcança: a empresa como contexto e a própria unidade.
        $owners = $this->getJson('/conta/institucional')->assertOk()->json('owners');
        $this->assertCount(1, $owners);
        $this->assertFalse($owners[0]['covered']);
        $this->assertSame([$this->guanambi->code], array_column($owners[0]['units'], 'code'));
    }

    public function test_tenants_do_not_share_profiles_and_denied_attempts_change_nothing(): void
    {
        $this->saveCompany('about', ['title' => 'Gartan']);

        $other = $this->account('Outro Grupo');
        $foreign = $this->company($other, 'Outro', '77888999');
        $stranger = $this->administrator($other);

        $this->actingAs($stranger);
        $this->getJson($this->companyUrl())->assertNotFound();
        $this->patchJson($this->companyUrl('', '/secoes/about'), ['mode' => 'override', 'value' => ['title' => 'X']])->assertNotFound();
        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertNotFound();
        $this->getJson($this->unitUrl($this->guanambi))->assertNotFound();
        $this->assertSame('Gartan', ProfileSection::sole()->value['title']);
        $this->assertNull(ProfileSection::sole()->published_mode);

        $this->saveCompanyAs($stranger, $foreign);
        $this->assertSame(2, ProfileSection::count());
        $this->assertSame(
            1,
            ProfileSection::where('account_id', $other->id)->count(),
        );
        $this->assertSame(0, AuditRecord::where('account_id', $this->gartan->id)->where('actor_id', $stranger->id)->count());
    }

    private function saveCompanyAs(User $user, Company $company): void
    {
        $this->actingAs($user);
        $this->patchJson('/conta/empresa/'.$company->code.'/institucional/secoes/about', [
            'mode' => 'override', 'value' => ['title' => 'Outro'],
        ])->assertNoContent();
    }

    public function test_saas_inspection_in_read_only_mode_cannot_write(): void
    {
        $role = $this->role($this->gartan, 'Gestor da Roupas', [
            'account.institutional.view', 'account.institutional.update', 'account.institutional.publish',
        ]);
        $ana = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->roupas->id)]]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $start = '/saas/contas/'.$this->gartan->id.'/inspecao';

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'read-only'])->assertOk();
        $this->getJson($this->companyUrl())->assertOk();
        $this->patchJson($this->companyUrl('', '/secoes/about'), ['mode' => 'override', 'value' => ['title' => 'X']])->assertForbidden();
        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertForbidden();
        $this->assertSame(0, ProfileSection::count());
        $this->postJson('/inspecao/encerrar')->assertOk();

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'edit'])->assertOk();
        $this->saveCompany('about', ['title' => 'Inspeção']);
        $event = AuditRecord::where('entity_type', 'profile-section')->where('event', 'created')->sole();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($ana->id, $event->represented_user_id);
        $this->assertSame($this->roupas->id, $event->company_id);
    }

    public function test_the_impact_summary_is_recomputed_by_the_server(): void
    {
        $this->saveCompany('about', ['title' => 'Empresa']);
        $this->saveUnit($this->caetite, 'about', 'override', ['title' => 'Local'])->assertNoContent();
        $this->publish($this->unitUrl($this->caetite, '/publicar'), ['about'])->assertNoContent();
        $this->sede->forceFill(['active' => true])->saveQuietly();
        $this->guanambi->forceFill(['active' => false])->saveQuietly();

        // Ativas: sede (herda) e Caetité (override). Guanambi está inativa e não conta.
        $impact = $this->getJson($this->companyUrl('', '/impacto?secoes[]=about'))->assertOk()->json();
        $this->assertSame(['about'], $impact['changed']);
        $this->assertSame(1, $impact['inheriting']);
        $this->assertSame(1, $impact['overriding']);
        $this->assertStringNotContainsString('Caetité', json_encode($impact));

        // Números errados do cliente são recusados, sem publicar.
        $this->publish($this->companyUrl('', '/publicar'), ['about'], 5, 5)
            ->assertUnprocessable()->assertJsonValidationErrors('impact');
        $this->assertNull(ProfileSection::where('company_id', $this->roupas->id)->whereNull('establishment_id')->sole()->published_mode);

        $this->publish($this->companyUrl('', '/publicar'), ['about'], 1, 1)->assertNoContent();
        $this->assertSame('override', ProfileSection::whereNull('establishment_id')->where('company_id', $this->roupas->id)->sole()->published_mode);

        // Publicar de novo, sem mudança, não grava nem audita.
        $before = AuditRecord::where('event', 'published')->count();
        $this->publish($this->companyUrl('', '/publicar'), ['about'])->assertNoContent();
        $this->assertSame($before, AuditRecord::where('event', 'published')->count());
    }

    public function test_a_failed_publication_leaves_no_partial_state(): void
    {
        $this->saveCompany('about', ['title' => 'Sobre']);
        $this->saveCompany('typography', ['heading' => 'source-sans-pro']);

        // A segunda gravação de seção falha depois de a primeira já ter sido executada na transação.
        $updates = 0;
        DB::listen(function ($query) use (&$updates): void {
            if (str_starts_with($query->sql, 'update "profile_sections"') && ++$updates === 2) {
                throw new \RuntimeException('falha simulada');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->publish($this->companyUrl('', '/publicar'), ['about', 'typography']);
            $this->fail('A publicação deveria falhar.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('falha simulada', $failure->getMessage());
        }

        $this->assertSame(0, ProfileSection::whereNotNull('published_mode')->count());
        $this->assertSame(0, AuditRecord::where('event', 'published')->count());
    }

    public function test_updates_without_change_do_not_create_audit_events(): void
    {
        $this->saveCompany('about', ['title' => 'Sobre']);
        $events = AuditRecord::where('entity_type', 'profile-section')->count();

        $this->saveCompany('about', ['title' => 'Sobre']);
        $this->assertSame($events, AuditRecord::where('entity_type', 'profile-section')->count());

        // Voltar a herdar sem linha anterior não grava nada.
        $this->saveUnit($this->guanambi, 'about', 'inherit')->assertNoContent();
        $this->assertSame(1, ProfileSection::count());

        $this->saveCompany('about', ['title' => 'Sobre 2']);
        $last = AuditRecord::where('entity_type', 'profile-section')->where('event', 'updated')->latest('id')->first();
        $this->assertSame($this->roupas->id, $last->company_id);
        $this->assertNull($last->establishment_id);
    }

    public function test_the_public_address_option_starts_off_is_explicit_and_never_copied(): void
    {
        $this->assertFalse($this->guanambi->fresh()->show_public_address);
        $page = $this->getJson($this->unitUrl($this->guanambi))->assertOk();
        $page->assertJsonPath('owner.show_public_address', false);

        $this->patchJson($this->unitUrl($this->guanambi, '/endereco-publico'), ['show' => true])->assertNoContent();
        $this->assertTrue($this->guanambi->fresh()->show_public_address);
        $this->assertFalse($this->caetite->fresh()->show_public_address);

        $event = AuditRecord::where('entity_type', 'establishment')->where('entity_id', $this->guanambi->id)
            ->where('event', 'updated')->latest('id')->first();
        $this->assertArrayHasKey('show_public_address', $event->changes);
        $this->assertSame($this->guanambi->id, $event->establishment_id);

        $this->patchJson($this->unitUrl($this->guanambi, '/endereco-publico'), [])->assertUnprocessable();
        $this->patchJson($this->unitUrl($this->guanambi, '/endereco-publico'), ['show' => 'talvez'])->assertUnprocessable();

        // Sem permissão de editar, a opção não muda. O endereço não vira seção do perfil.
        $viewer = $this->managerOf($this->guanambi, ['account.institutional.view']);
        $this->actingAs($viewer)->patchJson($this->unitUrl($this->guanambi, '/endereco-publico'), ['show' => false])->assertForbidden();
        $this->assertTrue($this->guanambi->fresh()->show_public_address);
        $this->assertNotContains('address', ProfileSectionType::values());
        $this->assertSame(0, ProfileSection::count());
    }

    public function test_public_contacts_are_never_inherited_from_people_or_the_establishment(): void
    {
        $this->guanambi->forceFill([
            'phone' => '7799990000', 'whatsapp' => '7799991111', 'email' => 'operacional@gartan.test',
        ])->saveQuietly();
        $person = Person::create([
            'account_id' => $this->gartan->id, 'name' => 'Maria', 'person_type' => 'individual',
            'email' => 'pessoal@example.test', 'phone' => '71999990000',
        ]);

        foreach ([$this->companyOwner(), $this->unitOwner($this->guanambi)] as $owner) {
            foreach ([ProfileState::Draft, ProfileState::Published] as $state) {
                $contacts = $this->resolver()->resolve($owner, ProfileSectionType::Contacts, $state);
                $this->assertSame(['phones' => [], 'whatsapps' => [], 'emails' => []], $contacts->value);
                $this->assertSame('platform', $contacts->source);
            }
        }

        $this->assertStringNotContainsString('operacional@gartan.test', json_encode($this->getJson($this->unitUrl($this->guanambi))->json()));
        $this->assertStringNotContainsString($person->email, json_encode($this->getJson($this->companyUrl())->json()));
        $this->assertSame(0, ProfileSection::count());
    }

    public function test_the_resolver_loads_one_query_per_owner_and_forgets_between_requests(): void
    {
        $this->saveCompany('about', ['title' => 'Sobre']);
        $this->saveUnit($this->guanambi, 'about', 'override', ['title' => 'Local'])->assertNoContent();

        $resolver = $this->resolver();
        DB::enableQueryLog();
        $all = $resolver->resolveAll($this->unitOwner($this->guanambi), ProfileState::Draft);
        $resolver->resolveAll($this->unitOwner($this->guanambi), ProfileState::Published);
        $resolver->resolve($this->unitOwner($this->guanambi), ProfileSectionType::About, ProfileState::Draft);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'profile_sections'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $queries);
        $this->assertSame('Local', $all['about']->value['title']);
        $this->assertCount(count(ProfileSectionType::cases()), $all);

        // Instância nova por requisição: nenhum resultado fica memorizado entre elas.
        $this->assertNotSame($resolver, $this->resolver());
        $this->assertSame($this->resolver(), app(ProfileResolver::class));
    }

    public function test_the_database_refuses_incoherent_sections(): void
    {
        $base = [
            'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'section' => 'about',
            'created_at' => now(), 'updated_at' => now(),
        ];

        $invalid = [
            ['mode' => 'inherit', 'value' => null, 'establishment_id' => null],
            ['mode' => 'override', 'value' => null, 'establishment_id' => $this->sede->id],
            ['mode' => 'inherit', 'value' => '{"a":1}', 'establishment_id' => $this->sede->id],
            ['mode' => 'invalido', 'value' => null, 'establishment_id' => $this->sede->id],
            ['mode' => 'override', 'value' => '{}', 'establishment_id' => null, 'published_mode' => 'disabled'],
            ['mode' => 'override', 'value' => '{}', 'establishment_id' => null, 'published_at' => now()],
        ];

        foreach ($invalid as $row) {
            try {
                DB::table('profile_sections')->insert([...$base, ...$row]);
                $this->fail('O banco aceitou uma seção incoerente: '.json_encode($row));
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }

        DB::table('profile_sections')->insert([...$base, 'mode' => 'override', 'value' => '{}', 'establishment_id' => null]);

        $this->expectException(QueryException::class);
        DB::table('profile_sections')->insert([...$base, 'mode' => 'override', 'value' => '{}', 'establishment_id' => null]);
    }

    public function test_a_section_cannot_point_to_an_establishment_of_another_company(): void
    {
        $foreignUnit = $this->express->primaryEstablishment()->sole();

        $this->expectException(QueryException::class);
        DB::table('profile_sections')->insert([
            'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'establishment_id' => $foreignUnit->id,
            'section' => 'about', 'mode' => 'override', 'value' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_profile_is_read_only_through_the_resolver_outside_the_allowed_files(): void
    {
        $allowed = [
            'app/Domains/Institucional/Application/Context/ProfileResolver.php',
            'app/Domains/Institucional/Domain/Models/ProfileSection.php',
            'app/Domains/Institucional/Application/Actions/Profile/',
            'app/Domains/Institucional/Application/Queries/Profile/ProfileImpactQuery.php',
            'app/Domains/Institucional/Application/Queries/Profile/FindProfileSectionQuery.php',
            'app/Domains/Colaboradores/Application/Queries/Violations/ListInstitutionalViolationsQuery.php',
        ];

        $violations = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'))) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace(base_path().'/', '', $file->getPathname());
            $source = file_get_contents($file->getPathname());

            if (! preg_match('/profile_sections|ProfileSection::/', $source)) {
                continue;
            }

            foreach ($allowed as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    continue 2;
                }
            }

            $violations[] = $path;
        }

        $this->assertSame([], $violations, 'Leitura de profile_sections fora do ProfileResolver.');
    }

    /** @param array<string,mixed> $input */
    private function createCategory(User $actor, Client $account, array $input)
    {
        $this->actingAs($actor);
        $this->enterAccount($actor, $account->id);

        return app(CreatePublicCategoryAction::class)->execute(
            CreatePublicCategoryData::fromInput($input, $account->id),
            $account,
        );
    }

    /** @param array<string,mixed> $input */
    private function updateCategory(User $actor, Client $account, int $id, array $input, ?Client $dataAccount = null)
    {
        $this->actingAs($actor);
        $this->enterAccount($actor, $account->id);

        return app(UpdatePublicCategoryAction::class)->execute(
            UpdatePublicCategoryData::fromInput(
                $input,
                ($dataAccount ?? $account)->id,
                $id,
            ),
            $id,
            $account,
        );
    }

    public function test_public_categories_belong_to_the_account_and_are_audited(): void
    {
        $other = $this->account('Outro Grupo');
        $this->company($other, 'Outro', '77888999');
        $stranger = $this->administrator($other);

        $category = $this->createCategory($this->owner, $this->gartan, ['name' => '  Moda   Feminina ', 'seo_title' => '<b>Moda</b>']);

        $this->assertSame('moda-feminina', $category->slug);
        $this->assertSame('Moda Feminina', $category->name);
        $this->assertSame('Moda', $category->seo_title);
        $this->assertTrue($category->active);
        $this->assertSame(1, AuditRecord::where('entity_type', 'public-category')->where('event', 'created')->count());
        $this->assertFalse(Schema::hasColumn('public_categories', 'company_id'));

        // Nome e slug repetidos na mesma conta são recusados, e em outra conta valem.
        foreach (['name' => ['name' => 'moda feminina', 'slug' => 'outro-endereco'], 'slug' => ['name' => 'Outra', 'slug' => 'moda-feminina']] as $field => $input) {
            try {
                $this->createCategory($this->owner, $this->gartan, $input);
                $this->fail('Deveria recusar '.$field);
            } catch (ValidationException $failure) {
                $this->assertArrayHasKey($field, $failure->errors());
            }
        }

        $foreign = $this->createCategory($stranger, $other, ['name' => 'Moda Feminina']);
        $this->assertSame('moda-feminina', $foreign->slug);
        $this->assertSame($other->id, $foreign->account_id);

        // Edição e inativação, sem exclusão. Sem mudança não gera evento.
        $input = ['name' => 'Moda Feminina', 'slug' => 'moda-feminina', 'active' => false, 'sort_order' => 2];
        $this->updateCategory($this->owner, $this->gartan, $category->id, $input);
        $this->assertFalse($category->fresh()->active);
        $this->assertSame(1, AuditRecord::where('entity_type', 'public-category')->where('event', 'updated')->count());
        $this->updateCategory($this->owner, $this->gartan, $category->id, $input);
        $this->assertSame(1, AuditRecord::where('entity_type', 'public-category')->where('event', 'updated')->count());
        $this->assertSame(0, AuditRecord::where('entity_type', 'public-category')->where('event', 'deleted')->count());
    }

    public function test_public_category_actions_refuse_every_access_outside_the_own_account(): void
    {
        $other = $this->account('Outro Grupo');
        $this->company($other, 'Outro', '77888999');
        $stranger = $this->administrator($other);
        $foreign = $this->createCategory($stranger, $other, ['name' => 'Alheia']);
        $own = $this->createCategory($this->owner, $this->gartan, ['name' => 'Própria']);

        // Categoria de outra conta responde como inexistente e nada muda.
        try {
            $this->updateCategory($this->owner, $this->gartan, $foreign->id, ['name' => 'Invadida', 'slug' => 'invadida', 'active' => true]);
            $this->fail('Deveria recusar a categoria de outra conta.');
        } catch (ModelNotFoundException) {
            $this->assertSame('Alheia', $foreign->fresh()->name);
        }

        // Conta informada diferente da conta em que o usuário está, ou nenhuma conta aberta: recusado.
        $this->actingAs($this->owner);
        $this->app->forgetScopedInstances();
        $action = app(CreatePublicCategoryAction::class);
        $data = CreatePublicCategoryData::fromInput(['name' => 'Sem conta'], $this->gartan->id);

        $this->expectCategoryRefusal(fn () => $action->execute($data, $this->gartan), AuthorizationException::class);

        $this->enterAccount($this->owner, $this->gartan->id);
        $action = app(CreatePublicCategoryAction::class);
        $this->expectCategoryRefusal(fn () => $action->execute($data, $other), AuthorizationException::class);

        $foreignData = CreatePublicCategoryData::fromInput(['name' => 'Mista'], $other->id);
        $this->expectCategoryRefusal(fn () => $action->execute($foreignData, $this->gartan), ResourceNotAvailable::class);

        $this->assertSame(['Alheia', 'Própria'], PublicCategory::orderBy('name')->pluck('name')->all());
        $this->assertSame($own->account_id, $this->gartan->id);
    }

    /** @param class-string<\Throwable> $exception */
    private function expectCategoryRefusal(\Closure $call, string $exception): void
    {
        try {
            $call();
            $this->fail('Deveria lançar '.$exception);
        } catch (\Throwable $failure) {
            $this->assertInstanceOf($exception, $failure);
        }
    }

    public function test_public_categories_are_not_reachable_from_routes_controllers_or_other_domains(): void
    {
        $consumers = [];

        foreach (['app', 'routes'] as $directory) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($directory))) as $file) {
                $path = str_replace(base_path().'/', '', $file->getPathname());

                if ($file->getExtension() !== 'php' || str_starts_with($path, 'app/Domains/Institucional/')) {
                    continue;
                }

                if (preg_match('/PublicCategory|public_categories|Actions.Categories/', (string) file_get_contents($file->getPathname()))) {
                    $consumers[] = $path;
                }
            }
        }

        // Só a migration, a configuração de auditoria e o rótulo de permissão podem citar a categoria, nunca uma rota.
        $this->assertSame([], array_values(array_filter($consumers, fn (string $path) => str_starts_with($path, 'routes/')
            || str_contains($path, 'Controller'))));
    }
}

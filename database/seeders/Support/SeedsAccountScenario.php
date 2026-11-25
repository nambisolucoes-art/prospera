<?php

namespace Database\Seeders\Support;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Buscas por chave natural e execução como operador real. As Actions rodam com a mesma autorização,
 * validação e auditoria de uma requisição: o autor dos eventos é a identidade indicada aqui.
 *
 * Resolva cada Action com app() depois de actAs...(), nunca por injeção no run(): várias guardam serviços
 * scoped (contexto da conta, permissões) no construtor, e trocar de operador descarta essas instâncias.
 */
trait SeedsAccountScenario
{
    protected function account(): Client
    {
        $document = FiscalDocuments::cnpj(SeedCatalog::ACCOUNT_ROOT, '0001');

        return $this->found(Client::where('document', $document)->first(), 'a conta', 'TenantAccountSeeder');
    }

    protected function user(string $email): User
    {
        return $this->found(User::where('email', $email)->first(), 'o usuário '.$email, 'o seeder de usuários');
    }

    protected function company(string $root): Company
    {
        $company = Company::where('account_id', $this->account()->id)
            ->where('tax_root', $root)
            ->first();

        return $this->found($company, 'a empresa de raiz '.$root, 'CompanySeeder');
    }

    protected function individualCompany(string $cpf): Company
    {
        $company = Company::where('account_id', $this->account()->id)
            ->where('individual_document', $cpf)
            ->first();

        return $this->found($company, 'a empresa do CPF informado', 'CompanySeeder');
    }

    /**
     * Empresa pela chave do catálogo: raiz do CNPJ (8 dígitos) ou base do CPF (9 dígitos) da empresa individual.
     */
    protected function companyByKey(string $key): Company
    {
        if (strlen($key) === 9) {
            return $this->individualCompany(FiscalDocuments::cpf($key));
        }

        return $this->company($key);
    }

    protected function establishment(string $code): Establishment
    {
        $establishment = Establishment::where('account_id', $this->account()->id)
            ->where('code', $code)
            ->first();

        return $this->found($establishment, 'o estabelecimento '.$code, 'EstablishmentSeeder');
    }

    protected function primaryEstablishment(string $root): Establishment
    {
        $establishment = $this->company($root)->primaryEstablishment()->first();

        return $this->found($establishment, 'a unidade principal da raiz '.$root, 'CompanySeeder');
    }

    protected function role(string $name): Role
    {
        $role = Role::where('account_id', $this->account()->id)
            ->where('name', $name)
            ->first();

        return $this->found($role, 'o cargo '.$name, 'RoleSeeder');
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    protected function tagIds(string $module, array $names): array
    {
        $found = Tag::where('account_id', $this->account()->id)
            ->where('module', $module)
            ->whereIn('name', $names)
            ->pluck('id')
            ->all();

        $ids = array_values(array_map(fn ($id): int => (int) $id, $found));

        if (count($ids) !== count($names)) {
            throw new RuntimeException('Etiquetas ausentes em '.$module.'. Execute antes o CommercialTagSeeder.');
        }

        return $ids;
    }

    protected function membership(User $user): AccountMembership
    {
        $membership = AccountMembership::where('account_id', $this->account()->id)
            ->where('user_id', $user->id)
            ->first();

        return $this->found($membership, 'o vínculo de '.$user->email, 'AccountUserSeeder');
    }

    /** Nó de acesso de uma concessão: conta, empresa (raiz ou CPF) ou estabelecimento (código). */
    protected function node(string $scope, ?string $company = null, ?string $establishment = null): AccessNode
    {
        $accountId = $this->account()->id;

        if ($scope === 'establishment' && $establishment !== null) {
            $unit = $this->establishment($establishment);

            return AccessNode::establishment($accountId, $unit->company_id, $unit->id);
        }

        if ($scope === 'company' && $company !== null) {
            return AccessNode::company($accountId, $this->company($company)->id);
        }

        return AccessNode::tenant($accountId);
    }

    protected function actAsPlatformAdministrator(): User
    {
        $administrator = $this->user(SeedCatalog::PLATFORM_ADMINISTRATOR);

        $this->actAs($administrator);

        return $administrator;
    }

    /** Opera dentro da conta como o administrador, com o contexto validado pelo vínculo ativo. */
    protected function actAsAccountAdministrator(): User
    {
        $administrator = $this->user(SeedCatalog::ACCOUNT_ADMINISTRATOR);

        $this->actAs($administrator);
        app(AccountContext::class)->enter($administrator, $this->account()->code);

        return $administrator;
    }

    protected function stopActing(): void
    {
        app()->forgetScopedInstances();
        Auth::forgetGuards();
    }

    /** Cada operação começa sem contexto, permissões ou auditoria da anterior, como uma requisição nova. */
    private function actAs(User $user): void
    {
        app()->forgetScopedInstances();
        Auth::guard('web')->setUser($user);
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel|null  $model
     * @return TModel
     */
    private function found(?Model $model, string $description, string $seeder): Model
    {
        if ($model === null) {
            throw new RuntimeException('Não foi encontrado '.$description.'. Execute antes o '.$seeder.'.');
        }

        return $model;
    }
}
